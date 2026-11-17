<?php
$testRoot=sys_get_temp_dir().'/filme-runtime-'.bin2hex(random_bytes(5));putenv('FBM_CACHE_DIR='.$testRoot);
require __DIR__.'/includes/functions.php';
function verify_runtime($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
 verify_runtime(runtime_cache_put('test','a',['text'=>'Poveste în română'],60),'Persistent cache write');
 $row=runtime_cache_get('test','a',60);verify_runtime(($row['value']['text']??'')==='Poveste în română','Persistent cache round trip');
 verify_runtime(runtime_cache_get('test','missing',60)===null,'Missing entries stay missing');
 $item=['imdb_id'=>'tt0293662','type'=>'movie','poster'=>'https://image.tmdb.org/t/p/w500/test.jpg'];
 verify_runtime(strpos(image_fallback_url($item),'exclude=')!==false,'Failed primary source is excluded from resolver');
 verify_runtime(episode_title(['title'=>'Episode #1.3','episode'=>3])==='Episodul 3','Generic episode title is Romanian');
 verify_runtime(episode_title(['title'=>'Titlu real','episode'=>3])==='Titlu real','Real episode names are preserved');
 verify_runtime(catalog_link(['nota'=>0.0,'voturi'=>0,'format'=>''])==='/catalog/','Default query values are omitted');
 verify_runtime(!valid_image_url('javascript:alert(1)')&&!valid_image_url('https://user:password@example.com/image.jpg'),'Image URL validation');
 verify_runtime(strpos(poster_attributes($item),'data-fallback=')!==false,'Poster fallback is present');
 $member=new CatalogMemberReader('tt0293662');$member->feed('{"tt029');$member->feed('3662":{"title":"A } brace","n":1},"other":{}}');verify_runtime(($member->result()['title']??'')==='A } brace','Chunked metadata parsing respects quoted braces');
 echo "Persistent cache, image fallback, Romanian episode names and source validation verified\n";
}finally{
 if(is_dir($testRoot)){$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($files as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($testRoot);}
}
