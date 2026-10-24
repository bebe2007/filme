<?php
/** CLI-only, hash-verified catalog sync. Never replaces a valid release with partial data. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
error_reporting(E_ALL);
function sync_fail($message){throw new RuntimeException($message);}
function sync_get($url,$dest=null,$max=33554432){
    $bytes=0;$body='';$file=$dest!==null?fopen($dest,'xb'):null;
    if($dest!==null&&!$file)sync_fail('Cannot create staging file');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>120,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'FilmeBratuCatalogSync/1',CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$bytes,&$body,$file,$max){$bytes+=strlen($chunk);if($bytes>$max)return 0;if($file)return fwrite($file,$chunk);$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);if($file)fclose($file);
    if($ok===false||$status!==200)sync_fail('Download failed: HTTP '.$status.' '.$error);
    return $dest!==null?$bytes:$body;
}
function sync_validate_rows($path,$total){
    $f=gzopen($path,'rb');if(!$f)sync_fail('Cannot open gzip');$count=0;$bytes=0;$seen=[];
    try {while(!gzeof($f)){$line=gzgets($f,1048577);if($line===false)break;$bytes+=strlen($line);if($bytes>536870912||strlen($line)>1048576)sync_fail('Index exceeds validation limit');if(trim($line)==='')continue;
        $row=json_decode($line,true);if(!is_array($row)||count($row)<14||!preg_match('/^tt\d{5,12}$/D',(string)($row[0]??''))||!in_array($row[1]??null,['movie','tv'],true)||isset($seen[$row[0]]))sync_fail('Invalid or duplicate index row');$seen[$row[0]]=true;$count++;
    }}finally{gzclose($f);}
    if($count!==$total)sync_fail('Index row count differs from manifest');return $count;
}
$root=dirname(__DIR__).'/data/catalog-sync';$lock=null;$stage=null;$statusCode=0;
try {
    if(!function_exists('curl_init')||!function_exists('gzopen'))sync_fail('PHP cURL and zlib are required');
    if(!is_dir($root)&&!mkdir($root,0700,true))sync_fail('Cannot create sync directory');
    $lock=fopen($root.'/sync.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo "Another synchronization is active\n";return;}
    $pointer=$root.'/current.json';$current=is_file($pointer)?json_decode(file_get_contents($pointer),true):[];
    if(in_array('--status',$argv,true)){echo json_encode($current,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";return;}
    $config=require dirname(__DIR__).'/config.php';$local=dirname(__DIR__).'/config.local.php';if(is_file($local))$config=array_replace($config,require $local);
    $base=rtrim((string)($config['data_base_url']??''),'/').'/sync/';
    if($base!=='https://bebe2007.github.io/filme/data/sync/')sync_fail('Unexpected catalog source');
    $manifest=json_decode(sync_get($base.'manifest.json',null,65536),true);
    if(!is_array($manifest)||($manifest['schema']??0)!==1||!preg_match('/^[a-f0-9]{24}$/D',(string)($manifest['release']??'')))sync_fail('Invalid manifest');
    $total=$manifest['total']??0;if(!is_int($total)||$total<1||$total>1000000||$total!==(int)$manifest['movies']+(int)$manifest['tv'])sync_fail('Invalid catalog counts');
    if(!empty($current['total'])&&$total<(int)$current['total'])sync_fail('Catalog shrink requires review; keeping prior release');
    if(($current['release']??'')===$manifest['release']){echo 'Already current: '.$manifest['release'].'; '.$total." titles\n";return;}
    $names=['catalog-v8-recent.ndjson.gz','catalog-v8-rating.ndjson.gz','catalog-v8-year.ndjson.gz'];
    if(array_diff($names,array_keys($manifest['files']??[]))||count($manifest['files']??[])!==3)sync_fail('Unexpected manifest files');
    $stage=$root.'/stage-'.bin2hex(random_bytes(6));if(!mkdir($stage,0700))sync_fail('Cannot create staging directory');
    foreach($names as $name){$spec=$manifest['files'][$name];if(!preg_match('/^[a-f0-9]{64}$/D',(string)($spec['sha256']??''))||!is_int($spec['bytes']??null)||$spec['bytes']<1||$spec['bytes']>33554432)sync_fail('Invalid file specification');
        $file=$stage.'/'.$name;$downloaded=sync_get($base.$name,$file,$spec['bytes']);if($downloaded!==$spec['bytes']||hash_file('sha256',$file)!==$spec['sha256'])sync_fail('File integrity check failed');sync_validate_rows($file,$total);chmod($file,0600);
    }
    file_put_contents($stage.'/manifest.json',json_encode($manifest,JSON_UNESCAPED_UNICODE),LOCK_EX);
    if(in_array('--dry-run',$argv,true)){echo 'Validated without publishing: '.$total." titles\n";return;}
    $releases=$root.'/releases';if(!is_dir($releases)&&!mkdir($releases,0700))sync_fail('Cannot create release directory');$target=$releases.'/'.$manifest['release'];
    if(is_dir($target)){foreach($names as $name)if(!is_file($target.'/'.$name)||hash_file('sha256',$target.'/'.$name)!==$manifest['files'][$name]['sha256'])sync_fail('Existing release differs');}
    elseif(!rename($stage,$target))sync_fail('Cannot promote validated release');else $stage=null;
    $manifest['previous_release']=$current['release']??null;$manifest['installed_at']=gmdate('c');$tmp=$pointer.'.'.bin2hex(random_bytes(4)).'.tmp';
    if(file_put_contents($tmp,json_encode($manifest,JSON_UNESCAPED_UNICODE),LOCK_EX)===false||!rename($tmp,$pointer))sync_fail('Cannot atomically switch active release');chmod($pointer,0600);
    echo 'Installed '.$manifest['release'].'; '.$total.' titles; '.(int)$manifest['episodes']." episodes in remote catalog\n";
    // Only prune this synchronizer's own generated releases; retain active and previous.
    foreach(glob($releases.'/*',GLOB_ONLYDIR)?:[] as $dir){$id=basename($dir);if(!preg_match('/^[a-f0-9]{24}$/D',$id)||in_array($id,[$manifest['release'],$manifest['previous_release']],true)||is_link($dir))continue;
        foreach(array_merge($names,['manifest.json']) as $name)if(is_file($dir.'/'.$name)&&!is_link($dir.'/'.$name))unlink($dir.'/'.$name);@rmdir($dir);
    }
}catch(Throwable $e){fwrite(STDERR,'Catalog sync failed; previous release retained: '.$e->getMessage()."\n");$statusCode=1;}
finally{
    if(is_string($stage)&&is_dir($stage)){foreach(scandir($stage) as $name)if($name!=='.'&&$name!=='..'&&is_file($stage.'/'.$name))unlink($stage.'/'.$name);rmdir($stage);}
    if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
}

exit($statusCode);
