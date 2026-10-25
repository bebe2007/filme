<?php
require __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/catalog-sync.php';
require __DIR__ . '/includes/legal-content.php';
$legalPage='';
$cspNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; frame-ancestors 'self'; script-src 'self' 'nonce-{$cspNonce}'; style-src 'self'; img-src 'self' data: https:; connect-src 'self'; frame-src https://vsembed.ru https://*.vsembed.ru https://www.youtube-nocookie.com; font-src 'self' data:; upgrade-insecure-requests");

if (!isset($_GET['route']) && isset($_GET['id']) && preg_match('/^tt\d{5,12}$/i', $_GET['id'])) {
    $old = load_title(strtolower($_GET['id']));
    if ($old) { header('Location: ' . title_url($old), true, 301); exit; }
}

$route=query_string('route','home');$page=max(1,(int)($_GET['page']??1));$meta=catalog_json('meta.json')?:[];
$meta['genres']=array_keys(genre_map());$scope=browse_scope();$browseDoc=null;
$items=[];$titleItem=null;$personItem=null;$personRole='';$requestedPersonRole='';$personItems=[];$personCounts=[];$searchPeople=[];$related=[];$total=0;$pages=1;$heading='';$subheading='';$canonical=site_url('/');
$robots='index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';$httpError=null;$prevUrl=null;$nextUrl=null;$selectedGenre=null;$selectedYear=null;$query=substr(trim(query_string('q')),0,200);
$catalogFilters=null;$catalogDoc=null;
$homeMovies=[];$homeTv=[];$homeFeatured=[];$homeMovieTop=[];$homeTvTop=[];

if($route==='catalog'){
 $catalogFilters=catalog_filters();$catalogDoc=filtered_catalog($catalogFilters,$page);$items=$catalogDoc['items'];$total=$catalogDoc['total'];$pages=$catalogDoc['pages'];$heading=($catalogFilters['adaugate']??'')==='azi'?($catalogFilters['tip']==='tv'?'Seriale adăugate astăzi':($catalogFilters['tip']==='movies'?'Filme adăugate astăzi':'Filme și seriale adăugate astăzi')):'Explorează catalogul';$subheading='Alege după gen, an și rating.';$canonical=site_url(catalog_link($catalogFilters,$page));$robots='noindex,follow';
 }elseif(in_array($route,['home','movies','tv'],true)){
    $scope=$route==='home'?'all':($route==='tv'?'tv':'movies');$browseDoc=['recent'=>true];
    if($route==='home'){
        $homeRecent=array_slice(recent_collection('movies',60),0,60);$homeFeatured=array_slice($homeRecent,0,10);$items=$homeRecent;$total=count($items);$pages=1;
        $heading='Recent adăugate';$subheading='Alege următorul film pentru seara ta.';$canonical=site_url('/');
    }else{
        $collectionFilters=collection_filters($scope);$collectionDoc=recent_listing($scope,$collectionFilters,$page,60);$items=$collectionDoc['items'];$total=$collectionDoc['total'];$pages=$collectionDoc['pages'];
        $heading=$route==='movies'?'Filme':'Seriale';$subheading=$route==='movies'?'Alege următorul film pentru seara ta.':'Găsește povestea care te ține aproape, episod după episod.';
        $canonical=site_url(collection_url($scope,$collectionFilters,$page));
        if($page>1)$prevUrl=site_url(collection_url($scope,$collectionFilters,$page-1));if($page<$pages)$nextUrl=site_url(collection_url($scope,$collectionFilters,$page+1));
    }
}elseif($route==='genre'){
    $slug=trim(query_string('genre',''));$selectedGenre=resolve_genre_from_route($slug,$meta['genres']??[]);if(!$selectedGenre)$httpError=404;else{$data=category_document($selectedGenre,$scope,$page);if(!$data)$httpError=503;$items=(array)($data['items']??[]);$total=(int)($data['total']??0);$pages=max(1,(int)($data['pages']??1));$heading=genre_label($selectedGenre);$subheading='Titluri din zona '.genre_label($selectedGenre).', selectate direct din catalog și ordonate pentru explorare rapidă.';$base='/gen/'.genre_route_slug($selectedGenre).'/';$canonical=site_url(ltrim($page>1?$base.'pagina/'.$page.'/':$base,'/'));if($page>1)$prevUrl=site_url(ltrim($page-1>1?$base.'pagina/'.($page-1).'/':$base,'/'));if($page<$pages)$nextUrl=site_url(ltrim($base.'pagina/'.($page+1).'/','/'));}
}elseif($route==='year'){
    $selectedYear=(int)($_GET['year']??0);if($selectedYear<1880||$selectedYear>2100)$httpError=404;else{$data=year_data($selectedYear,$page);if(!$data)$httpError=503;$items=(array)($data['items']??[]);$total=(int)($data['total']??0);$pages=max(1,(int)($data['pages']??1));$heading='Filme și seriale din '.$selectedYear;$subheading='Producții lansate în '.$selectedYear.', reunite într-o singură pagină.';$base='/an/'.$selectedYear.'/';$canonical=site_url(ltrim($page>1?$base.'pagina/'.$page.'/':$base,'/'));if($page>1)$prevUrl=site_url(ltrim($page-1>1?$base.'pagina/'.($page-1).'/':$base,'/'));if($page<$pages)$nextUrl=site_url(ltrim($base.'pagina/'.($page+1).'/','/'));}
}elseif($route==='search'){
    $robots='noindex,follow';$all=$query!==''?search_titles($query):[];$searchPeople=$query!==''?search_people($query,12):[];
    if(preg_match('/^tt\d{5,12}$/i',$query)&&count($all)===1){header('Location: '.title_url($all[0]),true,302);exit;}
    if(preg_match('/^nm\d{5,12}$/i',$query)&&count($searchPeople)===1){header('Location: '.site_url(ltrim($searchPeople[0]['url'],'/')),true,302);exit;}
    $total=count($all);$peopleTotal=count($searchPeople);$pages=max(1,(int)ceil($total/(int)cfg('items_per_page')));$page=min($page,$pages);$items=array_slice($all,($page-1)*(int)cfg('items_per_page'),(int)cfg('items_per_page'));
    $heading=$query?'Rezultate pentru „'.$query.'”':'Caută în catalog';
    if($query){$parts=[];if($peopleTotal)$parts[]=ro_number($peopleTotal).' '.($peopleTotal===1?'persoană':'persoane');if($total)$parts[]=ro_number($total).' '.($total===1?'titlu':'titluri');$subheading=$parts?'În catalog: '.implode(' și ',$parts).'.':'Nu am găsit un titlu sau o persoană care să corespundă căutării.';}else{$subheading='Caută un film, un serial sau un nume din distribuție.';}
    $canonical=site_url('/cauta/').($query?'?q='.rawurlencode($query):'');
}elseif($route==='title'){
    $id=strtolower(trim(query_string('id','')));$requestedType=(query_string('type',''))==='tv'?'tv':'movie';$titleItem=load_title($id,$requestedType);if(!$titleItem){$httpError=catalog_fetch_failed()?503:404;$robots='noindex,follow';}else{$actualType=title_type($titleItem);if($actualType!==$requestedType){header('Location: '.title_url($titleItem),true,301);exit;}$canonical=title_url($titleItem);$related=related_titles($titleItem,8);$heading=title_name($titleItem);$subheading=editorial_summary($titleItem);}
}elseif($route==='person'){
    $id=strtolower(trim(query_string('id','')));
    if(!preg_match('/^nm\d{5,12}$/',$id)){$httpError=404;$robots='noindex,follow';}
    else{
        $personItem=load_person($id);
        if(!$personItem){$httpError=catalog_fetch_failed()?503:404;$robots='noindex,follow';}
        else{
            foreach(['actor','regizor','scenarist'] as $rk)$personCounts[$rk]=count((array)($personItem['roles'][$rk]??[]));
            $requestedPersonRole=person_role_key(query_string('person_role',''));$personRole=$requestedPersonRole;
            if($personRole===''||empty($personCounts[$personRole])){foreach(['actor','regizor','scenarist'] as $rk){if(!empty($personCounts[$rk])){$personRole=$rk;break;}}}
            if($requestedPersonRole!==''&&$personRole!==$requestedPersonRole&&$personRole!==''){header('Location: '.person_url($personItem,$personRole),true,301);exit;}
            if($personRole===''){$httpError=404;$robots='noindex,follow';}
            else{
                $allPersonItems=(array)($personItem['roles'][$personRole]??[]);
                $total=count($allPersonItems);$pages=max(1,(int)ceil($total/(int)cfg('items_per_page')));$page=min($page,$pages);
                $personItems=array_slice($allPersonItems,($page-1)*(int)cfg('items_per_page'),(int)cfg('items_per_page'));$items=$personItems;
                $heading=$personItem['name']??$id;$subheading=person_role_heading($personRole,$heading).'. Alege un titlu pentru poveste și distribuție.';
                $canonical=person_url($personItem,($requestedPersonRole!==''?$personRole:null),$page);
                if($page>1)$prevUrl=person_url($personItem,$personRole,$page-1);if($page<$pages)$nextUrl=person_url($personItem,$personRole,$page+1);
            }
        }
    }
}elseif($route==='legal'){
    $legalPage=query_string('legal');if(!isset($legalPages[$legalPage])){$httpError=404;$robots='noindex,follow';}else{[$heading,$subheading]=$legalPages[$legalPage];$canonical=site_url('/'.$legalPage.'/');}
}elseif($route==='credits'){$heading='Despre acest catalog';$subheading='Filme și seriale prezentate de Marian Bratu.';$canonical=site_url('/credite/');}else{$httpError=404;$robots='noindex,follow';}

if($route==='genre'){ $suffix='?tip='.rawurlencode($scope);$canonical.=$suffix;if($prevUrl)$prevUrl.=$suffix;if($nextUrl)$nextUrl.=$suffix;}
if($httpError===404)http_response_code(404);if($httpError===503){http_response_code(503);header('Retry-After: 300');}
$siteName=cfg('site_name');$seoTitle=$siteName.' | '.cfg('owner_name');$seoDesc='Catalog personal de filme și seriale: informații esențiale, distribuție, ratinguri, sezoane, episoade și o experiență construită pentru alegerea rapidă a unui titlu.';$ogImage=null;
if($route==='title'&&$titleItem){$name=title_name($titleItem);$typeLabel=type_label(title_type($titleItem));$year=$titleItem['year']??'';$seoTitle=$name.($year?' ('.$year.')':'').' – '.$typeLabel.' | '.$siteName;$seoDesc=editorial_summary($titleItem);$ogImage=site_url(item_poster_url($titleItem));}
elseif($route==='person'&&$personItem){$name=$personItem['name']??$personItem['nconst'];$seoTitle=$name.' – '.person_role_label($personRole).' | '.$siteName;$seoDesc=person_role_heading($personRole,$name).' – '.ro_number($total).' titluri disponibile în catalog.';$ogImage=site_url(person_image_url($personItem['nconst']));}
elseif($route==='movies'){$seoTitle='Filme'.($page>1?' – pagina '.$page:'').' | '.$siteName;$seoDesc='Descoperă filme organizate după gen, an și rating, cu pagini individuale și informațiile esențiale într-un format clar.';}
elseif($route==='tv'){$seoTitle='Seriale'.($page>1?' – pagina '.$page:'').' | '.$siteName;$seoDesc='Descoperă seriale cu sezoane, episoade, distribuție și informații esențiale într-un catalog rapid.';}
elseif($route==='genre'&&$selectedGenre){$seoTitle=genre_label($selectedGenre).($page>1?' – pagina '.$page:'').' | '.$siteName;$seoDesc='Filme și seriale din categoria '.genre_label($selectedGenre).', organizate pentru navigare rapidă.';}
elseif($route==='year'&&$selectedYear){$seoTitle='Filme și seriale din '.$selectedYear.($page>1?' – pagina '.$page:'').' | '.$siteName;$seoDesc='Catalog de filme și seriale lansate în '.$selectedYear.'.';}
elseif($route==='legal'&&$legalPage){$seoTitle=$heading.' | '.$siteName;$seoDesc=$subheading;}
elseif($route==='search'){$seoTitle=($query?'Căutare: '.$query:'Căutare').' | '.$siteName;}

$breadcrumbs=[['name'=>'Acasă','url'=>site_url('/')]];
if($route==='title'&&$titleItem){$breadcrumbs[]=['name'=>title_type($titleItem)==='tv'?'Seriale':'Filme','url'=>site_url(title_type($titleItem)==='tv'?'/seriale/':'/filme/')];$titleGenres=public_genres($titleItem['genres']??[]);if($titleGenres){$g0=$titleGenres[0];$breadcrumbs[]=['name'=>genre_label($g0),'url'=>site_url('gen/'.genre_route_slug($g0).'/')];}$breadcrumbs[]=['name'=>title_name($titleItem),'url'=>$canonical];}
elseif($route==='person'&&$personItem){$breadcrumbs[]=['name'=>$personItem['name']??$personItem['nconst'],'url'=>$canonical];}
elseif($route==='movies')$breadcrumbs[]=['name'=>'Filme','url'=>$canonical];elseif($route==='tv')$breadcrumbs[]=['name'=>'Seriale','url'=>$canonical];elseif($route==='genre'&&$selectedGenre)$breadcrumbs[]=['name'=>genre_label($selectedGenre),'url'=>$canonical];elseif($route==='year'&&$selectedYear)$breadcrumbs[]=['name'=>(string)$selectedYear,'url'=>$canonical];

$schemas=[];$schemas[]=['@context'=>'https://schema.org','@type'=>'WebSite','name'=>$siteName,'url'=>site_url('/'),'inLanguage'=>'ro-RO','publisher'=>['@type'=>'Person','name'=>cfg('owner_name')],'potentialAction'=>['@type'=>'SearchAction','target'=>site_url('/cauta/').'?q={search_term_string}','query-input'=>'required name=search_term_string']];
if(count($breadcrumbs)>1)$schemas[]=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>array_map(function($b,$i){return['@type'=>'ListItem','position'=>$i+1,'name'=>$b['name'],'item'=>$b['url']];},$breadcrumbs,array_keys($breadcrumbs))];
if($route==='title'&&$titleItem){$schema=['@context'=>'https://schema.org','@type'=>title_type($titleItem)==='tv'?'TVSeries':'Movie','name'=>title_name($titleItem),'url'=>$canonical,'sameAs'=>imdb_url($titleItem['imdb_id']),'identifier'=>['@type'=>'PropertyValue','propertyID'=>'IMDb','value'=>$titleItem['imdb_id']],'image'=>site_url(item_poster_url($titleItem)),'inLanguage'=>'ro-RO','description'=>$seoDesc];if(!empty($titleItem['year']))$schema['dateCreated']=(string)$titleItem['year'];if(!empty($titleItem['runtime']))$schema['duration']='PT'.(int)$titleItem['runtime'].'M';if(!empty($titleItem['genres']))$schema['genre']=array_map('genre_label',public_genres($titleItem['genres']));if(!empty($titleItem['directors']))$schema['director']=array_values(array_map(function($x){return person_schema_ref($x,'regizor');},(array)$titleItem['directors']));if(!empty($titleItem['cast']))$schema['actor']=array_values(array_map(function($x){return person_schema_ref($x,'actor');},array_slice((array)$titleItem['cast'],0,20)));if(!empty($titleItem['rating'])&&!empty($titleItem['votes']))$schema['aggregateRating']=['@type'=>'AggregateRating','ratingValue'=>(float)$titleItem['rating'],'ratingCount'=>(int)$titleItem['votes'],'bestRating'=>10,'worstRating'=>1];$schemas[]=$schema;}
elseif($route==='person'&&$personItem){$personSchema=['@context'=>'https://schema.org','@type'=>'Person','name'=>$personItem['name']??$personItem['nconst'],'url'=>$canonical,'sameAs'=>imdb_person_url($personItem['nconst']),'image'=>site_url(ltrim(person_image_url($personItem['nconst']),'/'))];if(!empty($personItem['birth_year']))$personSchema['birthDate']=(string)$personItem['birth_year'];if(!empty($personItem['death_year']))$personSchema['deathDate']=(string)$personItem['death_year'];$schemas[]=$personSchema;}
elseif($items)$schemas[]=['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$heading?:$siteName,'url'=>$canonical,'mainEntity'=>['@type'=>'ItemList','itemListElement'=>array_values(array_map(function($item,$i){return['@type'=>'ListItem','position'=>$i+1,'url'=>title_url($item),'name'=>title_name($item)];},array_slice($items,0,30),array_keys(array_slice($items,0,30))))]];
$cssVersion=@filemtime(__DIR__.'/assets/css/app.css')?:time();$jsVersion=@filemtime(__DIR__.'/assets/js/app.js')?:time();$refinementsVersion=@filemtime(__DIR__.'/assets/css/refinements.css')?:time();
?>
<!doctype html><html lang="ro"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=e($seoTitle)?></title>
<meta name="description" content="<?=e($seoDesc)?>"><meta name="robots" content="<?=e($robots)?>"><meta name="googlebot" content="<?=e($robots)?>"><link rel="canonical" href="<?=e($canonical)?>"><link rel="alternate" hreflang="ro" href="<?=e($canonical)?>"><link rel="alternate" hreflang="x-default" href="<?=e($canonical)?>">
<?php if($prevUrl):?><link rel="prev" href="<?=e($prevUrl)?>"><?php endif;?><?php if($nextUrl):?><link rel="next" href="<?=e($nextUrl)?>"><?php endif;?>
<meta property="og:locale" content="ro_RO"><meta property="og:type" content="<?=($route==='title'&&$titleItem)?(title_type($titleItem)==='tv'?'video.tv_show':'video.movie'):'website'?>"><meta property="og:site_name" content="<?=e($siteName)?>"><meta property="og:title" content="<?=e($seoTitle)?>"><meta property="og:description" content="<?=e($seoDesc)?>"><meta property="og:url" content="<?=e($canonical)?>"><?php if($ogImage):?><meta property="og:image" content="<?=e($ogImage)?>"><?php endif;?>
<meta name="twitter:card" content="<?=$ogImage?'summary_large_image':'summary'?>"><meta name="twitter:title" content="<?=e($seoTitle)?>"><meta name="twitter:description" content="<?=e($seoDesc)?>"><?php if($ogImage):?><meta name="twitter:image" content="<?=e($ogImage)?>"><?php endif;?>
<meta name="theme-color" content="#000000"><link rel="icon" href="/assets/img/favicon.svg?v=14" type="image/svg+xml"><link rel="preconnect" href="https://images.metahub.space" crossorigin><link rel="dns-prefetch" href="//images.metahub.space"><link rel="dns-prefetch" href="//vsembed.ru"><link rel="stylesheet" href="/assets/css/app.css?v=<?=e($cssVersion)?>"><link rel="stylesheet" href="/assets/css/refinements.css?v=<?=e($refinementsVersion)?>"><link rel="stylesheet" href="/assets/css/experience.css?v=14"><link rel="stylesheet" href="/assets/css/catalog-sync.css?v=<?=e(@filemtime(__DIR__.'/assets/css/catalog-sync.css')?:2)?>">
<?php foreach($schemas as $schema):?><script type="application/ld+json" nonce="<?=e($cspNonce)?>"><?=safe_json($schema)?></script><?php endforeach;?>
</head><body class="cinema-v9 cinema-v10 cinema-v11 cinema-v12 cinema-v13" data-version="<?=e(cfg('version'))?>" data-suggest-url="/api.php?action=suggest&q=" data-provider-origin="<?=e(parse_url(cfg('provider_base_url'),PHP_URL_SCHEME).'://'.parse_url(cfg('provider_base_url'),PHP_URL_HOST))?>">
<a class="skip-link" href="#mainContent">Sari la conținut</a>
<?php include __DIR__.'/includes/header.php'; ?>


<main id="mainContent"><div class="wrap">
<?php if(count($breadcrumbs)>1&&!$browseDoc):?><nav class="breadcrumbs" aria-label="Breadcrumb"><?php foreach($breadcrumbs as $i=>$b):?><?php if($i):?><span class="sep">/</span><?php endif;?><?php if($i<count($breadcrumbs)-1):?><a href="<?=e($b['url'])?>"><?=e($b['name'])?></a><?php else:?><span><?=e($b['name'])?></span><?php endif;?><?php endforeach;?></nav><?php endif;?>
<?php if($httpError):?><section class="content"><div class="status"><h1><?=$httpError===404?'Nu am găsit această pagină':'Pagina nu se poate încărca acum'?></h1><p><?=$httpError===404?'Poți căuta un titlu sau un nume din distribuție.':'Încearcă din nou peste câteva momente sau explorează catalogul.'?></p><a class="primary-btn" href="/">Înapoi la catalog</a></div></section>
<?php elseif($route==='title'&&$titleItem):?>
<?php include __DIR__.'/includes/title-view.php'; ?>
<?php elseif($route==='person'&&$personItem):?>
<section class="content person-page" data-person-id="<?=e($personItem['nconst'])?>" data-person-name="<?=e($personItem['name']??$personItem['nconst'])?>" data-person-url="<?=e(person_path($personItem,$personRole))?>" data-person-image="<?=e(person_image_url($personItem['nconst']))?>" data-person-role="<?=e($personRole)?>">
  <section class="person-hero">
    <div class="person-portrait"><img referrerpolicy="no-referrer" src="<?=e(person_image_url($personItem['nconst']))?>" alt="<?=e($personItem['name']??'Persoană')?>" width="360" height="480" loading="eager" fetchpriority="high" data-fallback="/assets/img/person-placeholder.svg"></div>
    <div class="person-main"><span class="title-eyebrow">FILMOGRAFIE ÎN CATALOG</span><h1><?=e($personItem['name']??$personItem['nconst'])?></h1>
      <p class="person-intro"><?=e($subheading)?></p>
      
      <div class="person-facts"><?php if(!empty($personItem['birth_year'])):?><span><b>Născut(ă)</b><?=e($personItem['birth_year'])?></span><?php endif;?><?php if(!empty($personItem['death_year'])):?><span><b>Decedat(ă)</b><?=e($personItem['death_year'])?></span><?php endif;?><?php if(!empty($personItem['professions'])):?><span><b>Activitate</b><?=e(implode(' · ',array_map('person_profession_label',array_slice((array)$personItem['professions'],0,4))))?></span><?php endif;?></div>
    </div>
  </section>
  <nav class="person-role-tabs" aria-label="Roluri în catalog"><?php foreach(['actor','regizor','scenarist'] as $rk):$cnt=(int)($personCounts[$rk]??0);if(!$cnt)continue;?><a class="person-role-tab<?=$rk===$personRole?' active':''?>" href="<?=e(person_path($personItem,$rk))?>"><span><?=e(person_role_label($rk))?></span><strong><?=e(ro_number($cnt))?></strong></a><?php endforeach;?></nav>
  <section class="person-filmography"><div class="section-head"><div><span class="section-kicker"><?=e(person_role_label($personRole))?></span><h2><?=e(person_role_heading($personRole,$personItem['name']??$personItem['nconst']))?></h2><p><?=e(ro_number($total))?> titluri în catalog.</p></div></div><?php if($items):include __DIR__.'/includes/grid.php';else:?><div class="status">Nu există titluri disponibile pentru acest rol.</div><?php endif;?><?php if($pages>1):?><nav class="pagination" aria-label="Paginare filmografie"><?php foreach(pagination_window($page,$pages) as $p){if($p==='…'){echo'<span class="page-dots">…</span>';continue;}$href=person_path($personItem,$personRole,$p);echo'<a class="page-link'.($p==$page?' active':'').'" href="'.e($href).'">'.(int)$p.'</a>';}?></nav><?php endif;?></section>
</section>
<?php else:?>
  <?php if($route==='legal'&&$legalPage):include __DIR__.'/includes/legal.php';?>
  <?php elseif($route==='credits'):?>
    <section class="content"><h1>Despre acest catalog</h1><p>Filme și seriale prezentate de Marian Bratu. Aici găsești informații despre titluri, distribuție, sezoane și episoade.</p><?php if(tmdb_enabled()):?><section class="credits-panel"><h2>Credite pentru date și imagini</h2><a href="https://www.themoviedb.org" rel="noopener"><img referrerpolicy="no-referrer" class="credit-logo" src="https://www.themoviedb.org/assets/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg" alt="TMDB" width="130" height="50" loading="lazy"></a><p>This product uses the TMDB API but is not endorsed or certified by TMDB.</p></section><?php endif;?></section>
  <?php elseif($route==='catalog'):include __DIR__.'/includes/catalog-view.php';?>
  <?php elseif($browseDoc):?><?php include __DIR__.'/includes/browse.php'; ?>
  <?php else:?>
    <section class="content listing-intro"><div class="section-head"><div><h1><?=e($heading)?></h1><p><?=e($subheading)?></p></div></div></section>
    <?php if($route!=='search'):?><section class="toolbar" aria-label="Filtre catalog"><select data-go aria-label="Alege genul"><option data-url="" value="">Alege genul</option><?php foreach((array)($meta['genres']??[]) as $g):?><option data-url="/gen/<?=e(genre_route_slug($g))?>/" value="<?=e($g)?>"<?=$selectedGenre===$g?' selected':''?>><?=e(genre_label($g))?></option><?php endforeach;?></select><select data-go aria-label="Alege anul"><option data-url="" value="">Alege anul</option><?php foreach(array_slice((array)($meta['years']??[]),0,120) as $y):?><option data-url="/an/<?=(int)$y?>/" value="<?=(int)$y?>"<?=$selectedYear===(int)$y?' selected':''?>><?=(int)$y?></option><?php endforeach;?></select><a class="text-btn" href="<?=e(catalog_link(['tip'=>$scope==='today'?'all':$scope,'gen'=>$selectedGenre??'','de'=>$selectedYear?:1880,'pana'=>$selectedYear?:2100]))?>">Toate filtrele</a><div class="toolbar-count"><?=$total?e(ro_number($total)).' titluri în catalog':''?></div></section><?php endif;?>
    <?php if($route==='search'&&$searchPeople):?><section class="content search-people"><div class="section-head"><div><span class="section-kicker">Persoane</span><h2>Actori, regizori și scenariști</h2><p>Rezultate care au filmografie disponibilă în catalog.</p></div></div><div class="cast-grid"><?php foreach($searchPeople as $person):?><article class="cast-card"><a class="cast-person-link" href="<?=e($person['url'])?>"><div class="cast-photo"><img referrerpolicy="no-referrer" src="<?=e($person['image'])?>" alt="<?=e($person['name'])?>" loading="lazy" decoding="async" data-fallback="/assets/img/person-placeholder.svg"></div><div class="cast-copy"><strong><?=e($person['name'])?></strong><span><?=e($person['hint']?:'Filmografie disponibilă în catalog')?></span></div></a></article><?php endforeach;?></div></section><?php endif;?>
    <section class="content"><?php if($items):include __DIR__.'/includes/grid.php';elseif(!($route==='search'&&$searchPeople)):?><div class="status">Nu există rezultate pentru selecția curentă.</div><?php elseif($route==='search'):?><div class="section-head"><div><span class="section-kicker">Titluri</span><h2>Filme și seriale</h2><p>Nu există titluri cu numele căutat; rezultatele de persoane sunt afișate mai sus.</p></div></div><?php endif;?><?php if($pages>1):?><nav class="pagination" aria-label="Paginare"><?php foreach(pagination_window($page,$pages) as $p){if($p==='…'){echo'<span class="page-dots">…</span>';continue;}if($route==='movies'||$route==='tv')$href=listing_path($route==='tv'?'tv':'movie',$p);elseif($route==='genre'){$base='/gen/'.genre_route_slug($selectedGenre).'/';$href=($p>1?$base.'pagina/'.$p.'/':$base).'?tip='.rawurlencode($scope);}elseif($route==='year'){$base='/an/'.$selectedYear.'/';$href=$p>1?$base.'pagina/'.$p.'/':$base;}else$href='/cauta/?q='.rawurlencode($query).'&page='.$p;echo'<a class="page-link'.($p==$page?' active':'').'" href="'.e($href).'">'.(int)$p.'</a>';}?></nav><?php endif;?></section>
  <?php endif;?>
<?php endif;?></div></main>
<nav class="mobile-bottom-bar" aria-label="Navigare mobilă"><?php foreach([['home','/','home','Acasă'],['movies','/filme/','film','Filme'],['tv','/seriale/','tv','Seriale']] as $entry):?><a href="<?=$entry[1]?>"<?=$route===$entry[0]?' aria-current="page"':''?>><?=icon($entry[2])?><span><?=$entry[3]?></span></a><?php endforeach;?><button type="button" data-open-watchlist><?=icon('heart')?><span>Lista mea</span></button><a href="/cauta/"<?=$route==='search'?' aria-current="page"':''?>><?=icon('search')?><span>Caută</span></a></nav>
<footer class="site-footer"><div class="wrap">
  <div class="footer-row"><div class="catalog-footer-stats"><?=catalog_daily_footer()?></div><nav class="footer-links" aria-label="Navigare subsol"><a href="/filme/">Filme</a><a href="/seriale/">Seriale</a><a href="#categoryMenu" data-open-menu="categoryMenu">Genuri</a><a href="/catalog/">Ani și filtre</a><a href="/credite/">Despre catalog și credite</a></nav></div>
  <div class="footer-bottom"><span>© <?=date('Y')?> Marian Bratu</span><nav aria-label="Pagini legale"><?php foreach($legalPages as $slug=>$copy):?><a href="/<?=e($slug)?>/"><?=e($copy[0])?></a><?php endforeach;?><button type="button" data-privacy-open>Preferințe cookie</button></nav></div>
  <p class="footer-disclosure">Playerele video sunt furnizate de operatori externi. Fișierele video nu sunt găzduite pe acest server. Pentru sesizări, folosește pagina Drepturi de autor.</p>
</div></footer>
<button class="catalog-back-top" id="catalogBackTop" type="button" aria-label="Înapoi sus" title="Înapoi sus" hidden><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 19V5m-7 7 7-7 7 7"/></svg></button>
<div id="watchlistDialog" class="watchlist-overlay" role="dialog" aria-modal="true" aria-labelledby="watchlistHeading" hidden><div class="watchlist-panel"><div class="watchlist-head"><div><span class="section-kicker">Colecția ta personală</span><h2 id="watchlistHeading">Lista mea</h2></div><button type="button" class="watchlist-close icon-btn" data-close-watchlist aria-label="Închide"><?=icon('close')?></button></div><p class="watchlist-help">Titlurile preferate sunt memorate doar în browserul tău, atunci când ai permis stocarea funcțională.</p><div id="watchlistItems" class="watchlist-items"></div></div></div>
<?php include __DIR__.'/includes/privacy-controls.php'; ?>
<div class="player-modal" id="playerModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="playerModalTitle"><div class="player-panel" role="document"><div class="player-head"><div><div class="player-title" id="playerModalTitle">Player</div></div><button class="player-close" id="playerClose" type="button" aria-label="Închide"><?=icon('close')?></button></div><div class="player-frame"><div class="player-loading" id="playerLoading" role="status"><span class="spinner"></span><p>Se încarcă playerul…</p></div><iframe scrolling="no" title="Player video" id="playerFrame" src="about:blank" allow="autoplay; fullscreen; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="origin"></iframe></div><div class="player-note"><span>Subtitrări: meniul CC</span><button class="text-btn" type="button" id="playerRetry">Reîncarcă playerul</button></div><div class="player-timeout" id="playerTimeout" hidden role="status">Încărcarea durează mai mult. Poți reîncerca redarea.</div></div></div>
<div id="toast" class="toast" role="status" aria-live="polite" hidden></div>
<script src="/assets/js/privacy.js?v=<?=e($jsVersion)?>" defer></script>
<script src="/assets/js/app.js?v=<?=e($jsVersion)?>" defer></script><script src="/assets/js/catalog-sync-ui.js?v=<?=e(@filemtime(__DIR__.'/assets/js/catalog-sync-ui.js')?:2)?>" defer></script></body></html>
