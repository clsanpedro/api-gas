<?php

declare(strict_types=1);

/**
 * Obtiene los datos principales de la home
 * para un combustible concreto.
 */
function getHomeData(
  PDO $pdo,
  string $fuelCode
): array {

  /*
   * ============================================================
   * 1. OBTENER EL ÚLTIMO SNAPSHOT
   * ============================================================
   */

  $stmtSnapshot = $pdo->query(
    'SELECT id, api_date
    FROM snapshots
    ORDER BY api_date DESC
    LIMIT 1'
  );

  $latestSnapshot = $stmtSnapshot->fetch();

  if ($latestSnapshot === false) {
    return [
      'snapshot' => null,
      'fuel' => null,
    ];
  }

  $snapshotId = (int) $latestSnapshot['id'];


  /*
   * ============================================================
   * 2. MÉTRICAS DEL COMBUSTIBLE
   * ============================================================
   */

  $stmtStats = $pdo->prepare(
    'SELECT
          MIN(price) AS min_price,
          MAX(price) AS max_price,
          ROUND(AVG(price), 3) AS avg_price,
          COUNT(*) AS stations_count
    FROM prices
    WHERE snapshot_id = :snapshot_id
    AND fuel_code = :fuel_code'
  );

  $stmtStats->execute([
    'snapshot_id' => $snapshotId,
    'fuel_code' => $fuelCode,
  ]);

  $stats = $stmtStats->fetch();


  /*
   * ============================================================
   * 3. ESTACIÓN MÁS BARATA
   * ============================================================
   */

  $stmtCheapest = $pdo->prepare(
    'SELECT
          p.price,
          s.name,
          s.address,
          s.municipality,
          s.province
    FROM prices p
    INNER JOIN stations s
      ON s.id = p.station_id
    WHERE p.snapshot_id = :snapshot_id
      AND p.fuel_code = :fuel_code
    ORDER BY p.price ASC
    LIMIT 1'
  );

  $stmtCheapest->execute([
    'snapshot_id' => $snapshotId,
    'fuel_code' => $fuelCode,
  ]);

  $cheapestStation = $stmtCheapest->fetch();


  /*
   * ============================================================
   * 4. DEVOLVER DATOS PREPARADOS
   * ============================================================
   */

  return [
    'snapshot' => [
      'id' => $snapshotId,
      'api_date' => $latestSnapshot['api_date'],
    ],

    'fuel' => [
      'code' => $fuelCode,

      'min_price' =>
        $stats['min_price']
        ?? null,

      'max_price' =>
        $stats['max_price']
        ?? null,

      'avg_price' =>
        $stats['avg_price']
        ?? null,

      'stations_count' =>
        isset($stats['stations_count'])
        ? (int) $stats['stations_count']
        : 0,

      'cheapest_station' =>
        $cheapestStation !== false
        ? [
          'price' =>
            $cheapestStation['price'],

          'name' =>
            $cheapestStation['name'],

          'address' =>
            $cheapestStation['address'],

          'municipality' =>
            $cheapestStation['municipality'],

          'province' =>
            $cheapestStation['province'],
        ]
        : null,
    ],
  ];
}