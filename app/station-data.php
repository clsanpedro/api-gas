<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';


/**
 * Busca una estación por su IDEESS / external_id.
 *
 * Devuelve null si no existe.
 */
function getStationByExternalId(
  PDO $pdo,
  int $externalId
): ?array {

  $stmt = $pdo->prepare(
    'SELECT
            id,
            external_id,
            name,
            address,
            postal_code,
            locality,
            municipality,
            province,
            latitude,
            longitude,
            schedule
         FROM stations
         WHERE external_id = :external_id
         LIMIT 1'
  );

  $stmt->execute([
    'external_id' => $externalId,
  ]);

  $station = $stmt->fetch();

  if ($station === false) {
    return null;
  }

  return [
    'id' => (int) $station['id'],

    'external_id' =>
    (int) $station['external_id'],

    'name' =>
    $station['name'],

    'slug' =>
    slugify($station['name']),

    'address' =>
    $station['address'],

    'postal_code' =>
    $station['postal_code'],

    'locality' =>
    $station['locality'],

    'municipality' =>
    $station['municipality'],

    'municipality_name' =>
    displayName(
      $station['municipality']
    ),

    'province' =>
    $station['province'],

    'province_name' =>
    displayName(
      $station['province']
    ),

    'province_slug' =>
    slugify(
      $station['province']
    ),

    'municipality_slug' =>
    slugify(
      $station['municipality']
    ),

    'latitude' =>
    $station['latitude'],

    'longitude' =>
    $station['longitude'],

    'schedule' =>
    $station['schedule'],
  ];
}


/**
 * Obtiene los precios actuales de una estación.
 *
 * "Actuales" significa:
 * precios del último snapshot disponible.
 */
function getStationLatestPrices(
  PDO $pdo,
  int $stationId
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
     * 2. PRECIOS DE LA ESTACIÓN
     * ========================================================
     */

  $stmtPrices = $pdo->prepare(
    'SELECT
            fuel_code,
            price
         FROM prices
         WHERE snapshot_id = :snapshot_id
           AND station_id = :station_id
         ORDER BY fuel_code ASC'
  );

  $stmtPrices->execute([
    'snapshot_id' =>
    $snapshotId,

    'station_id' =>
    $stationId,
  ]);

  $prices = $stmtPrices->fetchAll();


  return [
    'snapshot' => [
      'id' =>
      $snapshotId,

      'api_date' =>
      $snapshot['api_date'],
    ],

    'prices' =>
    $prices,
  ];
}


/**
 * Devuelve toda la información necesaria
 * para la ficha de una gasolinera.
 */
function getStationData(
  PDO $pdo,
  int $externalId
): ?array {

  $station = getStationByExternalId(
    $pdo,
    $externalId
  );

  if ($station === null) {
    return null;
  }

  $latestPrices = getStationLatestPrices(
    $pdo,
    $station['id']
  );

  return [
    'station' =>
    $station,

    'latest' =>
    $latestPrices,
  ];
}
