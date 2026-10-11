# Локальные зависимости

Three.js 0.185.0 (MIT), включая OrbitControls и RoomEnvironment. Сборка esbuild 0.25.12. Нет CDN-зависимостей и сетевых вызовов к сторонним сервисам. Лицензия сохранена рядом.

Для пересборки: npm install three@0.185.0 esbuild@0.25.12; экспортировать все имена из `three`, OrbitControls и RoomEnvironment, собрать в ESM с `esbuild --bundle --format=esm --minify`.
