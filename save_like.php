<?php
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$category = $input['cat'] ?? '';
$index = intval($input['index'] ?? 0);

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

// تبديل الإعجاب
$likeKey = 'user_likes_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!isset($data['likedBy'])) {
    $data['likedBy'] = [];
}

if (in_array($likeKey, $data['likedBy'])) {
    $data['likedBy'] = array_filter($data['likedBy'], fn($v) => $v !== $likeKey);
    $liked = false;
} else {
    $data['likedBy'][] = $likeKey;
    $liked = true;
}

$data['likes'] = count($data['likedBy']);

// حفظ البيانات
if (!is_dir('gallery_data')) {
    mkdir('gallery_data', 0755, true);
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode(['likes' => $data['likes'], 'liked' => $liked]);
?>
