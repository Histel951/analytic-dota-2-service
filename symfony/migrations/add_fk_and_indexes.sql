-- ============================================
-- FK
-- ============================================

BEGIN;

-- ============================================
-- 1. Foreign Keys (только match_players)
-- ============================================

-- FK для match_players
ALTER TABLE match_players 
    DROP CONSTRAINT IF EXISTS fk_match_players_match,
    ADD CONSTRAINT fk_match_players_match 
        FOREIGN KEY (match_id) 
        REFERENCES matches(match_id) 
        ON DELETE CASCADE;

-- НЕ добавляем FK для match_processing, так как он создается до matches

-- ============================================
-- 2. Индексы для производительности
-- ============================================

-- Индексы для matches
CREATE INDEX IF NOT EXISTS idx_matches_start_time ON matches(start_time DESC);
CREATE INDEX IF NOT EXISTS idx_matches_created_at ON matches(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_matches_radiant_win ON matches(radiant_win);

-- Индексы для match_players
CREATE INDEX IF NOT EXISTS idx_match_players_player_id ON match_players(player_id);
CREATE INDEX IF NOT EXISTS idx_match_players_created_at ON match_players(created_at DESC);

-- Индексы для match_processing
CREATE INDEX IF NOT EXISTS idx_match_processing_status ON match_processing(status);
CREATE INDEX IF NOT EXISTS idx_match_processing_parsed_at ON match_processing(parsed_at DESC);
CREATE INDEX IF NOT EXISTS idx_match_processing_created_at ON match_processing(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_match_processing_status_created ON match_processing(status, created_at);
CREATE INDEX IF NOT EXISTS idx_match_processing_failed ON match_processing(status, created_at) WHERE status = 'failed';

COMMIT;

SELECT 'Migration completed successfully!' as status;
