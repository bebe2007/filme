<?php $feature=$homeFeature??null; ?>
<?php if($feature):$featureGenres=public_genres($feature['genres']??[]);?>
<section class="home-feature" aria-labelledby="homeHeading">
 <div class="home-feature-image" aria-hidden="true"><img src="<?=e(item_background_url($feature))?>" alt="" width="1280" height="720" fetchpriority="high" decoding="async" referrerpolicy="no-referrer" data-fallback="<?=e(item_poster_url($feature))?>"></div>
 <div class="home-feature-copy"><p class="eyebrow">De descoperit</p><h1 id="homeHeading"><?=e(title_name($feature))?></h1><p class="feature-meta"><?=e(implode(' · ',array_filter([(string)($feature['year']??''),implode(' / ',array_map('genre_label',array_slice($featureGenres,0,2))),!empty($feature['runtime'])?compact_duration($feature['runtime']):''])))?></p>
 <?php if(editorial_summary($feature)!==''):?><p class="feature-summary"><?=e(editorial_summary($feature))?></p><?php endif;?>
 <div class="feature-actions"><a class="primary-btn" href="<?=e(title_path($feature))?>">Descoperă filmul <?=icon('arrow')?></a><a class="ghost-btn" href="/catalog/">Explorează catalogul</a></div></div>
</section>
<?php else:?><section class="home-welcome"><p class="eyebrow">Filme și seriale</p><h1>Următoarea poveste începe aici.</h1><p>Alege după gen, an sau rating și descoperă distribuția fiecărui titlu.</p><a class="primary-btn" href="/catalog/">Explorează catalogul <?=icon('arrow')?></a></section><?php endif;?>
<section class="content" id="continueSection" hidden aria-labelledby="continueHeading"><div class="section-head"><div><p class="eyebrow">În browserul tău</p><h2 id="continueHeading">Continuă vizionarea</h2></div><button class="text-btn" data-clear-progress>Șterge istoricul</button></div><div class="continue-grid" id="continueItems"></div></section>
<?php foreach([
 ['items'=>$homeRecent,'heading'=>'Recent adăugate','intro'=>'Au intrat recent în catalog. Anul afișat este anul lansării.','url'=>'/filme/','link'=>'Toate filmele'],
 ['items'=>$homeTv,'heading'=>'Seriale de descoperit','intro'=>'Povești noi și sezoane de explorat.','url'=>'/seriale/','link'=>'Toate serialele'],
 ['items'=>$homeMovieTop,'heading'=>'Apreciate de spectatori','intro'=>'Rating IMDb, cu minimum 1.000 de voturi.','url'=>catalog_link(['tip'=>'movies','sort'=>'rating','voturi'=>1000]),'link'=>'Explorează selecția'],
] as $section):$items=array_slice(catalog_filter_items($section['items']),0,$section['heading']==='Recent adăugate'?18:12);if(!$items)continue;?>
<section class="content home-shelf"><div class="section-head"><div><h2><?=e($section['heading'])?></h2><p><?=e($section['intro'])?></p></div><a class="section-more" href="<?=e($section['url'])?>"><?=e($section['link'])?> <?=icon('arrow')?></a></div><?php include __DIR__.'/grid.php';?></section>
<?php endforeach;?>
<section class="content explore-section" aria-labelledby="exploreHeading"><div class="section-head"><div><p class="eyebrow">Alege atmosfera</p><h2 id="exploreHeading">Ce ai chef să vezi?</h2></div></div><div class="genre-shortcuts"><?php foreach(['Action','Thriller','Comedy','Drama','Sci-Fi','Documentary'] as $g):?><a href="<?=e(catalog_link(['gen'=>$g]))?>"><?=e(genre_label($g))?> <?=icon('arrow')?></a><?php endforeach;?></div><a class="text-btn" href="<?=e(catalog_link(['q'=>'','format'=>'feature']))?>">Explorează lungmetrajele <?=icon('arrow')?></a></section>
