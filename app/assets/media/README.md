# Происхождение визуальных ресурсов

`logo.png` — исходный логотип 234×92 из предоставленного `sources/demos/legacy.html`. Цвет меняется только CSS-фильтром для светлых поверхностей.

`alpine.webp` — новое изображение ImageGen для этого задания, 1672×941, преобразованное в WebP для загрузки. Сцена: спокойный бирюзовый альпийский залив, арка в известняковом массиве, утренний свет. Без текста и UI. Используется как фон лендинга, входа и рабочего стола.

3D-петля и предприятие создаются кодом, не заменяются растровыми изображениями.

## Окружение петли

`lakes_1k.hdr` — реальная HDR-панорама **Lakes**, Sergej Majboroda, Poly Haven, CC0. Источник: https://polyhaven.com/a/lakes . Файл получен с официального сервера Poly Haven. HDR служит освещением и отражениями металлической геометрии, не заменяет петлю картинкой. Карты албедо, шероховатости и нормалей металла создаются детерминированно в коде (2048×512), без запечённого освещения. Все ресурсы на хостинге локальные.


## Металл v3

`brushed-titanium-v3.webp` — отдельная растровая albedo-текстура, созданная ImageGen 11 октября 2026 для нанесения на настоящую замкнутую геометрию петли. Источник: `generated_images/exec-b51aced8-bbf1-4ed7-ab73-bb2f171eec72.png` в рабочем сеансе. Текстура не является изображением петли. Нормали/шероховатость дополняются процедурными микробороздками; окружение и свет формируются рендерером.

Промпт: square edge-to-edge extreme macro photographic texture of premium brushed titanium / stainless steel; flat orthographic, neutral diffuse illumination, graphite-silver tone, dense fine horizontal machining grain, microscopic pores, sparse hairline scratches, luxury aerospace finish; uniform brightness, no objects, text, directional glare, broad bands or perspective; continuous tile suitable for a rounded infinity ribbon.

## Фоновый ролик 16 октября

workspace-loop-20261016.mp4 — техническая редакция ролика из приложения владельца «Правки которые сделать были последние и видео». H.264, без звука, мягкое перекрытие начала и конца. Ролик используется только в авторизованном пространстве; исходная запись разговора с Drive к фону отношения не имеет.
