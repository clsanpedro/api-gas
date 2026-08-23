<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/api.php';
require_once __DIR__ . '/../app/market-data.php';

header('Content-Type: text/plain; charset=utf-8');


/*
 * ============================================================
 * CONFIGURACIÓN
 * ============================================================
 *
 * Consideramos que el histórico de carburantes está actualizado
 * si el último snapshot tiene una antigüedad máxima de 1 día.
 *
 * Los datos de mercado tienen mayor margen porque EIA y BCE
 * no publican durante fines de semana y determinados festivos.
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
 * Estado específico de las series de mercado.
 */

$brentOk = false;

$eurUsdOk = false;


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

$pdoMysqlOk =
  extension_loaded(
    'pdo_mysql'
  );

echo 'PDO MYSQL: '
  . ($pdoMysqlOk ? 'OK' : 'ERROR')
  . PHP_EOL;

if (!$pdoMysqlOk) {
  $status = 'ERROR';
}


$curlOk =
  extension_loaded(
    'curl'
  );

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

  $pdo->query(
    'SELECT 1'
  );

  echo 'MYSQL: OK'
    . PHP_EOL;
} catch (Throwable $e) {

  $status = 'ERROR';

  echo 'MYSQL: ERROR'
    . PHP_EOL;
}


/*
 * ============================================================
 * 4. API EXTERNA DE CARBURANTES
 * ============================================================
 */

try {

  $data =
    fetchFuelData();


  if (
    isset(
      $data['ListaEESSPrecio']
    )
    && is_array(
      $data['ListaEESSPrecio']
    )
  ) {

    echo 'API: OK'
      . PHP_EOL;

    echo 'API FECHA: '
      . (
        $data['Fecha']
        ?? 'desconocida'
      )
      . PHP_EOL;

    echo 'API ESTACIONES: '
      . count(
        $data['ListaEESSPrecio']
      )
      . PHP_EOL;
  } else {

    $status = 'ERROR';

    echo 'API: ERROR'
      . PHP_EOL;
  }
} catch (Throwable $e) {

  $status = 'ERROR';

  echo 'API: ERROR'
    . PHP_EOL;
}


/*
 * ============================================================
 * 5. DATOS LOCALES DE CARBURANTES
 * ============================================================
 */

try {

  /*
   * Número total de estaciones almacenadas.
   */

  $stationCount =
    (int) $pdo
      ->query(
        'SELECT COUNT(*) FROM stations'
      )
      ->fetchColumn();


  echo 'ESTACIONES: '
    . $stationCount
    . PHP_EOL;


  /*
   * ========================================================
   * ÚLTIMO SNAPSHOT
   * ========================================================
   */

  $stmt =
    $pdo->query(
      'SELECT
          id,
          api_date,
          fetched_at
       FROM snapshots
       ORDER BY api_date DESC
       LIMIT 1'
    );


  $snapshot =
    $stmt->fetch();


  if ($snapshot === false) {

    echo 'ULTIMO SNAPSHOT: ninguno'
      . PHP_EOL;

    echo 'HISTORICO: SIN DATOS'
      . PHP_EOL;

    $status = 'ERROR';
  } else {

    echo 'ULTIMO SNAPSHOT: '
      . $snapshot['api_date']
      . PHP_EOL;


    echo 'GUARDADO EN: '
      . $snapshot['fetched_at']
      . PHP_EOL;


    /*
     * ====================================================
     * 6. ANTIGÜEDAD DEL SNAPSHOT
     * ====================================================
     */

    $snapshotDate =
      new DateTime(
        $snapshot['api_date']
      );


    $now =
      new DateTime();


    $ageSeconds =
      $now->getTimestamp()
      - $snapshotDate->getTimestamp();


    $ageSeconds =
      max(
        0,
        $ageSeconds
      );


    $ageHours =
      round(
        $ageSeconds / 3600,
        1
      );


    $ageDays =
      (int) floor(
        $ageSeconds / 86400
      );


    echo 'ANTIGUEDAD SNAPSHOT: '
      . $ageHours
      . ' hora(s)'
      . PHP_EOL;


    if (
      $ageDays
      <= MAX_SNAPSHOT_AGE_DAYS
    ) {

      echo 'HISTORICO: OK'
        . PHP_EOL;
    } else {

      echo 'HISTORICO: DESACTUALIZADO'
        . PHP_EOL;

      $status = 'ERROR';
    }


    /*
     * ====================================================
     * 7. PRECIOS DEL ÚLTIMO SNAPSHOT
     * ====================================================
     */

    $stmtPrices =
      $pdo->prepare(
        'SELECT COUNT(*)
         FROM prices
         WHERE snapshot_id = :snapshot_id'
      );


    $stmtPrices->execute([
      'snapshot_id' =>
      $snapshot['id'],
    ]);


    $priceCount =
      (int) $stmtPrices
        ->fetchColumn();


    echo 'PRECIOS ULTIMO SNAPSHOT: '
      . $priceCount
      . PHP_EOL;


    if ($priceCount === 0) {

      echo 'DATOS SNAPSHOT: ERROR'
        . PHP_EOL;

      $status = 'ERROR';
    } else {

      echo 'DATOS SNAPSHOT: OK'
        . PHP_EOL;
    }
  }
} catch (Throwable $e) {

  /*
   * No mostramos detalles técnicos.
   */

  $status = 'ERROR';

  echo 'DATOS: ERROR'
    . PHP_EOL;
}


/*
 * ============================================================
 * 8. DATOS DE MERCADO - BRENT
 * ============================================================
 */

try {

  $brent =
    getLatestMarketPrice(
      $pdo,
      'RBRTE'
    );


  if ($brent === null) {

    echo 'BRENT: SIN DATOS'
      . PHP_EOL;

    $status = 'ERROR';
  } else {

    echo 'BRENT: OK'
      . PHP_EOL;


    echo 'BRENT FECHA: '
      . $brent['price_date']
      . PHP_EOL;


    echo 'BRENT PRECIO: '
      . number_format(
        $brent['value'],
        6,
        '.',
        ''
      )
      . ' '
      . $brent['unit']
      . PHP_EOL;


    echo 'BRENT FUENTE: '
      . $brent['source']
      . PHP_EOL;


    echo 'BRENT ACTUALIZADO EN: '
      . $brent['updated_at']
      . PHP_EOL;


    $brentDate =
      new DateTime(
        $brent['price_date']
      );


    $now =
      new DateTime();


    $brentAgeSeconds =
      $now->getTimestamp()
      - $brentDate->getTimestamp();


    $brentAgeSeconds =
      max(
        0,
        $brentAgeSeconds
      );


    $brentAgeHours =
      round(
        $brentAgeSeconds / 3600,
        1
      );


    $brentAgeDays =
      (int) floor(
        $brentAgeSeconds / 86400
      );


    echo 'ANTIGUEDAD BRENT: '
      . $brentAgeHours
      . ' hora(s)'
      . PHP_EOL;


    if (
      $brentAgeDays
      <= MAX_MARKET_AGE_DAYS
    ) {

      echo 'BRENT DATOS: OK'
        . PHP_EOL;

      $brentOk = true;
    } else {

      echo 'BRENT DATOS: DESACTUALIZADOS'
        . PHP_EOL;

      $status = 'ERROR';
    }
  }
} catch (Throwable $e) {

  echo 'BRENT: ERROR'
    . PHP_EOL;

  $status = 'ERROR';
}


/*
 * ============================================================
 * 9. DATOS DE MERCADO - EUR/USD
 * ============================================================
 */

try {

  $eurUsd =
    getLatestMarketPrice(
      $pdo,
      'EURUSD'
    );


  if ($eurUsd === null) {

    echo 'EURUSD: SIN DATOS'
      . PHP_EOL;

    $status = 'ERROR';
  } else {

    echo 'EURUSD: OK'
      . PHP_EOL;


    echo 'EURUSD FECHA: '
      . $eurUsd['price_date']
      . PHP_EOL;


    echo 'EURUSD VALOR: '
      . number_format(
        $eurUsd['value'],
        6,
        '.',
        ''
      )
      . ' '
      . $eurUsd['unit']
      . PHP_EOL;


    echo 'EURUSD FUENTE: '
      . $eurUsd['source']
      . PHP_EOL;


    echo 'EURUSD ACTUALIZADO EN: '
      . $eurUsd['updated_at']
      . PHP_EOL;


    $eurUsdDate =
      new DateTime(
        $eurUsd['price_date']
      );


    $now =
      new DateTime();


    $eurUsdAgeSeconds =
      $now->getTimestamp()
      - $eurUsdDate->getTimestamp();


    $eurUsdAgeSeconds =
      max(
        0,
        $eurUsdAgeSeconds
      );


    $eurUsdAgeHours =
      round(
        $eurUsdAgeSeconds / 3600,
        1
      );


    $eurUsdAgeDays =
      (int) floor(
        $eurUsdAgeSeconds / 86400
      );


    echo 'ANTIGUEDAD EURUSD: '
      . $eurUsdAgeHours
      . ' hora(s)'
      . PHP_EOL;


    if (
      $eurUsdAgeDays
      <= MAX_MARKET_AGE_DAYS
    ) {

      echo 'EURUSD DATOS: OK'
        . PHP_EOL;

      $eurUsdOk = true;
    } else {

      echo 'EURUSD DATOS: DESACTUALIZADOS'
        . PHP_EOL;

      $status = 'ERROR';
    }
  }
} catch (Throwable $e) {

  echo 'EURUSD: ERROR'
    . PHP_EOL;

  $status = 'ERROR';
}


/*
 * ============================================================
 * 10. ESTADO GENERAL DEL MERCADO
 * ============================================================
 */

if (
  $brentOk
  && $eurUsdOk
) {

  echo 'MERCADO: OK'
    . PHP_EOL;
} else {

  echo 'MERCADO: ERROR'
    . PHP_EOL;

  $status = 'ERROR';
}


/*
 * ============================================================
 * 11. ESTADO GENERAL
 * ============================================================
 */

echo PHP_EOL;


echo 'ESTADO GENERAL: '
  . $status
  . PHP_EOL;


/*
 * ============================================================
 * 12. CÓDIGO HTTP
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
