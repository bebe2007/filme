<?php
$menuYears=array_values(array_unique(array_filter(array_map('intval',(array)($meta['years']??[])))));rsort($menuYears);
?>
<header class="site-header"><div class="wrap header-row">
 <a class="brand brand-v10 brand-premium" href="/" aria-label="Bratu Marian — Filme și seriale, Acasă"><img class="cinema-logo" src="/assets/img/logo-cinema.svg" alt="Bratu Marian — Filme și seriale" width="336" height="72"></a>
 <nav class="main-nav" id="mainNav" aria-label="Navigare principală">
  <a href="/"<?=$route==='home'?' class="active" aria-current="page"':''?>><?=icon('home')?><span>Acasă</span></a>
  <a href="/filme/"<?=$route==='movies'?' class="active" aria-current="page"':''?>><?=icon('film')?><span>Filme</span></a>
  <a href="/seriale/"<?=$route==='tv'?' class="active" aria-current="page"':''?>><?=icon('tv')?><span>Seriale</span></a>
  <details class="nav-dropdown" id="categoryMenu"><summary><?=icon('tag')?><span>Genuri</span><?=icon('chevron-down','nav-chevron')?></summary><div class="menu-panel category-menu"><div class="menu-heading"><div><span class="section-kicker">Ce ai chef să vezi?</span><h2>Explorează genurile</h2></div><button class="icon-btn" type="button" data-close-menu aria-label="Închide genurile"><?=icon('close')?></button></div><div class="menu-genres"><?php foreach(genre_map() as $g=>$info):?><a href="<?=e(catalog_link(['gen'=>$g,'tip'=>$route==='tv'?'tv':($route==='movies'?'movies':'all')]))?>"><?=e(genre_label($g))?><?=icon('arrow')?></a><?php endforeach;?></div></div></details>
  <a href="/catalog/"<?=$route==='catalog'?' class="active" aria-current="page"':''?>><?=icon('grid')?><span>Catalog</span></a>
 </nav>
 <form class="header-search" action="/cauta/" role="search" autocomplete="off"><label class="visually-hidden" for="q">Caută filme, seriale sau persoane</label><input class="search-input" id="q" name="q" type="search" value="<?=e($query)?>" placeholder="Titlu, actor, regizor…" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="searchSuggest"><button class="search-button" aria-label="Caută"><?=icon('search')?></button><div class="search-suggest" id="searchSuggest" role="listbox" hidden></div></form>
 <button class="icon-btn header-watchlist" data-open-watchlist type="button" aria-label="Deschide Lista mea"><?=icon('heart')?></button>
 <button class="icon-btn mobile-nav-toggle" id="mobileNavToggle" type="button" aria-expanded="false" aria-controls="mainNav" aria-label="Deschide meniul"><?=icon('menu')?></button>
</div></header>
