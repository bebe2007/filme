import json,sqlite3,tempfile,unittest
from pathlib import Path
from tmdb_localization import TMDbClient,enrich_catalog

class DocumentClient(TMDbClient):
    def __init__(self,translations,kind='movie'):
        super().__init__(key='test-key',token='');self.docs=translations;self.kind=kind
    def get(self,path,**params):
        if path.startswith('find/'):
            return {('tv_results' if self.kind=='tv' else 'movie_results'):[{'id':7,'title':'English fallback','overview':'English fallback','backdrop_path':'/scene.jpg'}]}
        return {'translations':self.docs}

class BatchClient:
    def __init__(self,configured=True):self.configured=configured;self.calls=[]
    def translate(self,imdb,kind):
        self.calls.append((imdb,kind));return {'romanian_title':'Titlu românesc','description':'Poveste verificată.','description_language':'ro','source_url':'https://www.themoviedb.org/movie/7'}

class LocalizationTests(unittest.TestCase):
    def test_no_english_overview_disguised_as_romanian(self):
        client=DocumentClient([{'iso_639_1':'en','data':{'title':'English','overview':'English plot'}}])
        self.assertEqual(client.translate('tt0000001','movie'),{})
    def test_romanian_translation_and_images(self):
        row=DocumentClient([{'iso_639_1':'ro','iso_3166_1':'RO','data':{'title':'Titlu','overview':'Poveste.'}}]).translate('tt0000001','movie')
        self.assertEqual(row['description_language'],'ro');self.assertEqual(row['romanian_title'],'Titlu');self.assertTrue(row['background'].endswith('/scene.jpg'))
    def test_tv_uses_name(self):
        row=DocumentClient([{'iso_639_1':'ro','data':{'name':'Serial','overview':'Poveste.'}}],kind='tv').translate('tt0000001','tv')
        self.assertEqual(row['romanian_title'],'Serial')
    def test_empty_ro_translation_does_not_copy_english(self):
        row=DocumentClient([{'iso_639_1':'ro','data':{}}]).translate('tt0000001','movie')
        self.assertNotIn('description',row);self.assertNotIn('romanian_title',row)
    def test_cache_and_existing_imdb_title(self):
        con=sqlite3.connect(':memory:');con.execute('CREATE TABLE titles(imdb_id TEXT,type TEXT,romanian_title TEXT,year INTEGER,votes INTEGER)')
        con.executemany('INSERT INTO titles VALUES(?,?,?,?,?)',[('tt0000001','movie','Titlu IMDb',2025,1),('tt0000002','tv',None,2024,2)])
        with tempfile.TemporaryDirectory() as d:
            cache=Path(d)/'cache.json';history=Path(d)/'history.json';client=BatchClient()
            snap={'https://provider/movies/latest/page-1.json':{'result':[{'imdb_id':'tt0000001'}]},'https://provider/tvshows/latest/page-1.json':{'result':[{'imdb_id':'tt0000002'}]}}
            localized,status=enrich_catalog(con,cache,history,snap,client,2,now=10000000)
            self.assertEqual(len(client.calls),2);self.assertEqual(status['romanian_synopses'],2)
            self.assertEqual(con.execute('SELECT romanian_title FROM titles WHERE imdb_id="tt0000001"').fetchone()[0],'Titlu IMDb')
            self.assertEqual(con.execute('SELECT romanian_title FROM titles WHERE imdb_id="tt0000002"').fetchone()[0],'Titlu românesc')
            client.calls.clear();enrich_catalog(con,cache,history,snap,client,2,now=10000001);self.assertEqual(client.calls,[])
            disabled=BatchClient(False);result,status=enrich_catalog(con,cache,history,snap,disabled,2,now=10000002)
            self.assertFalse(status['configured']);self.assertEqual(len(result),2);self.assertEqual(disabled.calls,[])
            self.assertNotIn('test-key',cache.read_text())
        con.close()

if __name__=='__main__':unittest.main()
