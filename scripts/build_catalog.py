#!/usr/bin/env python3
"""Build the static movie/TV data catalog used by filme.bratu-marian.com.

Catalog membership comes from VSEmbed availability lists and factual metadata from IMDb TSV
datasets. The build also publishes compact "latest" feeds for the PHP homepage. The script
does not invent plot summaries or biographies.
"""
from __future__ import annotations

import argparse
import csv
import gzip
import json
import math
import os
import re
import shutil
import sqlite3
import sys
import time
import unicodedata
import urllib.error
import urllib.request
from collections import defaultdict
from pathlib import Path

# IMDb contains some unusually large TSV fields. Python's csv default (128 KiB) is too small.
csv.field_size_limit(64 * 1024 * 1024)

IMDB_BASE = "https://datasets.imdbws.com"
VSE_BASE = "https://vsembed.ru"
IMDB_FILES = [
    "title.basics.tsv.gz",
    "title.ratings.tsv.gz",
    "title.akas.tsv.gz",
    "title.episode.tsv.gz",
    "title.principals.tsv.gz",
    "title.crew.tsv.gz",
    "name.basics.tsv.gz",
]
VSE_FILES = {
    "movie_imdb.txt": f"{VSE_BASE}/ids/movie_imdb.txt",
    "tv_imdb.txt": f"{VSE_BASE}/ids/tv_imdb.txt",
    "eps_imdb.txt": f"{VSE_BASE}/ids/eps_imdb.txt",
}
TT_RE = re.compile(r"^tt\d{5,12}$")
EPS_RE = re.compile(r"^(tt\d{5,12})_(\d+)x(\d+)$", re.I)


def log(msg: str) -> None:
    print(time.strftime("[%H:%M:%S]"), msg, flush=True)


def clean(v):
    if v in (None, "", r"\N"):
        return None
    return v


def int_or_none(v):
    v = clean(v)
    if v is None:
        return None
    try:
        return int(v)
    except (ValueError, TypeError):
        return None


def float_or_none(v):
    v = clean(v)
    if v is None:
        return None
    try:
        return float(v)
    except (ValueError, TypeError):
        return None


def normalize(s: str) -> str:
    s = unicodedata.normalize("NFD", s or "")
    s = "".join(ch for ch in s if unicodedata.category(ch) != "Mn")
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", " ", s).strip()
    return s


def slugify(s: str) -> str:
    return normalize(s).replace(" ", "-") or "necunoscut"


RETRYABLE_HTTP_CODES = {429, 500, 502, 503, 504}


def validate_vse_list(path: Path, filename: str) -> bool:
    """Reject empty/error pages accidentally returned instead of VSE ID lists."""
    if not path.exists() or path.stat().st_size <= 0:
        return False
    matcher = EPS_RE if filename == "eps_imdb.txt" else TT_RE
    checked = 0
    valid = 0
    try:
        with open(path, "rt", encoding="utf-8", errors="replace") as f:
            for line in f:
                value = line.strip()
                if not value:
                    continue
                checked += 1
                candidate = value if filename == "eps_imdb.txt" else value.lower()
                if matcher.match(candidate):
                    valid += 1
                if checked >= 5000:
                    break
    except OSError:
        return False
    return checked >= 10 and (valid / checked) >= 0.95


def download(
    url: str,
    dest: Path,
    force: bool = False,
    attempts: int = 5,
    fallback: Path | None = None,
    validator=None,
) -> None:
    def is_valid(path: Path) -> bool:
        return path.exists() and path.stat().st_size > 0 and (validator is None or validator(path))

    if not force and is_valid(dest):
        log(f"Există deja: {dest.name}")
        return

    if dest.exists():
        dest.unlink()
    dest.parent.mkdir(parents=True, exist_ok=True)
    tmp = dest.with_suffix(dest.suffix + ".part")
    last_exc: Exception = RuntimeError(f"Nu am putut descărca {url}")

    for attempt in range(1, max(1, attempts) + 1):
        tmp.unlink(missing_ok=True)
        req = urllib.request.Request(
            url,
            headers={
                "User-Agent": "Mozilla/5.0 CatalogBuilder/1.1",
                "Accept": "text/plain,*/*;q=0.8",
            },
        )
        try:
            log(f"Descarc {url} (încercarea {attempt}/{attempts})")
            with urllib.request.urlopen(req, timeout=120) as r, open(tmp, "wb") as f:
                shutil.copyfileobj(r, f, length=1024 * 1024)
            if validator is not None and not validator(tmp):
                raise ValueError(f"Conținut invalid primit de la {url}")
            tmp.replace(dest)
            log(f"Descărcat: {dest.name} ({dest.stat().st_size / 1024 / 1024:.1f} MB)")
            return
        except urllib.error.HTTPError as exc:
            last_exc = exc
            tmp.unlink(missing_ok=True)
            if exc.code not in RETRYABLE_HTTP_CODES or attempt >= attempts:
                break
        except (urllib.error.URLError, TimeoutError, OSError, ValueError) as exc:
            last_exc = exc
            tmp.unlink(missing_ok=True)
            if attempt >= attempts:
                break

        wait = min(60, 5 * (2 ** (attempt - 1)))
        log(f"Descărcarea a eșuat: {last_exc}. Reîncerc în {wait}s.")
        time.sleep(wait)

    if fallback is not None and is_valid(fallback):
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(fallback, dest)
        log(f"Provider indisponibil. Folosesc ultima copie validă din cache: {fallback}")
        return

    raise last_exc


def fetch_json_document(url: str, attempts: int = 4, timeout: int = 30):
    """Fetch a small JSON endpoint with retries. Returns None on final failure."""
    last_exc = None
    for attempt in range(1, max(1, attempts) + 1):
        req = urllib.request.Request(
            url,
            headers={
                "User-Agent": "Mozilla/5.0 CatalogBuilder/1.1",
                "Accept": "application/json,text/plain,*/*;q=0.8",
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r:
                raw = r.read()
            doc = json.loads(raw.decode("utf-8", errors="replace"))
            return doc if isinstance(doc, (dict, list)) else None
        except urllib.error.HTTPError as exc:
            last_exc = exc
            if exc.code not in RETRYABLE_HTTP_CODES or attempt >= attempts:
                break
        except (urllib.error.URLError, TimeoutError, OSError, ValueError, json.JSONDecodeError) as exc:
            last_exc = exc
            if attempt >= attempts:
                break
        wait = min(30, 3 * (2 ** (attempt - 1)))
        log(f"Endpoint JSON indisponibil: {url}: {last_exc}. Reîncerc în {wait}s.")
        time.sleep(wait)
    log(f"Endpoint JSON indisponibil după {attempts} încercări: {url}: {last_exc}")
    return None


def open_tsv_gz(path: Path):
    f = gzip.open(path, "rt", encoding="utf-8", errors="replace", newline="")
    return f, csv.DictReader(f, delimiter="\t")


def init_db(path: Path) -> sqlite3.Connection:
    if path.exists():
        path.unlink()
    con = sqlite3.connect(path)
    con.executescript(
        """
        PRAGMA journal_mode=WAL;
        PRAGMA synchronous=OFF;
        PRAGMA temp_store=MEMORY;
        PRAGMA cache_size=-200000;

        CREATE TABLE titles(
          imdb_id TEXT PRIMARY KEY,
          type TEXT NOT NULL,
          title TEXT,
          original_title TEXT,
          romanian_title TEXT,
          year INTEGER,
          end_year INTEGER,
          runtime INTEGER,
          genres TEXT,
          rating REAL,
          votes INTEGER
        );
        CREATE INDEX idx_titles_type_year ON titles(type, year DESC);
        CREATE INDEX idx_titles_year ON titles(year);

        CREATE TABLE provider_eps(
          parent_imdb TEXT NOT NULL,
          season INTEGER NOT NULL,
          episode INTEGER NOT NULL,
          PRIMARY KEY(parent_imdb, season, episode)
        );

        CREATE TABLE episodes(
          ep_tconst TEXT PRIMARY KEY,
          parent_imdb TEXT NOT NULL,
          season INTEGER NOT NULL,
          episode INTEGER NOT NULL,
          title TEXT,
          original_title TEXT,
          year INTEGER,
          runtime INTEGER,
          rating REAL,
          votes INTEGER
        );
        CREATE INDEX idx_episodes_parent ON episodes(parent_imdb, season, episode);

        CREATE TABLE principals(
          imdb_id TEXT NOT NULL,
          ordering INTEGER,
          nconst TEXT NOT NULL,
          category TEXT,
          job TEXT,
          characters TEXT,
          PRIMARY KEY(imdb_id, ordering, nconst)
        );
        CREATE INDEX idx_principals_nconst ON principals(nconst);

        CREATE TABLE crew(
          imdb_id TEXT NOT NULL,
          role TEXT NOT NULL,
          nconst TEXT NOT NULL,
          PRIMARY KEY(imdb_id, role, nconst)
        );
        CREATE INDEX idx_crew_nconst ON crew(nconst);

        CREATE TABLE people(
          nconst TEXT PRIMARY KEY,
          name TEXT,
          birth_year INTEGER,
          death_year INTEGER,
          professions TEXT,
          known_for TEXT
        );

        CREATE TABLE search_bucket(
          bucket TEXT NOT NULL,
          imdb_id TEXT NOT NULL,
          PRIMARY KEY(bucket, imdb_id)
        );
        CREATE INDEX idx_search_bucket ON search_bucket(bucket);
        """
    )
    return con


def import_provider(con: sqlite3.Connection, work: Path) -> None:
    log("Import liste complete VSEmbed")
    cur = con.cursor()
    for filename, typ in (("movie_imdb.txt", "movie"), ("tv_imdb.txt", "tv")):
        rows = []
        with open(work / filename, "rt", encoding="utf-8", errors="replace") as f:
            for line in f:
                imdb = line.strip().lower()
                if TT_RE.match(imdb):
                    rows.append((imdb, typ))
                    if len(rows) >= 5000:
                        cur.executemany("INSERT OR IGNORE INTO titles(imdb_id,type) VALUES(?,?)", rows)
                        rows.clear()
        if rows:
            cur.executemany("INSERT OR IGNORE INTO titles(imdb_id,type) VALUES(?,?)", rows)
        con.commit()

    rows = []
    with open(work / "eps_imdb.txt", "rt", encoding="utf-8", errors="replace") as f:
        for line in f:
            m = EPS_RE.match(line.strip())
            if not m:
                continue
            rows.append((m.group(1).lower(), int(m.group(2)), int(m.group(3))))
            if len(rows) >= 5000:
                cur.executemany("INSERT OR IGNORE INTO provider_eps(parent_imdb,season,episode) VALUES(?,?,?)", rows)
                rows.clear()
    if rows:
        cur.executemany("INSERT OR IGNORE INTO provider_eps(parent_imdb,season,episode) VALUES(?,?,?)", rows)
    con.commit()
    log(f"Titluri provider: {con.execute('SELECT COUNT(*) FROM titles').fetchone()[0]:,}")
    log(f"Episoade provider: {con.execute('SELECT COUNT(*) FROM provider_eps').fetchone()[0]:,}")


def import_episode_map(con: sqlite3.Connection, path: Path) -> None:
    log("Corelez title.episode cu episoadele disponibile")
    tv_ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles WHERE type='tv'")}
    provider_eps = {(r[0], r[1], r[2]) for r in con.execute("SELECT parent_imdb,season,episode FROM provider_eps")}
    f, reader = open_tsv_gz(path)
    cur = con.cursor(); batch=[]; n=0
    try:
        for row in reader:
            parent = clean(row.get("parentTconst"))
            season = int_or_none(row.get("seasonNumber")); episode = int_or_none(row.get("episodeNumber"))
            if parent not in tv_ids or season is None or episode is None:
                continue
            if (parent, season, episode) not in provider_eps:
                continue
            ep = clean(row.get("tconst"))
            if ep:
                batch.append((ep, parent, season, episode))
                if len(batch) >= 5000:
                    cur.executemany("INSERT OR REPLACE INTO episodes(ep_tconst,parent_imdb,season,episode) VALUES(?,?,?,?)", batch)
                    n += len(batch); batch.clear()
        if batch:
            cur.executemany("INSERT OR REPLACE INTO episodes(ep_tconst,parent_imdb,season,episode) VALUES(?,?,?,?)", batch); n += len(batch)
        con.commit()
    finally:
        f.close()
    log(f"Episoade corelate: {n:,}")


def import_basics(con: sqlite3.Connection, path: Path) -> None:
    log("Import title.basics")
    title_ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles")}
    episode_ids = {r[0] for r in con.execute("SELECT ep_tconst FROM episodes")}
    f, reader = open_tsv_gz(path)
    cur = con.cursor(); tb=[]; eb=[]
    try:
        for row in reader:
            t = row.get("tconst")
            if t in title_ids:
                genres = clean(row.get("genres"))
                tb.append((clean(row.get("primaryTitle")), clean(row.get("originalTitle")), int_or_none(row.get("startYear")), int_or_none(row.get("endYear")), int_or_none(row.get("runtimeMinutes")), genres, t))
                if len(tb) >= 5000:
                    cur.executemany("UPDATE titles SET title=?,original_title=?,year=?,end_year=?,runtime=?,genres=? WHERE imdb_id=?", tb); tb.clear()
            elif t in episode_ids:
                eb.append((clean(row.get("primaryTitle")), clean(row.get("originalTitle")), int_or_none(row.get("startYear")), int_or_none(row.get("runtimeMinutes")), t))
                if len(eb) >= 5000:
                    cur.executemany("UPDATE episodes SET title=?,original_title=?,year=?,runtime=? WHERE ep_tconst=?", eb); eb.clear()
        if tb: cur.executemany("UPDATE titles SET title=?,original_title=?,year=?,end_year=?,runtime=?,genres=? WHERE imdb_id=?", tb)
        if eb: cur.executemany("UPDATE episodes SET title=?,original_title=?,year=?,runtime=? WHERE ep_tconst=?", eb)
        con.commit()
    finally:
        f.close()


def import_ratings(con: sqlite3.Connection, path: Path) -> None:
    log("Import title.ratings")
    title_ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles")}
    episode_ids = {r[0] for r in con.execute("SELECT ep_tconst FROM episodes")}
    f, reader = open_tsv_gz(path); cur=con.cursor(); tb=[]; eb=[]
    try:
        for row in reader:
            t=row.get("tconst"); rating=float_or_none(row.get("averageRating")); votes=int_or_none(row.get("numVotes"))
            if t in title_ids:
                tb.append((rating,votes,t))
                if len(tb)>=5000: cur.executemany("UPDATE titles SET rating=?,votes=? WHERE imdb_id=?",tb);tb.clear()
            elif t in episode_ids:
                eb.append((rating,votes,t))
                if len(eb)>=5000: cur.executemany("UPDATE episodes SET rating=?,votes=? WHERE ep_tconst=?",eb);eb.clear()
        if tb: cur.executemany("UPDATE titles SET rating=?,votes=? WHERE imdb_id=?",tb)
        if eb: cur.executemany("UPDATE episodes SET rating=?,votes=? WHERE ep_tconst=?",eb)
        con.commit()
    finally:
        f.close()


def import_akas(con: sqlite3.Connection, path: Path) -> None:
    log("Import titluri românești din title.akas")
    ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles")}
    chosen = set()
    f, reader = open_tsv_gz(path); cur=con.cursor(); batch=[]
    try:
        for row in reader:
            t=row.get("titleId")
            if t not in ids or t in chosen:
                continue
            region=(clean(row.get("region")) or '').upper(); language=(clean(row.get("language")) or '').lower()
            if region!='RO' and language not in ('ro','ron','rum'):
                continue
            title=clean(row.get("title"))
            if title:
                chosen.add(t); batch.append((title,t))
                if len(batch)>=5000: cur.executemany("UPDATE titles SET romanian_title=? WHERE imdb_id=?",batch);batch.clear()
        if batch: cur.executemany("UPDATE titles SET romanian_title=? WHERE imdb_id=?",batch)
        con.commit()
    finally:
        f.close()
    log(f"Titluri românești găsite: {len(chosen):,}")


def import_crew(con: sqlite3.Connection, path: Path) -> None:
    log("Import title.crew")
    ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles")}
    f, reader = open_tsv_gz(path); cur=con.cursor(); batch=[]
    try:
        for row in reader:
            t=row.get("tconst")
            if t not in ids: continue
            for role,field in (("director","directors"),("writer","writers")):
                raw=clean(row.get(field))
                if not raw: continue
                for nconst in raw.split(','):
                    if nconst: batch.append((t,role,nconst))
            if len(batch)>=5000:
                cur.executemany("INSERT OR IGNORE INTO crew(imdb_id,role,nconst) VALUES(?,?,?)",batch);batch.clear()
        if batch: cur.executemany("INSERT OR IGNORE INTO crew(imdb_id,role,nconst) VALUES(?,?,?)",batch)
        con.commit()
    finally: f.close()


def import_principals(con: sqlite3.Connection, path: Path) -> None:
    log("Import title.principals")
    ids = {r[0] for r in con.execute("SELECT imdb_id FROM titles")}
    f, reader = open_tsv_gz(path); cur=con.cursor(); batch=[]
    try:
        for row in reader:
            t=row.get("tconst")
            if t not in ids: continue
            batch.append((t,int_or_none(row.get("ordering")) or 0,clean(row.get("nconst")) or '',clean(row.get("category")),clean(row.get("job")),clean(row.get("characters"))))
            if len(batch)>=5000:
                cur.executemany("INSERT OR IGNORE INTO principals(imdb_id,ordering,nconst,category,job,characters) VALUES(?,?,?,?,?,?)",batch);batch.clear()
        if batch: cur.executemany("INSERT OR IGNORE INTO principals(imdb_id,ordering,nconst,category,job,characters) VALUES(?,?,?,?,?,?)",batch)
        con.commit()
    finally: f.close()


def import_people(con: sqlite3.Connection, path: Path) -> None:
    log("Import nume necesare din name.basics")
    needed={r[0] for r in con.execute("SELECT DISTINCT nconst FROM principals UNION SELECT DISTINCT nconst FROM crew")}
    log(f"Persoane necesare: {len(needed):,}")
    f, reader = open_tsv_gz(path); cur=con.cursor(); batch=[]
    try:
        for row in reader:
            n=row.get("nconst")
            if n not in needed: continue
            batch.append((n,clean(row.get("primaryName")),int_or_none(row.get("birthYear")),int_or_none(row.get("deathYear")),clean(row.get("primaryProfession")),clean(row.get("knownForTitles"))))
            if len(batch)>=5000:
                cur.executemany("INSERT OR REPLACE INTO people(nconst,name,birth_year,death_year,professions,known_for) VALUES(?,?,?,?,?,?)",batch);batch.clear()
        if batch: cur.executemany("INSERT OR REPLACE INTO people(nconst,name,birth_year,death_year,professions,known_for) VALUES(?,?,?,?,?,?)",batch)
        con.commit()
    finally: f.close()


def title_to_card(row, include_detail=False):
    imdb_id, typ, title, original, ro_title, year, end_year, runtime, genres, rating, votes = row
    card = {
        "imdb_id": imdb_id,
        "type": typ,
        "title": ro_title or title or imdb_id,
        "original_title": original if original and original != (ro_title or title) else None,
        "romanian_title": ro_title,
        "year": year,
        "rating": rating,
        "votes": votes,
    }
    # Listing/genre/year payloads also carry compact factual fields used by the
    # Bratu Marian site for same-type recommendations and richer cards. Search
    # buckets stay compact because they are repeated across many prefixes.
    if include_detail:
        card["end_year"] = end_year
        card["runtime"] = runtime
        card["genres"] = genres.split(",") if genres else []
    return card


def write_json(path: Path, obj) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp=path.with_suffix(path.suffix+'.tmp')
    with open(tmp,'w',encoding='utf-8') as f:
        json.dump(obj,f,ensure_ascii=False,separators=(',',':'))
    tmp.replace(path)


def page_query(con, where, params, page_size, out_dir: Path):
    total=con.execute(f"SELECT COUNT(*) FROM titles WHERE {where}",params).fetchone()[0]
    pages=max(1,math.ceil(total/page_size))
    for p in range(1,pages+1):
        rows=con.execute(f"""SELECT imdb_id,type,title,original_title,romanian_title,year,end_year,runtime,genres,rating,votes
                             FROM titles WHERE {where}
                             ORDER BY (year IS NULL), year DESC, COALESCE(romanian_title,title,imdb_id) COLLATE NOCASE, imdb_id
                             LIMIT ? OFFSET ?""",(*params,page_size,(p-1)*page_size)).fetchall()
        write_json(out_dir/f"page-{p}.json",{"page":p,"pages":pages,"total":total,"items":[title_to_card(r, True) for r in rows]})
    return total,pages


def build_search_buckets(con: sqlite3.Connection, out: Path) -> None:
    log("Construiesc indexul de căutare")
    cur=con.cursor(); batch=[]
    for imdb_id,title,original,ro in con.execute("SELECT imdb_id,title,original_title,romanian_title FROM titles"):
        buckets=set()
        for text in (title,original,ro):
            n=normalize(text or '')
            for word in n.split():
                if len(word)>=2: buckets.add(word[:2])
        buckets.add('tt')
        for b in buckets: batch.append((b,imdb_id))
        if len(batch)>=10000:
            cur.executemany("INSERT OR IGNORE INTO search_bucket(bucket,imdb_id) VALUES(?,?)",batch);batch.clear()
    if batch: cur.executemany("INSERT OR IGNORE INTO search_bucket(bucket,imdb_id) VALUES(?,?)",batch)
    con.commit()
    for (bucket,) in con.execute("SELECT DISTINCT bucket FROM search_bucket ORDER BY bucket"):
        rows=con.execute("""SELECT t.imdb_id,t.type,t.title,t.original_title,t.romanian_title,t.year,t.end_year,t.runtime,t.genres,t.rating,t.votes
                            FROM search_bucket s JOIN titles t ON t.imdb_id=s.imdb_id
                            WHERE s.bucket=? ORDER BY COALESCE(t.romanian_title,t.title,t.imdb_id) COLLATE NOCASE""",(bucket,)).fetchall()
        write_json(out/'search'/f'{bucket}.json',{"items":[title_to_card(r) for r in rows]})


def parse_characters(raw):
    if not raw: return []
    try:
        data=json.loads(raw)
        return [str(x) for x in data] if isinstance(data,list) else []
    except Exception:
        return []


def load_editorial_overrides(path: Path):
    if not path or not path.exists():
        return {}
    try:
        data=json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        log(f"Nu pot citi stratul editorial: {exc}")
        return {}
    if not isinstance(data,dict):
        return {}
    allowed={"description","tagline","poster","background","trailer_youtube","country","language","released","age_rating","source_url","trailer_source_url"}
    out={}
    for imdb,row in data.items():
        if not TT_RE.match(str(imdb)) or not isinstance(row,dict):
            continue
        clean_row={k:v for k,v in row.items() if k in allowed and isinstance(v,(str,int,float,bool)) and str(v).strip()}
        if clean_row:
            out[str(imdb).lower()]=clean_row
    log(f"Override-uri editoriale: {len(out):,}")
    return out


def build_title_shards(con: sqlite3.Connection, out: Path, editorial=None) -> None:
    editorial=editorial or {}
    log("Construiesc paginile virtuale / title shards")
    all_ids=[r[0] for r in con.execute("SELECT imdb_id FROM titles ORDER BY imdb_id")]
    groups=defaultdict(list)
    for imdb in all_ids: groups[imdb[:6]].append(imdb)
    for idx,(prefix,ids) in enumerate(sorted(groups.items()),1):
        marks=','.join('?' for _ in ids)
        title_rows=con.execute(f"SELECT imdb_id,type,title,original_title,romanian_title,year,end_year,runtime,genres,rating,votes FROM titles WHERE imdb_id IN ({marks})",ids).fetchall()
        principals=defaultdict(list)
        for r in con.execute(f"""SELECT p.imdb_id,p.ordering,p.category,p.job,p.characters,p.nconst,n.name
                                  FROM principals p LEFT JOIN people n ON n.nconst=p.nconst
                                  WHERE p.imdb_id IN ({marks}) ORDER BY p.imdb_id,p.ordering""",ids):
            principals[r[0]].append({"nconst":r[5],"name":r[6] or r[5],"category":r[2],"job":r[3],"characters":parse_characters(r[4])})
        crew=defaultdict(lambda:{"director":[],"writer":[]})
        for r in con.execute(f"""SELECT c.imdb_id,c.role,c.nconst,n.name FROM crew c LEFT JOIN people n ON n.nconst=c.nconst
                                  WHERE c.imdb_id IN ({marks}) ORDER BY c.imdb_id,c.role,n.name""",ids):
            crew[r[0]][r[1]].append({"nconst":r[2],"name":r[3] or r[2]})
        episodes=defaultdict(lambda:defaultdict(list))
        tv_ids=[r[0] for r in title_rows if r[1]=='tv']
        if tv_ids:
            m2=','.join('?' for _ in tv_ids)
            for r in con.execute(f"""SELECT parent_imdb,season,episode,ep_tconst,title,original_title,year,runtime,rating,votes
                                      FROM episodes WHERE parent_imdb IN ({m2}) ORDER BY parent_imdb,season,episode""",tv_ids):
                episodes[r[0]][r[1]].append({"episode":r[2],"imdb_id":r[3],"title":r[4] or r[3],"original_title":r[5],"year":r[6],"runtime":r[7],"rating":r[8],"votes":r[9]})
        shard={}
        for r in title_rows:
            imdb,typ,title,orig,ro,year,end_year,runtime,genres,rating,votes=r
            plist=principals.get(imdb,[])
            cast=[x for x in plist if x.get('category') in ('actor','actress','self','archive_footage','archive_sound')]
            other=[x for x in plist if x.get('category') not in ('actor','actress','self','archive_footage','archive_sound')]
            seasons=[]
            if typ=='tv':
                for season_num,eps in sorted(episodes.get(imdb,{}).items()):
                    seasons.append({"season":season_num,"episodes":len(eps),"first_episode":eps[0]['episode'] if eps else None,"last_episode":eps[-1]['episode'] if eps else None,"episodes_data":eps})
            shard[imdb]={
                "imdb_id":imdb,"type":typ,"title":ro or title or imdb,"romanian_title":ro,"original_title":orig,
                "year":year,"end_year":end_year,"runtime":runtime,"genres":genres.split(',') if genres else [],"rating":rating,"votes":votes,
                "directors":crew.get(imdb,{}).get('director',[]),"writers":crew.get(imdb,{}).get('writer',[]),"cast":cast,"credits":other,
                "seasons":seasons,"total_episodes":sum(len(x['episodes_data']) for x in seasons) if seasons else None
            }
            if imdb in editorial:
                shard[imdb]["editorial"]=editorial[imdb]
                for key in ("description","tagline","poster","background","trailer_youtube","country","language","released","age_rating","source_url","trailer_source_url"):
                    if key in editorial[imdb]:
                        shard[imdb][key]=editorial[imdb][key]
        write_json(out/'title-shards'/f'{prefix}.json',shard)
        if idx%500==0: log(f"  shards: {idx:,}/{len(groups):,}")



def build_people_index(con: sqlite3.Connection, out: Path) -> None:
    """Build reverse person -> available catalog credits shards.

    The public site can open an actor/director/writer page without scanning the full
    title catalog. Credits are limited to titles already present in the provider-backed
    catalog and are split by IMDb nconst prefix for efficient delivery.
    """
    log("Construiesc indexul invers pentru actori, regizori și scenariști")
    query = """
        SELECT p.nconst,n.name,n.birth_year,n.death_year,n.professions,
               t.imdb_id,t.type,t.title,t.original_title,t.romanian_title,
               t.year,t.rating,t.votes,t.genres,'actor' AS role
          FROM principals p
          JOIN titles t ON t.imdb_id=p.imdb_id
          LEFT JOIN people n ON n.nconst=p.nconst
         WHERE p.category IN ('actor','actress','self','archive_footage','archive_sound')
        UNION ALL
        SELECT c.nconst,n.name,n.birth_year,n.death_year,n.professions,
               t.imdb_id,t.type,t.title,t.original_title,t.romanian_title,
               t.year,t.rating,t.votes,t.genres,
               CASE WHEN c.role='director' THEN 'regizor' ELSE 'scenarist' END AS role
          FROM crew c
          JOIN titles t ON t.imdb_id=c.imdb_id
          LEFT JOIN people n ON n.nconst=c.nconst
         WHERE c.role IN ('director','writer')
        ORDER BY 1,15,11 DESC,6
    """

    people_dir = out / 'person-shards'
    people_dir.mkdir(parents=True, exist_ok=True)
    current_n = None
    current = None
    seen = None
    shard_prefix = None
    shard = {}
    people_count = 0
    credit_count = 0
    people_ids = []

    def compact_card(row):
        imdb_id, typ, title, original, ro_title, year, rating, votes, genres = row
        display = ro_title or title or imdb_id
        return {
            "imdb_id": imdb_id,
            "type": typ,
            "title": display,
            "original_title": original if original and original != display else None,
            "romanian_title": ro_title,
            "year": year,
            "rating": rating,
            "votes": votes,
            "genres": genres.split(",") if genres else [],
        }

    def flush_person():
        nonlocal current_n, current, seen, people_count
        if current_n and current is not None:
            current["roles"] = {k:v for k,v in current["roles"].items() if v}
            if current["roles"]:
                shard[current_n] = current
                people_ids.append(current_n)
                people_count += 1

    def flush_shard():
        if shard_prefix is not None and shard:
            write_json(people_dir / f"{shard_prefix}.json", shard)

    for row in con.execute(query):
        nconst,name,birth_year,death_year,professions,imdb_id,typ,title,original,ro_title,year,rating,votes,genres,role = row
        if not nconst:
            continue
        if nconst != current_n:
            old_prefix = current_n[:5] if current_n else None
            flush_person()
            new_prefix = nconst[:5]
            if old_prefix is not None and new_prefix != old_prefix:
                flush_shard()
                shard = {}
            shard_prefix = new_prefix
            current_n = nconst
            current = {
                "nconst": nconst,
                "name": name or nconst,
                "birth_year": birth_year,
                "death_year": death_year,
                "professions": professions.split(",") if professions else [],
                "roles": {"actor": [], "regizor": [], "scenarist": []},
            }
            seen = {"actor": set(), "regizor": set(), "scenarist": set()}
        if role not in current["roles"] or imdb_id in seen[role]:
            continue
        current["roles"][role].append(compact_card((imdb_id,typ,title,original,ro_title,year,rating,votes,genres)))
        seen[role].add(imdb_id)
        credit_count += 1

    flush_person()
    flush_shard()
    (out / 'people-ids.txt').write_text('\n'.join(people_ids) + ('\n' if people_ids else ''), encoding='utf-8')
    write_json(out / 'people-meta.json', {
        "people": people_count,
        "credits": credit_count,
        "generated_at": time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),
        "roles": ["actor","regizor","scenarist"],
    })
    log(f"Persoane indexate: {people_count:,}; credite: {credit_count:,}")



def build_latest_feeds(con: sqlite3.Connection, out: Path, limit: int = 70) -> None:
    """Publish homepage feeds ordered by the provider's latest endpoints.

    If a latest endpoint is temporarily unavailable, fall back to a deterministic recent
    selection from the already validated catalog so the daily build can still deploy.
    """
    log("Construiesc fluxurile latest pentru pagina principală")
    specs = {
        "movies": ("movie", f"{VSE_BASE}/movies/latest/page-1.json"),
        "tv": ("tv", f"{VSE_BASE}/tvshows/latest/page-1.json"),
    }
    select_sql = """SELECT imdb_id,type,title,original_title,romanian_title,year,end_year,runtime,genres,rating,votes
                    FROM titles WHERE imdb_id=? AND type=?"""
    fallback_sql = """SELECT imdb_id,type,title,original_title,romanian_title,year,end_year,runtime,genres,rating,votes
                      FROM titles WHERE type=?
                      ORDER BY (year IS NULL), year DESC, imdb_id DESC LIMIT ?"""

    for public_name, (typ, url) in specs.items():
        doc = fetch_json_document(url, attempts=4, timeout=30)
        rows = []
        if isinstance(doc, dict):
            rows = doc.get("result") or doc.get("items") or []
        elif isinstance(doc, list):
            rows = doc
        if not isinstance(rows, list):
            rows = []

        cards = []
        seen = set()
        for provider_item in rows:
            if not isinstance(provider_item, dict):
                continue
            imdb = str(provider_item.get("imdb_id") or provider_item.get("id") or "").strip().lower()
            if not TT_RE.match(imdb) or imdb in seen:
                continue
            dbrow = con.execute(select_sql, (imdb, typ)).fetchone()
            if not dbrow:
                continue
            card = title_to_card(dbrow, True)
            for key in ("quality", "time_added", "poster"):
                value = provider_item.get(key)
                if value not in (None, ""):
                    card[key] = value
            cards.append(card)
            seen.add(imdb)
            if len(cards) >= limit:
                break

        source = "provider_latest"
        if not cards:
            cards = [title_to_card(r, True) for r in con.execute(fallback_sql, (typ, limit)).fetchall()]
            source = "catalog_fallback"

        write_json(out / "latest" / f"{public_name}.json", {
            "generated_at": time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
            "source": source,
            "items": cards,
        })
        log(f"Latest {public_name}: {len(cards)} titluri ({source})")


def build_output(con: sqlite3.Connection, out: Path, page_size: int, editorial=None) -> None:
    if out.exists(): shutil.rmtree(out)
    out.mkdir(parents=True, exist_ok=True)
    log("Generez catalogul paginat")
    movies_total,_=page_query(con,"type='movie'",(),page_size,out/'movies')
    tv_total,_=page_query(con,"type='tv'",(),page_size,out/'tv')

    genres=set(); years=set()
    for genres_raw,year in con.execute("SELECT genres,year FROM titles"):
        if genres_raw:
            genres.update(g for g in genres_raw.split(',') if g)
        if year: years.add(int(year))
    for g in sorted(genres):
        page_query(con,"(','||genres||',') LIKE ?",(f'%,{g},%',),page_size,out/'genres'/slugify(g))
    for y in sorted(years,reverse=True):
        page_query(con,"year=?",(y,),page_size,out/'years'/str(y))

    write_json(out/'meta.json',{
        "schema_version":2,
        "generated_at":time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),
        "items_per_page":page_size,
        "movies":movies_total,"tv":tv_total,"total":movies_total+tv_total,
        "genres":sorted(genres),"years":sorted(years,reverse=True),
        "source_note":"Catalog membership follows the provider ID lists; factual metadata fields are populated only when present in the imported datasets."
    })
    build_latest_feeds(con,out,page_size)
    build_search_buckets(con,out)
    build_title_shards(con,out,editorial)
    build_people_index(con,out)


def main():
    ap=argparse.ArgumentParser()
    ap.add_argument('--out',default='public/data')
    ap.add_argument('--work',default='.catalog-work')
    ap.add_argument('--vse-cache',default='.catalog-cache/vse')
    ap.add_argument('--page-size',type=int,default=70)
    ap.add_argument('--force-download',action='store_true')
    ap.add_argument('--editorial',default='editorial/overrides.json')
    args=ap.parse_args()
    out=Path(args.out).resolve(); work=Path(args.work).resolve(); work.mkdir(parents=True,exist_ok=True)
    downloads=work/'downloads'; downloads.mkdir(exist_ok=True)
    vse_cache=Path(args.vse_cache).resolve(); vse_cache.mkdir(parents=True,exist_ok=True)

    # VSE determines catalog membership, so check it first. A temporary provider
    # outage can fall back to the most recent validated ID lists restored by Actions cache.
    for fn,url in VSE_FILES.items():
        validator=lambda p, filename=fn: validate_vse_list(p, filename)
        download(url,downloads/fn,args.force_download,attempts=5,fallback=vse_cache/fn,validator=validator)
        shutil.copy2(downloads/fn,vse_cache/fn)

    # IMDb files are large; only start downloading them after VSE lists are available.
    for fn in IMDB_FILES:
        download(f"{IMDB_BASE}/{fn}",downloads/fn,args.force_download,attempts=4)

    db=work/'catalog.sqlite'; con=init_db(db)
    try:
        import_provider(con,downloads)
        import_episode_map(con,downloads/'title.episode.tsv.gz')
        import_basics(con,downloads/'title.basics.tsv.gz')
        import_ratings(con,downloads/'title.ratings.tsv.gz')
        import_akas(con,downloads/'title.akas.tsv.gz')
        import_crew(con,downloads/'title.crew.tsv.gz')
        import_principals(con,downloads/'title.principals.tsv.gz')
        import_people(con,downloads/'name.basics.tsv.gz')
        editorial=load_editorial_overrides(Path(args.editorial).resolve())
        build_output(con,out,args.page_size,editorial)
    finally:
        con.close()
    log(f"Gata. Date: {out}")


if __name__=='__main__':
    main()