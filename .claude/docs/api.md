# HTTP API и консольные команды

База: `http://localhost:8080` (nginx). Роуты — атрибуты в `src/UI/Http/Controller/` (`config/routes.yaml` → `type: attribute`). Аутентификации нет.

## HTTP

### `POST /api/matches/parse`

Тело: `{"match_id": 7890123456}`. Нужен `match_id > 0` (`ParseMatchRequest`; отсутствующий ключ → 0 → 400). Кладёт `ParseMatchMessage` в Kafka.
Ответ `202`: `{"message": "Match accepted for processing", "match_id": 7890123456}`. Результат обработки смотри в `match_processing` (architecture.md → «Конвейер»).

### `GET /api/players/top?metric=gpm&limit=50&offset=0`

Средняя метрика по всем строкам `player_match_stats` игрока, `ORDER BY metric_value DESC`. Фильтров по команде, дате или роли нет.
`metric` ∈ `gpm, xpm, net_worth, total_gold, total_xp, kills, deaths, assists, hero_damage, tower_damage, hero_healing, creeps_last_hits, creeps_denies, observers_placed, teamfight_participation` (whitelist продублирован в `GetTopPlayersQuery` и `GetTopPlayersByMetricService::SUPPORTED_METRICS`), `limit` 1..200 (по умолчанию 50), `offset ≥ 0`.
Ответ `200`: `[{"player_id": 123, "metric": "gpm", "value": 712.4, "matches_count": 37}, …]`.

### `GET /api/fantasy/top-metrics`

| Параметр | Тип | По умолчанию | Смысл |
|---|---|---|---|
| `team_ids` | `"2586976,2163"` | все команды | фильтр `opendota_team_id IN (…)`; нечисловые элементы молча отбрасываются |
| `player_ids` | `"94155156,…"` | — | исключить матчи, где есть игрок не из списка (fantasy.md → «Фильтр player_ids») |
| `min_matches_per_player` | int > 0 | 10 | порог матчей игрока |
| `date_from`, `date_to` | `Y-m-d H:i:s` | — | `Assert\DateTime` принимает только полный формат, `Y-m-d` → 400 |
| `matches_limit` | int > 0 | — | последние N матчей **на каждую команду** |

Ответ `200` (`TopFantasyMetricsResult::toArray`):

```json
{
  "team_ids": [2586976] | null,
  "player_ids": [ …eligible player ids… ] | null,
  "team_count": 3, "player_count": 15,
  "role_groups": [{
    "group": "core_side", "group_label": "Core Side (Carry + Offlaner)", "roles": [1, 3],
    "top_metrics": [{"metric": "gpm_score", "avg_value": 1234.5, "contribution_percent": 18.2, "type": "core", "name": "Red (Core)"}],
    "top_combos": [{
      "team_id": 2586976,
      "top_metrics": [{"metric": …, "avg_value": …, "type": …, "name": …}],
      "players": [{
        "player_id": 1, "nickname": "Yatoro", "role": 1, "avg_total_score": 9876.5,
        "matches": [{"match_id": …, "match_duration": 2400, "start_time": "Y-m-d H:i:s", "is_win": true,
                     "statistic": {"kills", "deaths", "assists", "gpm", "xpm", "net_worth", "creeps_last_hits", "creeps_denies", "opendota_hero_id"}}],
        "top_metrics": [ …метрики с isTopMetric… ], "all_metrics": [ …16 метрик по убыванию avg… ],
        "matches_count": 20
      }],
      "avg_combined_score": 19000.1, "matches_count": 20, "avg_match_duration": 2310.4
    }],
    "matches_analyzed": 400, "players_analyzed": 40
  }]
}
```

`player_ids` в ответе — не входной фильтр, а список прошедших фильтры игроков (`getEligiblePlayerIds`). Группа ролей без подходящих игроков в `role_groups` не попадает. Алгоритм описан в fantasy.md.

### Ошибки (`Infrastructure/Http/EventSubscriber/ExceptionSubscriber`)

- `ValidationFailedException` → `400 {"error": "msg1, msg2"}`
- `UnsupportedMetricException`, `InvalidPaginationException` → `400 {"error": "…"}`
- всё остальное логируется и **пробрасывается дальше** (стандартная страница ошибки Symfony, в `dev` — с трассой). Например, `InvalidArgumentException` из VO бандла или `DateMalformedStringException` дают 500.

Подписчик глобальный и срабатывает на любой запрос, не только на `/api`.

## Консоль (`docker exec -it dota_app php bin/console …`)

| Команда | Аргументы и опции | Что делает |
|---|---|---|
| `parse:kafka:match <matchId>` | — | `ParseMatchMessage` в Kafka, без валидации |
| `match:retry-failed` | — | все `match_processing.status='failed'` → снова в очередь, печатает id |
| `team:fetch-matches <team-id>` | `-l/--limit`, `--date-from`, `--date-to` (`Y-m-d` или `Y-m-d H:i:s`) | матчи команды из OpenDota → очередь парсинга + `team_matches` |
| `team:fetch-multiple-matches "<ids>"` | `-l/--limit` | то же для списка команд по очереди (без дат); ошибки по командам — таблицей |
| `team:analyze-form "<ids>"` | `--date-from`, `--date-to` | форма команд по БД → `team_form_analysis`; печатает число успешных и таблицу ошибок |
| `fantasy:top-metrics` | `-t/--team-ids`, `-p/--player-ids`, `-m/--min-matches` (10), `--date-from`, `--date-to`, `-l/--matches-limit` | печатает только `top_metrics` групп ролей (без связок). Validator не вызывается |
| `fantasy:export-top-metrics` | те же + `-o/--output` | полный JSON как у HTTP (с валидацией) в файл; по умолчанию `var/exports/fantasy/top-metrics_teams-…_min-10_…_<Ymd-His>.json` |

Типичный сценарий подготовки данных: `team:fetch-multiple-matches "ids" -l 50` → дождаться воркеров (`match_processing` в `completed`) → `team:analyze-form` / `fantasy:export-top-metrics -t "ids" -l 20`.

ID команд для примеров: Team Spirit `2586976`, Team Liquid `2163`, Evil Geniuses `39`, PSG.LGD `15`, Tundra `8291895`.
