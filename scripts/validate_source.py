"""Syntax and runtime checks for the complete application, including legacy scripts."""
import os,re,subprocess,tempfile,xml.etree.ElementTree as ET
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def run(args):
    result=subprocess.run(args,cwd=ROOT,text=True,capture_output=True)
    if result.returncode or re.search(r'(?:Fatal error|Parse error):',result.stdout+result.stderr,re.I):
        raise SystemExit(result.stdout+result.stderr)
php=os.environ.get('PHP_BINARY','php')
files=list((ROOT/'hosting').rglob('*.php'))
for path in files:run([php,'-l',str(path)])
js_count=0
for path in ROOT.rglob('*'):
    if not path.is_file() or '.git' in path.parts:continue
    if path.suffix=='.js':run(['node','--check',str(path)]);js_count+=1
    elif path.suffix in ('.html','.xml'):
        for script in re.findall(r'<script[^>]*>(.*?)</script>',path.read_text(),re.S):
            script=script.replace('//<![CDATA[','').replace('//]]>','')
            with tempfile.NamedTemporaryFile(suffix='.js',mode='w') as f:
                f.write(script);f.flush();run(['node','--check',f.name])
            js_count+=1
    if path.suffix=='.svg':ET.parse(path)
for name in ('test-sync.php','test-collections.php','test-runtime.php','test-discover.php'):run([php,str(ROOT/'hosting'/name)])
print(f'{len(files)} PHP files, {js_count} JavaScript scripts, SVG syntax and PHP runtime checks passed.')
