<?php

declare(strict_types=1);

namespace App\Validators;

use App\Contracts\AuthValidatorInterface;
use App\Exceptions\ValidationException;

class LoginValidator implements AuthValidatorInterface
{
    public function validateLogin(array $data): array
    {
        $rawIdentificador = $data['identificador']
            ?? $data['usuario']
            ?? $data['correo']
            ?? $data['username']
            ?? '';

        $rawContrasena = $data['contrasena']
            ?? $data['password']
            ?? '';

        $identificador = is_string($rawIdentificador) ? trim($rawIdentificador) : '';
        $contrasena = is_string($rawContrasena) ? $rawContrasena : '';

        $errors = [];

        if ($identificador === '') {
            $errors['identificador'] = 'El usuario o correo es obligatorio.';
        }

        if (trim($contrasena) === '') {
            $errors['contrasena'] = 'La contraseña es obligatoria.';
        }

        if ($errors !== []) {
            throw new ValidationException('Datos de inicio de sesión incompletos.', $errors);
        }

        return [
            'identificador' => $identificador,
            'contrasena'    => $contrasena,
        ];
    }
}
