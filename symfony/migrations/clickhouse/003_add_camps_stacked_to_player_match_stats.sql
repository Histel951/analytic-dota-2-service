-- Add camps_stacked column to player_match_stats table
-- This column stores the number of neutral camps stacked by the player

ALTER TABLE player_match_stats
ADD COLUMN IF NOT EXISTS camps_stacked UInt16 DEFAULT 0;
