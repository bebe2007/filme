import unittest,tempfile,json,gzip,csv
from pathlib import Path
from unittest.mock import patch
import build_catalog as b
from provider_sync import collect,publish_indexes,merge_id_history

class SyncTest(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.root=Path(self.tmp.name);self.con=b.init_db(self.root/'db')
 def tearDown(self):self.con.close();self.tmp.cleanup()
 def feeds(self,url,**kw):
  if '/movies/' in url:return {'pages':1,'result':[{'imdb_id':'tt9029366','title':'12 Months','time_added':'2026-10-24 07:20:33'}]}
  if '/tvshows/' in url:return {'pages':1,'result':[{'imdb_id':'tt1000001','title':'Serial'}]}
  if '/episodes/' in url:return {'pages':1,'result':[{'imdb_id':'tt1000001','season':1,'episode':1},{'imdb_id':'tt1000001','season':1,'episode':2},{'imdb_id':'tt9999999','show_title':'Alt serial','season':1,'episode':1}]}
 def test_new_title_and_all_provider_episodes_survive_missing_imdb(self):
  docs,report,meta=collect(self.con,self.root/'cache',self.feeds,interval=0)
  self.assertTrue(all(r['complete'] for r in report.values()))
  self.assertIsNotNone(self.con.execute("SELECT imdb_id FROM titles WHERE imdb_id='tt9029366'").fetchone())
  with gzip.open(self.root/'episode.gz','wt',newline='') as f:
   csv.writer(f,delimiter=chr(9)).writerows([['tconst','parentTconst','seasonNumber','episodeNumber'],['tt2000001','tt1000001',1,1]])
  b.import_episode_map(self.con,self.root/'episode.gz');b.build_title_shards(self.con,self.root/'out')
  show=json.loads((self.root/'out/title-shards/tt1000.json').read_text())['tt1000001']
  self.assertEqual(show['total_episodes'],2)
  self.assertEqual([e['episode'] for s in show['seasons'] for e in s['episodes_data']],[1,2])
  self.assertIsNone(show['seasons'][0]['episodes_data'][1]['imdb_id'])
  self.assertTrue((self.root/'out/title-shards/tt9999.json').exists())
  # The same feed can run again without creating duplicate episodes.
  collect(self.con,self.root/'cache',self.feeds,interval=0)
  self.assertEqual(self.con.execute('SELECT COUNT(*) FROM episodes').fetchone()[0],3)
 def test_full_export_has_new_film_and_all_episode_tuples(self):
  collect(self.con,self.root/'cache',self.feeds,interval=0)
  out=self.root/'export'
  with patch.object(b,'fetch_json_document',self.feeds):
   b.build_output(self.con,out,70,{},self.root/'state.json')
  meta=publish_indexes(self.con,out)
  self.assertEqual((meta['movies'],meta['tv'],meta['episodes']),(1,2,3))
  title=json.loads((out/'title-shards/tt9029.json').read_text())['tt9029366']
  self.assertEqual(title['title'],'12 Months')
  self.assertEqual(json.loads((out/'latest/movies.json').read_text())['items'][0]['imdb_id'],'tt9029366')
 def test_membership_survives_provider_list_shrink(self):
  d=self.root/'downloads';c=self.root/'cache';d.mkdir();c.mkdir()
  (d/'movie_imdb.txt').write_text('tt1000001\n');(c/'retained-movie_imdb.txt').write_text('tt1000002\n')
  merge_id_history(d,c,['movie_imdb.txt']);self.assertEqual(set((d/'movie_imdb.txt').read_text().split()),{'tt1000001','tt1000002'})
 def test_partial_or_repeated_feed_is_reported_incomplete(self):
  def repeated(url,**kw):return {'pages':3,'result':[{'imdb_id':'tt1000001','title':'Known','season':1,'episode':1}]}
  _,report,_=collect(self.con,self.root/'cache',repeated,interval=0)
  self.assertTrue(all(not r['complete'] for r in report.values()))
 def test_compact_indexes_preserve_site_date_and_all_ids(self):
  collect(self.con,self.root/'cache',self.feeds,interval=0);out=self.root/'out';out.mkdir()
  (out/'meta.json').write_text(json.dumps({'generated_at':'2026-10-24T12:00:00Z','movies':1,'tv':2,'total':3}))
  (out/'catalog-history.json').write_text(json.dumps({'items':{'tt9029366':{'site_added_at':'2026-10-24T12:00:00+00:00'}}}))
  result=publish_indexes(self.con,out);self.assertEqual(result['episodes'],3)
  manifest=json.loads((out/'sync/manifest.json').read_text())
  for name in manifest['files']:
   with gzip.open(out/'sync'/name,'rt') as f:rows=[json.loads(l) for l in f]
   self.assertEqual(len(rows),3);self.assertEqual(next(r for r in rows if r[0]=='tt9029366')[13],'2026-10-24T12:00:00+00:00')
 def test_history_does_not_turn_provider_date_into_site_addition(self):
  b.ADDITION_DATES={}
  row=('tt9029366','movie','12 Months',None,None,2022,None,90,None,None,None)
  self.assertNotIn('site_added_at',b.title_to_card(row,True))

if __name__=='__main__':unittest.main()
