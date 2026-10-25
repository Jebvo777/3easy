"""Build a public update or a complete private delivery with supplied configuration."""
from pathlib import Path
import argparse, shutil, zipfile

p = argparse.ArgumentParser()
p.add_argument('--output', type=Path, required=True)
p.add_argument('--commit', required=True)
p.add_argument('--config-zip', type=Path, help='Private configuration ZIP; produces a complete configured delivery')
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
if a.config_zip:
    supplied = {}
    with zipfile.ZipFile(a.config_zip) as z:
        for item in z.infolist():
            name = Path(item.filename).name
            if not item.is_dir() and name in configs:
                if name in supplied:
                    raise ValueError('Duplicate configuration file: ' + name)
                if item.file_size > 1024 * 1024:
                    raise ValueError('Configuration file is too large: ' + name)
                supplied[name] = z.read(item)
    for example, local in [('config.example.php', 'config.local.php'), ('config.ai.example.php', 'config.ai.local.php')]:
        if example not in supplied:
            raise ValueError('Missing supplied configuration: ' + example)
        supplied.setdefault(local, supplied[example])
    for name, content in supplied.items():
        (stage / name).write_bytes(content)
    (stage / 'storage/imports').mkdir(parents=True, exist_ok=True)
    (stage / 'storage/imports/.htaccess').write_text('Require all denied\n')
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
if a.config_zip:
    full_instructions = '''EASY3 7.3 — полный проект с конфигурацией, 25 октября 2026

Архив содержит все файлы приложения, готовые JS/CSS, локальные библиотеки, медиа и вашу конфигурацию. index.php находится в корне архива. Можно загружать в пустой каталог после удаления старых файлов сайта.

1. Перед удалением скачайте с хостинга папку storage: в ней могут быть загруженные документы. Ваши документы с сервера не были присланы, поэтому в архиве их нет. После загрузки проекта верните сохранённое содержимое storage.
2. Базу данных на сервере оставьте существующую. Если обновление 25.10 ещё не применялось, импортируйте database/update_20261025.sql в эту базу через phpMyAdmin. Отдельный EASY3_update_20261025_mysql57.sql содержит тот же скрипт; повторный импорт не требуется. MySQL 5.7 поддерживается. install.sql и демонаполнения для действующей базы не запускать.
3. Загрузите ВСЁ содержимое архива в корень сайта, включая .htaccess и папки assets, lib, vendor, storage. Можно заменить старый код целиком, сохранив и затем вернув документы из пункта 1.
4. Присланные config.example.php и config.ai.example.php включены побайтно без изменений. В config.local.php и config.ai.local.php находятся их точные копии, если отдельные local-файлы не были приложены. Настройки БД и AI повторно вводить не нужно.
5. Обеспечьте PHP запись в storage/imports. Нужны PHP 8.2+ и прежние расширения. Node, Python и Composer на хостинге не требуются.
6. Обновите страницу Ctrl+F5.

SQL добавляет три таблицы и не переписывает существующие данные. Архив не является копией вашей базы и пользовательских документов.
Состав правок: docs/RELEASE_20261025.md. Базовые проверки приложения: QA_REPORT_V73.md.
'''
    (stage / 'START-HERE.txt').write_text(full_instructions)
    (stage / 'UPGRADE.md').write_text('# Полная поставка EASY3 7.3\n\n' + full_instructions)
    (stage / 'README.md').write_text('# EASY3 7.3 · полный проект с конфигурацией\n\n' + full_instructions)
    qa = stage / 'QA_REPORT_V73.md'
    qa.write_text(qa.read_text().replace('Все четыре config-файла исключены из обновления, чтобы при распаковке поверх сайта сохранить текущие настройки.', 'В полной частной поставке два присланных example-файла сохранены побайтно; local-файлы созданы как их точные копии при отсутствии отдельных local-файлов.'))
archive = out / ('EASY3_v7_3_20261025_full_configured.zip' if a.config_zip else 'EASY3_v7_3_20261025_update.zip')
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for f in sorted(stage.rglob('*')):
        if f.is_file():
            z.write(f, f.relative_to(stage).as_posix())
shutil.copyfile(root / 'app/database/update_20261025.sql', out / 'EASY3_update_20261025_mysql57.sql')
print(archive)
