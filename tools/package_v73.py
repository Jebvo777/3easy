"""Build an update archive preserving all four installed configuration files."""
from pathlib import Path
import argparse, shutil, zipfile

p = argparse.ArgumentParser()
p.add_argument('--output', type=Path, required=True)
p.add_argument('--commit', required=True)
a = p.parse_args()
root = Path(__file__).resolve().parents[1]
out = a.output.resolve()
out.mkdir(parents=True, exist_ok=True)
stage = out / 'hosting-v73'
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()
configs = {'config.example.php', 'config.ai.example.php', 'config.local.php', 'config.ai.local.php'}
for src in (root / 'app').rglob('*'):
    if not src.is_file():
        continue
    rel = src.relative_to(root / 'app')
    if any(part in {'node_modules', '.git', '__pycache__', 'output', 'qa'} for part in rel.parts):
        continue
    if rel.parts[0] == 'storage' and src.name != '.htaccess':
        continue
    if src.name in configs | {'.gitignore'} or src.suffix == '.pyc':
        continue
    dest = stage / rel
    dest.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(src, dest)
(stage / 'docs').mkdir(exist_ok=True)
(stage / 'docs/.htaccess').write_text('Require all denied\n')
shutil.copyfile(root / 'docs/RELEASE_20261025.md', stage / 'docs/RELEASE_20261025.md')
(stage / 'VERSION.txt').write_text('EASY3 v7.3 / 2026-10-25\nSource: ' + a.commit + '\n')
(stage / 'START-HERE.txt').write_text('''EASY3 7.3 — обновление 25 октября 2026

1. Импортируйте database/update_20261025.sql (тот же скрипт, что приложен отдельно) в вашу текущую базу MySQL 5.7 через phpMyAdmin. Создаются только три новые таблицы; старые таблицы и данные не переписываются.
2. Загрузите всё содержимое этого архива ПОВЕРХ существующего сайта. index.php находится в корне. Не удаляйте старый каталог перед загрузкой.
3. Конфигурация сохраняется на сервере: config.example.php, config.ai.example.php, config.local.php и config.ai.local.php отсутствуют в обновлении. Не удаляйте и не заменяйте эти четыре файла. Повторный ввод доступов не нужен.
4. Сохраните прежнюю папку storage с документами. В архиве нет пользовательских документов и дампа вашей базы.
5. Обновите страницу Ctrl+F5. Все JS/CSS уже собраны; установка Node/Python на хостинге не требуется.

install.sql и демонстрационные наполнения при обновлении НЕ запускать.
Этот архив предназначен для существующей установки, а не для нового пустого сайта.
Состав правок: docs/RELEASE_20261025.md. Базовые проверки: QA_REPORT_V73.md.
''')
archive = out / 'EASY3_v7_3_20261025_update.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for f in sorted(stage.rglob('*')):
        if f.is_file():
            z.write(f, f.relative_to(stage).as_posix())
shutil.copyfile(root / 'app/database/update_20261025.sql', out / 'EASY3_update_20261025_mysql57.sql')
print(archive)
