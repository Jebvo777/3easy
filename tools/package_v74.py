"""Full hosting overlay. Preserve server configuration and user storage; no checks."""
from pathlib import Path
import argparse, shutil, zipfile

p=argparse.ArgumentParser()
p.add_argument('--output',type=Path,required=True)
p.add_argument('--commit',required=True)
a=p.parse_args()
root=Path(__file__).resolve().parents[1]
out=a.output.resolve();out.mkdir(parents=True,exist_ok=True)
stage=out/'hosting-v74'
if stage.exists():shutil.rmtree(stage)
stage.mkdir()
configs={'config.example.php','config.ai.example.php','config.local.php','config.ai.local.php'}
for src in (root/'app').rglob('*'):
 if not src.is_file():continue
 rel=src.relative_to(root/'app')
 if any(part in {'node_modules','.git','__pycache__','output','qa'} for part in rel.parts):continue
 if rel.parts[0] in {'tools','storage'} and src.name!='.htaccess':continue
 if src.name in configs|{'.gitignore'} or src.suffix=='.pyc':continue
 dst=stage/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dst)
(stage/'docs').mkdir(exist_ok=True)
(stage/'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261025_74.md','IMPLEMENTATION_20261025_74.md','CUTTING_AGENT_74.md']:
 shutil.copyfile(root/'docs'/name,stage/'docs'/name)
(stage/'storage/imports').mkdir(parents=True,exist_ok=True)
(stage/'storage/imports/.htaccess').write_text('Require all denied\n')
instructions='''EASY3 7.4 — обновление поверх 7.3.2

1. Импортируйте отдельный EASY3_update_20261025_74_mysql57.sql в действующую БД MySQL 5.7.
   Он только создаёт новые таблицы. database/install.sql для существующего сайта НЕ запускать.
2. Загрузите всё содержимое архива ПОВЕРХ сайта. index.php расположен в корне архива.
   Не удаляйте каталог сайта и storage с пользовательскими документами.
3. Четыре файла config.local.php, config.ai.local.php, config.example.php,
   config.ai.example.php исключены: действующие настройки БД и AI сохраняются на сервере.
4. Обновите страницу Ctrl+F5. ПЛАН → Подготовка → Крой для пошива / Раскрой · калькулятор.
5. В Компании → Экономика → Раскрой доступны правила общего фонда.
   Три независимых тарифа пошива сохранены. Сложность меняет время, затем оплату.
6. Материалы, раскладки, ограничения и нормы заполните действительными данными предприятия.
   Готовые нормы и цены из макетов/видео автоматически не назначаются.
7. Локальный OCR требует Tesseract (ocr_binary) и для PDF pdftoppm (pdf_image_binary).
   Языки задаются ocr_languages, по умолчанию rus+eng. Без OCR доступен перенос текста вручную.

Полные PHP-файлы и готовые JS/CSS включены. Node/Python на хостинге не нужны.
Проверки, тесты, линтеры и запуск приложения не выполнялись по просьбе владельца.
Описание — docs/RELEASE_20261025_74.md.
'''
(stage/'START-HERE.txt').write_text(instructions)
(stage/'README.md').write_text('# EASY3 7.4\n\n'+instructions)
(stage/'UPGRADE.md').write_text('# Обновление до EASY3 7.4\n\n'+instructions)
(stage/'VERSION.txt').write_text('EASY3 v7.4 / 2026-10-25\nSource stages: '+a.commit+'\n')
archive=out/'EASY3_74_overlay.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
 for src in sorted(stage.rglob('*')):
  if src.is_file():z.write(src,src.relative_to(stage).as_posix())
sql=out/'EASY3_update_20261025_74_mysql57.sql'
shutil.copyfile(root/'app/database/update_20261025_74.sql',sql)
print(str(archive));print(str(sql))
