# Cinema Catalog pentru Blogger — v1.1.0

Pachetul include tema Blogger XML și generatorul de date statice pentru GitHub Pages.

## Funcționalități

- 70 de filme/seriale pe pagină;
- fiecare titlu are URL propriu în Blogger, de forma `/?id=tt0120812&type=movie`;
- cardul deschide pagina titlului;
- pagina titlului afișează numai informații existente în date: poster, an, durată, genuri, rating, voturi, regie, scenariu și distribuție principală;
- buton separat **Deschide playerul**, cu player modal;
- serialele afișează sezoanele și episoadele în pagină;
- clickul pe episod deschide episodul în modal;
- `ds_lang=ro` setează româna ca limbă preferată dacă pista există;
- nu sunt inventate synopsis-uri, biografii sau valori lipsă.

## GitHub Pages

Workflow-ul `.github/workflows/update-catalog.yml` generează datele în `public/data` și le publică prin GitHub Pages.

Pentru acest repository, URL-ul de date este:

`https://bebe2007.github.io/filme/data`

În tema Blogger, valoarea trebuie să fie:

`DATA_BASE_URL: 'https://bebe2007.github.io/filme/data',`

## Limitare Blogger

Tema oferă fiecărui titlu o adresă unică de tip query-string. Blogger nu permite unei teme XML să creeze singură sute de mii de postări/permalink-uri native. Pentru permalink-uri native ar fi necesar un publisher separat prin Blogger API.
