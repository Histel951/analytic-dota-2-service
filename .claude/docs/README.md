# dota2-analytical-service — контекст проекта

Symfony-приложение для аналитики про-сцены Dota 2. Оно забирает матчи из OpenDota (через бандл `histel951/dota-2-api`), раскладывает статистику игроков в ClickHouse, считает fantasy-очки по правилам TI 2026 и отдаёт аналитику: топ игроков по метрике, форму команд, топ fantasy-метрик и связки игроков по группам ролей.

- Код приложения — `symfony/` (namespace `App\` → `symfony/src/`), инфраструктура — `docker-compose.yml` и `docker/` в корне
- PHP `>=8.2` (в образе 8.3), Symfony `^7.2`, Doctrine DBAL 4 (без ORM), `smi2/phpclickhouse`, Messenger + Kafka (`alvarorosado/event-driven-kafka-messenger-transport`, `ed+kafka://`)
- Хранилища: PostgreSQL 16 (матчи, игроки, статусы обработки, связи команда–матч, очередь `async`), ClickHouse (статистика игроков, fantasy-очки, форма команд), Kafka (входная очередь парсинга и топик ошибок)
- Архитектура: DDD-слои `Domain / Application / Infrastructure / UI`, модули `Match`, `Fantasy`, `PlayerStats`, `Team`. Принципы SOLID, KISS, DRY, YAGNI
- Тестов нет (`symfony/tests/` отсутствует, PHPUnit не подключён)
- Ветка `feature/top-fantasy-analyze` — в работе эндпоинт `/api/fantasy/top-metrics` (эмблемы, связки игроков, лимит матчей на команду, матчи игроков в ответе, экспорт в JSON)

## Статус модулей

| Модуль | Что делает | Вход |
|---|---|---|
| Match | конвейер «матч из OpenDota → ClickHouse + Postgres → fantasy-очки», статусы `match_processing`, повтор упавших | `POST /api/matches/parse`, `parse:kafka:match`, `match:retry-failed` |
| PlayerStats | топ игроков по средней метрике из `player_match_stats` | `GET /api/players/top` |
| Team | матчи команды из OpenDota → очередь парсинга + `team_matches`; форма команды (winrate → prime/high/medium/low) только по БД → ClickHouse | `team:fetch-matches`, `team:fetch-multiple-matches`, `team:analyze-form` |
| Fantasy | топ fantasy-метрик по группам ролей с квотами эмблем, связки игроков одной команды, их матчи | `GET /api/fantasy/top-metrics`, `fantasy:top-metrics`, `fantasy:export-top-metrics` |

## Зависимость от dota-api

`histel951/dota-2-api` подключён как `dev-main` из VCS. Его версия закреплена в `composer.lock` коммитом `d1bcaba` — это структура **1.x**: `Histel951\Dota2Api\Domain\Common\ValueObjects\*`, `Domain\Entities\Match\*`, `Client\Dota2ApiClient::matches()/teams()`, конфиг `dota2_api.base_url/api_key/timeout/source`. Классы библиотеки используются напрямую в домене приложения (VO в сущностях, `MatchDetail` в сообщениях). Бандл 2.0 ломает совместимость, поэтому `composer update` без миграции кода сломает сборку (known-issues.md).

## Документы

- [architecture.md](architecture.md) — слои и модули, раскладка `src/`, DI, Messenger и транспорты, конвейер обработки матча и статусы, инфраструктура Docker
- [database.md](database.md) — схемы PostgreSQL и ClickHouse, миграции и как их применять, кто что пишет и читает
- [api.md](api.md) — HTTP-эндпоинты и консольные команды: параметры, валидация, формат ответов, ошибки
- [fantasy.md](fantasy.md) — расчёт fantasy-очков, эмблемы и группы ролей, алгоритм top-metrics и связок, фильтры
- [conventions.md](conventions.md) — стиль кода, раскладка модуля, рецепты «новая метрика», «новый эндпоинт», «новая миграция»
- [known-issues.md](known-issues.md) — баги и технический долг с пометкой: чинится после рефакторинга или закрывается его шагом
- [refactoring-plan.md](refactoring-plan.md) — решения, целевая архитектура (контексты, правила зависимостей, владение данными, источники, конвейер), шаги и способ сверки поведения

## Статус архитектуры

Проект писался как минимальный MVP. Рефакторинг идёт по шагам: после шага 2 код разложен по контекстам Ingestion / Fantasy / Statistics + Shared, но логика внутри — ещё MVP. architecture.md описывает, **как есть**, а не образец для подражания: новый код не должен копировать отступления, перечисленные в architecture.md → «Правила зависимостей» и в known-issues.md. **Целевая архитектура и план рефакторинга — [refactoring-plan.md](refactoring-plan.md)** (контексты Ingestion / Fantasy / Statistics, бандл dota-api 2.0 только в `Infrastructure`, поведение сохраняется). Для нового и переписываемого кода он главнее текущей раскладки.

## Агенты (`.claude/agents/`)

- `architect` — проектирование и план рефакторинга: где живёт код, модель, порты, хранилища, сообщения (только чтение, opus)
- `domain-implementer` — `<Context>/Domain/*`, `<Context>/Application/*`, `Shared/Domain`: сущности, VO, enum, доменные сервисы, use case'ы, хэндлеры и сообщения
- `persistence-implementer` — `<Context>/Infrastructure/Persistence/{Postgres,ClickHouse}`: репозитории, SQL, миграции, корректность аналитических запросов
- `api-designer` — входы и интеграции: HTTP-контроллеры и ответы, консольные команды, `ExceptionSubscriber`, Messenger/Kafka, DI (`services.yaml`, `config/packages`), интеграция с бандлом dota-api
- `test-writer` — PHPUnit-тесты (в проекте их пока нет, инфраструктуру тестов ставит по решению оркестратора)
- `ddd-reviewer` — ревью границ слоёв и модулей и соглашений из `.claude/docs` (только чтение)

Агентов вызывает основная сессия (оркестратор). Каждый правит только свой срез, ничего не коммитит и возвращает отчёт в фиксированном формате.

## Прочее

- `.env` (корень) — переменные для контейнеров (`env_file` в docker-compose): `DATABASE_URL`, `CLICKHOUSE_*`, `KAFKA_*`. `symfony/.env` — дефолты Symfony; в контейнерах их перекрывают переменные окружения
- `symfony/compose.yaml`, `compose.override.yaml` — заготовка Symfony Flex (сервис `database`), не используется
- `docker/supervisor/` — образ с supervisord для двух консьюмеров `kafka_parse_matches`, в `docker-compose.yml` не подключён
- `symfony/var/exports/fantasy/` — куда `fantasy:export-top-metrics` по умолчанию пишет JSON
