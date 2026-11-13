<?php
// Presentation helpers. Cached documents contain JSON/text only, never photographs.
function query_string($key,$default='') { return isset($_GET[$key])&&is_scalar($_GET[$key])?(string)$_GET[$key]:$default; }
function browse_scope($value=null) { $v=$value??query_string('tip','all');return in_array($v,['all','movies','tv'],true)?$v:'all'; }
function natural_names($people) {
    $names=[];foreach((array)$people as $p){$n=trim((string)(is_array($p)?($p['name']??''):$p));if($n!==''&&!in_array($n,$names,true))$names[]=$n;}return $names;
}
function natural_list($names) { $names=array_values($names);$last=array_pop($names);return $names?implode(', ',$names).' și '.$last:(string)$last; }
function language_label($value) {
    $map=['en'=>'Engleză','eng'=>'Engleză','english'=>'Engleză','fr'=>'Franceză','fra'=>'Franceză','fre'=>'Franceză','french'=>'Franceză','es'=>'Spaniolă','spa'=>'Spaniolă','spanish'=>'Spaniolă','it'=>'Italiană','ita'=>'Italiană','italian'=>'Italiană','ro'=>'Română','ron'=>'Română','rum'=>'Română','romanian'=>'Română','de'=>'Germană','deu'=>'Germană','ger'=>'Germană','german'=>'Germană','pt'=>'Portugheză','por'=>'Portugheză','portuguese'=>'Portugheză','ja'=>'Japoneză','jpn'=>'Japoneză','japanese'=>'Japoneză','ko'=>'Coreeană','kor'=>'Coreeană','korean'=>'Coreeană','zh'=>'Chineză','zho'=>'Chineză','chi'=>'Chineză','chinese'=>'Chineză','cn'=>'Cantoneză','yue'=>'Cantoneză','ru'=>'Rusă','rus'=>'Rusă','russian'=>'Rusă','uk'=>'Ucraineană','ukr'=>'Ucraineană','ar'=>'Arabă','ara'=>'Arabă','arabic'=>'Arabă','hi'=>'Hindi','hin'=>'Hindi','tr'=>'Turcă','tur'=>'Turcă','turkish'=>'Turcă','sv'=>'Suedeză','swe'=>'Suedeză','swedish'=>'Suedeză','no'=>'Norvegiană','nor'=>'Norvegiană','nb'=>'Norvegiană','nn'=>'Norvegiană','da'=>'Daneză','dan'=>'Daneză','fi'=>'Finlandeză','fin'=>'Finlandeză','pl'=>'Poloneză','pol'=>'Poloneză','nl'=>'Neerlandeză','nld'=>'Neerlandeză','dut'=>'Neerlandeză','el'=>'Greacă','ell'=>'Greacă','gre'=>'Greacă','hu'=>'Maghiară','hun'=>'Maghiară','cs'=>'Cehă','ces'=>'Cehă','cze'=>'Cehă','sk'=>'Slovacă','slk'=>'Slovacă','sl'=>'Slovenă','slv'=>'Slovenă','bg'=>'Bulgară','bul'=>'Bulgară','sr'=>'Sârbă','srp'=>'Sârbă','hr'=>'Croată','hrv'=>'Croată','he'=>'Ebraică','heb'=>'Ebraică','fa'=>'Persană','fas'=>'Persană','per'=>'Persană','id'=>'Indoneziană','ind'=>'Indoneziană','th'=>'Thailandeză','tha'=>'Thailandeză','vi'=>'Vietnameză','vie'=>'Vietnameză','ta'=>'Tamilă','tam'=>'Tamilă','te'=>'Telugu','tel'=>'Telugu','ml'=>'Malayalam','mal'=>'Malayalam','bn'=>'Bengaleză','ben'=>'Bengaleză','ur'=>'Urdu','urd'=>'Urdu','ms'=>'Malaieză','msa'=>'Malaieză','is'=>'Islandeză','isl'=>'Islandeză','et'=>'Estonă','est'=>'Estonă','lv'=>'Letonă','lav'=>'Letonă','lt'=>'Lituaniană','lit'=>'Lituaniană','ka'=>'Georgiană','kat'=>'Georgiană','hy'=>'Armeană','hye'=>'Armeană','sq'=>'Albaneză','sqi'=>'Albaneză','mk'=>'Macedoneană','mkd'=>'Macedoneană','bs'=>'Bosniacă','bos'=>'Bosniacă','af'=>'Afrikaans','sw'=>'Swahili','zu'=>'Zulu','eu'=>'Bască','ca'=>'Catalană','gl'=>'Galiciană','cy'=>'Galeză','ga'=>'Irlandeză','la'=>'Latină','tl'=>'Tagalog','fil'=>'Filipineză'];
    $out=[];foreach(preg_split('/[,;|\/]+/',is_array($value)?implode(',',$value):(string)$value) as $v){$v=strtolower(trim($v));$key=preg_replace('/[-_].*$/','',$v);$label=$map[$v]??$map[$key]??'';if($label===''&&in_array($v,array_map('strtolower',array_values($map)),true))$label=ucfirst($v);if($label!==''&&!in_array($label,$out,true))$out[]=$label;}return implode(', ',$out);
}
function duration_label($minutes) { $n=(int)$minutes;return $n>0?$n.($n===1?' minut':($n%100>=20||$n%100===0?' de minute':' minute')):''; }
require_once __DIR__.'/editorial.php';
function editorial_summary($item) {
    $manual=trim((string)($item['editorial']['description']??''));if($manual!=='')return $manual;
    $entries=editorial_entries();$id=$item['imdb_id']??'';
    if(isset($entries[$id]))return $entries[$id]['description'];
    return trim((string)($item['description']??$item['plot']??''));
}
function browse_document($scope) {
    $scope=browse_scope($scope);$d=catalog_json('browse/'.$scope.'.json');
    if(is_array($d)&&isset($d['sections']))return $d;
    // Compatibility: do not issue 28 external genre requests during an unavailable build.
    $sections=[];foreach(array_keys(genre_map()) as $g)$sections[]=['genre'=>$g,'items'=>[],'page'=>1,'pages'=>1,'total'=>0,'chronological'=>false];
    return ['sections'=>$sections,'hero'=>array_slice($scope==='tv'?latest_data('tv',6):latest_data('movies',6),0,6),'unavailable'=>true];
}
require_once __DIR__.'/daily.php';
function today_document() { return complete_daily_document(); }
function category_document($genre,$scope,$page) {
    if(!isset(genre_map()[$genre]))return null;$scope=$scope==='today'?'today':browse_scope($scope);$page=max(1,(int)$page);
    $d=catalog_json('categories/'.$scope.'/'.strtolower($genre).'/page-'.$page.'.json');
    if(!is_array($d))return null;
    if($scope==='today'&&(empty($d['verified'])||($d['date']??'')!==(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d')))return null;
    $d['items']=catalog_filter_items($d['items']??[]);remember_title_previews($d['items']);return $d;
}
function compact_duration($minutes){$n=max(0,(int)$minutes);return $n>=60?(int)floor($n/60).' h'.($n%60?' '.($n%60).' min':''):$n.' min';}
function related_titles($item,$limit=8) {
 $type=title_type($item);$genres=public_genres($item['genres']??[]);$rows=[];$candidates=[];
 foreach(array_slice($genres,0,2) as $g){$d=category_document($g,$type==='tv'?'tv':'movies',1);foreach((array)($d['items']??[]) as $c)$candidates[$c['imdb_id']??'']=$c;}
 $d=catalog_json('ranked/'.($type==='tv'?'tv':'movies').'.json');foreach((array)($d['items']??[]) as $c)$candidates[$c['imdb_id']??'']=$c;
 $people=natural_names($item['cast']??[]);
 foreach(catalog_filter_items(array_values($candidates)) as $c){if(($c['imdb_id']??'')===($item['imdb_id']??'')||title_type($c)!==$type)continue;
 $shared=count(array_intersect($genres,public_genres($c['genres']??[])));if(!$shared)continue;
 $score=$shared*20+count(array_intersect($people,natural_names($c['cast']??[])))*12+min(10,(float)($c['rating']??0));
 $rows[]=['score'=>$score,'item'=>$c];}
 usort($rows,static function($a,$b){return($b['score']<=>$a['score'])?:((int)($b['item']['votes']??0)<=>(int)($a['item']['votes']??0));});
 return array_column(array_slice($rows,0,max(1,min(40,(int)$limit))),'item');
}
function today_label() { $d=new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest'));$months=[1=>'Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'];return $d->format('j').' '.$months[(int)$d->format('n')].' '.$d->format('Y'); }
function daily_additions_text() { $d=catalog_json('daily.json',300);$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');$prefix='Astăzi '.today_label();if(!is_array($d)||empty($d['verified'])||($d['date']??'')!==$today)return $prefix.' · Numărul titlurilor adăugate nu este disponibil momentan.';return $prefix.' au fost adăugate '.str_pad((string)max(0,(int)($d['movies']??0)),3,'0',STR_PAD_LEFT).' de filme și '.str_pad((string)max(0,(int)($d['tv']??0)),3,'0',STR_PAD_LEFT).' de seriale.'; }
function media_url($item,$kind='poster',$season=null,$episode=null) { $params=['id'=>$item['imdb_id']??'','type'=>title_type($item),'kind'=>$kind];if($season!==null)$params['season']=(int)$season;if($episode!==null)$params['episode']=(int)$episode;return '/media.php?'.http_build_query($params); }
function valid_image_url($url) { if(!is_string($url)||preg_match('/[\x00-\x20]/',$url))return false;$p=parse_url($url);return is_array($p)&&($p['scheme']??'')==='https'&&!empty($p['host'])&&empty($p['user'])&&empty($p['pass'])&&(!isset($p['port'])||$p['port']===443); }
function tmdb_document($path) {
    if(!tmdb_enabled()||!preg_match('~^(?:movie|tv)/\d+(?:/season/\d+)?(?:/episode/\d+)?$~',$path))return null;
    $key='filme_tmdb_'.sha1($path);if(function_exists('apcu_fetch')){$ok=false;$hit=apcu_fetch($key,$ok);if($ok&&is_array($hit))return $hit;}
    $params=['language'=>'ro-RO'];$key=trim((string)cfg('tmdb_api_key'));if($key!=='')$params['api_key']=$key;
    $headers=['Accept: application/json'];$token=trim((string)cfg('tmdb_api_token'));if($token!=='')$headers[]='Authorization: Bearer '.$token;
    $raw=fetch_remote_now('https://api.themoviedb.org/3/'.$path.'?'.http_build_query($params),6,$headers);$d=$raw!==false?json_decode($raw,true):null;
    if(is_array($d)&&!isset($d['success'])){if(function_exists('apcu_store'))@apcu_store($key,$d,86400);return $d;}return null;
}
function detail_icon($kind) {
    $paths=['genre'=>'<rect x="3" y="4" width="18" height="16" rx="3"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','director'=>'<rect x="3" y="7" width="13" height="12" rx="2"/><path d="m16 11 5-3v10l-5-3M5 3l3 4M12 3l3 4"/>','writer'=>'<path d="m14 4 6 6M4 20l4-1L21 6l-4-4L4 15z"/>','language'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a18 18 0 0 1 0 18 18 18 0 0 1 0-18"/>','country'=>'<path d="M5 21V3c5-4 9 4 14 0v11c-5 4-9-4-14 0"/>'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$kind]??$paths['genre']).'</svg>';
}
function render_detail($label,$value,$icon,$html=false) { if($value==='')return;echo '<div class="detail-card"><span class="detail-icon">'.detail_icon($icon).'</span><div><span class="detail-label">'.e($label).'</span><strong>'.($html?$value:e($value)).'</strong></div></div>'; }
