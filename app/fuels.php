<?php

declare(strict_types=1);

function getFuelTypes(): array
{
  $fuels = [
    'gasolina_95_e5' => 'Gasolina 95 E5',
    'gasoleo_a' => 'Gasóleo A',
    'gasoleo_b' => 'Gasóleo B',
    'gasoleo_premium' => 'Gasóleo Premium',
    'gasolina_98_e5' => 'Gasolina 98 E5',
    'glp' => 'GLP',
  ];

  asort(
    $fuels,
    SORT_NATURAL | SORT_FLAG_CASE
  );

  return $fuels;
}
