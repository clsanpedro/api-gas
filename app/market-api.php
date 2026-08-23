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


/**
 * Consulta la API del BCE y devuelve
 * tipos de cambio diarios EUR/USD.
 *
 * Serie:
 * D.USD.EUR.SP00.A
 *
 * Interpretación:
 * 1 EUR = X USD
 *
 * Unidad:
 * USD por EUR
 */
function fetchEurUsdPrices(
  int $days = 15
): array {

  /*
   * Limitamos el número de días solicitados.
   */
  $days = max(
    1,
    min(
      $days,
      365
    )
  );


  /*
   * Pedimos algunos días adicionales para cubrir
   * fines de semana y festivos.
   */
  $calendarDays =
    $days + 10;


  $startDate =
    (new DateTimeImmutable())
    ->modify(
      '-' . $calendarDays . ' days'
    )
    ->format('Y-m-d');


  /*
   * Construimos la URL del BCE.
   */
  $query = http_build_query([
    'startPeriod' =>
      $startDate,

    'format' =>
      'csvdata',
  ]);


  $url =
    'https://data-api.ecb.europa.eu/service/data/'
    . 'EXR/D.USD.EUR.SP00.A?'
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
        "Accept: text/csv\r\n"
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
      'No se pudo consultar la API del BCE.'
    );
  }


  /*
   * Convertimos la respuesta CSV en líneas.
   */
  $lines = preg_split(
    '/\r\n|\r|\n/',
    trim($response)
  );


  if (
    !is_array($lines)
    || count($lines) < 2
  ) {
    throw new RuntimeException(
      'La respuesta del BCE no contiene datos válidos.'
    );
  }


  /*
   * Leemos la cabecera CSV.
   */
  $header =
    str_getcsv(
      array_shift($lines)
    );


  $dateIndex =
    array_search(
      'TIME_PERIOD',
      $header,
      true
    );


  $valueIndex =
    array_search(
      'OBS_VALUE',
      $header,
      true
    );


  if (
    $dateIndex === false
    || $valueIndex === false
  ) {
    throw new RuntimeException(
      'La respuesta del BCE no tiene las columnas esperadas.'
    );
  }


  $prices = [];


  /*
   * Recorremos las filas del CSV.
   */
  foreach ($lines as $line) {

    if (trim($line) === '') {
      continue;
    }


    $row =
      str_getcsv(
        $line
      );


    $period =
      $row[$dateIndex]
      ?? null;

    $value =
      $row[$valueIndex]
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
        'EURUSD',

      'value' =>
        (float) $value,

      'unit' =>
        'USD/EUR',

      'source' =>
        'ECB',
    ];
  }


  if ($prices === []) {
    throw new RuntimeException(
      'El BCE no ha devuelto tipos EUR/USD válidos.'
    );
  }


  /*
   * Ordenamos de más reciente a más antiguo.
   */
  usort(
    $prices,
    static function (
      array $a,
      array $b
    ): int {

      return strcmp(
        $b['price_date'],
        $a['price_date']
      );
    }
  );


  /*
   * Dejamos solo el número de registros solicitado.
   */
  $prices =
    array_slice(
      $prices,
      0,
      $days
    );


  return $prices;
}