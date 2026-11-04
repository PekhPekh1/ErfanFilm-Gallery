<?php
header('Content-Type: application/json');

$category = isset($_GET['cat']) ? $_GET['cat'] : '';
$index = isset($_GET['index']) ? intval($_GET['index']) : 0;

$file = 'gallery_data/' . md5($category) . '_' . $index . '.json';

if (file_exists($file)) {
    echo file_get_contents($file);
} else {
    echo json_encode([
        'title' => '',
        'year' => '',
        'rating' => 0,
        'description' => '',
        'director' => '',
        'cast' => '',
        'views' => 0,
        'likes' => 0,
        'comments' => []
    ]);
}
?>
