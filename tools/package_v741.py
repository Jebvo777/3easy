"""Full hosting overlay for 7.4.1, preserving deployed config and user files."""
from pathlib import Path
import argparse
import shutil
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, required=True)
parser.add_argument('--commit', required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
output = args.output.resolve()
output.mkdir(parents=True, exist_ok=True)
stage = output / 'hosting-v741'
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()
configs = {'config.local.php', 'config.ai.local.php', 'config.example.php', 'config.ai.example.php'}
for source in (root / 'app').rglob('*'):
    if not source.is_file():
        continue
    relative = source.relative_to(root / 'app')
    if any(part in {'node_modules', '.git', '__pycache__', 'output', 'qa'} for part in relative.parts):
        continue
    if relative.parts[0] in {'tools', 'storage'} and source.name != '.htaccess':
        continue
    if source.name in configs | {'.gitignore'} or source.suffix == '.pyc':
        continue
    target = stage / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, target)
(stage / 'docs').mkdir(exist_ok=True)
(stage / 'docs/.htaccess').write_text('Require all denied\n')
for name in ['RELEASE_20261026_741.md', 'RELEASE_20261025_74.md', 'IMPLEMENTATION_20261025_74.md', 'CUTTING_AGENT_74.md']:
    shutil.copyfile(root / 'docs' / name, stage / 'docs' / name)
(stage / 'storage/imports').mkdir(parents=True, exist_ok=True)
(stage / 'storage/imports/.htaccess').write_text('Require all denied\n')
instructions = '''EASY3 7.4.1 — исправление оформления, обновление поверх 7.4

1. Загрузите всё содержимое архива ПОВЕРХ действующего сайта.
   index.php расположен в корне архива. Не удаляйте каталог сайта.
2. Не удаляйте серверные конфигурации и storage с пользовательскими документами.
   config.local.php, config.ai.local.php, config.example.php и config.ai.example.php
   исключены из архива: действующие настройки БД и AI сохраняются на сервере.
3. Новый SQL не требуется. Если 7.4 уже установлена, миграцию повторять не нужно.
4. Обновите страницу Ctrl+F5.
5. Параметры плана и задание на смену раскрываются по нажатию на их заголовки.
   Схема и инспектор используют отдельное рабочее пространство во всех трёх режимах.

Полные PHP-файлы и готовые JS/CSS включены. Node/Python на хостинге не нужны.
Проверки, тесты, линтеры и запуск приложения не выполнялись по просьбе владельца.
Описание — docs/RELEASE_20261026_741.md.
'''
(stage / 'START-HERE.txt').write_text(instructions)
(stage / 'README.md').write_text('# EASY3 7.4.1\n\n' + instructions)
(stage / 'UPGRADE.md').write_text('# Обновление EASY3 7.4 → 7.4.1\n\n' + instructions)
(stage / 'VERSION.txt').write_text('EASY3 v7.4.1 / 2026-10-26\nSource: ' + args.commit + '\n')
archive = output / 'EASY3_741_overlay.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as bundle:
    for source in sorted(stage.rglob('*')):
        if source.is_file():
            bundle.write(source, source.relative_to(stage).as_posix())
report = output / 'EASY3_741_client_report.txt'
report.write_text('''1. Исправлена компоновка режимов «Целое изделие», «По узлам» и «Поток».
2. Схема цеха сохраняет рабочую высоту; блоки больше не сжимают её до полосы.
3. Параметры плана сделаны компактными и раскрываются по нажатию.
4. Задание на смену раскрывается отдельно; дата и количество видны в заголовке.
5. Исправлено размещение инспектора и прокрутки на компьютере и узких экранах.
6. Прогноз заработка перенесён внутрь содержимого окна плана.
7. Готовые ресурсы пересобраны с новой версией для обновления кеша браузера.
8. Конфигурации и пользовательские файлы сохраняются при загрузке поверх.
9. Изменения БД не требуются.

Проверки, тесты и запуск приложения не проводились по указанию владельца.
''')
print(archive)
print(report)
