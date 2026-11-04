<?php
// مسیر پایه
$base_path = 'F:/Gallery-Erfan/';

// تابع تمیز کردن مسیر
function sanitize_filename($name) {
    $name = basename($name);
    $name = str_replace(['..', '/', '\\'], '', $name);
    return $name;
}

// گرفتن دسته‌بندی و نام فایل از URL
$category = isset($_GET['cat']) ? sanitize_filename($_GET['cat']) : '';
$image = isset($_GET['img']) ? sanitize_filename($_GET['img']) : '';
$file = isset($_GET['file']) ? sanitize_filename($_GET['file']) : '';

// اگر فایل مستقیم داده شده باشد (backward compatibility)
if ($file && !$category && !$image) {
    $file = str_replace(['..', '\\'], ['', '/'], $file);
    $full_path = realpath($base_path . $file);
} else if ($category && $image) {
    // اگر دسته‌بندی و عکس داده شده باشد
    $full_path = realpath($base_path . $category . '/' . $image);
} else {
    http_response_code(400);
    exit('پارامترهای نادرست!');
}

$base_real = realpath($base_path);

// مطمئن شدن از اینکه فایل در محدوده مجاز است
if ($full_path === false || strpos($full_path, $base_real) !== 0) {
    http_response_code(403);
    exit('دسترسی رد شد!');
}

// بررسی وجود فایل
if (!file_exists($full_path) || !is_file($full_path)) {
    http_response_code(404);
    exit('فایل یافت نشد!');
}

// بررسی نوع فایل
$allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
$file_ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_types)) {
    http_response_code(403);
    exit('نوع فایل مجاز نیست!');
}

// تنظیم نوع Content-Type
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp'
];

header('Content-Type: ' . $mime_types[$file_ext]);
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: public, max-age=86400');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');

readfile($full_path);
?>
