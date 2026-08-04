CREATE TABLE player_match_stats
(
    match_id UInt64,
    duration UInt32,
    player_id UInt64,

    opendota_hero_id UInt32,
    opendota_team_id UInt64,

    kills UInt16,
    deaths UInt16,
    assists UInt16,

    gpm UInt32,
    xpm UInt32,
    net_worth UInt32,
    total_gold UInt32,
    total_xp UInt32,
    gold_spend UInt32,

    creeps_last_hits UInt16,
    creeps_lane_last_hits UInt16,
    creeps_denies UInt16,
    creeps_neutrals UInt16,
    creeps_ancients UInt16,

    roshan_kills UInt8,
    tower_kills UInt8,
    rune_pickups UInt8,

    observers_placed UInt16,
    observers_destroyed UInt16,
    sentry_placed UInt16,
    sentry_destroyed UInt16,

    hero_damage UInt64,
    tower_damage UInt64,
    hero_healing UInt64,

    buyback_count UInt8,
    first_blood_claimed Bool,
    courier_kills UInt8,
    stuns Float32,
    teamfight_participation Float32,

    side String,
    win Bool,

    lane UInt8,
    role UInt8,

    start_time DateTime,
    created_at DateTime DEFAULT now()
)
    ENGINE = ReplacingMergeTree(created_at)
ORDER BY (player_id, match_id);
