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
