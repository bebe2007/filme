"""Import verified Romanian TMDb translations using CI secrets, never browser keys."""
from __future__ import annotations
import argparse, json, os, re, time, urllib.error, urllib.parse, urllib.request
from pathlib import Path

ID = re.compile(r'^tt\d{5,12}$')

class TMDbClient:
    def __init__(self, key=None, token=None):
        self.key = os.environ.get('TMDB_API_KEY', '').strip() if key is None else key
        self.token = (os.environ.get('TMDB_API_TOKEN') or os.environ.get('TMDB_READ_ACCESS_TOKEN') or '').strip() if token is None else token
        self.last = 0.0
    @property
    def configured(self):
        return bool(self.key or self.token)
    def get(self, path, **params):
        if not self.configured:
            return None
        time.sleep(max(0, .22-(time.monotonic()-self.last)))
        self.last = time.monotonic()
        if self.key and not self.token:
            params['api_key'] = self.key
        headers = {'Accept': 'application/json', 'User-Agent': 'BratuMarianCatalog/1.0'}
        if self.token:
            headers['Authorization'] = 'Bearer '+self.token
        request = urllib.request.Request('https://api.themoviedb.org/3/'+path+'?'+urllib.parse.urlencode(params), headers=headers)
        try:
            with urllib.request.urlopen(request, timeout=12) as response:
                data = response.read(2*1024*1024+1)
            if len(data)>2*1024*1024:
                return None
            return json.loads(data)
        except urllib.error.HTTPError as error:
            if error.code in (401,403):
                raise RuntimeError('TMDb authentication rejected; verify the repository secret.') from None
            return None
        except (OSError, ValueError):
            # Never print exception strings: a URL could contain the API key.
            return None
    def translate(self, imdb_id, kind):
        doc = self.get('find/'+imdb_id, external_source='imdb_id', language='ro-RO')
        if not isinstance(doc, dict):
            return None
        rows = doc.get('tv_results' if kind=='tv' else 'movie_results') or []
        if not rows:
            return {}
        row = rows[0]
        tmdb_id = row.get('id')
        if not isinstance(tmdb_id, int):
            return {}
        media = 'tv' if kind=='tv' else 'movie'
        translations = self.get(f'{media}/{tmdb_id}/translations')
        if not isinstance(translations, dict):
            return None
        ro = [x for x in translations.get('translations', []) if x.get('iso_639_1')=='ro']
        ro.sort(key=lambda x: x.get('iso_3166_1')!='RO')
        if not ro:
            return {}
        data = ro[0].get('data') or {}
        out = {'source_url':f'https://www.themoviedb.org/{media}/{tmdb_id}', 'metadata_source':'TMDb'}
        name = str(data.get('name' if kind=='tv' else 'title') or '').strip()
        overview = str(data.get('overview') or '').strip()
        if name:
            out['romanian_title'] = name
        if overview:
            out.update(description=overview, description_language='ro', description_source='TMDb')
        for field, source, size in [('poster','poster_path','w780'),('background','backdrop_path','w1280')]:
            value = row.get(source)
            if isinstance(value,str) and re.fullmatch(r'/[A-Za-z0-9._/-]+',value):
                out[field] = 'https://image.tmdb.org/t/p/'+size+value
        return out

def read_json(path):
    try:
        value=json.loads(Path(path).read_text())
        return value if isinstance(value,dict) else {}
    except (OSError,ValueError):
        return {}

def priority_ids(con, history_path, snapshots):
    known = dict(con.execute('SELECT imdb_id,type FROM titles'))
    pools = {'movie':[], 'tv':[]}
    for url,doc in (snapshots or {}).items():
        if '/latest/' not in url or not isinstance(doc,dict):
            continue
        for row in doc.get('result') or doc.get('items') or []:
            if not isinstance(row,dict):
                continue
            imdb=str(row.get('imdb_id') or row.get('id') or '').lower()
            if imdb in known:
                pools[known[imdb]].append(imdb)
    ordered=[]
    for n in range(max(len(pools['movie']),len(pools['tv']))):
        for kind in ('movie','tv'):
            if n<len(pools[kind]):
                ordered.append(pools[kind][n])
    history=read_json(history_path).get('items',{})
    ordered += sorted((k for k in history if k in known),key=lambda k:(history[k].get('site_added_at') or history[k].get('provider_added_at') or '', -(history[k].get('provider_position') or 0)),reverse=True)
    ordered += [r[0] for r in con.execute('SELECT imdb_id FROM titles ORDER BY year DESC,votes DESC')]
    return list(dict.fromkeys(ordered)),known

def enrich_catalog(con, cache_path, history_path, snapshots=None, client=None, batch_limit=None, now=None):
    client=client or TMDbClient();now=time.time() if now is None else now
    limit=max(0,min(2000,int(batch_limit if batch_limit is not None else os.environ.get('TMDB_RO_BATCH_SIZE','400'))))
    rows=read_json(cache_path).get('items',{})
    rows={k:v for k,v in rows.items() if ID.fullmatch(k) and isinstance(v,dict)}
    ordered,known=priority_ids(con,history_path,snapshots)
    fetched=0;updated=0
    if client.configured:
        for imdb in ordered:
            if fetched>=limit:
                break
            cached=rows.get(imdb,{})
            ttl=30*86400 if cached.get('data',{}).get('description') else 7*86400
            if now-float(cached.get('checked_at') or 0)<ttl:
                continue
            fetched+=1
            data=client.translate(imdb,known[imdb])
            if data is not None:
                rows[imdb]={'checked_at':int(now),'data':data};updated+=1
    path=Path(cache_path);path.parent.mkdir(parents=True,exist_ok=True)
    temp=path.with_suffix('.tmp');temp.write_text(json.dumps({'items':rows},ensure_ascii=False,separators=(',',':')));temp.replace(path)
    localized={}
    for imdb,row in rows.items():
        if imdb not in known:
            continue
        data=row.get('data') or {}
        if data.get('romanian_title'):
            # Keep IMDb's Romanian release title when one is already available.
            con.execute("UPDATE titles SET romanian_title=? WHERE imdb_id=? AND (romanian_title IS NULL OR TRIM(romanian_title)='')",(data['romanian_title'],imdb))
        if data:
            localized[imdb]={k:v for k,v in data.items() if k!='romanian_title'}
    con.commit()
    status={'configured':client.configured,'catalog_titles':len(known),'checked_this_run':fetched,'updated_this_run':updated,'cached_titles':len(rows),'romanian_synopses':sum(bool(v.get('description')) for v in localized.values())}
    print('TMDb Romanian localization: '+json.dumps(status),flush=True)
    return localized,status

def main():
    argparse.ArgumentParser(description=__doc__).add_argument('--check',action='store_true')
    client=TMDbClient()
    result={'configured':client.configured,'api_ok':False}
    if client.configured:
        doc=client.get('configuration')
        result['api_ok']=bool(isinstance(doc,dict) and doc.get('images'))
        if not result['api_ok']:
            raise SystemExit('TMDb API unavailable; no credentials were printed.')
    print(json.dumps(result))
    summary=os.environ.get('GITHUB_STEP_SUMMARY')
    if summary:
        with open(summary,'a') as f:f.write('TMDb configured: **'+str(result['configured'])+'**; API valid: **'+str(result['api_ok'])+'**\n')

if __name__=='__main__':
    main()
