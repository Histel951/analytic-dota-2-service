# План рефакторинга архитектуры

Статус: **согласован 2026-10-31. Шаг 1 выполнен** (ветка `refactoring/step-1-dota-api-2`, эталон — `.claude/baseline/step1`), следующий — шаг 2. После завершения рефакторинга этот документ становится описанием целевой архитектуры, а `architecture.md` переписывается под фактическое состояние.

## Решения

| # | Решение |
|---|---|
| 1 | Первым делом — переход на `histel951/dota-2-api` 2.0. Источник (OpenDota / STRATZ) выбирается по сценарию и по данным, которые нужны функционалу, а не глобально. Основная схема источников определится позже, по опыту |
| 2 | Бандл dota-api используется **только в `Infrastructure`** (адаптеры источников). Домен, сообщения и сигнатуры — на собственной модели приложения |
| 3 | Bounded contexts: **Ingestion / Fantasy / Statistics** + общее ядро `Shared` |
| 4 | Только архитектура, **поведение сохраняется**. Баги не чиним, они ведутся в `known-issues.md` |
| 5 | HTTP-ответы по ходу рефакторинга не меняем. Перепроектирование `/api/fantasy/top-metrics` (разбиение на несколько эндпоинтов) отложено, см. `known-issues.md` |
| 6 | Тестов и новых dev-зависимостей по ходу рефакторинга не добавляем. Тесты пишутся на финальное состояние |
| 7 | БД PostgreSQL и ClickHouse пустые (volumes удалены): схему можно менять без миграции данных |
| 8 | Запись в ClickHouse остаётся как есть — простой `INSERT` без удаления старых строк и без `FINAL` при чтении. Повторный импорт матча по-прежнему добавляет дубли (known-issues.md §2) |
| 9 | Общий HTTP-слой — `src/Shared/UI/Http` |
| 10 | Контекст статистики называется `Statistics` |

Неизбежные изменения поведения, которые приходят вместе с 2.0 (не баги рефакторинга):

- **Роли OpenDota** — только из `position_est`, иначе `UNKNOWN`. В 1.x был резолвер ролей бандла, поэтому распределение ролей в `player_match_stats` может отличаться.
- **Конфиг `dota2_api`** — новый формат (`opendota.*`, `stratz.*`, `timeout`, `max_retries`). Корневые `base_url`/`api_key`/`source` в 2.0 не собираются.
- **PHP `>=8.3`** (требование бандла 2.0). Образ Docker уже 8.3, в `composer.json` поднимается минимум.

## Целевая архитектура

### Раскладка: контекст → слой

```
src/
  Shared/
    Domain/            — свои VO, одинаковые для всех контекстов: MatchId, PlayerId, TeamId, Role (0..5),
                         Side, RegionId (+ имя региона), исключения VO
    Event/             — интеграционные события между контекстами: MatchImported(matchId)
    UI/Http/           — общий HTTP-слой: ExceptionSubscriber, базовые JSON-ответы (ErrorResponse, …),
                         разбор общих параметров запроса (списки id, даты)
  Ingestion/           — загрузка данных из внешних источников
    Domain/
      Match/           — ImportedMatch (агрегат: id, startTime, duration, radiantWin, region, players),
                         PlayerPerformance (все метрики игрока в матче, скаляры/свои VO)
      Processing/      — MatchProcessing + ProcessingStatus (жизненный цикл импорта)
      Team/            — TeamMatchRef (матч команды: matchId, startTime, isWin)
      Port/            — MatchSource, TeamMatchesSource (источники); MatchRepository, MatchPlayerRepository,
                         PlayerPerformanceRepository, MatchProcessingRepository, TeamMatchRepository
    Application/       — ImportMatch (сообщение) + ImportMatchHandler, ImportTeamMatches, RetryFailedImports
    Infrastructure/
      Source/OpenDota/ — OpenDotaMatchSource, OpenDotaTeamMatchesSource, маппер MatchDetail 2.0 → ImportedMatch
      Source/Stratz/   — появится, когда сценарию понадобится STRATZ
      Persistence/     — Postgres (matches, match_players, match_processing, team_matches),
                         ClickHouse (player_match_stats — запись)
    UI/                — POST /api/matches/parse, parse:kafka:match, match:retry-failed,
                         team:fetch-matches, team:fetch-multiple-matches
  Fantasy/             — fantasy-очки и их анализ
    Domain/
      Scoring/         — FantasyScoreCalculator, ScoringConfig, FantasyScore
      Analysis/        — RoleGroup, EmblemType, квоты, выбор топ-метрик, вклад метрик, сборка связок
      Port/            — MatchPerformanceReader (вход расчёта), FantasyScoreRepository,
                         FantasyAggregatesReader (агрегаты для анализа), PlayerNameReader
    Application/       — CalculateFantasyScores (хэндлер MatchImported), GetTopFantasyMetrics
    Infrastructure/    — ClickHouse (player_match_fantasy_stats, чтение player_match_stats), Postgres (имена игроков)
    UI/                — GET /api/fantasy/top-metrics, fantasy:top-metrics, fantasy:export-top-metrics
  Statistics/          — статистика и read-модели
    Domain/
      PlayerStats/     — топ игроков по метрике (whitelist метрик, пагинация)
      TeamForm/        — TeamForm, Winrate, TeamFormAnalysis, правило формы (порог матчей, границы winrate)
      Port/            — PlayerMetricReader, TeamMatchHistoryReader, TeamFormRepository
    Application/       — GetTopPlayersByMetric, AnalyzeTeamsForm
    Infrastructure/    — ClickHouse (player_match_stats, team_form_analysis), Postgres (team_matches + matches)
    UI/                — GET /api/players/top, team:analyze-form
  Kernel.php
```

Раскладка «контекст → слой» повторяет устройство бандла dota-api («источник = bounded context, слои внутри»): всё, что относится к контексту, лежит рядом, и границы видны по путям.

### Правила зависимостей

- `Shared\Domain` и `Shared\Event` ни от чего не зависят. `Shared\UI\Http` → Symfony HttpKernel/HttpFoundation/Validator и `Shared\Domain`; контексты в него не импортируются.
- `<Context>\Domain` → свой `Domain`, `Shared\Domain`. Без Symfony, Doctrine, ClickHouse, бандла dota-api.
- `<Context>\Application` → свой `Domain`, `Shared`, `Psr\Log`, Messenger (шина, атрибут хэндлера), атрибуты Validator.
- `<Context>\Infrastructure` → свой `Domain`/`Application`, `Shared`, внешние библиотеки. **Бандл dota-api — только в `Ingestion\Infrastructure\Source\*`.**
- `<Context>\UI` → свой `Application`, `Shared\UI\Http`.
- **Контексты не импортируют классы друг друга.** Связь — только через `Shared` (VO, события) и через таблицы, которые контекст-владелец публикует как read-модель (ниже).

Исключения контекстов, которые должны давать 400/404, `ExceptionSubscriber` распознаёт по маркер-интерфейсам из `Shared\Domain` (например, `InvalidInput`, `NotFound`), а не по конкретным классам контекстов. Маппинг при этом остаётся прежним: 400 только для валидации, `UnsupportedMetricException` и `InvalidPaginationException` (known-issues.md §15).

### Владение данными

| Таблица | Владелец (пишет) | Читают через свой порт |
|---|---|---|
| PG `matches`, `match_players`, `match_processing`, `team_matches` | Ingestion | Statistics (`team_matches` + `matches` для формы), Fantasy (`match_players` — ники) |
| CH `player_match_stats` | Ingestion | Fantasy (вход расчёта и анализ), Statistics (топ игроков) |
| CH `player_match_fantasy_stats` | Fantasy | Fantasy |
| CH `team_form_analysis` | Statistics | Statistics |

Чтение чужой таблицы — порт в домене читающего контекста и адаптер в его `Infrastructure`. Схему таблицы при этом меняет только владелец.

### Источники данных

- Порты Ingestion описывают **что нужно приложению**, а не API источника: `MatchSource::fetch(MatchId): ImportedMatch`, `TeamMatchesSource::listByTeam(TeamId): list<TeamMatchRef>`. Ошибки — свои исключения Ingestion (`MatchNotFound`, `SourceUnavailable`, `SourceDataInvalid`), адаптер переводит в них `SourceRequestException`/`SourceDataException` бандла.
- Адаптер выбирается **по сценарию** в DI: какой реализацией связан порт. Сейчас оба порта связаны с OpenDota — только OpenDota даёт все метрики fantasy-расчёта и полную историю матчей команды. STRATZ-адаптер добавляется, когда сценарию нужны его данные (YAGNI).
- Ограничения STRATZ для будущих решений: нет roshan/tower/tormentor kills, watchers, first blood, teamfight participation, total gold/xp (полный fantasy-расчёт по нему невозможен); `team.matches` — последние 100; списка команд нет; нераспарсенные свежие матчи; лимит 150/мин и обязательный токен.
- Модель `PlayerPerformance` на шаге 1 — с обязательными полями (единственный источник — OpenDota). Отсутствующие у источника метрики (nullable) появятся вместе со вторым адаптером, которому они нужны.

### Конвейер импорта матча

```
POST /api/matches/parse | parse:kafka:match | team:fetch-matches | match:retry-failed
  → ImportMatch(matchId)                       → kafka_parse_matches (топик dota2.matches.parse.v1)
ImportMatchHandler (Ingestion)
  status: pending → MatchSource::fetch → запись matches, match_players, player_match_stats → imported
  → MatchImported(matchId)                     → async
CalculateFantasyScores (Fantasy)
  MatchPerformanceReader → FantasyScoreCalculator → player_match_fantasy_stats
  → сообщает Ingestion результат: статус completed | failed (через событие/команду в Shared\Event)
Ошибка → failed + MatchImportFailed{match_id, error_type, error_message, timestamp} → dota2.matches.failed.v1
```

- В сообщениях — только `match_id` и скаляры. `serialize()` объектов бандла уходит.
- Сохраняется внешнее поведение: те же входы, топики `dota2.matches.parse.v1` и `dota2.matches.failed.v1` (формат JSON ошибки — тот же), `match:retry-failed` перезапускает `failed`, уже `completed` матч пропускается.
- Внутренние статусы упрощаются (`pending`, `imported`, `completed`, `failed`). `match_processing` — внутренняя таблица, наружу статусы не отдаются.
- Запись в ClickHouse — как сейчас, простой `INSERT`: повторный импорт (`match:retry-failed`, повторное сообщение для незавершённого матча) снова добавляет строки (решение 8).
- Хэндлеры, как и сейчас, ловят ошибки и ставят `failed`. Включение ретраев Messenger — отдельное изменение поведения, вне рефакторинга (known-issues.md).

## Шаги

После каждого шага: `php -l` на изменённых файлах, `php bin/console lint:container`, `lint:yaml config`, `debug:router`, `list`, `debug:messenger` — приложение собирается, роуты и команды на месте. Каждый шаг — отдельная ветка или PR.

**Сверка поведения без тестов.** После шага 1 поднять Docker, применить миграции, загрузить фиксированный набор (например, `team:fetch-multiple-matches "<5 команд>" -l 30`) и снять эталон: `fantasy:export-top-metrics` с несколькими наборами фильтров, `GET /api/players/top` по 2–3 метрикам, `team:analyze-form` + `SELECT` из `team_form_analysis`. На шагах 3–5 те же матчи загружаются заново (`match_id` из эталона), выводы сравниваются `diff`. Эталон снимается **после** шага 1, потому что 2.0 меняет роли.

1. **dota-api 2.0 + изоляция бандла.**
   - `composer update histel951/dota-2-api --with-dependencies` (только этот пакет), PHP `>=8.3` в `composer.json`, новый `config/packages/dota2_api.yaml`, `OPENDOTA_API_KEY`/`STRATZ_TOKEN` в `.env` (пустые). Из `services.yaml` удаляются переопределение `Dota2ApiClient` и `RegionResolverInterface`.
   - `Shared\Domain` (id, Role, Side, регион) и модель `ImportedMatch`/`PlayerPerformance`. Пока в текущих папках, переезд — шаг 2.
   - Порты `MatchSource`/`TeamMatchesSource` + OpenDota-адаптеры на API 2.0 (`openDota()->matches()`, `openDota()->teams()`).
   - Все использования классов бандла вне адаптеров (сейчас 38 файлов) заменяются на свою модель. `PlayerMatchExtractor` превращается в маппер адаптера. Сообщения конвейера пока те же, но без `MatchDetail`: несут `ImportedMatch` (данные приложения) — до переделки конвейера на шаге 3.
   - Проверка: импорт пары матчей вживую, строки в PG и CH заполнены. Затем снимается эталон.
2. **Раскладка по контекстам** — перенос файлов в `Ingestion/Fantasy/Statistics/Shared` (общий HTTP-слой из `Infrastructure/Http` → `Shared/UI/Http`) и смена namespace **без изменения логики**. Обновляются `services.yaml` (ресурсы, алиасы), `routes.yaml` (атрибутные роуты из `src/*/UI/Http/`), маршруты `messenger.yaml` (FQCN сообщений), `preload`. Связи между контекстами, которые пока нарушают правило, перечисляются в `architecture.md` и закрываются шагами 3–5.
3. **Конвейер Ingestion** — `ImportMatch` + `ImportMatchHandler`, событие `MatchImported`, статусы, `match_id` в сообщениях, запись PG + CH в одном хэндлере (CH — тем же `INSERT`, без дедупликации). Fantasy-расчёт подписывается на событие (код расчёта переезжает в `Fantasy/Domain/Scoring`). Импорт матчей команды — через `TeamMatchesSource`. Сверка с эталоном.
4. **Fantasy-анализ** — логика из `ClickHouseFantasyMetricAnalysisRepository` (вклад метрик, квоты, сборка связок, подбор матчей игрока) переезжает в `Fantasy/Domain/Analysis`. Репозиторий отдаёт агрегаты по (`player`, `team`, `role`) и матчи игроков; фильтры и CTE строятся одним построителем. **Те же SQL-результаты и тот же JSON**, включая известные баги (`player_ids`, связки без «одного игрока на роль», счётчики без ролей). Сверка с эталоном.
5. **Statistics** — топ игроков и форма команд на своих портах чтения; `TeamFormAnalyzer` и правило формы — в домен, `team_name`/регион — как сейчас. Сверка с эталоном.
6. **Зачистка** — удаление мёртвого кода (`MatchProducer`, `KafkaTopicsConfig`, `SuccessResponse`, неиспользуемые методы репозиториев), сведение миграций в базовые схемы по владельцам (БД пустые), `error_log` → логгер, `.claude/docs` переписываются под итог (`architecture.md`, `database.md`, `conventions.md`, агенты).

После рефакторинга, отдельно: тесты на финальное состояние (PHPUnit), перенос скилла `develop-feature`, затем исправление багов и перепроектирование API из `known-issues.md`.
