-- Таблица для связи команд и матчей
CREATE TABLE IF NOT EXISTS team_matches
(
    team_id BIGINT NOT NULL,
    match_id BIGINT NOT NULL,
    is_win BOOLEAN NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (team_id, match_id)
);

-- Индексы для быстрого поиска
CREATE INDEX idx_team_matches_team_id ON team_matches(team_id);
CREATE INDEX idx_team_matches_match_id ON team_matches(match_id);

-- Комментарии
COMMENT ON TABLE team_matches IS 'Связь между командами и их матчами для автономного анализа';
COMMENT ON COLUMN team_matches.is_win IS 'TRUE если команда выиграла этот матч';
