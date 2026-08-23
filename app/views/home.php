<?php

/** @var array|null $fuelData */
/** @var array $fuelTypes */
/** @var string $selectedFuel */
/** @var string $selectedFuelName */
/** @var string $gasStationsUrl */
/** @var array|null $homeCheapest */
/** @var string $searchUrl */
/** @var array|null $snapshot */
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

      <p>

        Precio:

        <span
          class="price-highlight">
          <?= e(
            formatFuelPrice(
              $homeCheapest['price']
            )
          ) ?>
        </span>

      </p>

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