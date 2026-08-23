<?php

declare(strict_types=1);


/*
 * ============================================================
 * INICIO: DEPENDENCIAS
 * ============================================================
 */

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/helpers.php';

/*
 * ============================================================
 * FIN: DEPENDENCIAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: CONFIGURACIÓN
 * ============================================================
 */

$baseUrl =
  'https://clsanpedro.com';


header(
  'Content-Type: application/xml; charset=utf-8'
);

/*
 * ============================================================
 * FIN: CONFIGURACIÓN
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: RECOPILAR URLs
 * ============================================================
 */

$urls = [];


/*
 * ========================================================
 * INICIO: HOME
 * ========================================================
 */

$urls[] = [

  'loc' =>
  $baseUrl . '/',

  'changefreq' =>
  'daily',

  'priority' =>
  '1.0',
];

/*
 * ========================================================
 * FIN: HOME
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: ÍNDICE GENERAL DE GASOLINERAS
 * ========================================================
 */

$urls[] = [

  'loc' =>
  $baseUrl
    . '/gasolineras/',

  'changefreq' =>
  'daily',

  'priority' =>
  '0.9',
];

/*
 * ========================================================
 * FIN: ÍNDICE GENERAL DE GASOLINERAS
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: PROVINCIAS
 * ========================================================
 */

$stmtProvinces =
  $pdo->query(
    'SELECT DISTINCT
            province

         FROM stations

         WHERE province IS NOT NULL
           AND province <> \'\'

         ORDER BY
            province ASC'
  );


$provinces =
  $stmtProvinces->fetchAll();


foreach (
  $provinces
  as $provinceRow
) {

  $provinceName =
    $provinceRow['province'];


  $provinceSlug =
    slugify(
      $provinceName
    );


  $urls[] = [

    'loc' =>
    $baseUrl
      . '/gasolineras/'
      . $provinceSlug
      . '/',

    'changefreq' =>
    'daily',

    'priority' =>
    '0.8',
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

$stmtMunicipalities =
  $pdo->query(
    'SELECT DISTINCT
            province,
            municipality

         FROM stations

         WHERE province IS NOT NULL
           AND province <> \'\'
           AND municipality IS NOT NULL
           AND municipality <> \'\'

         ORDER BY
            province ASC,
            municipality ASC'
  );


$municipalities =
  $stmtMunicipalities->fetchAll();


foreach (
  $municipalities
  as $municipalityRow
) {

  $provinceSlug =
    slugify(
      $municipalityRow['province']
    );


  $municipalitySlug =
    slugify(
      $municipalityRow['municipality']
    );


  $urls[] = [

    'loc' =>
    $baseUrl
      . '/gasolineras/'
      . $provinceSlug
      . '/'
      . $municipalitySlug
      . '/',

    'changefreq' =>
    'daily',

    'priority' =>
    '0.7',
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

$stmtStations =
  $pdo->query(
    'SELECT
            external_id,
            name

         FROM stations

         WHERE external_id IS NOT NULL
           AND name IS NOT NULL
           AND name <> \'\'

         ORDER BY
            external_id ASC'
  );


$stations =
  $stmtStations->fetchAll();


foreach (
  $stations
  as $stationRow
) {

  $stationSlug =
    slugify(
      $stationRow['name']
    );


  $urls[] = [

    'loc' =>
    $baseUrl
      . '/gasolinera/'
      . (int) $stationRow['external_id']
      . '-'
      . $stationSlug
      . '/',

    'changefreq' =>
    'daily',

    'priority' =>
    '0.6',
  ];
}

/*
 * ========================================================
 * FIN: GASOLINERAS
 * ========================================================
 */


/*
 * ============================================================
 * FIN: RECOPILAR URLs
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: GENERAR XML
 * ============================================================
 */

echo
'<?xml version="1.0" encoding="UTF-8"?>'
  . PHP_EOL;


echo
'<urlset '
  . 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
  . PHP_EOL;


foreach (
  $urls
  as $url
) {

  echo
  '    <url>'
    . PHP_EOL;


  echo
  '        <loc>'
    . htmlspecialchars(
      $url['loc'],
      ENT_XML1,
      'UTF-8'
    )
    . '</loc>'
    . PHP_EOL;


  echo
  '        <changefreq>'
    . htmlspecialchars(
      $url['changefreq'],
      ENT_XML1,
      'UTF-8'
    )
    . '</changefreq>'
    . PHP_EOL;


  echo
  '        <priority>'
    . htmlspecialchars(
      $url['priority'],
      ENT_XML1,
      'UTF-8'
    )
    . '</priority>'
    . PHP_EOL;


  echo
  '    </url>'
    . PHP_EOL;
}


echo
'</urlset>'
  . PHP_EOL;

/*
 * ============================================================
 * FIN: GENERAR XML
 * ============================================================
 */