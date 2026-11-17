# Filme Bratu Marian — Cinema Gold

Sursa PHP a interfeței instalate la filme.bratu-marian.com. Necesită Apache cu mod_rewrite, PHP 8.1+ cu JSON, cURL și zlib. APCu este opțional. Datele sunt generate de workflow-ul catalogului și sunt separate de interfață.

## Instalare

Copiază conținutul acestui director, inclusiv `.htaccess`, în rădăcina publică a subdomeniului. Păstrează directorul `data/` și configurația existentă pe server la actualizarea temei. Datele locale sincronizate nu sunt incluse în acest director; generatorul din `scripts/` și workflow-urile existente le întrețin.

`config.php` conține doar valori publice. Cheile opționale se configurează în `config.local.php` (ignorat de Git); pentru TMDb pornește de la `config.local.example.php`. Nu suprascrie configurația existentă de pe server la instalarea unei actualizări vizuale.

## Identitate vizuală

- `assets/img/logo-cinema.svg`: logo vectorial Bratu Marian.
- `assets/img/favicon-cinema.svg`: simbolul pentru tab.
- `assets/img/cinema-banner.webp`: bannerul furnizat de proprietar, optimizat pentru web.
- `assets/css/cinema-gold.css`: baza vizuală cu accente aurii.
- `assets/css/cinema-premiere.css`: fundaluri pe toată lățimea și suport reduced-motion.
- `assets/css/cinema-compact.css`: carduri cinematografice compacte, cu scenă panoramică și poster mic; 264 px pe desktop, 225–238 px pe telefon.

Pagina principală afișează direct grila sub banner, fără caruselul mare. Fiecare pagină are un fundal decorativ: imaginea titlului pentru filme și seriale, prima imagine disponibilă în liste, respectiv bannerul ca rezervă. Straturile întunecate protejează lizibilitatea. Antetul rămâne în fluxul paginii pentru a nu acoperi informațiile.

Descrierile românești verificate sunt în `editorial/overrides.json` la rădăcina repository-ului. `includes/editorial.php` oferă și variante pe server pentru titlurile verificate. Restul descrierilor păstrează limba sursei; nu sunt inventate sinopsisuri sau recenzii personale.

## Pagini și SEO

Paginile virtuale sunt randate în PHP și au adrese individuale, canonical, descriere, Open Graph și JSON-LD Movie/TVSeries/BreadcrumbList. `sitemap.xml` este un index de sitemapuri; fiecare parte are maximum 45.000 URL-uri. `robots.txt` indică indexul. Căutările și combinațiile de filtre rămân noindex pentru a evita indexarea duplicatelor. Indexarea și poziția în rezultate nu sunt garantate.

## Player

Filmele folosesc ID-ul IMDb. Seriale: ID IMDb + sezon + episod. Subtitrarea cerută este `ro`. Iframe-ul se încarcă după acordul pentru media externă. Linkul „Deschide la furnizor” este o alternativă când iframe-ul nu funcționează. Conectarea și redarea depind de furnizor; testul din browserul de verificare a primit „refused to connect” de la vsembed.ru.

## Verificări la actualizarea din 13 noiembrie 2026

- 39 fișiere PHP validate sintactic cu php-parser (runtime-ul local nu include PHP).
- JavaScript verificat cu `node --check`.
- 18 teste Python ale catalogului trecute.
- Verificări live pentru prima pagină, film, serial, rutarea sezon/episod și preferințele media.
- Copie de revenire păstrată pe găzduire înaintea instalării.

## Localizare TMDb din GitHub Secrets

Workflow-ul catalogului citește `TMDB_API_KEY` sau `TMDB_API_TOKEN` / `TMDB_READ_ACCESS_TOKEN` din GitHub Actions Secrets. Nu publica cheia în cod și nu o pune în JavaScript. `hosting/api.php` este un endpoint al aplicației, nu o cheie API TMDb.

`scripts/tmdb_localization.py` verifică traducerile cu limba `ro` din endpointul TMDb `translations`, păstrează cache-ul în directorul existent al catalogului și procesează până la 400 de titluri neactualizate pe execuție. Titlurile recente au prioritate. Titlurile IMDb românești existente și textele editoriale manuale au prioritate. Nu etichetează sinopsisuri englezești drept românești și nu inventează traduceri.

Datele sunt înglobate în indexuri, feeduri și paginile virtuale înainte de publicare; cheia rămâne în GitHub. `data/localization-status.json` publică numai contoare și starea configurării. Dacă secretul lipsește, catalogul funcționează cu datele existente; traducerea integrală nu este garantată. După adăugarea secretului, rulează workflow-ul **Build movie catalog data** pentru prima actualizare.

Găzduirea poate folosi separat aceleași variabile de mediu PHP sau un fișier privat `config.local.php`, dar un secret GitHub nu este transmis automat procesului PHP.
