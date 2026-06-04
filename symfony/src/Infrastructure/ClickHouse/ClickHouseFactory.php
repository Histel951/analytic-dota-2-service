<?php
declare(strict_types=1);

namespace App\Infrastructure\ClickHouse;

use ClickHouseDB\Client;

final class ClickHouseFactory
{
    public static function create(): Client
    {
        $config = [
            'host' => $_ENV['CLICKHOUSE_HOST'],
            'port' => $_ENV['CLICKHOUSE_PORT'],
            'username' => $_ENV['CLICKHOUSE_USERNAME'],
            'password' => $_ENV['CLICKHOUSE_PASSWORD'],
        ];

        $client = new Client($config);

        $client->database($_ENV['CLICKHOUSE_DATABASE']);

        return $client;
    }
}