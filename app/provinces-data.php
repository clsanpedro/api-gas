<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';


/*
 * ============================================================
 * INICIO: LISTADO DE PROVINCIAS
 * ============================================================
 */

/**
 * Devuelve todas las provincias que existen en stations.
 *
 * Para cada provincia obtenemos:
 *
 * - nombre para mostrar
 * - nombre real en base de datos
 * - slug SEO
 * - número de estaciones
 */
function getAllProvinces(PDO $pdo): array
{
  $stmt = $pdo->query(
    'SELECT
            province,
            COUNT(*) AS station_count
         FROM stations
         WHERE province IS NOT NULL
           AND province <> \'\'
         GROUP BY province
         ORDER BY province ASC'
  );

  $rows = $stmt->fetchAll();

  $provinces = [];


  foreach ($rows as $row) {

    $dbName =
      $row['province'];

    $provinces[] = [

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

      'station_count' =>
      (int) $row['station_count'],
    ];
  }


  return $provinces;
}

/*
 * ============================================================
 * FIN: LISTADO DE PROVINCIAS
 * ============================================================
 */