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
 <?php foreach($discover as $i=>$feature):?><button class="discover-tab" id="discover-tab-<?=$i?>" role="tab" type="button" data-discover-tab aria-controls="discover-panel-<?=$i?>" aria-selected="<?=$i?'false':'true'?>" tabindex="<?=$i?'-1':'0'?>"><span class="discover-rank"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><span class="discover-tab-copy"><strong><?=e(title_name($feature))?></strong><span><?=icon('star')?> <?=e(number_format((float)$feature['rating'],1,',',''))?> <small>IMDb</small><?php if(!empty($feature['year'])):?><span class="discover-tab-year"><?=(int)$feature['year']?></span><?php endif;?></span></span><?=icon('chevron','discover-tab-arrow')?></button><?php endforeach;?></div><a class="discover-all" href="/#filme-noi">Toate filmele noi <?=icon('arrow')?></a></div>
</section>
<?php else:?><section class="home-welcome"><p class="eyebrow">Filme și seriale</p><h1>Ce vedem în seara asta?</h1><p>Intră în catalog și alege după gen, an sau nota IMDb.</p><a class="primary-btn" href="/catalog/">Vezi catalogul <?=icon('arrow')?></a></section><?php endif;?>
<section class="content" id="continueSection" hidden aria-labelledby="continueHeading"><div class="section-head"><div><p class="eyebrow">În browserul tău</p><h2 id="continueHeading">Continuă vizionarea</h2></div><button class="text-btn" data-clear-progress>Șterge istoricul</button></div><div class="continue-grid" id="continueItems"></div></section>
<?php
$dailySections=[
 'movies'=>['id'=>'filme-noi','param'=>'filme','heading'=>'Filme noi în catalog','intro'=>'Aici adun filmele care au intrat azi în catalog. Printre ele poți găsi și filme mai vechi, pe care abia acum le-am adăugat. Dacă îți atrage atenția un poster, deschide pagina filmului pentru poveste, distribuție și trailer. Poți salva oricând un titlu în Lista mea, ca să-l găsești ușor mai târziu.'],
 'tv'=>['id'=>'seriale-noi','param'=>'seriale','heading'=>'Seriale în catalog','intro'=>'Aici găsești serialele pe care le-am adăugat azi, fie că sunt producții noi sau seriale mai vechi care până acum lipseau din catalog. Deschide pagina unui serial ca să vezi despre ce e vorba și ce sezoane și episoade sunt disponibile. Dacă vrei să revii la el peste câteva zile, salvează-l în Lista mea.'],
];
foreach($dailySections as $kind=>$section):
 $daily=$homeDaily[$kind];
 $items=$daily['items'];
 $pageUrl=static function($number)use($section,$homeDaily){
     $params=['filme'=>$homeDaily['movies']['page'],'seriale'=>$homeDaily['tv']['page']];
     $params[$section['param']]=$number;
     $params=array_filter($params,static fn($page)=>$page>1);
     return '/'.($params?'?'.http_build_query($params):'').'#'.$section['id'];
 };
?>
<section class="content home-shelf home-daily home-daily-<?=e($kind)?>" id="<?=e($section['id'])?>" aria-labelledby="<?=e($section['id'])?>-titlu">
 <div class="section-head"><div><h2 id="<?=e($section['id'])?>-titlu"><?=icon($kind==='tv'?'tv':'film')?> <?=e($section['heading'])?></h2><p><?=e($section['intro'])?></p></div>
 <?php if(empty($daily['unavailable'])):?><span class="daily-total"><?=ro_number($daily['total'])?> <?=$kind==='tv'?'seriale':'filme'?> azi</span><?php endif;?></div>
 <?php if($items):include __DIR__.'/grid.php';else:?><p class="status"><?=!empty($daily['unavailable'])?'Lista de azi nu este disponibilă momentan.':($kind==='tv'?'Azi nu a fost adăugat niciun serial.':'Azi nu a fost adăugat niciun film.')?></p><?php endif;?>
 <?php if($daily['pages']>1):?><nav class="pagination" aria-label="<?=e('Pagini — '.$section['heading'])?>">
 <?php if($daily['page']>1):?><a href="<?=e($pageUrl($daily['page']-1))?>" aria-label="Pagina precedentă">‹</a><?php endif;?>
 <?php foreach(pagination_window($daily['page'],$daily['pages']) as $number):?>
 <?php if($number==='…'):?><span>…</span><?php else:?><a href="<?=e($pageUrl($number))?>"<?=$number==$daily['page']?' class="active" aria-current="page"':''?>><?=(int)$number?></a><?php endif;?>
 <?php endforeach;?>
 <?php if($daily['page']<$daily['pages']):?><a href="<?=e($pageUrl($daily['page']+1))?>" aria-label="Pagina următoare">›</a><?php endif;?>
 </nav><?php endif;?>
</section>
<?php endforeach;?>
<section class="content home-shelf" id="filme-cu-note-mari">
 <div class="section-head"><div><h2><?=icon('star')?> Filme cu note mari</h2><p>Când nu știu ce film să aleg, mă uit și la ce au apreciat alți spectatori. Aici am pus filme cu note mari pe IMDb și cu cel puțin 1.000 de voturi. Lângă fiecare poster găsești titlul, anul și câteva rânduri despre poveste, ca să-ți faci o idee înainte să deschizi pagina filmului și să vezi trailerul.</p></div><a class="section-more" href="<?=e(catalog_link(['tip'=>'movies','sort'=>'rating','voturi'=>1000]))?>">Explorează selecția <?=icon('arrow')?></a></div>
 <div class="episode-grid ranked-film-grid">
 <?php foreach(catalog_filter_items($homeMovieTop) as $ratedFilm):
   $name=trim((string)($ratedFilm['original_title']??''))?: (trim((string)($ratedFilm['title']??''))?:title_name($ratedFilm));$id=$ratedFilm['imdb_id'];$path=title_path($ratedFilm);
   $summary=isset(editorial_entries()[$id])||!empty($ratedFilm['editorial']['description'])||strpos(strtolower((string)($ratedFilm['description_language']??'')),'ro')===0?editorial_summary($ratedFilm):'';
 ?>
 <article class="episode ranked-film">
   <a class="episode-media ranked-poster" href="<?=e($path)?>" aria-label="<?=e($name)?>">
     <img src="<?=e(item_poster_url($ratedFilm))?>" alt="" width="342" height="513" loading="lazy" decoding="async" <?=poster_attributes($ratedFilm)?>>
     <span class="episode-play"><?=icon('play')?></span>
   </a>
   <div class="episode-content"><p class="episode-top"><span class="episode-rating"><?=icon('star')?> <?=e(number_format((float)$ratedFilm['rating'],1,',',''))?> IMDb</span><?php if(!empty($ratedFilm['year'])):?><span><?=(int)$ratedFilm['year']?></span><?php endif;?></p>
     <h3 class="episode-title"><a href="<?=e($path)?>"><?=e($name)?></a></h3>
     <p class="episode-description"<?=$summary===''?' data-ranked-summary="'.e($id).'"':''?>><?=e($summary?:'Descrierea în română nu este încă disponibilă.')?></p>
   </div>
 </article>
 <?php endforeach;?>
 </div>
</section>
<section class="content explore-section" aria-labelledby="exploreHeading"><div class="section-head"><div><p class="eyebrow">După gen</p><h2 id="exploreHeading">Ce vedem azi?</h2></div></div><div class="genre-shortcuts"><?php foreach(['Action','Thriller','Comedy','Drama','Sci-Fi','Documentary'] as $g):?><a href="<?=e(catalog_link(['gen'=>$g]))?>"><?=e(genre_label($g))?> <?=icon('arrow')?></a><?php endforeach;?></div><a class="text-btn" href="<?=e(catalog_link(['q'=>'','format'=>'feature']))?>">Vezi lungmetrajele <?=icon('arrow')?></a></section>
