<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Helpers\JsonResponder;
use App\Helpers\TokenGenerator;
use App\Middlewares\AuthMiddleware;
use App\Presentation\Repositories\AuthRepository;
use App\Validators\LoginValidator;
use Slim\App;

return function (App $app): void {
    $tokenGenerator = new TokenGenerator(32);
    $authController = new AuthController($tokenGenerator);
    $loginValidator = new LoginValidator();
    $jsonResponder = new JsonResponder();

    $authRepository = new AuthRepository(
        $authController,
        $loginValidator,
        $jsonResponder
    );

    $authMiddleware = new AuthMiddleware(
        $authController,
        $jsonResponder
    );

    // Rutas públicas
    $app->get('/health', [$authRepository, 'health']);
    $app->post('/auth/login', [$authRepository, 'login']);

    // Rutas protegidas por sesión activa y token
    $app->post('/auth/logout', [$authRepository, 'logout'])->add($authMiddleware);
    $app->get('/auth/validate', [$authRepository, 'validate'])->add($authMiddleware);
    $app->get('/auth/me', [$authRepository, 'validate'])->add($authMiddleware);
};
