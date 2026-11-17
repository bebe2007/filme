<?php
/** Original Romanian copy, grounded in catalogue membership and cited source plots. */
function editorial_entries(){
    static $entries=null;if($entries!==null)return $entries;
    return $entries=[
      'tt47791876'=>['heading'=>'Snoopy și lista lui Moș Crăciun','description'=>'Snoopy vrea să iasă de pe lista copiilor neascultători a lui Moș Crăciun. Așa că se pune pe treabă: e hotărât să fie cel mai cuminte beagle din oraș.','source'=>'https://filme.bratu-marian.com/film/tt47791876/'],
      'tt16154574'=>['heading'=>'Gătit fără rețetă','description'=>'Membrii The Try Guys se întrec la gătit, cu o singură regulă: fără rețetă. Fiecare încearcă să pregătească mâncarea în felul lui.','source'=>'https://filme.bratu-marian.com/serial/tt16154574/'],
      'tt0293662'=>['heading'=>'Curierul','description'=>'Frank Martin transportă colete pentru clienți pe care nu îi cunoaște. Când unul dintre colete începe să se miște, misiunea lui devine mult mai complicată decât o simplă livrare.','source'=>'https://v3-cinemeta.strem.io/meta/movie/tt0293662.json'],
      'tt0479884'=>['heading'=>'Răzbunare și adrenalină','description'=>'Asasinul Chev Chelios află că a fost injectat cu o otravă care îl poate ucide dacă pulsul îi scade. Pentru el, a rămâne în viață înseamnă să nu încetinească.','source'=>'https://v3-cinemeta.strem.io/meta/movie/tt0479884.json'],
      'tt0317740'=>['heading'=>'Jaf în stil italian','description'=>'Trădați și lăsați în urmă după o lovitură în Italia, Charlie Croker și echipa lui pregătesc un nou jaf. De data aceasta, ținta este aurul fostului lor aliat.','source'=>'https://v3-cinemeta.strem.io/meta/movie/tt0317740.json'],
    ];
}
function editorial_catalogue_items(){
    $wanted=array_fill_keys(['tt0293662','tt0479884','tt0317740'],true);$found=[];
    $file=catalog_release_path('rating');$stream=@gzopen($file,'rb');if(!$stream)return [];
    while(!gzeof($stream)){$line=gzgets($stream);if($line===false)break;$r=json_decode($line,true);if(!is_array($r)||!isset($wanted[$r[0]??'']))continue;
        $found[$r[0]]=['imdb_id'=>$r[0],'type'=>$r[1],'title'=>$r[2],'romanian_title'=>$r[3]?:null,'year'=>$r[4],'rating'=>$r[5],'genres'=>explode('|',$r[7]),'poster'=>$r[8]?:null];
        if(count($found)===count($wanted))break;
    }gzclose($stream);return catalog_filter_items(array_values($found));
}
