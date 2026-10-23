"""Build v7.2 hosting archives. QA runs separately, after publication."""
from pathlib import Path
import argparse,shutil,zipfile,hashlib
p=argparse.ArgumentParser();p.add_argument('--output',required=True,type=Path);p.add_argument('--commit',required=True);p.add_argument('--config-dir',type=Path);p.add_argument('--demo-sql',type=Path);a=p.parse_args()
root=Path(__file__).resolve().parents[1];out=a.output.resolve();out.mkdir(parents=True,exist_ok=True);stage=out/'hosting-v72'
if stage.exists():shutil.rmtree(stage)
stage.mkdir()
for src in (root/'app').rglob('*'):
 if not src.is_file():continue
 rel=src.relative_to(root/'app')
 if any(v in {'node_modules','.git','__pycache__','output'} for v in rel.parts):continue
 if rel.parts[0]=='storage' and src.name!='.htaccess':continue
 if src.name in {'.gitignore','config.local.php','config.ai.local.php'} or src.suffix=='.pyc':continue
 dst=stage/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dst)
if a.config_dir:
 for name in ['config.example.php','config.ai.example.php','config.local.php','config.ai.local.php']:shutil.copyfile(a.config_dir/name,stage/name)
if a.demo_sql:
 if not a.config_dir:p.error('Company-specific seed belongs only in a private delivery')
 shutil.copyfile(a.demo_sql,stage/'database/EASY3_fill_demo_20261023.sql')
(stage/'docs').mkdir(exist_ok=True);(stage/'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261023.md','AI_V7.md','FIX_LUNA_20261016.md']:
 shutil.copyfile(root/'docs'/name,stage/'docs'/name)
(stage/'VERSION.txt').write_text('EASY3 v7.2 / 2026-10-23\nGitHub source: '+a.commit+'\n')
private='Ваши четыре исходных example/local файла сохранены без изменения. Повторно вводить доступы не нужно.\n' if a.config_dir else 'Сохраните прежние config.local.php и config.ai.local.php перед заменой файлов. В публичном архиве ключей нет.\n'
(stage/'START-HERE.txt').write_text('EASY3 v7.2 — 23 октября 2026\n\n1. Сохраните копию файлов и БД.\n2. В ТЕКУЩУЮ базу импортируйте database/update_20261023.sql. Два новых хранилища комплектации; старые данные не удаляются.\n3. Для большого демо из последнего дампа импортируйте EASY3_fill_demo_20261023.sql. Это отдельный необязательный шаг. Прошлый Q4 SQL перед ним НЕ нужен. Скрипт проверяет исходные данные и повторно не наполняет демо.\n4. Замените файлы сайта файлами этого архива, index.php в корне.\n'+private+'5. PHP 8.2+, PDO MySQL, cURL, JSON, sessions, DOM/XML, zlib, iconv, mbstring; MySQL 5.7+/MariaDB. Node/Python/Composer на хостинге не нужны.\n6. Откройте План → заказ → Подготовка и комплектация. Пошив расположен отдельной ступенью ниже. В паспорте подразделения различаются назначением.\n7. Симуляция: выберите дату, людей, время на изделие и суммы; нажмите Рассчитать. AI и алгоритм не публикуют задания автоматически.\n8. При ошибке SQL остановите импорт и выполните ROLLBACK; не продолжайте к COMMIT вручную. install.sql применяется только для новой установки.\n\nНормы/факты нового большого демо вымышлены и явно отмечены. Финальные проверки: QA_REPORT_V72.md.\n')
archive=out/('EASY3_v7_2_20261023'+('' if a.config_dir else '_public')+'.zip')
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=6) as z:
 for f in sorted(stage.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(stage).as_posix())
(out/'CHECKSUMS-v72.sha256').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
print(str(archive),archive.stat().st_size)
