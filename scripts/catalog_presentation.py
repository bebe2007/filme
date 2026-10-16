"""Publish date-ordered browsing, daily counts and catalog-wide ranked recommendations.

No image binaries are downloaded. The state contains identifiers, timestamps and URLs.
On first build the provider's latest pages are walked in full; subsequent builds stop
when an unchanged, previously indexed page older than today is reached.
"""
from __future__ import annotations

import json
import math
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

GENRES = ['Action','Adventure','Animation','Biography','Comedy','Crime','Documentary',
          'Drama','Family','Fantasy','Film-Noir','Game-Show','History','Horror','Music',
          'Musical','Mystery','News','Reality-TV','Romance','Sci-Fi','Short','Sport',
          'Talk-Show','Thriller','War','Western','Adult']


def write(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_suffix(path.suffix + '.tmp')
    tmp.write_text(json.dumps(value, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
    tmp.replace(path)


def read(path):
    try:
        value = json.loads(path.read_text(encoding='utf-8'))
        return value if isinstance(value, dict) else {}
    except (OSError, ValueError):
        return {}


def provider_time(value, provider_zone='UTC'):
    if not value:
        return None
    try:
        if isinstance(value, (float, int)) or str(value).isdigit():
            return datetime.fromtimestamp(float(value), timezone.utc).isoformat()
        dt = datetime.fromisoformat(str(value).strip().replace('Z', '+00:00'))
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=ZoneInfo(provider_zone))
        return dt.astimezone(timezone.utc).isoformat()
    except (ValueError, TypeError, OverflowError):
        return None


def rows_from(doc):
    if isinstance(doc, list):
        return [x for x in doc if isinstance(x, dict)]
    if isinstance(doc, dict):
        rows = doc.get('result') or doc.get('items') or []
        return [x for x in rows if isinstance(x, dict)] if isinstance(rows, list) else []
    return []


def build_presentation(con, out, state_path, fetch, max_pages=4000, now=None):
    now = now or datetime.now(timezone.utc)
    local_today = now.astimezone(ZoneInfo('Europe/Bucharest')).date()
    old = read(state_path)
    if not old:
        old = fetch('https://bebe2007.github.io/filme/data/catalog-history.json', attempts=1, timeout=15) or {}
    old_items = old.get('items', {}) if isinstance(old, dict) else {}
    if not isinstance(old_items, dict):
        old_items = {}
    ids = {r[0]: r[1] for r in con.execute('SELECT imdb_id,type FROM titles')}
    history = {}
    for imdb, typ in ids.items():
        row = old_items.get(imdb)
        row = dict(row) if isinstance(row, dict) else {}
        # After bootstrap, this is the moment a title first enters this site's catalog.
        if old_items and imdb not in old_items:
            row['site_added_at'] = now.isoformat()
        row['type'] = typ
        history[imdb] = row

    completeness = {}
    for typ, endpoint in [('movie','movies'), ('tv','tvshows')]:
        seen = set()
        finished = False
        previous_complete = bool((old.get('complete') or {}).get(typ))
        for start in range(1, max_pages + 1, 8):
            pages = list(range(start, min(start+8, max_pages+1)))
            def get(page):
                return fetch(f'https://vsembed.ru/{endpoint}/latest/page-{page}.json', attempts=2, timeout=15)
            with ThreadPoolExecutor(max_workers=4) as pool:
                docs = list(pool.map(get, pages))
            stop = False
            for page, doc in zip(pages, docs):
                if doc is None or not isinstance(doc,(dict,list)) or (isinstance(doc,dict) and not any(k in doc for k in ('result','items'))):
                    stop = True
                    break
                rows = rows_from(doc)
                if not rows:
                    finished = True
                    stop = True
                    break
                unchanged = True
                dated_before_today = True
                new_on_page = 0
                for row in rows:
                    imdb = str(row.get('imdb_id') or row.get('id') or '').lower().strip()
                    if imdb not in ids or ids[imdb] != typ:
                        continue
                    if imdb not in seen:
                        new_on_page += 1
                    seen.add(imdb)
                    added = provider_time(row.get('time_added'))
                    prior = old_items.get(imdb) or {}
                    if not isinstance(prior, dict) or not prior.get('provider_added_at') or (added and prior.get('provider_added_at') != added):
                        unchanged = False
                    if not added or datetime.fromisoformat(added).astimezone(ZoneInfo('Europe/Bucharest')).date() >= local_today:
                        dated_before_today = False
                    entry = history[imdb]
                    if added:
                        entry['provider_added_at'] = added
                        if not entry.get('site_added_at'):
                            entry['site_added_at'] = added
                    poster = str(row.get('poster') or '')
                    if poster.startswith('https://'):
                        entry['poster'] = poster
                if new_on_page == 0:
                    # A repeated nonempty page is not proof of complete pagination.
                    stop = True
                    break
                if previous_complete and unchanged and dated_before_today:
                    finished = True
                    stop = True
                    break
                page_count = (doc.get('pages') or doc.get('total_pages')) if isinstance(doc, dict) else None
                if str(page_count).isdigit() and page >= int(page_count):
                    finished = True
                    stop = True
                    break
            print(f'Presentation {typ}: pages through {start+len(pages)-1}, indexed {len(seen):,}', flush=True)
            if stop:
                break
        # Unknown dates prevent a false claim of fully chronological order.
        unknown = any(not r.get('site_added_at') for k,r in history.items() if ids[k] == typ)
        completeness[typ] = finished and not unknown

    state = {'schema_version':1,'generated_at':now.isoformat(),'complete':completeness,'items':history}
    write(state_path, state)
    write(out/'catalog-history.json', state)

    columns = {r[1] for r in con.execute('PRAGMA table_info(titles)')}
    if 'added_at' not in columns:
        con.execute('ALTER TABLE titles ADD COLUMN added_at TEXT')
    con.executemany('UPDATE titles SET added_at=? WHERE imdb_id=?',
                    [(r.get('site_added_at'), k) for k,r in history.items()])
    con.commit()
    cards = []
    for row in con.execute('SELECT imdb_id,type,title,original_title,romanian_title,year,runtime,genres,rating,votes,added_at FROM titles'):
        imdb,typ,title,original,ro,year,runtime,genres,rating,votes,added = row
        c = {'imdb_id':imdb,'type':typ,'title':ro or title or imdb,'original_title':original,
             'romanian_title':ro,'year':year,'runtime':runtime,'genres':genres.split(',') if genres else [],
             'rating':rating,'votes':votes,'added_at':added}
        if history[imdb].get('poster'):
            c['poster'] = history[imdb]['poster']
        cards.append(c)
    cards.sort(key=lambda c:(c['added_at'] or '', c['year'] or 0, c['imdb_id']), reverse=True)
    row_size = 14
    for scope in ['all','movies','tv']:
        subset = cards if scope == 'all' else [c for c in cards if c['type'] == ('tv' if scope=='tv' else 'movie')]
        sections = []
        for genre in GENRES:
            matches = [c for c in subset if genre in c['genres']]
            pages = max(1, math.ceil(len(matches)/row_size))
            slug = genre.lower()
            doc = {'genre':genre,'slug':slug,'total':len(matches),'pages':pages,'page':1,
                   'items':matches[:row_size],'chronological':all(c.get('added_at') for c in matches)}
            sections.append(doc)
            for page in range(1,pages+1):
                write(out/'categories'/scope/slug/f'page-{page}.json',
                      {**doc,'page':page,'items':matches[(page-1)*row_size:page*row_size]})
        write(out/'browse'/f'{scope}.json',{'schema_version':1,'generated_at':now.isoformat(),
              'sections':sections,'hero':[c for c in subset if 'Adult' not in c['genres']][:6]})
    for typ, name in [('movie','movies'),('tv','tv')]:
        # A minimum voting sample avoids ranking a one-vote title above established titles.
        ranked = [c for c in cards if c['type']==typ and c.get('rating') is not None and (c.get('votes') or 0)>=1000]
        ranked.sort(key=lambda c:(c['rating'],c['votes'],c['imdb_id']),reverse=True)
        write(out/'ranked'/f'{name}.json',{'generated_at':now.isoformat(),'minimum_votes':1000,'items':ranked[:250]})
    counts = {'movies':0,'tv':0}
    for k,r in history.items():
        value = r.get('site_added_at')
        if value and datetime.fromisoformat(value).astimezone(ZoneInfo('Europe/Bucharest')).date()==local_today:
            counts['tv' if ids[k]=='tv' else 'movies'] += 1
    write(out/'daily.json',{'date':local_today.isoformat(),'timezone':'Europe/Bucharest',
          'generated_at':now.isoformat(),'verified':all(completeness.values()),**counts})
