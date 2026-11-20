<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseConfig
{
    public static function boot(string $basePath): void
    {
        if (class_exists(Dotenv::class) && file_exists($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }

        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => self::env('DB_HOST', '127.0.0.1'),
            'port'      => self::env('DB_PORT', '3306'),
            'database'  => self::env('DB_DATABASE', 'db_auth'),
            'username'  => self::env('DB_USERNAME', 'root'),
            'password'  => self::env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    private static function env(string $key, string $default): string
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }

        return $default;
    }
}
