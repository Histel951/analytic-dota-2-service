CREATE TABLE matches
(
    match_id BIGINT PRIMARY KEY,
    start_time TIMESTAMP NOT NULL,
    duration INTEGER NOT NULL,
    radiant_win BOOLEAN NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE match_players
(
    match_id BIGINT,
    player_id BIGINT,
    player_pro_name VARCHAR(50) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (match_id, player_id)
);

CREATE TABLE match_processing
(
    match_id BIGINT PRIMARY KEY,
    fetched_at TIMESTAMP,
    analytics_processed_at TIMESTAMP,
    fantasy_processed_at TIMESTAMP,
    status VARCHAR(50),
    parsed_at TIMESTAMP NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)