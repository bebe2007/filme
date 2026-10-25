<?php
require_once __DIR__.'/v14-ui.php';
require_once __DIR__.'/catalog-sync.php';
// Shared horizontal card. Reads existing metadata only; no network calls or disk writes.
$id=(string)($item['imdb_id']??'');if(!preg_match('/^tt\d{5,12}$/',$id))return;
$name=title_name($item);$type=title_type($item);$poster=item_poster_url($item);$year=(int)($item['year']??0);$rating=(float)($item['rating']??0);$runtime=(int)($item['runtime']??0);
$genres=array_slice(public_genres($item['genres']??[]),0,2);$summary=v14_summary($item);
$summary=trim(strip_tags($summary));$showFavorite=!empty($cardFavorites);$isEager=!empty($gridEager)||(isset($position)&&$position<4);
?>
<article class="movie-card cinema-card-v14<?=$showFavorite?' has-card-favorite':''?><?=!empty($cardDaily)?' daily-card':''?>" data-title-id="<?=e($id)?>">
<a class="movie-card-link" href="<?=e(title_path($item))?>" aria-label="<?=e($name)?> — vezi detalii">
<div class="poster"><img src="<?=e($poster)?>" alt="Poster <?=e($name)?>" width="300" height="450" loading="<?=$isEager?'eager':'lazy'?>" decoding="async" referrerpolicy="no-referrer" data-fallback="/assets/img/poster-placeholder.svg"><span class="catalog-poster-play" aria-hidden="true"><span><svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span></span></div>
<div class="card-body"><div class="cinema-card-topline"><span class="cinema-card-kind"><?=v14_icon('film')?><?=e(type_label($type))?><?php if($year):?><span aria-hidden="true"> · </span><?=$year?><?php endif;?><?php if($runtime>0):?><span class="cinema-card-time"><?=v14_icon('clock')?><?=e(compact_duration($runtime))?></span><?php endif;?></span><?php if($rating>0):?><span class="cinema-card-rating" aria-label="Rating IMDb <?=e(number_format($rating,1,',',''))?>"><?=v14_icon('star')?> <?=e(number_format($rating,1,',',''))?><small>IMDb</small></span><?php endif;?></div>
<h3 class="card-title"><?=e($name)?></h3>
<?php if($genres):?><div class="card-genres"><?=v14_icon('tag')?><?php foreach($genres as $g):?><span><?=e(genre_label($g))?></span><?php endforeach;?></div><?php endif;?>
<?php if($showFavorite&&has_verified_ro_subtitle($id)):?><span class="v14-ro" title="Subtitrare în română confirmată">RO</span><?php endif;?>
<?php if($summary!==''):?><p class="cinema-card-summary"><?=e($summary)?></p><?php endif;?>
<?=catalog_added_badge($item)?>
<div class="cinema-card-footer"><span class="cinema-card-cta"><span class="cinema-card-cta-label"><?=$type==='tv'?'Vezi serialul online':'Vezi filmul online'?></span><span class="cinema-card-cta-mobile"><?=$type==='tv'?'Vezi serialul online':'Vezi filmul online'?></span><span class="cinema-card-arrow" aria-hidden="true"><?=v14_icon('arrow')?></span></span></div></div></a>
<?php if($showFavorite):?><button class="favorite-toggle icon-btn" type="button" data-fav-id="<?=e($id)?>" data-fav-title="<?=e($name)?>" data-fav-url="<?=e(title_path($item))?>" data-fav-poster="<?=e($poster)?>" aria-label="Adaugă <?=e($name)?> în Lista mea" aria-pressed="false" title="Adaugă în Lista mea"><?=icon('heart')?></button><?php endif;?></article>
