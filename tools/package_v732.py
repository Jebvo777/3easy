"""Package management multi-department assignments as an overlay; no checks."""
from pathlib import Path
import argparse,shutil,zipfile

p=argparse.ArgumentParser()
p.add_argument('--output',type=Path,required=True)
p.add_argument('--commit',required=True)
a=p.parse_args()
root=Path(__file__).resolve().parents[1]
out=a.output.resolve();out.mkdir(parents=True,exist_ok=True)
stage=out/'hosting-v732'
if stage.exists():shutil.rmtree(stage)
stage.mkdir()
configs={'config.example.php','config.ai.example.php','config.local.php','config.ai.local.php'}
for src in (root/'app').rglob('*'):
 if not src.is_file():continue
 rel=src.relative_to(root/'app')
 if any(part in {'node_modules','.git','__pycache__','output','qa'} for part in rel.parts):continue
 if rel.parts[0]=='storage' and src.name!='.htaccess':continue
 if src.name in configs|{'.gitignore'} or src.suffix=='.pyc':continue
 dst=stage/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dst)
(stage/'docs').mkdir(exist_ok=True)
(stage/'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261025_MANAGEMENT.md','RELEASE_20261025_QR_DEMO.md']:
 shutil.copyfile(root/'docs'/name,stage/'docs'/name)
(stage/'storage/imports').mkdir(parents=True,exist_ok=True)
(stage/'storage/imports/.htaccess').write_text('Require all denied\n')
instructions='''EASY3 7.3.2 — руководство в нескольких подразделениях

1. Загрузите всё содержимое архива ПОВЕРХ существующего сайта. Не удаляйте старый каталог и папку storage. index.php находится в корне архива.
2. Четыре файла конфигурации в архив не включены: текущие настройки БД и AI остаются на сервере.
3. SQL для этой правки НЕ нужен. Назначения хранятся в существующих данных компании. install.sql не запускать.
4. Обновите страницу Ctrl+F5. Откройте Информация о компании → Команда → Руководство, выберите участника и отметьте несколько подразделений, нажмите «Применить», затем «Сохранить» в информации о компании.
5. Если создаёте бригаду, выберите для неё одно из отмеченных подразделений. Подразделение существующей бригады нельзя снять до переназначения бригады.

Исполнители и оборудование остаются в одном подразделении. Список подразделений руководителя виден в команде и схеме, а в карточке структуры компании доступны переходы во все его подразделения.
Готовые JS/CSS, исправления QR и демо предыдущей версии включены. Предыдущий SQL для QR/демо, если он ещё требуется, находится в database/update_20261025_qr_demo.sql. Для множественного выбора новых SQL нет.
Проверки, тесты, линтеры и запуск приложения не выполнялись. Node/Python на хостинге не нужны.
'''
(stage/'START-HERE.txt').write_text(instructions)
(stage/'README.md').write_text('# EASY3 7.3.2\n\n'+instructions)
(stage/'UPGRADE.md').write_text('# Обновление до EASY3 7.3.2\n\n'+instructions)
(stage/'VERSION.txt').write_text('EASY3 v7.3.2 / 2026-10-25\nSource: '+a.commit+'\n')
archive=out/'EASY3_732_overlay.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
 for f in sorted(stage.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(stage).as_posix())
print(archive)
