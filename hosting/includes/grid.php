<?php if(!isset($items)||!is_array($items))return;$cardDaily=false;$cardFavorites=true;?>
<div class="movie-grid cinema-grid-v14"><?php foreach(catalog_filter_items($items) as $position=>$item):if(empty($item['imdb_id']))continue;remember_title_preview($item);include __DIR__.'/card.php';endforeach;?></div>
<?php unset($cardDaily,$cardFavorites);?>
