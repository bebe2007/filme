"""Merge provider membership and recent feeds before enriching from IMDb.
Never drop an available episode just because IMDb has not mapped it yet.
"""
import json,re,time,hashlib,gzip
from pathlib import Path
from datetime import datetime,timezone

ID=re.compile(r'^tt\d{5,12}$')
BASE='https://vsembed.ru'

def read(path):
 try:return json.loads(Path(path).read_text())
 except (OSError,ValueError):return {}

def write(path,value):
 path=Path(path);path.parent.mkdir(parents=True,exist_ok=True)
 tmp=path.with_suffix(path.suffix+'.tmp');tmp.write_text(json.dumps(value,ensure_ascii=False,separators=(',',':')));tmp.replace(path)

def merge_id_history(downloads,cache,files):
 """Retain previously validated memberships. Removals require separate review."""
 for name in files:
  current=downloads/name;history=cache/('retained-'+name)
  values=set(current.read_text().splitlines())
  if history.exists():values.update(history.read_text().splitlines())
  text='\n'.join(sorted(v.strip() for v in values if v.strip()))+'\n'
  current.write_text(text);history.write_text(text)

def collect(con,cache,fetch,interval=1.5,max_pages=10000):
 cache=Path(cache);cache.mkdir(parents=True,exist_ok=True)
 metadata=read(cache/'provider-metadata.json');metadata=metadata if isinstance(metadata,dict) else {}
 responses={};report={};last=0
 for kind,typ in [('movies','movie'),('tvshows','tv'),('episodes','episode')]:
  seen=set();finished=False;rows_count=0;unresolved=0
  for page in range(1,max_pages+1):
   if interval:time.sleep(max(0,interval-(time.monotonic()-last)))
   last=time.monotonic();url=f'{BASE}/{kind}/latest/page-{page}.json';doc=fetch(url,attempts=4,timeout=25)
   if not isinstance(doc,dict) or not isinstance(doc.get('result',doc.get('items')),list):break
   rows=doc.get('result',doc.get('items'));responses[url]=doc
   if not rows:finished=True;break
   keys=[]
   for row in rows:
    if not isinstance(row,dict):unresolved+=1;continue
    imdb=str(row.get('imdb_id') or '').strip().lower()
    if not ID.fullmatch(imdb):
     tmdb=str(row.get('tmdb_id') or '')
     info=fetch(f'{BASE}/info/{"movie" if typ=="movie" else "tv"}/{tmdb}.json',attempts=1,timeout=15) if tmdb.isdigit() else None
     imdb=str((info or {}).get('imdb_id') or '').strip().lower()
     if not ID.fullmatch(imdb):unresolved+=1;continue
    season=episode=None
    if typ=='episode':
     try:season=int(row['season']);episode=int(row['episode'])
     except (KeyError,ValueError,TypeError):unresolved+=1;continue
     if season<0 or episode<0:unresolved+=1;continue
    key=(imdb,season,episode);keys.append(key)
    if key in seen:continue
    seen.add(key);rows_count+=1;title_type='movie' if typ=='movie' else 'tv'
    title=row.get('show_title') if typ=='episode' else row.get('title')
    con.execute('INSERT OR IGNORE INTO titles(imdb_id,type,title) VALUES(?,?,?)',(imdb,title_type,title or None))
    if typ=='episode':con.execute('INSERT OR IGNORE INTO provider_eps VALUES(?,?,?)',(imdb,season,episode))
    stored=metadata.setdefault(imdb,{})
    for field in ['poster','quality','time_added']:
     if row.get(field) and typ!='episode':stored[field]=row[field]
    if title:stored['title']=str(title)
   if page>1 and keys and not any(k not in seen_before for k in keys):break
   seen_before=set(seen)
   pages=doc.get('pages',doc.get('total_pages'))
   if str(pages).isdigit() and page>=int(pages):finished=True;break
  report[kind]={'complete':finished,'seen':rows_count,'unresolved':unresolved}
 # Retain metadata and IDs learned from recent feeds across daily ID-list lag.
 for imdb,row in metadata.items():
  if ID.fullmatch(imdb):
   typ=row.get('type')
   if typ in ('movie','tv'):con.execute('INSERT OR IGNORE INTO titles(imdb_id,type,title) VALUES(?,?,?)',(imdb,typ,row.get('title')))
 for imdb,typ in con.execute('SELECT imdb_id,type FROM titles'):
  if imdb in metadata:metadata[imdb]['type']=typ
 for imdb,row in metadata.items():
  if row.get('title'):con.execute('UPDATE titles SET title=COALESCE(title,?) WHERE imdb_id=?',(row['title'],imdb))
 # An episode's own availability establishes the parent show's membership.
 con.execute("INSERT OR IGNORE INTO titles(imdb_id,type) SELECT DISTINCT parent_imdb,'tv' FROM provider_eps")
 con.execute('INSERT OR IGNORE INTO episodes(parent_imdb,season,episode) SELECT parent_imdb,season,episode FROM provider_eps')
 con.commit();write(cache/'provider-metadata.json',metadata)
 # Recent episode tuples must survive until the next provider ID-list refresh.
 eps=cache/'retained-eps_imdb.txt'
 with eps.open('w') as f:
  for imdb,s,e in con.execute('SELECT parent_imdb,season,episode FROM provider_eps ORDER BY 1,2,3'):f.write(f'{imdb}_{s}x{e}\n')
 for typ,name in [('movie','movie_imdb.txt'),('tv','tv_imdb.txt')]:
  (cache/('retained-'+name)).write_text('\n'.join(r[0] for r in con.execute('SELECT imdb_id FROM titles WHERE type=? ORDER BY imdb_id',(typ,)))+'\n')
 return responses,report,metadata

def publish_indexes(con,out):
 """Same row layout used by the installed PHP filtered catalog."""
 out=Path(out);folder=out/'sync';folder.mkdir(parents=True,exist_ok=True)
 rows=[]
 history=read(out/'catalog-history.json').get('items',{})
 for r in con.execute('SELECT imdb_id,type,title,original_title,romanian_title,year,rating,votes,genres,runtime FROM titles'):
  imdb,typ,title,original,ro,year,rating,votes,genres,runtime=r;h=history.get(imdb,{})
  rows.append([imdb,typ,ro or title or imdb,ro or '',year or 0,rating or 0,votes or 0,(genres or '').replace(',','|'),h.get('poster',''),'',h.get('site_added_at') or h.get('provider_added_at') or '',runtime or 0,original or '',h.get('site_added_at') or ''])
 orders={'recent':lambda r:(r[10],r[4],r[0]),'rating':lambda r:(r[5],r[6],r[0]),'year':lambda r:(r[4],r[0])};files={}
 for kind,key in orders.items():
  name=f'catalog-v8-{kind}.ndjson.gz';path=folder/name
  with gzip.open(path,'wt',encoding='utf-8') as f:
   for row in sorted(rows,key=key,reverse=True):f.write(json.dumps(row,ensure_ascii=False,separators=(',',':'))+'\n')
  files[name]={'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'bytes':path.stat().st_size}
 meta=read(out/'meta.json');meta['episodes']=con.execute('SELECT COUNT(*) FROM episodes').fetchone()[0];meta['provider_episodes']=con.execute('SELECT COUNT(*) FROM provider_eps').fetchone()[0];meta['episodes_without_imdb_metadata']=con.execute('SELECT COUNT(*) FROM episodes WHERE ep_tconst IS NULL').fetchone()[0]
 if meta['episodes']!=meta['provider_episodes']:raise ValueError('Episode membership mismatch')
 write(out/'meta.json',meta)
 release=hashlib.sha256(json.dumps(files,sort_keys=True).encode()).hexdigest()[:24]
 write(folder/'manifest.json',{'schema':1,'release':release,'generated_at':meta['generated_at'],'total':len(rows),'movies':meta['movies'],'tv':meta['tv'],'episodes':meta['episodes'],'daily':read(out/'daily.json'),'files':files})
 return meta
