<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/api.php';


/**
 * Convierte un decimal de la API al formato que entiende MySQL.
 *
 * Ejemplos:
 * "1,599"     -> "1.599"
 * "39,211417" -> "39.211417"
 * ""          -> null
 */
function normalizeDecimal(?string $value): ?string
{
  if ($value === null || trim($value) === '') {
    return null;
  }

  $value = str_replace(',', '.', trim($value));

  if (!is_numeric($value)) {
    return null;
  }

  return $value;
}


/**
 * Guarda una línea en el log de importaciones correctas.
 */
function logImport(string $message): void
{
  $logFile = __DIR__ . '/../storage/import.log';

  $line = sprintf(
    "[%s] %s%s",
    date('Y-m-d H:i:s'),
    $message,
    PHP_EOL
  );

  error_log($line, 3, $logFile);
}


/*
 * Guardamos la hora de inicio para saber cuánto tarda
 * toda la importación.
 */
$startTime = microtime(true);


try {

  /*
     * ============================================================
     * 1. CONSULTAR LA API
     * ============================================================
     */

  $data = fetchFuelData();


  /*
     * ============================================================
     * 2. VALIDAR LA RESPUESTA PRINCIPAL
     * ============================================================
     */

  if (
    !isset($data['ListaEESSPrecio'])
    || !is_array($data['ListaEESSPrecio'])
    || count($data['ListaEESSPrecio']) === 0
  ) {
    throw new RuntimeException(
      'La API no ha devuelto ninguna estación.'
    );
  }


  /*
     * ============================================================
     * 3. CONVERTIR LA FECHA DE LA API
     * ============================================================
     *
     * API:
     * 22/08/2026 17:08:57
     *
     * MySQL:
     * 2026-08-22 17:08:57
     */

  $apiDate = DateTime::createFromFormat(
    'd/m/Y H:i:s',
    $data['Fecha']
  );

  if ($apiDate === false) {
    throw new RuntimeException(
      'La fecha de la API no tiene el formato esperado.'
    );
  }

  $apiDateMysql = $apiDate->format('Y-m-d H:i:s');


  /*
     * ============================================================
     * 4. COMPROBAR SI EL SNAPSHOT YA EXISTE
     * ============================================================
     */

  $apiDay = $apiDate->format('Y-m-d');

  $stmtSnapshotCheck = $pdo->prepare(
    'SELECT id
    FROM snapshots
    WHERE DATE(api_date) = :api_day
    LIMIT 1'
  );
  $stmtSnapshotCheck->execute([
    'api_day' => $apiDay,
  ]);

  $existingSnapshot = $stmtSnapshotCheck->fetch();

  if ($existingSnapshot !== false) {

    $output = [
      'Ya existe un snapshot importado hoy.',
      'Fecha API: ' . $apiDateMysql,
    ];

    if (PHP_SAPI === 'cli') {

      echo implode(PHP_EOL, $output) . PHP_EOL;
    } else {

      header('Content-Type: text/html; charset=utf-8');

      echo '<pre>';

      echo htmlspecialchars(
        implode(PHP_EOL, $output),
        ENT_QUOTES,
        'UTF-8'
      );

      echo '</pre>';
    }

    logImport(
      'Snapshot diario duplicado ignorado | Fecha API: '
        . $apiDateMysql
    );

    exit(0);
  }


  /*
     * ============================================================
     * 5. INICIAR TRANSACCIÓN
     * ============================================================
     *
     * Si algo falla durante el proceso,
     * deshacemos todos los cambios.
     */

  $pdo->beginTransaction();


  /*
     * ============================================================
     * 6. CREAR EL SNAPSHOT
     * ============================================================
     */

  $stmtSnapshot = $pdo->prepare(
    'INSERT INTO snapshots (api_date)
    VALUES (:api_date)'
  );

  $stmtSnapshot->execute([
    'api_date' => $apiDateMysql,
  ]);

  $snapshotId = (int) $pdo->lastInsertId();


  /*
     * ============================================================
     * 7. PREPARAR INSERT / UPDATE DE ESTACIONES
     * ============================================================
     */

  $sqlStation = '
        INSERT INTO stations (
            external_id,
            name,
            address,
            postal_code,
            locality,
            municipality,
            province,
            municipality_id,
            province_id,
            ccaa_id,
            latitude,
            longitude,
            schedule
        )
        VALUES (
            :external_id,
            :name,
            :address,
            :postal_code,
            :locality,
            :municipality,
            :province,
            :municipality_id,
            :province_id,
            :ccaa_id,
            :latitude,
            :longitude,
            :schedule
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            name = VALUES(name),
            address = VALUES(address),
            postal_code = VALUES(postal_code),
            locality = VALUES(locality),
            municipality = VALUES(municipality),
            province = VALUES(province),
            municipality_id = VALUES(municipality_id),
            province_id = VALUES(province_id),
            ccaa_id = VALUES(ccaa_id),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            schedule = VALUES(schedule)
    ';

  $stmtStation = $pdo->prepare($sqlStation);


  /*
     * ============================================================
     * 8. PREPARAR INSERT DE PRECIOS
     * ============================================================
     */

  $sqlPrice = '
        INSERT INTO prices (
            snapshot_id,
            station_id,
            fuel_code,
            price
        )
        VALUES (
            :snapshot_id,
            :station_id,
            :fuel_code,
            :price
        )
    ';

  $stmtPrice = $pdo->prepare($sqlPrice);


  /*
     * ============================================================
     * 9. MAPA DE COMBUSTIBLES
     * ============================================================
     */

  $fuels = [
    'Precio Adblue'
    => 'adblue',

    'Precio Amoniaco'
    => 'amoniaco',

    'Precio Biodiesel'
    => 'biodiesel',

    'Precio Bioetanol'
    => 'bioetanol',

    'Precio Biogas Natural Comprimido'
    => 'biogas_natural_comprimido',

    'Precio Biogas Natural Licuado'
    => 'biogas_natural_licuado',

    'Precio Diésel Renovable'
    => 'diesel_renovable',

    'Precio Gas Natural Comprimido'
    => 'gas_natural_comprimido',

    'Precio Gas Natural Licuado'
    => 'gas_natural_licuado',

    'Precio Gases licuados del petróleo'
    => 'glp',

    'Precio Gasoleo A'
    => 'gasoleo_a',

    'Precio Gasoleo B'
    => 'gasoleo_b',

    'Precio Gasoleo Premium'
    => 'gasoleo_premium',

    'Precio Gasolina 95 E10'
    => 'gasolina_95_e10',

    'Precio Gasolina 95 E25'
    => 'gasolina_95_e25',

    'Precio Gasolina 95 E5'
    => 'gasolina_95_e5',

    'Precio Gasolina 95 E5 Premium'
    => 'gasolina_95_e5_premium',

    'Precio Gasolina 95 E85'
    => 'gasolina_95_e85',

    'Precio Gasolina 98 E10'
    => 'gasolina_98_e10',

    'Precio Gasolina 98 E5'
    => 'gasolina_98_e5',

    'Precio Gasolina Renovable'
    => 'gasolina_renovable',

    'Precio Hidrogeno'
    => 'hidrogeno',

    'Precio Metanol'
    => 'metanol',
  ];


  /*
     * ============================================================
     * 10. OBTENER TODAS LAS ESTACIONES
     * ============================================================
     */

  $stations = $data['ListaEESSPrecio'];

  $stationCount = 0;
  $priceCount = 0;
  $invalidPriceCount = 0;


  /*
     * ============================================================
     * 11. RECORRER LAS ESTACIONES
     * ============================================================
     */

  foreach ($stations as $station) {

    /*
         * Validación mínima del identificador.
         */

    if (
      !isset($station['IDEESS'])
      || $station['IDEESS'] === ''
    ) {
      throw new RuntimeException(
        'Se ha recibido una estación sin IDEESS.'
      );
    }


    /*
         * Convertimos coordenadas.
         */

    $latitude = normalizeDecimal(
      $station['Latitud'] ?? null
    );

    $longitude = normalizeDecimal(
      $station['Longitud (WGS84)'] ?? null
    );


    /*
         * Insertamos o actualizamos la estación.
         */

    $stmtStation->execute([

      'external_id' =>
      (int) $station['IDEESS'],

      'name' =>
      $station['Rótulo'] ?? '',

      'address' =>
      $station['Dirección'] ?? '',

      'postal_code' => ($station['C.P.'] ?? '') !== ''
        ? $station['C.P.']
        : null,

      'locality' => ($station['Localidad'] ?? '') !== ''
        ? $station['Localidad']
        : null,

      'municipality' =>
      $station['Municipio'] ?? '',

      'province' =>
      $station['Provincia'] ?? '',

      'municipality_id' => ($station['IDMunicipio'] ?? '') !== ''
        ? (int) $station['IDMunicipio']
        : null,

      'province_id' => ($station['IDProvincia'] ?? '') !== ''
        ? $station['IDProvincia']
        : null,

      'ccaa_id' => ($station['IDCCAA'] ?? '') !== ''
        ? $station['IDCCAA']
        : null,

      'latitude' =>
      $latitude,

      'longitude' =>
      $longitude,

      'schedule' => ($station['Horario'] ?? '') !== ''
        ? $station['Horario']
        : null,
    ]);


    /*
         * Gracias a LAST_INSERT_ID(id), esto devuelve
         * el ID interno tanto si la estación era nueva
         * como si ya existía.
         */

    $stationId = (int) $pdo->lastInsertId();

    if ($stationId <= 0) {
      throw new RuntimeException(
        'No se pudo obtener el ID interno de la estación '
          . $station['IDEESS']
      );
    }


    /*
         * ========================================================
         * 12. GUARDAR PRECIOS
         * ========================================================
         */

    foreach ($fuels as $apiField => $fuelCode) {

      if (!array_key_exists($apiField, $station)) {
        continue;
      }

      $price = normalizeDecimal(
        $station[$apiField]
      );

      if ($price === null) {
        continue;
      }


      /*
             * Evitamos guardar precios claramente inválidos.
             *
             * No ponemos un límite superior agresivo porque
             * algunos combustibles especiales pueden tener
             * valores muy distintos.
             */
      if ((float) $price <= 0) {

        $invalidPriceCount++;

        continue;
      }


      $stmtPrice->execute([

        'snapshot_id' =>
        $snapshotId,

        'station_id' =>
        $stationId,

        'fuel_code' =>
        $fuelCode,

        'price' =>
        $price,
      ]);

      $priceCount++;
    }


    $stationCount++;
  }


  /*
     * ============================================================
     * 13. CONFIRMAR TODOS LOS CAMBIOS
     * ============================================================
     */

  $pdo->commit();


  /*
     * ============================================================
     * 14. CALCULAR DURACIÓN
     * ============================================================
     */

  $duration = round(
    microtime(true) - $startTime,
    2
  );


  /*
     * ============================================================
     * 15. MOSTRAR RESULTADO EN CONSOLA
     * ============================================================
     */

  $output = [
    'Importación completada correctamente.',
    'Snapshot ID: ' . $snapshotId,
    'Fecha API: ' . $apiDateMysql,
    'Estaciones procesadas: ' . $stationCount,
    'Precios guardados: ' . $priceCount,
    'Precios inválidos ignorados: ' . $invalidPriceCount,
    'Duración: ' . $duration . ' segundos',
  ];

  if (PHP_SAPI === 'cli') {

    echo implode(PHP_EOL, $output) . PHP_EOL;
  } else {

    header('Content-Type: text/html; charset=utf-8');

    echo '<pre>';
    echo htmlspecialchars(
      implode(PHP_EOL, $output),
      ENT_QUOTES,
      'UTF-8'
    );
    echo '</pre>';
  }


  /*
     * ============================================================
     * 16. GUARDAR LOG DE ÉXITO
     * ============================================================
     */

  logImport(
    'OK'
      . ' | Snapshot: ' . $snapshotId
      . ' | Fecha API: ' . $apiDateMysql
      . ' | Estaciones: ' . $stationCount
      . ' | Precios: ' . $priceCount
      . ' | Precios inválidos: ' . $invalidPriceCount
      . ' | Duración: ' . $duration . 's'
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
     * Guardamos el detalle técnico en error.log.
     */

  $logFile = __DIR__ . '/../storage/error.log';

  $message = sprintf(
    "[%s] Error importación: %s en %s:%d%s",
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
     * Como este script se ejecuta por consola,
     * mostramos el error también en pantalla.
     */

  if (PHP_SAPI === 'cli') {

    echo 'Error durante la importación:'
      . PHP_EOL;

    echo $e->getMessage()
      . PHP_EOL;
  } else {

    http_response_code(500);

    echo 'Error durante la importación.'
      . PHP_EOL;
  }

  exit(1);
}
