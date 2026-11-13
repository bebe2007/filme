<?php
require_once __DIR__.'/v14-ui.php';
require_once __DIR__.'/catalog-sync.php';
$id=(string)($item['imdb_id']??'');if(!preg_match('/^tt\d{5,12}$/',$id))return;
$name=title_name($item);$type=title_type($item);$poster=item_poster_url($item);$year=(int)($item['year']??0);$rating=(float)($item['rating']??0);$runtime=(int)($item['runtime']??0);
$genres=array_slice(public_genres($item['genres']??[]),0,2);$summary=trim(strip_tags(v14_summary($item)));$showFavorite=!empty($cardFavorites);$isEager=!empty($gridEager)||(isset($position)&&$position<5);
?>
<article class="movie-card cinema-card-v14 premiere-card<?=$showFavorite?' has-card-favorite':''?><?=!empty($cardDaily)?' daily-card':''?>" data-title-id="<?=e($id)?>">
<a class="movie-card-link" href="<?=e(title_path($item))?>" aria-label="<?=e($name)?> — vezi detalii">
 <div class="poster">
  <img src="<?=e($poster)?>" alt="Poster <?=e($name)?>" width="300" height="450" loading="<?=$isEager?'eager':'lazy'?>" decoding="async" referrerpolicy="no-referrer" data-fallback="/assets/img/poster-placeholder.svg">
  <span class="premiere-type"><?=e(type_label($type))?></span>
  <?php if($rating>0):?><span class="premiere-rating" aria-label="Rating IMDb <?=e(number_format($rating,1,',',''))?>"><?=v14_icon('star')?><strong><?=e(number_format($rating,1,',',''))?></strong><small>IMDb</small></span><?php endif;?>
  <span class="premiere-poster-action" aria-hidden="true"><span><?=icon('play')?></span><b>Vezi detalii</b></span>
 </div>
 <div class="card-body">
  <div class="premiere-facts"><?php if($year):?><span><?=$year?></span><?php endif;?><?php if($runtime>0):?><span><?=v14_icon('clock')?><?=e(compact_duration($runtime))?><?=$type==='tv'?' / ep.':''?></span><?php endif;?></div>
  <h3 class="card-title"><?=e($name)?></h3>
  <?php if($genres):?><div class="card-genres"><?php foreach($genres as $g):?><span><?=e(genre_label($g))?></span><?php endforeach;?></div><?php endif;?>
  <?php if($summary!==''):?><p class="premiere-summary"><?=e($summary)?></p><?php endif;?>
  <div class="premiere-card-bottom"><?=catalog_added_badge($item)?><span class="premiere-detail-link">Detalii <?=v14_icon('arrow')?></span></div>
 </div>
</a>
<?php if($showFavorite):?><button class="favorite-toggle icon-btn" type="button" data-fav-id="<?=e($id)?>" data-fav-title="<?=e($name)?>" data-fav-url="<?=e(title_path($item))?>" data-fav-poster="<?=e($poster)?>" aria-label="Adaugă <?=e($name)?> în Lista mea" aria-pressed="false" title="Adaugă în Lista mea"><?=icon('heart')?></button><?php endif;?>
</article>
