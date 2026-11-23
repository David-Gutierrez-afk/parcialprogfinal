<?php

declare(strict_types=1);

use App\Controllers\EmpleadosController;
use App\Helpers\JsonResponder;
use App\Helpers\SessionTokenValidator;
use App\Middlewares\AuthMiddleware;
use App\Presentation\Repositories\EmpleadoRepository;
use App\Validators\EmpleadoValidator;
use Slim\App;

return function (App $app): void {
    $empleadosController = new EmpleadosController();
    $empleadoValidator = new EmpleadoValidator();
    $jsonResponder = new JsonResponder();
    $tokenValidator = new SessionTokenValidator();

    $empleadoRepository = new EmpleadoRepository(
        $empleadosController,
        $empleadoValidator,
        $jsonResponder
    );

    $authMiddleware = new AuthMiddleware(
        $tokenValidator,
        $jsonResponder
    );

    // Ruta pública de estado
    $app->get('/health', [$empleadoRepository, 'health']);

    // Rutas protegidas por token y sesión activa
    $app->get('/empleados', [$empleadoRepository, 'index'])->add($authMiddleware);
    $app->get('/empleados/{id}', [$empleadoRepository, 'show'])->add($authMiddleware);
    $app->post('/empleados', [$empleadoRepository, 'store'])->add($authMiddleware);
    $app->put('/empleados/{id}', [$empleadoRepository, 'update'])->add($authMiddleware);
    $app->patch('/empleados/{id}/estado', [$empleadoRepository, 'changeStatus'])->add($authMiddleware);
};
