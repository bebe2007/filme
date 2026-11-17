<?php
require __DIR__.'/includes/functions.php';
header('X-Content-Type-Options: nosniff');
$id=strtolower(query_string('id'));if(!preg_match('/^tt\d{5,12}$/',$id)){http_response_code(404);exit;}
$preview=load_title_preview($id)?:['imdb_id'=>$id,'type'=>query_string('type')==='tv'?'tv':'movie'];
$type=query_string('type');if(in_array($type,['movie','tv'],true))$preview['type']=$type;
$kind=query_string('kind','poster');if(!in_array($kind,['poster','backdrop','season','episode'],true)){http_response_code(400);exit;}
$season=max(0,min(10000,(int)query_string('season')));$episode=max(1,min(10000,(int)query_string('episode','1')));
function media_redirect($url){$w=(int)query_string('w','0');if(in_array($w,[185,342,500,780,1280],true)&&preg_match('~^https://image\.tmdb\.org/t/p/(?:w[0-9]+|original)/~',$url))$url=preg_replace('~(/t/p/)(?:w[0-9]+|original)/~','$1w'.$w.'/',$url);if($w>=342&&str_starts_with($url,'https://images.metahub.space/poster/small/'))$url=str_replace('/poster/small/','/poster/medium/',$url);header('Cache-Control: public, max-age=3600');header('Referrer-Policy: no-referrer');header('Location: '.$url,true,302);exit;}

$meta=cached_title_metadata($id,false);
// List posters can use the direct image source without blocking on metadata APIs.
if(!is_array($meta))$meta=tmdb_enabled()?resolve_title_metadata($preview):[];
$excluded=query_string('exclude');
$url='';
if(in_array($kind,['season','episode'],true)&&title_type($preview)==='tv'&&!empty($meta['tmdb_id'])){
    // A season document supplies its own poster and every episode still in one JSON request.
    $doc=tmdb_document('tv/'.(int)$meta['tmdb_id'].'/season/'.$season);
    $image=$kind==='season'?($doc['poster_path']??''):'';
    if($kind==='episode')foreach((array)($doc['episodes']??[]) as $ep){if((int)($ep['episode_number']??0)===$episode){$image=$ep['still_path']??'';break;}}
    if(is_string($image)&&preg_match('~^/[A-Za-z0-9._/-]+$~',$image))$url='https://image.tmdb.org/t/p/w780'.$image;
}
$field=in_array($kind,['backdrop','episode'],true)?'background':'poster';
if($url==='')foreach([$meta[$field]??'', $preview[$field]??'', $meta['poster']??'', $preview['poster']??''] as $candidate){if(valid_image_url($candidate)&&$candidate!==$excluded){$url=$candidate;break;}}
if($url==='')$url=in_array($kind,['backdrop','episode'],true)?'https://images.metahub.space/background/medium/'.rawurlencode($id).'/img':poster_source_url($id);
// Redirect only. Images are requested directly by the visitor's browser; no files are saved.
if($url===$excluded){header('Content-Type: image/svg+xml');header('Cache-Control: public, max-age=3600');readfile(__DIR__.'/assets/img/poster-placeholder.svg');exit;}
media_redirect($url);
