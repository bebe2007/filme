<?php
require_once __DIR__.'/catalog-sync.php';
function catalog_filters(){
 $type=query_string('tip','all');$sort=query_string('sort','recent');$genre=query_string('gen','');$kind=query_string('format','');
 if(!in_array($type,['all','movies','tv'],true))$type='all';if(!in_array($sort,['recent','rating','year'],true))$sort='recent';if(!isset(genre_map()[$genre]))$genre='';
 if(!in_array($kind,['','feature','short','documentary','special','series'],true))$kind='';
 $from=max(1880,min(2100,(int)(query_string('de')?:1880)));$to=max($from,min(2100,(int)(query_string('pana')?:2100)));
 return ['adaugate'=>query_string('adaugate')==='azi'?'azi':'','tip'=>$type,'gen'=>$genre,'de'=>$from,'pana'=>$to,'nota'=>max(0.0,min(10.0,(float)query_string('nota','0'))),'voturi'=>max(0,min(10000000,(int)query_string('voturi','0'))),'format'=>$kind,'sort'=>$sort,'q'=>substr(trim(query_string('q')),0,200)];
}
function catalog_link($filters=[],$page=1){
 $params=array_filter($filters,static function($v,$k){return !($v===''||($k==='tip'&&$v==='all')||($k==='de'&&(int)$v===1880)||($k==='pana'&&(int)$v===2100)||($k==='nota'&&(float)$v===0.0)||($k==='voturi'&&(int)$v===0)||($k==='sort'&&$v==='recent'));},ARRAY_FILTER_USE_BOTH);
 if($page>1)$params['page']=$page;return '/catalog/'.($params?'?'.http_build_query($params):'');
}
function catalog_row_to_item($r){
 return ['imdb_id'=>$r[0],'type'=>$r[1],'title'=>$r[2],'romanian_title'=>$r[3]?:null,'year'=>$r[4]?:null,'rating'=>$r[5]?:null,'votes'=>$r[6],'genres'=>array_values(array_filter(explode('|',$r[7]))),'poster'=>$r[8]?:null,'background'=>$r[9]?:null,'runtime'=>$r[11]??0,'original_title'=>$r[12]??null,'site_added_at'=>$r[13]??null,'title_kind'=>$r[14]??''];
}
function local_catalog_title($id){
 $file=catalog_release_path('recent');$f=is_file($file)?@gzopen($file,'rb'):false;if(!$f)return null;
 try{while(!gzeof($f)){$line=gzgets($f);if($line===false)break;$r=json_decode($line,true);if(is_array($r)&&count($r)>=14&&($r[0]??'')===$id)return catalog_row_to_item($r);}}finally{gzclose($f);}return null;
}
function filtered_catalog($f,$page=1,$per=36){
 $file=catalog_release_path($f['sort']);$empty=['unavailable'=>true,'items'=>[],'total'=>0,'pages'=>1,'page'=>1];if(!is_file($file)||!function_exists('gzopen'))return $empty;
 $per=max(1,min(200,(int)$per));$page=max(1,min(100000,(int)$page));$offset=($page-1)*$per;$needle=normalize_text($f['q']);
 $cacheId=sha1($file.':'.(string)filemtime($file).':'.json_encode([$f,$page,$per,(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d')])) ;
 $cached=function_exists('runtime_cache_get')?runtime_cache_get('queries',$cacheId,300):null;if($cached&&is_array($cached['value'])){remember_title_previews($cached['value']['items']);return $cached['value'];}
 $stream=@gzopen($file,'rb');if(!$stream)return $empty;$total=0;$items=[];
 while(!gzeof($stream)){
  $line=gzgets($stream);if($line===false)break;$r=json_decode($line,true);if(!is_array($r)||count($r)<14)continue;$genres=explode('|',$r[7]);
  if(($f['adaugate']??'')==='azi'&&!catalog_added_today($r[13]??null))continue;
  if($f['tip']!=='all'&&($r[1]==='tv'?'tv':'movies')!==$f['tip'])continue;
  if($f['gen']!==''&&!in_array($f['gen'],$genres,true))continue;
  if(($f['de']>1880||$f['pana']<2100)&&((int)$r[4]<$f['de']||(int)$r[4]>$f['pana']))continue;
  if((float)$r[5]<$f['nota']||(int)$r[6]<(int)($f['voturi']??0))continue;
  $kind=$f['format']??'';$sourceKind=$r[14]??'';
  if($kind==='documentary'&&!in_array('Documentary',$genres,true))continue;
  if($kind!==''&&$kind!=='documentary'&&$sourceKind!==$kind)continue;
  if($needle!==''&&strpos(normalize_text($r[2].' '.$r[3].' '.($r[12]??'')),$needle)===false)continue;
  if(!is_public_item(['imdb_id'=>$r[0],'genres'=>$genres])||title_is_blocked($r[0])||(subtitle_filter_active()&&!has_verified_ro_subtitle($r[0])))continue;
  if($total>=$offset&&count($items)<$per)$items[]=catalog_row_to_item($r);$total++;
 }
 gzclose($stream);$doc=['items'=>$items,'total'=>$total,'pages'=>max(1,(int)ceil($total/$per)),'page'=>$page];
 if(function_exists('runtime_cache_put'))runtime_cache_put('queries',$cacheId,$doc,300);remember_title_previews($items);return $doc;
}

/** Today's additions first; complete the five from the preceding seven days. */
function select_discover_movies(array $items,DateTimeImmutable $now,int $limit=5):array{
 $zone=new DateTimeZone('Europe/Bucharest');$today=$now->setTimezone($zone)->setTime(0,0);$since=$today->modify('-7 days');$rated=[];
 foreach($items as $item){if(($item['type']??'movie')!=='movie'||(!empty($item['title_kind'])&&$item['title_kind']!=='feature')||(float)($item['rating']??0)<=0||empty($item['site_added_at']))continue;
  try{$added=(new DateTimeImmutable($item['site_added_at']))->setTimezone($zone);}catch(Exception $e){continue;}
  if($added>$now->setTimezone($zone)||$added<$since)continue;$item['_added']=$added->getTimestamp();$rated[$item['imdb_id']]=$item;
 }
 $sort=static function($a,$b){return ((float)$b['rating']<=>(float)$a['rating'])?:((int)($b['votes']??0)<=>(int)($a['votes']??0))?:(($b['_added']??0)<=>($a['_added']??0))?:strcmp($a['imdb_id'],$b['imdb_id']);};
 $current=array_values(array_filter($rated,static fn($x)=>$x['_added']>=$today->getTimestamp()));usort($current,$sort);$chosen=array_slice($current,0,$limit);$seen=array_column($chosen,'imdb_id');
 if(count($chosen)<$limit){$older=array_values(array_filter($rated,static fn($x)=>!in_array($x['imdb_id'],$seen,true)));usort($older,$sort);$chosen=array_merge($chosen,array_slice($older,0,$limit-count($chosen)));}
 usort($chosen,$sort);foreach($chosen as &$item)unset($item['_added']);unset($item);return $chosen;
}
function home_discover_items(int $limit=5):array{
 $file=catalog_release_path('recent');if(!is_file($file))return [];$now=new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest'));
 $key=$file.':'.filemtime($file).':'.$now->format('Y-m-d').':'.$limit.':features';$cache=runtime_cache_get('discover-v16',$key,120);if($cache&&is_array($cache['value']))return $cache['value'];
 $stream=@gzopen($file,'rb');if(!$stream)return [];$items=[];$cutoff=$now->setTime(0,0)->modify('-7 days')->getTimestamp();
 try{while(!gzeof($stream)){$line=gzgets($stream);if($line===false)break;$row=json_decode($line,true);if(!is_array($row)||count($row)<14||($row[1]??'')!=='movie'||(float)($row[5]??0)<=0||empty($row[13]))continue;
  $stamp=strtotime($row[13]);if($stamp===false||$stamp<$cutoff)continue;$item=catalog_row_to_item($row);if(!is_public_item($item)||title_is_blocked($item['imdb_id']))continue;$items[]=$item;}}
 finally{gzclose($stream);}
 $items=select_discover_movies($items,$now,$limit);runtime_cache_put('discover-v16',$key,$items,120);remember_title_previews($items);return $items;
}
