<?php
/** Extract one object without decoding the surrounding multi-megabyte catalogue. */
final class CatalogMemberReader {
    private $key;private $tail='';private $raw='';private $depth=0;private $quoted=false;private $escaped=false;
    public $done=false;public $failed=false;
    public function __construct($key){$this->key='"'.$key.'"';}
    public function feed($chunk){
        if($this->done||$this->failed)return false;
        if($this->depth===0){
            $scan=$this->tail.$chunk;
            if(!preg_match('/'.preg_quote($this->key,'/').'\\s*:\\s*\\{/',$scan,$m,PREG_OFFSET_CAPTURE)){$this->tail=substr($scan,-256);return true;}
            $offset=$m[0][1]+strlen($m[0][0])-1;$chunk=substr($scan,$offset);$this->tail='';
        }
        $length=strlen($chunk);
        for($i=0;$i<$length;$i++){
            $c=$chunk[$i];
            if($this->quoted){if($this->escaped)$this->escaped=false;elseif($c==='\\')$this->escaped=true;elseif($c==='"')$this->quoted=false;}
            elseif($c==='"')$this->quoted=true;elseif($c==='{')$this->depth++;elseif($c==='}')$this->depth--;
            if($this->depth===0){$this->raw.=substr($chunk,0,$i+1);$this->done=true;return false;}
        }
        $this->raw.=$chunk;
        if(strlen($this->raw)>8*1024*1024){$this->failed=true;$this->raw='';return false;}
        return true;
    }
    public function result(){if(!$this->done||$this->failed)return null;$value=json_decode($this->raw,true,128);return is_array($value)?$value:null;}
}
function catalog_fetch_failed($mark=null){static $failed=false;if($mark!==null)$failed=(bool)$mark;return $failed;}
function fetch_catalog_member($url,$id){
    $reader=new CatalogMemberReader($id);$received=0;
    $consume=static function($chunk)use($reader,&$received){$received+=strlen($chunk);if($received>64*1024*1024){$reader->failed=true;return false;}return $reader->feed($chunk);};
    if(function_exists('curl_init')){
        $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'FilmeBratuMarian/11',CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($handle,$chunk)use($consume){return $consume($chunk)?strlen($chunk):0;}]);
        curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_errno($ch);curl_close($ch);
        if($code!==200||$reader->failed||($error&&!$reader->done))catalog_fetch_failed(true);
        return $code===200?$reader->result():null;
    }
    $context=stream_context_create(['http'=>['timeout'=>15,'header'=>"Accept: application/json\r\n",'user_agent'=>'FilmeBratuMarian/11'],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $stream=@fopen($url,'rb',false,$context);if(!$stream){catalog_fetch_failed(true);return null;}
    while(!feof($stream)){$chunk=fread($stream,16384);if($chunk===false||$chunk===''||!$consume($chunk))break;}
    $status=stream_get_meta_data($stream);if(!empty($status['timed_out'])||$reader->failed)catalog_fetch_failed(true);fclose($stream);return $reader->result();
}
