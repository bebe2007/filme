<?php
require __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
$type=$_GET['type']??'';$part=max(1,(int)($_GET['part']??1));$size=min(45000,max(1000,(int)cfg('sitemap_part_size')));
function xml_e($v){return htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');}
function sitemap_ids($type){
    $local=__DIR__.'/data/catalog-'.$type.'-ids.txt';
    if(is_file($local))$raw=@file_get_contents($local);else{$file=$type==='tv'?'tv_imdb.txt':'movie_imdb.txt';$raw=remote_text(provider_url('ids/'.$file),43200);}
    if($raw===false)return[];$ids=preg_split('/\R+/',trim($raw));$ids=array_values(array_filter(array_map('strtolower',array_map('trim',$ids)),function($x){return preg_match('/^tt\d{5,12}$/',$x);}));
    if(subtitle_filter_active()){$idx=ro_subtitle_index();$ids=array_values(array_filter($ids,function($id)use($idx){$r=$idx[$id]??null;return$r===true||(is_array($r)&&!empty($r['confirmed']));}));}
    return array_values(array_filter($ids,function($id){return !title_is_blocked($id);}));
}
function sitemap_people_ids(){
    $raw=remote_text(data_url('people-ids.txt'),43200);if($raw===false)return[];
    return array_values(array_filter(array_map('strtolower',array_map('trim',preg_split('/\R+/',trim($raw)))),function($x){return preg_match('/^nm\d{5,12}$/',$x);}));
}
$meta=remote_json(data_url('meta.json'))?:[];$lastmod=!empty($meta['generated_at'])?substr((string)$meta['generated_at'],0,10):gmdate('Y-m-d');
if($type==='static'){$urls=[site_url('/'),site_url('/filme/'),site_url('/seriale/'),site_url('/credite/'),site_url('/contact/'),site_url('/politica-de-confidentialitate/'),site_url('/politica-cookie/'),site_url('/termeni-de-utilizare/'),site_url('/drepturi-de-autor/')];foreach(public_genres($meta['genres']??[]) as $g)$urls[]=site_url('/gen/'.genre_route_slug($g).'/');foreach((array)($meta['years']??[]) as $y)$urls[]=site_url('/an/'.(int)$y.'/');echo"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";foreach($urls as $u)echo'<url><loc>'.xml_e($u).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></url>\n";echo'</urlset>';exit;}
if($type==='movies'||$type==='tv'){$ids=sitemap_ids($type);$slice=array_slice($ids,($part-1)*$size,$size);echo"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";foreach($slice as $id){$url=$type==='tv'?site_url('/serial/'.$id.'/'):site_url('/film/'.$id.'/');echo'<url><loc>'.xml_e($url).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></url>\n";}echo'</urlset>';exit;}
if($type==='people'){$ids=sitemap_people_ids();$slice=array_slice($ids,($part-1)*$size,$size);echo"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";foreach($slice as $id){echo'<url><loc>'.xml_e(person_url($id)).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></url>\n";}echo'</urlset>';exit;}
$movieIds=sitemap_ids('movies');$tvIds=sitemap_ids('tv');$peopleIds=sitemap_people_ids();$movieParts=max(1,(int)ceil(count($movieIds)/$size));$tvParts=max(1,(int)ceil(count($tvIds)/$size));$peopleParts=count($peopleIds)?max(1,(int)ceil(count($peopleIds)/$size)):0;echo"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";echo'<sitemap><loc>'.xml_e(site_url('/sitemap-static.xml')).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></sitemap>\n";for($i=1;$i<=$movieParts;$i++)echo'<sitemap><loc>'.xml_e(site_url('/sitemap-movies-'.$i.'.xml')).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></sitemap>\n";for($i=1;$i<=$tvParts;$i++)echo'<sitemap><loc>'.xml_e(site_url('/sitemap-tv-'.$i.'.xml')).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></sitemap>\n";for($i=1;$i<=$peopleParts;$i++)echo'<sitemap><loc>'.xml_e(site_url('/sitemap-people-'.$i.'.xml')).'</loc><lastmod>'.xml_e($lastmod)."</lastmod></sitemap>\n";echo'</sitemapindex>';
