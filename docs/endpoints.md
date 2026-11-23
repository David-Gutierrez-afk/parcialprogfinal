# Documentación de Endpoints — Sistema Web de Gestión de Incapacidades Médicas

Este documento registra todos los endpoints REST expuestos por los microservicios del sistema, junto con sus parámetros, respuestas JSON, códigos de estado HTTP y la **justificación técnica de por qué se diseñó y utilizó cada ruta y método HTTP**.

---

## Convenciones Generales REST

### Métodos HTTP utilizados y su justificación
- **`GET`**: Operaciones de solo lectura (consultar listados, filtros o un recurso por ID). No modifican el estado del servidor y son idempotentes.
- **`POST`**: Creación de recursos nuevos (`201 Created`) o ejecución de acciones con efectos secundarios sobre el estado de sesión (`login` / `logout`), enviando datos sensibles en el cuerpo JSON y nunca en la URL.
- **`PUT`**: Actualización de los datos editables de un recurso existente.
- **`PATCH`**: Modificación parcial y puntual de un atributo específico del recurso (por ejemplo, cambiar únicamente el `estado` de un empleado o finalizar una incapacidad).

### Códigos de estado HTTP
| Código | Significado | Cuándo se utiliza |
|---|---|---|
| `200 OK` | Solicitud exitosa | Consultas, login, logout, actualizaciones y cambios de estado. |
| `201 Created` | Recurso creado | Registro exitoso de un nuevo empleado, incapacidad o seguimiento. |
| `401 Unauthorized` | No autenticado | Falta el token, el token no existe, la sesión está inactiva o credenciales inválidas. |
| `403 Forbidden` | Acceso denegado | El usuario existe pero su cuenta está en estado `inactivo`. |
| `404 Not Found` | Recurso no encontrado | El ID consultado o editado no existe en base de datos. |
| `409 Conflict` | Conflicto de negocio | Documento o correo duplicado, transición de estado redundante o solapamiento de fechas. |
| `422 Unprocessable Entity` | Error de validación | Campos obligatorios faltantes, correos o fechas inválidas, o rangos incoherentes. |
| `500 Internal Server Error` | Error interno | Fallos inesperados de servidor o conexión a base de datos. |

### Autenticación en rutas protegidas
Las rutas protegidas requieren enviar el token obtenido en el login dentro del encabezado HTTP:

```http
Authorization: Bearer <token>
```

*(También se admite `Authorization: <token>` o `X-Auth-Token: <token>`.)*

---

## 1. Microservicio `ms-auth`
- **URL Base:** `http://127.0.0.1:8001`
- **Base de Datos:** `db_auth` (tabla `usuarios`)
- **Responsabilidad:** Gestión de inicio de sesión, generación/validación de tokens simples, control de `sesion_activa` y cierre de sesión.

### `GET /health`
- **Protección:** Pública
- **Por qué se usa:** Permite comprobar que el microservicio está levantado e independiente sin depender de credenciales ni consultas complejas.
- **Respuesta (`200 OK`):**
```json
{
  "ok": true,
  "servicio": "ms-auth",
  "estado": "activo"
}
```

### `POST /auth/login`
- **Protección:** Pública
- **Por qué se usa:** Se emplea `POST` porque las credenciales deben viajar en el cuerpo JSON (nunca expuestas en la URL) y porque la operación modifica el estado del usuario en base de datos al generar un nuevo `token` y activar `sesion_activa = true`. Acepta tanto nombre de usuario como correo electrónico en el campo `identificador`.
- **Body JSON:**
```json
{
  "identificador": "admin",
  "contrasena": "admin123"
}
```
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "mensaje": "Inicio de sesión exitoso.",
  "token": "64_caracteres_hexadecimales...",
  "usuario": {
    "id": 1,
    "nombre": "Administrador General",
    "correo": "admin@empresa.com",
    "usuario": "admin",
    "rol": "administrador",
    "token": "64_caracteres_hexadecimales...",
    "sesion_activa": true,
    "estado": "activo",
    "created_at": "2026-11-18T19:45:00.000000Z",
    "updated_at": "2026-11-19T23:05:00.000000Z"
  }
}
```
- **Errores posibles:** `422` (campos vacíos), `401` (credenciales incorrectas), `403` (usuario inactivo).

### `POST /auth/logout`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** Se usa `POST` en lugar de `GET` porque realiza un cambio de estado en el servidor (`token = null`, `sesion_activa = false`). Requiere autenticación previa para garantizar que solo una sesión vigente pueda revocarse a sí misma e impedir accesos posteriores con el mismo token.
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "mensaje": "Sesión cerrada correctamente."
}
```
- **Errores posibles:** `401` (token faltante, inválido o sesión cerrada).

### `GET /auth/validate` (alias `GET /auth/me`)
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** Es una operación de solo lectura (`GET`) que permite al frontend validar al cargar cualquier vista si el token almacenado en `sessionStorage` sigue activo en `db_auth` y obtener la información del usuario autenticado (nombre y rol) sin modificar estado.
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "valida": true,
  "usuario": {
    "id": 1,
    "nombre": "Administrador General",
    "correo": "admin@empresa.com",
    "usuario": "admin",
    "rol": "administrador",
    "sesion_activa": true,
    "estado": "activo"
  }
}
```
- **Errores posibles:** `401` (token faltante o sesión inactiva).

---

## 2. Microservicio `ms-empleados`
- **URL Base:** `http://127.0.0.1:8002`
- **Base de Datos:** `db_empleados` (tabla `empleados`)
- **Responsabilidad:** Administración integral de empleados, filtros de búsqueda, validación de unicidad y control de estado activo/inactivo.

### `GET /health`
- **Protección:** Pública
- **Por qué se usa:** Permite verificar la disponibilidad del microservicio de empleados de forma independiente.
- **Respuesta (`200 OK`):**
```json
{
  "ok": true,
  "servicio": "ms-empleados",
  "estado": "activo"
}
```

### `GET /empleados`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Query Params opcionales:** `documento`, `area`, `estado` (`activo` | `inactivo`)
- **Ejemplo con filtros:** `GET /empleados?area=Tecnologia&estado=activo`
- **Por qué se usa:** Sigue el estándar REST de utilizar sustantivos en plural para consultar colecciones y *query parameters* combinables para filtrar por documento, área o estado sin multiplicar rutas innecesarias con verbos.
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "data": [
    {
      "id": 1,
      "nombres": "Carlos",
      "apellidos": "Ramirez",
      "documento": "1000123456",
      "correo": "carlos@empresa.com",
      "telefono": "3001234567",
      "cargo": "Analista",
      "area": "Tecnologia",
      "fecha_ingreso": "2024-01-15",
      "estado": "activo"
    }
  ],
  "total": 1,
  "filtros": {
    "area": "Tecnologia",
    "estado": "activo"
  }
}
```
- **Errores posibles:** `401` (no autenticado), `422` (filtro `estado` inválido).

### `GET /empleados/{id}`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** Permite recuperar un recurso puntual mediante su identificador único en la ruta, evitando cargar toda la tabla cuando el frontend necesita ver o precargar el formulario de edición de un empleado específico.
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "data": {
    "id": 1,
    "nombres": "Carlos",
    "apellidos": "Ramirez",
    "documento": "1000123456",
    "correo": "carlos@empresa.com",
    "telefono": "3001234567",
    "cargo": "Analista",
    "area": "Tecnologia",
    "fecha_ingreso": "2024-01-15",
    "estado": "activo"
  }
}
```
- **Errores posibles:** `401` (no autenticado), `422` (ID no numérico), `404` (empleado no encontrado).

### `POST /empleados`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** `POST` sobre la colección `/empleados` es la convención REST estándar para crear nuevos registros. Valida campos obligatorios, longitudes máximas, formato de documento, correo, teléfono y `fecha_ingreso` (formato `YYYY-MM-DD`, sin fechas futuras), y garantiza unicidad de documento y correo antes de persistir.
- **Body JSON:**
```json
{
  "nombres": "Andres",
  "apellidos": "Gomez",
  "documento": "1000789123",
  "correo": "andres@empresa.com",
  "telefono": "3105551234",
  "cargo": "Desarrollador",
  "area": "Tecnologia",
  "fecha_ingreso": "2025-02-01"
}
```
- **Respuesta exitosa (`201 Created`):**
```json
{
  "ok": true,
  "mensaje": "Empleado registrado correctamente.",
  "data": {
    "id": 3,
    "nombres": "Andres",
    "apellidos": "Gomez",
    "documento": "1000789123",
    "correo": "andres@empresa.com",
    "telefono": "3105551234",
    "cargo": "Desarrollador",
    "area": "Tecnologia",
    "fecha_ingreso": "2025-02-01",
    "estado": "activo"
  }
}
```
- **Errores posibles:** `401` (no autenticado), `422` (validaciones de formato/campos), `409` (documento o correo ya registrado).

### `PUT /empleados/{id}`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** Se usa `PUT` sobre el recurso `/empleados/{id}` para actualizar la información personal, cargo, área o estado de un empleado existente. Aplica las mismas reglas de negocio que el registro, validando que `documento` y `correo` no colisionen con otro empleado distinto (`id != {id}`).
- **Body JSON:**
```json
{
  "nombres": "Carlos Alberto",
  "apellidos": "Ramirez",
  "documento": "1000123456",
  "correo": "carlos@empresa.com",
  "telefono": "3009998877",
  "cargo": "Analista Senior",
  "area": "Tecnologia",
  "fecha_ingreso": "2024-01-15",
  "estado": "activo"
}
```
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "mensaje": "Empleado actualizado correctamente.",
  "data": {
    "id": 1,
    "nombres": "Carlos Alberto",
    "apellidos": "Ramirez",
    "documento": "1000123456",
    "correo": "carlos@empresa.com",
    "telefono": "3009998877",
    "cargo": "Analista Senior",
    "area": "Tecnologia",
    "fecha_ingreso": "2024-01-15",
    "estado": "activo"
  }
}
```
- **Errores posibles:** `401` (no autenticado), `404` (empleado no encontrado), `409` (documento o correo pertenece a otro empleado), `422` (datos inválidos).

### `PATCH /empleados/{id}/estado`
- **Protección:** Token obligatorio (`AuthMiddleware`)
- **Por qué se usa:** Se utiliza `PATCH` en un subrecurso dedicado (`/estado`) porque representa una actualización parcial y específica de negocio (activar o inactivar un empleado) sin necesidad de enviar el objeto completo. Además, evita transiciones redundantes respondiendo `409 Conflict` si el empleado ya posee el estado solicitado.
- **Body JSON:**
```json
{
  "estado": "inactivo"
}
```
- **Respuesta exitosa (`200 OK`):**
```json
{
  "ok": true,
  "mensaje": "Estado del empleado actualizado a inactivo.",
  "data": {
    "id": 1,
    "estado": "inactivo"
  }
}
```
- **Errores posibles:** `401` (no autenticado), `404` (empleado no encontrado), `409` (el empleado ya tiene ese estado), `422` (estado diferente de `activo` o `inactivo`).

---

*(Este documento se seguirá ampliando con los endpoints de `ms-incapacidades` y `ms-seguimiento` en los siguientes avances.)*
