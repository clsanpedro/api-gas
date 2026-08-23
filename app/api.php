<?php

declare(strict_types=1);

function fetchFuelData(): array
{
  $url = 'https://sedeaplicaciones.minetur.gob.es/ServiciosRESTCarburantes/PreciosCarburantes/EstacionesTerrestres/';

  $ch = curl_init($url);

  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
  ];

  if (
    PHP_OS_FAMILY === 'Windows'
    && defined('CURLSSLOPT_NATIVE_CA')
  ) {
    $options[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
  }

  curl_setopt_array($ch, $options);

  $json = curl_exec($ch);

  if ($json === false) {
    $error = curl_error($ch);

    logApiError(
      'No se pudo conectar con la API de carburantes. Detalle: ' . $error
    );

    throw new RuntimeException(
      'No se pudo obtener información de la API.'
    );
  }

  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($httpCode !== 200) {
    logApiError(
      'La API devolvió HTTP ' . $httpCode
    );

    throw new RuntimeException(
      'La API devolvió una respuesta HTTP no válida.'
    );
  }

  $data = json_decode($json, true);

  if (!is_array($data)) {
    logApiError('La API devolvió un JSON no válido.');

    throw new RuntimeException(
      'La respuesta de la API no es válida.'
    );
  }

  if (!isset($data['Fecha'], $data['ListaEESSPrecio'])) {
    logApiError(
      'La respuesta de la API no contiene los campos esperados.'
    );

    throw new RuntimeException(
      'La estructura de la API no es válida.'
    );
  }

  return $data;
}

function logApiError(string $message): void
{
  $logFile = __DIR__ . '/../storage/error.log';

  $line = sprintf(
    "[%s] Error API: %s%s",
    date('Y-m-d H:i:s'),
    $message,
    PHP_EOL
  );

  error_log($line, 3, $logFile);
}
