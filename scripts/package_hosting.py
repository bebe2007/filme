"""Build a reproducible, credential-free PHP interface release (data stays on hosting)."""
import argparse,hashlib,json,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def files():
    for p in sorted((ROOT/'hosting').rglob('*')):
        if not p.is_file():continue
        rel=p.relative_to(ROOT/'hosting')
        if any(part in ('data','cache') for part in rel.parts):continue
        if rel.name in ('config.php','config.local.php','config.local.example.php','README.md') or rel.name.startswith('test-') or rel.suffix=='.zip':continue
        yield rel,p.read_bytes()
def main():
    ap=argparse.ArgumentParser();ap.add_argument('output',type=Path);ap.add_argument('--preview',action='store_true');a=ap.parse_args();entries=dict(files())
    if a.preview:
        preview={Path('v15-preview')/rel:content for rel,content in entries.items()}
        for rel,content in entries.items():
            if str(rel).startswith('assets/'):preview[rel]=content
        preview[Path('v15-preview/config.php')]=b"<?php return require dirname(__DIR__).'/config.php';\n"
        preview[Path('v15-preview/config.local.php')]=b"<?php $p=dirname(__DIR__).'/config.local.php';return is_file($p)?require $p:[];\n"
        key=Path('v15-preview/includes/catalog-sync.php');preview[key]=preview[key].replace(b"dirname(__DIR__).'/data'",b"dirname(__DIR__,2).'/data'")
        key=Path('v15-preview/index.php');preview[key]=preview[key].replace(b"$robots='index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';",b"$robots='noindex,nofollow';").replace(b"'/assets/css/site.css'",b"'/assets/css/site.css'")
        # Keep preview requests on the tested API, while its links retain public canonical routes.
        key=Path('assets/js/app-v15-preview.js');preview[key]=entries[Path('assets/js/app-v15.js')].replace(b"'/api.php?",b"'/v15-preview/api.php?")
        key=Path('v15-preview/index.php');preview[key]=preview[key].replace(b'/assets/js/app-v15.js',b'/assets/js/app-v15-preview.js')
        preview[Path('v15-preview/.htaccess')]=b'Options -Indexes\nRewriteEngine On\nRewriteRule ^(?:cache|tools)/ - [F,L]\n<FilesMatch "^config.*\\.php$">\nRequire all denied\n</FilesMatch>\n'
        entries=preview
    manifest={str(k):hashlib.sha256(v).hexdigest() for k,v in entries.items()}
    entries[Path('RELEASE-v15.json')]=json.dumps({'version':'15.0.0','files':manifest},indent=2).encode()
    a.output.parent.mkdir(parents=True,exist_ok=True)
    with zipfile.ZipFile(a.output,'w',zipfile.ZIP_DEFLATED) as z:
        for rel,content in sorted(entries.items()):
            entry=zipfile.ZipInfo(str(rel));entry.external_attr=0o100644 << 16;entry.compress_type=zipfile.ZIP_DEFLATED;z.writestr(entry,content)
    print(json.dumps({'output':str(a.output),'files':len(entries),'bytes':a.output.stat().st_size,'sha256':hashlib.sha256(a.output.read_bytes()).hexdigest()}))
if __name__=='__main__':main()
