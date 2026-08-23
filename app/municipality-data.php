<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';


/*
 * ============================================================
 * INICIO: CONFIGURACIÓN
 * ============================================================
 */

const MUNICIPALITY_STATIONS_PER_PAGE = 20;
const MUNICIPALITY_STATIONS_MAX_PER_PAGE = 100;

/*
 * ============================================================
 * FIN: CONFIGURACIÓN
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCAR MUNICIPIO POR SLUG
 * ============================================================
 */

/**
 * Busca un municipio dentro de una provincia.
 *
 * Ejemplo:
 *
 * provincia:
 * BARCELONA
 *
 * slug:
 * mataro
 *
 * devuelve:
 *
 * [
 *     'name' => 'Mataró',
 *     'db_name' => 'MATARÓ',
 *     'slug' => 'mataro'
 * ]
 */
function getMunicipalityBySlug(
  PDO $pdo,
  string $provinceDbName,
  string $municipalitySlug
): ?array {

  $stmt = $pdo->prepare(
    'SELECT DISTINCT
            municipality
         FROM stations
         WHERE province = :province
           AND municipality IS NOT NULL
           AND municipality <> \'\'
         ORDER BY municipality ASC'
  );


  $stmt->execute([
    'province' =>
    $provinceDbName,
  ]);


  $rows =
    $stmt->fetchAll();


  foreach ($rows as $row) {

    $dbName =
      $row['municipality'];


    if (
      slugify($dbName)
      === $municipalitySlug
    ) {

      return [

        'name' =>
        displayName(
          $dbName
        ),

        'db_name' =>
        $dbName,

        'slug' =>
        slugify(
          $dbName
        ),
      ];
    }
  }


  return null;
}

/*
 * ============================================================
 * FIN: BUSCAR MUNICIPIO POR SLUG
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: ÚLTIMO SNAPSHOT
 * ============================================================
 */

/**
 * Devuelve el último snapshot disponible.
 */
function getMunicipalityLatestSnapshot(
  PDO $pdo
): ?array {

  $stmt =
    $pdo->query(
      'SELECT
                id,
                api_date
             FROM snapshots
             ORDER BY api_date DESC
             LIMIT 1'
    );


  $snapshot =
    $stmt->fetch();


  if ($snapshot === false) {
    return null;
  }


  return [

    'id' =>
    (int) $snapshot['id'],

    'api_date' =>
    $snapshot['api_date'],
  ];
}

/*
 * ============================================================
 * FIN: ÚLTIMO SNAPSHOT
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: ESTADÍSTICAS DEL MUNICIPIO
 * ============================================================
 */

/**
 * Devuelve:
 *
 * - snapshot
 * - estadísticas del combustible
 * - top 10 estaciones más baratas
 */
function getMunicipalityStats(
  PDO $pdo,
  string $provinceDbName,
  string $municipalityDbName,
  string $fuelCode
): ?array {

  /*
     * ========================================================
     * INICIO: SNAPSHOT
     * ========================================================
     */

  $snapshot =
    getMunicipalityLatestSnapshot(
      $pdo
    );


  if ($snapshot === null) {
    return null;
  }


  $snapshotId =
    $snapshot['id'];

  /*
     * ========================================================
     * FIN: SNAPSHOT
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: ESTADÍSTICAS
     * ========================================================
     */

  $stmtStats =
    $pdo->prepare(
      'SELECT
                MIN(p.price) AS min_price,
                AVG(p.price) AS avg_price,
                MAX(p.price) AS max_price,
                COUNT(DISTINCT s.id) AS stations_count

             FROM stations s

             INNER JOIN prices p
                ON p.station_id = s.id

             WHERE s.province = :province
               AND s.municipality = :municipality
               AND p.snapshot_id = :snapshot_id
               AND p.fuel_code = :fuel_code'
    );


  $stmtStats->execute([

    'province' =>
    $provinceDbName,

    'municipality' =>
    $municipalityDbName,

    'snapshot_id' =>
    $snapshotId,

    'fuel_code' =>
    $fuelCode,
  ]);


  $stats =
    $stmtStats->fetch();


  if ($stats === false) {

    $stats = [

      'min_price' =>
      null,

      'avg_price' =>
      null,

      'max_price' =>
      null,

      'stations_count' =>
      0,
    ];
  }


  /*
     * Normalizamos los tipos.
     */

  $stats = [

    'min_price' =>
    $stats['min_price'] !== null
      ? (float) $stats['min_price']
      : null,

    'avg_price' =>
    $stats['avg_price'] !== null
      ? (float) $stats['avg_price']
      : null,

    'max_price' =>
    $stats['max_price'] !== null
      ? (float) $stats['max_price']
      : null,

    'stations_count' =>
    (int) $stats['stations_count'],
  ];

  /*
     * ========================================================
     * FIN: ESTADÍSTICAS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: TOP 10 MÁS BARATAS
     * ========================================================
     */

  $stmtCheapest =
    $pdo->prepare(
      'SELECT
                s.id,
                s.external_id,
                s.name,
                s.address,
                s.postal_code,
                s.locality,
                s.municipality,
                s.province,
                p.price

             FROM stations s

             INNER JOIN prices p
                ON p.station_id = s.id

             WHERE s.province = :province
               AND s.municipality = :municipality
               AND p.snapshot_id = :snapshot_id
               AND p.fuel_code = :fuel_code

             ORDER BY
                p.price ASC,
                s.name ASC,
                s.address ASC,
                s.external_id ASC

             LIMIT 10'
    );


  $stmtCheapest->execute([

    'province' =>
    $provinceDbName,

    'municipality' =>
    $municipalityDbName,

    'snapshot_id' =>
    $snapshotId,

    'fuel_code' =>
    $fuelCode,
  ]);


  $cheapestRows =
    $stmtCheapest->fetchAll();


  $cheapestStations = [];


  foreach (
    $cheapestRows
    as $row
  ) {

    $cheapestStations[] = [

      'id' =>
      (int) $row['id'],

      'external_id' =>
      (int) $row['external_id'],

      'name' =>
      $row['name'],

      'address' =>
      $row['address'],

      'postal_code' =>
      $row['postal_code'],

      'locality' =>
      $row['locality'],

      'municipality' =>
      $row['municipality'],

      'province' =>
      $row['province'],

      'price' =>
      (float) $row['price'],
    ];
  }

  /*
     * ========================================================
     * FIN: TOP 10 MÁS BARATAS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: RESULTADO
     * ========================================================
     */

  return [

    'snapshot' =>
    $snapshot,

    'stats' =>
    $stats,

    'cheapest_stations' =>
    $cheapestStations,
  ];

  /*
     * ========================================================
     * FIN: RESULTADO
     * ========================================================
     */
}

/*
 * ============================================================
 * FIN: ESTADÍSTICAS DEL MUNICIPIO
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: TODAS LAS GASOLINERAS DEL MUNICIPIO
 * ============================================================
 */

/**
 * Devuelve todas las estaciones de un municipio
 * de forma paginada.
 *
 * IMPORTANTE:
 *
 * Incluye también estaciones que no tengan
 * precio para el combustible seleccionado.
 *
 * Las que sí tienen precio aparecen primero,
 * ordenadas de más barata a más cara.
 */
function getMunicipalityStationsPage(
  PDO $pdo,
  string $provinceDbName,
  string $municipalityDbName,
  string $fuelCode,
  int $page = 1,
  int $perPage = MUNICIPALITY_STATIONS_PER_PAGE
): array {

  /*
     * ========================================================
     * INICIO: VALIDAR PAGINACIÓN
     * ========================================================
     */

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
        MUNICIPALITY_STATIONS_MAX_PER_PAGE
      )
    );

  /*
     * ========================================================
     * FIN: VALIDAR PAGINACIÓN
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: CONTAR TODAS LAS ESTACIONES
     * ========================================================
     */

  $stmtCount =
    $pdo->prepare(
      'SELECT COUNT(*)

             FROM stations

             WHERE province = :province
               AND municipality = :municipality'
    );


  $stmtCount->execute([

    'province' =>
    $provinceDbName,

    'municipality' =>
    $municipalityDbName,
  ]);


  $total =
    (int) $stmtCount->fetchColumn();

  /*
     * ========================================================
     * FIN: CONTAR TODAS LAS ESTACIONES
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: CALCULAR PÁGINAS
     * ========================================================
     */

  $totalPages =
    $total > 0
    ? (int) ceil(
      $total / $perPage
    )
    : 0;


  if ($totalPages > 0) {

    $page =
      min(
        $page,
        $totalPages
      );
  } else {

    $page = 1;
  }


  $offset =
    ($page - 1)
    * $perPage;


  $from =
    $total > 0
    ? $offset + 1
    : 0;


  $to =
    $total > 0
    ? min(
      $offset + $perPage,
      $total
    )
    : 0;

  /*
     * ========================================================
     * FIN: CALCULAR PÁGINAS
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: ÚLTIMO SNAPSHOT
     * ========================================================
     */

  $snapshot =
    getMunicipalityLatestSnapshot(
      $pdo
    );


  if ($snapshot === null) {

    return [

      'stations' =>
      [],

      'total' =>
      $total,

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
     * ========================================================
     * FIN: ÚLTIMO SNAPSHOT
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: CONSULTAR ESTACIONES
     * ========================================================
     *
     * LEFT JOIN:
     *
     * permite que una gasolinera aparezca aunque
     * no tenga precio para el combustible elegido.
     */

  $sql = '
        SELECT
            s.id,
            s.external_id,
            s.name,
            s.address,
            s.postal_code,
            s.locality,
            s.municipality,
            s.province,
            p.price

        FROM stations s

        LEFT JOIN prices p
            ON p.station_id = s.id
           AND p.snapshot_id = :snapshot_id
           AND p.fuel_code = :fuel_code

        WHERE s.province = :province
          AND s.municipality = :municipality

        ORDER BY

            CASE
                WHEN p.price IS NULL
                    THEN 1
                ELSE 0
            END,

            p.price ASC,
            s.name ASC,
            s.address ASC,
            s.external_id ASC

        LIMIT ' . $perPage . '

        OFFSET ' . $offset;


  $stmt =
    $pdo->prepare(
      $sql
    );


  $stmt->execute([

    'snapshot_id' =>
    $snapshot['id'],

    'fuel_code' =>
    $fuelCode,

    'province' =>
    $provinceDbName,

    'municipality' =>
    $municipalityDbName,
  ]);


  $rows =
    $stmt->fetchAll();

  /*
     * ========================================================
     * FIN: CONSULTAR ESTACIONES
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: NORMALIZAR ESTACIONES
     * ========================================================
     */

  $stations = [];


  foreach ($rows as $row) {

    $stations[] = [

      'id' =>
      (int) $row['id'],

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

      'municipality' =>
      $row['municipality'],

      'province' =>
      $row['province'],

      'price' =>
      $row['price'] !== null
        ? (float) $row['price']
        : null,
    ];
  }

  /*
     * ========================================================
     * FIN: NORMALIZAR ESTACIONES
     * ========================================================
     */


  /*
     * ========================================================
     * INICIO: RESULTADO
     * ========================================================
     */

  return [

    'stations' =>
    $stations,

    'total' =>
    $total,

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

  /*
     * ========================================================
     * FIN: RESULTADO
     * ========================================================
     */
}

/*
 * ============================================================
 * FIN: TODAS LAS GASOLINERAS DEL MUNICIPIO
 * ============================================================
 */