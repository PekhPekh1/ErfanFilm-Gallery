<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$category = $input['cat'] ?? '';
$index = intval($input['index'] ?? 0);
$data = $input['data'] ?? [];

$file = 'gallery_data/' . md5($category) . '_' . $index . '.json';

// تحميل البيانات الموجودة
if (file_exists($file)) {
    $existingData = json_decode(file_get_contents($file), true);
    $data = array_merge($existingData, $data);
}

// حفظ البيانات
if (!is_dir('gallery_data')) {
    mkdir('gallery_data', 0755, true);
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['success' => true]);
?>
