# MercaTodo - Plataforma de Comercio Electrónico

Plataforma integral de comercio electrónico de **MercaTodo** desarrollada en Laravel 11, equipada con pasarela de pagos PlaceToPay (Webcheckout), administración de inventario con importación/exportación masiva en Excel/CSV, módulo analítico de reportes y sistema de control de acceso basado en roles y permisos (ACL) con autenticación Bearer Token mediante **Laravel Sanctum**.

Para consultar la especificación completa, contratos de datos, flujo de autenticación y directrices de Postman, revisa:
👉 **[API_DOCUMENTATION.md](API_DOCUMENTATION.md)**

---

## 🚀 Guía Rápida para Servicios Externos (API REST v1)

Esta API permite a clientes externos (aplicaciones móviles, microservicios, plataformas de inventario o frontends desacoplados) consultar y administrar recursos de forma segura, predecible y estandarizada.

### 1. URL Base
```
http://localhost:8000/api/v1
```
*(O el dominio/puerto configurado para el entorno de producción o staging)*.

### 2. Cabeceras Obligatorias (Headers)
Todo servicio externo debe enviar:
- **`Accept: application/json`** *(Obligatorio en todas las peticiones para garantizar respuestas en formato JSON)*.
- **`Content-Type: application/json`** *(Obligatorio en peticiones con cuerpo: POST, PUT, PATCH)*.
- **`Content-Type: multipart/form-data`** *(Solo al cargar imágenes o archivos Excel/CSV)*.
- **`Authorization: Bearer <token>`** *(Obligatorio para acceder a endpoints protegidos y administrativos)*.

---

### 3. Autenticación y Tokens de Acceso (Laravel Sanctum)

Los endpoints de consulta pública (catálogo de productos y categorías) están abiertos sin token. Todas las operaciones administrativas (creación, edición, eliminación, exportación/importación y módulo de reportes y métricas) requieren un Bearer Token emitido con los roles o permisos ACL correspondientes.

#### Obtención del Token de Acceso
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@placetopay.com",
    "password": "tu_password_seguro",
    "device_name": "mi-app-o-script"
  }'
```

**Respuesta Exitosa (HTTP 200 OK):**
```json
{
  "success": true,
  "message": "Autenticación exitosa.",
  "data": {
    "token": "1|abcdef1234567890...",
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "identification": 10000001,
      "name": "Admin",
      "last_name": "PlaceToPay",
      "email": "admin@placetopay.com",
      "role": "admin",
      "roles": { "admin": "Administrador General" },
      "permissions": ["*"]
    }
  }
}
```

#### Envío del Token en Peticiones Protegidas
```bash
curl -X POST http://localhost:8000/api/v1/products \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer 1|abcdef1234567890..." \
  -d '{
    "name": "Nuevo Producto",
    "price": 50000,
    "stock": 10,
    "category_id": 1
  }'
```

#### Consulta de Perfil y Permisos (`GET /api/v1/auth/me`)
Retorna los datos del usuario autenticado, sus roles, habilidades del token actual y permisos consolidados.

#### Cierre de Sesión y Revocación de Token (`POST /api/v1/auth/logout`)
Invalida y elimina el token de acceso actual en el servidor.

---

### 4. Estructura Uniforme de Respuestas (JSON Envelope)
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
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "name": ["El nombre es obligatorio."]
  }
}
```

---

### 5. Resumen de Endpoints Principales

#### 🔐 Módulo de Autenticación (`/api/v1/auth`)
| Método | Endpoint | Acceso | Permiso ACL Requerido | Descripción |
|---|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Público | Ninguno | Inicia sesión y emite Bearer Token con habilidades según rol. |
| `POST` | `/api/v1/auth/logout` | Protegido | `auth:sanctum` | Revoca y elimina el token actual. |
| `GET` | `/api/v1/auth/me` | Protegido | `auth:sanctum` | Consulta el usuario autenticado, roles y matriz de permisos. |

#### 📦 Módulo de Categorías (`/api/v1/categories`)
| Método | Endpoint | Acceso | Permiso ACL Requerido | Descripción |
|---|---|---|---|---|
| `GET` | `/api/v1/categories` | Público | Ninguno | Lista paginada de categorías (filtros: `search`, `is_active`). |
| `GET` | `/api/v1/categories/{id}` | Público | Ninguno | Detalle de categoría con conteo de productos asociados. |
| `GET` | `/api/v1/categories/{id}/products` | Público | Ninguno | Listado de productos pertenecientes a la categoría. |
| `POST` | `/api/v1/categories` | Protegido | `categories.create` | Crear nueva categoría comercial. |
| `PUT/PATCH` | `/api/v1/categories/{id}` | Protegido | `categories.edit` | Actualización total o parcial de categoría existente. |
| `DELETE` | `/api/v1/categories/{id}` | Protegido | `categories.edit` | Eliminar categoría (valida que no contenga productos o `?force=true`). |

#### 🛒 Módulo de Productos (`/api/v1/products`)
| Método | Endpoint | Acceso | Permiso ACL Requerido | Descripción |
|---|---|---|---|---|
| `GET` | `/api/v1/products` | Público | Ninguno | Catálogo paginado (`search`, `category`, `in_stock`, `sort_by`, `min_price`, `max_price`). |
| `GET` | `/api/v1/products/{id}` | Público | Ninguno | Detalle de producto con existencias e información de su categoría. |
| `POST` | `/api/v1/products` | Protegido | `products.create` | Registrar nuevo producto en catálogo comercial. |
| `PUT/PATCH` | `/api/v1/products/{id}` | Protegido | `products.edit` | Actualización completa o parcial de producto. |
| `PATCH` | `/api/v1/products/{id}/stock` | Protegido | `products.edit` | Ajuste directo (`stock`) o relativo (`adjustment`) de inventario. |
| `DELETE` | `/api/v1/products/{id}` | Protegido | `products.edit` | Eliminación de producto del catálogo. |
| `GET` | `/api/v1/products/export` | Protegido | `products.export` | Exportar catálogo a archivo Excel (.xlsx) o CSV. |
| `POST` | `/api/v1/products/import` | Protegido | `products.import` | Importación masiva de productos desde hoja de cálculo. |

#### 📊 Módulo de Reportes y Métricas en Tiempo Real (`/api/v1/reports`)
| Método | Endpoint | Acceso | Permiso ACL Requerido | Descripción |
|---|---|---|---|---|
| `GET` | `/api/v1/reports/metrics/sales` | Protegido | `reports.view` | Métricas de ventas en tiempo real (totales, ticket promedio, ventas diarias). |
| `GET` | `/api/v1/reports/metrics/payments` | Protegido | `reports.view` | Métricas de estados de transacción en tiempo real. |
| `GET` | `/api/v1/reports/metrics/top-products` | Protegido | `reports.view` | Ranking de productos más vendidos. |
| `GET` | `/api/v1/reports/metrics/inventory-alerts`| Protegido | `reports.view` | Alertas de stock crítico, agotado y productos inactivos. |
| `GET` | `/api/v1/reports` | Protegido | `reports.view` | Historial paginado de reportes generados. |
| `POST` | `/api/v1/reports` | Protegido | `reports.create` | Encola generación asíncrona de reporte (`202 Accepted`). |
| `GET` | `/api/v1/reports/{id}` | Protegido | `reports.view` | Detalle y estado de ejecución del reporte. |
| `GET` | `/api/v1/reports/{id}/download` | Protegido | `reports.download` | Descarga de archivo binario generado (PDF o Excel). |
| `DELETE` | `/api/v1/reports/{id}` | Protegido | `reports.view` | Eliminar reporte y archivos almacenados en servidor. |

---

### 6. Códigos de Estado HTTP
- **`200 OK`**: Petición procesada exitosamente.
- **`201 Created`**: Recurso creado exitosamente (incluye cabecera `Location`).
- **`202 Accepted`**: Petición aceptada y encolada para procesamiento asíncrono (Reportes).
- **`204 No Content`**: Recurso eliminado correctamente.
- **`400 Bad Request`**: Parámetros de consulta incorrectos o conflicto de negocio.
- **`401 Unauthorized`**: Token de autenticación ausente, expirado o inválido.
- **`403 Forbidden`**: Cuenta de usuario inactiva o falta de permisos ACL suficientes.
- **`404 Not Found`**: Recurso solicitado no existe.
- **`409 Conflict`**: Conflicto de integridad (ej: eliminar categoría con productos asociados).
- **`422 Unprocessable Entity`**: Error de validación en campos del payload.
- **`429 Too Many Requests`**: Límite de tasa de peticiones excedido (Rate limiting).
- **`500 Internal Server Error`**: Error no controlado en el servidor.

---

## 🛠️ Requisitos Técnicos del Entorno

- **PHP**: ^8.2 (extensiones `pdo_mysql`, `bcmath`, `zip`, `gd`)
- **Framework**: Laravel 11.x
- **Autenticación API**: Laravel Sanctum
- **Gestor de Paquetes**: Composer 2.x
- **Base de Datos**: MySQL 8.x / MariaDB
- **Servidor Web / Contenedores**: Docker & Docker Compose
- **Cola de Procesos**: Worker de Laravel (`php artisan queue:work`) para reportes en segundo plano

---

## 🔍 Análisis Estático de Código (PHPStan / Larastan)

El proyecto cuenta con **PHPStan** configurado a través de **Larastan** (Nivel 5) para garantizar la calidad del código, tipado estricto y prevención de errores en tiempo de ejecución.

### Ejecución del análisis:
- **A través de Composer / Docker:**
  ```powershell
  docker compose exec app composer phpstan
  # o directamente
  docker compose exec app php vendor/bin/phpstan analyse
  ```
