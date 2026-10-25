<?php
/** Verified local release pointer and factual site-addition labels. */
function catalog_release_path($kind) {
    $root=dirname(__DIR__).'/data';
    $pointer=$root.'/catalog-sync/current.json';
    $state=is_file($pointer)?json_decode((string)@file_get_contents($pointer),true):null;
    $release=is_array($state)?(string)($state['release']??''):'';
    if(preg_match('/^[a-f0-9]{24}$/D',$release)) {
        $path=$root.'/catalog-sync/releases/'.$release.'/catalog-v8-'.$kind.'.ndjson.gz';
        if(is_file($path)&&!is_link($path))return $path;
    }
    return $root.'/catalog-v8-'.$kind.'.ndjson.gz';
}
function catalog_added_label(array $item) {
    $raw=$item['site_added_at']??null;
    if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}T/',$raw))return '';
    try {$date=new DateTimeImmutable($raw);$date=$date->setTimezone(new DateTimeZone('Europe/Bucharest'));}
    catch(Exception $e){return '';}
    $months=[1=>'Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'];
    return (($item['type']??'movie')==='tv'?'Serial adăugat pe ':'Film adăugat pe ').(int)$date->format('j').' '.$months[(int)$date->format('n')].' '.$date->format('Y');
}
function catalog_added_badge(array $item) {
    $label=catalog_added_label($item);if($label==='')return '';
    return '<span class="catalog-added-date"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg><time datetime="'.htmlspecialchars($item['site_added_at'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</time></span>';
}

function catalog_daily_footer() {
    $root=dirname(__DIR__).'/data/catalog-sync/current.json';
    $state=is_file($root)?json_decode((string)@file_get_contents($root),true):null;
    $daily=is_array($state)?($state['daily']??null):null;
    $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
    if(!is_array($daily)||($daily['date']??'')!==$today)$daily=catalog_json('daily.json',60);
    if(!is_array($daily)||($daily['verified']??false)!==true||($daily['date']??'')!==$today||!isset($daily['movies'],$daily['tv'])||!is_int($daily['movies'])||!is_int($daily['tv'])||$daily['movies']<0||$daily['tv']<0) {
        return '<p>Adăugările de astăzi sunt în curs de verificare.</p><p>Numărul filmelor și serialelor va fi afișat după importul verificat.</p>';
    }
    $movies=$daily['movies'];$tv=$daily['tv'];
    $movieWords=$movies===1?'film':(($movies>=20&&($movies%100===0||$movies%100>=20))?'de filme':'filme');
    $tvWords=$tv===1?'serial':(($tv>=20&&($tv%100===0||$tv%100>=20))?'de seriale':'seriale');
    $first=$movies===1?'Astăzi a fost adăugat pe site ':'Astăzi au fost adăugate pe site ';
    $movieIcon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4m-4 8h4M17 8h4m-4 8h4"/></svg>';
    $tvIcon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="2"/><path d="m8 3 4 4 4-4"/></svg>';
    return '<p><a class="catalog-daily-link" href="/catalog/?tip=movies&amp;adaugate=azi">'.$movieIcon.'<span>'.$first.'<span class="catalog-daily-count">'.ro_number($movies).'</span> '.$movieWords.'</span></a></p><p><a class="catalog-daily-link" href="/catalog/?tip=tv&amp;adaugate=azi">'.$tvIcon.'<span>Astăzi site-ul a fost actualizat cu <span class="catalog-daily-count">'.ro_number($tv).'</span> '.$tvWords.'</span></a></p>';
}

function catalog_added_today($raw) {
    if(!is_string($raw)||!preg_match('/^\d{4}-\d{2}-\d{2}T/',$raw))return false;
    try {$zone=new DateTimeZone('Europe/Bucharest');return (new DateTimeImmutable($raw))->setTimezone($zone)->format('Y-m-d')===(new DateTimeImmutable('now',$zone))->format('Y-m-d');}
    catch(Exception $e){return false;}
}
