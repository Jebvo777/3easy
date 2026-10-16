"""Package the tracked application without private hosting credentials."""
from pathlib import Path
import argparse
import hashlib
import json
import shutil
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument("--output", required=True)
parser.add_argument("--commit", required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parents[1]
out = Path(args.output).resolve()
out.mkdir(parents=True, exist_ok=True)
stage = out / "hosting"
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()
for source in (root / "app").rglob("*"):
    if not source.is_file():
        continue
    relative = source.relative_to(root / "app")
    if any(part in {"node_modules", "__pycache__", "output", ".git"} for part in relative.parts):
        continue
    if source.name in {".gitignore", "config.local.php", "config.ai.local.php"} or source.suffix == ".pyc":
        continue
    destination = stage / relative
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, destination)
(stage / "VERSION.txt").write_text(
    "EASY3 v5 / 2026-10-16\nGitHub commit: " + args.commit + "\n", encoding="utf-8")
(stage / "START-HERE.txt").write_text(
    "EASY3: обновление 16 октября 2026\n\n"
    "1. Сохраните резервную копию сайта и БД.\n"
    "2. Импортируйте database/update_20261016.sql в текущую БД через phpMyAdmin.\n"
    "   install.sql предназначен только для новой установки.\n"
    "3. Загрузите содержимое ZIP в корень сайта: index.php уже на верхнем уровне.\n"
    "4. Сохраните существующие config.local.php и config.ai.local.php на сервере.\n"
    "   Частные реквизиты и ключи не включены в публичный архив.\n"
    "   Если AI-конфигурации ещё нет, скопируйте config.ai.example.php в\n"
    "   config.ai.local.php и укажите свой прежний ключ Artemox.\n"
    "5. Фоновое видео находится в assets/media. Сборка на хостинге не требуется.\n\n"
    "Базовые проверки кода пройдены. Живой AI из среды разработки получил\n"
    "HTTP 403 / Cloudflare 1010; доступ провайдера с целевого хостинга не подтверждён.\n",
    encoding="utf-8")
(stage / "docs").mkdir(exist_ok=True)
(stage / "docs" / ".htaccess").write_text("Require all denied\n")
for name in ["RELEASE_20261016.md", "PLAN_IMPLEMENTATION_20261016.md",
             "AI_TECHNOLOGY.md", "DESKTOP_20261016.md"]:
    shutil.copyfile(root / "docs" / name, stage / "docs" / name)
archive = out / "EASY3_v5_20261016.zip"
with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as handle:
    for source in sorted(stage.rglob("*")):
        if source.is_file():
            handle.write(source, source.relative_to(stage).as_posix())
sql = out / "EASY3_update_20261016.sql"
shutil.copyfile(root / "app" / "database" / "update_20261016.sql", sql)
with zipfile.ZipFile(archive) as handle:
    names = set(handle.namelist())
    manifest = json.loads(handle.read("assets/manifest.json"))
    required = {
        "index.php", "database/update_20261016.sql", "START-HERE.txt",
        "assets/media/workspace-loop-20261016.mp4",
        "data/operations.json", "data/examples.json",
        manifest["js"], manifest["css"],
    }
    assert required <= names, "Missing application entry or asset"
    assert not {"config.local.php", "config.ai.local.php"} & names
    assert handle.read("database/update_20261016.sql") == sql.read_bytes()
    assert handle.testzip() is None, "ZIP CRC failure"
notes = (
    "Установочный архив приложения с фоновым видео; index.php находится в корне ZIP.\n\n"
    "Сначала импортируйте EASY3_update_20261016.sql в текущую БД, затем загрузите файлы. "
    "Сохраните текущие config.local.php и config.ai.local.php: частные ключи в публичный ZIP не включены. "
    "При отсутствии AI-конфигурации используйте config.ai.example.php и прежний ключ Artemox.\n\n"
    "Код, расшифровка и новая демка сохранены в репозитории. Базовые проверки кода пройдены. "
    "Живой ответ AI не подтверждён: из среды разработки провайдер вернул HTTP 403 / Cloudflare 1010. "
    "Видеозапись разбора превышает лимит загрузки; использована предоставленная расшифровка.\n\n"
    "Commit: " + args.commit + "\n\n"
)
for artifact in [archive, sql]:
    checksum = hashlib.sha256(artifact.read_bytes()).hexdigest()
    notes += artifact.name + " — " + str(artifact.stat().st_size) + " bytes; SHA256 " + checksum + "\n"
(out / "RELEASE.txt").write_text(notes, encoding="utf-8")
print(notes)
