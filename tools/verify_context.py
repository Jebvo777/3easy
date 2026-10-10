"""Check the source register, authored links and structural reference counts.

This verifies the documentation package, not the production application.
"""
from pathlib import Path
from urllib.parse import unquote
import hashlib
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
errors = []

def check(condition, message):
    if not condition:
        errors.append(message)

def sha256(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

manifest = json.loads((ROOT / 'sources/source_manifest.json').read_text())
check(len(manifest) == 20, 'Expected 20 provided sources')
check(len({x['id'] for x in manifest}) == 20, 'Source IDs must be unique')
for item in manifest:
    path = ROOT / item['archive_path']
    check(path.is_file(), f'Missing source: {path}')
    if not path.is_file():
        continue
    check(sha256(path) == item['stored_sha256'], f'Stored hash mismatch: {path}')
    check(path.stat().st_size == item['stored_bytes'], f'Size mismatch: {path}')
    if item['edition'] == 'original':
        check(sha256(path) == item['original_sha256'], f'Original changed: {path}')
    if item.get('text_path'):
        p = ROOT / item['text_path']
        check(p.is_file(), f'Missing text: {p}')
        if p.is_file():
            check(sha256(p) == item['text_sha256'], f'Text hash mismatch: {p}')
    for derived in item.get('derived_paths', []):
        check((ROOT / derived).is_file(), f'Missing derived file: {derived}')

# Archived prose can contain historical unavailable references. Check authored
# navigation only, keeping the historical source text unmodified.
authored = [ROOT/'README.md', ROOT/'AGENTS.md', ROOT/'sources/README.md',
            ROOT/'demos/README.md', ROOT/'references/README.md',
            ROOT/'sources/conversations/CURRENT_BRIEF.md'] + sorted((ROOT/'docs').glob('*.md'))
link_count = 0
for path in authored:
    for match in re.finditer(r'(?<!!)\[[^\]]*\]\(([^)]+)\)', path.read_text()):
        target = match.group(1).split('#', 1)[0]
        if not target or re.match(r'^[a-zA-Z][a-zA-Z0-9+.-]*:', target):
            continue
        link_count += 1
        destination = (path.parent / unquote(target)).resolve()
        check(destination.is_relative_to(ROOT), f'Link escapes repo: {path}: {target}')
        check(destination.exists(), f'Broken link: {path}: {target}')

sql = (ROOT/'references/schema/EASY3_schema.sql').read_text()
tables = re.findall(r'CREATE TABLE\s+(\w+)\s*\((.*?)\);', sql, re.S|re.I)
columns = sum(sum(bool(re.match(r'\s*\w+\s+(?:uuid|text|varchar|numeric|integer|int|bigint|boolean|date|time|timestamptz|jsonb)\b', line, re.I))
                  for line in body.splitlines()) for _,body in tables)
stats = {'tables':len(tables), 'columns':columns,
         'foreign_keys':len(re.findall(r'\bFOREIGN KEY\b', sql, re.I)),
         'checks':len(re.findall(r'\bCHECK\s*\(', sql, re.I))}
check(stats == {'tables':61,'columns':502,'foreign_keys':166,'checks':5}, f'Unexpected SQL stats: {stats}')
pumls=list((ROOT/'references/diagrams').glob('*.puml'))
pngs=list((ROOT/'references/diagrams').glob('*.png'))
check(len(pumls)==9 and len(pngs)==9, 'Expected 9 PUML and 9 PNG')

index=json.loads((ROOT/'sources/conversations/chat-message-index.json').read_text())
chat=json.loads((ROOT/'sources/conversations/technical-chat.json').read_text())
check(len(index)==52, 'Expected 52 indexed chat messages')
check(len(chat)==17, 'Expected 17 technical message editions')
check({m['message'] for m in chat} == {m['message'] for m in index if m['included']}, 'Chat edition/index mismatch')
call=(ROOT/'sources/conversations/technical-call.md').read_text()
check(all(f'Звонок {n}' in call for n in range(1,5)), 'All four call parts must be covered')

report={'status':'passed' if not errors else 'failed','provided_sources':len(manifest),
        'preserved_originals':sum(x['edition']=='original' for x in manifest),
        'technical_editions':sum(x['edition']!='original' for x in manifest),
        'authored_relative_links_checked':link_count,'sql':stats,
        'diagrams':{'plantuml':len(pumls),'png':len(pngs)},
        'chat':{'indexed':len(index),'technical':len(chat)},
        'limitations':['No PostgreSQL execution','No full browser interaction/render verification',
                       'No real pilot financial reconciliation','No AI-provider integration or model training'],
        'errors':errors}
print(json.dumps(report,ensure_ascii=False,indent=2))
if '--report' in sys.argv:
    (ROOT/'docs/verification-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
sys.exit(bool(errors))
