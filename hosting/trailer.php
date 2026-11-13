<?php
require __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
$id = strtolower(trim((string)($_GET['id'] ?? '')));
$type = (($_GET['type'] ?? '') === 'tv') ? 'tv' : 'movie';
if (!preg_match('/^tt\d{5,12}$/', $id)) {
    http_response_code(400);
    echo safe_json(['ok'=>false,'error'=>'invalid_id']);
    exit;
}
$item = ['imdb_id'=>$id,'type'=>$type];
$url = trailer_embed_url($item, true);
echo safe_json([
    'ok' => $url !== '',
    'url' => $url,
    'source' => $url !== '' ? 'cinemeta' : '',
]);
