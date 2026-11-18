<?php
require __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=UTF-8');header('X-Robots-Tag: noindex, nofollow',true);
$action=query_string('action');
if ($action === 'watch-options') {
    $id = strtolower(query_string('id'));
    if (!preg_match('/^tt\d{5,12}$/', $id)) {
        http_response_code(400);
        echo safe_json(['ok'=>false]);
        exit;
    }
    $item = load_title($id, query_string('type'));
    if (empty($item['tmdb_id'])) {
        echo safe_json(['ok'=>true, 'options'=>[]]);
        exit;
    }
    $type = title_type($item) === 'tv' ? 'tv' : 'movie';
    $document = tmdb_document($type.'/'.(int)$item['tmdb_id'].'/watch/providers');
    if (!is_array($document['results'] ?? null)) {
        http_response_code(503);
        echo safe_json(['ok'=>false]);
        exit;
    }
    $region = $document['results']['RO'] ?? [];
    $options = [];
    foreach (['flatrate'=>'Abonament', 'free'=>'Gratuit', 'ads'=>'Cu reclame', 'rent'=>'Închiriere', 'buy'=>'Cumpărare'] as $key=>$label) {
        $names = array_unique(array_filter(array_column($region[$key] ?? [], 'provider_name'), 'is_string'));
        if ($names) $options[] = ['label'=>$label, 'names'=>array_values($names)];
    }
    echo safe_json(['ok'=>true, 'options'=>$options]);
    exit;
}
if($action==='resume'){
 $item=load_title(strtolower(query_string('id')),'tv');if(!$item||title_type($item)!=='tv'){http_response_code(404);echo safe_json(['ok'=>false]);exit;}
 $tuples=[];foreach((array)($item['seasons']??[]) as $season)foreach((array)($season['episodes_data']??[]) as $ep)$tuples[]=[(int)$season['season'],(int)$ep['episode']];sort($tuples);
 $wanted=[(int)query_string('season'),(int)query_string('episode')];$index=array_search($wanted,$tuples,true);if($index===false){echo safe_json(['ok'=>false]);exit;}
 if(query_string('completed')==='1')$index++;if(!isset($tuples[$index])){echo safe_json(['ok'=>false]);exit;}[$sn,$en]=$tuples[$index];
 echo safe_json(['ok'=>true,'season'=>$sn,'episode'=>$en,'player'=>player_url($item,$sn,$en)]);exit;
}
if($action==='season'){
 $id=strtolower(query_string('id'));$sn=(int)query_string('season','-1');if(!preg_match('/^tt\d{5,12}$/',$id)||$sn<0||$sn>10000){http_response_code(400);echo safe_json(['ok'=>false]);exit;}
 $titleItem=load_title($id,'tv');if(!$titleItem||title_type($titleItem)!=='tv'){http_response_code(404);echo safe_json(['ok'=>false]);exit;}
 $seasonEpisodes=null;foreach((array)($titleItem['seasons']??[]) as $season)if((int)$season['season']===$sn){$seasonEpisodes=(array)($season['episodes_data']??[]);break;}
 if($seasonEpisodes===null){http_response_code(404);echo safe_json(['ok'=>false]);exit;}
 ob_start();include __DIR__.'/includes/episode-list.php';$html=ob_get_clean();echo safe_json(['ok'=>true,'html'=>$html]);exit;
}
if($action==='feature'){
 $id=strtolower(query_string('id'));if(!preg_match('/^tt\d{5,12}$/',$id)){http_response_code(400);echo safe_json(['ok'=>false]);exit;}
 $item=load_title($id,query_string('type'));if(!$item){http_response_code(404);echo safe_json(['ok'=>false]);exit;}
 echo safe_json(['ok'=>true,'description'=>editorial_summary($item),'trailer'=>trailer_embed_url($item,true),'title'=>title_name($item)]);exit;
}

if($action==='related'){
 $item=load_title(strtolower(query_string('id')),query_string('type'));if(!$item){http_response_code(404);echo safe_json(['error'=>'not_found']);exit;}
 $items=array_slice(related_titles($item,12),6);ob_start();include __DIR__.'/includes/grid.php';$html=ob_get_clean();echo safe_json(['html'=>$html,'count'=>count($items)]);exit;
}

if($action==='category'){
    $genre=query_string('genre');$scope=query_string('scope')==='today'?'today':browse_scope(query_string('scope','all'));$page=max(1,(int)query_string('page','1'));
    if(!isset(genre_map()[$genre])||$page>20000){http_response_code(400);echo safe_json(['error'=>'invalid_category']);exit;}
    $doc=category_document($genre,$scope,$page);if(!$doc){http_response_code(503);echo safe_json(['error'=>'unavailable']);exit;}
    $items=(array)($doc['items']??[]);$gridLandscape=false;ob_start();include __DIR__.'/includes/grid.php';$html=ob_get_clean();
    $html=preg_replace('~^\s*<div class="movie-grid">~','',$html);$html=preg_replace('~</div>\s*$~','',$html);
    echo safe_json(['html'=>$html,'page'=>(int)($doc['page']??$page),'pages'=>(int)($doc['pages']??1)]);exit;
}
if($action==='suggest'){
    $q=substr(trim(query_string('q')),0,200);if((function_exists('mb_strlen')?mb_strlen($q):strlen($q))<2){echo safe_json(['items'=>[]]);exit;}
    $titles=array_slice(search_titles($q,10),0,6);$people=array_slice(search_people($q,6),0,4);$out=[];
    foreach($titles as $i)$out[]=['id'=>$i['imdb_id']??'','title'=>title_name($i),'year'=>$i['year']??null,'type'=>type_label(title_type($i)),'url'=>title_path($i),'poster'=>item_poster_url($i)];
    foreach($people as $p)$out[]=['id'=>$p['nconst']??'','title'=>$p['name']??'Persoană','year'=>$p['birth_year']??null,'type'=>'Persoană','url'=>$p['url']??person_path($p['nconst']??''),'poster'=>$p['image']??person_image_url($p['nconst']??'')];
    echo safe_json(['items'=>array_slice($out,0,8)]);exit;
}
if($action==='subtitle'){
    $id=strtolower(trim(query_string('id')));if(!preg_match('/^tt\d{5,12}$/',$id)){http_response_code(400);echo safe_json(['status'=>'invalid']);exit;}
    $idx=ro_subtitle_index();if(array_key_exists($id,$idx)){$r=$idx[$id];$ok=$r===true||(is_array($r)&&!empty($r['confirmed']));echo safe_json(['status'=>'cached','confirmed'=>$ok]);exit;}
    if(trim((string)cfg('opensubtitles_api_key'))===''){echo safe_json(['status'=>'unconfigured','confirmed'=>null]);exit;}
    $ok=opensubtitles_has_ro($id);if($ok===null){http_response_code(503);echo safe_json(['status'=>'unavailable','confirmed'=>null]);exit;}store_ro_subtitle_status($id,$ok,'OpenSubtitles');echo safe_json(['status'=>'checked','confirmed'=>$ok]);exit;
}
http_response_code(404);echo safe_json(['error'=>'not_found']);
