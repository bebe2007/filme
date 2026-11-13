<?php
/** Complete verified daily collection. Never substitutes release dates or older titles. */
function daily_scope_items($document,$scope='all') {
    return array_values(array_filter($document['items']??[],static function($item)use($scope){
        return $scope==='all'||($scope==='tv'?title_type($item)==='tv':title_type($item)==='movie');
    }));
}
function complete_daily_document($fetch=null,$date=null) {
    $date=$date??(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
    $fetch=$fetch??static function($path){return catalog_json($path,60);};
    $unavailable=['date'=>$date,'verified'=>false,'unavailable'=>true,'items'=>[],'sections'=>[],'hero'=>[],'total'=>0];
    $doc=$fetch('browse/today.json');
    if(!is_array($doc)||empty($doc['verified'])||($doc['date']??'')!==$date||!isset($doc['total'])||!is_array($doc['sections']??null))return $unavailable;
    $byId=[];
    $collect=static function($rows)use(&$byId,$date){
        foreach($rows as $item){
            if(!is_array($item)||!preg_match('/^tt\d{5,12}$/',(string)($item['imdb_id']??'')))return false;
            if(!empty($item['added_at'])){
                try{$added=(new DateTimeImmutable($item['added_at'],new DateTimeZone('Europe/Bucharest')))->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('Y-m-d');}
                catch(Throwable $e){return false;}
                if($added!==$date)return false;
            }
            $byId[$item['imdb_id']]=$item;
        }
        return true;
    };
    if(!$collect((array)($doc['items']??[]))||!$collect((array)($doc['hero']??[])))return $unavailable;
    foreach($doc['sections'] as $section){
        if(!is_array($section)||!is_array($section['items']??null))return $unavailable;
        $genre=$section['genre']??'';$expected=(int)($section['total']??count($section['items']));
        $sectionItems=$section['items'];$pages=max(1,(int)($section['pages']??1));
        if($pages>1&&!isset(genre_map()[$genre]))return $unavailable;
        for($page=2;$page<=$pages;$page++){
            $part=$fetch('categories/today/'.strtolower($genre).'/page-'.$page.'.json');
            if(!is_array($part)||empty($part['verified'])||($part['date']??'')!==$date||!is_array($part['items']??null))return $unavailable;
            $sectionItems=array_merge($sectionItems,$part['items']);
        }
        $sectionIds=[];foreach($sectionItems as $item)$sectionIds[$item['imdb_id']??'']=true;
        if(count($sectionIds)!==$expected||!$collect($sectionItems))return $unavailable;
    }
    $doc['source_total']=(int)$doc['total'];
    $doc['incomplete']=count($byId)!==$doc['source_total'];
    if(count($byId)>$doc['source_total'])return $unavailable;
    $items=catalog_filter_items(array_values($byId));
    usort($items,static function($a,$b){return strcmp((string)($b['added_at']??''),(string)($a['added_at']??''))?:strcmp(title_name($a),title_name($b));});
    $doc['items']=$items;$doc['unavailable']=false;$doc['total']=count($items);
    return $doc;
}
