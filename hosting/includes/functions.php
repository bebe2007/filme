<?php
require_once __DIR__.'/shard-reader.php';

function cfg($key = null) {
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
        $local = dirname(__DIR__) . '/config.local.php';
        if (is_file($local)) {
            $extra = require $local;
            if (is_array($extra)) $config = array_replace($config, $extra);
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function safe_json($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); }
function ro_number($n) { return number_format((int)$n, 0, ',', '.'); }

function cache_dir() { return dirname(__DIR__).'/cache'; }
function cache_path_for_url($url) { return cache_dir() . '/' . sha1($url) . '.cache'; }
function lock_path_for_url($url) { return ''; }

function fetch_remote_now($url,$timeout=8,array $headers=[]){
 $limit=2*1024*1024;$body='';$oversize=false;
 if(function_exists('curl_init')){
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>min(4,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'FilmeBratuMarian/11',CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json,text/plain,text/html,*/*'],$headers),CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use(&$body,&$oversize,$limit){if(strlen($body)+strlen($chunk)>$limit){$oversize=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
  $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return !$oversize&&$ok!==false&&$code>=200&&$code<300?$body:false;
 }
 $context=stream_context_create(['http'=>['timeout'=>$timeout,'follow_location'=>1,'user_agent'=>'FilmeBratuMarian/11','header'=>implode("\r\n",array_merge(['Accept: application/json,text/plain,text/html,*/*'],$headers))."\r\n"],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
 $stream=@fopen($url,'rb',false,$context);if(!$stream)return false;
 while(!feof($stream)){$chunk=fread($stream,16384);if($chunk===false){fclose($stream);return false;}$body.=$chunk;if(strlen($body)>$limit){fclose($stream);return false;}}
 fclose($stream);return $body!==''?$body:false;
}

function write_cache_atomic($cache,$data) { return false; }

function refresh_url_cache($url,$cache,$lock,$timeout=8) {return fetch_remote_now($url,$timeout);}

function queue_stale_refresh($url,$cache,$lock) {return;}

// Request memory and optional APCu RAM cache only. No files or locks are created.
function remote_text($url,$ttl=null,$staleTtl=null,$timeout=8) {
 static $request=[];
 if(array_key_exists($url,$request))return $request[$url];
 $ttl=$ttl===null?(int)cfg('cache_ttl'):max(0,(int)$ttl);
 $key='filme_v11_remote_'.sha1($url);
 if($ttl>0&&function_exists('apcu_fetch')){$hit=false;$v=apcu_fetch($key,$hit);if($hit&&is_string($v))return $request[$url]=$v;}
 $data=fetch_remote_now($url,max(1,(int)$timeout));
 if(!is_string($data)||$data===''||(substr((string)parse_url($url,PHP_URL_PATH),-5)==='.json'&&!is_array(json_decode($data,true))))return $request[$url]=false;
 if($ttl>0&&function_exists('apcu_store'))@apcu_store($key,$data,$ttl);
 return $request[$url]=$data;
}

function remote_json($url, $ttl = null, $staleTtl = null, $timeout = 8) {
    $raw = remote_text($url, $ttl, $staleTtl, $timeout); if ($raw === false) return null;
    $data = json_decode($raw, true); return is_array($data) ? $data : null;
}

function data_url($path) { return rtrim(cfg('data_base_url'), '/') . '/' . ltrim($path, '/'); }
function provider_url($path) { return rtrim(cfg('provider_base_url'), '/') . '/' . ltrim($path, '/'); }
function catalog_text($path, $ttl = null) {
    $ttl = $ttl === null ? (int)cfg('catalog_cache_ttl') : max(0,(int)$ttl);
    return remote_text(data_url($path), $ttl, (int)cfg('catalog_cache_stale_ttl'), 3);
}
function catalog_json($path, $ttl = null) {
    $raw = catalog_text($path, $ttl); if ($raw === false) return null;
    $data = json_decode($raw, true); return is_array($data) ? $data : null;
}
function poster_url($imdbId) { return '/poster.php?id=' . rawurlencode((string)$imdbId); }
function poster_source_url($imdbId, $size='medium') { return 'https://images.metahub.space/poster/' . rawurlencode((string)$size) . '/' . rawurlencode((string)$imdbId) . '/img'; }
function item_poster_url($item) {
 $poster=$item['poster']??'';
 if(valid_image_url($poster)&&!preg_match('~/(?:background|backdrop)(?:/|$)~i',$poster))return $poster;
 return poster_source_url($item['imdb_id']??'');
}
function item_background_url($item) { return media_url($item,'backdrop'); }
function valid_youtube_video_id($id) { return (bool)preg_match('/^[A-Za-z0-9_-]{6,20}$/', trim((string)$id)); }

function trailer_youtube_id($item, $allowRemote = true) {
    // 1) Câmp editorial verificat manual are întotdeauna prioritate.
    $manual = trim((string)($item['trailer_youtube'] ?? ''));
    if (valid_youtube_video_id($manual)) return $manual;

    if (!$allowRemote) return '';

    // 2) Pentru restul catalogului rezolvăm automat după IMDb ID din Cinemeta.
    $imdb = strtolower(trim((string)($item['imdb_id'] ?? '')));
    if (!preg_match('/^tt\d{5,12}$/', $imdb)) return '';
    $type = title_type($item) === 'tv' ? 'series' : 'movie';
    $base = rtrim((string)cfg('trailer_meta_base_url'), '/');
    if ($base === '') return '';
    $url = $base . '/meta/' . $type . '/' . rawurlencode($imdb) . '.json';
    $doc = remote_json($url, (int)cfg('trailer_cache_ttl'));
    if (!is_array($doc)) return '';
    $meta = (isset($doc['meta']) && is_array($doc['meta'])) ? $doc['meta'] : $doc;

    // Format standard Stremio/Cinemeta: trailers:[{source:"YouTubeId",type:"Trailer"},...]
    $fallback = '';
    foreach ((array)($meta['trailers'] ?? []) as $trailer) {
        $source = '';
        $kind = '';
        if (is_array($trailer)) {
            $source = trim((string)($trailer['source'] ?? $trailer['youtubeId'] ?? $trailer['id'] ?? ''));
            $kind = strtolower(trim((string)($trailer['type'] ?? '')));
        } elseif (is_string($trailer)) {
            $source = trim($trailer);
        }
        if (!valid_youtube_video_id($source)) continue;
        if ($kind === 'trailer' || $kind === 'official trailer') return $source;
        if ($fallback === '') $fallback = $source;
    }
    if ($fallback !== '') return $fallback;

    // Compatibilitate cu răspunsuri simplificate/mai vechi.
    foreach (['trailer_youtube','trailer','youtubeTrailer','youtube_trailer'] as $key) {
        $candidate = trim((string)($meta[$key] ?? ''));
        if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,20})~i', $candidate, $m)) $candidate = $m[1];
        if (valid_youtube_video_id($candidate)) return $candidate;
    }
    return '';
}

function trailer_embed_url($item, $allowRemote = true) {
    $id = trailer_youtube_id($item, $allowRemote);
    if ($id === '') return '';
    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($id) . '?autoplay=1&rel=0&modestbranding=1';
}
function person_image_url($nconst) { return '/person-image.php?id=' . rawurlencode((string)$nconst); }
function imdb_person_url($nconst) { return 'https://www.imdb.com/name/' . rawurlencode((string)$nconst) . '/'; }
function person_role_key($role) {
    $role=trim((string)$role);
    return in_array($role,['actor','regizor','scenarist'],true)?$role:'';
}
function person_role_label($role) {
    return ['actor'=>'Actor / actriță','regizor'=>'Regie','scenarist'=>'Scenariu'][person_role_key($role)] ?? 'Filmografie';
}
function person_profession_label($profession) {
    $key=strtolower(trim((string)$profession));
    $map=['actor'=>'actor','actress'=>'actriță','director'=>'regizor','writer'=>'scenarist','producer'=>'producător','composer'=>'compozitor','cinematographer'=>'director de imagine','editor'=>'montaj','soundtrack'=>'coloană sonoră','music_artist'=>'artist muzical','stunts'=>'cascadorii','production_designer'=>'scenografie'];
    return $map[$key]??str_replace('_',' ',$key);
}
function person_role_heading($role,$name) {
    $role=person_role_key($role);
    if($role==='regizor') return 'Filme și seriale regizate de '.$name;
    if($role==='scenarist') return 'Filme și seriale scrise de '.$name;
    return 'Filme și seriale cu '.$name;
}
function person_path($person,$role=null,$page=1) {
    $id=is_array($person)?($person['nconst']??''):(string)$person;
    $role=person_role_key($role);
    $base='/persoana/'.rawurlencode($id).'/';
    if($role!=='')$base.=rawurlencode($role).'/';
    return $page>1?$base.'pagina/'.(int)$page.'/':$base;
}
function person_url($person,$role=null,$page=1) { return site_url(ltrim(person_path($person,$role,$page),'/')); }
function person_links_html($people,$role) {
    $out=[];
    foreach((array)$people as $person){
        $name=is_array($person)?trim((string)($person['name']??'')):trim((string)$person);
        if($name==='')continue;
        $nconst=is_array($person)?trim((string)($person['nconst']??'')):'';
        if(preg_match('/^nm\d{5,12}$/i',$nconst))$out[]='<a class="person-inline" href="'.e(person_path(strtolower($nconst),$role)).'">'.e($name).'</a>';
        else $out[]=e($name);
    }
    return implode(', ',$out);
}
function person_schema_ref($person,$role='actor') {
    $name=is_array($person)?trim((string)($person['name']??'')):trim((string)$person);
    if($name==='')$name='Persoană';
    $out=['@type'=>'Person','name'=>$name];
    $nconst=is_array($person)?strtolower(trim((string)($person['nconst']??''))):'';
    if(preg_match('/^nm\d{5,12}$/',$nconst)){$out['url']=person_url($nconst,$role);$out['sameAs']=imdb_person_url($nconst);}
    return $out;
}
function json_object_member_decode($raw,$key) {
    if(!is_string($raw)||$raw===''||!is_string($key)||$key==='')return null;
    $needle='"'.addcslashes($key,"\\\"").'"';
    $pos=strpos($raw,$needle);
    if($pos===false)return null;
    $len=strlen($raw);$pos+=strlen($needle);
    while($pos<$len&&ctype_space($raw[$pos]))$pos++;
    if($pos>=$len||$raw[$pos]!==':')return null;$pos++;
    while($pos<$len&&ctype_space($raw[$pos]))$pos++;
    if($pos>=$len||$raw[$pos]!=='{')return null;
    $start=$pos;$depth=0;$inString=false;$escaped=false;
    for(;$pos<$len;$pos++){
        $ch=$raw[$pos];
        if($inString){
            if($escaped){$escaped=false;continue;}
            if($ch==='\\'){$escaped=true;continue;}
            if($ch==='"')$inString=false;
            continue;
        }
        if($ch==='"'){$inString=true;continue;}
        if($ch==='{'){$depth++;continue;}
        if($ch==='}'){$depth--;if($depth===0){
            $piece=substr($raw,$start,$pos-$start+1);
            $decoded=json_decode($piece,true);
            return is_array($decoded)?$decoded:null;
        }}
    }
    return null;
}
function person_record_cache_path($nconst) {return cache_dir().'/person-records/'.strtolower((string)$nconst).'.json';}
function load_person($nconst) {
    $nconst=strtolower(trim((string)$nconst));if(!preg_match('/^nm\d{5,12}$/',$nconst))return null;
    $key='filme_v11_person_'.$nconst;$person=false;
    if(function_exists('apcu_fetch')){$hit=false;$person=apcu_fetch($key,$hit);if(!$hit)$person=false;}
    if(!is_array($person)){
        $person=fetch_catalog_member(data_url('person-shards/'.substr($nconst,0,5).'.json'),$nconst);
        if(!is_array($person))return null;
        if(function_exists('apcu_store'))@apcu_store($key,$person,max(60,(int)cfg('cache_ttl')));
    }
    if(!isset($person['roles'])||!is_array($person['roles']))$person['roles']=[];
    foreach(['actor','regizor','scenarist'] as $role)$person['roles'][$role]=catalog_filter_items((array)($person['roles'][$role]??[]));
    return $person;
}
function site_url($path = '/') { if(preg_match('~^https://~i',(string)$path))return (string)$path;return rtrim(cfg('site_url'), '/') . '/' . ltrim($path, '/'); }
function imdb_url($id) { return 'https://www.imdb.com/title/' . rawurlencode((string)$id) . '/'; }

function normalize_text($value) {
    $value = trim((string)$value); if ($value === '') return '';
    $value=strtr($value,['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t','Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ş'=>'S','Ț'=>'T','Ţ'=>'T']);
    if (function_exists('iconv')) { $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value); if($ascii!==false)$value=$ascii; }
    $value = strtolower($value); $value = preg_replace('/[^a-z0-9]+/', ' ', $value); return trim($value);
}
function slugify($value) { return str_replace(' ', '-', normalize_text($value)); }

function genre_map() { return [
    'Action'=>['Acțiune','actiune'],'Adventure'=>['Aventură','aventura'],'Animation'=>['Animație','animatie'],'Biography'=>['Biografic','biografic'],
    'Comedy'=>['Comedie','comedie'],'Crime'=>['Crimă','crima'],'Documentary'=>['Documentar','documentar'],'Drama'=>['Dramă','drama'],
    'Family'=>['Familie','familie'],'Fantasy'=>['Fantezie','fantezie'],'Film-Noir'=>['Film noir','film-noir'],'Game-Show'=>['Game show','game-show'],
    'History'=>['Istoric','istoric'],'Horror'=>['Horror','horror'],'Music'=>['Muzică','muzica'],'Musical'=>['Muzical','muzical'],
    'Mystery'=>['Mister','mister'],'News'=>['Știri','stiri'],'Reality-TV'=>['Reality TV','reality-tv'],'Romance'=>['Romantic','romantic'],
    'Sci-Fi'=>['SF','sf'],'Short'=>['Scurtmetraj','scurtmetraj'],'Sport'=>['Sport','sport'],'Talk-Show'=>['Talk show','talk-show'],
    'Thriller'=>['Thriller','thriller'],'War'=>['Război','razboi'],'Western'=>['Western','western'],'Adult'=>['Adult','adult']
]; }
function genre_label($genre) { $m=genre_map(); return isset($m[$genre])?$m[$genre][0]:$genre; }
function genre_route_slug($genre) { $m=genre_map(); return isset($m[$genre])?$m[$genre][1]:slugify($genre); }
function resolve_genre_from_route($slug,$availableGenres){foreach((array)$availableGenres as $g){if($g==='Adult'&&cfg('hide_adult'))continue;if(genre_route_slug($g)===$slug||slugify($g)===$slug)return $g;}return null;}
function public_genres($genres){return array_values(array_filter((array)$genres,function($g){return !(cfg('hide_adult')&&$g==='Adult');}));}
function is_public_item($item){return !(cfg('hide_adult') && in_array('Adult',(array)($item['genres']??[]),true));}
function filter_public_items($items){return array_values(array_filter((array)$items,'is_public_item'));}

function title_name($item) { return $item['romanian_title'] ?? $item['title'] ?? $item['original_title'] ?? $item['imdb_id'] ?? 'Titlu'; }
function title_type($item) { return (($item['type']??'')==='tv')?'tv':'movie'; }
function type_label($type) { return $type==='tv'?'Serial':'Film'; }
function title_path($item){$id=$item['imdb_id']??'';return (title_type($item)==='tv'?'/serial/':'/film/').rawurlencode($id).'/';}
function title_url($item){return site_url(ltrim(title_path($item),'/'));}
function listing_path($kind,$page=1){$base=$kind==='tv'?'/seriale/':'/filme/';return $page>1?$base.'pagina/'.(int)$page.'/':$base;}

function ro_subtitle_document(){
    static $doc=null;if($doc!==null)return $doc;
    $path=cfg('romanian_subtitles_index');$raw=is_file($path)?@file_get_contents($path):false;$data=$raw?json_decode($raw,true):null;
    $doc=is_array($data)?$data:['complete'=>false,'items'=>[]];if(!isset($doc['items'])||!is_array($doc['items']))$doc['items']=[];return $doc;
}
function ro_subtitle_index(){return ro_subtitle_document()['items'];}
function has_verified_ro_subtitle($id){$idx=ro_subtitle_index();$row=$idx[strtolower((string)$id)]??null;return $row===true||(is_array($row)&&!empty($row['confirmed']));}
function store_ro_subtitle_status($id,$confirmed,$source='OpenSubtitles'){return false; /* readonly catalog */}
function subtitle_filter_active(){ $doc=ro_subtitle_document(); return cfg('romanian_subtitles_mode')==='confirmed_only' && !empty($doc['complete']) && count($doc['items'])>0; }
function filter_subtitle_items($items){if(!subtitle_filter_active())return array_values((array)$items);return array_values(array_filter((array)$items,function($x){return has_verified_ro_subtitle($x['imdb_id']??'');}));}
function title_is_blocked($id){return in_array(strtolower((string)$id),array_map('strtolower',(array)cfg('blocked_title_ids')),true);}
function catalog_filter_items($items){$items=array_values(array_filter((array)$items,static function($item){return is_array($item)&&preg_match('/^tt\d{5,12}$/',(string)($item['imdb_id']??''));}));return array_values(array_filter(filter_subtitle_items(filter_public_items($items)),static function($item){return !title_is_blocked($item['imdb_id']??'');}));}

function title_preview_cache_dir(){return cache_dir().'/titles';}
function title_metadata_cache_dir(){return cache_dir().'/metadata';}
function title_metadata_cache_path($id){return title_metadata_cache_dir().'/'.strtolower((string)$id).'.json';}
function cached_title_metadata($id,$allowStale=false){
 if(function_exists('apcu_fetch')){$hit=false;$row=apcu_fetch('filme_metadata_'.strtolower((string)$id),$hit);if($hit&&is_array($row))return $row;}return null;
}
function store_title_metadata($id,array $row){
 $id=strtolower((string)$id);if(!preg_match('/^tt\d{5,12}$/',$id))return false;$row['_checked_at']=time();
 return function_exists('apcu_store')?apcu_store('filme_metadata_'.$id,$row,max(300,(int)cfg('title_metadata_ttl'))):false;
}
function tmdb_enabled(){return trim((string)cfg('tmdb_api_key'))!==''||trim((string)cfg('tmdb_api_token'))!=='';}
function tmdb_find_document($imdbId,$language){
    if(!tmdb_enabled())return null;
    $params=['external_source'=>'imdb_id','language'=>$language];$key=trim((string)cfg('tmdb_api_key'));
    if($key!=='')$params['api_key']=$key;
    $url='https://api.themoviedb.org/3/find/'.rawurlencode($imdbId).'?'.http_build_query($params);
    $headers=['Accept: application/json'];$token=trim((string)cfg('tmdb_api_token'));
    if($token!=='')$headers[]='Authorization: Bearer '.$token;
    $raw=fetch_remote_now($url,6,$headers);if($raw===false)return null;$doc=json_decode($raw,true);return is_array($doc)?$doc:null;
}
function tmdb_pick_title_result($doc,$type){
    if(!is_array($doc))return null;$key=$type==='tv'?'tv_results':'movie_results';$rows=(array)($doc[$key]??[]);
    return isset($rows[0])&&is_array($rows[0])?$rows[0]:null;
}
function tmdb_metadata($item){
    if(!tmdb_enabled())return [];$id=strtolower(trim((string)($item['imdb_id']??'')));if(!preg_match('/^tt\d{5,12}$/',$id))return [];
    $type=title_type($item);$preferred=trim((string)cfg('tmdb_language'))?:'ro-RO';$row=tmdb_pick_title_result(tmdb_find_document($id,$preferred),$type);if(!$row)return [];
    $overview=trim((string)($row['overview']??''));$descLang=$overview!==''?$preferred:'';
    if($overview===''){
        $fallback=trim((string)cfg('tmdb_fallback_language'))?:'en-US';$en=tmdb_pick_title_result(tmdb_find_document($id,$fallback),$type);
        if($en){$overview=trim((string)($en['overview']??''));if($overview!=='')$descLang=$fallback;foreach(['poster_path','backdrop_path','original_language','release_date','first_air_date'] as $k){if(empty($row[$k])&&!empty($en[$k]))$row[$k]=$en[$k];}}
    }
    $posterPath=trim((string)($row['poster_path']??''));$backdropPath=trim((string)($row['backdrop_path']??''));
    $out=[
        'metadata_source'=>'TMDb','tmdb_id'=>(int)($row['id']??0),
        'description'=>$overview?:null,'description_language'=>$descLang?:null,'description_source'=>$overview!==''?'TMDb':null,
        'poster'=>$posterPath!==''?'https://image.tmdb.org/t/p/w780'.$posterPath:null,
        'background'=>$backdropPath!==''?'https://image.tmdb.org/t/p/w1280'.$backdropPath:null,
        'image_source'=>($posterPath!==''||$backdropPath!=='')?'TMDb':null,
        'language'=>trim((string)($row['original_language']??''))?:null,
        'released'=>trim((string)($type==='tv'?($row['first_air_date']??''):($row['release_date']??'')))?:null,
    ];
    return array_filter($out,function($v){return $v!==null&&$v!==''&&$v!==0;});
}
function cinemeta_metadata($item){
    $id=strtolower(trim((string)($item['imdb_id']??'')));if(!preg_match('/^tt\d{5,12}$/',$id))return [];
    $ct=title_type($item)==='tv'?'series':'movie';$base=rtrim((string)cfg('trailer_meta_base_url'),'/');if($base==='')return [];
    $doc=remote_json($base.'/meta/'.$ct.'/'.rawurlencode($id).'.json',max(86400,(int)cfg('title_metadata_fallback_ttl')));$m=is_array($doc)?($doc['meta']??null):null;if(!is_array($m))return [];
    $description=trim((string)($m['description']??''));$poster=trim((string)($m['poster']??''));$background=trim((string)($m['background']??''));
    $out=['metadata_source'=>'Cinemeta'];
    if($description!==''){$out['description']=$description;$out['description_language']='en';$out['description_source']='Cinemeta';}
    if(preg_match('~^https://~i',$poster)){$out['poster']=$poster;$out['image_source']='Cinemeta';}
    if(preg_match('~^https://~i',$background)){$out['background']=$background;$out['image_source']='Cinemeta';}
    if(!empty($m['released']))$out['released']=(string)$m['released'];
    return $out;
}
function resolve_title_metadata($item){
    if(!is_array($item))return [];$id=strtolower(trim((string)($item['imdb_id']??'')));if(!preg_match('/^tt\d{5,12}$/',$id))return [];
    $cached=cached_title_metadata($id,false);
    if(is_array($cached)){
        $age=time()-(int)($cached['_checked_at']??time());
        // Dacă TMDb tocmai a fost activat, upgradează rapid un cache vechi Cinemeta.
        if(!(tmdb_enabled()&&strpos((string)($cached['metadata_source']??''),'TMDb')===false&&$age>21600))return $cached;
    }
    $meta=[];
    if(tmdb_enabled())$meta=tmdb_metadata($item);
    $hadTmdb=(($meta['metadata_source']??'')==='TMDb');
    $needDescription=empty($meta['description']);$needPoster=empty($meta['poster']);$needBackground=empty($meta['background']);
    if($needDescription||$needPoster||$needBackground){
        $fallback=cinemeta_metadata($item);
        foreach($fallback as $k=>$v){if(!isset($meta[$k])||$meta[$k]===null||$meta[$k]==='')$meta[$k]=$v;}
        if($hadTmdb&&!empty($fallback))$meta['metadata_source']='TMDb + Cinemeta';
    }
    if(!$meta)$meta=['metadata_source'=>'none'];
    store_title_metadata($id,$meta);return $meta;
}
function enrich_title_item($item){
    if(!is_array($item))return $item;$meta=resolve_title_metadata($item);if(!is_array($meta))return $item;
    if(stripos((string)($meta['description_language']??''),'ro')===0 && empty($item['editorial']['description'])){foreach(['description','description_language','description_source'] as $field){if(isset($meta[$field]))$item[$field]=$meta[$field];}}
    foreach(['description','description_language','description_source','poster','background','image_source','language','released','tmdb_id','metadata_source'] as $k){
        if((!isset($item[$k])||$item[$k]===null||$item[$k]==='')&&isset($meta[$k])&&$meta[$k]!==null&&$meta[$k]!=='')$item[$k]=$meta[$k];
    }
    return $item;
}
function remember_title_preview($item){
 if(!is_array($item))return;$id=strtolower((string)($item['imdb_id']??''));if(!preg_match('/^tt\d{5,12}$/',$id))return;
 if(function_exists('apcu_store'))@apcu_store('filme_preview_'.$id,$item,21600);
}
function remember_title_previews($items){foreach((array)$items as $item)remember_title_preview($item);}
function load_title_preview($id){
 if(function_exists('apcu_fetch')){$hit=false;$row=apcu_fetch('filme_preview_'.strtolower((string)$id),$hit);if($hit&&is_array($row))return $row;}return null;
}
function cinemeta_title($id,$typeHint=null){
    $types=[];
    if($typeHint==='tv'||$typeHint==='series')$types=['series'];
    elseif($typeHint==='movie')$types=['movie'];
    else $types=['movie','series'];
    foreach($types as $ct){
        $url='https://v3-cinemeta.strem.io/meta/'.$ct.'/'.rawurlencode($id).'.json';
        $doc=remote_json($url,86400);$m=is_array($doc)?($doc['meta']??null):null;if(!is_array($m))continue;
        $row=[
            'imdb_id'=>$id,
            'type'=>$ct==='series'?'tv':'movie',
            'title'=>$m['name']??$id,
            'original_title'=>$m['name']??null,
            'romanian_title'=>null,
            'year'=>isset($m['releaseInfo'])&&preg_match('/(18|19|20)\d{2}/',(string)$m['releaseInfo'],$ym)?(int)$ym[0]:null,
            'runtime'=>isset($m['runtime'])&&preg_match('/\d+/',(string)$m['runtime'],$rm)?(int)$rm[0]:null,
            'genres'=>array_values(array_filter((array)($m['genres']??[]))),
            'rating'=>isset($m['imdbRating'])&&is_numeric($m['imdbRating'])?(float)$m['imdbRating']:null,
            'votes'=>null,
            'description'=>trim((string)($m['description']??'')),
            'poster'=>preg_match('~^https://~i',(string)($m['poster']??''))?(string)$m['poster']:null,
            'background'=>preg_match('~^https://~i',(string)($m['background']??''))?(string)$m['background']:null,
            'directors'=>[], 'writers'=>[], 'cast'=>[], 'credits'=>[], 'seasons'=>[]
        ];
        foreach((array)($m['director']??[]) as $name){if(is_string($name)&&trim($name)!=='')$row['directors'][]=['name'=>trim($name)];}
        foreach((array)($m['cast']??[]) as $name){if(is_string($name)&&trim($name)!=='')$row['cast'][]=['name'=>trim($name),'category'=>'actor','characters'=>[]];}
        if(!is_public_item($row))return null;
        remember_title_preview($row);return $row;
    }
    return null;
}
function queue_cinemeta_preview_refresh($id,$typeHint){
    if(!function_exists('fastcgi_finish_request'))return;
    static $queued=[];$key=$id.'|'.$typeHint;if(isset($queued[$key]))return;$queued[$key]=true;
    register_shutdown_function(function()use($id,$typeHint){
        @fastcgi_finish_request();
        $row=cinemeta_title($id,$typeHint);if(is_array($row))remember_title_preview($row);
    });
}

function page_data($type,$page){$type=$type==='tv'?'tv':'movies';$d=catalog_json($type.'/page-'.max(1,(int)$page).'.json');if($d&&isset($d['items'])){$d['items']=catalog_filter_items($d['items']);remember_title_previews($d['items']);}return $d;}
function latest_data($type,$limit=28){$type=$type==='tv'?'tv':'movies';$d=catalog_json('latest/'.$type.'.json');$items=(array)($d['items']??[]);if(!$items){$fallback=page_data($type,1)?:[];$items=(array)($fallback['items']??[]);} $items=array_slice(catalog_filter_items($items),0,max(1,(int)$limit));remember_title_previews($items);return $items;}
function genre_data($genre,$page){if(cfg('hide_adult')&&$genre==='Adult')return null;$d=catalog_json('genres/'.slugify($genre).'/page-'.max(1,(int)$page).'.json');if($d&&isset($d['items'])){$d['items']=catalog_filter_items($d['items']);remember_title_previews($d['items']);}return $d;}
function year_data($year,$page){$d=catalog_json('years/'.(int)$year.'/page-'.max(1,(int)$page).'.json');if($d&&isset($d['items'])){$d['items']=catalog_filter_items($d['items']);remember_title_previews($d['items']);}return $d;}
function load_title($id,$typeHint=null){
    if(title_is_blocked($id))return null;
    if(!preg_match('/^tt\d{5,12}$/i',$id))return null;$id=strtolower($id);
    $item=fetch_catalog_member(data_url('title-shards/'.substr($id,0,6).'.json'),$id);
    if(is_array($item)&&is_public_item($item)){
        if(subtitle_filter_active()&&!has_verified_ro_subtitle($id))return null;
        $item=enrich_title_item($item);remember_title_preview($item);return $item;
    }
    // A transient GitHub Pages/shard failure must never turn a card already shown to the user into a false 404.
    $preview=load_title_preview($id);
    if(is_array($preview)&&is_public_item($preview)){
        if($typeHint&&title_type($preview)!==($typeHint==='tv'?'tv':'movie'))$preview=null;
        elseif(!(subtitle_filter_active()&&!has_verified_ro_subtitle($id))){
            // The visitor gets the title immediately. Rich fallback metadata is warmed after the response.
            $preview=enrich_title_item($preview);remember_title_preview($preview);return $preview;
        }
    }
    $fallback=cinemeta_title($id,$typeHint);
    if(is_array($fallback)&&!(subtitle_filter_active()&&!has_verified_ro_subtitle($id))){$fallback=enrich_title_item($fallback);remember_title_preview($fallback);return $fallback;}
    return null;
}

function search_titles($query,$limit=120){
    $limit=max(1,min(200,(int)$limit));$q=normalize_text(substr((string)$query,0,200));if($q==='')return[];$compact=str_replace(' ','',$q);
    if(preg_match('/^tt\d{5,12}$/',$compact)){$item=load_title($compact);return$item?[$item]:[];}
    if(strlen($compact)<2)return[];$bucket=substr($compact,0,2);$data=catalog_json('search/'.rawurlencode($bucket).'.json');
    $items=$data['items']??(is_array($data)?$data:[]);$scored=[];
    foreach(catalog_filter_items($items) as $item){
        $names=array_filter([$item['romanian_title']??'', $item['title']??'', $item['original_title']??'']);$best=0;
        foreach($names as $n){$h=normalize_text($n);if($h===$q)$best=max($best,100);elseif(strpos($h,$q)===0)$best=max($best,90);elseif(strpos($h,$q)!==false)$best=max($best,75);else{$dist=levenshtein(substr($h,0,min(strlen($h),strlen($q)+3)),$q);if($dist<=2)$best=max($best,55-$dist*8);}}
        if($best>0)$scored[]=['s'=>$best,'i'=>$item];
    }
    $seen=[];foreach($scored as $row)$seen[$row['i']['imdb_id']]=true;
    $f=catalog_filters();$f['q']=$query;$f['tip']='all';$f['gen']='';$f['de']=1880;$f['pana']=2100;$f['nota']=0;
    $local=filtered_catalog($f,1,$limit);
    foreach($local['items'] as $item){if(isset($seen[$item['imdb_id']]))continue;$best=75;foreach([$item['title']??'',$item['romanian_title']??'',$item['original_title']??''] as $n){$h=normalize_text($n);if($h===$q)$best=100;elseif(strpos($h,$q)===0)$best=max($best,90);}$scored[]=['s'=>$best,'i'=>$item];}
    usort($scored,function($a,$b){$d=$b['s']<=>$a['s'];if($d)return$d;return((int)($b['i']['votes']??0))<=>((int)($a['i']['votes']??0));});
    return array_slice(array_column($scored,'i'),0,$limit);
}


function catalog_people_id_set(){
    static $loaded=false,$set=null;
    if($loaded)return $set;
    $loaded=true;
    $local=dirname(__DIR__).'/data/people-ids-v11.txt';$raw=is_file($local)?file_get_contents($local):remote_text(data_url('people-ids.txt'),43200);
    if($raw===false||trim($raw)===''){return $set=null;}
    $set="\n".trim(str_replace("\r",'',$raw))."\n";
    return $set;
}
function person_search_item($nconst,$name,$extra=[]){
    $nconst=strtolower(trim((string)$nconst));$name=trim((string)$name);
    if(!preg_match('/^nm\d{5,12}$/',$nconst)||$name==='')return null;
    return array_merge([
        'nconst'=>$nconst,'name'=>$name,'url'=>person_path($nconst),'image'=>person_image_url($nconst),
        'type'=>'Persoană','birth_year'=>null,'hint'=>''
    ],is_array($extra)?$extra:[]);
}
function local_people_search($query,$limit=12){
 $q=normalize_text($query);$compact=str_replace(' ','',$q);if(strlen($compact)<2)return [];
 $file=dirname(__DIR__).'/data/people-search-v11/'.bin2hex(substr($compact,0,2)).'.ndjson.gz';
 $stream=@gzopen($file,'rb');if(!$stream)return [];$scored=[];
 while(!gzeof($stream)){$line=gzgets($stream);if($line===false)break;$r=json_decode($line,true);if(!is_array($r)||count($r)<2)continue;$name=normalize_text($r[1]);$score=0;
  if($name===$q)$score=100;elseif(strpos($name,$q)===0)$score=90;elseif(strpos($name,$q)!==false)$score=80;
  elseif(strlen($q)>=4&&levenshtein(substr($name,0,min(strlen($name),strlen($q)+2)),$q)<=2)$score=50;
  if(!$score)continue;$item=person_search_item($r[0],$r[1],['birth_year'=>$r[2]??null,'professions'=>$r[3]??[],'hint'=>implode(' · ',array_map('person_profession_label',(array)($r[3]??[])))]);if($item)$scored[]=['score'=>$score,'item'=>$item];
 }gzclose($stream);usort($scored,static function($a,$b){return ($b['score']<=>$a['score'])?:strcmp($a['item']['nconst'],$b['item']['nconst']);});return array_slice(array_column($scored,'item'),0,$limit);
}

function search_people($query,$limit=12){
    $rawQuery=trim((string)$query);$q=normalize_text($rawQuery);if($q==='')return[];
    $compact=str_replace(' ','',$q);$limit=max(1,min(25,(int)$limit));
    if(preg_match('/^nm\d{5,12}$/',$compact)){
        $person=load_person($compact);
        return $person?[person_search_item($person['nconst'],$person['name']??$person['nconst'],['birth_year'=>$person['birth_year']??null,'professions'=>$person['professions']??[]])]:[];
    }
    if(strlen($compact)<2)return[];
    if(is_file(dirname(__DIR__).'/data/people-search-v11/meta.json'))return local_people_search($rawQuery,$limit);

    // IMDb autocomplete resolves a human name to nconst. We only expose IDs that
    // are present in this site's own people index, so search never links to a 404.
    $slug=str_replace(' ','_',normalize_text($rawQuery));
    if($slug==='')return[];
    $url='https://v3.sg.media-imdb.com/suggestion/names/x/'.rawurlencode($slug).'.json?includeVideos=0';
    $doc=remote_json($url,21600);
    if(!is_array($doc)){
        $url='https://v3.sg.media-imdb.com/suggestion/x/'.rawurlencode($slug).'.json?includeVideos=0';
        $doc=remote_json($url,21600);
    }
    $rows=is_array($doc)?(array)($doc['d']??[]):[];if(!$rows)return[];
    $known=catalog_people_id_set();$scored=[];$fallbackChecks=0;
    foreach($rows as $row){
        if(!is_array($row))continue;
        $id=strtolower(trim((string)($row['id']??'')));$name=trim((string)($row['l']??''));
        if(!preg_match('/^nm\d{5,12}$/',$id)||$name==='')continue;
        if(is_string($known)){
            if(strpos($known,"\n".$id."\n")===false)continue;
        }else{
            // If the compact ID index is temporarily unavailable, validate only a
            // few candidates against person shards to keep autocomplete responsive.
            if($fallbackChecks>=3)break;$fallbackChecks++;
            if(!load_person($id))continue;
        }
        $h=normalize_text($name);$score=0;
        if($h===$q)$score=100;elseif(strpos($h,$q)===0)$score=90;elseif(strpos($h,$q)!==false)$score=75;else{$dist=levenshtein(substr($h,0,min(strlen($h),strlen($q)+3)),$q);if($dist<=2)$score=55-$dist*8;}
        if($score<=0)continue;
        $item=person_search_item($id,$name,['hint'=>trim((string)($row['s']??''))]);
        if($item)$scored[]=['s'=>$score,'rank'=>(int)($row['rank']??PHP_INT_MAX),'i'=>$item];
    }
    usort($scored,function($a,$b){$d=$b['s']<=>$a['s'];if($d)return$d;return$a['rank']<=>$b['rank'];});
    return array_slice(array_column($scored,'i'),0,$limit);
}

function pagination_window($page,$pages,$radius=3){$page=max(1,min((int)$page,(int)$pages));$pages=max(1,(int)$pages);$o=[];$s=max(1,$page-$radius);$e=min($pages,$page+$radius);if($s>1)$o[]=1;if($s>2)$o[]='…';for($i=$s;$i<=$e;$i++)$o[]=$i;if($e<$pages-1)$o[]='…';if($e<$pages)$o[]=$pages;return$o;}

function player_url($item,$season=null,$episode=null){$type=title_type($item);$id=$item['imdb_id']??'';$base=rtrim(cfg('provider_base_url'),'/').($type==='tv'?'/embed/tv/':'/embed/movie/').rawurlencode($id);if($type==='tv'&&$season!==null&&$episode!==null)$base.='/'.rawurlencode((string)$season).'/'.rawurlencode((string)$episode);$p=['ds_lang'=>cfg('subtitle_lang')];if($type==='tv')$p['autonext']='1';return$base.'?'.http_build_query($p);}

function human_factual_summary($item){return editorial_summary($item);}

function ranking_score($item){$r=(float)($item['rating']??0);$v=(int)($item['votes']??0);$y=(int)($item['year']??0);$rec=$y?max(0,12-min(12,max(0,(int)date('Y')-$y))):0;return$r*10+min(35,log10(max(1,$v))*7)+$rec;}
function top_items($items,$limit=12){$rows=[];foreach(catalog_filter_items($items) as $i)$rows[]=['s'=>ranking_score($i),'i'=>$i];usort($rows,function($a,$b){return$b['s']<=>$a['s'];});return array_slice(array_column($rows,'i'),0,$limit);}

function opensubtitles_has_ro($imdbId){
    $key=trim((string)cfg('opensubtitles_api_key'));if($key==='')return null;if(!preg_match('/^tt(\d{5,12})$/i',$imdbId,$m))return null;
    $url='https://api.opensubtitles.com/api/v1/subtitles?'.http_build_query(['imdb_id'=>ltrim($m[1],'0')?:'0','languages'=>'ro','page'=>1]);
    $raw=fetch_remote_now($url,8,['Api-Key: '.$key,'User-Agent: '.cfg('opensubtitles_user_agent')]);if($raw===false)return null;$d=json_decode($raw,true);if(!is_array($d))return null;return (int)($d['total_count']??0)>0;
}

require_once __DIR__.'/presentation.php';

require_once __DIR__.'/icons.php';
require_once __DIR__.'/catalog.php';

require_once __DIR__.'/collections.php';
