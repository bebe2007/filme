<?php
require_once __DIR__.'/v14-ui.php';
require_once __DIR__.'/catalog-sync.php';
$id=(string)($item['imdb_id']??'');if(!preg_match('/^tt\d{5,12}$/',$id))return;
$name=title_name($item);$type=title_type($item);$poster=item_poster_url($item);$backdrop=item_background_url($item);$year=(int)($item['year']??0);$rating=(float)($item['rating']??0);$runtime=(int)($item['runtime']??0);
$genres=array_slice(public_genres($item['genres']??[]),0,2);$showFavorite=!empty($cardFavorites);$isEager=!empty($gridEager)||(isset($position)&&$position<3);
?>
<article class="movie-card cinema-card-v14 premiere-card compact-card<?=$showFavorite?' has-card-favorite':''?><?=!empty($cardDaily)?' daily-card':''?>" data-title-id="<?=e($id)?>">
 <a class="movie-card-link" href="<?=e(title_path($item))?>" aria-label="<?=e($name)?> — vezi detalii">
  <div class="poster compact-scene"><img src="<?=e($backdrop)?>" alt="" width="780" height="440" loading="<?=$isEager?'eager':'lazy'?>" decoding="async" referrerpolicy="no-referrer" data-fallback="<?=e($poster)?>"></div>
  <span class="compact-kind"><?=v14_icon('film')?><?=e(type_label($type))?></span>
  <?php if($rating>0):?><span class="compact-rating" aria-label="Rating IMDb <?=e(number_format($rating,1,',',''))?>"><?=v14_icon('star')?><strong><?=e(number_format($rating,1,',',''))?></strong><small>IMDb</small></span><?php endif;?>
  <div class="card-body compact-body">
   <img class="compact-cover" src="<?=e($poster)?>" alt="Poster <?=e($name)?>" width="150" height="225" loading="<?=$isEager?'eager':'lazy'?>" decoding="async" referrerpolicy="no-referrer" data-fallback="/assets/img/poster-placeholder.svg">
   <div class="compact-copy">
    <div class="compact-facts"><?php if($year):?><span><?=$year?></span><?php endif;?><?php if($runtime>0):?><span><?=e(compact_duration($runtime))?><?=$type==='tv'?' / ep.':''?></span><?php endif;?></div>
    <h3 class="card-title"><?=e($name)?></h3>
    <?php if($genres):?><p class="compact-genres"><?=e(implode(' · ',array_map('genre_label',$genres)))?></p><?php endif;?>
    <span class="compact-cta">Vezi detalii <?=v14_icon('arrow')?></span>
   </div>
  </div>
  <span class="compact-center-play" aria-hidden="true"><?=icon('play')?></span>
 </a>
 <?php if($showFavorite):?><button class="favorite-toggle icon-btn" type="button" data-fav-id="<?=e($id)?>" data-fav-title="<?=e($name)?>" data-fav-url="<?=e(title_path($item))?>" data-fav-poster="<?=e($poster)?>" aria-label="Adaugă <?=e($name)?> în Lista mea" aria-pressed="false" title="Adaugă în Lista mea"><?=icon('heart')?></button><?php endif;?>
</article>
