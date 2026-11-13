<?php
require __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/v14-ui.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$id=strtolower(query_string('id'));
if(!preg_match('/^tt\d{5,12}$/',$id)){http_response_code(400);echo safe_json(['ok'=>false]);exit;}
try{$item=load_title($id,'movie');}catch(Throwable $error){http_response_code(503);echo safe_json(['ok'=>false]);exit;}
if(!$item||title_type($item)!=='movie'){http_response_code(404);echo safe_json(['ok'=>false]);exit;}
$entries=v14_entries();$director=$entries[$id]['director']??'';
if($director==='')$director=natural_list(array_slice(natural_names($item['directors']??[]),0,2));
echo safe_json(['ok'=>true,'id'=>$id,'description'=>v14_summary($item),'director'=>$director]);
