<?php

declare(strict_types=1);


/*
 * ============================================================
 * INICIO: VALIDACIÓN DE DATOS DEL MAPA
 * ============================================================
 */

if (
  !isset($station)
  || $station === null
  || $station['latitude'] === null
  || $station['longitude'] === null
  || !is_numeric($station['latitude'])
  || !is_numeric($station['longitude'])
) {
  return;
}

$mapLatitude =
  (float) $station['latitude'];

$mapLongitude =
  (float) $station['longitude'];


/*
 * Validamos los límites geográficos posibles.
 */

if (
  $mapLatitude < -90
  || $mapLatitude > 90
  || $mapLongitude < -180
  || $mapLongitude > 180
) {
  return;
}

/*
 * ============================================================
 * FIN: VALIDACIÓN DE DATOS DEL MAPA
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: DATOS DEL MAPA
 * ============================================================
 */

$mapStationName =
  $station['name'];

$mapStationAddress =
  $station['address'];

$openStreetMapUrl =
  'https://www.openstreetmap.org/'
  . '?mlat='
  . rawurlencode((string) $mapLatitude)
  . '&mlon='
  . rawurlencode((string) $mapLongitude)
  . '#map=17/'
  . rawurlencode((string) $mapLatitude)
  . '/'
  . rawurlencode((string) $mapLongitude);

/*
 * ============================================================
 * FIN: DATOS DEL MAPA
 * ============================================================
 */

?>


<!-- ==========================================================
     INICIO: MAPA DE LA ESTACIÓN
     ========================================================== -->

<section class="station-map-section">

  <div class="station-map-heading">

    <div>

      <h2>
        Ubicación
      </h2>

      <p class="section-intro">
        Localiza esta estación de servicio en el mapa.
      </p>

    </div>


    <a
      href="<?= e($openStreetMapUrl) ?>"
      class="map-external-link"
      target="_blank"
      rel="noopener noreferrer">
      Ver en OpenStreetMap
      <span aria-hidden="true">↗</span>
    </a>

  </div>


  <div class="station-map-card">

    <div
      id="station-map"
      class="station-map"
      data-latitude="<?= e((string) $mapLatitude) ?>"
      data-longitude="<?= e((string) $mapLongitude) ?>"
      data-name="<?= e($mapStationName) ?>"
      data-address="<?= e($mapStationAddress) ?>"></div>

  </div>

</section>

<!-- ==========================================================
     FIN: MAPA DE LA ESTACIÓN
     ========================================================== -->