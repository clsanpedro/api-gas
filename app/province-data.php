<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';


/**
 * Busca una provincia por su slug.
 *
 * Ejemplo:
 *
 * zaragoza
 *
 * devuelve:
 *
 * [
 *     'name' => 'Zaragoza',
 *     'db_name' => 'ZARAGOZA',
 *     'slug' => 'zaragoza'
 * ]
 */
function getProvinceBySlug(
  PDO $pdo,
  string $slug
): ?array {

  $stmt = $pdo->query(
    'SELECT DISTINCT province
         FROM stations
         ORDER BY province ASC'
  );

  $provinces = $stmt->fetchAll();

  foreach ($provinces as $province) {

    if (
      slugify($province['province'])
      === $slug
    ) {
      return [
        'name' => displayName(
          $province['province']
        ),

        'db_name' =>
        $province['province'],

        'slug' =>
        $slug,
      ];
    }
  }

  return null;
}


/**
 * Devuelve los municipios pertenecientes
 * a una provincia.
 */
function getProvinceMunicipalities(
  PDO $pdo,
  string $province
): array {

  $stmt = $pdo->prepare(
    'SELECT DISTINCT municipality
         FROM stations
         WHERE province = :province
           AND municipality IS NOT NULL
           AND municipality <> \'\'
         ORDER BY municipality ASC'
  );

  $stmt->execute([
    'province' => $province,
  ]);

  $rows = $stmt->fetchAll();

  $municipalities = [];

  foreach ($rows as $row) {

    $dbName = $row['municipality'];

    $municipalities[] = [
      'name' => displayName(
        $dbName
      ),

      'db_name' =>
      $dbName,

      'slug' =>
      slugify($dbName),
    ];
  }

  return $municipalities;
}


/**
 * Obtiene los datos necesarios para
 * una página de provincia.
 */
function getProvinceStats(
  PDO $pdo,
  string $province,
  string $fuelCode = 'gasolina_95_e5'
): ?array {

  /*
     * ========================================================
     * 1. ÚLTIMO SNAPSHOT
     * ========================================================
     */

  $stmtSnapshot = $pdo->query(
    'SELECT id, api_date
         FROM snapshots
         ORDER BY api_date DESC
         LIMIT 1'
  );

  $snapshot = $stmtSnapshot->fetch();

  if ($snapshot === false) {
    return null;
  }

  $snapshotId = (int) $snapshot['id'];


  /*
     * ========================================================
     * 2. ESTADÍSTICAS DE LA PROVINCIA
     * ========================================================
     */

  $stmtStats = $pdo->prepare(
    'SELECT
            MIN(p.price) AS min_price,
            MAX(p.price) AS max_price,
            ROUND(AVG(p.price), 3) AS avg_price,
            COUNT(*) AS stations_count
         FROM prices p
         INNER JOIN stations s
            ON s.id = p.station_id
         WHERE p.snapshot_id = :snapshot_id
           AND p.fuel_code = :fuel_code
           AND s.province = :province'
  );

  $stmtStats->execute([
    'snapshot_id' =>
    $snapshotId,

    'fuel_code' =>
    $fuelCode,

    'province' =>
    $province,
  ]);

  $stats = $stmtStats->fetch();


  /*
     * ========================================================
     * 3. TOP 10 GASOLINERAS MÁS BARATAS
     * ========================================================
     */

  $stmtCheapest = $pdo->prepare(
    'SELECT
            p.price,
            s.external_id,
            s.name,
            s.address,
            s.municipality,
            s.province
         FROM prices p
         INNER JOIN stations s
            ON s.id = p.station_id
         WHERE p.snapshot_id = :snapshot_id
           AND p.fuel_code = :fuel_code
           AND s.province = :province
         ORDER BY p.price ASC
         LIMIT 10'
  );

  $stmtCheapest->execute([
    'snapshot_id' =>
    $snapshotId,

    'fuel_code' =>
    $fuelCode,

    'province' =>
    $province,
  ]);

  $cheapestStations =
    $stmtCheapest->fetchAll();


  /*
     * ========================================================
     * 4. MUNICIPIOS DE LA PROVINCIA
     * ========================================================
     */

  $municipalities =
    getProvinceMunicipalities(
      $pdo,
      $province
    );


  /*
     * ========================================================
     * 5. DEVOLVER TODOS LOS DATOS
     * ========================================================
     */

  return [

    'snapshot' => [
      'id' =>
      $snapshotId,

      'api_date' =>
      $snapshot['api_date'],
    ],


    'stats' => [
      'min_price' =>
      $stats['min_price'] ?? null,

      'max_price' =>
      $stats['max_price'] ?? null,

      'avg_price' =>
      $stats['avg_price'] ?? null,

      'stations_count' =>
      isset(
        $stats['stations_count']
      )
        ? (int) $stats['stations_count']
        : 0,
    ],


    'cheapest_stations' =>
    $cheapestStations,


    'municipalities' =>
    $municipalities,
  ];
}
