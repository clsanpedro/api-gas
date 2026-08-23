<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/cron-config.php';


/*
 * Solo aceptamos peticiones GET.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);

  echo 'Método no permitido.';

  exit;
}


/*
 * Recuperamos el token recibido por URL.
 */
$token = $_GET['token'] ?? '';


/*
 * Validamos el token.
 */
if (
  $token === ''
  || !hash_equals(CRON_TOKEN, $token)
) {
  http_response_code(403);

  echo 'Acceso denegado.';

  exit;
}


/*
 * Ejecutamos el importador de datos de mercado.
 */
require_once __DIR__ . '/../scripts/import-market.php';
