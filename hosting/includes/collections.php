<?php
/** Public listings always read the same validated release as the full catalog. */
function collection_filters($scope){$f=catalog_filters();$f['tip']=$scope;$f['q']='';$f['de']=1880;$f['pana']=2100;$f['nota']=0;$f['sort']='recent';return $f;}
function collection_url($scope,$filters=[],$page=1){$url=listing_path($scope==='tv'?'tv':'movie',$page);return $url.(!empty($filters['gen'])?'?'.http_build_query(['gen'=>$filters['gen']]):'');}
function recent_stamp($item){$v=$item['site_added_at']??$item['added_at']??'';return is_string($v)&&preg_match('/^20\d\d-\d\d-\d\dT/',$v)?(strtotime($v)?:0):0;}
function recent_compare($a,$b){return (recent_stamp($b)<=>recent_stamp($a))?:strcmp($a['imdb_id'],$b['imdb_id']);}
function recent_collection($scope,$limit=60){
 static $memo=[];$scope=$scope==='tv'?'tv':'movies';$limit=max(1,min(600,(int)$limit));$key=$scope.':'.$limit;
 if(isset($memo[$key]))return $memo[$key];
 $f=collection_filters($scope);$f['gen']='';$f['adaugate']='';$rows=[];
 $per=min(200,$limit);$pages=(int)ceil($limit/$per);
 for($page=1;$page<=$pages;$page++){
  $doc=filtered_catalog($f,$page,$per);
  if(!empty($doc['unavailable']))return $memo[$key]=[];
  foreach((array)($doc['items']??[]) as $item){$item['added_at']=$item['site_added_at']??null;$rows[]=$item;}
  if($page>=(int)($doc['pages']??1))break;
 }
 return $memo[$key]=array_slice($rows,0,$limit);
}
function recent_listing($scope,$filters,$page=1,$per=60){
 $f=collection_filters($scope==='tv'?'tv':'movies');$f['gen']=$filters['gen']??'';
 $doc=filtered_catalog($f,$page,$per);
 foreach($doc['items'] as &$item)$item['added_at']=$item['site_added_at']??null;unset($item);
 $doc['archive_start']=null;$doc['dated_total']=$doc['total'];
 return $doc;
}
