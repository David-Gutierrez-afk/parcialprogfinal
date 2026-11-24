<?php

declare(strict_types=1);

use App\Controllers\IncapacidadesController;
use App\Helpers\DiasIncapacidadCalculator;
use App\Helpers\EmpleadoLookup;
use App\Helpers\JsonResponder;
use App\Helpers\SessionTokenValidator;
use App\Middlewares\AuthMiddleware;
use App\Presentation\Repositories\IncapacidadRepository;
use App\Validators\IncapacidadValidator;
use Slim\App;

return function (App $app): void {
    $empleadoLookup = new EmpleadoLookup();
    $diasCalculator = new DiasIncapacidadCalculator();
    $incapacidadesController = new IncapacidadesController(
        $empleadoLookup,
        $diasCalculator
    );
    $incapacidadValidator = new IncapacidadValidator();
    $jsonResponder = new JsonResponder();
    $tokenValidator = new SessionTokenValidator();

    $incapacidadRepository = new IncapacidadRepository(
        $incapacidadesController,
        $incapacidadValidator,
        $jsonResponder
    );

    $authMiddleware = new AuthMiddleware(
        $tokenValidator,
        $jsonResponder
    );

    // Ruta pública de estado
    $app->get('/health', [$incapacidadRepository, 'health']);

    // Rutas protegidas por token y sesión activa
    $app->get('/incapacidades', [$incapacidadRepository, 'index'])->add($authMiddleware);
    $app->get('/incapacidades/{id}', [$incapacidadRepository, 'show'])->add($authMiddleware);
    $app->post('/incapacidades', [$incapacidadRepository, 'store'])->add($authMiddleware);
    $app->put('/incapacidades/{id}', [$incapacidadRepository, 'update'])->add($authMiddleware);
    $app->patch('/incapacidades/{id}/finalizar', [$incapacidadRepository, 'finalize'])->add($authMiddleware);
    $app->patch('/incapacidades/{id}/estado', [$incapacidadRepository, 'changeStatus'])->add($authMiddleware);
};
