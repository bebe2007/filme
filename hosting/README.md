# Filme Bratu Marian — aplicația PHP

Apache/mod_rewrite, PHP 8.1+, JSON, cURL și zlib. APCu și mbstring sunt opționale. Datele sunt generate separat de GitHub Actions; interfața folosește un singur CSS (`assets/css/site.css`) și scriptul versionat `assets/js/app-v15.js`.

## Instalare și revenire

Rulează `python scripts/package_hosting.py release.zip`. Arhiva exclude datele, cache-ul, testele și orice configurație privată. Pe găzduire, creează întâi o arhivă a aplicației curente în afara `public_html`, apoi extrage release.zip în `public_html`. Păstrează `config.php`, `config.local.php` și `data/` existente. Pentru instalare nouă copiază și stubul public `config.php`. Revenire: extrage backupul anterior peste aplicație; datele nu se schimbă. Nu servi arhive cu configurații private din rădăcina publică.

`--preview` pregătește o variantă `v16-preview/index.php` care citește datele locale și configurația curentă; nu este destinată indexării. API-ul de preview este separat. După verificare, mută directorul de preview și arhiva în afara rădăcinii publice.

## TMDb: cele două configurații

| Destinație | Valoare | Scop |
|---|---|---|
| GitHub repository → Settings → Secrets and variables → Actions → Secrets | `TMDB_API_KEY` (v3) **sau** `TMDB_API_TOKEN` (Read Access Token, fără prefix Bearer) | Generarea catalogului, imaginilor și traducerilor |
| Găzduire: `public_html/config.local.php` | `return ['tmdb_api_key' => 'valoarea privată'];` sau cheia `tmdb_api_token` | Completarea metadatelor în paginile PHP |

Nu introduce cheia în fișiere GitHub, JavaScript, HTML sau URL-uri publice. Configurația locală este ignorată de Git și protejată de Apache. GitHub Secrets nu sunt transmise automat către PHP. Configurația veche de pe găzduire este încărcată peste valorile publice implicite, apoi configurația locală are prioritate.

După salvarea secretului, pornește Actions → **Build movie catalog data** → **Run workflow**, main, 2000 titluri și 200 sezoane. Procesarea continuă în loturi; titlurile neverificate au prioritate față de cache-ul expirat. `data/localization-status.json` arată progresul, inclusiv numărul rămas. O execuție nu acoperă întregul catalog. Nu se garantează existența traducerilor românești pentru toate titlurile.

Traducerile verificate `ro` au prioritate; textele editoriale manuale și titlurile IMDb românești sunt păstrate. Imaginile se recuperează și când lipsește traducerea. Sezoanele TMDb completează numai episoadele deja prezente la furnizor. Nu adaugă episoade disponibile fictiv. Colecțiile, distribuția, certificările și recomandările folosesc metadate existente; informațiile lipsă nu sunt inventate.

## Cache și imagini

`cache/` păstrează doar JSON/text și URL-uri; niciun film sau fișier imagine. Scriere atomică, acces web blocat, directoare 0700 și fișiere 0600. Cache-ul poate fi eliminat pentru regenerare. Sursele de imagini au încercări limitate și rezervă SVG locală. O imagine absentă din toate sursele rămâne înlocuită cu această rezervă.

## Redare și subtitrări

Playerul extern se încarcă după acordul pentru media externă. Disponibilitatea redării și a subtitrării depinde de furnizor. `ro` este o preferință, nu dovadă de disponibilitate. Interfața afișează „neconfirmată” în absența unei verificări. Istoricul și favoritele se salvează doar cu acord în browserul vizitatorului.

## Verificări

`python scripts/validate_source.py` verifică toate fișierele PHP, JavaScript inclusiv scripturi încorporate, SVG și comportamente PHP. `python -m unittest discover -s scripts -p 'test_*.py'` verifică generatorul, localizarea și integritatea catalogului. Workflow-ul de validare rulează aceleași verificări. Testarea în browser rămâne necesară înainte de publicare.

## Interfață v16

Meniurile folosesc SVG. „De descoperit” afișează cele mai bine cotate cinci lungmetraje adăugate în ziua curentă (ora României); dacă nu sunt cinci filme cu notă, completează din ultimele șapte zile. La egalitate decide numărul de voturi, apoi data adăugării. Selecția se schimbă după actualizarea catalogului și are cache de maximum două minute. Nu presupune că anul lansării este data adăugării.

Cardurile au poster 2:3, nota IMDb în stânga și anul cu icon calendar în dreapta. Durata rămâne în detaliile filmului, nu pe carduri. Titlurile complete determină spațiul comun pentru text din fiecare grilă. Nu există rotire automată a selecției; funcționează cu mouse, atingere și tastele săgeți/Home/End. Playerul aplicației nu are link către furnizor.
