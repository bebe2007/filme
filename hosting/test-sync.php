<?php
require __DIR__.'/catalog-sync.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(catalog_added_label(['type'=>'movie','site_added_at'=>'2026-10-24T12:00:00+00:00'])==='Film adăugat pe 24 Octombrie 2026','Romanian movie date');
check(catalog_added_label(['type'=>'tv','site_added_at'=>'2026-10-24T12:00:00+00:00'])==='Serial adăugat pe 24 Octombrie 2026','Romanian series date');
check(catalog_added_label(['provider_added_at'=>'2026-10-24T12:00:00Z'])==='','Provider date is not a site addition date');
check(catalog_added_label(['site_added_at'=>'unknown'])==='','Unknown date must not be invented');
check(str_contains(catalog_added_badge(['site_added_at'=>'2026-10-24T12:00:00Z']),'<svg'),'Calendar icon');
echo "Date labels verified\n";

function ro_number($value){return number_format($value,0,',','.');}
function catalog_json($path,$ttl=null){global $testDaily;return $testDaily;}
$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
$testDaily=['date'=>$today,'verified'=>true,'movies'=>82,'tv'=>16];
$footer=catalog_daily_footer();
check(str_contains($footer,'<strong>82</strong> de filme'),'Verified movie footer count');
check(str_contains($footer,'<strong>16</strong> seriale'),'Verified TV footer count');
$testDaily=['date'=>'2000-01-01','verified'=>true,'movies'=>0,'tv'=>0];
check(!str_contains(catalog_daily_footer(),'<strong>0</strong>'),'Stale counts are not presented as zero today');
echo "Footer counts verified\n";
