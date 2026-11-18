<?php
require __DIR__.'/includes/catalog.php';
function ensure_discover($condition,$message){if(!$condition)throw new RuntimeException($message);}
$now=new DateTimeImmutable('2026-11-18T15:00:00+02:00');
function movie_fixture($id,$rating,$date,$type='movie',$votes=10){return ['imdb_id'=>$id,'rating'=>$rating,'site_added_at'=>$date,'type'=>$type,'votes'=>$votes];}
$rows=[movie_fixture('today-low',6,'2026-11-18T01:00:00+02:00'),movie_fixture('today-high',9,'2026-11-18T11:00:00+02:00'),movie_fixture('older-high',10,'2026-11-17T10:00:00+02:00'),movie_fixture('older-mid',8,'2026-11-16T10:00:00+02:00'),movie_fixture('older-low',7,'2026-11-15T10:00:00+02:00'),movie_fixture('outside',10,'2026-11-01T10:00:00+02:00'),movie_fixture('series',10,'2026-11-18T10:00:00+02:00','tv'),movie_fixture('future',10,'2026-11-19T10:00:00+02:00'),movie_fixture('unrated',0,'2026-11-18T10:00:00+02:00')];
$special=movie_fixture('special',10,'2026-11-18T10:00:00+02:00');$special['title_kind']='special';$rows[]=$special;
$selection=select_discover_movies($rows,$now);
ensure_discover(array_column($selection,'imdb_id')===['older-high','today-high','older-mid','older-low','today-low'],'Daily movies must survive fallback, with five results ordered by rating.');
$today=[];foreach([5,6,7,8,9,10] as $n)$today[]=movie_fixture('daily-'.$n,$n,'2026-11-18T10:00:00+02:00');
ensure_discover(array_column(select_discover_movies(array_merge($rows,$today),$now),'rating')===[10,9,9,8,7],'When today has five rated films, older films must be excluded.');
$boundary=[movie_fixture('romanian-today',8,'2026-11-17T22:30:00Z'),movie_fixture('utc-prior-day',9,'2026-11-17T20:00:00Z')];
ensure_discover(select_discover_movies($boundary,$now,1)[0]['imdb_id']==='romanian-today','Day boundaries must use Romanian time.');
echo "Daily discovery selection passed.\n";
