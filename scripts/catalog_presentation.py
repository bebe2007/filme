"""Publish date-ordered browsing, daily counts and catalog-wide ranked recommendations.

No image binaries are downloaded. The state contains identifiers, timestamps and URLs.
Provider requests are sequential and throttled. Site addition counts use a verified
previous-day membership snapshot; undated TV lists retain their provider order.
"""
from __future__ import annotations

import json
import math
import os
import time
import re
from datetime import datetime, timezone, timedelta
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


def baseline_for_day(old, ids, now, baseline_path=None):
    today = now.astimezone(ZoneInfo('Europe/Bucharest')).date()
    prior = old.get('daily_baseline') or {}
    if prior.get('date') == str(today) and isinstance(prior.get('ids'), list):
        return prior
    generated = provider_time(old.get('generated_at'))
    if old.get('schema_version', 0) >= 2 and generated and datetime.fromisoformat(generated).astimezone(ZoneInfo('Europe/Bucharest')).date() < today:
        return {'date':str(today),'verified':True,'ids':list((old.get('items') or {}).keys())}
    if baseline_path:
        folder = Path(baseline_path)
        manifest = read(folder/'manifest.json')
        if manifest.get('catalog_date') == str(today-timedelta(days=1)):
            prior_ids = set()
            valid = True
            for name in ('movie_imdb.txt', 'tv_imdb.txt'):
                try:
                    values = {x.strip().lower() for x in (folder/name).read_text().splitlines() if x.strip()}
                    if not values or not all(re.fullmatch(r'tt\d{5,12}', x) for x in values): valid=False
                    prior_ids.update(values)
                except OSError:
                    valid=False
            if valid and len(prior_ids.intersection(ids)) >= max(1, int(len(ids)*0.5)):
                return {'date':str(today),'verified':True,'ids':sorted(prior_ids)}
    # This is a baseline observation, never an invented historical addition time.
    return {'date':str(today),'verified':False,'ids':list(ids)}


def build_presentation(con, out, state_path, fetch, max_pages=4000, now=None, request_interval=1.5, baseline_path=None):
    now = now or datetime.now(timezone.utc)
    local_today = now.astimezone(ZoneInfo('Europe/Bucharest')).date()
    old = read(state_path)
    if not old:
        old = fetch('https://bebe2007.github.io/filme/data/catalog-history.json', attempts=1, timeout=15) or {}
    old_items = old.get('items', {}) if isinstance(old, dict) else {}
    if not isinstance(old_items, dict): old_items = {}
    ids = {r[0]: r[1] for r in con.execute('SELECT imdb_id,type FROM titles')}
    baseline = baseline_for_day(old, ids, now, baseline_path or os.environ.get('CATALOG_BASELINE_DIR'))
    baseline_ids = set(baseline['ids'])
    history = {}
    for imdb, typ in ids.items():
        row = old_items.get(imdb)
        row = dict(row) if isinstance(row, dict) else {}
        if old.get('schema_version', 0) < 2:
            row.pop('site_added_at', None)  # v1 confused provider timestamps with site additions.
        if baseline['verified'] and imdb not in baseline_ids:
            row.setdefault('site_added_at', now.isoformat())
        elif old.get('schema_version', 0) >= 2 and imdb not in old_items:
            row['site_added_at'] = now.isoformat()
        row['type'] = typ
        history[imdb] = row

    completeness = {}
    pagination = {}
    last_request = 0.0
    for typ, endpoint in [('movie','movies'), ('tv','tvshows')]:
        seen = set()
        raw_seen = set()
        finished = False
        for page in range(1, max_pages+1):
            if request_interval:
                time.sleep(max(0, request_interval-(time.monotonic()-last_request)))
            last_request = time.monotonic()
            doc = fetch(f'https://vsembed.ru/{endpoint}/latest/page-{page}.json', attempts=4, timeout=20)
            if doc is None or not isinstance(doc,(dict,list)) or (isinstance(doc,dict) and not any(k in doc for k in ('result','items'))): break
            rows = rows_from(doc)
            if not rows:
                finished = True
                break
            raw_ids = [str(row.get('imdb_id') or row.get('id') or '').lower().strip() for row in rows]
            if not any(x and x not in raw_seen for x in raw_ids):
                print(f'Presentation {typ}: repeated upstream page {page}; keeping unverified history',flush=True)
                break
            raw_seen.update(raw_ids)
            if page == 1:
                print(f'Presentation {typ}: upstream pages={(doc.get("pages") or doc.get("total_pages")) if isinstance(doc,dict) else "unknown"}; row fields={list(rows[0])}',flush=True)
            for offset, row in enumerate(rows):
                imdb = str(row.get('imdb_id') or row.get('id') or '').lower().strip()
                if imdb not in ids or ids[imdb] != typ: continue
                seen.add(imdb)
                entry = history[imdb]
                entry['provider_position'] = (page-1)*50+offset
                added = provider_time(row.get('time_added'))
                if added: entry['provider_added_at'] = added
                poster = str(row.get('poster') or '')
                if poster.startswith('https://'): entry['poster'] = poster
            if page % 25 == 0 or page == 1:
                print(f'Presentation {typ}: page {page}, indexed {len(seen):,}',flush=True)
            page_count = (doc.get('pages') or doc.get('total_pages')) if isinstance(doc, dict) else None
            if str(page_count).isdigit() and page >= int(page_count):
                finished = True
                break
        unknown = any(not r.get('site_added_at') and not r.get('provider_added_at') for k,r in history.items() if ids[k] == typ)
        completeness[typ] = finished and not unknown
        pagination[typ] = {'finished':finished,'indexed':len(seen),'total':sum(t==typ for t in ids.values())}
        print(f'Presentation {typ}: finished={finished}, indexed {len(seen):,}',flush=True)

    state = {'schema_version':2,'generated_at':now.isoformat(),'complete':completeness,'pagination':pagination,'daily_baseline':baseline,'items':history}
    write(state_path, state)
    write(out/'catalog-history.json', state)

    columns = {r[1] for r in con.execute('PRAGMA table_info(titles)')}
    if 'added_at' not in columns:
        con.execute('ALTER TABLE titles ADD COLUMN added_at TEXT')
    con.executemany('UPDATE titles SET added_at=? WHERE imdb_id=?',
                    [(r.get('site_added_at') or r.get('provider_added_at'), k) for k,r in history.items()])
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
    # TV lists have no time_added field. Preserve their documented newest-first position.
    def order(c):
        h=history[c['imdb_id']]
        observed=h.get('site_added_at') or ''
        return (bool(observed), observed, -h.get('provider_position', 10**12), c['year'] or 0, c['imdb_id'])
    cards.sort(key=order, reverse=True)
    row_size = 14
    for scope in ['all','movies','tv','today']:
        subset = cards if scope == 'all' else [c for c in cards if c['type'] == ('tv' if scope=='tv' else 'movie')]
        date_fields = {}
        if scope == 'today':
            subset = [c for c in cards if baseline['verified'] and c['imdb_id'] not in baseline_ids]
            date_fields = {'date':local_today.isoformat(),'timezone':'Europe/Bucharest','verified':bool(baseline['verified'])}
        sections = []
        for genre in GENRES:
            matches = [c for c in subset if genre in c['genres']]
            pages = max(1, math.ceil(len(matches)/row_size))
            slug = genre.lower()
            doc = {'genre':genre,'slug':slug,'total':len(matches),'pages':pages,'page':1,
                   'items':matches[:row_size],'chronological':all(c.get('added_at') for c in matches),**date_fields}
            sections.append(doc)
            for page in range(1,pages+1):
                write(out/'categories'/scope/slug/f'page-{page}.json',
                      {**doc,'page':page,'items':matches[(page-1)*row_size:page*row_size]})
        write(out/'browse'/f'{scope}.json',{'schema_version':1,'generated_at':now.isoformat(),
              'sections':sections,'hero':[c for c in subset if 'Adult' not in c['genres']][:6],
              'total':len(subset),**date_fields})
    for typ, name in [('movie','movies'),('tv','tv')]:
        # A minimum voting sample avoids ranking a one-vote title above established titles.
        ranked = [c for c in cards if c['type']==typ and c.get('rating') is not None and (c.get('votes') or 0)>=1000]
        ranked.sort(key=lambda c:(c['rating'],c['votes'],c['imdb_id']),reverse=True)
        write(out/'ranked'/f'{name}.json',{'generated_at':now.isoformat(),'minimum_votes':1000,'items':ranked[:250]})
    counts = {'movies':0,'tv':0}
    if baseline['verified']:
        for imdb,typ in ids.items():
            if imdb not in baseline_ids: counts['tv' if typ=='tv' else 'movies'] += 1
    write(out/'daily.json',{'date':local_today.isoformat(),'timezone':'Europe/Bucharest',
          'generated_at':now.isoformat(),'verified':bool(baseline['verified']),
          'basis':'catalog_membership_since_previous_day',**counts})
