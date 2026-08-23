<?php

declare(strict_types=1);


/**
 * Devuelve el último dato disponible
 * de una serie de mercado.
 */
function getLatestMarketPrice(
  PDO $pdo,
  string $seriesCode
): ?array {

  $stmt = $pdo->prepare(
    'SELECT
        price_date,
        series_code,
        value,
        unit,
        source,
        created_at,
        updated_at
     FROM market_prices
     WHERE series_code = :series_code
     ORDER BY price_date DESC
     LIMIT 1'
  );

  $stmt->execute([
    'series_code' =>
    $seriesCode,
  ]);

  $row = $stmt->fetch();

  if ($row === false) {
    return null;
  }

  return [
    'price_date' =>
    $row['price_date'],

    'series_code' =>
    $row['series_code'],

    'value' =>
    (float) $row['value'],

    'unit' =>
    $row['unit'],

    'source' =>
    $row['source'],

    'created_at' =>
    $row['created_at'],

    'updated_at' =>
    $row['updated_at'],
  ];
}


/**
 * Devuelve el histórico de Brent convertido
 * a euros por barril.
 *
 * Para cada fecha necesitamos:
 *
 * - Brent en USD/barril (RBRTE)
 * - EUR/USD (USD por 1 EUR)
 *
 * Fórmula:
 *
 * Brent EUR/barril =
 * Brent USD/barril / EURUSD
 */
function getBrentEurHistory(
  PDO $pdo,
  int $limit = 365
): array {

  /*
   * Evitamos consultas excesivamente grandes.
   */
  $limit = max(
    1,
    min(
      $limit,
      3650
    )
  );


  /*
   * Unimos ambas series por fecha.
   *
   * Solo devolvemos días en los que existen
   * tanto Brent como EUR/USD.
   */
  $sql = '
    SELECT
      b.price_date,
      b.value AS brent_usd,
      fx.value AS eur_usd
    FROM market_prices b
    INNER JOIN market_prices fx
      ON fx.price_date = b.price_date
     AND fx.series_code = \'EURUSD\'
    WHERE b.series_code = \'RBRTE\'
    ORDER BY b.price_date DESC
    LIMIT ' . $limit;


  $stmt = $pdo->query($sql);

  $rows = $stmt->fetchAll();

  $history = [];


  foreach ($rows as $row) {

    $brentUsd =
      (float) $row['brent_usd'];

    $eurUsd =
      (float) $row['eur_usd'];


    /*
     * Evitamos división entre cero
     * o datos claramente inválidos.
     */
    if (
      $brentUsd <= 0
      || $eurUsd <= 0
    ) {
      continue;
    }


    $brentEur =
      $brentUsd
      / $eurUsd;


    $history[] = [
      'price_date' =>
      $row['price_date'],

      'brent_usd' =>
      $brentUsd,

      'eur_usd' =>
      $eurUsd,

      'brent_eur' =>
      round(
        $brentEur,
        4
      ),
    ];
  }


  /*
   * La consulta viene de más reciente
   * a más antiguo.
   *
   * Para gráficos queremos orden cronológico.
   */
  return array_reverse(
    $history
  );
}
