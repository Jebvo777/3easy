"""Package the data display fix; no tests or application startup."""
from pathlib import Path
import argparse,shutil,zipfile

p=argparse.ArgumentParser();p.add_argument('--output',type=Path,required=True);p.add_argument('--commit',required=True);p.add_argument('--config-dir',type=Path);p.add_argument('--demo-sql',type=Path);a=p.parse_args()
root=Path(__file__).resolve().parents[1];out=a.output.resolve();out.mkdir(parents=True,exist_ok=True);stage=out/'hosting-v721';stage.mkdir(parents=True,exist_ok=True)
for src in (root/'app').rglob('*'):
 if not src.is_file():continue
 rel=src.relative_to(root/'app')
 if any(v in {'node_modules','.git','__pycache__','output'} for v in rel.parts):continue
 if rel.parts[0]=='storage' and src.name!='.htaccess':continue
 if src.name in {'.gitignore','config.local.php','config.ai.local.php'} or src.suffix=='.pyc':continue
 dst=stage/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dst)
if a.config_dir:
 for name in ['config.example.php','config.ai.example.php','config.local.php','config.ai.local.php']:shutil.copyfile(a.config_dir/name,stage/name)
if a.demo_sql and a.config_dir:shutil.copyfile(a.demo_sql,stage/'database/EASY3_fill_demo_20261023.sql')
(stage/'docs').mkdir(exist_ok=True);(stage/'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261023.md','RELEASE_20261023_721.md','AI_V7.md','FIX_LUNA_20261016.md']:shutil.copyfile(root/'docs'/name,stage/'docs'/name)
(stage/'VERSION.txt').write_text('EASY3 v7.2.1 / 2026-10-23\nGitHub source: '+a.commit+'\n')
(stage/'QA_STATUS_V721.txt').write_text('EASY3 v7.2.1\nПо прямой просьбе пользователя проверки, тесты, линтеры и запуск приложения не выполнялись.\nОтчёты предыдущих версий сохранены как история и не подтверждают эту правку.\n')
private='Четыре прежних example/local файла конфигурации сохранены без изменения. Доступы заново вводить не нужно.\n' if a.config_dir else 'Сохраните прежние config.local.php и config.ai.local.php при замене файлов сайта. Публичная поставка не содержит ключей.\n'
(stage/'START-HERE.txt').write_text('EASY3 v7.2.1 — исправлено пустое демо\n\nВ присланной БД большое наполнение уже есть: 27 целей, 55 сотрудников, 48 ресурсов. Кнопка входа раньше создавала другую, маленькую компанию.\n\n1. Замените файлы сайта содержимым архива; index.php находится в корне.\n'+private+'2. Для текущей уже наполненной базы никакой SQL импортировать не нужно. install.sql и повторное наполнение не запускайте.\n3. Перезагрузите страницу. Нетронутое маленькое демо без плана автоматически заменяется активной отдельной копией наполненной фабрики. Исходная компания остаётся в БД.\n4. В остальных малых демо нажмите на рабочем столе «Открыть наполненное демо» либо завершите сеанс и выберите «Открыть демо» на главной.\n5. Большое демо содержит цели октября–декабря 2026, два швейных цеха, отдельную подготовку, рабочие календари, задания, факты ОТК и экономику. Производственные показатели относятся к вымышленному учебному сценарию.\n6. При смене предприятия финансовый обзор открывается по всему портфелю. День/неделя/месяц показывают только цели соответствующего срока сдачи.\n\nНовая схема БД не требуется. Node/Python на хостинге не нужны. Требования PHP и прежний функционал сохранены.\nПроверки по вашей просьбе не запускались. Подробнее: docs/RELEASE_20261023_721.md.\n')
archive=out/('EASY3_v7_2_1_20261023'+('' if a.config_dir else '_public')+'.zip')
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
 for f in sorted(stage.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(stage).as_posix())
print(str(archive))
