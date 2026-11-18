<?php $discover=array_values((array)($homeDiscover??[])); ?>
<?php if($discover):?>
<section class="discover" data-discover aria-labelledby="discoverHeading">
 <div class="discover-stage">
 <div class="discover-heading"><h1 id="discoverHeading">De descoperit</h1><span data-discover-count>01 / <?=str_pad((string)count($discover),2,'0',STR_PAD_LEFT)?></span></div>
 <?php foreach($discover as $i=>$feature):$featureGenres=public_genres($feature['genres']??[]);$featureSummary=(isset(editorial_entries()[$feature['imdb_id']])||strpos(strtolower((string)($feature['description_language']??'')),'ro')===0)?editorial_summary($feature):'';?>
 <article class="discover-panel" role="tabpanel" id="discover-panel-<?=$i?>" aria-labelledby="discover-tab-<?=$i?>" data-discover-panel<?=$i?' hidden':''?>>
  <div class="discover-image" aria-hidden="true"><img src="<?=e(item_background_url($feature))?>" alt="" width="1280" height="720" <?=$i?'loading="lazy"':'fetchpriority="high"'?> decoding="async" referrerpolicy="no-referrer" data-fallback="<?=e(item_poster_url($feature))?>"></div>
  <div class="discover-copy"><p class="discover-intro">Cele mai bune note dintre filmele adăugate recent.</p><div class="discover-facts"><span class="discover-rating"><?=icon('star')?> <?=e(number_format((float)$feature['rating'],1,',',''))?> <small>IMDb</small></span><?php if(!empty($feature['year'])):?><span><?=icon('calendar')?> <?=(int)$feature['year']?></span><?php endif;?></div><h2><?=e(title_name($feature))?></h2><p class="discover-genres"><?=e(implode(' / ',array_map('genre_label',array_slice($featureGenres,0,3))))?></p>
  <?php if($featureSummary!==''):?><p class="discover-summary"><?=e($featureSummary)?></p><?php else:?><p class="discover-summary">Vezi povestea, distribuția și detaliile filmului.</p><?php endif;?>
  <a class="primary-btn" href="<?=e(title_path($feature))?>">Vezi filmul <?=icon('arrow')?></a></div>
 </article><?php endforeach;?>
 </div>
 <div class="discover-selection"><div class="discover-selection-head"><span>Selecția zilei</span><span><?=count($discover)?> filme</span></div><div class="discover-tabs" role="tablist" aria-label="Filme adăugate recent, în ordinea notei IMDb">
 <?php foreach($discover as $i=>$feature):?><button class="discover-tab" id="discover-tab-<?=$i?>" role="tab" type="button" data-discover-tab aria-controls="discover-panel-<?=$i?>" aria-selected="<?=$i?'false':'true'?>" tabindex="<?=$i?'-1':'0'?>"><span class="discover-rank"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><span class="discover-tab-copy"><strong><?=e(title_name($feature))?></strong><span><?=icon('star')?> <?=e(number_format((float)$feature['rating'],1,',',''))?> <small>IMDb</small><?php if(!empty($feature['year'])):?><span class="discover-tab-year"><?=(int)$feature['year']?></span><?php endif;?></span></span><?=icon('chevron','discover-tab-arrow')?></button><?php endforeach;?></div><a class="discover-all" href="/filme/">Toate filmele noi <?=icon('arrow')?></a></div>
</section>
<?php else:?><section class="home-welcome"><p class="eyebrow">Filme și seriale</p><h1>Ce vedem în seara asta?</h1><p>Intră în catalog și alege după gen, an sau nota IMDb.</p><a class="primary-btn" href="/catalog/">Vezi catalogul <?=icon('arrow')?></a></section><?php endif;?>
<section class="content" id="continueSection" hidden aria-labelledby="continueHeading"><div class="section-head"><div><p class="eyebrow">În browserul tău</p><h2 id="continueHeading">Continuă vizionarea</h2></div><button class="text-btn" data-clear-progress>Șterge istoricul</button></div><div class="continue-grid" id="continueItems"></div></section>
<?php foreach([
 ['items'=>$homeRecent,'heading'=>'Filme noi în catalog','intro'=>'Ultimele filme adăugate pe site.','url'=>'/filme/','link'=>'Toate filmele'],
 ['items'=>$homeTv,'heading'=>'Seriale în catalog','intro'=>'Alege un serial și vezi episoadele disponibile.','url'=>'/seriale/','link'=>'Toate serialele'],
 ['items'=>$homeMovieTop,'heading'=>'Filme cu note mari','intro'=>'Note IMDb calculate din cel puțin 1.000 de voturi.','url'=>catalog_link(['tip'=>'movies','sort'=>'rating','voturi'=>1000]),'link'=>'Explorează selecția'],
] as $section):$items=array_slice(catalog_filter_items($section['items']),0,$section['heading']==='Filme noi în catalog'?18:12);if(!$items)continue;?>
<section class="content home-shelf"><div class="section-head"><div><h2><?=e($section['heading'])?></h2><p><?=e($section['intro'])?></p></div><a class="section-more" href="<?=e($section['url'])?>"><?=e($section['link'])?> <?=icon('arrow')?></a></div><?php include __DIR__.'/grid.php';?></section>
<?php endforeach;?>
<section class="content explore-section" aria-labelledby="exploreHeading"><div class="section-head"><div><p class="eyebrow">După gen</p><h2 id="exploreHeading">Ce vedem azi?</h2></div></div><div class="genre-shortcuts"><?php foreach(['Action','Thriller','Comedy','Drama','Sci-Fi','Documentary'] as $g):?><a href="<?=e(catalog_link(['gen'=>$g]))?>"><?=e(genre_label($g))?> <?=icon('arrow')?></a><?php endforeach;?></div><a class="text-btn" href="<?=e(catalog_link(['q'=>'','format'=>'feature']))?>">Vezi lungmetrajele <?=icon('arrow')?></a></section>
