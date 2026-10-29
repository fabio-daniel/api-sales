# API de Ventas – Laravel

API REST en Laravel 11 para gestionar un flujo básico de ventas: listar productos, registrar ventas con control de
stock y descuentos, consultarlas y eliminarlas.

## Demo en línea

| | URL |
|---|---|
| **Aplicación (front)** | https://demo.ackee-company.com |
| **API (base)** | https://api-demo.ackee-company.com/api |
| Ejemplo rápido | https://api-demo.ackee-company.com/api/products |

El front que consume esta API está en [fabio-daniel/app-sales](https://github.com/fabio-daniel/app-sales).

```bash
curl https://api-demo.ackee-company.com/api/products
```

## Contenido

- [Requisitos](#requisitos)
- [Instalación local](#instalación-local)
- [Endpoints](#endpoints)
- [Decisiones de diseño](#decisiones-de-diseño)
- [Estructura relevante](#estructura-relevante)
- [Limitaciones conocidas](#limitaciones-conocidas)
- [Enunciado original de la prueba](#prueba-técnica--api-rest-de-ventas-en-laravel)

## Requisitos

- PHP **8.2** o superior (con las extensiones habituales de Laravel: `mbstring`, `pdo_mysql`, `openssl`, `tokenizer`,
  `xml`, `ctype`, `fileinfo`)
- Composer 2
- MySQL / MariaDB

## Instalación local

```bash
git clone https://github.com/fabio-daniel/api-sales.git
cd api-sales

composer install
cp .env.example .env
php artisan key:generate
```

Crea una base de datos vacía y configura en el `.env` los datos de conexión (`DB_DATABASE`, `DB_USERNAME`,
`DB_PASSWORD`). Luego ejecuta las migraciones y los seeders:

```bash
php artisan migrate --seed
php artisan serve
```

La API queda en `http://127.0.0.1:8000/api`.

El seeder carga **10 productos** y **2 usuarios** de prueba. La venta usa el usuario estático `user_id = 1`, como pide
el enunciado.

> Para reiniciar la base con datos limpios: `php artisan migrate:fresh --seed` (borra todas las tablas).

## Endpoints

Todas las respuestas son JSON. Conviene enviar el encabezado `Accept: application/json`.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/products` | Lista los productos. |
| `POST` | `/api/sales` | Registra una venta. |
| `GET` | `/api/sales` | Lista las ventas con su detalle. |
| `DELETE` | `/api/sales/{id}` | Elimina una venta. |

### `GET /api/products`

```json
[
  {
    "id": 1,
    "name": "Laptop Lenovo ThinkPad",
    "price": 7500,
    "stock": 10000,
    "image": "https://images.unsplash.com/photo-1517336714731-489689fd1ca8"
  }
]
```

### `POST /api/sales`

```json
{
  "user_id": 1,
  "items": [
    { "product_id": 1, "quantity": 2, "discount_percent": 10 },
    { "product_id": 3, "quantity": 1 }
  ]
}
```

`discount_percent` es opcional (0 por defecto) y debe estar entre 0 y 10.

Respuesta `201 Created`:

```json
{
  "message": "Venta creada correctamente",
  "sale_id": 1,
  "total": 13950
}
```

Ejemplo del cálculo con ese request (laptop 7 500 × 2 con 10 %, teclado 450 × 1 sin descuento):

```text
laptop:  subtotal = 15 000   descuento = 1 500   subtotal con descuento = 13 500
teclado: subtotal =    450   descuento =     0   subtotal con descuento =    450
total = 13 950   total_discount = 1 500
```

### `GET /api/sales`

```json
[
  {
    "id": 1,
    "total": 13950,
    "total_discount": 1500,
    "created_at": "2026-10-29",
    "created_at_datetime": "2026-10-29T14:41:40+00:00",
    "user": { "id": 1, "name": "Test User", "email": "admin@test.com" },
    "units": 3,
    "details": [
      {
        "product": "Laptop Lenovo ThinkPad",
        "image": "https://images.unsplash.com/photo-1517336714731-489689fd1ca8",
        "quantity": 2,
        "price": 7500,
        "subtotal": 15000,
        "discount_percent": 10,
        "discount_amount": 1500,
        "subtotal_with_discount": 13500
      },
      {
        "product": "Teclado Mecánico RGB",
        "image": "https://images.unsplash.com/photo-1517336714731-489689fd1ca8",
        "quantity": 1,
        "price": 450,
        "subtotal": 450,
        "discount_percent": 0,
        "discount_amount": 0,
        "subtotal_with_discount": 450
      }
    ]
  }
]
```

### `DELETE /api/sales/{id}`

```json
{ "message": "Venta eliminada correctamente" }
```

Si la venta no existe responde `404` con `{ "message": "Venta no encontrada" }`.

### Errores

| Caso | Código | Ejemplo de respuesta |
|---|---|---|
| Validación (sin productos, cantidad ≤ 0, producto inexistente, descuento fuera de 0–10…) | `422` | `{ "message": "...", "errors": { "items.0.quantity": ["..."] } }` |
| Stock insuficiente | `422` | `{ "message": "Stock insuficiente.", "errors": { "stock.1": "Stock insuficiente para '...': disponible 10, solicitado 50." } }` |
| Venta inexistente al eliminar | `404` | `{ "message": "Venta no encontrada" }` |

### Probar con cURL

```bash
# Crear una venta con descuento
curl -X POST https://api-demo.ackee-company.com/api/sales \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"user_id":1,"items":[{"product_id":1,"quantity":2,"discount_percent":10},{"product_id":3,"quantity":1}]}'

# Listar ventas
curl -H "Accept: application/json" https://api-demo.ackee-company.com/api/sales

# Eliminar una venta
curl -X DELETE -H "Accept: application/json" https://api-demo.ackee-company.com/api/sales/1
```

## Decisiones de diseño

- **Transacción y bloqueo de stock.** La venta, sus detalles y el descuento de stock se ejecutan dentro de
  `DB::transaction`. Las filas de los productos se leen con `lockForUpdate()` para que dos ventas simultáneas no
  vendan el mismo stock. Si algo falla, no queda nada guardado.
- **Precios desde la base de datos.** El request solo trae `product_id`, `quantity` y, opcionalmente,
  `discount_percent`. El precio nunca se toma del cliente.
- **Productos repetidos.** El stock se valida por el total pedido de cada producto. Si el mismo producto llega varias
  veces con el mismo descuento, se suman las cantidades en una sola línea; con descuentos distintos se crean líneas
  separadas.
- **Descuento por producto (característica opcional, implementada).** Entre 0 y 10 % (admite decimales). Se calcula
  dentro de la transacción: `discount_amount = subtotal × discount_percent / 100` y
  `subtotal_with_discount = subtotal − discount_amount`. El `total` es la suma de los subtotales con descuento, y
  `total_discount` la suma de los descuentos. Todo se redondea a 2 decimales. La migración
  `2026_10_29_000001_add_discounts_to_sales_tables` agrega las columnas y rellena las ventas anteriores.
- **Eliminar es un borrado lógico** (`SoftDeletes`, la tabla `sales` ya tenía `deleted_at`). La venta deja de
  aparecer en el listado, pero **no se devuelve el stock**, porque el enunciado no lo pide.
- **Campos adicionales en las respuestas.** Además de los campos pedidos, se agregan `image` (productos y detalles),
  `user`, `units`, `created_at_datetime` y `price` por detalle. Los usa el front y no alteran los campos del
  enunciado.
- **Total del ejemplo con descuento.** El ejemplo del enunciado muestra `total: 15000` junto a un subtotal con
  descuento de `13500`, lo que contradice la regla "el total incluye la suma de los subtotales con descuento". Se
  implementó la regla.
- **Decimales en JSON.** `AppServiceProvider` fija `serialize_precision = -1`, porque algunos hostings usan 17 y
  devuelven números como `150.530000000000001136…`.

## Estructura relevante

```text
app/
├── Http/Controllers/Api/
│   ├── ProductController.php   # GET /api/products
│   └── SaleController.php      # store, index y destroy de ventas
├── Models/                     # Product, Sale, SaleDetail (relaciones y casts)
└── Providers/AppServiceProvider.php
database/
├── migrations/                 # incluye la migración de descuentos
└── seeders/                    # ProductSeeder y usuarios de prueba
routes/api.php
```

## Limitaciones conocidas

- **Sin autenticación.** La prueba usa un `user_id` estático y no pide login. Cualquiera que conozca la URL puede
  crear o eliminar ventas. Para un uso real habría que protegerla (por ejemplo con Sanctum).
- **CORS abierto** (valor por defecto de Laravel para `api/*`).
- **Eliminar una venta no repone el stock** (ver decisiones de diseño).

---

> A continuación se conserva, sin cambios, el enunciado original de la prueba.

# Prueba Técnica – API REST de Ventas en Laravel

## Objetivo

Desarrollar una API REST en Laravel para gestionar un flujo básico de ventas.

La prueba evaluará:

- Uso de relaciones entre modelos.
- Validación de datos.
- Manejo de transacciones.
- Control de stock.
- Lógica de negocio.
- Respuestas JSON y códigos HTTP.
- Pruebas mediante Postman u otro cliente HTTP.

---

## Estado inicial del proyecto

El proyecto ya incluye:

- Laravel configurado como API.
- Migraciones listas.
- Seeders con productos cargados.
- Modelos base: `Product`, `Sale` y `SaleDetail`.

---

## Fuera de alcance

No se debe implementar mantenimiento de productos.

Por lo tanto, no corresponde:

- Crear productos.
- Modificar productos.
- Eliminar productos.

Solo se deben utilizar los productos existentes.

---

## Requerimientos

### 1. Listar productos

**Endpoint**

```http
GET /api/products
```

Debe retornar:

- `id`
- `name`
- `price`
- `stock`

**Ejemplo**

```json
[
  {
    "id": 1,
    "name": "Laptop Lenovo",
    "price": 7500,
    "stock": 10
  }
]
```

---

### 2. Crear una venta

Usar el usuario estático:

```text
user_id = 1
```

**Endpoint**

```http
POST /api/sales
```

**Request**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    },
    {
      "product_id": 3,
      "quantity": 1
    }
  ]
}
```

La operación debe:

1. Validar que exista al menos un producto.
2. Validar que cada producto exista.
3. Validar que la cantidad sea mayor que cero.
4. Verificar que exista stock suficiente.
5. Crear la venta.
6. Crear los detalles de la venta.
7. Calcular el subtotal de cada producto.
8. Calcular el total de la venta.
9. Descontar el stock vendido.

Los precios deben obtenerse desde la base de datos y no desde el request.

La creación de la venta, sus detalles y el descuento de stock deben ejecutarse dentro de una transacción.

**Cálculos**

```text
subtotal = price × quantity
total = suma de subtotales
```

**Ejemplo de respuesta**

```json
{
  "message": "Venta creada correctamente",
  "sale_id": 1,
  "total": 16500
}
```

Si no existe stock suficiente, la venta no debe registrarse parcialmente.

---

### 3. Listar ventas

**Endpoint**

```http
GET /api/sales
```

Debe retornar:

- `id`
- `total`
- `created_at`
- Detalle de productos vendidos

**Ejemplo**

```json
[
  {
    "id": 1,
    "total": 16500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000
      }
    ]
  }
]
```

---

### 4. Eliminar una venta

**Endpoint**

```http
DELETE /api/sales/{id}
```

Debe eliminar la venta seleccionada.

**Ejemplo de respuesta**

```json
{
  "message": "Venta eliminada correctamente"
}
```

Si la venta no existe, debe devolverse una respuesta de error adecuada.

---

## Reglas de negocio

- Una venta debe contener al menos un producto.
- No se permiten cantidades iguales o menores que cero.
- No se puede vender más stock del disponible.
- El precio debe obtenerse desde el producto registrado.
- El subtotal se calcula por producto.
- El total corresponde a la suma de los subtotales.
- La creación debe ejecutarse dentro de una transacción.
- Ante cualquier error, no deben quedar datos parciales.
- Las respuestas deben estar en formato JSON.

---

## Resultado esperado

Al finalizar la prueba, la API debe permitir:

- Consultar productos existentes.
- Registrar una venta con sus detalles.
- Calcular subtotales y total.
- Descontar stock.
- Consultar ventas.
- Eliminar ventas.

---

## Características Opcionales

### Descuento por producto

**Esta funcionalidad es opcional y no es requerida para completar la prueba.**

Se puede implementar un sistema de descuentos aplicados a nivel de producto dentro de una venta.

**Cambios en el request de crear venta**

```json
{
  "user_id": 1,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "discount_percent": 10
    },
    {
      "product_id": 3,
      "quantity": 1,
      "discount_percent": 5
    }
  ]
}
```

**Cálculos con descuento**

```text
subtotal = price × quantity
discount_amount = subtotal × (discount_percent / 100)
subtotal_with_discount = subtotal - discount_amount
total = suma de subtotales_with_discount
```

**Cambios en la respuesta de listar ventas**

```json
[
  {
    "id": 1,
    "total": 15000,
    "total_discount": 1500,
    "created_at": "2026-01-21",
    "details": [
      {
        "product": "Laptop Lenovo",
        "quantity": 2,
        "subtotal": 15000,
        "discount_percent": 10,
        "discount_amount": 1500,
        "subtotal_with_discount": 13500
      }
    ]
  }
]
```

**Migración requerida**

Se debe crear las migraciones requeridas para aplicar los requerimientos solicitados

**Reglas de negocio adicionales (si se implementa)**

- El descuento es opcional (por defecto 0%).
- El descuento debe ser un valor entre 0 y 10.
- El cálculo del descuento debe realizarse dentro de la transacción.
- El total debe incluir la suma de los subtotales con descuento.
