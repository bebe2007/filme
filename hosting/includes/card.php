<?php
require_once __DIR__.'/v14-ui.php';
$id=(string)($item['imdb_id']??'');if(!preg_match('/^tt\d{5,12}$/',$id))return;
$name=title_name($item);$type=title_type($item);$poster=item_poster_url($item);$year=(int)($item['year']??0);$rating=(float)($item['rating']??0);$eager=!empty($gridEager)&&isset($position)&&$position<6;
?>
<article class="movie-card" data-title-id="<?=e($id)?>">
 <a class="movie-card-link" href="<?=e(title_path($item))?>" aria-label="<?=e($name)?> — vezi detalii">
  <div class="poster"><img src="<?=e($poster)?>" alt="Poster <?=e($name)?>" width="342" height="513" loading="<?=$eager?'eager':'lazy'?>" decoding="async" <?=poster_attributes($item)?>>
   <?php if($rating>0):?><span class="card-rating"><?=icon('star')?><strong><?=e(number_format($rating,1,',',''))?></strong><span class="rating-source">IMDb</span></span><?php endif;?>
   <?php if($year):?><span class="card-year"><?=icon('calendar')?><span><?=$year?></span></span><?php endif;?>
   <span class="card-details">Vezi detalii <?=icon('arrow')?></span>
  </div>
  <div class="card-body"><h3 class="card-title"><?=e($name)?></h3><p class="card-meta"><span><?=e(type_label($type))?></span><?php $cardGenres=public_genres($item['genres']??[]);if($cardGenres):?><span><?=e(genre_label($cardGenres[0]))?></span><?php endif;?></p><?php if(!empty($item['recommendation_reason'])):?><p class="card-reason"><?=e($item['recommendation_reason'])?></p><?php endif;?></div>
 </a>
 <button class="favorite-toggle icon-btn" type="button" data-fav-id="<?=e($id)?>" data-fav-title="<?=e($name)?>" data-fav-url="<?=e(title_path($item))?>" data-fav-poster="<?=e($poster)?>" aria-label="Adaugă <?=e($name)?> în Lista mea" aria-pressed="false"><?=icon('heart')?></button>
</article>
