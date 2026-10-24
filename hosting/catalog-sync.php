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
    return '<span class="catalog-added-date" style="display:flex;align-items:center;gap:.35em;font-size:.75rem;line-height:1.45;opacity:.82;margin:.35rem 0"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" style="flex-shrink:0"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg><time datetime="'.htmlspecialchars($item['site_added_at'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</time></span>';
}
