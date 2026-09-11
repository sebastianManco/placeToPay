# MercaTodo - Plataforma de Comercio Electrónico

<p align="center">
  <a href="https://laravel.com" target="_blank">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="Laravel Logo">
  </a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Release-v1.1.0-blue.svg?style=for-the-badge&logo=github" alt="Release Stable">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20.svg?style=for-the-badge&logo=laravel" alt="Laravel Version">
  <img src="https://img.shields.io/badge/PHP-%5E8.2-777BB4.svg?style=for-the-badge&logo=php" alt="PHP Version">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED.svg?style=for-the-badge&logo=docker" alt="Docker">
  <img src="https://img.shields.io/badge/PlaceToPay-WebCheckout-00A499.svg?style=for-the-badge" alt="PlaceToPay">
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="License">
</p>

---

## 📌 Descripción General

**MercaTodo** es una solución e-commerce integral desarrollada con **Laravel 11**, diseñada para brindar una experiencia de compra fluida a los clientes y herramientas de gestión robustas a los administradores.

### Características Principales
- 💳 **Pasarela de Pagos Segura**: Integración completa con **PlaceToPay (WebCheckout)** para procesamiento y verificación de transacciones.
- 📦 **Gestión Masiva de Catálogo**: Importación y exportación de productos en formatos Excel (`.xlsx`) y `.csv` con streaming de alto rendimiento.
- 📊 **Módulo Analítico y Reportes**: Generación y exportación de métricas de ventas, estados de pago y alertas de inventario (PDF/Excel) mediante procesamiento en segundo plano (Queue Workers).
- 🛡️ **Control de Acceso (ACL)**: Sistema de roles y permisos granulares para clientes y administradores.
- 🔑 **API REST v1 Segura**: Endpoints estandarizados con **Laravel Sanctum** (Bearer Tokens) y formato JSON Envelope consistente.
- 🐳 **Contenerización y Multiplataforma**: Entornos aislados con Docker Compose para desarrollo local y despliegue autónomo en producción.

---

## 🏷️ Versión Estable Disponible

- **Versión Actual:** `v1.1.0` *(Estable)*
- **Registro de Cambios Clave:** Contenerización multi-stage autónoma con Nginx + PHP-FPM bajo Supervisord, orquestación multiplataforma, integración de workers en cola y suite de API REST v1.

---

## 📋 Requisitos Previos

Antes de instalar y levantar el proyecto, asegúrate de contar con alguna de las siguientes alternativas según el método de ejecución:

### Opción A: Ejecución con Docker (Recomendada)
- **Docker Engine**: `>= 24.0`
- **Docker Compose**: `>= 2.20`
- **Make** *(Opcional, para atajos rápidos en terminal)*

### Opción B: Ejecución en Entorno Local Nativo
- **PHP**: `^8.2` con extensiones requeridas:
  - `pdo_mysql`, `pdo_sqlite`, `bcmath`, `zip`, `gd`, `pcntl`, `posix`, `curl`, `mbstring`, `openssl`
- **Composer**: `>= 2.x`
- **Node.js**: `>= 18.x` y **NPM**
- **Base de Datos**: MySQL `8.x` o MariaDB `10.6+`

---

## 🚀 Guía de Configuración y Levantamiento

### 1. Clonar el Repositorio

```bash
git clone https://github.com/sebastianManco/placeToPay.git
cd placeToPay
```

---

### 2. Levantamiento con Docker (Entorno de Desarrollo)

El proyecto incluye configuración de Docker Compose con MariaDB y PHP-FPM preconfigurados.

1. **Copiar las variables de entorno:**
   ```bash
   cp .env.example .env
   ```

2. **Levantar los contenedores:**
   ```bash
   docker compose up -d
   # O si usas Make:
   make up
   ```

3. **Instalar dependencias y configurar la aplicación:**
   ```bash
   # Instalar dependencias PHP
   docker compose exec app composer install

   # Generar clave de aplicación
   docker compose exec app php artisan key:generate

   # Crear enlace simbólico de almacenamiento público
   docker compose exec app php artisan storage:link

   # Ejecutar migraciones y poblar datos iniciales
   docker compose exec app php artisan migrate --seed
   ```

4. **Compilar assets de frontend (Vite):**
   ```bash
   docker compose exec app npm install
   docker compose exec app npm run build
   ```

5. **Acceder a la aplicación:**
   - Aplicación Web: [http://localhost:8000](http://localhost:8000)
   - Conexión Externa a Base de Datos (Host): `127.0.0.1:33060`

---

### 3. Levantamiento Local Nativo (Sin Docker)

1. **Copiar y ajustar variables de entorno:**
   ```bash
   cp .env.example .env
   ```
   *Edita `.env` para apuntar a tu base de datos local (`DB_HOST=127.0.0.1`, `DB_PORT=3306`, etc.).*

2. **Instalar dependencias de PHP y Node:**
   ```bash
   composer install
   npm install
   npm run build
   ```

3. **Inicializar claves, enlace simbólico y base de datos:**
   ```bash
   php artisan key:generate
   php artisan storage:link
   php artisan migrate --seed
   ```

4. **Iniciar el servidor local y el worker de colas:**
   ```bash
   # Terminal 1: Servidor web
   php artisan serve

   # Terminal 2: Procesador de colas (necesario para reportes)
   php artisan queue:work
   ```

---

### 4. Despliegue en Producción (Multi-Stage Standalone)

El proyecto incluye un `Dockerfile.prod` y `docker-compose.prod.yml` que compilan Vite, optimizan Composer y orquestan Nginx, PHP-FPM y Queue Workers:

```bash
# Construir y levantar stack productivo
docker compose -f docker-compose.prod.yml up -d --build
# O usando Make:
make prod-up
```

---

## ⚙️ Configuración de Variables de Entorno (`.env`)

Asegúrate de definir correctamente los siguientes parámetros en tu archivo `.env`:

### Parámetros Generales
| Variable | Descripción | Valor por Defecto / Ejemplo |
|---|---|---|
| `APP_NAME` | Nombre de la aplicación | `MercaTodo` / `PlaceToPay` |
| `APP_ENV` | Entorno de ejecución | `local` / `production` |
| `APP_KEY` | Clave criptográfica única | Generada vía `php artisan key:generate` |
| `APP_URL` | URL base accesible | `http://localhost:8000` |
| `QUEUE_CONNECTION` | Driver para colas y jobs | `database` o `sync` |

### Pasarela de Pagos PlaceToPay (WebCheckout)
| Variable | Descripción | Valor por Defecto / Ejemplo |
|---|---|---|
| `PLACETOPAY_LOGIN` | Identificador de comercio | Proporcionado por PlaceToPay |
| `PLACETOPAY_TRAN_KEY` | Llave secreta transaccional | Proporcionada por PlaceToPay |
| `PLACETOPAY_URL` | Endpoint del servicio WebCheckout | `https://checkout-test.placetopay.com` |
| `PLACETOPAY_REST_TYPE` | Algoritmo de firma digital | `sha256` |
| `PLACETOPAY_TIMEOUT` | Tiempo de espera en segundos | `30` |

---

## 👤 Credenciales de Prueba (Seeders)

Al ejecutar `php artisan migrate --seed`, se configuran las siguientes cuentas iniciales:

| Rol | Correo Electrónico | Contraseña | Identificación |
|---|---|---|---|
| **Administrador** | `admin@placetopay.com` | `Admin123*` | `10000001` |
| **Cliente** | `cliente@placetopay.com` | `Cliente123*` | `10000002` |
| **Cliente Inactivo** | `inactivo@placetopay.com` | `Inactivo123*` | `10000003` |

---

## 🔌 API REST v1 y Servicios Externos

MercaTodo expone una API REST v1 desacoplada bajo el prefijo `/api/v1` con autenticación **Laravel Sanctum**.

Para la documentación completa de endpoints, esquemas de payload, códigos de error y colección de pruebas, consulta:
👉 **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**

### Cabeceras Globales
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer <tu_token_sanctum>
```

### Endpoints Principales
- **Autenticación**: `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
- **Categorías**: `GET|POST /api/v1/categories`, `GET|PUT|DELETE /api/v1/categories/{id}`
- **Productos**: `GET|POST /api/v1/products`, `GET|PUT|DELETE /api/v1/products/{id}`, `PATCH /api/v1/products/{id}/stock`
- **Importación y Exportación Masiva**: `GET /api/v1/products/export`, `POST /api/v1/products/import`
- **Métricas y Reportes**: `GET /api/v1/reports/metrics/*`, `POST /api/v1/reports`, `GET /api/v1/reports/{id}/download`

---

## 🛠️ Comandos de Utilidad (Makefile)

Si cuentas con `make` instalado en tu sistema:

| Comando | Descripción |
|---|---|
| `make up` | Levanta el entorno de desarrollo con Docker Compose. |
| `make down` | Detiene y remueve los contenedores de desarrollo. |
| `make restart` | Reinicia los contenedores. |
| `make build` | Reconstruye las imágenes de Docker. |
| `make logs` | Visualiza los logs en tiempo real de los contenedores. |
| `make prod-up` | Levanta el stack completo de producción. |
| `make prod-down` | Detiene el stack de producción. |

---

## 📄 Licencia

Este proyecto está bajo la licencia [MIT](LICENSE).

