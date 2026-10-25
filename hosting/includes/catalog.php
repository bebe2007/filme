<?php
require_once __DIR__.'/catalog-sync.php';
// A streamed, compact index avoids loading the entire catalog into PHP memory.
function catalog_filters() {
 $type=query_string('tip','all');$sort=query_string('sort','recent');$genre=query_string('gen','');
 if(!in_array($type,['all','movies','tv'],true))$type='all';
 if(!in_array($sort,['recent','rating','year'],true))$sort='recent';
 if(!isset(genre_map()[$genre]))$genre='';
 $from=max(1880,min(2100,(int)(query_string('de')?:'1880')));$to=max($from,min(2100,(int)(query_string('pana')?:'2100')));
 return ['adaugate'=>query_string('adaugate','')==='azi'?'azi':'','tip'=>$type,'gen'=>$genre,'de'=>$from,'pana'=>$to,'nota'=>max(0,min(10,(float)query_string('nota','0'))),'sort'=>$sort,'q'=>substr(trim(query_string('q')),0,200)];
}
function catalog_link($filters=[],$page=1) {
 $params=array_filter($filters,static function($v,$k){return !($v===''||($k==='tip'&&$v==='all')||($k==='de'&&(int)$v===1880)||($k==='pana'&&(int)$v===2100)||($k==='nota'&&(float)$v===0)||($k==='sort'&&$v==='recent'));},ARRAY_FILTER_USE_BOTH);
 if($page>1)$params['page']=$page;
 return '/catalog/'.($params?'?'.http_build_query($params):'');
}
function filtered_catalog($f,$page=1,$per=36) {
 $file=catalog_release_path($f['sort']);
 if(!is_file($file)||!function_exists('gzopen'))return ['unavailable'=>true,'items'=>[],'total'=>0,'pages'=>1];
 $handle=@gzopen($file,'rb');if(!$handle)return ['unavailable'=>true,'items'=>[],'total'=>0,'pages'=>1];
 $per=max(1,min(200,(int)$per));$page=max(1,min(100000,(int)$page));$offset=($page-1)*$per;$total=0;$items=[];$needle=normalize_text($f['q']);
 while(!gzeof($handle)){
  $line=gzgets($handle);if($line===false)break;$r=json_decode($line,true);if(!is_array($r)||count($r)<11)continue;
  if(($f['adaugate']??'')==='azi'&&!catalog_added_today($r[13]??null))continue;
  if($f['tip']!=='all'&&($r[1]==='tv'?'tv':'movies')!==$f['tip'])continue;
  if($f['gen']!==''&&!in_array($f['gen'],explode('|',$r[7]),true))continue;
  if(($f['de']>1880||$f['pana']<2100)&&((int)$r[4]<$f['de']||(int)$r[4]>$f['pana']))continue;
  if((float)$r[5]<$f['nota'])continue;
  if($needle!==''&&strpos(normalize_text($r[2].' '.$r[3].' '.($r[12]??'')),$needle)===false)continue;
  if(!is_public_item(['imdb_id'=>$r[0],'genres'=>explode('|',$r[7])])||title_is_blocked($r[0])||(subtitle_filter_active()&&!has_verified_ro_subtitle($r[0])))continue;
  if($total>=$offset&&count($items)<$per)$items[]=['imdb_id'=>$r[0],'type'=>$r[1],'title'=>$r[2],'romanian_title'=>$r[3]?:null,'year'=>$r[4]?:null,'rating'=>$r[5]?:null,'votes'=>$r[6],'genres'=>explode('|',$r[7]),'poster'=>$r[8]?:null,'background'=>$r[9]?:null,'runtime'=>$r[11]??0,'original_title'=>$r[12]??null,'site_added_at'=>$r[13]??null];
  $total++;
 }
 gzclose($handle);remember_title_previews($items);return ['items'=>$items,'total'=>$total,'pages'=>max(1,(int)ceil($total/$per)),'page'=>$page];
}

