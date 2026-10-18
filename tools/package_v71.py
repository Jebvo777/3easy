"""Build hosting deliverables. Verification is a separate, post-publication step."""
from pathlib import Path
import argparse
import hashlib
import shutil
import zipfile

p = argparse.ArgumentParser()
p.add_argument('--output', required=True)
p.add_argument('--commit', required=True)
p.add_argument('--db-config')
p.add_argument('--ai-config')
a = p.parse_args()
if bool(a.db_config) != bool(a.ai_config):
    p.error('Supply both private configurations, or neither')
private = bool(a.db_config)
root = Path(__file__).resolve().parents[1]
out = Path(a.output).resolve()
out.mkdir(parents=True, exist_ok=True)
stage = out / 'hosting'
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()
for src in (root / 'app').rglob('*'):
    if not src.is_file():
        continue
    rel = src.relative_to(root / 'app')
    if any(part in {'node_modules', '.git', '__pycache__', 'output'} for part in rel.parts):
        continue
    if rel.parts[0] == 'storage' and src.name != '.htaccess':
        continue
    if src.name in {'.gitignore', 'config.local.php', 'config.ai.local.php'} or src.suffix == '.pyc':
        continue
    dst = stage / rel
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(src, dst)
if private:
    for original, names in [(a.db_config, ['config.example.php', 'config.local.php']),
                            (a.ai_config, ['config.ai.example.php', 'config.ai.local.php'])]:
        for name in names:
            shutil.copyfile(original, stage / name)
(stage / 'docs').mkdir(exist_ok=True)
(stage / 'docs' / '.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261018_71.md', 'RELEASE_20261018.md', 'AI_V7.md', 'RELEASE_20261017.md', 'RELEASE_20261017_61.md', 'RELEASE_20261017_62.md', 'FIX_LUNA_20261016.md', 'PLAN_RECORDING_20261013.md']:
    content = (root / 'docs' / name).read_text(encoding='utf-8').replace('../app/QA_REPORT_', '../QA_REPORT_')
    (stage / 'docs' / name).write_text(content, encoding='utf-8')
(stage / 'VERSION.txt').write_text('EASY3 v7.1 / 2026-10-18\nGitHub source: ' + a.commit + '\n', encoding='utf-8')
configuration = (
    '4. Ваши прежние реквизиты уже включены в example и local-файлы в корне.\n'
    '   Повторно вводить их не нужно. Для последующих изменений используйте local-файлы.\n'
    '   Этот архив с реквизитами предназначен только для вашего хостинга.\n'
    if private else
    '4. Сохраните свои config.local.php и config.ai.local.php перед заменой файлов.\n'
    '   Публичная поставка содержит только примеры без ключей и паролей.\n'
)
(stage / 'START-HERE.txt').write_text(
    'EASY3 v7.1: обновление 18 октября 2026\n\n'
    '1. Сохраните резервную копию файлов сайта и текущей БД.\n'
    '2. При обновлении с v6.2 SQL не требуется: структура БД не меняется.\n'
    '   Для более ранних версий используйте прежнее обновление из UPGRADE.md.\n'
    '   install.sql предназначен только для НОВОЙ учебной установки.\n'
    '3. Загрузите все файлы из этого архива в корень сайта. index.php уже в корне.\n' + configuration +
    '5. PHP 8.2+; MySQL 5.7+/MariaDB; PDO MySQL, cURL, JSON, sessions, DOM/XML,\n'
    '   zlib, iconv, mbstring. Composer, Node.js, Python и OCR на хостинге не нужны.\n'
    '6. Для загрузки документов разрешите PHP запись в storage/imports;\n'
    '   upload_max_filesize не менее 12M, post_max_size не менее 16M.\n'
    '7. После обновления продолжите сохранённую генерацию или создайте учебный сеанс.\n'
    '   Нормы AI проверяются человеком. Сценарии не публикуют задания автоматически.\n\n'
    'Включены видео высокого разрешения, каталог операций и технические примеры.\n'
    'Проверки поставки описаны в QA_REPORT_V71.md. Подробности: UPGRADE.md.\n', encoding='utf-8')
archive = out / ('EASY3_v7_1_20261018' + ('' if private else '_public') + '.zip')
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for src in sorted(stage.rglob('*')):
        if src.is_file():
            z.write(src, src.relative_to(stage).as_posix())
(out / 'CHECKSUMS-v71.sha256').write_text(
    hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n',
    encoding='utf-8')
print('Created', archive.name, archive.stat().st_size, 'bytes; SQL not required for v6.2 update')
