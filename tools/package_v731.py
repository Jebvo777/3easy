"""Package the QR/demo correction for overlay deployment. No checks or app startup."""
from pathlib import Path
import argparse, shutil, zipfile

p=argparse.ArgumentParser()
p.add_argument('--output',type=Path,required=True)
p.add_argument('--commit',required=True)
p.add_argument('--config-zip',type=Path)
a=p.parse_args()
root=Path(__file__).resolve().parents[1]
out=a.output.resolve();out.mkdir(parents=True,exist_ok=True)
stage=out/('hosting-v731-private' if a.config_zip else 'hosting-v731-public')
if stage.exists():shutil.rmtree(stage)
stage.mkdir()
config_names={'config.example.php','config.ai.example.php','config.local.php','config.ai.local.php'}
for src in (root/'app').rglob('*'):
 if not src.is_file():continue
 rel=src.relative_to(root/'app')
 if any(part in {'node_modules','.git','__pycache__','output','qa'} for part in rel.parts):continue
 if rel.parts[0]=='storage' and src.name!='.htaccess':continue
 if src.name in config_names|{'.gitignore'} or src.suffix=='.pyc':continue
 dest=stage/rel;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dest)
if a.config_zip:
 files={}
 with zipfile.ZipFile(a.config_zip) as z:
  for item in z.infolist():
   name=Path(item.filename).name
   if item.is_dir() or name not in config_names:continue
   if name in files:raise ValueError('Duplicate configuration: '+name)
   if item.file_size>1024*1024:raise ValueError('Configuration is too large')
   files[name]=z.read(item)
 for example,local in [('config.example.php','config.local.php'),('config.ai.example.php','config.ai.local.php')]:
  if example not in files:raise ValueError('Missing supplied configuration: '+example)
  files.setdefault(local,files[example])
 for name,data in files.items():(stage/name).write_bytes(data)
(stage/'storage/imports').mkdir(parents=True,exist_ok=True)
(stage/'storage/imports/.htaccess').write_text('Require all denied\n')
(stage/'docs').mkdir(exist_ok=True)
(stage/'docs/.htaccess').write_text('Require all denied\n')
shutil.copyfile(root/'docs/RELEASE_20261025_QR_DEMO.md',stage/'docs/RELEASE_20261025_QR_DEMO.md')
(stage/'VERSION.txt').write_text('EASY3 v7.3.1 / 2026-10-25\nSource: '+a.commit+'\n')
config_note=('Присланные example/local-конфигурации включены без изменения байтов. При отсутствии отдельных local-файлов они являются точными копиями присланных example-файлов. Повторно вводить настройки не нужно.' if a.config_zip else 'Четыре файла конфигурации не включены в публичный архив: при загрузке поверх сайта действующие настройки на хостинге сохранятся. Старый каталог сайта не удалять.')
instructions='''EASY3 7.3.1 — QR и готовое демо, 25 октября 2026

1. Через phpMyAdmin импортируйте database/update_20261025_qr_demo.sql в текущую базу MySQL 5.7. Отдельный EASY3_update_20261025_QR_demo_mysql57.sql содержит тот же скрипт. Если прошлое обновление 25.10 не импортировалось, новые таблицы для него тоже создадутся.
2. Загрузите ВСЁ содержимое архива ПОВЕРХ установленного сайта. index.php находится в корне. Сохраните серверную папку storage и существующую БД. install.sql и демонстрационные наполнения не запускать.
3. CONFIG_NOTE
4. Обновите страницу Ctrl+F5 и откройте демо. Текущее заполнение демо обновится один раз: исполнители универсальные, оборудование распределено, рабочие места доступны и добавлены на схемы. Прежняя конфигурация сохраняется в demo_environment_revisions. Опубликованные планы и производственные факты остаются прежними.
5. Информация о компании → Подразделения и ресурсы: QR виден на карточке. Нажмите QR для скачивания SVG/PNG или печати. Кнопки печати есть также для подразделения и всех мест.

Учёт времени требует входа под сотрудником своего подразделения. В собственном демо для QR другого подразделения выбирается его учебный исполнитель. Пока работа на предыдущем месте не завершена, второе не начнётся.
Готовые JS/CSS и локальный генератор включены. Node/Python на хостинге не нужны. Требования PHP 8.2+ сохранены.
Проверки, тесты, линтеры и запуск приложения не выполнялись по прямому указанию владельца. Сборка и упаковка выполнены. Старые QA_REPORT не являются проверками этой правки.
'''.replace('CONFIG_NOTE',config_note)
(stage/'START-HERE.txt').write_text(instructions)
(stage/'UPGRADE.md').write_text('# Установка EASY3 7.3.1\n\n'+instructions)
(stage/'README.md').write_text('# EASY3 7.3.1 · QR и готовое демо\n\n'+instructions)
archive=out/('EASY3_v7_3_1_20261025_configured_overlay.zip' if a.config_zip else 'EASY3_v7_3_1_20261025_public_overlay.zip')
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
 for f in sorted(stage.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(stage).as_posix())
shutil.copyfile(root/'app/database/update_20261025_qr_demo.sql',out/'EASY3_update_20261025_QR_demo_mysql57.sql')
print(archive)
