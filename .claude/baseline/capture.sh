#!/usr/bin/env bash
# Снимает выводы приложения для сверки поведения между шагами рефакторинга.
# Использование (из корня репозитория, стек Docker поднят, данные загружены):
#   .claude/baseline/capture.sh <каталог-вывода>
# Набор данных — команды и матчи из .claude/baseline/dataset/ (см. README.md рядом).
set -euo pipefail

OUT="${1:?usage: capture.sh <out-dir>}"
mkdir -p "$OUT"

TEAMS="8261500,2586976,2163,8291895"
API="http://localhost:8080"
CH="docker exec -i dota_clickhouse clickhouse-client -d analytics -q"
PG="docker exec -i dota_postgres psql -tA -U dota -d dota -c"

# --- Состояние данных
$PG "SELECT status, count(*) FROM match_processing GROUP BY status ORDER BY status" > "$OUT/pg_processing.txt"
$PG "SELECT match_id, start_time, duration, radiant_win, region FROM matches ORDER BY match_id" > "$OUT/pg_matches.txt"
$PG "SELECT match_id, player_id, player_pro_name FROM match_players ORDER BY match_id, player_id" > "$OUT/pg_match_players.txt"
$CH "SELECT * EXCEPT created_at FROM player_match_stats ORDER BY match_id, player_id FORMAT TSV" > "$OUT/ch_player_match_stats.tsv"
$CH "SELECT * EXCEPT calculated_at FROM player_match_fantasy_stats ORDER BY match_id, player_id FORMAT TSV" > "$OUT/ch_player_match_fantasy_stats.tsv"

# --- HTTP
for m in gpm kills deaths teamfight_participation observers_placed; do
  curl -s "$API/api/players/top?metric=$m&limit=30" > "$OUT/http_players_top_$m.json"
done
curl -s "$API/api/players/top?metric=unknown" > "$OUT/http_players_top_error.json"

fantasy() { # $1 — имя, $2 — query string
  curl -s "$API/api/fantasy/top-metrics?$2" > "$OUT/http_fantasy_$1.json"
}
fantasy all_min5 "min_matches_per_player=5"
fantasy teams_min3_limit10 "team_ids=$TEAMS&min_matches_per_player=3&matches_limit=10"
fantasy spirit_min1 "team_ids=2586976&min_matches_per_player=1"
fantasy dates "min_matches_per_player=2&date_from=2026-01-01%2000:00:00&date_to=2026-12-31%2023:59:59"
fantasy bad_date "date_from=2026-01-01"

# player_ids — фильтр с известным багом (known-issues.md §1): фиксируем как есть
SPIRIT_PLAYERS=$($CH "SELECT arrayStringConcat(groupUniqArray(toString(player_id)), ',') FROM (SELECT player_id FROM player_match_stats WHERE opendota_team_id = 2586976 ORDER BY player_id LIMIT 5)")
fantasy spirit_players "team_ids=2586976&player_ids=$SPIRIT_PLAYERS&min_matches_per_player=1"

# --- CLI
docker exec dota_app php bin/console fantasy:export-top-metrics -t "$TEAMS" -m 3 -l 10 -o var/exports/fantasy/baseline-cli.json > /dev/null
docker exec dota_app cat var/exports/fantasy/baseline-cli.json > "$OUT/cli_fantasy_export.json"
docker exec dota_app rm -f var/exports/fantasy/baseline-cli.json
docker exec dota_app php bin/console fantasy:top-metrics -t "$TEAMS" -m 3 2>/dev/null | grep -v '^\s*$' > "$OUT/cli_fantasy_top_metrics.txt"

# --- Форма команд (пишет в CH; analyzed_at исключаем)
$CH "TRUNCATE TABLE team_form_analysis"
docker exec dota_app php bin/console team:analyze-form "$TEAMS" 2>/dev/null | grep -v '^\s*$' > "$OUT/cli_team_analyze_form.txt"
$CH "SELECT team_id, team_name, form, winrate, region, arraySort(matches_analyzed), last_match_time, matches_count FROM team_form_analysis ORDER BY team_id FORMAT TSV" > "$OUT/ch_team_form_analysis.tsv"

echo "captured into $OUT"
ls -1 "$OUT" | wc -l
