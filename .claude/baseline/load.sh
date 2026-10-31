#!/usr/bin/env bash
# Загружает эталонный набор данных заново: очищает БД, восстанавливает связи команда–матч
# из dataset/team_matches.csv и ставит каждый матч из dataset/match_ids.txt в очередь парсинга.
# Использование (из корня репозитория, стек Docker поднят, миграции применены):
#   .claude/baseline/load.sh
# Затем дождаться, пока все матчи в match_processing станут completed
# (429 от OpenDota → match:retry-failed), и запустить capture.sh.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
PG="docker exec -i dota_postgres psql -q -v ON_ERROR_STOP=1 -U dota -d dota"

$PG -c "TRUNCATE match_players, matches, match_processing, team_matches, messenger_messages"
for t in player_match_stats player_match_fantasy_stats team_form_analysis; do
  docker exec -i dota_clickhouse clickhouse-client -d analytics -q "TRUNCATE TABLE $t"
done

$PG -c "\copy team_matches (team_id, match_id, is_win) FROM STDIN WITH (FORMAT csv)" < "$DIR/dataset/team_matches.csv"

while read -r id; do
  [ -n "$id" ] && docker exec dota_app php bin/console parse:kafka:match "$id" > /dev/null
done < "$DIR/dataset/match_ids.txt"

echo "queued $(grep -c . "$DIR/dataset/match_ids.txt") matches"
