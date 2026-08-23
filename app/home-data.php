<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

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
    'gasoline_95_e5' => null,
  ];
}

$snapshotId = (int) $latestSnapshot['id'];


/*
 * ============================================================
 * 2. MÉTRICAS DE GASOLINA 95 E5
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
  'fuel_code' => 'gasolina_95_e5',
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
  'fuel_code' => 'gasolina_95_e5',
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

  'gasoline_95_e5' => [
    'min_price' => $stats['min_price'] ?? null,
    'max_price' => $stats['max_price'] ?? null,
    'avg_price' => $stats['avg_price'] ?? null,
    'stations_count' => isset($stats['stations_count'])
      ? (int) $stats['stations_count']
      : 0,

    'cheapest_station' => $cheapestStation !== false
      ? [
        'price' => $cheapestStation['price'],
        'name' => $cheapestStation['name'],
        'address' => $cheapestStation['address'],
        'municipality' => $cheapestStation['municipality'],
        'province' => $cheapestStation['province'],
      ]
      : null,
  ],
];
