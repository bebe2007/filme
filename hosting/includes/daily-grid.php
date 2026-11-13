<?php if(!isset($items)||!is_array($items))return;$cardDaily=true;$cardFavorites=false;?>
<div class="movie-grid daily-grid cinema-grid-v14"><?php foreach($items as $position=>$item):?>
<?php if(isset($archiveStart)&&$archiveStart===$position):?><div class="archive-divider"><h2>Mai multe de descoperit</h2><p>Explorează și restul catalogului.</p></div><?php endif;?>
<?php include __DIR__.'/card.php';?>
<?php endforeach;?></div><?php unset($cardDaily,$cardFavorites);?>
