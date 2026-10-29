<?php
// Exercise the real streamed reader and listing helpers with isolated releases.
$root=sys_get_temp_dir().'/filme-listings-'.bin2hex(random_bytes(5));
mkdir($root.'/includes',0700,true);
copy(__DIR__.'/catalog-sync.php',$root.'/includes/catalog-sync.php');
copy(__DIR__.'/includes/catalog.php',$root.'/includes/catalog.php');
copy(__DIR__.'/includes/collections.php',$root.'/includes/collections.php');
function query_string($key,$default=''){return $GLOBALS['query'][$key]??$default;}
function genre_map(){return ['Drama'=>['Dramă']];}
function normalize_text($value){return strtolower(trim($value));}
function is_public_item($item){return true;}
function title_is_blocked($id){return false;}
function subtitle_filter_active(){return false;}
function has_verified_ro_subtitle($id){return false;}
function remember_title_previews($items){}
function listing_path($type,$page=1){return '/'.$type.'/'.$page.'/';}
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
function make_row($id,$type,$genre=''){return [$id,$type,$id,'',2026,7,100,$genre,'','','2026-10-29T10:00:00Z',90,$id,'2026-10-29T10:00:00Z'];}
function publish_test_release($root,$release,$rows){
 $folder=$root.'/data/catalog-sync/releases/'.$release;mkdir($folder,0700,true);
 $g=gzopen($folder.'/catalog-v8-recent.ndjson.gz','wb');
 foreach($rows as $r)gzwrite($g,json_encode($r)."\n");gzclose($g);
 $pointer=$root.'/data/catalog-sync/current.json';file_put_contents($pointer.'.tmp',json_encode(['release'=>$release]));
 rename($pointer.'.tmp',$pointer);
}
try {
 require $root.'/includes/catalog.php';require $root.'/includes/collections.php';
 $rows=[make_row('tt100001','movie','Drama'),make_row('tt100002','movie'),make_row('tt100003','movie','Drama'),make_row('tt100004','tv'),make_row('tt100005','tv','Drama')];
 publish_test_release($root,str_repeat('a',24),$rows);
 $films=recent_listing('movies',[],1,2);$second=recent_listing('movies',[],2,2);
 verify($films['total']===3&&$films['pages']===2,'Full movie count and pagination');
 verify(array_column($films['items'],'imdb_id')===['tt100001','tt100002'],'A movie without genres must remain visible');
 verify(array_column($second['items'],'imdb_id')===['tt100003'],'Page two cannot overlap or omit the remaining movie');
 $tv=recent_listing('tv',[],1,60);
 verify($tv['total']===2&&$tv['items'][0]['imdb_id']==='tt100004','A series without genres must remain visible');
 verify(count(recent_collection('movies',60))===3,'Home and movie listings use the same release');
 $drama=recent_listing('movies',['gen'=>'Drama'],1,60);
 verify(array_column($drama['items'],'imdb_id')===['tt100001','tt100003'],'Explicit genre filtering remains accurate');
 array_unshift($rows,make_row('tt100006','movie'));
 publish_test_release($root,str_repeat('b',24),$rows);
 $next=recent_listing('movies',[],1,60);
 verify($next['total']===4&&$next['items'][0]['imdb_id']==='tt100006','A promoted release appears without rebuilding the archive or changing code');
 echo "Complete listings, genre filters, pagination and automatic release promotion verified\n";
} finally {
 if(is_dir($root)){
  $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($files as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}
  rmdir($root);
 }
}
