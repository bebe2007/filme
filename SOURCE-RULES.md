# Reguli de integritate a datelor

1. Un titlu intră în catalogul Filme/Seriale numai dacă IMDb ID-ul apare în lista completă a providerului.
2. `ds_lang=ro` înseamnă preferință de limbă, nu confirmarea existenței unei subtitrări românești.
3. Un episod este afișat numai dacă perechea `serial IMDb ID + sezon + episod` apare în lista de episoade a providerului și poate fi corelată cu `title.episode`.
4. „Distribuție principală” provine din `title.principals`; nu este prezentată drept distribuție completă.
5. Regizorii/scenariștii provin din `title.crew`.
6. Numele persoanelor provin din `name.basics`.
7. Titlul românesc este completat numai din AKA-uri cu regiune/limbă românească.
8. Calitatea, posterul providerului și `time_added` sunt preluate din endpointul `/info/...` atunci când acesta le furnizează.
9. Tema nu inventează descrieri sau valori lipsă.
10. Datele editoriale suplimentare ale paginii publice (descriere, poster alternativ, fundal și trailer) pot fi rezolvate după IMDb ID din Cinemeta/Stremio și cache-uite local de site. Aceste date nu decid apartenența titlului la catalog și nu înlocuiesc valorile IMDb existente.
11. Dacă sursa editorială nu furnizează un câmp, site-ul nu inventează sinopsis, trailer, poster sau altă informație lipsă.
