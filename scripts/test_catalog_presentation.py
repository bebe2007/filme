import json
import sqlite3
import tempfile
import unittest
from datetime import datetime, timezone, timedelta
from pathlib import Path

from catalog_presentation import build_presentation, provider_time, baseline_for_day


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

    def build(self,fetch=None,baseline=True):
        folder=self.root/'baseline'
        if baseline:
            folder.mkdir(exist_ok=True)
            (folder/'manifest.json').write_text(json.dumps({'catalog_date':str((self.now-timedelta(days=1)).date())}))
            for typ,name in [('movie','movie_imdb.txt'),('tv','tv_imdb.txt')]:
                (folder/name).write_text('\n'.join(r['imdb_id'] for r in self.provider[typ][1:]))
        build_presentation(self.con,self.root/'out',self.root/'state.json',fetch or self.fetch,now=self.now,max_pages=8,request_interval=0,baseline_path=folder if baseline else None)

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
        self.build(fetch=lambda *a,**k:None,baseline=False)
        doc=self.doc('daily.json')
        self.assertFalse(doc['verified'])
        self.assertEqual((doc['movies'],doc['tv']),(0,0))

    def test_tv_without_dates_preserves_upstream_order(self):
        for row in self.provider['tv']: row.pop('time_added',None)
        self.build(baseline=False)
        action=next(s for s in self.doc('browse/tv.json')['sections'] if s['genre']=='Action')
        self.assertEqual(action['items'][0]['imdb_id'],'tt2000000')
        self.assertEqual(action['items'][0]['year'],1995)

    def test_today_counts_use_membership_not_provider_updates(self):
        for row in self.provider['movie']+self.provider['tv']: row['time_added']=self.now.isoformat()
        self.build()
        daily=self.doc('daily.json')
        self.assertTrue(daily['verified'])
        self.assertEqual((daily['movies'],daily['tv']),(1,1))

    def test_verified_membership_counts_survive_metadata_outage(self):
        self.build(fetch=lambda *a,**k:None)
        daily=self.doc('daily.json')
        self.assertTrue(daily['verified'])
        self.assertEqual((daily['movies'],daily['tv']),(1,1))

    def test_new_day_baseline_rolls_forward(self):
        self.build()
        self.now+=timedelta(days=1)
        self.build(baseline=False)
        daily=self.doc('daily.json')
        self.assertTrue(daily['verified'])
        self.assertEqual((daily['movies'],daily['tv']),(0,0))

    def test_today_feed_excludes_provider_updates_and_uses_membership(self):
        for row in self.provider['movie']+self.provider['tv']: row['time_added']=self.now.isoformat()
        self.build()
        today=self.doc('browse/today.json')
        self.assertTrue(today['verified'])
        self.assertEqual(today['date'],'2026-10-15')
        self.assertEqual(today['total'],2)
        self.assertEqual({c['imdb_id'] for s in today['sections'] for c in s['items']},{'tt1000000','tt2000000'})
        self.assertEqual(len(today['sections']),28)
        self.assertEqual(self.doc('categories/today/action/page-1.json')['total'],2)

    def test_today_feed_empty_when_unverified_or_no_additions(self):
        self.build(baseline=False)
        today=self.doc('browse/today.json')
        self.assertFalse(today['verified'])
        self.assertEqual(today['total'],0)
        self.assertFalse(today['hero'])
        self.now+=timedelta(days=1)
        self.build(baseline=False)
        today=self.doc('browse/today.json')
        self.assertTrue(today['verified'])
        self.assertEqual(today['total'],0)
        self.assertTrue(all(not s['items'] for s in today['sections']))

    def test_today_feed_dates_follow_bucharest_midnight(self):
        self.now=datetime(2026,10,15,22,30,tzinfo=timezone.utc)
        folder=self.root/'baseline';folder.mkdir()
        (folder/'manifest.json').write_text(json.dumps({'catalog_date':'2026-10-15'}))
        for typ,name in [('movie','movie_imdb.txt'),('tv','tv_imdb.txt')]:
            (folder/name).write_text('\n'.join(r['imdb_id'] for r in self.provider[typ][1:]))
        build_presentation(self.con,self.root/'out',self.root/'state.json',self.fetch,now=self.now,max_pages=8,request_interval=0,baseline_path=folder)
        self.assertEqual(self.doc('browse/today.json')['date'],'2026-10-16')
        self.assertEqual(self.doc('browse/today.json')['total'],2)

    def test_unmatched_page_does_not_stop_catalog_walk(self):
        def fetch(url,**kwargs):
            if 'catalog-history' in url:return None
            typ='tv' if '/tvshows/' in url else 'movie'
            if 'page-1.' in url:return {'pages':2,'result':[{'imdb_id':'tt99999999'}]}
            return {'pages':2,'result':self.provider[typ]}
        self.build(fetch=fetch)
        state=json.loads((self.root/'state.json').read_text())
        self.assertEqual(state['pagination']['movie']['indexed'],55)
        self.assertTrue(state['pagination']['tv']['finished'])


if __name__=='__main__':
    unittest.main()

