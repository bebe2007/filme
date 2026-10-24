<?php
require __DIR__.'/catalog-sync.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(catalog_added_label(['type'=>'movie','site_added_at'=>'2026-10-24T12:00:00+00:00'])==='Film adăugat pe 24 Octombrie 2026','Romanian movie date');
check(catalog_added_label(['type'=>'tv','site_added_at'=>'2026-10-24T12:00:00+00:00'])==='Serial adăugat pe 24 Octombrie 2026','Romanian series date');
check(catalog_added_label(['provider_added_at'=>'2026-10-24T12:00:00Z'])==='','Provider date is not a site addition date');
check(catalog_added_label(['site_added_at'=>'unknown'])==='','Unknown date must not be invented');
check(str_contains(catalog_added_badge(['site_added_at'=>'2026-10-24T12:00:00Z']),'<svg'),'Calendar icon');
echo "Date labels verified\n";
