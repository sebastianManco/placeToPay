# MercaTodo - Plataforma de Comercio Electrónico

Plataforma integral de comercio electrónico de **MercaTodo** desarrollada en Laravel 11, equipada con pasarela de pagos PlaceToPay (Webcheckout), administración de inventario con importación/exportación masiva en Excel/CSV, módulo analítico de reportes y sistema de control de acceso basado en roles y permisos (ACL).

Para consultar la especificación completa, contratos de datos y buenas prácticas de Postman, revisa:
👉 **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**

---

## 🚀 Guía Rápida para Servicios Externos (API REST v1)

Esta API permite a clientes externos (aplicaciones móviles, microservicios, plataformas de inventario o frontends desacoplados) consultar y administrar recursos de forma predecible y estandarizada.

### 1. URL Base
```
http://localhost:8000/api/v1
```
*(O el dominio/puerto configurado para el entorno de producción o staging)*.

### 2. Cabeceras Obligatorias (Headers)
Todo servicio externo debe enviar:
- **`Accept: application/json`** *(Obligatorio en todas las peticiones para garantizar respuestas en JSON)*.
- **`Content-Type: application/json`** *(Obligatorio en peticiones con cuerpo: POST, PUT, PATCH)*.
- **`Content-Type: multipart/form-data`** *(Solo al cargar imágenes o archivos)*.

### 3. Estructura Uniforme de Respuestas (JSON Envelope)
Todas las respuestas siguen el estándar:
```json
{
  "success": true,
  "message": "Mensaje descriptivo del resultado",
  "data": {},
  "meta": {}
}
```

En caso de error de validación (`422 Unprocessable Entity`):
```json
{
  "success": false,
  "message": "Los datos proporcionados no son válidos.",
  "errors": {
    "name": ["El campo nombre es obligatorio."]
  }
}
```

### 4. Endpoints Principales

#### 📦 Módulo de Categorías
| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/v1/categories` | Lista paginada de categorías activas (filtro opcional: `search`). |
| `GET` | `/api/v1/categories/{id}` | Detalle de categoría con conteo de productos asociados. |
| `POST` | `/api/v1/categories` | Crear nueva categoría (`name`, `description`, `is_active`). |
| `PUT/PATCH` | `/api/v1/categories/{id}` | Actualizar categoría existente. |
| `DELETE` | `/api/v1/categories/{id}` | Eliminar categoría (valida que no tenga productos asociados). |
| `GET` | `/api/v1/categories/{id}/products` | Lista de productos asociados a la categoría. |

#### 🛒 Módulo de Productos
| Método | Endpoint | Descripción |
|---|---|---|
| `GET` | `/api/v1/products` | Catálogo paginado con filtros (`search`, `category`, `in_stock`, `sort_by`, `per_page`, `page`). |
| `GET` | `/api/v1/products/{id}` | Detalle de un producto con stock e información de su categoría. |
| `POST` | `/api/v1/products` | Registrar nuevo producto (`name`, `category_id`, `price`, `stock`, `description`, `image`). |
| `PUT/PATCH` | `/api/v1/products/{id}` | Actualización completa o parcial de producto. |
| `PATCH` | `/api/v1/products/{id}/stock` | Ajuste directo de existencias en inventario (`stock`). |
| `DELETE` | `/api/v1/products/{id}` | Eliminación de producto. |

#### 📊 Módulo de Reportes Asíncronos
| Método | Endpoint | Descripción |
|---|---|---|
| `POST` | `/api/v1/reports` | Encola la generación de un reporte (`202 Accepted`). Retorna el `id` para seguimiento. |
| `GET` | `/api/v1/reports/{id}/status` | Consulta el estado del reporte (`pending`, `processing`, `completed`, `failed`). |
| `GET` | `/api/v1/reports/{id}` | Consulta datos resumidos del reporte generado. |
| `GET` | `/api/v1/reports/{id}/download` | Descarga directa del archivo binario generado (PDF o Excel). |

### 5. Códigos de Estado HTTP
- **`200 OK`**: Petición procesada exitosamente.
- **`201 Created`**: Recurso creado exitosamente.
- **`202 Accepted`**: Petición encolada para procesamiento asíncrono (Reportes).
- **`204 No Content`**: Recurso eliminado correctamente (sin contenido en la respuesta).
- **`400 Bad Request`**: Parámetros de consulta incorrectos o acción no permitida.
- **`404 Not Found`**: Recurso solicitado no existe.
- **`422 Unprocessable Entity`**: Error de validación en campos del payload.
- **`500 Internal Server Error`**: Error no controlado en servidor.

---

## 🛠️ Requisitos Técnicos del Entorno

- **PHP**: ^8.2 (extensiones `pdo_mysql`, `bcmath`, `zip`, `gd`)
- **Framework**: Laravel 11.x
- **Gestor de Paquetes**: Composer 2.x
- **Base de Datos**: MySQL 8.x / MariaDB
- **Servidor Web / Contenedores**: Docker & Docker Compose
- **Cola de Procesos**: Worker de Laravel (`php artisan queue:work`) para reportes en segundo plano

---

## 🔍 Análisis Estático de Código (PHPStan / Larastan)

El proyecto cuenta con **PHPStan** configurado a través de **Larastan** (Nivel 5) para garantizar la calidad del código, tipado estricto y prevención de errores en tiempo de ejecución.

### Ejecución del análisis:

- **Desde Windows (script directo):**
  ```powershell
  .\phpstan.cmd
  # o pasando opciones adicionales
  .\phpstan.cmd analyse --error-format=table
  ```

- **A través de Composer / Docker:**
  ```powershell
  docker compose exec app composer phpstan
  # o directamente
  docker compose exec app php vendor/bin/phpstan analyse
  ```

- **Generar o actualizar línea base (Baseline):**
  ```powershell
  docker compose exec app php vendor/bin/phpstan analyse --generate-baseline
  ```
