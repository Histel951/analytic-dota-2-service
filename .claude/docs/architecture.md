# Архитектура

## Слои и модули (`symfony/src/`)

Код разложен по слоям, внутри слоя — по модулям (`Match`, `Fantasy`, `PlayerStats`, `Team`). Внутри модуля классы разложены по типу (`Dto/`, `UseCase/`, `Service/`, `Repository/` …).

```
Kernel.php
Domain/                        — сущности, VO, enum, интерфейсы репозиториев, доменные сервисы
  Match/
    Entity/                    — MatchEntity (matches), MatchPlayers (match_players), MatchProcessing (match_processing,
                                 переходы статуса markAs*), PlayerMatchStats (строка player_match_stats, ~40 полей на VO бандла)
    Enum/                      — MatchProcessingStatus
    Repository/                — MatchRepository, MatchPlayersRepository, MatchProcessingRepository,
                                 AnalyticMatchRepository (CH player_match_stats), FantasyStatsRepository (CH fantasy)
    Service/                   — FantasyScoreCalculator
    ValueObject/               — FantasyScoringConfig (коэффициенты из parameters)
    Dto/                       — FantasyScoreResult (очки по 16 метрикам + total)
  Fantasy/
    Enum/                      — RoleGroup (core_side [1,3], mid [2], support [4,5], квоты эмблем), EmblemType (core/utility/support → метрики)
    Service/                   — SelectTopMetricsByEmblemRules
    Entity/                    — RoleGroupMetricRecommendation, TopComboRecommendation, PlayerInCombo
    ValueObject/               — MetricContribution, PlayerMetrics, PlayerAnalyzedMatch, PlayerMatchStatistic
    Repository/                — FantasyMetricAnalysisRepository
  PlayerStats/                 — Entity/PlayerMetric, Repository/PlayerStatsReadRepository
  Team/                        — Entity/TeamMatch, TeamFormAnalysis; Enum/TeamForm; ValueObjects/Winrate;
                                 Repository/TeamMatchRepository, TeamFormAnalysisRepository
Application/                   — сценарии
  Match/
    UseCase/                   — ParseMatchUseCase (dispatch ParseMatchMessage), RetryFailedMatchesUseCase
    Handler/                   — #[AsMessageHandler]: FetchMatchFromApiHandler, ProcessMatchAnalyticsHandler,
                                 PersistMatchDataHandler, ProcessFantasyScoreHandler, MatchFailedHandler
    Message/                   — ParseMatchMessage, MatchFetchedMessage, MatchAnalyticsProcessedMessage,
                                 MatchFantasyCalculateMessage, MatchFailedMessage, MatchFailedKafkaMessage
    Extractor/                 — PlayerMatchExtractor (MatchDetail бандла → PlayerMatchStats[] обеих сторон)
    Dto/                       — ParseMatchRequest
  Fantasy/                     — UseCase/GetTopFantasyMetricsUseCase, Service/GetTopFantasyMetricsService (домен → DTO), Dto/*
  PlayerStats/                 — UseCase, Service (whitelist метрик, пагинация), Dto, Exception
  Team/                        — UseCase/FetchTeamMatchesUseCase, AnalyzeTeamsFormUseCase; Service/FetchTeamMatchesService,
                                 TeamFormAnalyzer; Dto; Exception
Infrastructure/
  Postgres/Repository/         — Psql*Repository (DBAL Connection, сырой SQL)
  ClickHouse/                  — ClickHouseFactory (из $_ENV CLICKHOUSE_*), Repository/ClickHouse*Repository,
                                 AnalyticMatchAnalyticsRepository
  Kafka/                       — Serializer/MatchFailedKafkaSerializer (только encode → JSON),
                                 Config/KafkaTopicsConfig, Producer/MatchProducer (не используются)
  Http/                        — EventSubscriber/ExceptionSubscriber, Response/* (JsonResponse-обёртки)
UI/
  Http/Controller/             — MatchController, PlayerStatsController, FantasyController (атрибутные роуты)
  Console/{Match,Team,Fantasy}/ — консольные команды
```

Цепочка вызова одинакова во всех модулях: **UI** (парсинг входа → DTO запроса с `#[Assert]` → `ValidatorInterface`) → **UseCase** (примитивы → VO бандла) → **Service** (бизнес-валидация, оркестрация) → **Repository**-интерфейс домена → реализация в Infrastructure. В Match вместо Service стоит цепочка Messenger-хэндлеров.

## Правила зависимостей

Базовые правила действуют для нового и переписываемого кода, пока не утверждена целевая архитектура (она их уточнит). Автоматической проверки нет: тестов нет, deptrac не подключён. Проверка — grep по `use` (ddd-reviewer).

- `Domain` → только `Domain` (свой модуль; другие модули — только через явно разрешённое общее ядро) и PHP. Не зависит от `Application`, `Infrastructure`, `UI`, Symfony, Doctrine, ClickHouse и формата OpenDota.
- `Application` → `Domain`, `Psr\Log`, `Symfony\Component\Messenger` (шина и атрибут хэндлера), `Symfony\Component\Validator\Constraints` (атрибуты DTO запроса). Не зависит от `Infrastructure` и `UI`.
- `Infrastructure` → `Domain`, `Application` (реализует интерфейсы домена, сериализует сообщения), внешние библиотеки.
- `UI` → `Application` (use case'ы, DTO запроса/результата), `Infrastructure\Http\Response`. В `Domain` и репозитории напрямую не ходит.
- Доменная логика (расчёты, правила отбора, квоты) живёт в `Domain`, а не в SQL-репозитории и не в контроллере. Репозиторий отдаёт данные, а не решения.
- Внешний источник (`histel951/dota-2-api`) — деталь инфраструктуры: его классы не должны протекать в новые сущности, сообщения и сигнатуры домена.

**Отступления текущего кода (MVP)** — не образец, не тиражировать, закрываются рефакторингом:

- `Domain` повсеместно зависит от VO бандла dota-api (`MatchId`, `PlayerId`, `TeamId`, `Region`, VO статистики, `Side`, `Role`), `PlayerMatchStats` построен на них
- `Application\Match\Message\*` несут `serialize(MatchDetail)` бандла, хэндлеры и `FetchTeamMatchesService` вызывают `Dota2ApiClient` напрямую, без порта
- `ClickHouseFantasyMetricAnalysisRepository` (Infrastructure) содержит логику анализа: вклад метрик, квоты эмблем через доменный `SelectTopMetricsByEmblemRules`, сборку связок, никнеймы через `MatchPlayersRepository`
- модули связаны напрямую: Fantasy ← `Domain\Match\Repository\MatchPlayersRepository`, Team ← `Domain\Match\Repository\MatchRepository`, Team ← `Application\Match\Message\ParseMatchMessage`
- `Application\Fantasy\Service` — маппер домен → DTO, а не сервис; UseCase'ы PlayerStats/Team — проксирующие обёртки

## DI (`symfony/config/services.yaml`)

- `App\` — автовайринг и автоконфигурация всего `src/` (кроме `Kernel.php`)
- Интерфейсы ClickHouse-репозиториев алиасятся явно на реализации. Postgres-репозитории (`MatchRepository`, `MatchPlayersRepository`, `MatchProcessingRepository`) резолвятся автовайрингом по единственной реализации, `TeamMatchRepository` — явным алиасом
- `ClickHouseDB\Client` — через фабрику `ClickHouseFactory::create`. `ClickHouseTeamFormAnalysisRepository` получает саму фабрику и создаёт клиент на каждый вызов
- `FantasyScoringConfig` — 18 аргументов из `parameters.fantasy_scoring.*` (fantasy.md)
- `Histel951\Dota2Api\Client\Dota2ApiClient` — `autowire: true`, `lazy: false`; конфиг бандла `config/packages/dota2_api.yaml` (OpenDota, `api_key: null`, `timeout: 30`, `source: opendota`)
- `RegionResolverInterface` → `RegionResolver` — класса в установленной версии бандла нет; определение удаляется как неиспользуемое, `lint:container` проходит
- `ExportTopFantasyMetricsCommand::$projectDir` = `%kernel.project_dir%`; `KafkaTopicsConfig` — имена топиков строками

## Messenger и транспорты (`config/packages/messenger.yaml`)

| Транспорт | DSN | Сообщения | Ретраи |
|---|---|---|---|
| `kafka_parse_matches` | `ed+kafka://kafka:9092`, топик `dota2.matches.parse.v1`, group `matches-parser`, `earliest` | `ParseMatchMessage` | `max_retries: 0` |
| `async` | `doctrine://default?auto_setup=true` (таблица `messenger_messages` в Postgres) | `MatchFetchedMessage`, `MatchAnalyticsProcessedMessage`, `MatchFantasyCalculateMessage`, `MatchFailedMessage` | 3, задержка 1с ×2 |
| `kafka_failed_matches` | `ed+kafka://kafka:9092`, топик `dota2.matches.failed.v1`, `acks: all`, сериализатор `MatchFailedKafkaSerializer` | `MatchFailedKafkaMessage` | — (только продюсер, консьюмера нет) |

Общие настройки консьюмера Kafka — `config/packages/event_drive_kafka_transport.yaml` (`commit_async`, `enable.auto.commit: false`, `consume_timeout_ms: 500`).

Сообщения между хэндлерами несут `matchId` и `serializedMatchDetail` — `serialize()` объекта `MatchDetail` бандла (геттер `getMatchDetail()` делает `unserialize()`). Хэндлеры ловят `Throwable` сами и не пробрасывают исключение, поэтому ретраи `async` на практике не срабатывают. Ошибка оформляется явно: статус `failed` + `MatchFailedMessage`.

## Конвейер обработки матча

```
POST /api/matches/parse | parse:kafka:match | team:fetch-matches | match:retry-failed
  → ParseMatchMessage(matchId)                         → kafka_parse_matches
FetchMatchFromApiHandler
  match_processing: нет строки → INSERT pending; status=completed → пропуск (любой другой статус, в т.ч. failed, обрабатывается заново)
  markAsFetched (status=fetched, fetched_at) — ДО запроса к API
  Dota2ApiClient::matches()->getMatch(MatchId) → MatchDetail (null → RuntimeException)
  → MatchFetchedMessage(serialize(MatchDetail))        → async
ProcessMatchAnalyticsHandler
  PlayerMatchExtractor::extract → 10 × PlayerMatchStats → INSERT в CH player_match_stats
  markAnalyticsProcessed (analytics_completed)
  → MatchAnalyticsProcessedMessage                     → async
PersistMatchDataHandler
  UPSERT matches (start_time, duration, radiant_win, region = id региона бандла)
  INSERT match_players … ON CONFLICT DO NOTHING (player_pro_name)
  → MatchFantasyCalculateMessage                       → async
ProcessFantasyScoreHandler
  processing_fantasy → SELECT player_match_stats WHERE match_id (CH) → FantasyScoreCalculator × 10
  → INSERT в CH player_match_fantasy_stats → fantasy_completed → completed
  (нет строк статистики → warning и return, статус остаётся processing_fantasy)
Ошибка в любом хэндлере → status=failed → MatchFailedMessage(errorType) → async
MatchFailedHandler → MatchFailedKafkaMessage{match_id, error_type, error_message, timestamp} → kafka_failed_matches (JSON)
```

`errorType`: `invalid_match_id`, `fetch_error`, `analytics_error`, `persist_error`, `fantasy_calculation_error`.

Статусы `MatchProcessingStatus`: `pending`, `fetched`, `analytics_completed`, `processing_fantasy`, `fantasy_completed`, `completed`, `failed`. Значения `fetching` и `processing_analytics` объявлены, но не выставляются.

`match:retry-failed` берёт все строки `status = 'failed'` и шлёт по `ParseMatchMessage` на каждую: матч проходит весь конвейер заново, включая повторный INSERT в ClickHouse (known-issues.md → дубли).

## Поток Team

```
team:fetch-matches <id> [--limit] [--date-from] [--date-to]
  → FetchTeamMatchesService: Dota2ApiClient::teams()->getMatchesByTeam(TeamId) (OpenDota /teams/{id}/matches)
    фильтр по дате → до limit матчей: dispatch ParseMatchMessage + TeamMatch(team, match, isWin)
    TypeError маппера бандла → матч пропускается с warning
  → PsqlTeamMatchRepository::saveBulk (UPSERT team_matches в транзакции)

team:analyze-form "<ids>" [--date-from] [--date-to]     (OpenDota не вызывается)
  → TeamFormAnalyzer: team_matches INNER JOIN matches (только обработанные матчи), < 5 → InsufficientMatchesException
    winrate = wins/total ×100 → TeamForm (≥70 prime, ≥60 high, ≥40 medium, иначе low)
    регион — самый частый matches.region (по запросу на матч), last_match_time — первый по start_time DESC
    team_name = "Team {id}" (имя не запрашивается)
  → INSERT в CH team_form_analysis
```

Чтение `team_form_analysis` (`findByTeamId`, `findAll`, `findFavorites`, `findUnderdogs`) реализовано в репозитории, но ни одним эндпоинтом или командой не используется.

## Инфраструктура (`docker-compose.yml`)

| Сервис | Контейнер | Порт хоста | Назначение |
|---|---|---|---|
| `app` | `dota_app` | — | php-fpm 8.3 (`docker/php/Dockerfile`: librdkafka 2.10 из исходников, `rdkafka`, `pdo_pgsql`, `pcntl`), `./symfony` → `/var/www` |
| `nginx` | `dota_nginx` | 8080 | `public/index.php` → `app:9000` |
| `postgres` | `dota_postgres` | 5432 | БД `dota`, пользователь/пароль `dota/dota` |
| `clickhouse` | `dota_clickhouse` | 8123, 9000 | пользователь `default` без пароля (`docker/clickhouse/users.xml`) |
| `kafka` | `dota_kafka` | 9092 | Apache Kafka 4.1.1 KRaft, один брокер |
| `kafka-ui` | `kafka-ui` | 8081 | Provectus Kafka UI |
| `worker_kafka` | `dota_worker_kafka` | — | `messenger:consume kafka_parse_matches --time-limit=3600` |
| `worker_async` | `dota_worker_async` | — | `messenger:consume async --time-limit=3600` |

Redis поднимается и прописан в `.env` (`REDIS_URL`), но приложением не используется (кеш — файловый по умолчанию). Консоль: `docker exec -it dota_app php bin/console …`. Таблицы БД создаются вручную (database.md).
