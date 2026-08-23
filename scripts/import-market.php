<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/market-api.php';


/**
 * Guarda una línea en el log de importación
 * de datos de mercado.
 */
function logMarketImport(
  string $message
): void {

  $logFile =
    __DIR__
    . '/../storage/market-import.log';

  $line = sprintf(
    "[%s] %s%s",
    date('Y-m-d H:i:s'),
    $message,
    PHP_EOL
  );

  error_log(
    $line,
    3,
    $logFile
  );
}


/*
 * ============================================================
 * 1. CARGAR CONFIGURACIÓN
 * ============================================================
 */

$configFile =
  __DIR__
  . '/../app/market-config.php';


if (!is_file($configFile)) {
  throw new RuntimeException(
    'No existe app/market-config.php.'
  );
}


$config = require $configFile;


if (
  !is_array($config)
  || !isset($config['eia_api_key'])
  || !is_string($config['eia_api_key'])
  || trim($config['eia_api_key']) === ''
) {
  throw new RuntimeException(
    'La API key de EIA no está configurada correctamente.'
  );
}


$apiKey =
  trim(
    $config['eia_api_key']
  );


/*
 * ============================================================
 * 2. INICIO DE IMPORTACIÓN
 * ============================================================
 */

$startTime =
  microtime(true);


try {

  /*
   * ============================================================
   * 3. CONSULTAR EIA - BRENT
   * ============================================================
   */

  $brentPrices =
    fetchBrentPrices(
      $apiKey,
      15
    );


  if ($brentPrices === []) {
    throw new RuntimeException(
      'EIA no ha devuelto precios Brent para importar.'
    );
  }


  /*
   * ============================================================
   * 4. CONSULTAR BCE - EUR/USD
   * ============================================================
   */

  $eurUsdPrices =
    fetchEurUsdPrices(
      15
    );


  if ($eurUsdPrices === []) {
    throw new RuntimeException(
      'El BCE no ha devuelto tipos EUR/USD para importar.'
    );
  }


  /*
   * ============================================================
   * 5. PREPARAR UPSERT
   * ============================================================
   */

  $sql = '
    INSERT INTO market_prices (
      price_date,
      series_code,
      value,
      unit,
      source
    )
    VALUES (
      :price_date,
      :series_code,
      :value,
      :unit,
      :source
    )
    ON DUPLICATE KEY UPDATE
      value = VALUES(value),
      unit = VALUES(unit),
      source = VALUES(source)
  ';


  $stmt =
    $pdo->prepare($sql);


  /*
   * ============================================================
   * 6. INICIAR TRANSACCIÓN
   * ============================================================
   */

  $pdo->beginTransaction();


  $brentCount = 0;
  $eurUsdCount = 0;


  /*
   * ============================================================
   * 7. GUARDAR BRENT
   * ============================================================
   */

  foreach ($brentPrices as $price) {

    if (
      !isset(
        $price['price_date'],
        $price['series_code'],
        $price['value'],
        $price['unit'],
        $price['source']
      )
    ) {
      continue;
    }


    if (
      !is_numeric(
        $price['value']
      )
    ) {
      continue;
    }


    $value =
      (float) $price['value'];


    if ($value <= 0) {
      continue;
    }


    $stmt->execute([
      'price_date' =>
      $price['price_date'],

      'series_code' =>
      $price['series_code'],

      'value' =>
      $value,

      'unit' =>
      $price['unit'],

      'source' =>
      $price['source'],
    ]);


    $brentCount++;
  }


  /*
   * ============================================================
   * 8. GUARDAR EUR/USD
   * ============================================================
   */

  foreach ($eurUsdPrices as $price) {

    if (
      !isset(
        $price['price_date'],
        $price['series_code'],
        $price['value'],
        $price['unit'],
        $price['source']
      )
    ) {
      continue;
    }


    if (
      !is_numeric(
        $price['value']
      )
    ) {
      continue;
    }


    $value =
      (float) $price['value'];


    if ($value <= 0) {
      continue;
    }


    $stmt->execute([
      'price_date' =>
      $price['price_date'],

      'series_code' =>
      $price['series_code'],

      'value' =>
      $value,

      'unit' =>
      $price['unit'],

      'source' =>
      $price['source'],
    ]);


    $eurUsdCount++;
  }


  /*
   * ============================================================
   * 9. VALIDAR RESULTADOS
   * ============================================================
   */

  if ($brentCount === 0) {
    throw new RuntimeException(
      'No se ha podido guardar ningún precio Brent válido.'
    );
  }


  if ($eurUsdCount === 0) {
    throw new RuntimeException(
      'No se ha podido guardar ningún tipo EUR/USD válido.'
    );
  }


  /*
   * ============================================================
   * 10. CONFIRMAR TRANSACCIÓN
   * ============================================================
   */

  $pdo->commit();


  /*
   * ============================================================
   * 11. CALCULAR DURACIÓN
   * ============================================================
   */

  $duration =
    round(
      microtime(true)
        - $startTime,
      2
    );


  /*
   * ============================================================
   * 12. MOSTRAR RESULTADO
   * ============================================================
   */

  $output = [
    'Importación de mercado completada correctamente.',
    'Brent - Serie: RBRTE',
    'Brent - Registros procesados: ' . $brentCount,
    'EUR/USD - Serie: EURUSD',
    'EUR/USD - Registros procesados: ' . $eurUsdCount,
    'Duración: ' . $duration . ' segundos',
  ];


  if (PHP_SAPI === 'cli') {

    echo implode(
      PHP_EOL,
      $output
    ) . PHP_EOL;
  } else {

    header(
      'Content-Type: text/html; charset=utf-8'
    );

    echo '<pre>';

    echo htmlspecialchars(
      implode(
        PHP_EOL,
        $output
      ),
      ENT_QUOTES,
      'UTF-8'
    );

    echo '</pre>';
  }


  /*
   * ============================================================
   * 13. LOG DE ÉXITO
   * ============================================================
   */

  logMarketImport(
    'OK'
      . ' | Brent: '
      . $brentCount
      . ' | EURUSD: '
      . $eurUsdCount
      . ' | Duración: '
      . $duration
      . 's'
  );
} catch (Throwable $e) {

  /*
   * ============================================================
   * ERROR
   * ============================================================
   */

  if (
    isset($pdo)
    && $pdo->inTransaction()
  ) {
    $pdo->rollBack();
  }


  /*
   * Guardamos detalle técnico.
   */

  $logFile =
    __DIR__
    . '/../storage/error.log';


  $message = sprintf(
    "[%s] Error importación mercado: %s en %s:%d%s",
    date('Y-m-d H:i:s'),
    $e->getMessage(),
    $e->getFile(),
    $e->getLine(),
    PHP_EOL
  );


  error_log(
    $message,
    3,
    $logFile
  );


  /*
   * Salida pública limitada.
   */

  if (PHP_SAPI === 'cli') {

    echo 'Error durante la importación de mercado:'
      . PHP_EOL;

    echo $e->getMessage()
      . PHP_EOL;
  } else {

    http_response_code(500);

    echo 'Error durante la importación de mercado.'
      . PHP_EOL;
  }


  exit(1);
}
