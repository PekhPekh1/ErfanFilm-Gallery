<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function api_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// API key فقط برای api.php روی هاست اصلی است.
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
if (!hash_equals(API_KEY, (string)$providedKey)) {
    api_response(['error' => 'کلید API نامعتبر است'], 401);
}

function clean_relative_path(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = trim($path, '/');

    if ($path !== '' && (strpos($path, '..') !== false || strpos($path, "\0") !== false)) {
        api_response(['error' => 'مسیر نامعتبر است'], 400);
    }

    return $path;
}

function is_image_file(string $name): bool
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, IMAGE_EXTENSIONS, true);
}

function normalize_display_name(string $name): string
{
    // Directory Listing must be treated as UTF-8. Do not run a blind
    // Windows-1252 conversion: valid Persian UTF-8 must remain untouched.
    if (function_exists('mb_check_encoding') && mb_check_encoding($name, 'UTF-8')) {
        return $name;
    }

    // Fallback only for genuinely invalid UTF-8 byte sequences.
    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($name, 'UTF-8', 'Windows-1252');
        if ($converted !== false && $converted !== '') {
            return $converted;
        }
    }

    return $name;
}

function encode_url_path(string $relativePath): string
{
    $parts = explode('/', str_replace('\\', '/', $relativePath));
    return implode('/', array_map('rawurlencode', $parts));
}

function directory_url(string $relativeDir = ''): string
{
    $base = rtrim(MEDIA_BASE_URL, '/') . '/';
    $relativeDir = clean_relative_path($relativeDir);

    return $base . ($relativeDir !== '' ? encode_url_path($relativeDir) . '/' : '');
}

/**
 * Directory Listing را با HTTP(S) می‌خواند و لینک‌های همان پوشه را برمی‌گرداند.
 * خروجی هر آیتم: name, path, type
 */
function http_directory_entries(string $relativeDir = ''): array
{
    $relativeDir = clean_relative_path($relativeDir);
    $url = directory_url($relativeDir);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'G4-Gallery/HTTP-Directory-Listing',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml'],
    ]);

    $html = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($html === false) {
        api_response([
            'error' => 'ارتباط HTTP/HTTPS با هاست دانلود برقرار نشد',
            'detail' => $curlError
        ], 502);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        api_response([
            'error' => 'Directory Listing قابل دسترسی نیست',
            'http_code' => $httpCode,
            'url' => $url
        ], $httpCode === 404 ? 404 : 502);
    }

    if ($html === '' || (stripos($contentType, 'text/html') === false && stripos($html, '<a') === false)) {
        api_response([
            'error' => 'پاسخ هاست دانلود Directory Listing معتبر نیست',
            'content_type' => $contentType,
            'url' => $url
        ], 502);
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    if (!$loaded) {
        api_response(['error' => 'خواندن HTML مربوط به Directory Listing ناموفق بود'], 502);
    }

    $entries = [];
    $seen = [];

    foreach ($dom->getElementsByTagName('a') as $anchor) {
        $href = trim((string) $anchor->getAttribute('href'));
        if ($href === '' || $href[0] === '#') {
            continue;
        }

        // لینک‌های parent/current یا خارج از این دایرکتوری را نادیده می‌گیریم.
        if ($href === '../' || $href === '..' || $href === './' || $href === '.') {
            continue;
        }

        // Query/fragment را حذف می‌کنیم.
        $href = preg_replace('/[?#].*$/', '', $href);

        // فقط لینک نسبی را قبول می‌کنیم.
        $parts = parse_url($href);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            continue;
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || $path === '/') {
            continue;
        }

        $urlPath = str_replace('\\', '/', $path);
        $path = rawurldecode($urlPath);

        // لینک absolute-path مثل /www/foo را فقط اگر زیر MEDIA_BASE_URL باشد نمی‌پذیریم
        // تا از خروج از ریشه جلوگیری شود. Directory Listing معمولاً لینک نسبی می‌دهد.
        if (str_starts_with($path, '/')) {
            continue;
        }

        $isDir = str_ends_with($path, '/');
        $name = basename(rtrim($path, '/'));
        $name = normalize_display_name($name);
        $urlName = basename(rtrim($urlPath, '/'));

        if ($name === '' || $name === '.' || $name === '..') {
            continue;
        }

        if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            continue;
        }

        // فقط آیتم مستقیم داخل همین listing.
        if (strpos(trim($path, '/'), '/') !== false) {
            continue;
        }

        $relative = ($relativeDir !== '' ? $relativeDir . '/' : '') . $name;
        $relativeUrlPath = ($relativeDir !== '' ? $relativeDir . '/' : '') . rawurldecode($urlName);
        $key = strtolower($relative) . '|' . ($isDir ? 'dir' : 'file');

        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $entries[] = [
            'name' => $name,
            'display_name' => $name,
            'path' => $relative,
            'url_path' => $relativeUrlPath,
            'url_name' => $urlName,
            'type' => $isDir ? 'dir' : 'file'
        ];
    }

    usort($entries, static function (array $a, array $b): int {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });

    return $entries;
}

/** Resolve a user-facing category name to the exact URL path from Directory Listing. */
function resolve_category_url_path(string $category): string
{
    $category = clean_relative_path($category);
    $entries = http_directory_entries('');

    foreach ($entries as $entry) {
        if ($entry['type'] === 'dir' && ($entry['name'] === $category || ($entry['display_name'] ?? '') === $category)) {
            return $entry['url_path'] ?? $entry['path'];
        }
    }

    // Also allow an already encoded/URL-safe path when explicitly supplied.
    return $category;
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : 'test';

if ($action === 'test') {
    $entries = http_directory_entries();
    $dirs = 0;
    $files = 0;

    foreach ($entries as $entry) {
        if ($entry['type'] === 'dir') {
            $dirs++;
        } else {
            $files++;
        }
    }

    api_response([
        'success' => true,
        'message' => 'اتصال HTTP/HTTPS و Directory Listing موفق بود',
        'base_url' => rtrim(MEDIA_BASE_URL, '/') . '/',
        'directories' => $dirs,
        'files' => $files
    ]);
}

if ($action === 'categories') {
    $entries = http_directory_entries();
    $categories = [];

    foreach ($entries as $entry) {
        if ($entry['type'] !== 'dir') {
            continue;
        }

        // شمارش تصاویر مستقیم داخل هر دسته
        $count = 0;
        try {
            $files = http_directory_entries($entry['url_path'] ?? $entry['path']);
            foreach ($files as $file) {
                if ($file['type'] === 'file' && is_image_file($file['name'])) {
                    $count++;
                }
            }
        } catch (Throwable $e) {
            // Keep the category visible even if one child listing cannot be read.
            $count = 0;
        }

        $categories[] = [
            'name' => $entry['name'],
            'count' => $count
        ];
    }

    usort($categories, static function (array $a, array $b): int {
        return strnatcasecmp($a['name'], $b['name']);
    });

    api_response(['categories' => $categories]);
}

if ($action === 'images') {
    $cat = isset($_GET['cat']) ? clean_relative_path((string) $_GET['cat']) : '';

    if ($cat === '') {
        api_response(['error' => 'دسته‌بندی مشخص نشده است'], 400);
    }

    $resolvedCat = resolve_category_url_path($cat);
    $entries = http_directory_entries($resolvedCat);
    $images = [];
    $names = [];

    foreach ($entries as $entry) {
        if ($entry['type'] !== 'file' || !is_image_file($entry['name'])) {
            continue;
        }

        $images[] = rtrim(MEDIA_BASE_URL, '/') . '/' . encode_url_path($entry['url_path'] ?? $entry['path']);
        $names[] = pathinfo($entry['name'], PATHINFO_FILENAME);
    }

    array_multisort($names, SORT_NATURAL | SORT_FLAG_CASE, $images);

    api_response([
        'images' => $images,
        'names' => $names
    ]);
}

api_response(['error' => 'عملیات نامشخص'], 400);
