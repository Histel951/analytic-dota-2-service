# Эталон поведения для рефакторинга

Сверка «поведение не изменилось» без тестов (refactoring-plan.md → «Шаги»).

- `dataset/` — фиксированный набор: 4 команды (`8261500` Xtreme Gaming, `2586976` Team Spirit, `2163` Team Liquid, `8291895` Tundra) × 25 последних матчей на 2026-10-31, всего 95 уникальных матчей. `match_ids.txt` — матчи, `team_matches.csv` — связи команда–матч (100 строк).
- `step1/` — выводы после шага 1 (dota-api 2.0, роли из `position_est`): состояние PG/CH, `/api/players/top`, `/api/fantasy/top-metrics` (6 наборов фильтров, в т.ч. ошибка даты и баг `player_ids`), `fantasy:export-top-metrics`, `fantasy:top-metrics`, `team:analyze-form` + `team_form_analysis`.
- Шаг 2 (раскладка по контекстам) сверен: выводы совпали со `step1/` байт в байт, отдельный каталог не хранится.
- `load.sh` — очистить БД и загрузить набор заново; `capture.sh <dir>` — снять выводы.

Повторное снятие на тех же данных даёт байт-в-байт тот же результат (проверено 2026-10-31).

Сверка после шага N:

```bash
.claude/baseline/load.sh                 # дождаться completed у всех 95 (429 → match:retry-failed)
.claude/baseline/capture.sh .claude/baseline/stepN
diff -r .claude/baseline/step1 .claude/baseline/stepN
```

Ожидаемые отличия: только там, где шаг явно меняет внутреннее состояние (например, статусы в `pg_processing.txt` на шаге 3). Всё, что видно снаружи (HTTP, CLI, данные CH), должно совпасть.
