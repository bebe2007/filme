<?php
require_once __DIR__.'/v14-ui.php';
$id=(string)($item['imdb_id']??'');if(!preg_match('/^tt\d{5,12}$/',$id))return;
$name=title_name($item);$type=title_type($item);$poster=item_poster_url($item);$year=(int)($item['year']??0);$rating=(float)($item['rating']??0);$genres=public_genres($item['genres']??[]);$primaryGenre=$genres?genre_label($genres[0]):'';$isSerial=$type==='tv';$eager=!empty($gridEager)&&isset($position)&&$position<6;
?>
<article class="movie-card" data-title-id="<?=e($id)?>" data-card-type="<?=e($type)?>">
 <a class="movie-card-link" href="<?=e(title_path($item))?>" aria-label="<?=e($name)?> — deschide pagina">
  <div class="poster"><span class="card-kind"><?=icon($isSerial?'tv':'film')?><?=e(type_label($type))?></span><img src="<?=e($poster)?>" alt="Poster <?=e($name)?>" width="342" height="513" loading="<?=$eager?'eager':'lazy'?>" decoding="async" <?=poster_attributes($item,true)?>>
   <div class="poster-reserve" hidden aria-hidden="true"><?=icon($isSerial?'tv':'film')?></div>
   <?php if($rating>0):?><span class="card-rating"><?=icon('star')?><strong><?=e(number_format($rating,1,',',''))?></strong><span class="rating-source">IMDb</span></span><?php endif;?>
   <?php if($year):?><span class="card-year"><?=icon('calendar')?><span><?=$year?></span></span><?php endif;?>
   <?php if($primaryGenre!==''):?><span class="card-genre"><?=icon('tag')?><?=e($primaryGenre)?></span><?php endif;?>
   <span class="card-play" aria-hidden="true"><?=icon('play')?></span>
  </div>
 </a>
 <button class="favorite-toggle icon-btn" type="button" data-fav-id="<?=e($id)?>" data-fav-title="<?=e($name)?>" data-fav-url="<?=e(title_path($item))?>" data-fav-poster="<?=e($poster)?>" aria-label="Adaugă <?=e($name)?> în Lista mea" aria-pressed="false"><?=icon('heart')?></button>
</article>
