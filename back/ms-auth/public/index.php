<?php

declare(strict_types=1);

use App\Config\DatabaseConfig;
use App\Middlewares\CorsMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

DatabaseConfig::boot(dirname(__DIR__));

$app = AppFactory::create();

$app->addRoutingMiddleware();

$registerRoutes = require __DIR__ . '/../app/Presentation/Routers/endpoints.php';
$registerRoutes($app);

// CorsMiddleware al final para envolver incluso respuestas de error y OPTIONS
$app->add(new CorsMiddleware());

$app->run();
