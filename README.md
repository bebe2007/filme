<h1>Filme Online</h1>

<p>
Această pagină afișează un player video fullscreen folosind un ID IMDb introdus direct în link.
</p>

<hr>

<h2>Link-ul paginii</h2>

<p>
Pagina principală este:
</p>

<pre>
https://bebe2007.github.io/filme/
</pre>

<hr>

<h2>Cum se adaugă un film</h2>

<p>
Pentru a deschide un film, trebuie să adaugi parametrul <strong>?id=</strong> urmat de ID-ul IMDb al filmului.
</p>

<p>
Formatul corect este:
</p>

<pre>
https://bebe2007.github.io/filme/?id=ID_IMDB
</pre>

<p>
Exemplu:
</p>

<pre>
https://bebe2007.github.io/filme/?id=tt19759204
</pre>

<hr>

<h2>De unde iei ID-ul IMDb</h2>

<ol>
    <li>Intră pe <strong>IMDb</strong>: https://www.imdb.com</li>
    <li>Caută filmul sau serialul dorit.</li>
    <li>Deschide pagina filmului.</li>
    <li>Uită-te în bara de adresă a browserului.</li>
</ol>

<p>
Exemplu de link IMDb:
</p>

<pre>
https://www.imdb.com/title/tt19759204/
</pre>

<p>
ID-ul IMDb este partea care începe cu <strong>tt</strong>:
</p>

<pre>
tt19759204
</pre>

<p>
Acest ID se pune în linkul paginii:
</p>

<pre>
https://bebe2007.github.io/filme/?id=tt19759204
</pre>

<hr>

<h2>Cum se adaugă un episod dintr-un serial</h2>

<p>
Pentru seriale se folosește acest format:
</p>

<pre>
https://bebe2007.github.io/filme/?id=ID_IMDB&type=tv&season=NUMAR_SEZON&episode=NUMAR_EPISOD
</pre>

<p>
Exemplu:
</p>

<pre>
https://bebe2007.github.io/filme/?id=tt0944947&type=tv&season=1&episode=1
</pre>

<p>
Explicație:
</p>

<ul>
    <li><strong>id=tt0944947</strong> este ID-ul IMDb al serialului.</li>
    <li><strong>type=tv</strong> spune paginii că este serial.</li>
    <li><strong>season=1</strong> înseamnă sezonul 1.</li>
    <li><strong>episode=1</strong> înseamnă episodul 1.</li>
</ul>

<hr>

<h2>Exemple rapide</h2>

<h3>Film</h3>

<pre>
https://bebe2007.github.io/filme/?id=tt19759204
</pre>

<h3>Serial - Sezonul 1, Episodul 1</h3>

<pre>
https://bebe2007.github.io/filme/?id=tt0944947&type=tv&season=1&episode=1
</pre>

<h3>Serial - Sezonul 2, Episodul 5</h3>

<pre>
https://bebe2007.github.io/filme/?id=tt0944947&type=tv&season=2&episode=5
</pre>

<hr>

<h2>Reguli importante</h2>

<ul>
    <li>ID-ul IMDb trebuie să înceapă obligatoriu cu <strong>tt</strong>.</li>
    <li>Exemplu corect: <strong>tt19759204</strong></li>
    <li>Exemplu greșit: <strong>19759204</strong></li>
    <li>Pentru filme se folosește doar <strong>?id=tt...</strong></li>
    <li>Pentru seriale trebuie adăugat și <strong>type=tv</strong>, plus sezonul și episodul.</li>
</ul>

<hr>

<h2>Subtitrare</h2>

<p>
Playerul cere automat subtitrarea în limba română folosind:
</p>

<pre>
ds_lang=ro
</pre>

<p>
Dacă sursa video are subtitrare în română, aceasta va fi selectată automat.
Dacă nu există subtitrare română la sursă, pagina nu o poate crea automat.
</p>

<h2>Autor</h2>

<p>
Proiect creat de <strong>Bratu Marian</strong>.
</p>


<hr>

<h2>Metadate editoriale pentru filme.bratu-marian.com</h2>

<p>Generatorul catalogului păstrează un strat editorial separat în <code>editorial/overrides.json</code>. Cheia fiecărei intrări este ID-ul IMDb, iar câmpurile acceptate sunt <code>description</code>, <code>poster</code>, <code>background</code>, <code>trailer_youtube</code>, <code>country</code>, <code>language</code> și <code>released</code>.</p>

<p>Aceste valori au prioritate pe site față de metadatele editoriale de rezervă și nu sunt șterse de reconstrucția zilnică a catalogului. Datele factuale IMDb (an, durată, genuri, rating, distribuție, regie și scenariu) rămân generate din seturile IMDb.</p>

<p>Exemplu:</p>

<pre>
{
  "tt1234567": {
    "description": "Descriere editorială în limba română.",
    "trailer_youtube": "VIDEO_ID_YOUTUBE",
    "poster": "https://exemplu.ro/poster.jpg"
  }
}
</pre>
