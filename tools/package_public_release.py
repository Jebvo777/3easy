"""Build a public ZIP or a private hosting copy; secrets never enter Git."""
from pathlib import Path
import argparse
import hashlib
import json
import shutil
import subprocess
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
parser.add_argument('--commit', required=True)
parser.add_argument('--db-config')
parser.add_argument('--ai-config')
args = parser.parse_args()
if bool(args.db_config) != bool(args.ai_config):
    parser.error('Supply both private configuration files, or neither')
private = bool(args.db_config)
root = Path(__file__).resolve().parents[1]
out = Path(args.output).resolve()
out.mkdir(parents=True, exist_ok=True)
stage = out / 'hosting'
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()
for source in (root / 'app').rglob('*'):
    if not source.is_file():
        continue
    relative = source.relative_to(root / 'app')
    if any(part in {'node_modules', '__pycache__', 'output', '.git'} for part in relative.parts):
        continue
    if source.name in {'.gitignore', 'config.local.php', 'config.ai.local.php'} or source.suffix == '.pyc':
        continue
    destination = stage / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, destination)
if private:
    shutil.copyfile(args.db_config, stage / 'config.local.php')
    shutil.copyfile(args.ai_config, stage / 'config.ai.local.php')
(stage / 'VERSION.txt').write_text('EASY3 v5.7 / 2026-10-16\nGitHub source: ' + args.commit + '\n', encoding='utf-8')
configuration = (
    '4. Включены config.local.php и config.ai.local.php с вашими прежними настройками.\n'
    '   Если вы меняли подключение после отправки исходного архива, сохраните свою конфигурацию.\n'
    '   Частный архив с ключом не предназначен для публичного размещения.\n'
    if private else
    '4. Сохраните на сервере свои config.local.php и config.ai.local.php.\n'
    '   Публичный ZIP не содержит ключей. Если ключ раньше находился в config.ai.example.php,\n'
    '   перенесите его в config.ai.local.php до замены файлов. Аналогично сохраните настройки БД.\n'
)
(stage / 'START-HERE.txt').write_text(
    'EASY3 v5.7: обновление 16 октября 2026\n\n'
    '1. Сохраните резервную копию сайта и БД.\n'
    '2. Импортируйте database/update_20261016.sql в текущую БД system404_easy3 через phpMyAdmin.\n'
    '   Это повторяемое обновление, без PREPARE, CHECK и новых внешних ключей.\n'
    '   Оно также освобождает только просроченные запросы генерации, не удаляя прогресс.\n'
    '   install.sql используется только для новой установки.\n'
    '3. Загрузите содержимое ZIP в корень сайта: index.php уже на верхнем уровне.\n' + configuration +
    '5. Основная модель — gpt-6-luna. Остальные включаются при её недоступности.\n'
    '   Расширение PHP cURL и исходящий HTTPS к прежнему провайдеру должны быть доступны.\n'
    '6. Откройте сайт и при необходимости продолжите сохранённую генерацию.\n'
    '   Генерация идёт небольшими порциями; общий размер технологии не ограничен.\n\n'
    'Фоновое видео включено. Node.js и сборка на хостинге не требуются. Подробности: UPGRADE.md.\n', encoding='utf-8')
(stage / 'docs').mkdir(exist_ok=True)
(stage / 'docs' / '.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261016.md', 'PLAN_IMPLEMENTATION_20261016.md', 'AI_TECHNOLOGY.md', 'DESKTOP_20261016.md', 'FIX_LUNA_20261016.md']:
    shutil.copyfile(root / 'docs' / name, stage / 'docs' / name)
archive = out / ('EASY3_v5_7_20261016' + ('' if private else '_public') + '.zip')
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as handle:
    for source in sorted(stage.rglob('*')):
        if source.is_file():
            handle.write(source, source.relative_to(stage).as_posix())
sql = out / 'EASY3_update_v5_7_20261016.sql'
shutil.copyfile(root / 'app/database/update_20261016.sql', sql)
# Basic checks run after the implementation commit has been published.
with zipfile.ZipFile(archive) as handle:
    names = set(handle.namelist())
    manifest = json.loads(handle.read('assets/manifest.json'))
    assert {'index.php', 'database/update_20261016.sql', 'START-HERE.txt',
            'assets/media/workspace-loop-20261016-fixed.mp4', 'data/operations.json',
            'data/examples.json', manifest['js'], manifest['css']} <= names
    assert ({'config.local.php', 'config.ai.local.php'} <= names) if private else not ({'config.local.php', 'config.ai.local.php'} & names)
    assert handle.read('database/update_20261016.sql') == sql.read_bytes()
    assert handle.testzip() is None, 'ZIP CRC failure'
    for kind in ['js', 'css']:
        payload = handle.read(manifest[kind])
        payload.decode('utf-8', errors='strict')
        expected = manifest[kind].rsplit('/', 1)[-1].split('.')[1]
        assert hashlib.sha256(payload).hexdigest().startswith(expected), 'Asset filename/hash mismatch'
    result = subprocess.run(['node', '--check', '--input-type=module'], input=handle.read(manifest['js']), capture_output=True)
    if result.returncode:
        raise RuntimeError('Packaged JS does not parse: ' + result.stderr.decode(errors='replace')[:500])
    for name in ['data/operations.json', 'data/examples.json']:
        json.loads(handle.read(name))
notes = 'EASY3 v5.7 / source ' + args.commit + '\n'
notes += 'Private configuration included. Do not publish this ZIP.\n' if private else 'Public archive; preserve server configuration.\n'
for artifact in [archive, sql]:
    notes += hashlib.sha256(artifact.read_bytes()).hexdigest() + '  ' + artifact.name + '\n'
(out / 'CHECKSUMS.sha256').write_text(notes, encoding='utf-8')
print('Created', archive.name, archive.stat().st_size, 'bytes;', sql.name, sql.stat().st_size, 'bytes')
