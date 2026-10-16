import json
import sqlite3
import tempfile
import unittest
from datetime import datetime, timezone
from pathlib import Path

from catalog_presentation import build_presentation, provider_time


class PresentationTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.con = sqlite3.connect(':memory:')
        self.con.execute('CREATE TABLE titles(imdb_id TEXT PRIMARY KEY,type TEXT,title TEXT,original_title TEXT,romanian_title TEXT,year INTEGER,runtime INTEGER,genres TEXT,rating REAL,votes INTEGER)')
        self.now = datetime(2026,10,15,12,tzinfo=timezone.utc)
        self.provider = {'movie':[], 'tv':[]}
        for typ,base in [('movie',1000000),('tv',2000000)]:
            for n in range(55):
                imdb='tt'+str(base+n)
                self.con.execute('INSERT INTO titles VALUES(?,?,?,?,?,?,?,?,?,?)', (imdb,typ,'Title '+str(n),None,None,1995 if n==0 else 2026,90,'Action,Adult' if n==1 else 'Action',9.5-n/100,1000+n))
                self.provider[typ].append({'imdb_id':imdb,'time_added':'2026-10-15T12:00:00Z' if n==0 else '2026-01-01T10:00:00Z'})

    def tearDown(self):
        self.con.close()
        self.tmp.cleanup()

    def fetch(self,url,**kwargs):
        if 'catalog-history' in url:
            return None
        typ='tv' if '/tvshows/' in url else 'movie'
        return {'pages':1,'result':self.provider[typ]}

    def build(self,fetch=None):
        build_presentation(self.con,self.root/'out',self.root/'state.json',fetch or self.fetch,now=self.now,max_pages=8)

    def doc(self,path):
        return json.loads((self.root/'out'/path).read_text())

    def test_added_date_and_type_and_all_genres(self):
        self.build()
        for scope in ['all','movies','tv']:
            doc=self.doc('browse/'+scope+'.json')
            self.assertEqual(len(doc['sections']),28)
            self.assertTrue(any(x['genre']=='Adult' for x in doc['sections']))
            action=next(x for x in doc['sections'] if x['genre']=='Action')
            self.assertEqual(action['items'][0]['year'],1995)
            if scope!='all':
                self.assertTrue(all(x['type']==('tv' if scope=='tv' else 'movie') for section in doc['sections'] for x in section['items']))

    def test_ranking_excludes_insufficient_vote_sample(self):
        self.con.execute("UPDATE titles SET rating=10,votes=1 WHERE imdb_id='tt1000001'")
        self.build()
        rows=self.doc('ranked/movies.json')['items']
        self.assertGreaterEqual(len(rows),50)
        self.assertNotIn('tt1000001',{x['imdb_id'] for x in rows})
        self.assertEqual([x['rating'] for x in rows],sorted([x['rating'] for x in rows],reverse=True))

    def test_daily_counts_are_verified_only_with_known_dates(self):
        self.build()
        doc=self.doc('daily.json')
        self.assertTrue(doc['verified'])
        self.assertEqual((doc['movies'],doc['tv']),(1,1))
        self.assertEqual(provider_time('2026-10-14 22:30:00'),'2026-10-14T22:30:00+00:00')

    def test_outage_does_not_fabricate_first_day_additions(self):
        self.build(fetch=lambda *a,**k:None)
        doc=self.doc('daily.json')
        self.assertFalse(doc['verified'])
        self.assertEqual((doc['movies'],doc['tv']),(0,0))


if __name__=='__main__':
    unittest.main()
