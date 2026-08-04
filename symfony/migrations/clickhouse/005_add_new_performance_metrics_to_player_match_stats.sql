-- Add new performance metrics to player_match_stats table
-- Tormentor Kills: Number of Tormentors killed by the player
-- Smoke Uses: Number of Smoke of Deceit items used by the player
-- Observer Takeovers (Watchers): Number of observer wards taken over by the player

ALTER TABLE player_match_stats
ADD COLUMN IF NOT EXISTS tormentor_kills UInt8 DEFAULT 0,
ADD COLUMN IF NOT EXISTS smoke_uses UInt8 DEFAULT 0,
ADD COLUMN IF NOT EXISTS observer_takeovers UInt8 DEFAULT 0;
