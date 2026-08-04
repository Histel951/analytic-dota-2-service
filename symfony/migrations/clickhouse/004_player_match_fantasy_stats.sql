CREATE TABLE player_match_fantasy_stats
(
    match_id UInt64,
    player_id UInt64,

    kills_score Int32,
    deaths_score Int32,
    creeps_score Int32,
    gpm_score Int32,
    tower_score Int32,
    observer_score Int32,
    stacks_score Int32,
    rune_score Int32,
    roshan_score Int32,
    courier_score Int32,
    stun_score Float32,
    first_blood_score Int32,
    teamfight_score Float32,

    total_score Float32,

    version UInt8 DEFAULT 1,
    calculated_at DateTime DEFAULT now()
)
    ENGINE = ReplacingMergeTree(calculated_at)
ORDER BY (player_id, match_id);