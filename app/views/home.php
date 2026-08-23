<?php

/** @var array|null $fuelData */
/** @var array $fuelTypes */
/** @var string $selectedFuel */
/** @var string $selectedFuelName */
/** @var string $gasStationsUrl */
/** @var array|null $homeCheapest */
/** @var string $searchUrl */
/** @var array|null $snapshot */
/** @var array $homeFuelHistory */
/** @var array $homeBrentHistory */
?>

<h1>
  Precios de carburantes en España
</h1>

<p class="intro">
  Consulta y compara los precios de gasolina
  y gasóleo en las estaciones de servicio de
  España. Encuentra las gasolineras más baratas
  con datos actualizados diariamente.
</p>


<section>

  <h2>
    Encuentra una gasolinera
  </h2>

  <p class="section-intro">
    Busca por nombre, municipio, provincia,
    código postal o dirección.
  </p>


  <form
    action="<?= e($searchUrl) ?>"
    method="get"
    class="search-form home-search-form">

    <label
      for="home-search"
      class="search-label">
      Buscar gasolinera
    </label>


    <div class="search-form-row">

      <input
        type="search"
        name="q"
        id="home-search"
        class="search-input"
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

</section>


<?php if (
  $snapshot === null
  || $fuelData === null
): ?>

  <p>
    Todavía no hay datos disponibles.
  </p>

<?php else: ?>

  <p class="updated-at">

    Última actualización:

    <?= e(
      $snapshot['api_date']
    ) ?>

  </p>

  <form
    method="get"
    class="fuel-selector">

    <label for="fuel">
      Combustible
    </label>

    <select
      id="fuel"
      name="fuel"
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
      <?= e($selectedFuelName) ?>
    </h2>

    <div class="stats">

      <div class="card">

        <strong>
          Precio mínimo
        </strong>

        <p>
          <?= e(
            formatFuelPrice(
              $fuelData['min_price']
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
              $fuelData['avg_price']
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
              $fuelData['max_price']
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
            $fuelData['stations_count'],
            0,
            ',',
            '.'
          ) ?>

        </p>

      </div>

    </div>

  </section>

  <?php if (
    count($homeFuelHistory) >= 2
  ): ?>

    <section class="home-history">

      <h2>
        Evolución del precio medio
      </h2>

      <p class="section-intro">
        Evolución del precio medio nacional de
        <?= e($selectedFuelName) ?>.
      </p>

      <div
        class="history-chart-card home-history-chart"
        data-home-history-chart>

        <div class="history-chart-header">

          <strong>
            Precio medio nacional
          </strong>

          <span>
            <?= count($homeFuelHistory) ?>
            registros
          </span>

        </div>

        <div class="history-chart-wrapper">

          <canvas
            data-home-history-canvas
            aria-label="Gráfico de evolución del precio medio de <?= e($selectedFuelName) ?>"
            role="img">
          </canvas>

        </div>

        <div hidden>

          <?php foreach (
            $homeFuelHistory
            as $historyItem
          ): ?>

            <span
              data-home-history-point
              data-history-date="<?= e($historyItem['api_date']) ?>"
              data-history-price="<?= e($historyItem['avg_price']) ?>">
            </span>

          <?php endforeach; ?>

        </div>

      </div>

    </section>

  <?php endif; ?>

  <?php if (
    count($homeBrentHistory) >= 2
  ): ?>
    <?php require __DIR__ . '/components/market-summary.php'; ?>
    <section class="home-market">

      <h2>
        Carburantes y petróleo
      </h2>

      <p class="section-intro">
        Compara la evolución relativa del precio medio nacional de
        <?= e($selectedFuelName) ?>
        con el petróleo Brent expresado en euros.
        Ambas series parten de un índice base 100 para facilitar
        la comparación de sus variaciones.
      </p>

      <div
        class="history-chart-card home-market-chart"
        data-home-market-chart>

        <div class="history-chart-header">

          <strong>
            Carburante vs petróleo Brent
          </strong>

          <span>
            Comparación de tendencia
          </span>

        </div>

        <div class="history-chart-wrapper">

          <canvas
            data-home-market-canvas
            aria-label="Comparación entre <?= e($selectedFuelName) ?> y petróleo Brent"
            role="img">
          </canvas>

        </div>

        <div hidden>

          <?php foreach (
            $homeBrentHistory
            as $marketItem
          ): ?>

            <span
              data-home-brent-point
              data-market-date="<?= e($marketItem['price_date']) ?>"
              data-market-price="<?= e($marketItem['brent_eur']) ?>">
            </span>

          <?php endforeach; ?>


          <?php foreach (
            $homeFuelHistory
            as $historyItem
          ): ?>

            <span
              data-home-fuel-market-point
              data-market-date="<?= e($historyItem['api_date']) ?>"
              data-market-price="<?= e($historyItem['avg_price']) ?>">
            </span>

          <?php endforeach; ?>

        </div>

      </div>

      <p class="section-intro">
        El precio del petróleo no se traslada de forma inmediata
        ni proporcional al precio de los carburantes. También
        influyen el tipo de cambio, el refino, la distribución,
        los márgenes y los impuestos.
      </p>

    </section>

  <?php endif; ?>


  <?php if (
    $fuelData['cheapest_station'] !== null
  ): ?>

    <?php

    $homeCheapest =
      $fuelData['cheapest_station'];

    ?>

    <section class="cheapest">

      <h2>
        Gasolinera más barata
      </h2>

      <div class="cheapest-layout">

        <div class="cheapest-details">

          <p>
            <strong>
              <?= e(
                $homeCheapest['name']
              ) ?>
            </strong>
          </p>

          <p>
            <?= e(
              $homeCheapest['address']
            ) ?>
          </p>

          <p>
            <?= e(
              $homeCheapest['municipality']
            ) ?>,

            <?= e(
              displayName(
                $homeCheapest['province']
              )
            ) ?>
          </p>

        </div>

        <div class="cheapest-price">

          <span class="cheapest-price-label">
            <?= e($selectedFuelName) ?>
          </span>

          <span class="price-highlight">
            <?= e(
              formatFuelPrice(
                $homeCheapest['price']
              )
            ) ?>
          </span>

        </div>

      </div>

    </section>

  <?php endif; ?>


  <section>

    <h2>
      Consulta gasolineras por provincia
    </h2>

    <p class="section-intro">
      Explora las estaciones de servicio
      por provincia y municipio.
    </p>

    <a
      href="<?= e($gasStationsUrl) ?>"
      class="button-primary">
      Ver todas las provincias
    </a>

  </section>

<?php endif; ?>