CREATE TABLE IF NOT EXISTS team_form_analysis
(
    team_id UInt64,
    team_name String,
    form Enum8('prime' = 1, 'high' = 2, 'medium' = 3, 'low' = 4),
    winrate Float32,
    region String,
    matches_analyzed Array(UInt64),
    last_match_time DateTime,
    analyzed_at DateTime DEFAULT now(),
    matches_count UInt16
)
ENGINE = ReplacingMergeTree(analyzed_at)
ORDER BY (team_id, analyzed_at);
