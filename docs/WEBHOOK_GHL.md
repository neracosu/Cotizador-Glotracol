# Webhook Glotracol → GoHighLevel — Certificación

El plugin despacha un webhook JSON firmado (HMAC-SHA256) en dos momentos:

- **`event: "created"`** — al enviarse la cotización desde el formulario.
- **`event: "converted"`** — al convertir una cotización en pedido desde el admin.

Para automatizar **pedidos** en GHL, escucha los eventos con `type: "order"`
(normalmente `event: "converted"`, o `event: "created"` si el cliente eligió
"Pedido" en el modal y los precios ya estaban resueltos).

## Cabeceras

- `Content-Type: application/json; charset=utf-8`
- `X-Glotracol-Signature: sha256=<hmac>` — HMAC-SHA256 del cuerpo crudo con el
  `webhook_secret` configurado en Ajustes → Integraciones. Verifícalo en GHL/middleware.

## Estructura del payload (pedido)

```json
{
  "event": "converted",
  "quote_id": 123,
  "reference": "ab12cd34ef56gh78",
  "type": "order",
  "status": "glo-auto-priced",
  "pricing_status": "priced",
  "currency": "COP",
  "total": 850000,
  "units_total": 8,
  "weight_total_kg": 1200.5,
  "size_tag": "tons",
  "created_at": "2026-06-03T15:00:00+00:00",
  "converted_at": "2026-06-03T16:10:00+00:00",
  "client": { "id": 45, "nit": "900123456-7", "name": "Distribuidora X", "is_b2b": true },
  "customer": { "name": "Juan Pérez", "email": "j@x.co", "phone": "+57...", "company": "X", "nit": "900123456-7", "city": "Bogotá" },
  "message": "...",
  "items": [
    { "product_id": 10, "sku": "ALM-500", "sku_producto": "ALM", "name": "Almendras", "presentacion_label": "500 g", "quantity": 5, "unit_price": 28000, "subtotal": 140000, "price_source": "b2b" }
  ],
  "admin_url": "https://glotracol.neracosu.com/wp-admin/post.php?post=123&action=edit"
}
```

## Mapeo sugerido a un pedido en GHL

| Campo GHL | Campo del payload |
|---|---|
| Contacto (nombre/email/teléfono) | `customer.*` |
| Empresa / NIT | `customer.company`, `customer.nit` |
| Valor del pedido | `total` (moneda `currency`) |
| Líneas | `items[]` → `name` + `presentacion_label`, `quantity`, `unit_price`, `subtotal` |
| Identificador externo | `reference` o `quote_id` |
| Disparador "es pedido" | `type == "order"` |

## Verificación

- Pantalla **Cotizaciones → Logs**, categoría `webhook`: registra cada intento (OK/FAIL + status_code).
- Reintentos automáticos con backoff: 1m, 5m, 15m.
- Para probar sin GHL, apunta `webhook_url` a https://webhook.site y revisa el cuerpo recibido.
