<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';


/*
 * ============================================================
 * INICIO: CONFIGURACIÓN DEL BUSCADOR
 * ============================================================
 */

const SEARCH_RESULTS_PER_PAGE = 20;
const SEARCH_MAX_RESULTS_PER_PAGE = 50;
const SEARCH_GEO_LIMIT = 10;

/*
 * ============================================================
 * FIN: CONFIGURACIÓN DEL BUSCADOR
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: NORMALIZACIÓN
 * ============================================================
 */

/**
 * Limpia el término introducido por el usuario.
 */
function normalizeSearchQuery(
  string $query
): string {

  $query = trim($query);

  $query = preg_replace(
    '/\s+/u',
    ' ',
    $query
  );

  return $query ?? '';
}

/*
 * ============================================================
 * FIN: NORMALIZACIÓN
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: CONTAR GASOLINERAS
 * ============================================================
 */

/**
 * Cuenta todas las estaciones que coinciden con:
 *
 * - nombre
 * - dirección
 * - código postal
 * - localidad
 * - municipio
 * - provincia
 */
function countSearchStations(
  PDO $pdo,
  string $query
): int {

  $query =
    normalizeSearchQuery(
      $query
    );

  if ($query === '') {
    return 0;
  }


  $contains =
    '%' . $query . '%';


  $sql = '
        SELECT COUNT(*)

        FROM stations

        WHERE
            name LIKE :term_name
            OR address LIKE :term_address
            OR postal_code LIKE :term_postal
            OR locality LIKE :term_locality
            OR municipality LIKE :term_municipality
            OR province LIKE :term_province
    ';


  $stmt =
    $pdo->prepare(
      $sql
    );


  $stmt->execute([

    'term_name' =>
    $contains,

    'term_address' =>
    $contains,

    'term_postal' =>
    $contains,

    'term_locality' =>
    $contains,

    'term_municipality' =>
    $contains,

    'term_province' =>
    $contains,
  ]);


  return (int) $stmt->fetchColumn();
}

/*
 * ============================================================
 * FIN: CONTAR GASOLINERAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCAR GASOLINERAS PAGINADAS
 * ============================================================
 */

/**
 * Devuelve las estaciones de una página concreta.
 *
 * La ordenación intenta imitar la intención humana:
 *
 *  1. Nombre exacto
 *  2. Municipio exacto
 *  3. Provincia exacta
 *  4. Localidad exacta
 *  5. Código postal exacto
 *  6. Nombre que empieza por el término
 *  7. Municipio que empieza por el término
 *  8. Provincia que empieza por el término
 *  9. Localidad que empieza por el término
 * 10. Dirección que empieza por el término
 * 11. Nombre que contiene el término
 * 12. Municipio que contiene el término
 * 13. Provincia que contiene el término
 * 14. Resto
 */
function searchStationsPage(
  PDO $pdo,
  string $query,
  int $page = 1,
  int $perPage = SEARCH_RESULTS_PER_PAGE
): array {

  $query =
    normalizeSearchQuery(
      $query
    );


  if ($query === '') {
    return [];
  }


  $page =
    max(
      1,
      $page
    );


  $perPage =
    max(
      1,
      min(
        $perPage,
        SEARCH_MAX_RESULTS_PER_PAGE
      )
    );


  $offset =
    ($page - 1)
    * $perPage;


  $contains =
    '%' . $query . '%';

  $startsWith =
    $query . '%';


  /*
     * ========================================================
     * IMPORTANTE
     * ========================================================
     *
     * Cada placeholder PDO tiene un nombre diferente.
     *
     * No reutilizamos:
     *
     * :exact
     * :starts
     * :term
     *
     * porque ya vimos que nuestra configuración PDO
     * puede provocar HY093 al reutilizar parámetros.
     * ========================================================
     */

  $sql = '
        SELECT
            id,
            external_id,
            name,
            address,
            postal_code,
            locality,
            municipality,
            province

        FROM stations

        WHERE
            name LIKE :term_name
            OR address LIKE :term_address
            OR postal_code LIKE :term_postal
            OR locality LIKE :term_locality
            OR municipality LIKE :term_municipality
            OR province LIKE :term_province

        ORDER BY

            CASE

                /*
                 * 1. Nombre exacto.
                 *
                 * REPSOL
                 */
                WHEN LOWER(name)
                    = LOWER(:exact_name)
                THEN 1


                /*
                 * 2. Municipio exacto.
                 *
                 * Barcelona
                 * Mataró
                 * Zaragoza
                 */
                WHEN LOWER(municipality)
                    = LOWER(:exact_municipality)
                THEN 2


                /*
                 * 3. Provincia exacta.
                 */
                WHEN LOWER(province)
                    = LOWER(:exact_province)
                THEN 3


                /*
                 * 4. Localidad exacta.
                 */
                WHEN LOWER(locality)
                    = LOWER(:exact_locality)
                THEN 4


                /*
                 * 5. Código postal exacto.
                 *
                 * 08001
                 */
                WHEN postal_code
                    = :exact_postal
                THEN 5


                /*
                 * 6. Nombre empieza por término.
                 *
                 * REPSOL...
                 */
                WHEN name
                    LIKE :start_name
                THEN 6


                /*
                 * 7. Municipio empieza por término.
                 */
                WHEN municipality
                    LIKE :start_municipality
                THEN 7


                /*
                 * 8. Provincia empieza por término.
                 */
                WHEN province
                    LIKE :start_province
                THEN 8


                /*
                 * 9. Localidad empieza por término.
                 */
                WHEN locality
                    LIKE :start_locality
                THEN 9


                /*
                 * 10. Dirección empieza por término.
                 */
                WHEN address
                    LIKE :start_address
                THEN 10


                /*
                 * 11. Nombre contiene término.
                 */
                WHEN name
                    LIKE :contains_name
                THEN 11


                /*
                 * 12. Municipio contiene término.
                 */
                WHEN municipality
                    LIKE :contains_municipality
                THEN 12


                /*
                 * 13. Provincia contiene término.
                 */
                WHEN province
                    LIKE :contains_province
                THEN 13


                /*
                 * 14. Resto.
                 */
                ELSE 14

            END,

            /*
             * Orden secundario estable.
             *
             * Muy importante para paginación:
             * la misma búsqueda debe producir siempre
             * el mismo orden.
             */
            province ASC,
            municipality ASC,
            name ASC,
            address ASC,
            external_id ASC

        LIMIT ' . $perPage . '

        OFFSET ' . $offset;


  $stmt =
    $pdo->prepare(
      $sql
    );


  $stmt->execute([

    /*
         * WHERE
         */

    'term_name' =>
    $contains,

    'term_address' =>
    $contains,

    'term_postal' =>
    $contains,

    'term_locality' =>
    $contains,

    'term_municipality' =>
    $contains,

    'term_province' =>
    $contains,


    /*
         * EXACTOS
         */

    'exact_name' =>
    $query,

    'exact_municipality' =>
    $query,

    'exact_province' =>
    $query,

    'exact_locality' =>
    $query,

    'exact_postal' =>
    $query,


    /*
         * EMPIEZA POR
         */

    'start_name' =>
    $startsWith,

    'start_municipality' =>
    $startsWith,

    'start_province' =>
    $startsWith,

    'start_locality' =>
    $startsWith,

    'start_address' =>
    $startsWith,


    /*
         * CONTIENE
         */

    'contains_name' =>
    $contains,

    'contains_municipality' =>
    $contains,

    'contains_province' =>
    $contains,
  ]);


  $rows =
    $stmt->fetchAll();


  $results = [];


  foreach ($rows as $row) {

    $municipality =
      $row['municipality']
      ?? '';

    $province =
      $row['province']
      ?? '';


    $results[] = [

      'type' =>
      'station',

      'external_id' =>
      (int) $row['external_id'],

      'name' =>
      $row['name'],

      'slug' =>
      slugify(
        $row['name']
      ),

      'address' =>
      $row['address'],

      'postal_code' =>
      $row['postal_code'],

      'locality' =>
      $row['locality'],

      'municipality_name' =>
      $municipality !== ''
        ? displayName(
          $municipality
        )
        : '',

      'municipality_slug' =>
      $municipality !== ''
        ? slugify(
          $municipality
        )
        : '',

      'province_name' =>
      $province !== ''
        ? displayName(
          $province
        )
        : '',

      'province_slug' =>
      $province !== ''
        ? slugify(
          $province
        )
        : '',
    ];
  }


  return $results;
}

/*
 * ============================================================
 * FIN: BUSCAR GASOLINERAS PAGINADAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCAR MUNICIPIOS
 * ============================================================
 */

/**
 * Busca municipios coincidentes.
 *
 * Los resultados exactos aparecen primero.
 */
function searchMunicipalities(
  PDO $pdo,
  string $query,
  int $limit = SEARCH_GEO_LIMIT
): array {

  $query =
    normalizeSearchQuery(
      $query
    );


  if ($query === '') {
    return [];
  }


  $limit =
    max(
      1,
      min(
        $limit,
        25
      )
    );


  $sql = '
        SELECT
            province,
            municipality,
            COUNT(*) AS station_count

        FROM stations

        WHERE
            municipality LIKE :municipality_term

        GROUP BY
            province,
            municipality

        ORDER BY

            CASE

                WHEN LOWER(municipality)
                    = LOWER(:municipality_exact)
                THEN 1

                WHEN municipality
                    LIKE :municipality_start
                THEN 2

                ELSE 3

            END,

            municipality ASC,
            province ASC

        LIMIT ' . $limit;


  $stmt =
    $pdo->prepare(
      $sql
    );


  $stmt->execute([

    'municipality_term' =>
    '%' . $query . '%',

    'municipality_exact' =>
    $query,

    'municipality_start' =>
    $query . '%',
  ]);


  $rows =
    $stmt->fetchAll();


  $results = [];


  foreach ($rows as $row) {

    $results[] = [

      'type' =>
      'municipality',

      'name' =>
      displayName(
        $row['municipality']
      ),

      'slug' =>
      slugify(
        $row['municipality']
      ),

      'province_name' =>
      displayName(
        $row['province']
      ),

      'province_slug' =>
      slugify(
        $row['province']
      ),

      'station_count' =>
      (int) $row['station_count'],
    ];
  }


  return $results;
}

/*
 * ============================================================
 * FIN: BUSCAR MUNICIPIOS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCAR PROVINCIAS
 * ============================================================
 */

/**
 * Busca provincias coincidentes.
 *
 * Las coincidencias exactas aparecen primero.
 */
function searchProvinces(
  PDO $pdo,
  string $query,
  int $limit = SEARCH_GEO_LIMIT
): array {

  $query =
    normalizeSearchQuery(
      $query
    );


  if ($query === '') {
    return [];
  }


  $limit =
    max(
      1,
      min(
        $limit,
        25
      )
    );


  $sql = '
        SELECT
            province,
            COUNT(*) AS station_count

        FROM stations

        WHERE
            province LIKE :province_term

        GROUP BY province

        ORDER BY

            CASE

                WHEN LOWER(province)
                    = LOWER(:province_exact)
                THEN 1

                WHEN province
                    LIKE :province_start
                THEN 2

                ELSE 3

            END,

            province ASC

        LIMIT ' . $limit;


  $stmt =
    $pdo->prepare(
      $sql
    );


  $stmt->execute([

    'province_term' =>
    '%' . $query . '%',

    'province_exact' =>
    $query,

    'province_start' =>
    $query . '%',
  ]);


  $rows =
    $stmt->fetchAll();


  $results = [];


  foreach ($rows as $row) {

    $results[] = [

      'type' =>
      'province',

      'name' =>
      displayName(
        $row['province']
      ),

      'slug' =>
      slugify(
        $row['province']
      ),

      'station_count' =>
      (int) $row['station_count'],
    ];
  }


  return $results;
}

/*
 * ============================================================
 * FIN: BUSCAR PROVINCIAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCADOR GLOBAL
 * ============================================================
 */

/**
 * Ejecuta todas las búsquedas y calcula
 * la información de paginación.
 */
function globalSearch(
  PDO $pdo,
  string $query,
  int $page = 1,
  int $perPage = SEARCH_RESULTS_PER_PAGE
): array {

  $query =
    normalizeSearchQuery(
      $query
    );


  if ($query === '') {

    return [

      'query' =>
      '',

      'provinces' =>
      [],

      'municipalities' =>
      [],

      'stations' =>
      [],

      'stations_total' =>
      0,

      'page' =>
      1,

      'per_page' =>
      $perPage,

      'total_pages' =>
      0,

      'from' =>
      0,

      'to' =>
      0,
    ];
  }


  /*
     * ========================================================
     * INICIO: TOTAL DE GASOLINERAS
     * ========================================================
     */

  $stationsTotal =
    countSearchStations(
      $pdo,
      $query
    );

  /*
     * ========================================================
     * FIN: TOTAL DE GASOLINERAS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: PAGINACIÓN
     * ========================================================
     */

  $perPage =
    max(
      1,
      min(
        $perPage,
        SEARCH_MAX_RESULTS_PER_PAGE
      )
    );


  $totalPages =
    $stationsTotal > 0
    ? (int) ceil(
      $stationsTotal
        / $perPage
    )
    : 0;


  if ($totalPages > 0) {

    $page =
      max(
        1,
        min(
          $page,
          $totalPages
        )
      );
  } else {

    $page = 1;
  }


  $from =
    $stationsTotal > 0
    ? (($page - 1) * $perPage) + 1
    : 0;


  $to =
    $stationsTotal > 0
    ? min(
      $page * $perPage,
      $stationsTotal
    )
    : 0;

  /*
     * ========================================================
     * FIN: PAGINACIÓN
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: RESULTADOS
     * ========================================================
     */

  $provinces =
    searchProvinces(
      $pdo,
      $query
    );


  $municipalities =
    searchMunicipalities(
      $pdo,
      $query
    );


  $stations =
    searchStationsPage(
      $pdo,
      $query,
      $page,
      $perPage
    );

  /*
     * ========================================================
     * FIN: RESULTADOS
     * ========================================================
     */


  return [

    'query' =>
    $query,

    'provinces' =>
    $provinces,

    'municipalities' =>
    $municipalities,

    'stations' =>
    $stations,

    'stations_total' =>
    $stationsTotal,

    'page' =>
    $page,

    'per_page' =>
    $perPage,

    'total_pages' =>
    $totalPages,

    'from' =>
    $from,

    'to' =>
    $to,
  ];
}

/*
 * ============================================================
 * INICIO: AUTOCOMPLETADO
 * ============================================================
 */

/**
 * Devuelve sugerencias rápidas para el buscador.
 *
 * Máximo recomendado:
 * 8 resultados.
 *
 * Mezclamos:
 *
 * - provincias
 * - municipios
 * - gasolineras
 */
function searchSuggestions(
  PDO $pdo,
  string $query,
  int $limit = 8
): array {

  $query =
    normalizeSearchQuery(
      $query
    );


  if (
    $query === ''
    || mb_strlen(
      $query,
      'UTF-8'
    ) < 2
  ) {
    return [];
  }


  $limit =
    max(
      1,
      min(
        $limit,
        12
      )
    );


  $suggestions = [];


  /*
     * ========================================================
     * INICIO: PROVINCIAS
     * ========================================================
     */

  $provinceResults =
    searchProvinces(
      $pdo,
      $query,
      3
    );


  foreach (
    $provinceResults
    as $province
  ) {

    $suggestions[] = [

      'type' =>
      'province',

      'label' =>
      $province['name'],

      'meta' =>
      'Provincia',

      'url' =>
      '/gasolineras/'
        . $province['slug']
        . '/',
    ];
  }

  /*
     * ========================================================
     * FIN: PROVINCIAS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: MUNICIPIOS
     * ========================================================
     */

  $municipalityResults =
    searchMunicipalities(
      $pdo,
      $query,
      3
    );


  foreach (
    $municipalityResults
    as $municipality
  ) {

    $suggestions[] = [

      'type' =>
      'municipality',

      'label' =>
      $municipality['name'],

      'meta' =>
      'Municipio · '
        . $municipality['province_name'],

      'url' =>
      '/gasolineras/'
        . $municipality['province_slug']
        . '/'
        . $municipality['slug']
        . '/',
    ];
  }

  /*
     * ========================================================
     * FIN: MUNICIPIOS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: GASOLINERAS
     * ========================================================
     */

  $stationResults =
    searchStationsPage(
      $pdo,
      $query,
      1,
      6
    );


  foreach (
    $stationResults
    as $station
  ) {

    $metaParts = [];


    if (
      $station['municipality_name'] !== ''
    ) {
      $metaParts[] =
        $station['municipality_name'];
    }


    if (
      $station['province_name'] !== ''
    ) {
      $metaParts[] =
        $station['province_name'];
    }


    $suggestions[] = [

      'type' =>
      'station',

      'label' =>
      $station['name'],

      'meta' =>
      'Gasolinera'
        . (
          !empty($metaParts)
          ? ' · '
          . implode(
            ', ',
            $metaParts
          )
          : ''
        ),

      'url' =>
      '/gasolinera/'
        . $station['external_id']
        . '-'
        . $station['slug']
        . '/',
    ];
  }

  /*
     * ========================================================
     * FIN: GASOLINERAS
     * ========================================================
     */


  /*
     * Limitamos el total final.
     */
  return array_slice(
    $suggestions,
    0,
    $limit
  );
}

/*
 * ============================================================
 * FIN: AUTOCOMPLETADO
 * ============================================================
 */

/*
 * ============================================================
 * FIN: BUSCADOR GLOBAL
 * ============================================================
 */