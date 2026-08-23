<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/api.php';

header('Content-Type: text/plain; charset=utf-8');


/*
 * ============================================================
 * CONFIGURACIÓN
 * ============================================================
 *
 * Consideramos que el histórico está actualizado si el último
 * snapshot tiene una antigüedad máxima de 1 día.
 */

const MAX_SNAPSHOT_AGE_DAYS = 1;
const MAX_MARKET_AGE_DAYS = 7;


/*
 * Estado general.
 *
 * Si cualquiera de las comprobaciones importantes falla,
 * terminaremos devolviendo HTTP 503.
 */

$status = 'OK';


/*
 * ============================================================
 * 1. APLICACIÓN / PHP
 * ============================================================
 */

echo 'APP: OK' . PHP_EOL;

echo 'PHP: '
  . PHP_VERSION
  . PHP_EOL;


/*
 * ============================================================
 * 2. EXTENSIONES PHP
 * ============================================================
 */

$pdoMysqlOk = extension_loaded('pdo_mysql');

echo 'PDO MYSQL: '
  . ($pdoMysqlOk ? 'OK' : 'ERROR')
  . PHP_EOL;

if (!$pdoMysqlOk) {
  $status = 'ERROR';
}


$curlOk = extension_loaded('curl');

echo 'CURL: '
  . ($curlOk ? 'OK' : 'ERROR')
  . PHP_EOL;

if (!$curlOk) {
  $status = 'ERROR';
}


/*
 * ============================================================
 * 3. CONEXIÓN MYSQL
 * ============================================================
 */

try {

  $pdo->query('SELECT 1');

  echo 'MYSQL: OK' . PHP_EOL;
} catch (Throwable $e) {

  $status = 'ERROR';

  echo 'MYSQL: ERROR' . PHP_EOL;
}


/*
 * ============================================================
 * 4. API EXTERNA
 * ============================================================
 */

try {

  $data = fetchFuelData();

  if (
    isset($data['ListaEESSPrecio'])
    && is_array($data['ListaEESSPrecio'])
  ) {

    echo 'API: OK' . PHP_EOL;

    echo 'API FECHA: '
      . ($data['Fecha'] ?? 'desconocida')
      . PHP_EOL;

    echo 'API ESTACIONES: '
      . count($data['ListaEESSPrecio'])
      . PHP_EOL;
  } else {

    $status = 'ERROR';

    echo 'API: ERROR' . PHP_EOL;
  }
} catch (Throwable $e) {

  $status = 'ERROR';

  echo 'API: ERROR' . PHP_EOL;
}


/*
 * ============================================================
 * 5. DATOS LOCALES
 * ============================================================
 */

try {

  /*
     * Número total de estaciones almacenadas.
     */

  $stationCount = (int) $pdo
    ->query('SELECT COUNT(*) FROM stations')
    ->fetchColumn();

  echo 'ESTACIONES: '
    . $stationCount
    . PHP_EOL;


  /*
     * ========================================================
     * ÚLTIMO SNAPSHOT
     * ========================================================
     */

  $stmt = $pdo->query(
    'SELECT id, api_date, fetched_at
         FROM snapshots
         ORDER BY api_date DESC
         LIMIT 1'
  );

  $snapshot = $stmt->fetch();


  if ($snapshot === false) {

    /*
         * Todavía no existe histórico.
         */

    echo 'ULTIMO SNAPSHOT: ninguno' . PHP_EOL;
    echo 'HISTORICO: SIN DATOS' . PHP_EOL;

    $status = 'ERROR';
  } else {

    /*
         * Mostramos la fecha del último snapshot.
         */

    echo 'ULTIMO SNAPSHOT: '
      . $snapshot['api_date']
      . PHP_EOL;


    /*
         * Mostramos también cuándo fue almacenado.
         */

    echo 'GUARDADO EN: '
      . $snapshot['fetched_at']
      . PHP_EOL;


    /*
         * ====================================================
         * 6. ANTIGÜEDAD DEL SNAPSHOT
         * ====================================================
         */

    $snapshotDate = new DateTime(
      $snapshot['api_date']
    );

    $now = new DateTime();

    $ageSeconds =
      $now->getTimestamp()
      - $snapshotDate->getTimestamp();

    /*
         * Por seguridad, si existiera alguna diferencia de
         * reloj y la fecha apareciera ligeramente en el futuro,
         * no queremos obtener una antigüedad negativa.
         */

    $ageSeconds = max(
      0,
      $ageSeconds
    );

    $ageHours = round(
      $ageSeconds / 3600,
      1
    );

    $ageDays = (int) floor(
      $ageSeconds / 86400
    );


    echo 'ANTIGUEDAD SNAPSHOT: '
      . $ageHours
      . ' hora(s)'
      . PHP_EOL;


    /*
         * Como importamos una vez al día, permitimos hasta
         * prácticamente 48 horas antes de considerar que existe
         * un problema.
         *
         * MAX_SNAPSHOT_AGE_DAYS = 1 significa que:
         *
         * 0 días completos → OK
         * 1 día completo    → OK
         * 2+ días           → desactualizado
         */

    if ($ageDays <= MAX_SNAPSHOT_AGE_DAYS) {

      echo 'HISTORICO: OK' . PHP_EOL;
    } else {

      echo 'HISTORICO: DESACTUALIZADO' . PHP_EOL;

      $status = 'ERROR';
    }


    /*
         * ====================================================
         * 7. PRECIOS DEL ÚLTIMO SNAPSHOT
         * ====================================================
         */

    $stmtPrices = $pdo->prepare(
      'SELECT COUNT(*)
             FROM prices
             WHERE snapshot_id = :snapshot_id'
    );

    $stmtPrices->execute([
      'snapshot_id' => $snapshot['id'],
    ]);

    $priceCount = (int) $stmtPrices->fetchColumn();


    echo 'PRECIOS ULTIMO SNAPSHOT: '
      . $priceCount
      . PHP_EOL;


    /*
         * Un snapshot sin precios no es válido.
         */

    if ($priceCount === 0) {

      echo 'DATOS SNAPSHOT: ERROR' . PHP_EOL;

      $status = 'ERROR';
    } else {

      echo 'DATOS SNAPSHOT: OK' . PHP_EOL;
    }
  }
} catch (Throwable $e) {

  /*
     * No mostramos $e->getMessage().
     *
     * health.php es público y no queremos revelar
     * SQL, rutas internas ni información del servidor.
     */

  $status = 'ERROR';

  echo 'DATOS: ERROR' . PHP_EOL;
}

/*
 * ============================================================
 * 8. DATOS DE MERCADO / BRENT
 * ============================================================
 */

try {

  /*
   * Obtenemos el último precio disponible
   * de Brent Europe almacenado localmente.
   */

  $stmtMarket = $pdo->prepare(
    'SELECT
        price_date,
        value,
        unit,
        source,
        updated_at
     FROM market_prices
     WHERE series_code = :series_code
     ORDER BY price_date DESC
     LIMIT 1'
  );

  $stmtMarket->execute([
    'series_code' => 'RBRTE',
  ]);

  $marketPrice = $stmtMarket->fetch();


  if ($marketPrice === false) {

    echo 'BRENT: SIN DATOS' . PHP_EOL;

    $status = 'ERROR';
  } else {

    echo 'BRENT: OK' . PHP_EOL;

    echo 'BRENT FECHA: '
      . $marketPrice['price_date']
      . PHP_EOL;

    echo 'BRENT PRECIO: '
      . $marketPrice['value']
      . ' '
      . $marketPrice['unit']
      . PHP_EOL;

    echo 'BRENT FUENTE: '
      . $marketPrice['source']
      . PHP_EOL;

    echo 'BRENT ACTUALIZADO EN: '
      . $marketPrice['updated_at']
      . PHP_EOL;


    /*
     * ========================================================
     * ANTIGÜEDAD DEL DATO BRENT
     * ========================================================
     *
     * EIA no publica necesariamente datos todos los días.
     * Fines de semana y festivos generan huecos naturales.
     */

    $marketDate = new DateTime(
      $marketPrice['price_date']
    );

    $now = new DateTime();

    $marketAgeSeconds =
      $now->getTimestamp()
      - $marketDate->getTimestamp();

    $marketAgeSeconds = max(
      0,
      $marketAgeSeconds
    );

    $marketAgeHours = round(
      $marketAgeSeconds / 3600,
      1
    );

    $marketAgeDays = (int) floor(
      $marketAgeSeconds / 86400
    );


    echo 'ANTIGUEDAD BRENT: '
      . $marketAgeHours
      . ' hora(s)'
      . PHP_EOL;


    if (
      $marketAgeDays
      <= MAX_MARKET_AGE_DAYS
    ) {

      echo 'MERCADO: OK' . PHP_EOL;
    } else {

      echo 'MERCADO: DESACTUALIZADO'
        . PHP_EOL;

      $status = 'ERROR';
    }
  }
} catch (Throwable $e) {

  /*
   * No mostramos detalles técnicos.
   */

  echo 'MERCADO: ERROR' . PHP_EOL;

  $status = 'ERROR';
}


/*
 * ============================================================
 * 9. ESTADO GENERAL
 * ============================================================
 */

echo PHP_EOL;

echo 'ESTADO GENERAL: '
  . $status
  . PHP_EOL;


/*
 * ============================================================
 * 10. CÓDIGO HTTP
 * ============================================================
 *
 * 200 → todo correcto
 * 503 → algún componente importante está fallando
 */

if ($status !== 'OK') {

  http_response_code(503);
} else {

  http_response_code(200);
}
