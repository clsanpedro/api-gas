<?php

declare(strict_types=1);


/*
 * ============================================================
 * INICIO: DEPENDENCIAS
 * ============================================================
 */

require_once __DIR__ . '/../app/home-data.php';
require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/provinces-data.php';
require_once __DIR__ . '/../app/province-data.php';
require_once __DIR__ . '/../app/municipality-data.php';
require_once __DIR__ . '/../app/station-data.php';
require_once __DIR__ . '/../app/history-data.php';
require_once __DIR__ . '/../app/market-data.php';
require_once __DIR__ . '/../app/search-data.php';
require_once __DIR__ . '/../app/fuels.php';

/*
 * ============================================================
 * FIN: DEPENDENCIAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: FUNCIONES AUXILIARES
 * ============================================================
 */

function e(
  string|int|float|null $value
): string {

  return htmlspecialchars(
    (string) (
      $value ?? ''
    ),
    ENT_QUOTES,
    'UTF-8'
  );
}


/**
 * Construye una URL añadiendo parámetros GET.
 */
function buildQueryUrl(
  string $path,
  array $params
): string {

  $params =
    array_filter(
      $params,
      static fn(
        mixed $value
      ): bool =>
      $value !== null
        && $value !== ''
    );


  if ($params === []) {
    return $path;
  }


  return
    $path
    . '?'
    . http_build_query(
      $params,
      '',
      '&',
      PHP_QUERY_RFC3986
    );
}

/*
 * ============================================================
 * FIN: FUNCIONES AUXILIARES
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: RUTAS Y ASSETS
 * ============================================================
 */

$basePath =
  rtrim(
    str_replace(
      '\\',
      '/',
      dirname(
        $_SERVER['SCRIPT_NAME']
      )
    ),
    '/'
  );


if (
  $basePath === '.'
  || $basePath === '/'
) {
  $basePath = '';
}


$homeUrl =
  $basePath !== ''
  ? $basePath . '/'
  : '/';

$gasStationsUrl =
  $basePath
  . '/gasolineras/';

$searchUrl =
  $basePath
  . '/buscar/';

$cssFile = __DIR__ . '/css/styles.css';
$jsFile = __DIR__ . '/js/app.js';

$cssVersion =
  is_file($cssFile)
  ? (string) filemtime($cssFile)
  : '1';

$jsVersion =
  is_file($jsFile)
  ? (string) filemtime($jsFile)
  : '1';

$cssUrl =
  $basePath
  . '/css/styles.css?v='
  . rawurlencode($cssVersion);

$jsUrl =
  $basePath
  . '/js/app.js?v='
  . rawurlencode($jsVersion);

/*
 * ============================================================
 * FIN: RUTAS Y ASSETS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: ANALIZAR URL
 * ============================================================
 */

$path =
  parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
  )
  ?? '/';


if (
  $basePath !== ''
  && str_starts_with(
    $path,
    $basePath
  )
) {

  $path =
    substr(
      $path,
      strlen(
        $basePath
      )
    );
}


$path =
  trim(
    $path,
    '/'
  );


$segments =
  $path === ''
  ? []
  : explode(
    '/',
    $path
  );

/*
 * ============================================================
 * FIN: ANALIZAR URL
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: ROUTER
 * ============================================================
 */

$pageType = 'home';


if (
  isset($segments[0])
  && $segments[0] === 'buscar'
  && count($segments) === 1
) {
  $pageType = 'search';
}


if (
  isset($segments[0])
  && $segments[0] === 'gasolineras'
  && count($segments) === 1
) {
  $pageType = 'provinces';
}


if (
  isset(
    $segments[0],
    $segments[1]
  )
  && $segments[0]
  === 'gasolineras'
  && count($segments) === 2
) {
  $pageType = 'province';
}


if (
  isset(
    $segments[0],
    $segments[1],
    $segments[2]
  )
  && $segments[0]
  === 'gasolineras'
  && count($segments) === 3
) {
  $pageType =
    'municipality';
}


if (
  isset(
    $segments[0],
    $segments[1]
  )
  && $segments[0]
  === 'gasolinera'
  && count($segments) === 2
) {
  $pageType =
    'station';
}


if (
  $segments !== []
  && $pageType === 'home'
) {
  $pageType = '404';
}

/*
 * ============================================================
 * FIN: ROUTER
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: COMBUSTIBLES
 * ============================================================
 */

$fuelTypes = getFuelTypes();


$selectedFuel =
  $_GET['fuel']
  ?? 'gasolina_95_e5';


if (
  !array_key_exists(
    $selectedFuel,
    $fuelTypes
  )
) {

  $selectedFuel =
    'gasolina_95_e5';
}


$selectedFuelName =
  $fuelTypes[$selectedFuel];

$homeData =
  getHomeData(
    $pdo,
    $selectedFuel
  );

$homeFuelHistory =
  getNationalFuelHistory(
    $pdo,
    $selectedFuel,
    365
  );

$homeBrentHistory =
  getBrentEurHistory(
    $pdo,
    365
  );

/*
 * ============================================================
 * FIN: COMBUSTIBLES
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BUSCADOR
 * ============================================================
 */

$searchQuery = '';
$searchResults = null;
$searchTooShort = false;

$searchPage =
  max(
    1,
    (int) (
      $_GET['page']
      ?? 1
    )
  );


if ($pageType === 'search') {

  $searchQuery =
    normalizeSearchQuery(
      (string) (
        $_GET['q']
        ?? ''
      )
    );


  if (
    $searchQuery !== ''
    && mb_strlen(
      $searchQuery,
      'UTF-8'
    ) < 2
  ) {

    $searchTooShort =
      true;
  } elseif (
    $searchQuery !== ''
  ) {

    $searchResults =
      globalSearch(
        $pdo,
        $searchQuery,
        $searchPage,
        SEARCH_RESULTS_PER_PAGE
      );


    $searchPage =
      $searchResults['page'];
  }
}

/*
 * ============================================================
 * FIN: BUSCADOR
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: RESOLVER PROVINCIA
 * ============================================================
 */

$province = null;


if (
  $pageType === 'province'
  || $pageType
  === 'municipality'
) {

  $province =
    getProvinceBySlug(
      $pdo,
      $segments[1]
    );


  if ($province === null) {
    $pageType = '404';
  }
}

/*
 * ============================================================
 * FIN: RESOLVER PROVINCIA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: RESOLVER MUNICIPIO
 * ============================================================
 */

$municipality = null;


if (
  $pageType === 'municipality'
  && $province !== null
) {

  $municipality =
    getMunicipalityBySlug(
      $pdo,
      $province['db_name'],
      $segments[2]
    );


  if ($municipality === null) {
    $pageType = '404';
  }
}

/*
 * ============================================================
 * FIN: RESOLVER MUNICIPIO
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: RESOLVER GASOLINERA
 * ============================================================
 */

$station = null;
$stationData = null;


if ($pageType === 'station') {

  $stationSegment =
    $segments[1];


  if (
    preg_match(
      '/^(\d+)(?:-(.*))?$/',
      $stationSegment,
      $matches
    ) !== 1
  ) {

    $pageType =
      '404';
  } else {

    $externalId =
      (int) $matches[1];

    $requestedSlug =
      $matches[2]
      ?? '';


    $stationData =
      getStationData(
        $pdo,
        $externalId
      );


    if (
      $stationData === null
    ) {

      $pageType =
        '404';
    } else {

      $station =
        $stationData['station'];


      /*
             * Redirección a la URL canónica de la estación.
             */
      if (
        $requestedSlug
        !== $station['slug']
      ) {

        $url =
          $basePath
          . '/gasolinera/'
          . $station['external_id']
          . '-'
          . $station['slug']
          . '/';


        header(
          'Location: '
            . $url,
          true,
          301
        );

        exit;
      }
    }
  }
}

/*
 * ============================================================
 * FIN: RESOLVER GASOLINERA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: DATOS HOME
 * ============================================================
 */

$snapshot =
  $homeData['snapshot']
  ?? null;

$fuelData =
  $homeData['fuel']
  ?? null;

/*
 * ============================================================
 * FIN: DATOS HOME
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: DATOS PROVINCIAS
 * ============================================================
 */

$provincesData = [];


if (
  $pageType
  === 'provinces'
) {

  $provincesData =
    getAllProvinces(
      $pdo
    );
}

/*
 * ============================================================
 * FIN: DATOS PROVINCIAS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: DATOS PROVINCIA
 * ============================================================
 */

$provinceData = null;


if (
  $pageType === 'province'
  && $province !== null
) {

  $provinceData =
    getProvinceStats(
      $pdo,
      $province['db_name'],
      $selectedFuel
    );
}

/*
 * ============================================================
 * FIN: DATOS PROVINCIA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: DATOS MUNICIPIO
 * ============================================================
 */

$municipalityData = null;
$municipalityStations = null;

$municipalityPage =
  max(
    1,
    (int) (
      $_GET['page']
      ?? 1
    )
  );


if (
  $pageType
  === 'municipality'
  && $province !== null
  && $municipality !== null
) {

  /*
     * Estadísticas y Top 10.
     */
  $municipalityData =
    getMunicipalityStats(
      $pdo,
      $province['db_name'],
      $municipality['db_name'],
      $selectedFuel
    );


  /*
     * Listado completo paginado.
     */
  $municipalityStations =
    getMunicipalityStationsPage(
      $pdo,
      $province['db_name'],
      $municipality['db_name'],
      $selectedFuel,
      $municipalityPage,
      MUNICIPALITY_STATIONS_PER_PAGE
    );


  /*
     * IMPORTANTE:
     *
     * getMunicipalityStationsPage() corrige páginas
     * inválidas o superiores al máximo disponible.
     *
     * Esta es la página que utilizaremos también
     * para construir el canonical.
     */
  $municipalityPage =
    $municipalityStations['page'];
}

/*
 * ============================================================
 * FIN: DATOS MUNICIPIO
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: HISTÓRICO GASOLINERA
 * ============================================================
 */

$stationHistory = [];
$stationHistorySummary = null;


if (
  $pageType === 'station'
  && $station !== null
) {

  $stationHistory =
    getStationFuelHistory(
      $pdo,
      $station['id'],
      $selectedFuel,
      365
    );


  $stationHistorySummary =
    getStationFuelHistorySummary(
      $pdo,
      $station['id'],
      $selectedFuel
    );
}

/*
 * ============================================================
 * FIN: HISTÓRICO GASOLINERA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: SEO
 * ============================================================
 */

$title =
  'Precio de gasolina y carburantes en España | PrecioCarburante';

$description =
  'Consulta precios de gasolina y carburantes en España. '
  . 'Compara precios y encuentra las gasolineras más baratas.';

$canonicalPath = '/';


/*
 * ========================================================
 * INICIO: SEO BUSCADOR
 * ========================================================
 */

if ($pageType === 'search') {

  $title =
    $searchQuery !== ''
    ? 'Resultados para '
    . $searchQuery
    . ' | PrecioCarburante'
    : 'Buscar gasolineras | PrecioCarburante';

  $description =
    'Busca gasolineras, municipios, provincias, '
    . 'direcciones y códigos postales.';

  $canonicalPath =
    '/buscar/';
}

/*
 * ========================================================
 * FIN: SEO BUSCADOR
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: SEO ÍNDICE PROVINCIAS
 * ========================================================
 */

if (
  $pageType
  === 'provinces'
) {

  $title =
    'Gasolineras por provincia en España | PrecioCarburante';

  $description =
    'Consulta las gasolineras de España por provincia.';

  $canonicalPath =
    '/gasolineras/';
}

/*
 * ========================================================
 * FIN: SEO ÍNDICE PROVINCIAS
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: SEO PROVINCIA
 * ========================================================
 */

if (
  $pageType === 'province'
  && $province !== null
) {

  $title =
    'Gasolineras más baratas de '
    . $province['name']
    . ' | PrecioCarburante';

  $description =
    'Consulta y compara los precios de carburantes '
    . 'en las gasolineras de '
    . $province['name']
    . '.';

  $canonicalPath =
    '/gasolineras/'
    . $province['slug']
    . '/';
}

/*
 * ========================================================
 * FIN: SEO PROVINCIA
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: SEO MUNICIPIO
 * ========================================================
 */

if (
  $pageType
  === 'municipality'
  && $province !== null
  && $municipality !== null
) {

  $title =
    'Gasolineras más baratas de '
    . $municipality['name']
    . ' | PrecioCarburante';

  $description =
    'Consulta todas las gasolineras y compara '
    . 'los precios de carburantes en '
    . $municipality['name']
    . ', '
    . $province['name']
    . '.';


  /*
     * Canonical base del municipio.
     */
  $canonicalPath =
    '/gasolineras/'
    . $province['slug']
    . '/'
    . $municipality['slug']
    . '/';


  /*
     * ====================================================
     * INICIO: CANONICAL PAGINACIÓN MUNICIPIO
     * ====================================================
     *
     * Página 1:
     *
     * /gasolineras/barcelona/barcelona/
     *
     *
     * Página 2:
     *
     * /gasolineras/barcelona/barcelona/?page=2
     *
     *
     * IMPORTANTE:
     *
     * No incluimos ?fuel= en el canonical.
     *
     * De esta forma evitamos multiplicar páginas
     * indexables por cada tipo de combustible.
     * ====================================================
     */

  if (
    $municipalityPage > 1
  ) {

    $canonicalPath .=
      '?page='
      . $municipalityPage;
  }

  /*
     * ====================================================
     * FIN: CANONICAL PAGINACIÓN MUNICIPIO
     * ====================================================
     */
}

/*
 * ========================================================
 * FIN: SEO MUNICIPIO
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: SEO GASOLINERA
 * ========================================================
 */

if (
  $pageType === 'station'
  && $station !== null
) {

  $title =
    $station['name']
    . ' en '
    . $station['municipality_name']
    . ' | PrecioCarburante';

  $description =
    'Consulta los precios actuales de carburantes de '
    . $station['name']
    . ' en '
    . $station['municipality_name']
    . ', '
    . $station['province_name']
    . '.';

  $canonicalPath =
    '/gasolinera/'
    . $station['external_id']
    . '-'
    . $station['slug']
    . '/';
}

/*
 * ========================================================
 * FIN: SEO GASOLINERA
 * ========================================================
 */


/*
 * ========================================================
 * INICIO: SEO 404
 * ========================================================
 */

if ($pageType === '404') {

  http_response_code(
    404
  );

  $title =
    'Página no encontrada | PrecioCarburante';

  $description =
    'La página que buscas no existe o ha cambiado de dirección.';
}

/*
 * ========================================================
 * FIN: SEO 404
 * ========================================================
 */

/*
 * ============================================================
 * FIN: SEO
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: URL CANÓNICA ABSOLUTA
 * ============================================================
 */

$canonicalUrl =
  'https://clsanpedro.com'
  . $canonicalPath;

/*
 * ============================================================
 * FIN: URL CANÓNICA ABSOLUTA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: BREADCRUMBS JSON-LD
 * ============================================================
 */

$breadcrumbItems = [];


if ($pageType === 'home') {

  $breadcrumbItems[] = [

    'name' =>
    'Inicio',

    'url' =>
    'https://clsanpedro.com/',
  ];
}


if ($pageType === 'search') {

  $breadcrumbItems = [

    [
      'name' =>
      'Inicio',

      'url' =>
      'https://clsanpedro.com/',
    ],

    [
      'name' =>
      'Buscar',

      'url' =>
      'https://clsanpedro.com/buscar/',
    ],
  ];
}


if (
  $pageType
  === 'provinces'
) {

  $breadcrumbItems = [

    [
      'name' =>
      'Inicio',

      'url' =>
      'https://clsanpedro.com/',
    ],

    [
      'name' =>
      'Gasolineras',

      'url' =>
      'https://clsanpedro.com/gasolineras/',
    ],
  ];
}


if (
  $pageType === 'province'
  && $province !== null
) {

  $breadcrumbItems = [

    [
      'name' =>
      'Inicio',

      'url' =>
      'https://clsanpedro.com/',
    ],

    [
      'name' =>
      'Gasolineras',

      'url' =>
      'https://clsanpedro.com/gasolineras/',
    ],

    [
      'name' =>
      $province['name'],

      'url' =>
      'https://clsanpedro.com/gasolineras/'
        . $province['slug']
        . '/',
    ],
  ];
}


if (
  $pageType
  === 'municipality'
  && $province !== null
  && $municipality !== null
) {

  $breadcrumbItems = [

    [
      'name' =>
      'Inicio',

      'url' =>
      'https://clsanpedro.com/',
    ],

    [
      'name' =>
      'Gasolineras',

      'url' =>
      'https://clsanpedro.com/gasolineras/',
    ],

    [
      'name' =>
      $province['name'],

      'url' =>
      'https://clsanpedro.com/gasolineras/'
        . $province['slug']
        . '/',
    ],

    [
      'name' =>
      $municipality['name'],

      /*
             * El breadcrumb representa el municipio,
             * no una página concreta de paginación.
             *
             * Por eso mantenemos aquí la URL base.
             */
      'url' =>
      'https://clsanpedro.com/gasolineras/'
        . $province['slug']
        . '/'
        . $municipality['slug']
        . '/',
    ],
  ];
}


if (
  $pageType === 'station'
  && $station !== null
) {

  $breadcrumbItems = [

    [
      'name' =>
      'Inicio',

      'url' =>
      'https://clsanpedro.com/',
    ],

    [
      'name' =>
      'Gasolineras',

      'url' =>
      'https://clsanpedro.com/gasolineras/',
    ],

    [
      'name' =>
      $station['province_name'],

      'url' =>
      'https://clsanpedro.com/gasolineras/'
        . $station['province_slug']
        . '/',
    ],

    [
      'name' =>
      $station['municipality_name'],

      'url' =>
      'https://clsanpedro.com/gasolineras/'
        . $station['province_slug']
        . '/'
        . $station['municipality_slug']
        . '/',
    ],

    [
      'name' =>
      $station['name'],

      'url' =>
      'https://clsanpedro.com/gasolinera/'
        . $station['external_id']
        . '-'
        . $station['slug']
        . '/',
    ],
  ];
}

/*
 * ============================================================
 * FIN: BREADCRUMBS JSON-LD
 * ============================================================
 */

?>
<!DOCTYPE html>

<html lang="es">

<head>

  <!-- ======================================================
         INICIO: META
         ====================================================== -->

  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1">

  <title><?= e($title) ?></title>

  <meta
    name="description"
    content="<?= e($description) ?>">


  <?php if (
    $pageType === '404'
    || $pageType === 'search'
  ): ?>

    <meta
      name="robots"
      content="noindex, follow">

  <?php else: ?>

    <meta
      name="robots"
      content="index, follow">

  <?php endif; ?>


  <!-- ======================================================
         INICIO: CANONICAL
         ====================================================== -->

  <link
    rel="canonical"
    href="<?= e($canonicalUrl) ?>">

  <!-- ======================================================
         FIN: CANONICAL
         ====================================================== -->


  <!-- ======================================================
         INICIO: OPEN GRAPH
         ====================================================== -->

  <meta
    property="og:type"
    content="website">

  <meta
    property="og:title"
    content="<?= e($title) ?>">

  <meta
    property="og:description"
    content="<?= e($description) ?>">

  <meta
    property="og:url"
    content="<?= e($canonicalUrl) ?>">

  <meta
    property="og:locale"
    content="es_ES">

  <meta
    name="twitter:card"
    content="summary">

  <!-- ======================================================
         FIN: OPEN GRAPH
         ====================================================== -->


  <!-- ======================================================
         INICIO: FUENTES
         ====================================================== -->

  <link
    rel="preconnect"
    href="https://fonts.googleapis.com">

  <link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <!-- ======================================================
         FIN: FUENTES
         ====================================================== -->


  <!-- ======================================================
         INICIO: CSS
         ====================================================== -->

  <link
    rel="stylesheet"
    href="<?= e($cssUrl) ?>">

  <!-- ======================================================
         FIN: CSS
         ====================================================== -->


  <!-- ======================================================
         INICIO: JSON-LD BREADCRUMBS
         ====================================================== -->

  <?php if (
    !empty($breadcrumbItems)
  ): ?>

    <script type="application/ld+json">
      <?= json_encode(
        [
          '@context' =>
          'https://schema.org',

          '@type' =>
          'BreadcrumbList',

          'itemListElement' =>
          array_map(
            static function (
              array $item,
              int $index
            ): array {

              return [

                '@type' =>
                'ListItem',

                'position' =>
                $index + 1,

                'name' =>
                $item['name'],

                'item' =>
                $item['url'],
              ];
            },
            $breadcrumbItems,
            array_keys(
              $breadcrumbItems
            )
          ),
        ],
        JSON_UNESCAPED_SLASHES
          | JSON_UNESCAPED_UNICODE
      ) ?>
    </script>

  <?php endif; ?>

  <!-- ======================================================
         FIN: JSON-LD BREADCRUMBS
         ====================================================== -->

</head>


<body>

  <div class="page">


    <!-- ======================================================
         INICIO: HEADER
         ====================================================== -->

    <?php require __DIR__ . '/../app/views/layout/header.php'; ?>

    <!-- ======================================================
         FIN: HEADER
         ====================================================== -->


    <!-- ======================================================
         INICIO: MAIN
         ====================================================== -->

    <main class="site-main">

      <div class="container">


        <!-- ==================================================
        INICIO: HOME
        ================================================== -->

        <?php if (
          $pageType === 'home'
        ): ?>

          <?php require __DIR__ . '/../app/views/home.php'; ?>


          <!-- ==================================================
        FIN: HOME
        ================================================== -->


          <!-- ==================================================
                 INICIO: BUSCADOR
                 ================================================== -->

        <?php elseif (
          $pageType === 'search'
        ): ?>

          <nav
            class="breadcrumbs"
            aria-label="Migas de pan">

            <a href="<?= e($homeUrl) ?>">
              Inicio
            </a>

            <span aria-hidden="true">
              /
            </span>

            <span aria-current="page">
              Buscar
            </span>

          </nav>


          <nav
            class="context-nav"
            aria-label="Navegación secundaria">

            <a
              href="<?= e($homeUrl) ?>"
              class="
                            context-nav-link
                            context-nav-link-primary
                        ">
              ← Volver al inicio
            </a>

          </nav>


          <h1>
            Buscar gasolineras
          </h1>

          <p class="intro">
            Busca estaciones de servicio, municipios,
            provincias, direcciones o códigos postales.
          </p>


          <form
            action="<?= e($searchUrl) ?>"
            method="get"
            class="search-form">

            <label
              for="search-query"
              class="search-label">
              ¿Qué estás buscando?
            </label>


            <div class="search-form-row">

              <input
                type="search"
                name="q"
                id="search-query"
                class="search-input"
                value="<?= e($searchQuery) ?>"
                placeholder="Ej. Repsol, Mataró, 08001..."
                minlength="2"
                autocomplete="off"
                data-search-autocomplete
                required>

              <button
                type="submit"
                class="button-primary search-submit">
                Buscar
              </button>

            </div>


            <div
              class="search-autocomplete"
              data-search-autocomplete-results
              hidden></div>

          </form>


          <?php if (
            $searchTooShort
          ): ?>

            <section>

              <div class="card">

                <strong>
                  Escribe al menos 2 caracteres
                </strong>

                <p class="history-message">
                  Así podremos ofrecer resultados
                  más útiles.
                </p>

              </div>

            </section>


          <?php elseif (
            $searchQuery !== ''
            && $searchResults !== null
          ): ?>

            <p class="search-results-summary">

              <?= number_format(
                $searchResults['stations_total'],
                0,
                ',',
                '.'
              ) ?>

              gasolineras encontradas para

              <strong>
                “<?= e($searchQuery) ?>”
              </strong>

            </p>


            <?php

            $hasProvinceResults =
              !empty($searchResults['provinces']);

            $hasMunicipalityResults =
              !empty($searchResults['municipalities']);

            $hasGeoResults =
              $hasProvinceResults
              || $hasMunicipalityResults;

            ?>


            <?php if (
              $searchResults['stations_total'] === 0
              && !$hasGeoResults
            ): ?>

              <section>

                <div class="card">

                  <strong>
                    Sin resultados
                  </strong>

                  <p class="history-message">

                    No hemos encontrado resultados
                    para “<?= e($searchQuery) ?>”.

                  </p>

                </div>

              </section>


            <?php else: ?>

              <div
                class="
                                search-results-layout
                                <?= $hasGeoResults
                                  ? 'has-sidebar'
                                  : 'no-sidebar'
                                ?>
                            ">


                <?php if (
                  $hasGeoResults
                ): ?>

                  <aside
                    class="search-results-sidebar">


                    <?php if (
                      $hasProvinceResults
                    ): ?>

                      <section
                        class="search-panel search-geo-panel">

                        <header
                          class="search-panel-header">

                          <h2>
                            Provincias
                          </h2>

                          <span
                            class="search-count-badge">
                            <?= count(
                              $searchResults['provinces']
                            ) ?>
                          </span>

                        </header>


                        <div
                          class="search-panel-body search-geo-list">

                          <?php foreach (
                            $searchResults['provinces']
                            as $result
                          ): ?>

                            <?php

                            $resultUrl =
                              $basePath
                              . '/gasolineras/'
                              . $result['slug']
                              . '/';

                            ?>

                            <a
                              href="<?= e($resultUrl) ?>"
                              class="search-geo-link">

                              <span>

                                <strong>
                                  <?= e(
                                    $result['name']
                                  ) ?>
                                </strong>

                                <small>

                                  <?= number_format(
                                    $result['station_count'],
                                    0,
                                    ',',
                                    '.'
                                  ) ?>

                                  estaciones

                                </small>

                              </span>

                              <span
                                class="search-geo-arrow"
                                aria-hidden="true">
                                →
                              </span>

                            </a>

                          <?php endforeach; ?>

                        </div>

                      </section>

                    <?php endif; ?>


                    <?php if (
                      $hasMunicipalityResults
                    ): ?>

                      <section
                        class="search-panel search-geo-panel">

                        <header
                          class="search-panel-header">

                          <h2>
                            Municipios
                          </h2>

                          <span
                            class="search-count-badge">
                            <?= count(
                              $searchResults['municipalities']
                            ) ?>
                          </span>

                        </header>


                        <div
                          class="search-panel-body search-geo-list">

                          <?php foreach (
                            $searchResults['municipalities']
                            as $result
                          ): ?>

                            <?php

                            $resultUrl =
                              $basePath
                              . '/gasolineras/'
                              . $result['province_slug']
                              . '/'
                              . $result['slug']
                              . '/';

                            ?>

                            <a
                              href="<?= e($resultUrl) ?>"
                              class="search-geo-link">

                              <span>

                                <strong>
                                  <?= e(
                                    $result['name']
                                  ) ?>
                                </strong>

                                <small>

                                  <?= e(
                                    $result['province_name']
                                  ) ?>

                                  ·

                                  <?= number_format(
                                    $result['station_count'],
                                    0,
                                    ',',
                                    '.'
                                  ) ?>

                                  estaciones

                                </small>

                              </span>

                              <span
                                class="search-geo-arrow"
                                aria-hidden="true">
                                →
                              </span>

                            </a>

                          <?php endforeach; ?>

                        </div>

                      </section>

                    <?php endif; ?>

                  </aside>

                <?php endif; ?>


                <div
                  class="search-results-main">

                  <section
                    class="search-panel search-stations-panel">

                    <header
                      class="search-panel-header">

                      <h2>
                        Gasolineras
                      </h2>

                      <span
                        class="search-count-badge">

                        <?= number_format(
                          $searchResults['stations_total'],
                          0,
                          ',',
                          '.'
                        ) ?>

                      </span>

                    </header>


                    <div
                      class="search-panel-body">

                      <?php if (
                        empty($searchResults['stations'])
                      ): ?>

                        <div
                          class="search-empty-result">

                          <strong>
                            No hay gasolineras
                          </strong>

                        </div>


                      <?php else: ?>

                        <div
                          class="search-station-list">

                          <?php foreach (
                            $searchResults['stations']
                            as $result
                          ): ?>

                            <?php

                            $resultUrl =
                              $basePath
                              . '/gasolinera/'
                              . $result['external_id']
                              . '-'
                              . $result['slug']
                              . '/';

                            ?>

                            <article
                              class="search-station-item">

                              <div
                                class="station-info">

                                <h3>

                                  <a
                                    href="<?= e($resultUrl) ?>"
                                    class="station-name-link">
                                    <?= e(
                                      $result['name']
                                    ) ?>
                                  </a>

                                </h3>

                                <p
                                  class="search-address">
                                  <?= e(
                                    $result['address']
                                  ) ?>
                                </p>

                                <p
                                  class="station-location">

                                  <?= e(
                                    $result['municipality_name']
                                  ) ?>,

                                  <?= e(
                                    $result['province_name']
                                  ) ?>

                                  <?php if (
                                    !empty($result['postal_code'])
                                  ): ?>

                                    ·

                                    <?= e(
                                      $result['postal_code']
                                    ) ?>

                                  <?php endif; ?>

                                </p>

                              </div>


                              <a
                                href="<?= e($resultUrl) ?>"
                                class="search-result-action">
                                Ver ficha →
                              </a>

                            </article>

                          <?php endforeach; ?>

                        </div>

                      <?php endif; ?>

                    </div>

                  </section>


                  <?php if (
                    $searchResults['total_pages'] > 1
                  ): ?>

                    <?php

                    $currentPage =
                      $searchResults['page'];

                    $totalPages =
                      $searchResults['total_pages'];

                    $firstPage =
                      max(
                        1,
                        $currentPage - 2
                      );

                    $lastPage =
                      min(
                        $totalPages,
                        $currentPage + 2
                      );

                    ?>


                    <nav
                      class="search-pagination"
                      aria-label="Páginas de resultados">

                      <?php if (
                        $currentPage > 1
                      ): ?>

                        <a
                          href="<?= e(
                                  buildQueryUrl(
                                    $searchUrl,
                                    [
                                      'q' =>
                                      $searchQuery,

                                      'page' =>
                                      $currentPage - 1,
                                    ]
                                  )
                                ) ?>"
                          class="
                                                    search-page-link
                                                    search-page-direction
                                                ">
                          ← Anterior
                        </a>

                      <?php endif; ?>


                      <?php for (
                        $number = $firstPage;
                        $number <= $lastPage;
                        $number++
                      ): ?>

                        <?php if (
                          $number === $currentPage
                        ): ?>

                          <span
                            class="
                                                        search-page-link
                                                        is-active
                                                    "
                            aria-current="page">
                            <?= $number ?>
                          </span>

                        <?php else: ?>

                          <a
                            href="<?= e(
                                    buildQueryUrl(
                                      $searchUrl,
                                      [
                                        'q' =>
                                        $searchQuery,

                                        'page' =>
                                        $number,
                                      ]
                                    )
                                  ) ?>"
                            class="search-page-link">
                            <?= $number ?>
                          </a>

                        <?php endif; ?>

                      <?php endfor; ?>


                      <?php if (
                        $currentPage
                        < $totalPages
                      ): ?>

                        <a
                          href="<?= e(
                                  buildQueryUrl(
                                    $searchUrl,
                                    [
                                      'q' =>
                                      $searchQuery,

                                      'page' =>
                                      $currentPage + 1,
                                    ]
                                  )
                                ) ?>"
                          class="
                                                    search-page-link
                                                    search-page-direction
                                                ">
                          Siguiente →
                        </a>

                      <?php endif; ?>

                    </nav>


                    <p
                      class="search-pagination-summary">

                      Mostrando

                      <?= number_format(
                        $searchResults['from'],
                        0,
                        ',',
                        '.'
                      ) ?>

                      a

                      <?= number_format(
                        $searchResults['to'],
                        0,
                        ',',
                        '.'
                      ) ?>

                      de

                      <?= number_format(
                        $searchResults['stations_total'],
                        0,
                        ',',
                        '.'
                      ) ?>

                      gasolineras

                    </p>

                  <?php endif; ?>

                </div>

              </div>

            <?php endif; ?>

          <?php endif; ?>


          <!-- ==================================================
                 FIN: BUSCADOR
                 ================================================== -->


          <!-- ==================================================
                 INICIO: PROVINCIAS
                 ================================================== -->

        <?php elseif (
          $pageType === 'provinces'
        ): ?>

          <nav
            class="breadcrumbs"
            aria-label="Migas de pan">

            <a href="<?= e($homeUrl) ?>">
              Inicio
            </a>

            <span>/</span>

            <span aria-current="page">
              Gasolineras
            </span>

          </nav>


          <nav
            class="context-nav"
            aria-label="Navegación secundaria">

            <a
              href="<?= e($homeUrl) ?>"
              class="
                            context-nav-link
                            context-nav-link-primary
                        ">
              ← Volver al inicio
            </a>

          </nav>


          <h1>
            Gasolineras por provincia en España
          </h1>

          <p class="intro">
            Selecciona una provincia para consultar
            sus precios, municipios y estaciones.
          </p>


          <section>

            <h2>
              Provincias
            </h2>

            <div class="municipality-grid">

              <?php foreach (
                $provincesData
                as $provinceItem
              ): ?>

                <?php

                $url =
                  $basePath
                  . '/gasolineras/'
                  . $provinceItem['slug']
                  . '/';

                ?>

                <a
                  href="<?= e($url) ?>"
                  class="municipality-link">

                  <span>

                    <?= e(
                      $provinceItem['name']
                    ) ?>

                    <small>

                      <?= number_format(
                        $provinceItem['station_count'],
                        0,
                        ',',
                        '.'
                      ) ?>

                      estaciones

                    </small>

                  </span>

                  <span
                    class="municipality-arrow">
                    →
                  </span>

                </a>

              <?php endforeach; ?>

            </div>

          </section>


          <!-- ==================================================
                 FIN: PROVINCIAS
                 ================================================== -->


          <!-- ==================================================
                 INICIO: PROVINCIA
                 ================================================== -->

        <?php elseif (
          $pageType === 'province'
          && $province !== null
          && $provinceData !== null
        ): ?>

          <nav
            class="breadcrumbs"
            aria-label="Migas de pan">

            <a href="<?= e($homeUrl) ?>">
              Inicio
            </a>

            <span>/</span>

            <a
              href="<?= e($gasStationsUrl) ?>">
              Gasolineras
            </a>

            <span>/</span>

            <span aria-current="page">
              <?= e(
                $province['name']
              ) ?>
            </span>

          </nav>


          <nav
            class="context-nav"
            aria-label="Navegación secundaria">

            <a
              href="<?= e($gasStationsUrl) ?>"
              class="
                            context-nav-link
                            context-nav-link-primary
                        ">
              ← Ver todas las provincias
            </a>

          </nav>


          <h1>

            Gasolineras más baratas de

            <?= e(
              $province['name']
            ) ?>

          </h1>


          <p class="intro">

            Consulta y compara los precios de carburantes
            en las estaciones de servicio de

            <?= e(
              $province['name']
            ) ?>.

          </p>


          <p class="updated-at">

            Última actualización:

            <?= e(
              $provinceData['snapshot']['api_date']
            ) ?>

          </p>


          <form
            method="get"
            class="fuel-selector">

            <label for="fuel">
              Combustible
            </label>

            <select
              name="fuel"
              id="fuel"
              onchange="this.form.submit()">

              <?php foreach (
                $fuelTypes
                as $code => $name
              ): ?>

                <option
                  value="<?= e($code) ?>"
                  <?= $code === $selectedFuel
                    ? 'selected'
                    : ''
                  ?>>
                  <?= e($name) ?>
                </option>

              <?php endforeach; ?>

            </select>

          </form>


          <section>

            <h2>
              <?= e(
                $selectedFuelName
              ) ?>
            </h2>

            <div class="stats">

              <div class="card">

                <strong>
                  Precio mínimo
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $provinceData['stats']['min_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Precio medio
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $provinceData['stats']['avg_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Precio máximo
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $provinceData['stats']['max_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Estaciones con precio
                </strong>

                <p>
                  <?= number_format(
                    $provinceData['stats']['stations_count'],
                    0,
                    ',',
                    '.'
                  ) ?>
                </p>

              </div>

            </div>

          </section>


          <?php

          $provinceStationCount =
            count(
              $provinceData['cheapest_stations']
            );

          ?>


          <section>

            <h2>

              <?php if (
                $provinceStationCount >= 10
              ): ?>

                Las 10 gasolineras más baratas

              <?php elseif (
                $provinceStationCount === 1
              ): ?>

                La gasolinera más barata

              <?php else: ?>

                Las
                <?= $provinceStationCount ?>
                gasolineras más baratas

              <?php endif; ?>

            </h2>


            <div class="station-list">

              <?php foreach (
                $provinceData['cheapest_stations']
                as $index => $item
              ): ?>

                <?php

                $url =
                  $basePath
                  . '/gasolinera/'
                  . $item['external_id']
                  . '-'
                  . slugify(
                    $item['name']
                  )
                  . '/';

                ?>

                <article
                  class="station-item">

                  <div
                    class="station-rank">
                    <?= $index + 1 ?>
                  </div>

                  <div
                    class="station-info">

                    <h3>

                      <a
                        href="<?= e($url) ?>"
                        class="station-name-link">
                        <?= e(
                          $item['name']
                        ) ?>
                      </a>

                    </h3>

                    <p>
                      <?= e(
                        $item['address']
                      ) ?>
                    </p>

                    <p
                      class="station-location">
                      <?= e(
                        displayName(
                          $item['municipality']
                        )
                      ) ?>
                    </p>

                  </div>

                  <div
                    class="station-price">
                    <?= e(
                      formatFuelPrice(
                        $item['price']
                      )
                    ) ?>
                  </div>

                </article>

              <?php endforeach; ?>

            </div>

          </section>


          <?php if (
            !empty($provinceData['municipalities'])
          ): ?>

            <section
              class="municipalities-section">

              <h2>

                Gasolineras por municipio en

                <?= e(
                  $province['name']
                ) ?>

              </h2>

              <div
                class="municipality-grid"
                data-municipality-list>

                <?php foreach (
                  $provinceData['municipalities']
                  as $item
                ): ?>

                  <?php

                  $url =
                    $basePath
                    . '/gasolineras/'
                    . $province['slug']
                    . '/'
                    . $item['slug']
                    . '/';


                  if (
                    $selectedFuel
                    !== 'gasolina_95_e5'
                  ) {

                    $url =
                      buildQueryUrl(
                        $url,
                        [
                          'fuel' =>
                          $selectedFuel,
                        ]
                      );
                  }

                  ?>

                  <a
                    href="<?= e($url) ?>"
                    class="municipality-link"
                    data-municipality-item>

                    <span>
                      <?= e(
                        $item['name']
                      ) ?>
                    </span>

                    <span
                      class="municipality-arrow">
                      →
                    </span>

                  </a>

                <?php endforeach; ?>

              </div>


              <button
                type="button"
                class="municipality-toggle"
                data-municipality-toggle
                aria-expanded="false"
                hidden>
                Ver todos los municipios
              </button>

            </section>

          <?php endif; ?>


          <!-- ==================================================
                 FIN: PROVINCIA
                 ================================================== -->


          <!-- ==================================================
                 INICIO: MUNICIPIO
                 ================================================== -->

        <?php elseif (
          $pageType
          === 'municipality'
          && $province !== null
          && $municipality !== null
          && $municipalityData !== null
          && $municipalityStations !== null
        ): ?>

          <?php

          $provinceUrl =
            $basePath
            . '/gasolineras/'
            . $province['slug']
            . '/';


          if (
            $selectedFuel
            !== 'gasolina_95_e5'
          ) {

            $provinceUrl =
              buildQueryUrl(
                $provinceUrl,
                [
                  'fuel' =>
                  $selectedFuel,
                ]
              );
          }


          $municipalityBaseUrl =
            $basePath
            . '/gasolineras/'
            . $province['slug']
            . '/'
            . $municipality['slug']
            . '/';

          ?>


          <nav
            class="breadcrumbs"
            aria-label="Migas de pan">

            <a href="<?= e($homeUrl) ?>">
              Inicio
            </a>

            <span>/</span>

            <a href="<?= e($gasStationsUrl) ?>">
              Gasolineras
            </a>

            <span>/</span>

            <a href="<?= e($provinceUrl) ?>">
              <?= e(
                $province['name']
              ) ?>
            </a>

            <span>/</span>

            <span aria-current="page">
              <?= e(
                $municipality['name']
              ) ?>
            </span>

          </nav>


          <nav
            class="context-nav"
            aria-label="Navegación secundaria">

            <a
              href="<?= e($provinceUrl) ?>"
              class="
                            context-nav-link
                            context-nav-link-primary
                        ">

              ← Volver a

              <?= e(
                $province['name']
              ) ?>

            </a>

            <a
              href="<?= e($gasStationsUrl) ?>"
              class="context-nav-link">
              Ver todas las provincias
            </a>

          </nav>


          <h1>

            Gasolineras más baratas de

            <?= e(
              $municipality['name']
            ) ?>

          </h1>


          <p class="intro">

            Consulta y compara los precios de carburantes
            en las estaciones de servicio de

            <?= e(
              $municipality['name']
            ) ?>,

            <?= e(
              $province['name']
            ) ?>.

          </p>


          <p class="updated-at">

            Última actualización:

            <?= e(
              $municipalityData['snapshot']['api_date']
            ) ?>

          </p>


          <!-- ==============================================
                     INICIO: SELECTOR DE COMBUSTIBLE
                     ============================================== -->

          <form
            method="get"
            class="fuel-selector">

            <label for="fuel">
              Combustible
            </label>

            <select
              name="fuel"
              id="fuel"
              onchange="this.form.submit()">

              <?php foreach (
                $fuelTypes
                as $code => $name
              ): ?>

                <option
                  value="<?= e($code) ?>"
                  <?= $code === $selectedFuel
                    ? 'selected'
                    : ''
                  ?>>
                  <?= e($name) ?>
                </option>

              <?php endforeach; ?>

            </select>

          </form>

          <!-- ==============================================
                     FIN: SELECTOR DE COMBUSTIBLE
                     ============================================== -->


          <!-- ==============================================
                     INICIO: ESTADÍSTICAS
                     ============================================== -->

          <section>

            <h2>
              <?= e(
                $selectedFuelName
              ) ?>
            </h2>


            <div class="stats">

              <div class="card">

                <strong>
                  Precio mínimo
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $municipalityData['stats']['min_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Precio medio
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $municipalityData['stats']['avg_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Precio máximo
                </strong>

                <p>
                  <?= e(
                    formatFuelPrice(
                      $municipalityData['stats']['max_price']
                    )
                  ) ?>
                </p>

              </div>


              <div class="card">

                <strong>
                  Estaciones con precio
                </strong>

                <p>

                  <?= number_format(
                    $municipalityData['stats']['stations_count'],
                    0,
                    ',',
                    '.'
                  ) ?>

                </p>

              </div>

            </div>

          </section>

          <!-- ==============================================
                     FIN: ESTADÍSTICAS
                     ============================================== -->


          <!-- ==============================================
                     INICIO: TOP 10 MUNICIPIO
                     ============================================== -->

          <?php

          $rankingCount =
            count(
              $municipalityData['cheapest_stations']
            );

          ?>


          <section>

            <div
              class="section-heading-with-copy">

              <h2>

                <?php if (
                  $rankingCount >= 10
                ): ?>

                  Las 10 gasolineras más baratas

                <?php elseif (
                  $rankingCount === 1
                ): ?>

                  La gasolinera más barata

                <?php else: ?>

                  Las
                  <?= $rankingCount ?>
                  gasolineras más baratas

                <?php endif; ?>

              </h2>

              <p class="section-intro">

                Ranking de precios para

                <?= e(
                  $selectedFuelName
                ) ?>.

              </p>

            </div>


            <?php if (
              $rankingCount > 0
            ): ?>

              <div class="station-list">

                <?php foreach (
                  $municipalityData['cheapest_stations']
                  as $index => $item
                ): ?>

                  <?php

                  $url =
                    $basePath
                    . '/gasolinera/'
                    . $item['external_id']
                    . '-'
                    . slugify(
                      $item['name']
                    )
                    . '/';

                  ?>

                  <article
                    class="station-item">

                    <div
                      class="station-rank">
                      <?= $index + 1 ?>
                    </div>


                    <div
                      class="station-info">

                      <h3>

                        <a
                          href="<?= e($url) ?>"
                          class="station-name-link">
                          <?= e(
                            $item['name']
                          ) ?>
                        </a>

                      </h3>

                      <p>
                        <?= e(
                          $item['address']
                        ) ?>
                      </p>

                    </div>


                    <div
                      class="station-price">

                      <?= e(
                        formatFuelPrice(
                          $item['price']
                        )
                      ) ?>

                    </div>

                  </article>

                <?php endforeach; ?>

              </div>

            <?php else: ?>

              <div class="card">

                <p class="history-message">

                  No hay precios disponibles
                  para este combustible.

                </p>

              </div>

            <?php endif; ?>

          </section>

          <!-- ==============================================
                     FIN: TOP 10 MUNICIPIO
                     ============================================== -->


          <!-- ==============================================
                     INICIO: TODAS LAS GASOLINERAS
                     ============================================== -->

          <section
            id="todas-gasolineras"
            class="municipality-all-stations">

            <div
              class="municipality-list-heading">

              <div>

                <h2>

                  Todas las gasolineras de

                  <?= e(
                    $municipality['name']
                  ) ?>

                </h2>

                <p class="section-intro">

                  <?= number_format(
                    $municipalityStations['total'],
                    0,
                    ',',
                    '.'
                  ) ?>

                  estaciones encontradas.

                </p>

              </div>


              <span
                class="municipality-total-badge">

                <?= number_format(
                  $municipalityStations['total'],
                  0,
                  ',',
                  '.'
                ) ?>

              </span>

            </div>


            <div
              class="municipality-stations-panel">

              <?php foreach (
                $municipalityStations['stations']
                as $item
              ): ?>

                <?php

                $url =
                  $basePath
                  . '/gasolinera/'
                  . $item['external_id']
                  . '-'
                  . $item['slug']
                  . '/';

                ?>

                <article
                  class="municipality-station-row">

                  <div
                    class="municipality-station-info">

                    <h3>

                      <a
                        href="<?= e($url) ?>"
                        class="station-name-link">
                        <?= e(
                          $item['name']
                        ) ?>
                      </a>

                    </h3>


                    <p>

                      <?= e(
                        $item['address']
                      ) ?>

                    </p>


                    <?php if (
                      !empty($item['postal_code'])
                    ): ?>

                      <small>

                        CP
                        <?= e(
                          $item['postal_code']
                        ) ?>

                      </small>

                    <?php endif; ?>

                  </div>


                  <div
                    class="municipality-station-side">

                    <?php if (
                      $item['price'] !== null
                    ): ?>

                      <strong
                        class="municipality-station-price">

                        <?= e(
                          formatFuelPrice(
                            $item['price']
                          )
                        ) ?>

                      </strong>

                    <?php else: ?>

                      <span
                        class="municipality-no-price">
                        Sin precio
                      </span>

                    <?php endif; ?>


                    <a
                      href="<?= e($url) ?>"
                      class="search-result-action">
                      Ver ficha →
                    </a>

                  </div>

                </article>

              <?php endforeach; ?>

            </div>


            <!-- ==========================================
                         INICIO: PAGINACIÓN MUNICIPIO
                         ========================================== -->

            <?php if (
              $municipalityStations['total_pages'] > 1
            ): ?>

              <?php

              $currentPage =
                $municipalityStations['page'];

              $totalPages =
                $municipalityStations['total_pages'];

              $firstPage =
                max(
                  1,
                  $currentPage - 2
                );

              $lastPage =
                min(
                  $totalPages,
                  $currentPage + 2
                );

              ?>


              <nav
                class="municipality-pagination"
                aria-label="Páginas de gasolineras">


                <?php if (
                  $currentPage > 1
                ): ?>

                  <a
                    href="<?= e(
                            buildQueryUrl(
                              $municipalityBaseUrl,
                              [
                                'fuel' =>
                                $selectedFuel,

                                'page' =>
                                $currentPage - 1,
                              ]
                            )
                              . '#todas-gasolineras'
                          ) ?>"
                    class="
                                        municipality-page-link
                                        municipality-page-direction
                                    ">
                    ← Anterior
                  </a>

                <?php endif; ?>


                <?php if (
                  $firstPage > 1
                ): ?>

                  <a
                    href="<?= e(
                            buildQueryUrl(
                              $municipalityBaseUrl,
                              [
                                'fuel' =>
                                $selectedFuel,

                                'page' =>
                                1,
                              ]
                            )
                              . '#todas-gasolineras'
                          ) ?>"
                    class="municipality-page-link">
                    1
                  </a>


                  <?php if (
                    $firstPage > 2
                  ): ?>

                    <span
                      class="municipality-page-ellipsis">
                      …
                    </span>

                  <?php endif; ?>

                <?php endif; ?>


                <?php for (
                  $number = $firstPage;
                  $number <= $lastPage;
                  $number++
                ): ?>

                  <?php if (
                    $number
                    === $currentPage
                  ): ?>

                    <span
                      class="
                                            municipality-page-link
                                            is-active
                                        "
                      aria-current="page">
                      <?= $number ?>
                    </span>

                  <?php else: ?>

                    <a
                      href="<?= e(
                              buildQueryUrl(
                                $municipalityBaseUrl,
                                [
                                  'fuel' =>
                                  $selectedFuel,

                                  'page' =>
                                  $number,
                                ]
                              )
                                . '#todas-gasolineras'
                            ) ?>"
                      class="municipality-page-link">
                      <?= $number ?>
                    </a>

                  <?php endif; ?>

                <?php endfor; ?>


                <?php if (
                  $lastPage
                  < $totalPages
                ): ?>

                  <?php if (
                    $lastPage
                    < $totalPages - 1
                  ): ?>

                    <span
                      class="municipality-page-ellipsis">
                      …
                    </span>

                  <?php endif; ?>


                  <a
                    href="<?= e(
                            buildQueryUrl(
                              $municipalityBaseUrl,
                              [
                                'fuel' =>
                                $selectedFuel,

                                'page' =>
                                $totalPages,
                              ]
                            )
                              . '#todas-gasolineras'
                          ) ?>"
                    class="municipality-page-link">
                    <?= $totalPages ?>
                  </a>

                <?php endif; ?>


                <?php if (
                  $currentPage
                  < $totalPages
                ): ?>

                  <a
                    href="<?= e(
                            buildQueryUrl(
                              $municipalityBaseUrl,
                              [
                                'fuel' =>
                                $selectedFuel,

                                'page' =>
                                $currentPage + 1,
                              ]
                            )
                              . '#todas-gasolineras'
                          ) ?>"
                    class="
                                        municipality-page-link
                                        municipality-page-direction
                                    ">
                    Siguiente →
                  </a>

                <?php endif; ?>


              </nav>


              <p
                class="municipality-pagination-summary">

                Mostrando

                <?= number_format(
                  $municipalityStations['from'],
                  0,
                  ',',
                  '.'
                ) ?>

                a

                <?= number_format(
                  $municipalityStations['to'],
                  0,
                  ',',
                  '.'
                ) ?>

                de

                <?= number_format(
                  $municipalityStations['total'],
                  0,
                  ',',
                  '.'
                ) ?>

                gasolineras

              </p>

            <?php endif; ?>

            <!-- ==========================================
                         FIN: PAGINACIÓN MUNICIPIO
                         ========================================== -->


          </section>

          <!-- ==============================================
                     FIN: TODAS LAS GASOLINERAS
                     ============================================== -->


          <!-- ==================================================
                 FIN: MUNICIPIO
                 ================================================== -->


          <!-- ==================================================
                 INICIO: GASOLINERA
                 ================================================== -->

        <?php elseif (
          $pageType === 'station'
          && $station !== null
          && $stationData !== null
        ): ?>

          <?php

          $stationProvinceUrl =
            $basePath
            . '/gasolineras/'
            . $station['province_slug']
            . '/';

          $stationMunicipalityUrl =
            $basePath
            . '/gasolineras/'
            . $station['province_slug']
            . '/'
            . $station['municipality_slug']
            . '/';

          ?>


          <nav
            class="breadcrumbs"
            aria-label="Migas de pan">

            <a href="<?= e($homeUrl) ?>">
              Inicio
            </a>

            <span>/</span>

            <a href="<?= e($gasStationsUrl) ?>">
              Gasolineras
            </a>

            <span>/</span>

            <a
              href="<?= e($stationProvinceUrl) ?>">
              <?= e(
                $station['province_name']
              ) ?>
            </a>

            <span>/</span>

            <a
              href="<?= e($stationMunicipalityUrl) ?>">
              <?= e(
                $station['municipality_name']
              ) ?>
            </a>

            <span>/</span>

            <span aria-current="page">
              <?= e(
                $station['name']
              ) ?>
            </span>

          </nav>


          <nav
            class="context-nav"
            aria-label="Navegación secundaria">

            <a
              href="<?= e($stationMunicipalityUrl) ?>"
              class="
                            context-nav-link
                            context-nav-link-primary
                        ">

              ← Volver a

              <?= e(
                $station['municipality_name']
              ) ?>

            </a>

            <a
              href="<?= e($stationProvinceUrl) ?>"
              class="context-nav-link">

              Ver

              <?= e(
                $station['province_name']
              ) ?>

            </a>

          </nav>


          <section
            class="station-hero">

            <div
              class="station-hero-main">

              <span
                class="station-eyebrow">
                Estación de servicio
              </span>

              <h1>
                <?= e(
                  $station['name']
                ) ?>
              </h1>

              <p
                class="station-hero-location">

                <?= e(
                  $station['municipality_name']
                ) ?>,

                <?= e(
                  $station['province_name']
                ) ?>

              </p>

            </div>

            <div
              class="station-hero-badge">

              IDEESS

              <?= e(
                $station['external_id']
              ) ?>

            </div>

          </section>


          <section
            class="station-details-card">

            <h2>
              Información de la estación
            </h2>

            <div
              class="station-details-grid">

              <div
                class="station-detail">

                <span
                  class="station-detail-label">
                  Dirección
                </span>

                <span
                  class="station-detail-value">
                  <?= e(
                    $station['address']
                  ) ?>
                </span>

              </div>


              <?php if (
                $station['postal_code'] !== null
              ): ?>

                <div
                  class="station-detail">

                  <span
                    class="station-detail-label">
                    Código postal
                  </span>

                  <span
                    class="station-detail-value">
                    <?= e(
                      $station['postal_code']
                    ) ?>
                  </span>

                </div>

              <?php endif; ?>


              <div
                class="station-detail">

                <span
                  class="station-detail-label">
                  Municipio
                </span>

                <a
                  href="<?= e($stationMunicipalityUrl) ?>"
                  class="station-detail-link">
                  <?= e(
                    $station['municipality_name']
                  ) ?>
                </a>

              </div>


              <div
                class="station-detail">

                <span
                  class="station-detail-label">
                  Provincia
                </span>

                <a
                  href="<?= e($stationProvinceUrl) ?>"
                  class="station-detail-link">
                  <?= e(
                    $station['province_name']
                  ) ?>
                </a>

              </div>


              <?php if (
                $station['schedule'] !== null
              ): ?>

                <div
                  class="
                                    station-detail
                                    station-detail-wide
                                ">

                  <span
                    class="station-detail-label">
                    Horario
                  </span>

                  <span
                    class="station-detail-value">
                    <?= e(
                      $station['schedule']
                    ) ?>
                  </span>

                </div>

              <?php endif; ?>

            </div>

          </section>


          <?php
          require __DIR__
            . '/../app/station-map.php';
          ?>


          <?php if (
            $stationData['latest'] !== null
          ): ?>

            <section
              class="station-prices-section">

              <h2>
                Precios actuales
              </h2>

              <p class="updated-at">

                Última actualización:

                <?= e(
                  $stationData['latest']['snapshot']['api_date']
                ) ?>

              </p>

              <div
                class="station-price-grid">

                <?php foreach (
                  $stationData['latest']['prices']
                  as $price
                ): ?>

                  <?php

                  $fuelName =
                    $fuelTypes[$price['fuel_code']]
                    ?? displayName(
                      str_replace(
                        '_',
                        ' ',
                        $price['fuel_code']
                      )
                    );

                  ?>

                  <article
                    class="fuel-price-card">

                    <span
                      class="fuel-price-name">
                      <?= e(
                        $fuelName
                      ) ?>
                    </span>

                    <span
                      class="fuel-price-value">
                      <?= e(
                        formatFuelPrice(
                          $price['price']
                        )
                      ) ?>
                    </span>

                  </article>

                <?php endforeach; ?>

              </div>

            </section>

          <?php endif; ?>


          <section>

            <h2>
              Evolución del precio
            </h2>

            <p class="section-intro">

              Consulta la evolución de

              <?= e(
                $selectedFuelName
              ) ?>

              en esta estación.

            </p>


            <form
              method="get"
              class="fuel-selector">

              <label
                for="fuel-history">
                Combustible
              </label>

              <select
                name="fuel"
                id="fuel-history"
                onchange="this.form.submit()">

                <?php foreach (
                  $fuelTypes
                  as $code => $name
                ): ?>

                  <option
                    value="<?= e($code) ?>"
                    <?= $code === $selectedFuel
                      ? 'selected'
                      : ''
                    ?>>
                    <?= e($name) ?>
                  </option>

                <?php endforeach; ?>

              </select>

            </form>


            <?php if (
              $stationHistorySummary
              !== null
            ): ?>

              <div class="stats">

                <div class="card">

                  <strong>
                    Precio actual
                  </strong>

                  <p>
                    <?= e(
                      formatFuelPrice(
                        $stationHistorySummary['current']['price']
                      )
                    ) ?>
                  </p>

                </div>


                <?php if (
                  $stationHistorySummary['previous'] !== null
                ): ?>

                  <div class="card">

                    <strong>
                      Precio anterior
                    </strong>

                    <p>
                      <?= e(
                        formatFuelPrice(
                          $stationHistorySummary['previous']['price']
                        )
                      ) ?>
                    </p>

                  </div>


                  <div class="card">

                    <strong>
                      Variación
                    </strong>

                    <p>
                      <?= e(
                        formatPriceChange(
                          $stationHistorySummary['change']
                        )
                      ) ?>
                    </p>

                  </div>


                  <div class="card">

                    <strong>
                      Variación %
                    </strong>

                    <p>
                      <?= e(
                        formatPercentChange(
                          $stationHistorySummary['change_percent']
                        )
                      ) ?>
                    </p>

                  </div>

                <?php endif; ?>

              </div>


              <?php if (
                count(
                  $stationHistory
                ) > 1
              ): ?>

                <!-- ==========================================
                     INICIO: LISTADO HISTÓRICO DE PRECIOS
                     ========================================== -->

                <div
                  class="history-list">

                  <div
                    class="history-list-header"
                    aria-hidden="true">

                    <span>
                      Fecha y hora
                    </span>

                    <span>
                      Precio
                    </span>

                    <span>
                      Variación
                    </span>

                    <span>
                      Variación %
                    </span>

                  </div>


                  <?php foreach (
                    array_reverse(
                      $stationHistory
                    )
                    as $historyRow
                  ): ?>

                    <?php

                    $historyTimestamp =
                      strtotime(
                        $historyRow['api_date']
                      );

                    $historyDate =
                      $historyTimestamp !== false
                      ? date(
                        'd/m/Y · H:i',
                        $historyTimestamp
                      )
                      : $historyRow['api_date'];

                    $hasHistoryChange =
                      $historyRow['change']
                      !== null;

                    ?>

                    <article
                      class="history-row"
                      data-history-date="<?= e($historyDate) ?>"
                      data-history-datetime="<?= e($historyRow['api_date']) ?>"
                      data-history-price="<?= e((string) $historyRow['price']) ?>">

                      <div
                        class="history-cell history-date">

                        <span
                          class="history-mobile-label">
                          Fecha y hora
                        </span>

                        <time
                          datetime="<?= e(
                                      $historyRow['api_date']
                                    ) ?>">
                          <?= e(
                            $historyDate
                          ) ?>
                        </time>

                      </div>


                      <div
                        class="history-cell history-price">

                        <span
                          class="history-mobile-label">
                          Precio
                        </span>

                        <strong>
                          <?= e(
                            formatFuelPrice(
                              $historyRow['price']
                            )
                          ) ?>
                        </strong>

                      </div>


                      <div
                        class="history-cell history-change">

                        <span
                          class="history-mobile-label">
                          Variación
                        </span>

                        <?php if (
                          $hasHistoryChange
                        ): ?>

                          <span>
                            <?= e(
                              formatPriceChange(
                                $historyRow['change']
                              )
                            ) ?>
                          </span>

                        <?php else: ?>

                          <span
                            class="history-no-data"
                            aria-label="Sin dato anterior">
                            —
                          </span>

                        <?php endif; ?>

                      </div>


                      <div
                        class="history-cell history-percent">

                        <span
                          class="history-mobile-label">
                          Variación %
                        </span>

                        <?php if (
                          $hasHistoryChange
                        ): ?>

                          <span>
                            <?= e(
                              formatPercentChange(
                                $historyRow['change_percent']
                              )
                            ) ?>
                          </span>

                        <?php else: ?>

                          <span
                            class="history-no-data"
                            aria-label="Sin dato anterior">
                            —
                          </span>

                        <?php endif; ?>

                      </div>

                    </article>

                  <?php endforeach; ?>

                </div>

                <!-- ==========================================
                     FIN: LISTADO HISTÓRICO DE PRECIOS
                     ========================================== -->

              <?php else: ?>

                <div class="card">

                  <strong>
                    Histórico en construcción
                  </strong>

                  <p class="history-message">

                    Todavía solo disponemos
                    de una captura para este
                    combustible. Cuando se registre
                    el siguiente snapshot diario
                    podremos mostrar su evolución.

                  </p>

                </div>

              <?php endif; ?>


            <?php else: ?>

              <div class="card">

                <strong>
                  Sin datos
                </strong>

                <p class="history-message">

                  Esta estación no tiene datos
                  históricos para

                  <?= e(
                    $selectedFuelName
                  ) ?>.

                </p>

              </div>

            <?php endif; ?>

          </section>


          <!-- ==================================================
                 FIN: GASOLINERA
                 ================================================== -->


          <!-- ==================================================
                 INICIO: 404
                 ================================================== -->

        <?php else: ?>

          <section
            class="error-page">

            <div class="error-code">
              404
            </div>

            <div
              class="fuel-icon"
              aria-hidden="true">
              ⛽
            </div>

            <h1>
              Te has quedado sin combustible
            </h1>

            <p class="error-text">

              La página que buscas no existe
              o ha cambiado de dirección.

            </p>

            <a
              href="<?= e($homeUrl) ?>"
              class="button-primary">
              Volver al inicio
            </a>

          </section>


        <?php endif; ?>

        <!-- ==================================================
                 FIN: 404
                 ================================================== -->


      </div>

    </main>

    <!-- ======================================================
         FIN: MAIN
         ====================================================== -->


    <!-- ======================================================
         INICIO: FOOTER
         ====================================================== -->

    <?php require __DIR__ . '/../app/views/layout/footer.php'; ?>

    <!-- ======================================================
         FIN: FOOTER
         ====================================================== -->


  </div>


  <script
    src="<?= e($jsUrl) ?>"
    defer></script>

</body>

</html>