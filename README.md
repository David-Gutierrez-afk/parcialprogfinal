# Sistema Web de Gestión de Incapacidades Médicas — Corporate Solutions

Plataforma web distribuida bajo arquitectura de microservicios para la gestión de empleados, incapacidades médicas, autenticación de usuarios y seguimiento administrativo.

## Arquitectura del Proyecto

```text
PAPF/
├── back/
│   ├── ms-auth/             # Puerto 8001 -> BD: db_auth
│   ├── ms-empleados/        # Puerto 8002 -> BD: db_empleados
│   ├── ms-incapacidades/    # Puerto 8003 -> BD: db_incapacidades
│   └── ms-seguimiento/      # Puerto 8004 -> BD: db_seguimiento
├── front/                   # Puerto 8000 -> HTML5 + CSS3 puro + JS Vanilla
├── sql/                     # Scripts de creación y datos semilla de las 4 BDs
└── README.md
```

### Tecnologías
- **Frontend:** HTML5, CSS3 puro (archivos `.css` propios, sin etiquetas `<style>` ni frameworks), JavaScript Vanilla (`fetch`).
- **Backend:** PHP 8+, Slim Framework 4, Eloquent ORM (`illuminate/database`), Composer.
- **Base de Datos:** MySQL (4 bases de datos independientes).

---

## Guía de Instalación en una Máquina Nueva (desde cero en Windows)

Si necesitas instalar y correr el proyecto en un computador que no tiene nada instalado, sigue estos pasos en orden:

### Paso 1: Instalar las herramientas base
1. **Git:** Descargar e instalar desde [git-scm.com](https://git-scm.com/downloads) (dejar las opciones por defecto).
2. **XAMPP (PHP 8.2+ y MySQL):** Descargar e instalar desde [apachefriends.org](https://www.apachefriends.org/) en `C:\xampp`.
3. **Agregar PHP y MySQL al PATH de Windows (recomendado):**
   - Abrir *Variables de entorno del sistema* -> editar `Path` -> agregar:
     - `C:\xampp\php`
     - `C:\xampp\mysql\bin`
   - Cerrar y abrir una terminal nueva y comprobar con `php -v`.
4. **Composer:** Descargar el instalador `Composer-Setup.exe` desde [getcomposer.org](https://getcomposer.org/download/). Durante la instalación, seleccionar `C:\xampp\php\php.exe`. Comprobar con `composer -V`.

### Paso 2: Clonar el repositorio
```powershell
git clone <URL_DEL_REPOSITORIO>
cd PAPF
```

### Paso 3: Crear las 4 bases de datos en MySQL
1. Abrir **XAMPP Control Panel** e iniciar el módulo **MySQL** (y Apache si vas a usar phpMyAdmin).
2. **Opción rápida por consola:**
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root < sql/init_all.sql
   ```
3. **Opción gráfica (phpMyAdmin):**
   - Abrir `http://localhost/phpmyadmin`
   - Ir a la pestaña **Importar**
   - Seleccionar el archivo `sql/init_all.sql` (o importar uno por uno `01_db_auth.sql` hasta `04_db_seguimiento.sql`) y hacer clic en **Importar**.

### Paso 4: Instalar dependencias de Composer en cada microservicio
> Nota: Por reglas del proyecto, ni `vendor/` ni `composer.lock` se suben al repositorio. En la máquina nueva se regeneran con `composer install`:

```powershell
cd back/ms-auth
composer install
copy .env.example .env

cd ../ms-empleados
composer install
copy .env.example .env

cd ../ms-incapacidades
composer install
copy .env.example .env

cd ../ms-seguimiento
composer install
copy .env.example .env
```

*(Si tu MySQL tiene contraseña distinta a vacía, edita `DB_PASSWORD` y `AUTH_DB_PASSWORD` en cada archivo `.env`.)*

### Paso 5: Levantar los microservicios y el frontend
Abrir una terminal por servicio (o usar el script de arranque del proyecto cuando esté disponible):

```powershell
# Terminal 1 - ms-auth
php -S 127.0.0.1:8001 -t back/ms-auth/public

# Terminal 2 - ms-empleados
php -S 127.0.0.1:8002 -t back/ms-empleados/public

# Terminal 3 - ms-incapacidades
php -S 127.0.0.1:8003 -t back/ms-incapacidades/public

# Terminal 4 - ms-seguimiento
php -S 127.0.0.1:8004 -t back/ms-seguimiento/public

# Terminal 5 - Frontend
php -S 127.0.0.1:8000 -t front
```

Luego abre en el navegador: `http://127.0.0.1:8000`

### Credenciales de prueba (datos semilla)
- **Administrador:** usuario `admin` (o `admin@empresa.com`) / contraseña `admin123`
- **Gestión Humana:** usuario `gestionhumana` (o `gh@empresa.com`) / contraseña `gh123`
