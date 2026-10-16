"""Build the hosting ZIP. Secrets are injected into the output only, never Git."""
from pathlib import Path
import argparse, re, zipfile, shutil, hashlib
p=argparse.ArgumentParser();p.add_argument('--output',required=True);p.add_argument('--db-config',required=True);p.add_argument('--ai-instruction',required=True);p.add_argument('--commit',required=True);a=p.parse_args()
root=Path(__file__).resolve().parents[1];out=Path(a.output);out.mkdir(parents=True,exist_ok=True)
stage=out/'hosting';stage.mkdir(exist_ok=True)
for f in (root/'app').rglob('*'):
    if not f.is_file():continue
    rel=f.relative_to(root/'app')
    if any(x in ['node_modules','output','__pycache__'] for x in rel.parts) or f.name in ['.gitignore','config.local.php','config.ai.local.php'] or f.suffix=='.pyc':continue
    dst=stage/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(f,dst)
shutil.copyfile(a.db_config,stage/'config.local.php')
key=re.search(r'sk-[A-Za-z0-9_-]+',Path(a.ai_instruction).read_text()).group(0)
(stage/'config.ai.local.php').write_text("<?php\n// Private deployment configuration. Never publish.\nreturn ['key'=>'"+key+"'];\n")
(stage/'VERSION.txt').write_text('EASY3 v5 / 2026-10-16\nGitHub commit: '+a.commit+'\n')
(stage/'START-HERE.txt').write_text('EASY3 обновление 16 октября 2026\n\n1. Сохраните резервную копию сайта и БД.\n2. В текущую БД импортируйте database/update_20261016.sql. Не используйте install.sql для рабочей БД.\n3. Загрузите все файлы этого архива в корень сайта. index.php уже на верхнем уровне.\n4. Частные реквизиты находятся в config.local.php и config.ai.local.php. Если реквизиты хостинга изменились, поправьте только свою конфигурацию.\n5. Откройте сайт. Для новой компании удобен отдельный учебный сеанс «Открыть демо».\n\nПодробности: UPGRADE.md. Фоновое видео включено в assets/media. Никакие данные рабочей БД архив не заменяет.\n')
(stage/'docs').mkdir(exist_ok=True);(stage/'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261016.md','PLAN_IMPLEMENTATION_20261016.md','AI_TECHNOLOGY.md','DESKTOP_20261016.md']:
    shutil.copyfile(root/'docs'/name,stage/'docs'/name)
archive=out/'EASY3_v5_20261016.zip'
with zipfile.ZipFile(archive,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=6) as z:
    for f in sorted(stage.rglob('*')):
        if f.is_file():z.write(f,f.relative_to(stage).as_posix())
sql=out/'EASY3_update_20261016.sql';shutil.copyfile(root/'app/database/update_20261016.sql',sql)
print('Created:',archive.name,archive.stat().st_size,'bytes;',sql.name,sql.stat().st_size,'bytes')
