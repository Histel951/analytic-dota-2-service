# Известные проблемы и технический долг

Состояние на 2026-10-31, ветка `feature/top-fantasy-analyze` (`3a64349`). Найдено чтением кода; тестов, которые подтверждали бы поведение, нет.

Отношение к рефакторингу (refactoring-plan.md). Рефакторинг меняет только архитектуру, поведение сохраняется, поэтому:

- **[баг]** — в ходе рефакторинга **не чинится** и переносится в новый код как есть. Исправляется отдельными задачами после рефакторинга.
- **[рефакторинг: шаг N]** — исчезает как следствие нового устройства на указанном шаге плана.
- **[после]** — улучшение или решение, отложенное до окончания рефакторинга.

## Корректность данных

1. **[баг] Фильтр `player_ids` отсекает почти все матчи.** Подзапрос «матчи, где есть игрок не из списка» идёт по всем 10 игрокам матча, включая соперников (fantasy.md → «Фильтр player_ids»). Исправление: ограничить подзапрос стороной/командой игрока (`opendota_team_id` строки или `side`).
2. **[баг] Дубли в ClickHouse.** Повторная обработка матча (`match:retry-failed`, повторный `ParseMatchMessage` для незавершённого матча, повтор после падения на поздней стадии) снова вставляет строки в `player_match_stats` и `player_match_fantasy_stats`. `ReplacingMergeTree` схлопывает их только при слиянии, а запросы идут без `FINAL`, поэтому `avg`, `count(*)`, `min_matches` и `matches_count` до слияния завышены или искажены. Варианты: `FINAL` / `argMax` в чтении, `OPTIMIZE … FINAL` после ретраев, удаление строк матча перед вставкой. **Решение 2026-10-31: в рефакторинге не трогаем ни запись, ни чтение** (refactoring-plan.md, решение 8). Новый конвейер (шаг 3) пишет тем же простым `INSERT`, поэтому поведение с дублями сохраняется и при сверке с эталоном должно совпадать.
3. **[баг] Связки core_side/support не ограничены «по игроку на роль».** `team_combos` суммирует всех eligible-игроков команды в ролях группы. Команда со сменой состава или игрок, сыгравший и роль 1, и роль 3, дают 3+ игроков в связке (игрок дублируется по ролям), и `combined_score` завышен. `HAVING players_in_combo >= 1` пропускает и одиночек.
4. **[баг] `team_count` / `player_count` считаются без ролей и без `matches_limit`** (`getEligible*Ids` параметр `$matchesLimit` игнорируют), поэтому расходятся с данными групп.
5. **[баг] `lane` = `role`.** `AnalyticMatchAnalyticsRepository` пишет в `lane` значение роли, настоящей линии в ClickHouse нет.
6. **[рефакторинг: шаг 3] Матч без строк статистики застревает в `processing_fantasy`.** `ProcessFantasyScoreHandler` делает `return` без смены статуса, и `match:retry-failed` такой матч не подхватит. В новом конвейере статусы `pending → imported → completed | failed`; поведение для пустой статистики нужно сохранить явно (не `completed`).
7. **[рефакторинг: шаг 3] `fetched` выставляется до запроса к API** (`FetchMatchFromApiHandler`). Статусы `fetching` и `processing_analytics` не используются.
8. **[рефакторинг: шаг 6] `PsqlMatchRepository::findByTeamIdAndDateRange` игнорирует `$teamId`** и возвращает все матчи периода. Метод не вызывается, удаляется.
9. **[баг] Форма команд:** `team_name` всегда `"Team {id}"`; регион при чтении из CH собирается как `new Region(0, name, strtolower(name))` (id и код теряются); на каждый матч — отдельный `SELECT` в `matches` (N+1); `team_matches` без обработанного `matches` в анализ не попадает (INNER JOIN), поэтому `team:analyze-form` сразу после `team:fetch-matches` даёт меньше матчей или `InsufficientMatchesException`.
10. **[баг] `team:fetch-matches --limit`** берёт первые N матчей в порядке ответа OpenDota после фильтра по дате; порядок кодом не гарантируется.

## Зависимость от dota-api

11. **[закрыто: шаг 1] Привязка к 1.x бандла.** Бандл обновлён до 2.0 (`dev-main` → `73f9a54`), классы бандла — только в `src/Infrastructure/OpenDota/` (адаптеры `MatchSource`/`TeamMatchesSource`).
12. **[закрыто: шаг 1] `RegionResolverInterface` в `services.yaml`** — удалён.
13. **[частично: шаг 1, до конца — шаг 3] Сериализованные объекты в сообщениях `async`.** `MatchDetail` бандла из сообщений ушёл, теперь в них `serialize(ImportedMatch)` (модель приложения). Смена этого класса при непустой `messenger_messages` по-прежнему сломает `unserialize`. На шаге 3 в сообщениях останется только `match_id`.
14. **[изменено: шаг 1] Пропуск матчей команды при `TypeError` маппера 1.x убран.** В 2.0 история матчей команды маппится целиком при запросе: ошибка данных источника даёт `TeamMatchesSourceException` на всю команду (`team:fetch-matches` → FAILURE для этой команды), а не пропуск одного матча. Поле `skipped_count` в логе удалено.

## Обработка ошибок и API

15. **[баг] `ExceptionSubscriber::handleUnexpectedException`** пробрасывает исключение (код после `throw` недостижим), поэтому неожиданные ошибки отдаются не JSON-ом, а стандартной страницей Symfony. Подписчик глобальный. Исключения `Application/Team/Exception` и VO (`Invalid*Exception`) в 400 не маппятся.
16. **[баг] `date_from`/`date_to` в HTTP** принимают только `Y-m-d H:i:s` (`Assert\DateTime`), а CLI принимает и `Y-m-d`.
17. **[баг] `fantasy:top-metrics`** не валидирует `GetTopFantasyMetricsQuery` (в отличие от `fantasy:export-top-metrics`) и печатает только топ-метрики, без связок.
18. **[после] Ретраи `async` не работают по задумке.** Хэндлеры ловят `Throwable` сами, поэтому `retry_strategy` (3 × 1с ×2) не срабатывает. Kafka-транспорт парсинга — `max_retries: 0`. В рефакторинге поведение сохраняется (ловим ошибку → `failed`).
19. **[после] Топик `dota2.matches.failed.v1`** только пишется, консьюмера нет.
20. **[после] `/api/fantasy/top-metrics` слишком большой.** Один ответ содержит топ-метрики групп, все связки, все метрики каждого игрока и все его матчи. Формат не зафиксирован потребителями, его можно перепроектировать. Направление — разбить на несколько небольших эндпоинтов: топ-метрики групп ролей, связки группы (с пагинацией), детализация игрока (метрики, матчи). `fantasy:export-top-metrics` при этом — сборка из них. В рефакторинге ответ не меняется: на шаге 4 доменная модель анализа строится так, чтобы эти части отдавались независимо.

## Код и инфраструктура

21. **[рефакторинг: шаг 4]** `ClickHouseFantasyMetricAnalysisRepository` (~1000 строк) содержит доменную логику (квоты, вклад, сборку связок), дублирует построение фильтров и CTE в 5 методах и оставляет отладочный `error_log` (последнее — шаг 6).
22. **[рефакторинг: шаг 6]** Не используются: `KafkaTopicsConfig` (топики `analytics`, `players.aggregate`, `fantasy.calculate` в `messenger.yaml` не заведены), `MatchProducer`, `SuccessResponse`, `TeamFormAnalysisRepository::findAll/findFavorites/findUnderdogs`, `TeamMatchRepository::exists`, `MatchEntity::isLongMatch`, `TeamForm::getNumericValue`, `insertMatchStats`, `saveMatchPlayer`, Redis.
23. **[рефакторинг: шаг 5]** `ClickHouseTeamFormAnalysisRepository` создаёт новый клиент ClickHouse на каждый вызов (через фабрику), а не берёт сервис `ClickHouseDB\Client`. `ClickHouseFactory` читает `$_ENV` напрямую.
24. **[рефакторинг: шаг 6 — частично]** Миграции применяются вручную, учёта нет; psql 001/003 и clickhouse 001/004 не идемпотентны. На шаге 6 миграции сводятся в базовые схемы (БД пустые). Инструмент учёта миграций — [после].
25. **[после]** `docker/supervisor/` не подключён, воркеры запускаются отдельными сервисами compose без supervisord. `symfony/compose.yaml` (Flex) и `symfony/.env` (`MESSENGER_TRANSPORT_DSN=rdkafka://…`, `DATABASE_URL` на `app`) — мёртвые дефолты. Volume `kafka_data` объявлен, но не смонтирован.
26. **[после] Healthcheck Kafka в `docker-compose.yml` всегда падает:** `kafka-broker-api-versions.sh: not found`, в образе `apache/kafka` скрипт лежит в `/opt/kafka/bin` (не в `PATH`). Kafka при этом работает, но `dota_kafka` — `unhealthy`, и `docker compose up` не стартует `worker_kafka` (`depends_on: service_healthy`). Обход: `docker compose up -d --no-deps worker_kafka`. Исправление — полный путь `/opt/kafka/bin/kafka-broker-api-versions.sh` в `healthcheck.test`.
27. **[после]** Тестов нет совсем, PHPUnit не установлен. Тесты пишутся на финальное состояние после рефакторинга.
