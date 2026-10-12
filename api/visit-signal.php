<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$privateConfig = dirname(__DIR__, 2) . '/private_bot/config.php';
$config = is_file($privateConfig) ? require $privateConfig : [];

$day = gmdate('Y-m-d');
$storageDir = dirname(__DIR__, 2) . '/private_bot/storage';
if (!is_dir($storageDir)) {
  mkdir($storageDir, 0755, true);
}

$counterFile = $storageDir . '/visit_counter.json';
$counter = [
  'days' => [],
  'events' => [],
];
if (is_file($counterFile)) {
  $loaded = json_decode(file_get_contents($counterFile) ?: '', true);
  if (is_array($loaded)) {
    $counter = array_replace_recursive($counter, $loaded);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['mode'] ?? '') === 'stats') {
  $key = (string)($_SERVER['HTTP_X_VISIT_ADMIN_KEY'] ?? '');
  $adminKey = (string)(getenv('VISIT_ADMIN_KEY') ?: ($config['VISIT_ADMIN_KEY'] ?? ''));
  if (!security_secret_matches($adminKey, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
  }

  $totalPageViews = 0;
  $totalPromoShown = 0;
  $totalDailyUniques = 0;
  foreach ($counter['days'] as $date => $row) {
    $totalPageViews += (int)($row['page_views'] ?? 0);
    $totalPromoShown += (int)($row['promo_shown'] ?? 0);
    $totalDailyUniques += count($row['unique_visitors'] ?? []);
  }

  $topPages = [];
  foreach ($counter['events'] as $eventRow) {
    if (($eventRow['event'] ?? '') !== 'page_view') {
      continue;
    }
    $eventPath = (string)($eventRow['path'] ?? '/');
    if ($eventPath === '') {
      $eventPath = '/';
    }
    $topPages[$eventPath] = (int)($topPages[$eventPath] ?? 0) + 1;
  }
  arsort($topPages);

  echo json_encode([
    'ok' => true,
    'stats' => [
      'today' => [
        'date' => $day,
        'page_views' => (int)($counter['days'][$day]['page_views'] ?? 0),
        'promo_shown' => (int)($counter['days'][$day]['promo_shown'] ?? 0),
        'unique_visitors' => count($counter['days'][$day]['unique_visitors'] ?? []),
      ],
      'total' => [
        'page_views' => $totalPageViews,
        'promo_shown' => $totalPromoShown,
        'daily_unique_visitors' => $totalDailyUniques,
      ],
      'top_pages' => array_slice($topPages, 0, 5, true),
      'recent_events' => array_slice($counter['events'], -8),
    ],
  ]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
  $data = [];
}

$event = preg_replace('/[^a-z0-9_:-]/i', '', (string)($data['event'] ?? 'page_view'));
if (!in_array($event, ['page_view', 'promo_shown'], true)) {
  $event = 'page_view';
}
$visitorId = preg_replace('/[^a-z0-9_-]/i', '', (string)($data['visitor_id'] ?? ''));
$path = substr((string)(parse_url((string)($data['path'] ?? '/'), PHP_URL_PATH) ?: '/'), 0, 180);
$title = substr((string)($data['title'] ?? ''), 0, 120);
$referrer = substr((string)(parse_url((string)($data['referrer'] ?? ''), PHP_URL_HOST) ?: ''), 0, 180);

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$now = gmdate('c');
$visitorHash = hash('sha256', ($visitorId !== '' ? $visitorId : $ip . '|' . $ua));

$counter['days'][$day] = $counter['days'][$day] ?? [
  'unique_visitors' => [],
  'promo_shown' => 0,
  'page_views' => 0,
];

$isNewVisitorToday = !in_array($visitorHash, $counter['days'][$day]['unique_visitors'], true);
if ($isNewVisitorToday) {
  $counter['days'][$day]['unique_visitors'][] = $visitorHash;
}

if ($event === 'page_view') {
  $counter['days'][$day]['page_views'] = (int)($counter['days'][$day]['page_views'] ?? 0) + 1;
}
if ($event === 'promo_shown') {
  $counter['days'][$day]['promo_shown'] = (int)($counter['days'][$day]['promo_shown'] ?? 0) + 1;
}

$counter['events'][] = [
  'time' => $now,
  'event' => $event,
  'path' => $path,
  'title' => $title,
  'referrer' => $referrer,
  'visitor' => substr($visitorHash, 0, 12),
  'new_daily_visitor' => $isNewVisitorToday,
];
$counter['events'] = array_slice($counter['events'], -300);

file_put_contents($counterFile, json_encode($counter, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

function bobi_send_telegram(array $config, string $message): void {
  $token = trim((string)($config['BOT_TOKEN'] ?? ''));
  $adminId = trim((string)($config['ADMIN_CHAT_ID'] ?? ''));
  if ($token === '' || $adminId === '' || str_contains($token, 'PASTE_')) {
    return;
  }

  $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
      'chat_id' => $adminId,
      'text' => $message,
      'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 8,
  ]);
  curl_exec($ch);
  curl_close($ch);
}

if ($isNewVisitorToday && $event === 'promo_shown' && ($config['VISITOR_TELEGRAM_NOTIFICATIONS'] ?? false) === true) {
  $totalUnique = count($counter['days'][$day]['unique_visitors']);
  bobi_send_telegram(
    $config,
    "BOBI visitor signal\n" .
    "Event: promo shown\n" .
    "Today unique visitors: " . $totalUnique . "\n" .
    "Page: " . ($path !== '' ? $path : '/') . "\n" .
    "Time: " . $now
  );
}

echo json_encode([
  'ok' => true,
  'new_daily_visitor' => $isNewVisitorToday,
  'today_unique_visitors' => count($counter['days'][$day]['unique_visitors']),
]);
