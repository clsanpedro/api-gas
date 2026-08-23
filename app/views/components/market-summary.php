<?php

declare(strict_types=1);

/** @var array|null $homeBrentSummary */

if ($homeBrentSummary === null) {
  return;
}

$current =
  $homeBrentSummary['current'];

$previous =
  $homeBrentSummary['previous'];

$change =
  $homeBrentSummary['change'];

$changePercent =
  $homeBrentSummary['change_percent'];

$changeClass = '';

if ($change !== null) {

  if ($change > 0) {
    $changeClass =
      ' is-negative';
  } elseif ($change < 0) {
    $changeClass =
      ' is-positive';
  } else {
    $changeClass =
      ' is-neutral';
  }
}
?>

<section class="market-summary">

  <h2>
    Petróleo Brent
  </h2>

  <div class="stats market-summary-stats">

    <!--
     * ========================================================
     * PRECIO ACTUAL
     * ========================================================
     -->

    <div class="card market-summary-card">

      <strong class="market-summary-label">
        Precio actual
      </strong>

      <div class="market-summary-value">

        <span class="market-summary-highlight<?= e($changeClass) ?>">
          <?= e(
            number_format(
              (float) $current['brent_eur'],
              2,
              ',',
              '.'
            )
          ) ?>
          €/barril
        </span>

      </div>

      <small class="market-summary-date">
        <?= e(
          date(
            'd/m/Y',
            strtotime(
              $current['price_date']
            )
          )
        ) ?>
      </small>

    </div>


    <!--
     * ========================================================
     * PRECIO ANTERIOR
     * ========================================================
     -->

    <div class="card market-summary-card">

      <strong class="market-summary-label">
        Precio anterior
      </strong>

      <?php if ($previous !== null): ?>

        <div class="market-summary-value">

          <span class="market-summary-highlight">
            <?= e(
              number_format(
                (float) $previous['brent_eur'],
                2,
                ',',
                '.'
              )
            ) ?>
            €/barril
          </span>

        </div>

        <small class="market-summary-date">
          <?= e(
            date(
              'd/m/Y',
              strtotime(
                $previous['price_date']
              )
            )
          ) ?>
        </small>

      <?php else: ?>

        <div class="market-summary-value">
          Sin dato anterior
        </div>

      <?php endif; ?>

    </div>


    <!--
     * ========================================================
     * VARIACIÓN
     * ========================================================
     -->

    <div class="card market-summary-card">

      <strong class="market-summary-label">
        Variación
      </strong>

      <?php if (
        $change !== null
        && $changePercent !== null
      ): ?>

        <div class="market-summary-value">

          <span class="market-summary-highlight">

            <?= $change > 0 ? '+' : '' ?>

            <?= e(
              number_format(
                (float) $change,
                2,
                ',',
                '.'
              )
            ) ?>
            €/barril

          </span>

        </div>

        <small class="market-summary-change<?= e($changeClass) ?>">

          <?= $changePercent > 0 ? '+' : '' ?>

          <?= e(
            number_format(
              (float) $changePercent,
              2,
              ',',
              '.'
            )
          ) ?>
          %

        </small>

      <?php else: ?>

        <div class="market-summary-value">
          Sin variación disponible
        </div>

      <?php endif; ?>

    </div>

  </div>

</section>