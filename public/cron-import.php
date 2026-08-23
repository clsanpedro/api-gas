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
 *
 * Ejemplo:
 * ?token=abc123...
 */
$token = $_GET['token'] ?? '';


/*
 * Comparamos el token recibido con el secreto.
 *
 * hash_equals() evita comparaciones inseguras.
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
 * Si el token es correcto, ejecutamos el importador.
 */
require_once __DIR__ . '/../scripts/import.php';
