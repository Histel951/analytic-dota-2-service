-- Add fantasy scores for new performance metrics
-- Tormentor Score: Fantasy points for killing Tormentors
-- Smoke Score: Fantasy points for using Smoke of Deceit
-- Watcher Score: Fantasy points for taking over observer wards

ALTER TABLE player_match_fantasy_stats
ADD COLUMN IF NOT EXISTS tormentor_score Int32 DEFAULT 0,
ADD COLUMN IF NOT EXISTS smoke_score Int32 DEFAULT 0,
ADD COLUMN IF NOT EXISTS watcher_score Int32 DEFAULT 0;
