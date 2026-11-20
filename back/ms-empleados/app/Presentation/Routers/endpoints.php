<?php

declare(strict_types=1);

use App\Controllers\EmpleadosController;
use App\Helpers\JsonResponder;
use App\Presentation\Repositories\EmpleadoRepository;
use App\Validators\EmpleadoValidator;
use Slim\App;

return function (App $app): void {
    $empleadosController = new EmpleadosController();
    $empleadoValidator = new EmpleadoValidator();
    $jsonResponder = new JsonResponder();

    $empleadoRepository = new EmpleadoRepository(
        $empleadosController,
        $empleadoValidator,
        $jsonResponder
    );

    $app->get('/health', [$empleadoRepository, 'health']);
    $app->get('/empleados', [$empleadoRepository, 'index']);
    $app->get('/empleados/{id}', [$empleadoRepository, 'show']);
    $app->post('/empleados', [$empleadoRepository, 'store']);
};
