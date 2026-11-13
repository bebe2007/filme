# Filme Bratu Marian — Cinema Gold

Sursa PHP a interfeței instalate la filme.bratu-marian.com. Necesită Apache cu mod_rewrite, PHP 8.1+ cu JSON, cURL și zlib. APCu este opțional. Datele sunt generate de workflow-ul catalogului și sunt separate de interfață.

## Instalare

Copiază conținutul acestui director, inclusiv `.htaccess`, în rădăcina publică a subdomeniului. Păstrează directorul `data/` și configurația existentă pe server la actualizarea temei. Datele locale sincronizate nu sunt incluse în acest director; generatorul din `scripts/` și workflow-urile existente le întrețin.

`config.php` conține doar valori publice. Cheile opționale se configurează în `config.local.php` (ignorat de Git); pentru TMDb pornește de la `config.local.example.php`. Nu suprascrie configurația existentă de pe server la instalarea unei actualizări vizuale.

## Identitate vizuală

- `assets/img/logo-cinema.svg`: logo vectorial Bratu Marian.
- `assets/img/favicon-cinema.svg`: simbolul pentru tab.
- `assets/img/cinema-banner.webp`: bannerul furnizat de proprietar, optimizat pentru web.
- `assets/css/cinema-gold.css`: tema responsive cu accente aurii și suport reduced-motion.

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
