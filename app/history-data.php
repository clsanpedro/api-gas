<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';


/**
 * Devuelve el histórico de precios de una estación
 * para un combustible concreto.
 *
 * Cada fila incluirá:
 *
 * - fecha
 * - precio
 * - variación absoluta respecto al registro anterior
 * - variación porcentual respecto al registro anterior
 *
 * Ejemplo:
 *
 * [
 *     [
 *         'api_date' => '2026-08-22 19:06:25',
 *         'price' => 1.785,
 *         'change' => null,
 *         'change_percent' => null,
 *     ],
 *     [
 *         'api_date' => '2026-08-23 19:04:10',
 *         'price' => 1.799,
 *         'change' => 0.014,
 *         'change_percent' => 0.78,
 *     ],
 * ]
 */
function getStationFuelHistory(
  PDO $pdo,
  int $stationId,
  string $fuelCode,
  int $limit = 365
): array {

  /*
     * Limitamos el número de registros para evitar
     * consultas enormes accidentalmente.
     */
  $limit = max(
    1,
    min(
      $limit,
      3650
    )
  );


  /*
     * Obtenemos los precios ordenados de más antiguo
     * a más reciente.
     */
  $sql = '
        SELECT
            sn.api_date,
            p.price
        FROM prices p
        INNER JOIN snapshots sn
            ON sn.id = p.snapshot_id
        WHERE p.station_id = :station_id
          AND p.fuel_code = :fuel_code
        ORDER BY sn.api_date ASC
        LIMIT ' . $limit;


  $stmt = $pdo->prepare($sql);

  $stmt->execute([
    'station_id' => $stationId,
    'fuel_code' => $fuelCode,
  ]);

  $rows = $stmt->fetchAll();

  $history = [];

  $previousPrice = null;


  /*
     * Recorremos los registros calculando
     * la variación respecto al anterior.
     */
  foreach ($rows as $row) {

    $price = (float) $row['price'];

    $change = null;
    $changePercent = null;


    /*
         * El primer registro no tiene anterior,
         * así que no puede tener variación.
         */
    if ($previousPrice !== null) {

      $change = round(
        $price - $previousPrice,
        3
      );


      /*
             * Evitamos división entre cero.
             */
      if ($previousPrice > 0) {

        $changePercent = round(
          (
            ($price - $previousPrice)
            / $previousPrice
          ) * 100,
          2
        );
      }
    }


    $history[] = [
      'api_date' =>
      $row['api_date'],

      'price' =>
      $price,

      'change' =>
      $change,

      'change_percent' =>
      $changePercent,
    ];


    /*
         * Este precio será el "anterior"
         * en la siguiente vuelta.
         */
    $previousPrice = $price;
  }


  return $history;
}


/**
 * Devuelve un pequeño resumen del histórico.
 *
 * Nos servirá después en la ficha:
 *
 * precio actual
 * precio anterior
 * diferencia
 * diferencia %
 */
function getStationFuelHistorySummary(
  PDO $pdo,
  int $stationId,
  string $fuelCode
): ?array {

  /*
     * Solo necesitamos los dos últimos registros.
     */
  $stmt = $pdo->prepare(
    'SELECT
            sn.api_date,
            p.price
         FROM prices p
         INNER JOIN snapshots sn
            ON sn.id = p.snapshot_id
         WHERE p.station_id = :station_id
           AND p.fuel_code = :fuel_code
         ORDER BY sn.api_date DESC
         LIMIT 2'
  );

  $stmt->execute([
    'station_id' => $stationId,
    'fuel_code' => $fuelCode,
  ]);

  $rows = $stmt->fetchAll();


  /*
     * Si no tenemos ningún precio, no hay resumen.
     */
  if (count($rows) === 0) {
    return null;
  }


  $current = (float) $rows[0]['price'];

  $previous = isset($rows[1])
    ? (float) $rows[1]['price']
    : null;


  $change = null;
  $changePercent = null;


  /*
     * Si existe un precio anterior,
     * calculamos la variación.
     */
  if ($previous !== null) {

    $change = round(
      $current - $previous,
      3
    );


    if ($previous > 0) {

      $changePercent = round(
        (
          ($current - $previous)
          / $previous
        ) * 100,
        2
      );
    }
  }


  return [
    'current' => [
      'api_date' =>
      $rows[0]['api_date'],

      'price' =>
      $current,
    ],

    'previous' =>
    isset($rows[1])
      ? [
        'api_date' =>
        $rows[1]['api_date'],

        'price' =>
        $previous,
      ]
      : null,

    'change' =>
    $change,

    'change_percent' =>
    $changePercent,
  ];
}

/**
 * Devuelve la evolución del precio medio nacional
 * para un combustible concreto.
 *
 * Cada fila incluye:
 *
 * - fecha del snapshot
 * - precio medio nacional
 */
function getNationalFuelHistory(
  PDO $pdo,
  string $fuelCode,
  int $limit = 365
): array {

  /*
   * Limitamos el número de snapshots para evitar
   * consultas excesivamente grandes.
   */
  $limit = max(
    1,
    min(
      $limit,
      3650
    )
  );

  /*
   * Seleccionamos primero los últimos snapshots.
   *
   * Después calculamos el precio medio nacional
   * del combustible para cada uno de ellos.
   */
  $sql = '
    SELECT
      sn.api_date,
      ROUND(AVG(p.price), 3) AS avg_price
    FROM (
      SELECT
        id,
        api_date
      FROM snapshots
      ORDER BY api_date DESC
      LIMIT ' . $limit . '
    ) sn
    INNER JOIN prices p
      ON p.snapshot_id = sn.id
    WHERE p.fuel_code = :fuel_code
    GROUP BY
      sn.id,
      sn.api_date
    ORDER BY sn.api_date ASC
  ';

  $stmt = $pdo->prepare($sql);

  $stmt->execute([
    'fuel_code' => $fuelCode,
  ]);

  $rows = $stmt->fetchAll();

  $history = [];

  foreach ($rows as $row) {

    $history[] = [
      'api_date' =>
      $row['api_date'],

      'avg_price' =>
      (float) $row['avg_price'],
    ];
  }

  return $history;
}
