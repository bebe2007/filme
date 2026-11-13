<?php
function v14_icon($name){
 $paths=[
 'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'film'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
 'tag'=>'<path d="M20 13 13 20a2 2 0 0 1-3 0l-7-7V3h10l7 7a2 2 0 0 1 0 3Z"/><circle cx="7.5" cy="7.5" r=".8"/>',
 'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
 'star'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>',
 'person'=>'<circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>',
 'heart'=>'<path d="M20 5a5 5 0 0 0-7 0l-1 1-1-1a5 5 0 0 0-7 7l8 8 8-8a5 5 0 0 0 0-7Z"/>',
 'chevron-left'=>'<path d="m15 6-6 6 6 6"/>','chevron-right'=>'<path d="m9 6 6 6-6 6"/>',
 'info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
 ];$p=$paths[$name]??$paths['info'];return '<svg class="v14-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
}
function v14_entries(){static $entries=null;if($entries===null)$entries=json_decode((string)@file_get_contents(dirname(__DIR__).'/data/editorial-v14.json'),true)?:[];return $entries;}
function v14_summary($item){$id=$item['imdb_id']??'';$entries=v14_entries();if(!empty($entries[$id]['description']))return $entries[$id]['description'];$manual=editorial_entries();if(!empty($manual[$id]['description']))return $manual[$id]['description'];if(!empty($item['editorial']['description']))return trim(strip_tags((string)$item['editorial']['description']));if(strpos(strtolower((string)($item['description_language']??'')),'ro')===0)return trim(strip_tags(editorial_summary($item)));return '';}
function v14_factual_intro($item){$genres=public_genres($item['genres']??[]);$kind=in_array('Documentary',$genres,true)?'Documentar':(title_type($item)==='tv'?'Serial':'Film');$year=(int)($item['year']??0);$duration=(int)($item['runtime']??0);$text=$kind.($year?' din '.$year:'');if($duration>0)$text.=', cu o durată de '.duration_label($duration);return $text.'.';}
