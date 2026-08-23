<?php

declare(strict_types=1);


/*
 * ============================================================
 * INICIO: SLUGS
 * ============================================================
 */

/**
 * Convierte un texto en un slug apto para URL.
 *
 * Ejemplos:
 *
 * "Mataró"                      -> "mataro"
 * "A Coruña"                    -> "a-coruna"
 * "Las Palmas de Gran Canaria"  -> "las-palmas-de-gran-canaria"
 */
function slugify(string $text): string
{
  $text = trim($text);

  $text = mb_strtolower(
    $text,
    'UTF-8'
  );

  $search = [
    'á',
    'à',
    'ä',
    'â',
    'é',
    'è',
    'ë',
    'ê',
    'í',
    'ì',
    'ï',
    'î',
    'ó',
    'ò',
    'ö',
    'ô',
    'ú',
    'ù',
    'ü',
    'û',
    'ñ',
    'ç',
  ];

  $replace = [
    'a',
    'a',
    'a',
    'a',
    'e',
    'e',
    'e',
    'e',
    'i',
    'i',
    'i',
    'i',
    'o',
    'o',
    'o',
    'o',
    'u',
    'u',
    'u',
    'u',
    'n',
    'c',
  ];

  $text = str_replace(
    $search,
    $replace,
    $text
  );

  $text = preg_replace(
    '/[^a-z0-9]+/',
    '-',
    $text
  );

  $text = trim(
    $text,
    '-'
  );

  return $text;
}

/*
 * ============================================================
 * FIN: SLUGS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: NOMBRES PARA MOSTRAR
 * ============================================================
 */

/**
 * Convierte textos procedentes de la API
 * a un formato más natural para mostrar.
 *
 * Ejemplos:
 *
 * "ZARAGOZA"   -> "Zaragoza"
 * "A CORUÑA"   -> "A Coruña"
 * "LAS PALMAS" -> "Las Palmas"
 */
function displayName(string $text): string
{
  return mb_convert_case(
    trim($text),
    MB_CASE_TITLE,
    'UTF-8'
  );
}

/*
 * ============================================================
 * FIN: NOMBRES PARA MOSTRAR
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: FORMATO DE PRECIOS
 * ============================================================
 */

/**
 * Formatea un precio con convención española.
 *
 * Ejemplos:
 *
 * 1.969   -> "1,969"
 * "1.785" -> "1,785"
 *
 * Si recibe null:
 *
 * null -> "-"
 */
function formatPrice(
  float|int|string|null $price,
  int $decimals = 3
): string {

  if (
    $price === null
    || $price === ''
    || !is_numeric($price)
  ) {
    return '-';
  }

  return number_format(
    (float) $price,
    $decimals,
    ',',
    '.'
  );
}


/**
 * Formatea un precio y añade la unidad.
 *
 * Ejemplo:
 *
 * 1.969 -> "1,969 €/l"
 */
function formatFuelPrice(
  float|int|string|null $price
): string {

  $formattedPrice = formatPrice(
    $price,
    3
  );

  if ($formattedPrice === '-') {
    return '-';
  }

  return $formattedPrice . ' €/l';
}

/*
 * ============================================================
 * FIN: FORMATO DE PRECIOS
 * ============================================================
 */


/*
 * ============================================================
 * INICIO: FORMATO DE VARIACIONES
 * ============================================================
 */

/**
 * Formatea una variación absoluta.
 *
 * Ejemplos:
 *
 *  0.014 -> "+0,014 €"
 * -0.030 -> "-0,030 €"
 *  0     -> "0,000 €"
 */
function formatPriceChange(
  float|int|string|null $change
): string {

  if (
    $change === null
    || $change === ''
    || !is_numeric($change)
  ) {
    return '-';
  }

  $value = (float) $change;

  $prefix =
    $value > 0
    ? '+'
    : '';

  return $prefix
    . formatPrice(
      $value,
      3
    )
    . ' €';
}


/**
 * Formatea una variación porcentual.
 *
 * Ejemplos:
 *
 *  0.78  -> "+0,78 %"
 * -1.67  -> "-1,67 %"
 *  0     -> "0,00 %"
 */
function formatPercentChange(
  float|int|string|null $change
): string {

  if (
    $change === null
    || $change === ''
    || !is_numeric($change)
  ) {
    return '-';
  }

  $value = (float) $change;

  $prefix =
    $value > 0
    ? '+'
    : '';

  return $prefix
    . number_format(
      $value,
      2,
      ',',
      '.'
    )
    . ' %';
}

/*
 * ============================================================
 * FIN: FORMATO DE VARIACIONES
 * ============================================================
 */