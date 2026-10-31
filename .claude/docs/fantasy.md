# Fantasy

## Расчёт очков (`Domain/Match/Service/FantasyScoreCalculator`)

Считается в `ProcessFantasyScoreHandler` по строкам `player_match_stats` матча. Коэффициенты — `parameters.fantasy_scoring.*` в `config/services.yaml` («The International 2026») → `FantasyScoringConfig`. Чтобы поменять коэффициенты, достаточно отредактировать YAML; пересчёт уже посчитанных матчей не предусмотрен (`version` в таблице всегда 1).

| Метрика (`*_score`) | Формула | Коэфф. | Эмблема |
|---|---|---|---|
| kills | kills × k | 107 | core |
| deaths | max(0, 1950 − deaths × 195) | base 1950, penalty 195 | core |
| creeps | (last_hits + denies) × k | 3 | core |
| gpm | gpm × k | 2 | core |
| tower | tower_kills × k | 352 | core |
| teamfight | min(teamfight_participation × 2124, 2124) | 2124 / max 2124 | utility |
| tormentor | tormentor_kills × k | 879 | utility |
| first_blood | 1934, если first_blood_claimed | 1934 | utility |
| roshan | roshan_kills × k | 1172 | utility |
| courier | courier_kills × k | 703 | utility |
| stun | stuns (сек) × k | 10 | utility |
| smoke | smoke_uses × k | 293 | support |
| stacks | camps_stacked × k | 234 | support |
| watcher | observer_takeovers × k | 147 | support |
| observer | observers_placed × k | 117 | support |
| rune | rune_pickups × k | 141 | support |

`total_score` — сумма всех 16. При записи в ClickHouse всё, кроме `stun_score`, `teamfight_score` и `total_score`, приводится к `int`.
## Группы ролей и эмблемы (`Domain/Fantasy/Enum`)

`RoleGroup` (роль = `player_match_stats.role`, 1–5 от бандла):

| Группа | Роли | Квота эмблем в топ-метриках |
|---|---|---|
| `core_side` — Carry + Offlaner | 1, 3 | 2 core + 1 utility |
| `mid` | 2 | 1 core + 1 utility + 1 support |
| `support` — Soft + Hard | 4, 5 | 2 support + 1 utility |

`EmblemType`: `core` «Red (Core)», `utility` «Green (Utility)», `support` «Blue (Support)». Соответствие метрика → эмблема — столбец «Эмблема» выше; `EmblemType::fromMetric` бросает исключение на неизвестную метрику.

`SelectTopMetricsByEmblemRules::select(RoleGroup, contributions)` раскладывает метрики по эмблемам, внутри эмблемы сортирует по `contribution` desc, берёт по квоте группы, итог снова сортирует по `contribution` desc и возвращает `MetricContribution[]` (процент округлён до 2 знаков, валидация 0..100). Отсюда «топ-3»: сумма квот всегда равна 3.

`contribution` = |avg(metric) / avg(total_score)| × 100.

## Алгоритм `/api/fantasy/top-metrics`

Вся работа — в `Infrastructure/ClickHouse/Repository/ClickHouseFantasyMetricAnalysisRepository`. `GetTopFantasyMetricsService` только вызывает три метода репозитория и маппит домен в DTO.

**Общие фильтры** (`whereClause` над `player_match_stats pms`): `role IN (роли группы)`, `opendota_team_id IN (team_ids)`, `start_time` в `[date_from, date_to]`, фильтр `player_ids`.

**`limited_matches` (`buildLimitedMatchesCte`).** Без `matches_limit` — все строки `pms`, прошедшие фильтр, у eligible-игроков. С `matches_limit = N` сначала строится `limited_team_matches`: пары (`match_id`, `opendota_team_id`), `ORDER BY start_time DESC LIMIT N BY opendota_team_id`, то есть последние N матчей **каждой команды**, а не глобальный лимит. Затем строки `pms` джойнятся с этими парами.

**По каждой `RoleGroup` (`getTopMetricsForRoleGroup`):**

1. eligible-игроки — `player_id` с `count(*) >= min_matches` по фильтру группы. Пусто → группа пропускается
2. средние всех `*_score` и `total_score` по `player_match_fantasy_stats ⋈ limited_matches` → `contribution` → `top_metrics` группы по квотам эмблем; `matches_analyzed` = число строк, `players_analyzed` = число разных игроков
3. `top_combos`:
   - **mid** (`getTopPlayersForSingleRole`) — eligible по (`player_id`, `opendota_team_id`), средние по игроку в рамках команды, `ORDER BY avg_total_score DESC`. Каждая «связка» — один игрок
   - **core_side / support** (`getTopCombosForDoubleRoles`) — eligible по (`player_id`, `team`, `role`) → `player_metrics` → `team_combos` по команде: `combined_score = sum(avg_total_score)` всех игроков команды в этих ролях, `matches_count = min(matches_count)`, `avg_match_duration`. Сортировка по `combined_score DESC`. Ограничения «ровно один игрок на роль» нет (known-issues.md)
   - для каждой связки: `top_metrics` по суммам avg игроков и квотам группы (`buildComboTopMetrics`); у игрока — 16 `PlayerMetrics` по убыванию avg с флагом `isTopMetric` (квоты группы его роли), никнейм из Postgres `match_players` (`Unknown`, если нет), список матчей из `limited_matches` (`fetchPlayerMatchesByRows`, ключ `"{player}_{team}"`, `start_time DESC`)

**`team_count` / `player_count`** — `getEligibleTeamIds` / `getEligiblePlayerIds`: те же фильтры, но **без фильтра по ролям и без `matches_limit`** (параметр принимается и игнорируется). Поэтому эти числа не обязаны совпадать с суммой по группам.

Игрок, сменивший команду, учитывается отдельно в каждой команде: связки строятся по (`player_id`, `opendota_team_id`). Определения «актуальной команды игрока по последнему матчу» (было в исходных требованиях) в коде нет.

## Фильтр `player_ids`

```sql
AND pms.match_id NOT IN (SELECT DISTINCT match_id FROM player_match_stats WHERE player_id NOT IN (<player_ids>))
```

Задумка («подход B») — отбросить матчи, где у команды играла замена. Но в `player_match_stats` лежат все 10 игроков матча, включая соперников, поэтому подзапрос исключает практически все матчи, если в списке нет игроков обеих сторон. Нужна привязка к стороне: `opendota_team_id` или `side` (known-issues.md).

## Отладка

Репозиторий пишет в `error_log` число строк для каждой роли или пары ролей (`Single role %d: returned %d rows …`, `Double roles [...]`). Это видно в логах php-fpm (`docker logs dota_app`) и в stderr консольной команды.
