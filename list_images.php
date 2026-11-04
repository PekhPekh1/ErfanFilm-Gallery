<?php
declare(strict_types=1);

// این فایل روی همان هاست اصلی اجرا می‌شود؛ دیگر به api.php روی هاست دانلود وصل نمی‌شود.
require_once __DIR__ . '/config.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$apiBase = $scheme . '://' . $host . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/api.php';
$apiUrl = $apiBase . '?action=images&cat=' . rawurlencode((string)($_GET['cat'] ?? ''));

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . API_KEY],
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');

if ($response !== false && $httpCode === 200) {
    echo $response;
    exit;
}

echo json_encode([
    'error' => 'خطا در خواندن تصاویر از هاست دانلود',
    'http_code' => $httpCode,
    'detail' => $curlError
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
