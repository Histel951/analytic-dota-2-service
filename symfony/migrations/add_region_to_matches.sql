-- Добавление поля region в таблицу matches для кеширования информации о регионе
ALTER TABLE matches ADD COLUMN region INTEGER NULL;

-- Создание индекса для быстрого поиска
CREATE INDEX idx_matches_cluster ON matches(region);
