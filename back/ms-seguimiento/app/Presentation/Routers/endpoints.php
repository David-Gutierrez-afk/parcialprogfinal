<?php

declare(strict_types=1);

use App\Controllers\SeguimientosController;
use App\Helpers\IncapacidadGateway;
use App\Helpers\JsonResponder;
use App\Helpers\SessionTokenValidator;
use App\Helpers\UsuarioLookup;
use App\Middlewares\AuthMiddleware;
use App\Presentation\Repositories\SeguimientoRepository;
use App\Validators\SeguimientoValidator;
use Slim\App;

return function (App $app): void {
    $incapacidadGateway = new IncapacidadGateway();
    $usuarioLookup = new UsuarioLookup();
    $seguimientosController = new SeguimientosController(
        $incapacidadGateway,
        $usuarioLookup
    );
    $seguimientoValidator = new SeguimientoValidator();
    $jsonResponder = new JsonResponder();
    $tokenValidator = new SessionTokenValidator();

    $seguimientoRepository = new SeguimientoRepository(
        $seguimientosController,
        $seguimientoValidator,
        $jsonResponder
    );

    $authMiddleware = new AuthMiddleware(
        $tokenValidator,
        $jsonResponder
    );

    // Ruta pública de estado
    $app->get('/health', [$seguimientoRepository, 'health']);

    // Rutas protegidas por token y sesión activa
    $app->get('/seguimientos', [$seguimientoRepository, 'index'])->add($authMiddleware);
    $app->get('/seguimientos/incapacidad/{incapacidad_id}', [$seguimientoRepository, 'historyByIncapacidad'])->add($authMiddleware);
    $app->get('/seguimientos/{id}', [$seguimientoRepository, 'show'])->add($authMiddleware);
    $app->post('/seguimientos', [$seguimientoRepository, 'store'])->add($authMiddleware);
    $app->patch('/seguimientos/incapacidad/{incapacidad_id}/estado', [$seguimientoRepository, 'updateStatus'])->add($authMiddleware);
};
