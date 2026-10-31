# Базы данных

Doctrine Migrations и ORM не используются. Схема задаётся SQL-файлами в `symfony/migrations/{psql,clickhouse}/`, которые применяются вручную. Доступ — сырой SQL: DBAL `Connection` для Postgres, `ClickHouseDB\Client` для ClickHouse.

## PostgreSQL (`DATABASE_URL=pgsql://dota:dota@postgres:5432/dota`)

| Таблица | Ключ | Колонки | Пишет | Читает |
|---|---|---|---|---|
| `matches` | `match_id` | `start_time`, `duration` (сек), `radiant_win`, `region` (int, id региона бандла, nullable), `created_at` | `PersistMatchDataHandler` (UPSERT) | `TeamFormAnalyzer` (регион, время последнего матча), JOIN в `team_matches` |
| `match_players` | (`match_id`, `player_id`), FK → `matches` ON DELETE CASCADE | `player_pro_name` varchar(50) | `PersistMatchDataHandler` (`ON CONFLICT DO NOTHING`) | никнеймы в fantasy (`getNicknamesByIds`) |
| `match_processing` | `match_id` (без FK: строка создаётся до `matches`) | `status`, `parsed_at` (NOT NULL), `fetched_at`, `analytics_processed_at`, `fantasy_processed_at`, `created_at` | все хэндлеры Match | `FetchMatchFromApiHandler`, `match:retry-failed` |
| `team_matches` | (`team_id`, `match_id`) | `is_win`, `created_at` | `team:fetch-matches` (UPSERT) | `team:analyze-form` (INNER JOIN `matches`) |
| `messenger_messages` | — | служебная Messenger | создаётся `auto_setup` транспорта `async` | — |

Индексы (004): `matches(start_time DESC, created_at DESC, radiant_win)`, `match_players(player_id, created_at DESC)`, `match_processing(status, parsed_at DESC, created_at DESC, (status, created_at))`, частичный `(status, created_at) WHERE status='failed'`. Миграция 003 создаёт индекс `idx_matches_cluster` на колонке `region` (имя осталось от колонки `cluster`).

## ClickHouse (`CLICKHOUSE_DATABASE`, в `.env` = `analytics`)

Все таблицы — `ReplacingMergeTree(<время версии>)`. Дубли схлопываются только при фоновом слиянии, а запросы приложения `FINAL` не используют (known-issues.md).

**`player_match_stats`** — `ORDER BY (player_id, match_id)`, версия `created_at`. Одна строка на игрока в матче, все 10 игроков:

- идентификаторы и контекст: `match_id`, `duration`, `player_id`, `opendota_hero_id`, `opendota_team_id`, `side` (String, значение enum `Side` бандла), `win`, `lane`, `role` (1–5, 0 = UNKNOWN), `start_time`
- KDA и экономика: `kills`, `deaths`, `assists`, `gpm`, `xpm`, `net_worth`, `total_gold`, `total_xp`, `gold_spend`
- крипы: `creeps_last_hits`, `creeps_lane_last_hits`, `creeps_denies`, `creeps_neutrals`, `creeps_ancients`, `camps_stacked` (003)
- объекты: `roshan_kills`, `tower_kills`, `rune_pickups`, `tormentor_kills` (005)
- варды: `observers_placed`, `observers_destroyed`, `sentry_placed`, `sentry_destroyed`, `observer_takeovers` (005)
- урон и прочее: `hero_damage`, `tower_damage`, `hero_healing`, `buyback_count`, `first_blood_claimed`, `courier_kills`, `stuns` (Float32), `teamfight_participation` (Float32, 0..1), `smoke_uses` (005)

`lane` пишется тем же значением, что `role` (`AnalyticMatchAnalyticsRepository::createStatsData`).

**`player_match_fantasy_stats`** — `ORDER BY (player_id, match_id)`, версия `calculated_at`. Очки по метрикам: `kills_score`, `deaths_score`, `creeps_score`, `gpm_score`, `tower_score`, `observer_score`, `stacks_score`, `rune_score`, `roshan_score`, `courier_score`, `first_blood_score`, `tormentor_score`, `smoke_score`, `watcher_score` (Int32, дробная часть отбрасывается при записи), `stun_score`, `teamfight_score`, `total_score` (Float32), `version` (=1). Последние три `*_score` добавлены в 006.

**`team_form_analysis`** — `ORDER BY (team_id, analyzed_at)`, версия `analyzed_at`, то есть каждый анализ — новая строка истории. Колонки: `team_name`, `form` Enum8(`prime`=1, `high`, `medium`, `low`), `winrate` (0..100), `region` (название), `matches_analyzed` Array(UInt64), `last_match_time`, `matches_count`.

## Миграции

```
migrations/psql/        001 matches, match_players, match_processing
                        002 team_matches (+ индексы, COMMENT)
                        003 matches.region + idx_matches_cluster
                        004 FK match_players → matches, индексы (в транзакции)
migrations/clickhouse/  001 player_match_stats
                        002 team_form_analysis
                        003 player_match_stats.camps_stacked
                        004 player_match_fantasy_stats
                        005 player_match_stats.tormentor_kills, smoke_uses, observer_takeovers
                        006 player_match_fantasy_stats.tormentor_score, smoke_score, watcher_score
```

Учёта применённых миграций нет. Порядок такой: сначала Postgres, затем ClickHouse, внутри — по номеру. Повторно применять безопасно только файлы с `IF NOT EXISTS` (psql 002, 004; clickhouse 002, 003, 005, 006).

Применение (каталог `symfony/` смонтирован в `app` как `/var/www`; в контейнеры БД миграции не смонтированы, поэтому SQL передаётся через stdin):

```bash
# PostgreSQL
for f in symfony/migrations/psql/*.sql; do docker exec -i dota_postgres psql -U dota -d dota < "$f"; done

# ClickHouse (БД из CLICKHOUSE_DATABASE; создать при первом запуске)
docker exec -i dota_clickhouse clickhouse-client -q "CREATE DATABASE IF NOT EXISTS analytics"
for f in symfony/migrations/clickhouse/*.sql; do docker exec -i dota_clickhouse clickhouse-client -d analytics --multiquery < "$f"; done
```
## Полезные запросы

```bash
docker exec -it dota_postgres psql -U dota -d dota -c "SELECT status, count(*) FROM match_processing GROUP BY status;"
docker exec -it dota_clickhouse clickhouse-client -d analytics -q "SELECT count(), uniqExact(match_id) FROM player_match_stats"
docker exec -it dota_clickhouse clickhouse-client -d analytics -q "OPTIMIZE TABLE player_match_stats FINAL"   # схлопнуть дубли
```
