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
check(len(manifest) == 22, 'Expected 22 provided sources')
check({x['id'] for x in manifest} == {f'S{n:02d}' for n in range(1,23)},
      'Source register must cover S01 through S22 exactly once')
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
authored += sorted((ROOT/'sources/recordings').glob('*.md'))
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

# Check independent transcript representations and provenance, including every
# raw ASR ID retained through an explicitly labeled exclusion or summary.
recordings = []
for sid in ['S21','S22']:
    entry = next(x for x in manifest if x['id'] == sid)
    data = json.loads((ROOT/entry['archive_path']).read_text())
    for key in ['original_name','original_bytes','original_sha256','duration_seconds']:
        check(data[key] == entry[key], f'{sid}: provenance mismatch for {key}')
    check(data['original_in_repository'] is False, f'{sid}: original video must be distinguished from edition')
    check(data['edition'] == 'asr-technical-public-edition', f'{sid}: edition label missing')
    check(data['asr']['speaker_diarization'] is False, f'{sid}: unperformed diarization claimed')
    check(bool(re.fullmatch(r'[0-9a-f]{64}',data['asr']['model_weights_sha256'])),
          f'{sid}: invalid model SHA-256')
    check(data['audio_extraction']['sample_rate_hz']==16000 and
          data['audio_extraction']['channels']==1, f'{sid}: unexpected audio format')
    segments=data['segments']
    check(len(segments)==entry['public_segment_count']==data['public_segment_count'],
          f'{sid}: public segment count mismatch')
    raw_ids=[n for s in segments for n in s['source_segment_ids']]
    check(raw_ids==list(range(1,data['asr']['raw_segment_count']+1)),
          f'{sid}: missing, duplicated or reordered source ASR segments')
    previous_end=0
    for pos,s in enumerate(segments,1):
        check(s['id']==pos, f'{sid}: invalid public segment numbering')
        check(0<=s['start_seconds']<s['end_seconds']<=data['duration_seconds'],
              f'{sid}: segment {pos} outside recording')
        check(s['start_seconds']>=previous_end-0.001,
              f'{sid}: overlapping or reordered segment {pos}')
        previous_end=s['end_seconds']
        check(bool(s['text'].strip()), f'{sid}: empty transcript segment {pos}')
        check(s['kind'] in ['asr','technical_summary','redacted_non_project','unclear_speech'],
              f'{sid}: unlabeled edition kind')
    srt=(ROOT/f'sources/recordings/{sid}-transcript.srt').read_text().strip().split('\n\n')
    check(len(srt)==len(segments),f'{sid}: SRT segment count mismatch')
    def srt_seconds(value):
        hours,minutes,seconds=value.replace(',','.').split(':')
        return int(hours)*3600+int(minutes)*60+float(seconds)
    for block,s in zip(srt,segments):
        lines=block.splitlines()
        check(lines[0]==str(s['id']),f'{sid}: SRT ID mismatch')
        start,end=lines[1].split(' --> ')
        check(abs(srt_seconds(start)-s['start_seconds'])<=0.00051 and
              abs(srt_seconds(end)-s['end_seconds'])<=0.00051,
              f'{sid}: SRT timing mismatch at {s["id"]}')
        check('\n'.join(lines[2:])==s['text'],f'{sid}: SRT text mismatch at {s["id"]}')
    md=(ROOT/entry['text_path']).read_text()
    check(all(f'**{sid}-{s["id"]:03d} ·' in md and s['text'] in md for s in segments),
          f'{sid}: Markdown edition missing transcript content')
    recordings.append({'source_id':sid,'video_duration_seconds':data['duration_seconds'],
                       'audio_processed_seconds':data['asr']['audio_processed_seconds'],
                       'asr_segments':data['asr']['raw_segment_count'],
                       'public_segments':len(segments),'all_asr_ids_accounted_for':True,
                       'unclear_segments':sum(bool(s.get('needs_audio_confirmation') or s['kind']=='unclear_speech') for s in segments),
                       'original_video_in_repository':False})

frames=json.loads((ROOT/'sources/recordings/frames-index.json').read_text())
check(len(frames)==5,'Expected five reviewed UI excerpts')
for frame in frames:
    p=ROOT/frame['path']
    check(p.is_file() and sha256(p)==frame['sha256'],f'Frame hash mismatch: {p}')
    check(p.stat().st_size==frame['bytes'],f'Frame size mismatch: {p}')
    check(frame['source_id']=='S21' and 0<=frame['seconds']<1931.183,
          f'Frame source/time mismatch: {p}')

report={'status':'passed' if not errors else 'failed','provided_sources':len(manifest),
        'preserved_originals':sum(x['edition']=='original' for x in manifest),
        'technical_editions':sum(x['edition']!='original' for x in manifest),
        'authored_relative_links_checked':link_count,'sql':stats,
        'diagrams':{'plantuml':len(pumls),'png':len(pngs)},
        'chat':{'indexed':len(index),'technical':len(chat)},
        'recordings':recordings,'reviewed_ui_frames':len(frames),
        'limitations':['No PostgreSQL execution',
                       'Recorded UI frames reviewed; no independent full browser interaction/render verification',
                       'Machine ASR editions; no certified verbatim transcript or speaker diarization',
                       'No real pilot financial reconciliation','No production AI-provider integration or model training'],
        'errors':errors}
print(json.dumps(report,ensure_ascii=False,indent=2))
if '--report' in sys.argv:
    (ROOT/'docs/verification-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
sys.exit(bool(errors))
