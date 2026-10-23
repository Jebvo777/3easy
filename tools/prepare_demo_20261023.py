"""Prepare a synthetic, company-scoped MySQL 5.7 data expansion from a parsed dump.

This builds data, not a live database connection. The private source-data.json is
never distributed. Validation is deliberately a separate post-publication step.
"""
from __future__ import annotations

import argparse
import copy
from datetime import datetime, timedelta
from hashlib import md5, sha256
import json
from pathlib import Path


def compact(value):
    return json.dumps(value, ensure_ascii=False, separators=(',', ':'))


def digest(value):
    return sha256(value.encode()).hexdigest()


def ident(key):
    return md5((NAMESPACE + '/' + CID + '/' + key).encode()).hexdigest()


def stamp(value):
    return value.strftime('%Y-%m-%d %H:%M:%S')


def sql(value):
    if value is None:
        return 'NULL'
    if isinstance(value, (int, float)):
        return str(int(value)) if isinstance(value, bool) else str(value)
    value = str(value).replace('\\', '\\\\').replace("'", "''").replace('\0', '\\0')
    value = value.replace('\n', '\\n').replace('\r', '\\r')
    expr = "'" + value + "'"
    for marker, variable in ACTORS.items():
        if marker in value:
            expr = 'REPLACE(' + expr + ',\'' + marker + '\',' + variable + ')'
    return expr


def insert(table, records, columns=None, size=40):
    if not records:
        return
    columns = columns or list(records[0])
    for offset in range(0, len(records), size):
        parts = []
        for record in records[offset:offset + size]:
            parts.append('SELECT ' + ','.join(sql(record.get(k)) for k in columns) + ' FROM DUAL WHERE @demo_apply=1')
        SQL.append('INSERT INTO `' + table + '` (' + ','.join('`' + k + '`' for k in columns) + ')\n' + '\nUNION ALL\n'.join(parts) + ';')


def add_person(pid, name, role, workshop, brigade, skills, roles=None):
    PASSPORT['people'].append(dict(id=pid, name=name, role=role, roles=roles or ['worker'], workshop=workshop, brigade=brigade, skills=skills, active=True))


def add_resource(workshop, code, kind, name, serial, skills=None):
    rid = 'd26-' + code
    pid = 'd26-person-' + code
    brigade = ('d26-cut' if kind in ['cut', 'hand'] else 'b-sew') if workshop == 'w-main' else ('d26-finish' if kind in ['iron', 'qc', 'pack'] else 'd26-workwear')
    labels = dict(cut='Раскройщик', sew='Швея', overlock='Оператор оверлока', iron='Оператор ВТО', qc='Контролёр ОТК', hand='Комплектовщик', pack='Упаковщик')
    skills = skills or ({'cut': ['cut', 'hand'], 'sew': ['sew', 'overlock', 'hand'], 'overlock': ['sew', 'overlock'], 'iron': ['iron', 'hand', 'qc', 'pack'], 'qc': ['qc', 'pack', 'hand'], 'pack': ['pack', 'hand', 'qc'], 'hand': ['hand', 'pack']}[kind])
    add_person(pid, labels[kind] + ' ' + serial + ' · демо', labels[kind], workshop, brigade, skills, ['qc'] if kind == 'qc' else ['worker'])
    resource = dict(id=rid, name=name, type=kind, worker=pid, workshop=workshop, active=True)
    PASSPORT['resources'].append(resource)
    NEW_RESOURCES.append(resource)


def layout_for(workshop):
    resources = [r for r in PASSPORT['resources'] if r['workshop'] == workshop]
    rank = dict(cut=0, hand=1, sew=2, overlock=3, iron=4, qc=5, pack=6)
    resources.sort(key=lambda r: (rank[r['type']], r['id']))
    old_objects = {o['resource']: o for o in ORIGINAL_LAYOUT.get('objects', []) if o.get('resource')}
    objects = []
    by_type = {}
    for i, r in enumerate(resources):
        oid = old_objects.get(r['id'], {}).get('id', 'f-' + r['id'])
        objects.append(dict(id=oid, resource=r['id'], type=r['type'], x=-12 + (i % 6) * 4.5, z=-7.5 + (i // 6) * 4.5, rotation=0, zone=dict(cut='Крой', hand='Комплектация', sew='Пошив', overlock='Обработка швов', iron='ВТО', qc='ОТК', pack='Упаковка')[r['type']]))
        by_type.setdefault(r['type'], []).append(oid)
    objects.append(dict(id='d26-stock-' + workshop, resource='', type='storage', x=12, z=8, rotation=0, zone='Материалы и готовые партии'))
    links = []
    flow = ['cut', 'hand', 'sew', 'overlock', 'iron', 'qc', 'pack']
    for before, after in zip(flow, flow[1:]):
        for i, oid in enumerate(by_type.get(before, [])):
            dest = by_type.get(after, [])
            if dest:
                links.append([oid, dest[i % len(dest)]])
    return dict(width=32, depth=24, objects=objects, links=links)


# Every garment has actual preparation, parallel subassemblies and one release sink.
# Explicit dependencies are operation numbers, not coordinates on the editor canvas.
ROUTES = {
    'tee': [
        ('Раскрой деталей переда, спинки и рукавов', 'cut', 54, []),
        ('Проверить и скомплектовать крой', 'hand', 24, [1]),
        ('Подготовить бейку горловины', 'sew', 35, [2]),
        ('Стачать плечевые швы', 'overlock', 48, [2]),
        ('Притачать бейку горловины', 'overlock', 65, [3, 4]),
        ('Закрепить припуск горловины', 'sew', 42, [5]),
        ('Притачать рукава в открытую пройму', 'overlock', 80, [4]),
        ('Втачать составник и стачать боковые швы', 'overlock', 70, [6, 7]),
        ('Заметать низ рукавов', 'hand', 25, [8]),
        ('Подшить низ рукавов', 'sew', 60, [9]),
        ('Подшить низ изделия', 'sew', 65, [8]),
        ('Пришить размерную маркировку', 'sew', 25, [10, 11]),
        ('Удалить нитки и проверить швы', 'hand', 32, [12]),
        ('Выполнить финальное ВТО', 'iron', 65, [13]),
        ('Сложить изделие и вложить в пакет', 'pack', 28, [14]),
        ('Итоговый ОТК и разрешение выпуска', 'qc', 40, [15]),
    ],
    'hoodie': [
        ('Раскрой футера, капюшона и кашкорсе', 'cut', 95, []),
        ('Комплектовать детали по размерам', 'hand', 38, [1]),
        ('Обработать входы кармана кенгуру', 'sew', 70, [2]),
        ('Настрочить карман на перед', 'sew', 105, [3]),
        ('Стачать средний шов капюшона', 'overlock', 52, [2]),
        ('Собрать подкладку капюшона', 'overlock', 48, [2]),
        ('Соединить капюшон с подкладкой', 'sew', 90, [5, 6]),
        ('Проложить отделочную строчку капюшона', 'sew', 72, [7]),
        ('Установить люверсы и продеть шнур', 'hand', 65, [8]),
        ('Стачать плечевые швы', 'overlock', 56, [4]),
        ('Притачать рукава', 'overlock', 100, [10]),
        ('Обработать боковые и рукавные швы', 'overlock', 112, [11]),
        ('Замкнуть манжеты', 'sew', 42, [2]),
        ('Притачать манжеты', 'overlock', 90, [12, 13]),
        ('Замкнуть пояс', 'sew', 35, [2]),
        ('Притачать пояс по низу изделия', 'overlock', 75, [12, 15]),
        ('Втачать капюшон в горловину', 'overlock', 92, [9, 10]),
        ('Настрочить тесьму по горловине', 'sew', 62, [17]),
        ('Пришить размерник и составник', 'sew', 32, [14, 16, 18]),
        ('Удалить нитки и осмотреть карман', 'hand', 45, [19]),
        ('Провести влажно-тепловую обработку', 'iron', 95, [20]),
        ('Сложить, промаркировать и упаковать', 'pack', 42, [21]),
        ('Итоговый ОТК: строчки, размеры и карман', 'qc', 58, [22]),
    ],
    'sweat': [
        ('Раскрой футера и отделочных деталей', 'cut', 80, []),
        ('Проверить комплект деталей', 'hand', 32, [1]),
        ('Подготовить воротник из кашкорсе', 'sew', 42, [2]),
        ('Стачать плечевые швы', 'overlock', 52, [2]),
        ('Притачать воротник', 'overlock', 68, [3, 4]),
        ('Настрочить тесьму горловины', 'sew', 55, [5]),
        ('Притачать рукава', 'overlock', 90, [4]),
        ('Обработать боковые и рукавные швы', 'overlock', 98, [6, 7]),
        ('Замкнуть манжеты', 'sew', 40, [2]),
        ('Притачать манжеты', 'overlock', 85, [8, 9]),
        ('Замкнуть пояс', 'sew', 35, [2]),
        ('Притачать пояс', 'overlock', 72, [8, 11]),
        ('Выполнить отделочные закрепки', 'sew', 38, [10, 12]),
        ('Пришить маркировку', 'sew', 28, [13]),
        ('Удалить нитки', 'hand', 35, [14]),
        ('Обработать изделие паром', 'iron', 88, [15]),
        ('Упаковать изделие', 'pack', 35, [16]),
        ('Итоговый ОТК и выпуск', 'qc', 48, [17]),
    ],
    'jogger': [
        ('Раскрой передних и задних половинок', 'cut', 90, []),
        ('Проверить комплект брюк', 'hand', 38, [1]),
        ('Подготовить мешковины карманов', 'overlock', 55, [2]),
        ('Обработать входы в боковые карманы', 'sew', 105, [3]),
        ('Стачать мешковины карманов', 'overlock', 72, [4]),
        ('Обметать шаговые срезы', 'overlock', 65, [2]),
        ('Соединить боковые срезы брюк', 'overlock', 100, [5]),
        ('Стачать шаговые швы', 'overlock', 105, [6, 7]),
        ('Стачать средний шов', 'overlock', 72, [8]),
        ('Подготовить эластичную тесьму', 'hand', 32, [2]),
        ('Замкнуть пояс и оформить отверстия', 'sew', 62, [10]),
        ('Втачать пояс с эластичной тесьмой', 'overlock', 100, [9, 11]),
        ('Закрепить пояс и продеть шнур', 'sew', 85, [12]),
        ('Замкнуть манжеты', 'sew', 38, [2]),
        ('Притачать манжеты брюк', 'overlock', 78, [9, 14]),
        ('Закрепить входы карманов', 'sew', 42, [13, 15]),
        ('Пришить размерник и составник', 'sew', 28, [16]),
        ('Удалить нитки и проверить симметрию', 'hand', 45, [17]),
        ('Выполнить финальное ВТО', 'iron', 92, [18]),
        ('Сложить и упаковать', 'pack', 40, [19]),
        ('Итоговый ОТК брюк', 'qc', 55, [20]),
    ],
    'polo': [
        ('Раскрой пике, воротника и планок', 'cut', 85, []),
        ('Проверить комплект и разметить планку', 'hand', 40, [1]),
        ('Продублировать детали планки', 'iron', 45, [2]),
        ('Обтачать и вывернуть планки', 'sew', 100, [3]),
        ('Обработать застёжку поло', 'sew', 155, [4]),
        ('Подготовить воротник и стойку', 'sew', 115, [2]),
        ('Стачать плечевые швы', 'overlock', 50, [5]),
        ('Втачать воротник', 'sew', 125, [6, 7]),
        ('Закрыть шов притачивания воротника', 'sew', 75, [8]),
        ('Притачать рукава', 'overlock', 85, [7]),
        ('Обработать боковые швы', 'overlock', 80, [9, 10]),
        ('Оформить разрезы по бокам', 'sew', 85, [11]),
        ('Подшить низ рукавов', 'sew', 60, [10]),
        ('Подшить низ изделия', 'sew', 70, [12]),
        ('Обметать петли на планке', 'sew', 48, [9]),
        ('Пришить пуговицы', 'sew', 48, [15]),
        ('Выполнить закрепки планки', 'sew', 35, [16]),
        ('Пришить маркировку', 'sew', 25, [13, 14, 17]),
        ('Удалить нитки', 'hand', 38, [18]),
        ('Выполнить окончательное ВТО', 'iron', 90, [19]),
        ('Упаковать изделие', 'pack', 35, [20]),
        ('Итоговый ОТК планки и воротника', 'qc', 55, [21]),
    ],
    'apron': [
        ('Раскрой полотна, карманов и завязок', 'cut', 65, []),
        ('Проверить крой и разметить карманы', 'hand', 40, [1]),
        ('Обработать верхние срезы карманов', 'sew', 60, [2]),
        ('Заутюжить припуски карманов', 'iron', 40, [3]),
        ('Настрочить карманы на полотно', 'sew', 115, [4]),
        ('Обработать боковые края фартука', 'sew', 80, [2]),
        ('Обработать верхний край', 'sew', 62, [5, 6]),
        ('Стачать завязки и шейную бретель', 'sew', 100, [2]),
        ('Вывернуть и приутюжить завязки', 'iron', 50, [8]),
        ('Пришить бретель и завязки', 'sew', 72, [7, 9]),
        ('Закрепить места крепления', 'sew', 45, [10]),
        ('Подшить нижний край', 'sew', 70, [11]),
        ('Удалить нитки и приутюжить изделие', 'iron', 75, [12]),
        ('Вложить инструкцию и упаковать', 'pack', 30, [13]),
        ('Итоговый ОТК креплений и геометрии', 'qc', 45, [14]),
    ],
    'vest': [
        ('Раскрой верха, подкладки и утеплителя', 'cut', 120, []),
        ('Комплектовать детали по размерам', 'hand', 48, [1]),
        ('Обработать наружные карманы', 'sew', 165, [2]),
        ('Настрочить карманы на полочки', 'sew', 135, [3]),
        ('Соединить детали спинки', 'sew', 90, [2]),
        ('Стачать плечевые швы верха', 'sew', 75, [4, 5]),
        ('Стачать боковые швы верха', 'sew', 90, [6]),
        ('Собрать подкладку', 'sew', 150, [2]),
        ('Подготовить стойку воротника', 'sew', 85, [2]),
        ('Втачать воротник-стойку', 'sew', 95, [7, 9]),
        ('Притачать молнию к полочкам', 'sew', 145, [10]),
        ('Соединить верх с подкладкой', 'sew', 160, [8, 11]),
        ('Обтачать проймы', 'sew', 130, [12]),
        ('Вывернуть жилет и выправить края', 'hand', 80, [13]),
        ('Застрочить технологическое отверстие', 'sew', 40, [14]),
        ('Проложить отделочную строчку борта', 'sew', 110, [15]),
        ('Настрочить светоотражающие элементы', 'sew', 85, [16]),
        ('Установить фурнитуру и размерник', 'hand', 55, [17]),
        ('Проверить застёжку и удалить нитки', 'hand', 55, [18]),
        ('Выполнить финишное ВТО', 'iron', 105, [19]),
        ('Упаковать по размерам', 'pack', 42, [20]),
        ('Итоговый ОТК жилета', 'qc', 65, [21]),
    ],
    'workpants': [
        ('Раскрой саржи, карманов и усилений', 'cut', 110, []),
        ('Комплектовать и разметить детали', 'hand', 50, [1]),
        ('Обметать срезы деталей брюк', 'overlock', 95, [2]),
        ('Обработать накладные карманы', 'sew', 130, [2]),
        ('Настрочить карманы и усиления', 'sew', 175, [3, 4]),
        ('Обработать застёжку-молнию', 'sew', 165, [3]),
        ('Стачать задний средний шов', 'sew', 85, [5]),
        ('Стачать боковые швы', 'sew', 115, [6, 7]),
        ('Проложить отделочные боковые строчки', 'sew', 95, [8]),
        ('Стачать шаговые швы', 'sew', 120, [9]),
        ('Закончить средний шов', 'sew', 75, [10]),
        ('Подготовить пояс', 'sew', 95, [2]),
        ('Изготовить шлёвки', 'sew', 65, [2]),
        ('Притачать пояс и шлёвки', 'sew', 145, [11, 12, 13]),
        ('Закрыть пояс отделочной строчкой', 'sew', 90, [14]),
        ('Обметать петлю пояса', 'sew', 35, [15]),
        ('Пришить пуговицу пояса', 'sew', 30, [16]),
        ('Подшить низ брюк', 'sew', 105, [10]),
        ('Выполнить закрепки карманов и шлёвок', 'sew', 85, [17, 18]),
        ('Удалить нитки и пришить маркировку', 'hand', 55, [19]),
        ('Выполнить окончательное ВТО', 'iron', 110, [20]),
        ('Упаковать с инструкцией по уходу', 'pack', 42, [21]),
        ('Итоговый ОТК размеров и закрепок', 'qc', 70, [22]),
    ],
}


def route_for(kind):
    out = []
    for i, (name, typ, seconds, deps) in enumerate(ROUTES[kind], 1):
        node = ('cutting' if typ == 'cut' else 'finish' if typ in ['qc', 'pack', 'iron'] and i > len(ROUTES[kind]) * .65 else 'parts' if i < 6 else 'assembly')
        equipment = dict(cut='Раскройный стол', hand='Ручное рабочее место', sew='Прямострочная машина', overlock='Оверлок', iron='Паровой стол ВТО', pack='Упаковочный стол', qc='Стол контроля качества')[typ]
        if 'петл' in name.lower() or 'пуговиц' in name.lower():
            equipment = 'Петельная / пуговичная машина'
        out.append(dict(id=f'op-{i:02d}', name=name, type=typ, seconds=seconds, setup_minutes=10 if typ == 'cut' else 2 if typ in ['sew', 'overlock'] else 1, dependencies=[f'op-{d:02d}' for d in deps], source='Учебная норма сценария EASY3 Q4-2026, редакция 1; вымышленная оценка для демонстрации, не норматив предприятия.', batch=150, equipment=equipment, grade=4 if typ in ['sew', 'overlock'] else 3, notes='Норма на 1 изделие; подготовка задана отдельно. Все величины демонстрационные.', node_id=node, node_name=dict(cutting='Крой и комплектование', parts='Подготовка узлов', assembly='Сборка изделия', finish='Отделка и выпуск')[node], estimated_seconds=None))
    return out


def resource_for(op, workshop, seed, after=None, seconds=None):
    candidates = [r for r in NEW_RESOURCES if r['workshop'] == workshop and r['type'] == op['type']]
    special = 'Петельная' in op.get('equipment', '')
    candidates = [r for r in candidates if ('петельная' in r['name'].lower()) == special] if op['type'] == 'sew' else candidates
    if after is not None:
        return min(candidates, key=lambda r: (fit_work(after, seconds, r, reserve=False)[-1][1],r['id']))
    return candidates[seed % len(candidates)]


def fit_work(after, seconds, resource, reserve=True):
    blocked = sorted(BUSY.get('r:' + resource['id'], []) + BUSY.get('p:' + resource['worker'], []))
    remaining = seconds
    slots = []
    day = after.replace(hour=0, minute=0, second=0)
    while remaining:
        if day.isoweekday() <= 5:
            for a, b in [(9, 13), (14, 18)]:
                start, end = max(after, day.replace(hour=a)), day.replace(hour=b)
                if start >= end:
                    continue
                free = [(start, end)]
                for bs, be in blocked:
                    fresh = []
                    for fs, fe in free:
                        if be <= fs or bs >= fe:
                            fresh.append((fs, fe))
                        else:
                            if bs > fs:
                                fresh.append((fs, bs))
                            if be < fe:
                                fresh.append((be, fe))
                    free = fresh
                for fs, fe in free:
                    take = min(remaining, int((fe - fs).total_seconds()))
                    if take > 0:
                        slots.append((fs, fs + timedelta(seconds=take)))
                        remaining -= take
                    if not remaining:
                        break
                if not remaining:
                    break
        day += timedelta(days=1)
    if reserve:
        for key in ['r:' + resource['id'], 'p:' + resource['worker']]:
            BUSY.setdefault(key, []).extend(slots)
    return slots


def add_task(order, op, batch, resource, deps, after, cutting=False, extra_seconds=0):
    seconds = batch['quantity'] * op['seconds'] + op['setup_minutes'] * 60 + extra_seconds
    slots = fit_work(after, seconds, resource)
    key = order['id'] + '/' + batch['id'] + '/' + op['id']
    tid = ident('task/' + key)
    final = op['id'] == order['_route'][-1]['id'] and not cutting
    meta = dict(schema=2, batch_id=batch['id'], variant_id=batch['variant_id'], color=batch['color'], size=batch['size'], node_id=op['node_id'], node_name=op['node_name'], job_key=key, norm_seconds=seconds, mode=order['_workspace']['mode'], work_group=op['node_id'], slots=[[stamp(a), stamp(b)] for a, b in slots], workspace_revision=1, demo_source=NAMESPACE)
    if cutting:
        meta['cutting'] = True
    task = dict(id=tid, company_id=CID, order_id=order['id'], plan_id=PLAN_ID, operation_id=op['id'], title=op['name'], resource_id=resource['id'], worker_id=resource['worker'], quantity=batch['quantity'], released=0, transferred=0, accepted=0, rework=0, rework_ready=0, scrap=0, state='ready', is_final=int(final), dependencies=compact([d['id'] for d in deps]), revision=1, planned_start=stamp(slots[0][0]), planned_end=stamp(slots[-1][1]), planning_meta=compact(meta))
    TASKS.append(task)
    SCHEDULE.append(dict(order_id=order['id'], operation_id=op['id'], title=op['name'], resource_id=resource['id'], worker_id=resource['worker'], quantity=batch['quantity'], start=task['planned_start'], end=task['planned_end'], slots=meta['slots'], seconds=seconds, dependencies=[d['_job'] for d in deps], job_key=key, task_id=tid, is_final=final, pinned=False, retained=False, meta=meta))
    task['_job'] = key
    task['_end'] = slots[-1][1]
    return task


def add_event(task, kind, quantity, when, details=None):
    task['revision'] += 1
    event = dict(id=ident('event/' + task['id'] + '/' + str(task['revision'])), company_id=CID, task_id=task['id'], actor_id=OWNER if kind not in ['quality', 'rework_quality'] else QUALITY, kind=kind, quantity=quantity, details=compact(dict(details or {}, _task_revision=task['revision'], source='Синтетический учебный сценарий EASY3 Q4-2026; не реальные производственные сведения.')), created_at=stamp(when))
    EVENTS.append(event)


def apply_facts():
    incident = None
    for task in sorted(TASKS, key=lambda t: (t['planned_end'], t['id'])):
        if task['_end'] > datetime(2026, 10, 16, 17, 0):
            continue
        start = datetime.fromisoformat(task['planned_start'])
        end = task['_end']
        add_event(task, 'start', None, start)
        # A closed interruption demonstrates period analytics without masking the QC signal.
        if task['order_id'] == ORDERS[1]['id'] and task['operation_id'] == 'op-05' and not any(e['kind'] == 'pause' for e in EVENTS):
            pause_at = start + timedelta(seconds=60)
            add_event(task, 'pause', None, pause_at, {'reason': 'Учебная переналадка направителя перед обработкой кармана'})
            add_event(task, 'start', None, pause_at + timedelta(seconds=60))
        task['released'] = task['transferred'] = task['quantity']
        add_event(task, 'release', task['quantity'], end)
        add_event(task, 'transfer', task['quantity'], end)
        task['state'] = 'completed'
        if task['is_final']:
            fault = task['order_id'] == ORDERS[3]['id'] and incident is None
            rework = 8 if fault else 0
            task['accepted'] = task['quantity'] - rework
            task['rework'] = rework
            task['state'] = 'rework' if fault else 'completed'
            add_event(task, 'quality', task['quantity'], end, {'accepted': task['accepted'], 'rework': rework, 'scrap': 0, 'reason': 'Учебный дефект: пропуски строчки на входе в карман кенгуру. Проверить иглу и натяжение, перешить участок, повторить ОТК.' if fault else 'Учебная приёмка: размеры, швы и маркировка соответствуют сценарию.'})
            if fault:
                incident = task
    # Keep the incident visible in the latest 60 events used by the desktop signal.
    if incident:
        incident['rework_ready'] = 2
        add_event(incident, 'rework_release', 4, datetime(2026, 10, 16, 17, 55), {'reason': 'Учебное продолжение: 4 из 8 изделий после исправления переданы на повторный ОТК.'})
        add_event(incident, 'rework_quality', 2, datetime(2026, 10, 16, 17, 56), {'accepted': 0, 'rework': 2, 'scrap': 0, 'reason': 'Учебный дефект: повторный ОТК двух худи выявил пропуски строчки кармана. На доработке 8 шт.; 2 шт. ещё в очереди повторного контроля.'})
    return incident


def money_ratio(value, part, whole):
    return (value * part * 2 + whole) // (2 * whole)


def economy_for(order, seconds):
    p = PASSPORT['economy']
    labor = money_ratio(p['labor_hour_cents'], seconds, 3600)
    common = money_ratio(p['monthly_common_cents'], seconds, 3600 * p['monthly_hours'])
    direct = sum(x['cents'] for x in order['_expenses'])
    cost = labor + common + direct
    value = order['price_cents'] * order['quantity'] if order['mode'] == 'CONTRACT' else None
    return dict(quantity=order['quantity'], norm_seconds=seconds, scope='Производственный контур', status='partial', cost_cents=cost, labor_cents=labor, common_cents=common, direct_cents=direct, unit_cents=money_ratio(cost, 1, order['quantity']), value_cents=value, result_cents=None if value is None else value-cost, unknown=[], excluded=p['excluded'], method='Прямые учебные статьи + нормированный труд + общие расходы по трудочасам')


def prepare_orders():
    specs = [
        ('tee', 'Футболки «Линия»', 300, '2026-10-01', '2026-10-08'),
        ('apron', 'Фартуки «Кофейня»', 300, '2026-10-01', '2026-10-12'),
        ('sweat', 'Свитшоты «Туман»', 450, '2026-10-05', '2026-10-16'),
        ('hoodie', 'Худи «Полярный контур»', 300, '2026-10-01', '2026-10-23'),
        ('polo', 'Поло «Команда»', 450, '2026-10-12', '2026-10-26'),
        ('jogger', 'Джоггеры «Город»', 600, '2026-10-14', '2026-10-28'),
        ('vest', 'Жилеты «Смена»', 300, '2026-10-19', '2026-10-30'),
        ('workpants', 'Брюки «Мастер»', 450, '2026-10-20', '2026-10-30'),
        ('tee', 'Футболки «Спорт»', 600, '2026-10-26', '2026-11-06'),
        ('apron', 'Фартуки «Пекарня»', 450, '2026-10-28', '2026-11-10'),
        ('hoodie', 'Худи «Северный ветер»', 600, '2026-11-02', '2026-11-13'),
        ('polo', 'Поло «Сервис»', 450, '2026-11-05', '2026-11-18'),
        ('sweat', 'Свитшоты «Лес»', 600, '2026-11-09', '2026-11-20'),
        ('jogger', 'Джоггеры «Маршрут»', 600, '2026-11-12', '2026-11-24'),
        ('vest', 'Жилеты «Склад»', 450, '2026-11-16', '2026-11-27'),
        ('workpants', 'Брюки «Монтаж»', 600, '2026-11-18', '2026-11-30'),
        ('hoodie', 'Худи «Зимний город»', 600, '2026-11-23', '2026-12-04'),
        ('apron', 'Фартуки «Праздник»', 600, '2026-11-26', '2026-12-08'),
        ('tee', 'Футболки «Подарок»', 600, '2026-12-01', '2026-12-11'),
        ('polo', 'Поло «Партнёр»', 450, '2026-12-03', '2026-12-15'),
        ('sweat', 'Свитшоты «Снег»', 600, '2026-12-07', '2026-12-18'),
        ('jogger', 'Джоггеры «Каникулы»', 600, '2026-12-10', '2026-12-24'),
        ('vest', 'Жилеты «Логистика»', 450, '2026-12-14', '2026-12-28'),
        ('workpants', 'Брюки «Резерв»', 600, '2026-12-16', '2026-12-30'),
    ]
    prices = dict(tee=92000, apron=145000, sweat=185000, hoodie=255000, polo=225000, jogger=215000, vest=360000, workpants=310000)
    fabric = dict(tee=24000, apron=38000, sweat=65000, hoodie=98000, polo=52000, jogger=70000, vest=135000, workpants=105000)
    for i, (kind, title, quantity, start, deadline) in enumerate(specs):
        code = f'D26-{deadline[5:7]}-{i%8+1:02d}'
        own = i % 8 in [0, 6]
        mode = 'OWN' if own else 'CONTRACT'
        ready = i % 4 == 1
        workshop = 'w-main' if kind in ['tee', 'hoodie', 'sweat', 'polo', 'jogger'] else 'd26-workshop-2'
        expenses = [dict(id=code+'-setup', name='Лекала, образец и подготовка партии · демо', kind='direct', cents=1800000+(i%3)*400000), dict(id=code+'-trim', name='Нитки, маркировка и упаковка · демо', kind='direct', cents=quantity*(3800+(i%4)*500)), dict(id=code+'-logistics', name='Внутренняя логистика и доставка партии · демо', kind='direct', cents=850000+(i%3)*150000)]
        if own:
            expenses.append(dict(id=code+'-fabric', name='Ткань, утеплитель и фурнитура компании · демо', kind='direct', cents=quantity*fabric[kind]))
        if ready:
            expenses.append(dict(id=code+'-kits', name='Подрядной раскрой и готовые комплекты · демо', kind='direct', cents=quantity*8000))
        route = route_for(kind)
        part = quantity//3
        variants = [dict(id='size-'+size.lower(), color=['Графит', 'Синий', 'Песочный'][i%3], size=size, quantity=part, ready=part if ready else 0) for size in ['S','M','L']]
        allocations = {}
        for k, op in enumerate(route):
            r = resource_for(op, workshop, i+k)
            fixed='Петельная' in op['equipment'] or op['type']=='qc'
            allocations[op['id']] = dict(resource_id=r['id'] if fixed else '', worker_id=r['worker'] if fixed else '', unassigned=False)
        workspace = dict(schema=1, mode='nodes' if i%3==1 else 'flow', transfer_batch=150, input=dict(mode='ready' if ready else 'cut', max_layers=50, material_width_cm=180, available=True, timing=dict(setup_seconds=600, layer_seconds=8, kit_seconds=route[0]['seconds'], source='Учебная карта раскроя Q4-2026: подготовка настила + время слоя + норма комплекта.'), variants=variants, markers=[dict(id='marker-balanced', name='S:M:L = 2:2:2', sizes={'S':2,'M':2,'L':2}, length_m=6.6 if kind in ['tee','polo'] else 10.8, width_cm=175)], auto=True, lays=[]), allocations=allocations, work_groups={op['id']:op['node_id'] for op in route}, group_names={op['node_id']:op['node_name'] for op in route}, positions={op['id']:dict(x=80+(k//6)*360, y=80+(k%6)*145) for k,op in enumerate(route)}, whole_workers=[], node_workers={})
        record = dict(id=ident('order/'+code), company_id=CID, title=code+' · '+title, product=title+'; размеры S–L; учебная партия', customer='Внутренний заказ коллекции' if own else 'Учебный заказчик '+str(i%6+1).zfill(2), mode=mode, quantity=quantity, deadline=deadline, start_date=start, price_cents=None if own else prices[kind], price_basis='unit', target_cents=fabric[kind]+105000 if own else None, material_owner='company' if own else 'customer', expenses=compact(expenses), route=compact(route), revision=2, status='active', color=['mint','gold','clay'][i%3], created_at=min(start+' 08:00:00',AS_OF_TEXT), _route=route, _expenses=expenses, _workspace=workspace, _workshop=workshop, _kind=kind)
        ORDERS.append(record)


def build_schedule():
    for index, order in enumerate(ORDERS):
        ws = order['_workspace']
        start = datetime.fromisoformat(order['start_date']+' 09:00:00')
        supplies = []
        lays = []
        if ws['input']['mode'] == 'cut':
            rest = order['quantity']//6
            n = 0
            while rest:
                n += 1
                layers = min(rest, 50)
                rest -= layers
                lay_id = f'lay-{n}'
                lay = dict(id=lay_id, color=ws['input']['variants'][0]['color'], marker='marker-balanced', layers=layers, available=True)
                lays.append(lay)
                output = layers*6
                op = copy.deepcopy(order['_route'][0])
                op.update(id='cut-'+lay_id, name='Настил '+lay['color']+' · S:M:L = 2:2:2', seconds=op['seconds'], setup_minutes=10)
                # Layer handling is included once in this prepared cutting job.
                r = resource_for(op, order['_workshop'], index)
                batch = dict(id='cut-'+lay_id, variant_id='cutting', size='Комплект', color=lay['color'], quantity=output)
                t = add_task(order, op, batch, r, [], start, cutting=True, extra_seconds=layers*8)
                meta = json.loads(t['planning_meta'])
                meta['layer_seconds'] = layers*8
                t['planning_meta'] = compact(meta)
                for v in ws['input']['variants']:
                    supplies.append((dict(id=lay_id+'-'+v['id']+'-b1', variant_id=v['id'], size=v['size'], color=v['color'], quantity=layers*2), t))
        else:
            for v in ws['input']['variants']:
                supplies.append((dict(id='ready-'+v['id']+'-b1', variant_id=v['id'], size=v['size'], color=v['color'], quantity=v['quantity']), None))
        ws['input']['lays'] = lays
        done = {}
        for op in order['_route'][1:]:
            for batch, source in supplies:
                deps = [done[batch['id']+'/'+d] for d in op['dependencies'] if d != order['_route'][0]['id']]
                if not deps and source:
                    deps = [source]
                after = max([start] + [d['_end'] for d in deps])
                allocation=ws['allocations'][op['id']]
                resource=next(r for r in NEW_RESOURCES if r['id']==allocation['resource_id']) if allocation['resource_id'] else resource_for(op,order['_workshop'],index,after,op['seconds']*batch['quantity']+op['setup_minutes']*60)
                done[batch['id']+'/'+op['id']] = add_task(order, op, batch, resource, deps, after)
        finals = [t for t in TASKS if t['order_id']==order['id'] and t['is_final']]
        FINISHES[order['id']] = max(t['planned_end'] for t in finals)
        SUMMARIES[order['id']] = dict(mode=ws['mode'], cutting=dict(batches=[dict(batch, ready=source['planned_end'] if source else stamp(start), dependency=source['_job'] if source else None) for batch,source in supplies], lays=[dict(lay, output=lay['layers']*6, marker_name='S:M:L = 2:2:2', ready=next(t['planned_end'] for t in TASKS if t['order_id']==order['id'] and t['operation_id']=='cut-'+lay['id']), meters=lay['layers']*ws['input']['markers'][0]['length_m'], valid=True) for lay in lays], deficit=0, meters=sum(l['layers']*ws['input']['markers'][0]['length_m'] for l in lays), planned=order['quantity']), batches=len(supplies), retained_batches=0, finish=FINISHES[order['id']], partial_finish=FINISHES[order['id']], scope=order['quantity'], errors=[])


def scope_rows(table):
    return [copy.deepcopy(r) for r in DATA.get(table, []) if r.get('company_id')==CID]


def write_sql(company, snapshot, workspaces, old_updates, observations, incident, extra_revisions):
    old_orders = scope_rows('orders')
    old_tasks = scope_rows('tasks')
    guard = ['c.id=@demo_company', 'c.is_demo=1', 'c.revision='+str(company['revision']), 'SHA2(c.passport,256)='+sql(digest(company['passport'])), 'SHA2(c.layout,256)='+sql(digest(company['layout']))]
    for table in ['orders','tasks','plan_versions','production_events','plan_workspaces','plan_workspace_revisions','economy_observations','order_preparations','preparation_revisions']:
        guard.append('(SELECT COUNT(*) FROM `'+table+'` WHERE company_id=@demo_company)='+str(len(scope_rows(table))))
    for o in old_orders:
        guard.append('EXISTS (SELECT 1 FROM orders WHERE id='+sql(o['id'])+' AND company_id=@demo_company AND revision='+str(o['revision'])+' AND SHA2(route,256)='+sql(digest(o['route']))+' AND SHA2(expenses,256)='+sql(digest(o['expenses']))+')')
    for task in old_tasks:
        fields=['revision','quantity','released','transferred','accepted','rework','rework_ready','scrap','state','planned_start','planned_end','resource_id','worker_id','dependencies']
        guard.append('EXISTS (SELECT 1 FROM tasks WHERE id='+sql(task['id'])+' AND company_id=@demo_company AND '+' AND '.join('`'+k+'`='+sql(task[k]) for k in fields)+')')
    for ws in scope_rows('plan_workspaces'):
        guard.append('EXISTS (SELECT 1 FROM plan_workspaces WHERE id='+sql(ws['id'])+' AND revision='+str(ws['revision'])+' AND SHA2(state_json,256)='+sql(digest(ws['state_json']))+')')
    SQL.extend([
        '-- EASY3 Q4-2026: synthetic expansion of the attached existing demo; MySQL 5.7+ / MariaDB.\n-- Import into the CURRENT database. No DROP/TRUNCATE, schema reset, FK disable, new accounts or credentials.\n-- Exact target: latest attached demo, created 2026-10-22 20:53:40. Read README before importing.\n-- Every generated norm, customer, cost and fact is fictional. Dates are fixed to Q4-2026.\n-- Collation fix: binary ASCII identifiers work across utf8/utf8mb4 and mixed table collations.\n-- ROLLBACK clears an unfinished attempt; committed data is never undone.\n-- A second import is a no-op. A changed/missing source is a no-op with an explanatory final result.\n-- If the SQL client reports an error, STOP and ROLLBACK; do not continue to COMMIT manually.',
        'ROLLBACK;',
        'SET @demo_apply=0;',
        'SET @demo_already=0;',
        'SET @demo_old_sql_mode=@@SESSION.sql_mode;',
        'SET @demo_old_time_zone=@@SESSION.time_zone;',
        "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';",
        'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;',
        "SET SESSION time_zone='+03:00';",
        'SET @demo_company=CAST('+sql(CID)+' AS BINARY);',
        'SET @demo_marker=CAST('+sql(ident('applied'))+' AS BINARY);',
        'START TRANSACTION;',
        'SELECT id,name,revision FROM companies WHERE id=@demo_company AND is_demo=1 FOR UPDATE;',
        "SET @demo_owner=(SELECT CAST(user_id AS BINARY) FROM memberships WHERE company_id=@demo_company AND role='owner' ORDER BY user_id LIMIT 1);",
        "SET @demo_qc=COALESCE((SELECT CAST(user_id AS BINARY) FROM memberships WHERE company_id=@demo_company AND role='qc' ORDER BY user_id LIMIT 1),@demo_owner);",
        "SET @demo_foreman=COALESCE((SELECT CAST(user_id AS BINARY) FROM memberships WHERE company_id=@demo_company AND role='foreman' ORDER BY user_id LIMIT 1),@demo_owner);",
        "SET @demo_technologist=COALESCE((SELECT CAST(user_id AS BINARY) FROM memberships WHERE company_id=@demo_company AND role='technologist' ORDER BY user_id LIMIT 1),@demo_owner);",
        "SET @demo_chief=COALESCE((SELECT CAST(user_id AS BINARY) FROM memberships WHERE company_id=@demo_company AND role='chief' ORDER BY user_id LIMIT 1),@demo_owner);",
        'SET @demo_already=EXISTS(SELECT 1 FROM audit_log WHERE id=@demo_marker AND company_id=@demo_company);',
        'SET @demo_apply=IF(@demo_already=0 AND @demo_owner IS NOT NULL AND EXISTS(SELECT 1 FROM companies c WHERE\n'+'\n AND '.join(guard)+'),1,0);',
        'UPDATE companies SET name='+sql('Ателье Север · фабрика Q4 2026')+',passport='+sql(compact(PASSPORT))+',layout='+sql(compact(LAYOUT))+',revision=revision+1 WHERE id=@demo_company AND @demo_apply=1;',
    ])
    clean_orders=[{k:v for k,v in o.items() if not k.startswith('_')} for o in ORDERS]
    insert('orders',clean_orders,size=6)
    for update in old_updates:
        SQL.append(update)
    insert('order_preparations',PREPARATIONS,size=6)
    insert('preparation_revisions',[dict(id=ident('prep-revision/'+p['order_id']),preparation_id=p['id'],company_id=CID,revision=1,state_json=p['state_json'],actor_id=OWNER,created_at=AS_OF_TEXT) for p in PREPARATIONS],size=6)
    insert('plan_workspaces',workspaces,size=6)
    revisions=[dict(id=ident('workspace-revision/'+w['order_id']),workspace_id=w['id'],company_id=CID,revision=w['revision'],state_json=w['state_json'],actor_id=OWNER,created_at=AS_OF_TEXT) for w in workspaces]
    insert('plan_workspace_revisions',revisions,size=6)
    # Store the large snapshot in bounded statement chunks (not one multi-MB literal).
    SQL.append("SET @demo_snapshot='';")
    data=compact(snapshot)
    for pos in range(0,len(data),48000):
        SQL.append('SET @demo_snapshot=CONCAT(@demo_snapshot,'+sql(data[pos:pos+48000])+');')
    approvals=[dict(stage=s,actor_id='__DEMO_'+s.upper()+'__',at='2026-10-23T00:00:00+03:00',input_hash=snapshot['input_hash'],source='Учебное согласование импортируемого сценария; не реальная подпись сотрудника.') for s in ['foreman','technologist','chief']]
    SQL.append('INSERT INTO plan_versions (id,company_id,version_no,state,snapshot,input_hash,approvals,creator_id,published_at,created_at) SELECT '+','.join([sql(PLAN_ID),'@demo_company',str(max(p['version_no'] for p in scope_rows('plan_versions'))+1),"'published'",'@demo_snapshot',sql(snapshot['input_hash']),sql(compact(approvals)),'@demo_owner',sql(AS_OF_TEXT),sql(AS_OF_TEXT)])+' FROM DUAL WHERE @demo_apply=1;')
    insert('tasks',[{k:v for k,v in t.items() if not k.startswith('_')} for t in TASKS],size=48)
    insert('production_events',EVENTS,size=80)
    insert('economy_observations',observations,size=20)
    insert('plan_workspace_revisions',extra_revisions)
    audit=[dict(id=ident('applied'),company_id=CID,actor_id=OWNER,action='demo_q4_2026_seed',details=compact(dict(source=NAMESPACE,synthetic=True,as_of=AS_OF_TEXT,added_goals=len(ORDERS),added_tasks=len(TASKS),added_events=len(EVENTS),incident_task_id=incident['id'] if incident else None,incident_order_id=incident['order_id'] if incident else None,notice='Старые опубликованные планы и события сохранены. Наполнение выполняется один раз; последующие импорты не переписывают работу пользователя.')),created_at=AS_OF_TEXT)]
    insert('audit_log',audit)
    SQL.extend(['COMMIT;',"SELECT CASE WHEN @demo_apply=1 THEN 'Демо наполнено: 24 цели на октябрь, ноябрь и декабрь 2026' WHEN @demo_already=1 THEN 'Уже применено: данные не изменены' ELSE 'НЕ ПРИМЕНЕНО: исходное демо отсутствует или изменилось после дампа; нужна актуальная БД' END AS result,@demo_company AS company_id;",'SELECT c.name,(SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id) AS goals,(SELECT COUNT(*) FROM tasks t WHERE t.company_id=c.id AND t.state<>\'cancelled\') AS tasks,JSON_LENGTH(c.passport,\'$.people\') AS people,JSON_LENGTH(c.passport,\'$.resources\') AS resources FROM companies c WHERE c.id=@demo_company;', 'SET SESSION sql_mode=@demo_old_sql_mode;','SET SESSION time_zone=@demo_old_time_zone;'])




original_prepare_orders = prepare_orders
original_route_for = route_for

def canonical_item(item):
    item=copy.deepcopy(item)
    name=(item.get('name','')+' '+item.get('equipment','')).lower()
    spec=item.get('specialization')
    if item['type']=='overlock':spec='overlock'
    elif item['type']=='sew':
        for stem,kind in [('петель','buttonhole'),('петлю','buttonhole'),('пуговиц','button'),('пугович','button'),('закрепки','bar_tack'),('закрепить места','bar_tack')]:
            if stem in name:spec=kind;break
    if spec:
        item.update(type='special',specialization=spec)
        item['equipment']=dict(overlock='Оверлок',buttonhole='Петельная машина',button='Пуговичная машина',bar_tack='Закрепочная машина').get(spec,item.get('equipment','Спецмашина'))
    return item

def assembly_route(route):
    old={op['id']:op for op in route}
    excluded={op['id'] for op in route if op['type']=='cut' or 'комплект' in op['name'].lower() or 'проверить крой' in op['name'].lower()}
    def predecessors(oid):
        return [d for dep in old[oid].get('dependencies',[]) for d in (predecessors(dep) if dep in excluded else [dep])]
    out=[]
    for op in route:
        if op['id'] in excluded:continue
        op=canonical_item(op);op['dependencies']=list(dict.fromkeys(predecessors(op['id'])));out.append(op)
    return out

def route_for(kind):
    return assembly_route(original_route_for(kind))

def resource_for(op,workshop,seed,after=None,seconds=None):
    candidates=[v for v in PASSPORT['resources'] if v['active'] and v['workshop']==workshop and v['type']==op['type'] and v.get('worker') and (op['type']!='special' or v.get('specialization')==op.get('specialization'))]
    if not candidates:raise ValueError('No resource for '+str((op['name'],op['type'],op.get('specialization'),workshop)))
    if after is not None:return min(candidates,key=lambda v:(fit_work(after,seconds,v,reserve=False)[-1][1],v['id']))
    return candidates[seed%len(candidates)]

def new_resource(wid,kind,n,spec=None):
    code=wid+'-'+kind+'-'+(spec or '')+'-'+str(n)
    rid='d23-'+code;pid='d23p-'+code
    brigade='d26-cut' if wid=='d26-preparation' else 'b-sew' if wid=='w-main' else 'd26-finish' if kind in ['iron','qc','pack'] else 'd26-workwear'
    label=dict(cut='Раскройщик',sew='Швея',special='Оператор спецмашины',hand='Подготовка комплектов' if wid=='d26-preparation' else 'Ручная обработка',iron='Оператор ВТО',qc='Контролёр ОТК',pack='Упаковщик')[kind]
    add_person(pid,label+' '+str(n).zfill(2)+' · демо',label,wid,brigade,[kind,'hand'] if kind not in ['hand'] else ['hand'],['qc'] if kind=='qc' else ['worker'])
    PASSPORT['people'][-1]['specializations']=[spec] if spec else []
    name=dict(cut='Раскройный стол',sew='Прямострочная машина',special=dict(overlock='Оверлок',buttonhole='Петельная машина',button='Пуговичная машина',bar_tack='Закрепочная машина').get(spec,'Спецмашина'),hand='Стол комплектации' if wid=='d26-preparation' else 'Ручное рабочее место',iron='Паровой стол ВТО',qc='Стол ОТК',pack='Упаковочный стол')[kind]
    PASSPORT['resources'].append(dict(id=rid,name=name+' '+str(n).zfill(2),type=kind,specialization=spec,workshop=wid,worker=pid,active=True))

def layout_for(workshop):
    resources=[v for v in PASSPORT['resources'] if v['workshop']==workshop]
    rank=dict(cut=0,hand=1,sew=2,special=3,iron=4,pack=5,qc=6)
    resources.sort(key=lambda v:(rank[v['type']],v.get('specialization') or '',v['id']))
    objects=[];bytype={}
    for i,v in enumerate(resources):
        oid='f-'+v['id'];objects.append(dict(id=oid,resource=v['id'],type=v['type'],x=-12+(i%6)*4.5,z=-7.5+(i//6)*4.5,rotation=0,zone='Подготовка' if workshop=='d26-preparation' else 'Сборка и выпуск'))
        bytype.setdefault(v['type'],[]).append(oid)
    objects.append(dict(id='d23-stock-'+workshop,resource='',type='storage',x=12,z=8,rotation=0,zone='Готовые компоненты' if workshop=='d26-preparation' else 'Готовые изделия'))
    links=[]
    flow=['cut','hand'] if workshop=='d26-preparation' else ['sew','special','hand','iron','pack','qc']
    for a,b in zip(flow,flow[1:]):
        for i,oid in enumerate(bytype.get(a,[])):
            if bytype.get(b):links.append([oid,bytype[b][i%len(bytype[b])]])
    return dict(width=32,depth=24,objects=objects,links=links)

def preparation_for(order,variants):
    start=datetime.fromisoformat(order['start_date']+' 09:00:00')
    items=[dict(v,ready=v['quantity'] if start<AS_OF else 0,expected=0 if start<AS_OF else v['quantity'],ready_date=None if start<AS_OF else order['start_date']) for v in variants]
    original=original_route_for(order['_kind']) if order.get('_kind') else []
    ops=[dict(op,domain='preparation',dependencies=[] if i==0 else [original[0]['id']]) for i,op in enumerate(original[:2])]
    cost=order['quantity']*8000
    state=dict(schema=1,department_id='d26-preparation',unit='комплект',items=items,source='Синтетическая ведомость подготовки на 23.10.2026; комплект деталей для одного готового изделия.',operations=ops,cost_mode='amount',cost_cents=cost)
    PREPARATIONS.append(dict(id=ident('preparation/'+order['id']),company_id=CID,order_id=order['id'],revision=1,state_json=compact(state),actor_id=OWNER,created_at=min(AS_OF_TEXT,order['created_at']),updated_at=AS_OF_TEXT))
    expenses=[v for v in order['_expenses'] if not v['id'].endswith('-kits') and v['id']!='preparation-cost']
    expenses.append(dict(id='preparation-cost',name='Подготовка и комплектация · демо',kind='direct',cents=cost))
    order['_expenses']=expenses;order['expenses']=compact(expenses)
    return state

def prepare_orders():
    original_prepare_orders()
    for order in ORDERS:
        ws=order['_workspace'];ws['input']['mode']='ready';ws['input']['timing']=dict(setup_seconds=None,layer_seconds=None,kit_seconds=None,source='Подготовка ведётся отдельно')
        ws['input']['lays']=[];ws['input']['markers']=[]
        for v in ws['input']['variants']:v['ready']=v['quantity'] if order['start_date']<'2026-10-23' else 0
        preparation_for(order,ws['input']['variants'])

def build_schedule():
    for index,order in enumerate(ORDERS):
        ws=order['_workspace'];start=datetime.fromisoformat(order['start_date']+' 09:00:00');supplies=[]
        for v in ws['input']['variants']:
            rest=v['quantity'];i=0
            while rest:
                i+=1;q=min(rest,ws['transfer_batch']);rest-=q
                supplies.append(dict(id='supply-'+v['id']+'-'+str(i),variant_id=v['id'],size=v['size'],color=v['color'],quantity=q))
        done={}
        for op in order['_route']:
            for batch in supplies:
                deps=[done[batch['id']+'/'+d] for d in op['dependencies']]
                after=max([start]+[d['_end'] for d in deps]);allocation=ws['allocations'][op['id']]
                resource=next(v for v in PASSPORT['resources'] if v['id']==allocation['resource_id']) if allocation['resource_id'] else resource_for(op,order['_workshop'],index,after,op['seconds']*batch['quantity']+op['setup_minutes']*60)
                done[batch['id']+'/'+op['id']]=add_task(order,op,batch,resource,deps,after)
        finals=[t for t in TASKS if t['order_id']==order['id'] and t['is_final']]
        FINISHES[order['id']]=max(t['planned_end'] for t in finals)
        prep=json.loads(next(v['state_json'] for v in PREPARATIONS if v['order_id']==order['id']))
        SUMMARIES[order['id']]=dict(mode=ws['mode'],preparation=dict(department_id='d26-preparation',ready=sum(v['ready'] for v in prep['items']),expected=sum(v['expected'] for v in prep['items']),deficit=0,unit='комплект'),batches=len(supplies),retained_batches=0,finish=FINISHES[order['id']],partial_finish=FINISHES[order['id']],scope=order['quantity'],errors=[])

def apply_facts():
    incident=None
    # A polo has genuine buttonholes, checked at final quality control.
    incident_order=next(o for o in ORDERS if o['_kind']=='polo' and o['deadline'].startswith('2026-10'))
    for task in sorted(TASKS,key=lambda t:(t['planned_end'],t['id'])):
        if task['_end']>=AS_OF:continue
        start=datetime.fromisoformat(task['planned_start']);end=task['_end']
        add_event(task,'start',None,start)
        task['released']=task['transferred']=task['quantity'];add_event(task,'release',task['quantity'],end);add_event(task,'transfer',task['quantity'],end);task['state']='completed'
        if task['is_final']:
            fault=task['order_id']==incident_order['id'] and incident is None
            rework=8 if fault else 0;task.update(accepted=task['quantity']-rework,rework=rework,state='rework' if fault else 'completed')
            add_event(task,'quality',task['quantity'],end,dict(accepted=task['accepted'],rework=rework,scrap=0,reason='Учебный дефект петли планки поло: пропуски обмётки и недостаточная плотность. Проверить иглу/натяжение петельной машины, восстановить петлю, повторить ОТК.' if fault else 'Учебная приёмка размеров, швов, упаковки и маркировки.'))
            if fault:incident=task
    if incident:
        incident['rework_ready']=2
        add_event(incident,'rework_release',4,datetime(2026,10,22,17,50),dict(reason='Четыре петли восстановлены и переданы на повторный ОТК · демо.'))
        add_event(incident,'rework_quality',2,datetime(2026,10,22,17,55),dict(accepted=0,rework=2,scrap=0,reason='Повторный ОТК: две петли ещё не соответствуют плотности обмётки. На доработке 8 изделий, 2 ждут повторной проверки · демо.'))
    return incident

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--source-data',required=True,type=Path)
parser.add_argument('--company',required=True)
parser.add_argument('--output',required=True,type=Path)
args=parser.parse_args()
DATA=json.loads(args.source_data.read_text());CID=args.company
COMPANY=next(c for c in DATA['companies'] if c['id']==CID and c['is_demo'])
NAMESPACE='easy3-demo-q4-20261023-v2';OWNER='__DEMO_OWNER__';QUALITY='__DEMO_QC__'
ACTORS={OWNER:'@demo_owner',QUALITY:'@demo_qc','__DEMO_FOREMAN__':'@demo_foreman','__DEMO_TECHNOLOGIST__':'@demo_technologist','__DEMO_CHIEF__':'@demo_chief'}
AS_OF=datetime(2026,10,23,0,0,0);AS_OF_TEXT=stamp(AS_OF);PLAN_ID=ident('published-plan')
PASSPORT=json.loads(COMPANY['passport']);ORIGINAL_LAYOUT=json.loads(COMPANY['layout'])
PASSPORT.update(area=1800,description='Учебная фабрика Q4-2026. Самостоятельная подготовка компонентов и два швейных цеха. Срез 23 октября. Все новые нормы, суммы и факты вымышлены.')
PASSPORT['structure']['workshops']=[dict(id='w-main',name='Швейный цех 1 · трикотаж',kind='assembly'),dict(id='d26-workshop-2',name='Швейный цех 2 · спецодежда',kind='assembly'),dict(id='d26-preparation',name='Подготовка · раскрой и комплектация',kind='preparation')]
for p in PASSPORT['people']:
    p['specializations']=['overlock'] if 'overlock' in p['skills'] else []
    p['skills']=list(dict.fromkeys('special' if s=='overlock' else s for s in p['skills'] if s!='cut' or p['id']=='p-cut'))
    if p['id']=='p-cut':p.update(workshop='d26-preparation',brigade='d26-cut')
PASSPORT['resources']=[canonical_item(v) for v in PASSPORT['resources']]
for v in PASSPORT['resources']:
    if v['type']=='cut':v['workshop']='d26-preparation'
for bid,wid,name,lead in [('d26-cut','d26-preparation','Подготовка','d26-lead-cut'),('d26-workwear','d26-workshop-2','Спецодежда','d26-lead-workwear'),('d26-finish','d26-workshop-2','Финиш и ОТК','d26-lead-finish')]:
    PASSPORT['structure']['brigades'].append(dict(id=bid,name=name,workshop=wid,lead=lead));add_person(lead,'Бригадир · '+name,'Бригадир',wid,bid,[],['foreman'])
add_person('d23-tech-2','Технолог цеха 2 · демо','Технолог','d26-workshop-2','',[],['technologist'])
for wid in ['w-main','d26-workshop-2']:
    for kind,count in dict(sew=6,hand=2,iron=2,qc=1,pack=1).items():
        for n in range(1,count+1):new_resource(wid,kind,n)
    for spec,count in dict(overlock=3,buttonhole=1,button=1,bar_tack=1).items():
        for n in range(1,count+1):new_resource(wid,'special',n,spec)
for n in range(1,3):new_resource('d26-preparation','cut',n);new_resource('d26-preparation','hand',n)
NEW_RESOURCES=PASSPORT['resources']
PASSPORT['shift']=dict(start='09:00',end='18:00',break_start='13:00',break_minutes=60,days=[1,2,3,4,5])
productive=len({v['worker'] for v in PASSPORT['resources'] if v['worker'] and v['workshop']!='d26-preparation'})
breakdown=[dict(name=n,cents=c) for n,c in [('Аренда производственной площади',55000000),('Электроэнергия и вода',17000000),('Ремонт и обслуживание машин',12000000),('Производственное управление и уборка',26000000)]]
PASSPORT['economy']=dict(labor_hour_cents=52000,monthly_common_cents=sum(v['cents'] for v in breakdown),common_breakdown=breakdown,monthly_hours=productive*168,currency='RUB',excluded=['Продажи OWN и наценка бренда','Налоги и финансы компании','Материалы заказчика, не оплачиваемые фабрикой'],unknown=[])
maps={w['id']:layout_for(w['id']) for w in PASSPORT['structure']['workshops']};LAYOUT=dict(maps['w-main'],workshops=maps)
TASKS=[];ORDERS=[];SCHEDULE=[];EVENTS=[];FINISHES={};SUMMARIES={};BUSY={};SQL=[];PREPARATIONS=[]
old_orders=scope_rows('orders');old_tasks=scope_rows('tasks');old_updates=[];old_workspaces=scope_rows('plan_workspaces')
for order in old_orders:
    order['_expenses']=json.loads(order['expenses']);variants=[dict(id='variant-main',color='Основной',size='Единый',quantity=order['quantity'])]
    preparation_for(order,variants);order['revision']+=1
    old_updates.append('UPDATE orders SET expenses='+sql(order['expenses'])+',revision=revision+1 WHERE id='+sql(order['id'])+' AND company_id=@demo_company AND @demo_apply=1;')
# Reserve every existing historical interval; no current task or published plan is rewritten.
for t in old_tasks:
    a=datetime.fromisoformat(t['planned_start']);b=datetime.fromisoformat(t['planned_end'])
    for key in ['r:'+t['resource_id'],'p:'+t['worker_id']]:BUSY.setdefault(key,[]).append((a,b))
prepare_orders();build_schedule();INCIDENT=apply_facts()
# Snapshot boundary excludes old cutting tasks but keeps them in the database.
old_routes={o['id']:assembly_route(json.loads(o['route'])) for o in old_orders}
old_visible=[t for t in old_tasks if t['operation_id'] in {v['id'] for v in old_routes[t['order_id']]}]
for t in old_visible:
    op=next(v for v in old_routes[t['order_id']] if v['id']==t['operation_id']);key='task-'+t['id'];deps=[d for d in json.loads(t['dependencies']) if d in {v['id'] for v in old_visible}]
    SCHEDULE.append(dict(order_id=t['order_id'],operation_id=t['operation_id'],title=t['title'],resource_id=t['resource_id'],worker_id=t['worker_id'],quantity=t['quantity'],start=t['planned_start'],end=t['planned_end'],slots=[],seconds=op['seconds']*t['quantity']+op['setup_minutes']*60,dependencies=['task-'+d for d in deps],job_key=key,task_id=t['id'],is_final=bool(t['is_final']),pinned=True,retained=True,meta={}))
for o in old_orders:FINISHES[o['id']]=max(t['planned_end'] for t in old_visible if t['order_id']==o['id'])
all_orders=old_orders+ORDERS;all_tasks=old_visible+TASKS;economy={}
for o in all_orders:
    route=o.get('_route') or old_routes[o['id']];entries=[v for v in SCHEDULE if v['order_id']==o['id']];economy[o['id']]=economy_for(o,sum(v['seconds'] for v in entries))
workspaces=[dict(id=ident('workspace/'+o['id']),company_id=CID,order_id=o['id'],revision=1,state_json=compact(o['_workspace']),actor_id=OWNER,created_at=min(o['created_at'],AS_OF_TEXT),updated_at=AS_OF_TEXT) for o in ORDERS]
company_revision=COMPANY['revision']+1
input_hash=digest(compact(dict(version='easy3-assembly-preparation/3.0',company_revision=company_revision,orders=[[o['id'],o['revision']] for o in sorted(all_orders,key=lambda x:x['id'])],tasks=[[t['id'],t['revision'],t['state']] for t in sorted(all_tasks,key=lambda x:x['id'])])))
snapshot=dict(engine='easy3-demo-assembly/20261023',status='feasible',errors=[],warnings=['Синтетическое учебное наполнение на 23.10.2026, не реальный учёт.'],schedule=SCHEDULE,strategy='deadline',calculated_at='2026-10-23T00:00:00+03:00',timezone='Europe/Moscow',order_finishes=FINISHES,late_orders=sum(FINISHES[o['id']][:10]>o['deadline'] for o in all_orders),finish=max(FINISHES.values()),economy=economy,layout=LAYOUT,resource_snapshot=PASSPORT['resources'],people_snapshot=PASSPORT['people'],structure_snapshot=PASSPORT['structure'],shift_snapshot=PASSPORT['shift'],order_quantities={o['id']:o['quantity'] for o in all_orders},order_revisions={o['id']:o['revision'] for o in all_orders},routes={o['id']:o.get('_route') or old_routes[o['id']] for o in all_orders},workspace_snapshot={w['order_id']:dict(revision=w['revision'],state=json.loads(w['state_json'])) for w in old_workspaces+workspaces},preparation_snapshot={p['order_id']:dict(revision=1,state=json.loads(p['state_json'])) for p in PREPARATIONS},decision_summary=SUMMARIES,company_revision=company_revision,input_hash=input_hash,source_event_count=len(scope_rows('production_events'))+len(EVENTS),assumptions=['Вся добавленная история, нормы, суммы и участники вымышлены. Срез 23.10.2026 00:00 Europe/Moscow.','Два швейных цеха и самостоятельное подразделение подготовки. Только подтверждённая комплектация является фактом.','План снабжения задан вручную датами для будущих изделий. Раскрой не занимает швейных работников.','Рабочий календарь Пн–Пт 09–13 и 14–18, без моделирования праздников.','Новые факты только до 22 октября включительно; ноябрь и декабрь являются прогнозом.','Производственная экономика включает подготовку один раз; OWN не объявляется продажей.','Прежние планы и факты сохранены.'])
observations=[]
for i,o in enumerate(ORDERS):
    accepted=sum(t['accepted'] for t in TASKS if t['order_id']==o['id'] and t['is_final'])
    if not accepted:continue
    ec=economy[o['id']];factor=[98,104,101,112,103,100][i%6]
    observations.append(dict(id=ident('observation/'+o['id']),company_id=CID,order_id=o['id'],actor_id=OWNER,revision=1,accepted_quantity=accepted,recognized_cents=None if o['mode']=='OWN' else accepted*o['price_cents'],direct_cents=money_ratio(ec['direct_cents']*factor,accepted,o['quantity']*100),labor_cents=money_ratio(ec['labor_cents']*factor,accepted,o['quantity']*100),common_cents=money_ratio(ec['common_cents'],accepted,o['quantity']),source='Синтетический накопительный факт принятой части на 23.10.2026; не платёж.',created_at=AS_OF_TEXT))
write_sql(COMPANY,snapshot,workspaces,old_updates,observations,INCIDENT,[])
args.output.mkdir(parents=True,exist_ok=True)
(args.output/'EASY3_fill_demo_20261023.sql').write_text('\n\n'.join(SQL)+'\n')
manifest=dict(version=NAMESPACE,as_of=AS_OF_TEXT,added_orders=len(ORDERS),total_orders=len(all_orders),quantity=sum(o['quantity'] for o in all_orders),people=len(PASSPORT['people']),resources=len(PASSPORT['resources']),assembly_workshops=2,preparation_departments=1,brigades=len(PASSPORT['structure']['brigades']),assembly_operations=sum(len(o['_route']) for o in ORDERS),tasks=len(TASKS),events=len(EVENTS),preparations=len(PREPARATIONS),observations=len(observations),accepted=sum(t['accepted'] for t in TASKS if t['is_final']),incident=next(o['title'] for o in ORDERS if INCIDENT and o['id']==INCIDENT['order_id']) if INCIDENT else None,incident_rework=8 if INCIDENT else 0,deadline_months={m:sum(o['deadline'].startswith(m) for o in ORDERS) for m in ['2026-10','2026-11','2026-12']})
(args.output/'DATASET.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
(args.output/'synthetic-fixture.json').write_text(json.dumps(dict(company=dict(COMPANY,passport=compact(PASSPORT),layout=compact(LAYOUT),revision=company_revision),orders=all_orders,tasks=all_tasks,preparations=PREPARATIONS,snapshot=snapshot),ensure_ascii=False,default=stamp))
print(compact(manifest))
