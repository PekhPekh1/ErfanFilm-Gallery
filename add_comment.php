<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$category = $input['cat'] ?? '';
$index = intval($input['index'] ?? 0);
$comment = $input['comment'] ?? '';

if (empty($comment)) {
    echo json_encode(['error' => 'التعليق فارغ']);
    exit;
}

$file = 'gallery_data/' . md5($category) . '_' . $index . '.json';

// تحميل البيانات
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
} else {
    $data = [
        'title' => '',
        'year' => '',
        'rating' => 0,
        'description' => '',
        'director' => '',
        'cast' => '',
        'views' => 0,
        'likes' => 0,
        'comments' => []
    ];
}

// إضافة التعليق
if (!isset($data['comments'])) {
    $data['comments'] = [];
}

$data['comments'][] = [
    'text' => htmlspecialchars($comment),
    'date' => date('Y-m-d H:i:s'),
    'user' => md5($_SERVER['REMOTE_ADDR'] ?? 'unknown')
];

// حفظ البيانات
if (!is_dir('gallery_data')) {
    mkdir('gallery_data', 0755, true);
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['success' => true, 'commentsCount' => count($data['comments'])]);
?>
