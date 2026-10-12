<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('BOBI_CRICKET_INTERNAL')) {
  http_response_code(404);
  exit;
}

date_default_timezone_set('Asia/Kolkata');

$root = dirname(__DIR__);
$dataFile = $root . '/data/cricket.json';
$privateConfig = dirname($root) . '/private_bot/config.php';
$config = is_file($privateConfig) ? require $privateConfig : [];
require_once $root . '/includes/config.php';

function cricket_default_payload(): array {
  return bobi_default_cricket_data();
}

function cricket_normalize_payload(array $payload): array {
  $payload = bobi_normalize_cricket_data($payload);
  $payload['updated_at'] = date('c');

  return $payload;
}

function cricket_fetch_api_payload(array $config): ?array {
  $apiUrl = trim((string)($config['CRICKET_CACHE_SOURCE_URL'] ?? $config['CRICKET_API_URL'] ?? ''));
  $apiKey = trim((string)($config['CRICKET_API_KEY'] ?? ''));

  if ($apiUrl === '') {
    return null;
  }

  $url = str_replace('{API_KEY}', rawurlencode($apiKey), $apiUrl);
  if (!str_starts_with($url, 'https://')) return null;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 18,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_HTTPHEADER => array_values(array_filter([
      'Accept: application/json',
      $apiKey !== '' ? 'Authorization: Bearer ' . $apiKey : '',
    ])),
  ]);
  $body = curl_exec($ch);
  $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if (!is_string($body) || $body === '' || $status >= 400) {
    return null;
  }

  $json = json_decode($body, true);
  if (!is_array($json)) {
    return null;
  }

  // Optional adapter: if a future provider already returns BOBI-shaped data, use it directly.
  if (isset($json['featured'], $json['matches']) && is_array($json['matches'])) {
    $json['source'] = 'api-cache';
    return $json;
  }

  return null;
}

function cricket_fetch_private_source(string $root): ?array {
  $sourceFile = dirname($root) . '/private_bot/cricket-source.json';
  if (!is_file($sourceFile)) {
    return null;
  }

  $json = json_decode((string) file_get_contents($sourceFile), true);
  if (!is_array($json)) {
    return null;
  }

  $json['source'] = 'private-source';
  return $json;
}

$existing = is_file($dataFile) ? json_decode((string) file_get_contents($dataFile), true) : null;
$payload = cricket_fetch_api_payload($config) ?? cricket_fetch_private_source($root) ?? (is_array($existing) ? $existing : cricket_default_payload());
$payload = cricket_normalize_payload($payload);

if (!is_dir(dirname($dataFile))) {
  mkdir(dirname($dataFile), 0755, true);
}

file_put_contents($dataFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

$message = [
  'ok' => true,
  'updated_at' => $payload['updated_at'],
  'source' => $payload['source'],
  'matches' => count($payload['matches']),
];

if (PHP_SAPI === 'cli') {
  echo json_encode($message, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $message;
