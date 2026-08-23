<?php

declare(strict_types=1);


/**
 * Consulta la API de EIA y devuelve
 * precios diarios de Brent Europe.
 *
 * Serie:
 * RBRTE
 *
 * Unidad:
 * USD por barril
 */
function fetchBrentPrices(
  string $apiKey,
  int $days = 15
): array {

  /*
   * Limitamos el número de registros solicitados
   * para evitar peticiones accidentales demasiado grandes.
   */
  $days = max(
    1,
    min(
      $days,
      365
    )
  );


  /*
   * Construimos los parámetros de la API.
   */
  $query = http_build_query([
    'api_key' =>
    $apiKey,

    'frequency' =>
    'daily',

    'data' => [
      'value',
    ],

    'facets' => [
      'series' => [
        'RBRTE',
      ],
    ],

    'sort' => [
      [
        'column' =>
        'period',

        'direction' =>
        'desc',
      ],
    ],

    'length' =>
    $days,
  ]);


  $url =
    'https://api.eia.gov/v2/petroleum/pri/spt/data/?'
    . $query;


  /*
   * Configuración de la petición HTTP.
   */
  $context = stream_context_create([
    'http' => [
      'method' =>
      'GET',

      'timeout' =>
      20,

      'header' =>
      "Accept: application/json\r\n"
        . "User-Agent: PrecioCarburante/1.0\r\n",
    ],
  ]);


  /*
   * Ejecutamos la petición.
   */
  $response = @file_get_contents(
    $url,
    false,
    $context
  );


  if ($response === false) {
    throw new RuntimeException(
      'No se pudo consultar la API de EIA.'
    );
  }


  /*
   * Decodificamos el JSON.
   */
  $data = json_decode(
    $response,
    true
  );


  if (!is_array($data)) {
    throw new RuntimeException(
      'La respuesta de EIA no contiene JSON válido.'
    );
  }


  /*
   * Validamos la estructura principal.
   */
  if (
    !isset(
      $data['response']['data']
    )
    || !is_array(
      $data['response']['data']
    )
  ) {
    throw new RuntimeException(
      'La respuesta de EIA no tiene la estructura esperada.'
    );
  }


  $prices = [];


  /*
   * Normalizamos la respuesta.
   */
  foreach (
    $data['response']['data']
    as $row
  ) {

    $period =
      $row['period']
      ?? null;

    $value =
      $row['value']
      ?? null;


    if (
      !is_string($period)
      || $period === ''
    ) {
      continue;
    }


    if (
      $value === null
      || !is_numeric($value)
    ) {
      continue;
    }


    $date =
      DateTimeImmutable::createFromFormat(
        'Y-m-d',
        $period
      );


    if ($date === false) {
      continue;
    }


    $prices[] = [
      'price_date' =>
      $date->format('Y-m-d'),

      'series_code' =>
      'RBRTE',

      'value' =>
      (float) $value,

      'unit' =>
      'USD/BBL',

      'source' =>
      'EIA',
    ];
  }


  if ($prices === []) {
    throw new RuntimeException(
      'EIA no ha devuelto precios Brent válidos.'
    );
  }


  return $prices;
}
