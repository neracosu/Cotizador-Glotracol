# Glotracol Cotizador — Documentación técnica

**Versión documentada:** 2.0.1 · **Última actualización:** 2026-04-30
**Autor:** Neracosu (https://neracosu.com/) · **Cliente:** Glotracol — Global Trading de Colombia

Este documento describe el estado actual del plugin. La versión inicial 1.0.0 (línea base) fue documentada el mismo día. Las 1.1.0 → 1.2.0 corresponden a las primeras dos fases del roadmap v2.0.

## Changelog rápido

- **2.0.1** (2026-04-30) — Fix bug importador (token mixed-case → file not found) + sistema de logs centralizado (`Glotracol_Quote_Logger`) con pantalla viewer, filtros, banner de alerta en dashboard.
- **2.0.0** (2026-04-30) — Fase E — Reportes y QA. Release final del roadmap v2.0.
- **1.5.0** (2026-04-30) — Fase D — Cotización vs Pedido + auto-respuesta: modal pre-submit, conexión a pricing resolver, statuses nuevos, emails diferenciados, "Convertir en pedido" desde admin.
- **1.4.0** (2026-04-30) — Fase C — Presentaciones: pestaña "Presentaciones" en producto WC, selector en single-product, dropdown swap en carrito.
- **1.3.0** (2026-04-30) — Fase B — Data foundation: CRM clientes B2B (F8), sistema de precios público + B2B (F9), importador CSV de 4 hojas (F10).
- **1.2.0** (2026-04-30) — Fase A — UX wins: refinamiento del mensaje post-add-to-cart (F1), CTA en pestaña Descripción + rename "Información adicional"→"Presentación" (F2), auto-clasificación small/medium/large (F3), email destacado para pedidos grandes (F4).
- **1.1.0** (2026-04-30) — Fase 0 — Hardening: rename del carrito en triple capa, `ensure_wc_cart_loaded()` defensivo, sección "Estado de compatibilidad" en el dashboard.
- **1.0.0** (2026-04-30) — Release inicial.

---

## 1. Propósito

Convierte una tienda WooCommerce en un sistema **RFQ (Request for Quote)**:

- Oculta todos los precios (catálogo, producto individual, carrito, emails de WC).
- Reemplaza "Añadir al carrito" por **"Añadir a la cotización"** y "Finalizar compra" por **"Solicitar cotización ahora"**.
- Bloquea el checkout: cualquier acceso a `/finalizar-compra` se redirige (302) al formulario.
- El cliente arma un carrito normal (productos + cantidades), va al formulario de cotización, llena sus datos y envía.
- Al enviar:
  - Se crea un CPT `glo_quote` con estado `glo-new`.
  - Se envía email al equipo comercial (configurable, multi-destinatario, BCC opcional).
  - Se envía email de confirmación al cliente.
  - Se dispara webhook firmado (HMAC-SHA256) si está configurado, con reintento.
  - Se vacía el carrito y se redirige a la página de gracias con `?qid=<token>`.
- El equipo gestiona las cotizaciones desde el admin (CPT con estados, dashboard propio, acciones rápidas mailto / WhatsApp).

---

## 2. Estructura de archivos

```
glotracol-quote/
├── glotracol-quote.php              # Bootstrap: constantes, requires, hooks de activación, declaración HPOS+Blocks
├── uninstall.php                    # Borra datos solo si delete_data_on_uninstall = yes
├── readme.txt                       # Cabecera estilo WP.org
├── languages/                       # (vacío) — text domain: glotracol-quote
├── includes/
│   ├── helpers.php                  # Funciones globales: settings, placeholders, IP, templates
│   ├── class-activator.php          # Crea páginas form/thanks, settings, registra CPT y statuses
│   ├── class-plugin.php             # Singleton bootstrap, instancia clases, enqueue assets
│   ├── class-rate-limit.php         # Transients por IP
│   ├── class-product-buttons.php    # Filtros de WC en catálogo y producto
│   ├── class-cart-overrides.php     # Filtros de WC en carrito + bloqueo checkout + rename strings
│   ├── class-quote-cpt.php          # Registro CPT glo_quote + 4 estados + columnas admin
│   ├── class-quote-form.php         # Shortcodes form/thanks + handle_submit + AJAX qty
│   ├── class-quote-emails.php       # wp_mail admin + cliente con templates HTML
│   ├── class-smtp.php               # phpmailer_init override + detección de SMTP externo + ajax test
│   ├── class-webhook.php            # wp_schedule_single_event + HMAC + retry
│   ├── class-admin-meta-box.php     # 4 metaboxes en pantalla de cotización
│   ├── class-admin-settings.php     # Página de ajustes con 6 tabs
│   └── class-admin-dashboard.php    # Página "Inicio" con stats, checklist y FAQ
├── templates/                       # Override desde tema en /<theme>/glotracol-quote/<file>
│   ├── form.php
│   ├── thanks.php
│   ├── email-admin.php
│   └── email-customer.php
├── assets/
│   ├── css/quote.css                # Toast, botones, formulario, statuses
│   ├── css/admin.css                # Dashboard admin
│   └── js/quote.js                  # Toast post-add-to-cart + editor inline de cantidades
└── docs/
    └── ARQUITECTURA.md              # Este documento
```

---

## 3. Bootstrap y constantes

`glotracol-quote.php` define:

| Constante | Valor |
|---|---|
| `GLOTRACOL_QUOTE_VERSION` | `1.0.0` |
| `GLOTRACOL_QUOTE_FILE` | `__FILE__` del plugin |
| `GLOTRACOL_QUOTE_PATH` | `plugin_dir_path()` |
| `GLOTRACOL_QUOTE_URL` | `plugin_dir_url()` |
| `GLOTRACOL_QUOTE_BASENAME` | `plugin_basename()` |

Compatibilidad declarada (en `before_woocommerce_init`):
- `custom_order_tables` (HPOS) → true
- `cart_checkout_blocks` → true

Si WooCommerce no está activo, se muestra notice de error y se aborta. Carga el text domain desde `languages/` y arranca `Glotracol_Quote_Plugin::instance()` con prioridad 20 en `plugins_loaded`.

---

## 4. Custom Post Type y estados

**CPT slug:** `glo_quote`

| Propiedad | Valor |
|---|---|
| `public` | false |
| `show_ui` | true |
| `show_in_admin_bar` | false |
| `show_in_nav_menus` | false |
| `menu_icon` | `dashicons-clipboard` |
| `menu_position` | 56 |
| `supports` | `[ 'title' ]` (solo título) |
| `has_archive` | false |
| `rewrite` | false |
| `query_var` | false |
| `exclude_from_search` | true |

**Estados personalizados** (registrados con `register_post_status`):

| Slug | Etiqueta |
|---|---|
| `glo-new` | Nueva |
| `glo-processing` | En proceso |
| `glo-responded` | Respondida |
| `glo-closed` | Cerrada |

Todos los estados son `protected`, no públicos. El dropdown de estado en la pantalla de edición se reemplaza vía JS (`append_statuses_to_dropdown()` en `admin_footer-post.php`) porque WP no soporta nativamente publish-state custom statuses en ese dropdown.

**Columnas custom en list table:** Cliente, Email, Empresa, Teléfono, Items (count + qty), Estado, Fecha. Sortable: customer/email/company.

### 4.1. Meta keys del CPT

| Meta key | Tipo | Contenido |
|---|---|---|
| `_glo_qid` | string (16 chars) | Token público para deep-link a página de gracias |
| `_glo_customer_name` | string | Nombre del cliente |
| `_glo_customer_email` | string | Email |
| `_glo_customer_phone` | string | Teléfono |
| `_glo_customer_company` | string | Empresa |
| `_glo_customer_nit` | string | NIT/Documento (opcional) |
| `_glo_customer_city` | string | Ciudad/País (opcional) |
| `_glo_customer_message` | string | Mensaje libre del cliente |
| `_glo_items` | array | Lista de items: `[ {product_id, name, sku, quantity}, ... ]` |
| `_glo_meta` | array | `{ ip, user_agent, referer, lang, timestamp }` |
| `_glo_email_log` | array | Log apilado de envíos: `{ type, to, sent_at, success }`. **Tipo `webhook` también se loguea aquí** (no en una clave separada) |
| `_glo_webhook_retried` | int | Marker `1` cuando ya se reintentó el webhook |

---

## 5. Páginas autocreadas y shortcodes

Activación (`Glotracol_Quote_Activator::activate`) crea o reutiliza:

| Slug | Título | Shortcode | Option |
|---|---|---|---|
| `solicitar-cotizacion` | Solicitar cotización | `[glotracol_quote_form]` | `glotracol_quote_form_page_id` |
| `cotizacion-enviada` | Cotización enviada | `[glotracol_quote_thanks]` | `glotracol_quote_thanks_page_id` |

Si la página ya existe pero no contiene el shortcode, se inyecta. Si el option ya apunta a un post válido, no se toca.

### 5.1. Shortcodes

#### `[glotracol_quote_form]`

- Renderiza el formulario completo (lista editable de items + datos personales).
- Si el carrito está vacío: muestra estado vacío con link al catálogo.
- Recibe vars del template: `cart_items, error, old, action_url, submit_action, nonce_field, nonce_action, form_intro, terms_text, shop_url`.
- `error` y `old` se leen de `$_GET['gloq_error']` / `$_GET['gloq_old']` (base64-json) tras un redirect de error.

#### `[glotracol_quote_thanks]`

- Lee `?qid=<token>` y resuelve el post id buscando por `_glo_qid`.
- Reemplaza placeholders `{quote_id}`, `{customer_email}`, `{customer_name}` en el `thanks_message`.

---

## 6. Settings / Options

Option key: `glotracol_quote_settings` (array).

Defaults declarados en `glotracol_quote_get_settings()`:

| Key | Default | Descripción |
|---|---|---|
| `destination_emails` | `admin_email` | Destinatarios internos (CSV) |
| `bcc_emails` | `''` | BCC opcional (CSV) |
| `sender_name` | `bloginfo('name')` | Nombre From |
| `sender_email` | `admin_email` | Email From |
| `admin_subject` | `Nueva cotización #{quote_id} — {customer_name}` | Asunto admin |
| `customer_subject` | `Hemos recibido tu cotización — Glotracol` | Asunto cliente |
| `customer_intro` | (texto largo) | Intro email cliente |
| `admin_intro` | (texto largo) | Intro email admin |
| `thanks_message` | (texto largo) | Mensaje página de gracias |
| `form_intro` | (texto largo) | Intro del formulario |
| `terms_text` | (texto largo) | Texto del checkbox de aceptación |
| `webhook_url` | `''` | URL del webhook |
| `webhook_secret` | `''` | Secret HMAC |
| `rate_limit_per_hour` | `3` | Envíos/hora por IP (0 = sin límite) |
| `delete_data_on_uninstall` | `'no'` | `'yes'`/`'no'` |
| `smtp_enabled` | `'no'` | `'yes'`/`'no'` |
| `smtp_host` | `''` | |
| `smtp_port` | `'587'` | |
| `smtp_encryption` | `'tls'` | `'tls'`/`'ssl'`/`'none'` |
| `smtp_username` | `''` | |
| `smtp_password` | `''` | (sanitize: si entra vacío, conserva el actual) |
| `smtp_from_name` | `''` | |
| `smtp_from_email` | `''` | |

**Placeholders soportados en asuntos y cuerpos:** `{quote_id}`, `{customer_name}`, `{customer_email}`, `{customer_phone}`, `{customer_company}`, `{site_name}`.

**Options independientes:**
- `glotracol_quote_form_page_id` — ID de la página de formulario.
- `glotracol_quote_thanks_page_id` — ID de la página de gracias.

---

## 7. Pantallas admin

Bajo el menú "Cotizaciones" (icono dashicons-clipboard, posición 56):

| Submenú | Slug | Clase responsable |
|---|---|---|
| **Inicio** (forzado a la posición 0 vía `$submenu`) | `glotracol-quote-dashboard` | `Glotracol_Quote_Admin_Dashboard` |
| Todas las cotizaciones | `edit.php?post_type=glo_quote` | (CPT) |
| Configuración | `glotracol-quote-settings` | `Glotracol_Quote_Admin_Settings` |

### 7.1. Dashboard

Página "Inicio" del cotizador. Componentes:

- **Hero** con badge de versión, título, descripción y 3 CTAs (lista, settings, formulario público).
- **Stats grid** con 5 tarjetas (total, nuevas con pulse, en proceso, respondidas, cerradas). Datos de `wp_count_posts('glo_quote')`.
- **Empty state** si total = 0.
- **Tabla "Últimas cotizaciones"** con las 5 más recientes (cualquier estado glo-*).
- **Checklist "Estado de configuración"** con 6 checks:
  1. Email destino interno configurado (warning si es admin del sitio).
  2. Página de formulario creada.
  3. Página de confirmación creada.
  4. Remitente configurado (warning si no es @glotracol.com).
  5. Webhook (opcional).
  6. SMTP configurado (detecta plugin externo o SMTP propio activo).
- **Guía paso a paso** (5 pasos).
- **Flujo del cliente** (5 pasos).
- **Cómo gestionar cotizaciones**.
- **6 FAQs** en `<details>`.
- **Footer** con créditos a Neracosu y eagencia.

### 7.2. Pantalla de cotización individual

Metaboxes registrados en `add_meta_boxes`:

| ID | Posición | Contenido |
|---|---|---|
| `glotracol_quote_customer` | normal/high | Tabla de datos del cliente |
| `glotracol_quote_items` | normal/high | Tabla de productos solicitados |
| `glotracol_quote_meta` | side/low | IP, UA, referer, lang, timestamp |
| `glotracol_quote_email_log` | side/low | Log de envíos (admin + cliente + webhook) |

**Acciones rápidas** (inyectadas en `edit_form_after_title`): botones Mailto y WhatsApp (`wa.me/<phone-sin-no-numericos>`).

El dropdown de estado se sustituye vía JS para listar los 4 estados glo-*.

### 7.3. Settings — 6 tabs

| Tab | Campos |
|---|---|
| **General** | destination_emails, bcc_emails, sender_name, sender_email |
| **Emails** | admin_subject, admin_intro, customer_subject, customer_intro |
| **Formulario** | form_intro, terms_text, thanks_message |
| **SMTP** | (notice si detecta plugin SMTP externo) smtp_enabled, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password (placeholder "•••• sin cambios"), smtp_from_name, smtp_from_email + botón "enviar email de prueba" (AJAX `gloq_smtp_test`) |
| **Integraciones** | webhook_url, webhook_secret |
| **Avanzado** | rate_limit_per_hour, delete_data_on_uninstall |

**Sanitize:** escapa cada campo según su tipo. La contraseña SMTP **conserva el valor anterior si entra vacía** (la UI siempre envía `''` para no exponer la pwd guardada).

---

## 8. Flujo público

### 8.1. Catálogo y producto

`Glotracol_Quote_Product_Buttons` aplica:

- `woocommerce_product_single_add_to_cart_text` → `"Añadir a mi cotización"` o, si ya está en cart, `"✓ Añadir más (ya tienes N en tu cotización)"`.
- `woocommerce_product_add_to_cart_text` (loop) → `"Añadir a la cotización"` o `"✓ Añadir más a la cotización"`.
- `woocommerce_get_price_html` → `''` (oculta precios en todo el frontend).
- `woocommerce_is_purchasable` y `woocommerce_variation_is_purchasable` → fuerza `true` si el producto existe y tiene stock (necesario porque sin precio WC marca como no comprable).
- `woocommerce_loop_add_to_cart_args` → añade clases `gloq-add-button` + `gloq-in-cart` (si aplica) y `data-gloq-product-name`.
- `woocommerce_loop_add_to_cart_link` → si está en cart, agrega badge `<span class="gloq-cart-badge">✓ N en tu cotización</span>`.

### 8.2. Mensaje "añadido al carrito"

Filtro `wc_add_to_cart_message_html` reemplaza el mensaje WC por:

```html
<a class="button wc-forward gloq-with-icon">[icono] Ver mi cotización</a>
<a class="button glotracol-quote-button">Solicitar cotización ahora</a>
<span>N producto(s) añadido(s) a tu cotización.</span>
```

### 8.3. Carrito (`/carrito`)

`Glotracol_Quote_Cart_Overrides`:

- Vacía precio/subtotal/total en cart e ítems (`woocommerce_cart_item_price`, `..._subtotal`, `woocommerce_cart_subtotal`, `woocommerce_cart_totals_order_total_html`).
- Quita el botón "Proceed to checkout" estándar (`remove_action(...)`) y añade el suyo: `<a class="checkout-button button alt wc-forward glotracol-quote-button" href="<form_url>">Solicitar cotización ahora →</a>`.
- Inyecta CSS inline en `wp_print_styles` que oculta `.product-price`, `.product-subtotal`, `.cart_totals .order-total`, `.cart_totals .cart-subtotal`, `.woocommerce-shipping-totals`, `.cart-collaterals .shipping-calculator-button`, `.cart-collaterals .checkout-button:not(.glotracol-quote-button)`.
- Retitula la página del carrito (`the_title` filter en `wc_get_page_id('cart')`) → `"Mi cotización"`.
- Renombra strings de WC vía filtros `gettext` y `gettext_with_context` (mapa hardcoded: Cart→Mi cotización, Update cart→Actualizar cotización, Return to shop→Ver catálogo, Remove this item→Quitar de la cotización, etc., con variantes ES preexistentes).
- Localiza `GloqData` (cartUrl, quoteUrl, cartCount, i18n) en el handle `glotracol-quote`.

### 8.4. Bloqueo checkout

`template_redirect`:

- Si `is_checkout()` y NO `is_wc_endpoint_url()` → 302 al formulario.
- Si endpoint `order-pay` u `order-received` → 302 al formulario.

### 8.5. Formulario `/solicitar-cotizacion`

Template `templates/form.php`. Estructura:

1. Alert de error (si `?gloq_error=`).
2. `form_intro` (configurable).
3. Header con count y link "+ Añadir más productos" (al shop).
4. Tabla de items con: imagen, nombre+permalink, SKU, **input de cantidad editable inline**, botón × para quitar.
5. Helper text: "Las cantidades se guardan automáticamente al cambiarlas".
6. Form de datos personales: name*, email*, phone*, company*, nit, city, message.
7. Honeypot oculto (`gloq_website`) en `position:absolute;left:-9999px`.
8. Checkbox de términos (configurable, requerido).
9. Submit.

**Editor inline de cantidades** (`assets/js/quote.js`):
- Debounce 400ms al `change/input`.
- POST a `admin-ajax.php` con action `gloq_update_qty`, nonce `gloq_update_qty`, key, qty.
- Endpoint en `Glotracol_Quote_Form::ajax_update_qty()` → llama `ensure_wc_cart_loaded()` (ver §11).
- Si qty=0 → `WC()->cart->remove_cart_item($key)`. Si qty>0 → `WC()->cart->set_quantity($key, $qty, true)`.
- Si la fila se elimina y queda vacío → `location.reload()`.

### 8.6. Submit del formulario

`Glotracol_Quote_Form::handle_submit` (action `admin_post[_nopriv]_glotracol_quote_submit`):

1. `ensure_wc_cart_loaded()` (admin-post.php no inicializa WC frontend).
2. Honeypot: si `gloq_website` no está vacío → redirige a thanks (sin guardar).
3. Verifica nonce; si falla → redirect con error.
4. Rate limit: `Glotracol_Quote_Rate_Limit::check($ip)` con transient `gloq_rl_<md5(ip)>`.
5. Sanitiza campos (`sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`).
6. Valida required: name, email, phone, company, terms. Si falta algo → redirect con `gloq_error` + `gloq_old` (b64 json).
7. Verifica que el cart no esté vacío.
8. Construye `$items` desde el cart actual.
9. `do_action('glotracol_quote_before_save', $payload)`.
10. `wp_insert_post` con status `glo-new` → guarda meta keys (`_glo_qid` con `wp_generate_password(16,false,false)`).
11. Renombra el post a `Cotización #<id> — <name>`.
12. `Glotracol_Quote_Rate_Limit::record($ip)`.
13. `do_action('glotracol_quote_created', $post_id, $payload)`.
14. `WC()->cart->empty_cart()`.
15. Redirect a thanks con `?qid=<token>`.

---

## 9. Emails

`Glotracol_Quote_Emails::send_emails` se engancha a `glotracol_quote_created`.

### 9.1. Email al admin

- **Para:** `destination_emails` (parseado por `glotracol_quote_emails_to_array`). Fallback: `admin_email`.
- **Headers:** `Content-Type: text/html`, `From: <sender_name> <sender_email>`, `Reply-To: <customer_email>`, `Bcc: ...` (uno por cada).
- **Asunto:** `admin_subject` con placeholders.
- **Body:** template `email-admin.php` (HTML responsive, header verde `#0a4d3a`, tabla de cliente, mensaje, tabla de productos, botón CTA al admin, footer con IP). Filtrable vía `glotracol_quote_email_admin_body`.

### 9.2. Email al cliente

- **Para:** `customer.email` (si es válido).
- **Headers:** mismos sin Bcc/Reply-To.
- **Asunto:** `customer_subject` con placeholders.
- **Body:** template `email-customer.php`. Filtrable vía `glotracol_quote_email_customer_body`.

### 9.3. Log

Cada envío llama a `log()` que apila `{type, to, sent_at, success}` en `_glo_email_log`. **El webhook también se loguea aquí** con `type='webhook'`.

---

## 10. SMTP propio

`Glotracol_Quote_SMTP::configure_phpmailer` (prioridad 100 en `phpmailer_init`):

- Solo actúa si `smtp_enabled === 'yes'` Y hay `smtp_host` Y `smtp_port`.
- `$phpmailer->isSMTP()` + Host/Port/Auth/Username/Password.
- Encriptación: `'tls'` → `SMTPSecure='tls'`, `'ssl'` → `'ssl'`, `'none'` → `''` y `SMTPAutoTLS=false`.
- Override `setFrom()` si `smtp_from_email` es válido (catch silencioso de Exception).

**Detección de SMTP externo** (`detect_external_smtp` static):

Por **clase**: `SiteMailer\Plugin`, `Site_Mailer\Plugin`, `WPMailSMTP\Core`, `WPMailSMTP\Pro\Pro`, `EasyWPSMTP\Plugin`, `FluentMail\App\Application`, `Post_SMTP_Mailer\Postman`, `Wpo365\Wpo365_Plugin`.

Por **slug activo** (`is_plugin_active`): `site-mailer/plugin.php`, `wp-mail-smtp/wp_mail_smtp.php`, `easy-wp-smtp/easy-wp-smtp.php`, `fluent-smtp/fluent-smtp.php`, `post-smtp/postman-smtp.php`.

Por **función/constante**: `easy_wp_smtp()`, `POST_SMTP_VER`, `WPMS_PLUGIN_VER`.

Devuelve el nombre legible del primer match o `null`.

**Test endpoint** AJAX `gloq_smtp_test` (admin-only, nonce `gloq_smtp_test`): envía un email HTML de prueba y captura errores vía `wp_mail_failed`.

---

## 11. Webhook

`Glotracol_Quote_Webhook`:

- Engancha `glotracol_quote_created` con prioridad 20.
- Programa `wp_schedule_single_event(time()+5, 'glotracol_quote_webhook_dispatch', [$quote_id])` para no bloquear el redirect.
- En `dispatch($quote_id)`:
  - Reconstruye el payload desde meta keys:
    ```json
    {
      "quote_id": int,
      "reference": "<_glo_qid>",
      "status": "glo-new|...",
      "created_at": "ISO8601",
      "customer": { "name", "email", "phone", "company", "nit", "city" },
      "message": "...",
      "items": [{ "product_id", "name", "sku", "quantity" }, ...],
      "admin_url": "..."
    }
    ```
  - Filtrable vía `glotracol_quote_webhook_payload`.
  - Headers: `Content-Type: application/json` y, si hay secret, `X-Glotracol-Signature: sha256=<hmac>`.
  - `wp_remote_post` con timeout 10s.
  - Considera OK si HTTP 2xx.
  - **Reintento simple:** si falla y no hay marker `_glo_webhook_retried`, lo programa otra vez en +60s y marca.

---

## 12. Anti-spam

Tres capas:

1. **Nonce** WP estándar (`wp_nonce_field` / `wp_verify_nonce`) con action `glotracol_quote_submit_nonce` y field `glotracol_quote_nonce`.
2. **Honeypot** `gloq_website` (oculto via CSS `position:absolute;left:-9999px`).
3. **Rate limit por IP** vía `transient` con TTL `HOUR_IN_SECONDS`. Default 3 envíos/hora. Configurable. `0` desactiva.

Detección de IP (`glotracol_quote_get_client_ip`): orden `HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP`, `REMOTE_ADDR` con validación `FILTER_VALIDATE_IP`.

---

## 13. Templates (override desde tema)

`glotracol_quote_load_template($name, $vars)` busca primero `<stylesheet_directory>/glotracol-quote/<name>` y luego `<plugin>/templates/<name>`. Permite a un tema sobrescribir cualquier template colocando el archivo en `wp-content/themes/<tema>/glotracol-quote/<name>.php`.

| Template | Vars disponibles |
|---|---|
| `form.php` | `cart_items, error, old, action_url, submit_action, nonce_field, nonce_action, form_intro, terms_text, shop_url` |
| `thanks.php` | `message, quote_id, customer_email, customer_name` |
| `email-admin.php` | `quote_id, customer, items, message, meta, intro, edit_url` |
| `email-customer.php` | `quote_id, customer, items, intro` |

---

## 14. Hooks públicos (contrato de extensión)

### 14.1. Actions

| Hook | Args | Cuándo |
|---|---|---|
| `glotracol_quote_before_save` | `$payload` | Antes de `wp_insert_post` |
| `glotracol_quote_created` | `$quote_id, $payload` | Después de guardar el CPT y todas sus metas, justo antes del redirect |
| `glotracol_quote_webhook_dispatch` | `$quote_id` | (Interno, vía cron) Ejecuta el dispatch del webhook |

### 14.2. Filters

| Hook | Args | Devuelve |
|---|---|---|
| `glotracol_quote_email_admin_body` | `$body, $quote_id, $payload` | HTML del email admin |
| `glotracol_quote_email_customer_body` | `$body, $quote_id, $payload` | HTML del email cliente |
| `glotracol_quote_webhook_payload` | `$payload, $quote_id` | Array final del webhook antes de serializar |

### 14.3. AJAX

| Action | Auth | Nonce | Descripción |
|---|---|---|---|
| `gloq_update_qty` | nopriv + priv | `gloq_update_qty` | Actualiza cantidad de un item del cart desde la página de formulario |
| `gloq_smtp_test` | priv (`manage_options`) | `gloq_smtp_test` | Envía email de prueba SMTP |

### 14.4. Admin-post

| Action | Auth | Descripción |
|---|---|---|
| `glotracol_quote_submit` | nopriv + priv | Submit del formulario público |

---

## 15. Assets

### 15.1. CSS frontend (`assets/css/quote.css`)

Bloques principales:

- **Toast container** (`#gloq-toast-container`, fixed top-right, mobile top-fullwidth).
- **Toast** con icono ✓, mensaje, dos links (Ver mi cotización + Solicitar ahora) y close button.
- **Botón "Añadir a la cotización"** (`.gloq-add-button`) con animación `gloq-just-added` (1.8s) y estado persistente `.gloq-in-cart`.
- **Badge** `.gloq-cart-badge` ("✓ N en tu cotización").
- **Override del enlace `.added_to_cart.wc-forward`** post-AJAX (estilo botón verde con icono carrito SVG inline).
- **Tabla de items** del formulario con flash `gloq-saved` al guardar qty.
- **Formulario** con grid responsive (`flex-wrap`) y focus ring verde.
- **Página de gracias** centrada con icono ✓.
- **Statuses** del CPT: pills coloreadas (`.glo-status-glo-new` verde, `glo-processing` amarillo, `glo-responded` azul, `glo-closed` gris).

**Brand color principal:** `#0a4d3a` (verde Glotracol). Hover: `#0d6149`.

### 15.2. CSS admin (`assets/css/admin.css`)

Estilos del dashboard (hero, stats grid, checklist, FAQ, etc.). Solo se carga en la página `glotracol-quote-dashboard`.

### 15.3. JS frontend (`assets/js/quote.js`)

- **`ensureToastContainer()`** crea `#gloq-toast-container` si no existe.
- **`showToast(productName)`** crea un toast, anima entrada, autodismiss en 5s, click-to-close.
- Listener `added_to_cart` (evento WC) → captura `data-gloq-product-name` del botón, dispara toast y actualiza counter desde fragments.
- **Form submit:** disabled + texto "Enviando…" 30s.
- **Auto-clean URL:** quita `?gloq_error=` / `?gloq_old=` con `history.replaceState`.
- **Editor inline de cantidades** (sección §8.5).

### 15.4. Carga condicional

`Glotracol_Quote_Plugin::should_load_assets`:

- `is_cart() || is_shop() || is_product_category() || is_product_tag() || is_product()` → carga.
- `is_page(form_page_id)` o `is_page(thanks_page_id)` → carga.

`GloqAjax` solo se localiza cuando estamos en la página del formulario.

---

## 16. `ensure_wc_cart_loaded()` (workaround crítico)

`admin-post.php` y `admin-ajax.php` no arrancan el frontend de WC, por lo que `WC()->cart`, `WC()->session` y las funciones de cart no están disponibles. Esto rompía:

1. El submit del formulario (no veía los items del carrito del usuario).
2. La edición inline de cantidades (mismo problema).

**Solución implementada** en `Glotracol_Quote_Form::ensure_wc_cart_loaded`:

- Carga manualmente `wc-cart-functions.php`, `wc-notice-functions.php`, `wc-template-functions.php` (vía `WC_ABSPATH` o fallback `WP_PLUGIN_DIR/woocommerce/`) si `wc_get_cart_item_data_hash` no existe.
- Instancia `WC()->session` (clase via filtro `woocommerce_session_handler`) y la inicializa.
- Instancia `WC()->customer` y `WC()->cart`.
- Llama a `WC()->cart->get_cart_from_session()` para que los items aparezcan.

Sin este helper, ni el submit ni el AJAX de cantidades funcionarían correctamente.

---

## 17. Activación / Desactivación / Desinstalación

### 17.1. Activación

`Glotracol_Quote_Activator::activate`:

1. Crea/reutiliza páginas form y thanks (asegura que tienen el shortcode).
2. Si no existe la option de settings, la crea con los defaults.
3. Registra CPT y statuses (necesario para que `wp_count_posts` los vea inmediatamente).
4. `flush_rewrite_rules`.

### 17.2. Desactivación

Solo `flush_rewrite_rules`. **No** borra páginas, settings ni datos.

### 17.3. Desinstalación

`uninstall.php` lee la setting `delete_data_on_uninstall`. Si es `'yes'`:

- Borra todos los posts `glo_quote` (force delete).
- Borra options `glotracol_quote_settings`, `glotracol_quote_form_page_id`, `glotracol_quote_thanks_page_id`.

**No borra:** las páginas WP creadas, los transients de rate-limit, el cron event de webhook.

---

## 18. Internacionalización

- Text domain: `glotracol-quote`.
- Domain Path: `/languages` (vacío al momento de este snapshot).
- Strings hardcoded en español. No hay archivos .pot/.po/.mo aún.
- Renombrado de strings de WooCommerce vía filtro `gettext` (no usa __() → no es traducible vía glotextdomain).

---

## 19. Compatibilidad declarada

| Feature | Declarado |
|---|---|
| HPOS (`custom_order_tables`) | ✅ |
| Cart/Checkout Blocks | ✅ |
| WC mínimo | 8.0 |
| WC tested up to | 10.7 |
| WP mínimo | 6.0 |
| PHP mínimo | 7.4 |

Nota: el plugin no usa órdenes WC ni endpoints de cart blocks; las declaraciones son nominales y evitan el banner "incompatible" en el listado de plugins.

---

## 20. Limitaciones / observaciones conocidas

Este apartado es **inventario de cosas a tener presente**, no un backlog acordado de cambios. Útil para arrancar la siguiente iteración:

- **Strings no internacionalizados.** Todos los textos están hardcoded en español; el rename de carrito→cotización vía `gettext` no respeta `domain` del plugin (pisa solo el dominio `woocommerce`).
- **Templates de email no usan estilos del tema** ni respetan dark mode de clientes de email modernos (Gmail clip > 102KB).
- **Webhook log comparte clave** con el log de emails (`_glo_email_log`), no hay consulta independiente; tampoco se persiste el response body en caso de error (solo OK/no-OK).
- **Reintento de webhook es de un solo intento** (60s después). No hay backoff exponencial ni cola persistente más allá del cron de WP.
- **Sin reCAPTCHA / Turnstile.** Anti-spam se apoya en honeypot + rate limit + nonce.
- **Sin export de cotizaciones** (CSV, PDF). El equipo comercial responde vía email.
- **Sin filtros por estado** en el list table (el método `inject_status_filter` está reservado vacío).
- **Page title hack** (`cart_page_title`) tiene la condición `! is_admin() === false` que es siempre falsa por precedencia → el bloque de fall-through no hace nada útil pero tampoco rompe; el código real funciona por la rama posterior `wc_get_page_id('cart')`.
- **`rename_cart_strings_ctx` ignora `$context`** (delega al filtro sin contexto), así que strings de WC marcadas con contexto pueden o no traducirse según el match exacto del original.
- **No persiste la versión del plugin en una option** (`glotracol_quote_version`) — no hay routine de upgrade/migration aún.
- **`smtp_password` se guarda en claro** en `wp_options` (sin encriptación). Es la práctica habitual en plugins SMTP de WP, pero conviene documentarlo.
- **`force_purchasable` usa prioridad 100** y se aplica a todos los productos en stock; un producto que el comercio quiera ocultar vía "no comprable" no podrá hacerlo desde la UI estándar de WC.
- **`languages/` está vacío.** No se distribuyen .mo/.po.
- **El submit usa `admin-post.php`** (sync, redirect-based). Un cambio futuro a AJAX evitaría el roundtrip de carga de WC en `ensure_wc_cart_loaded`.

---

## 21. Diagrama de flujo end-to-end

```
[Catálogo]                 [Producto]                    [Carrito]
  |                          |                              |
  | "Añadir a la            | "Añadir a mi                 | • Sin precios
  |  cotización"            |  cotización"                  | • Sin checkout
  |                          |                              | • Botón "Solicitar
  v                          v                              |    cotización ahora →"
  +------------- WC añade item -----------+                 |
                                           |                v
                  Toast: "✓ X añadido"  ←--+      [/solicitar-cotizacion]
                                                           |
                                                           | • Editor inline qty (AJAX)
                                                           | • Form datos personales
                                                           | • Honeypot + nonce + rate-limit
                                                           v
                                          POST admin-post.php?action=glotracol_quote_submit
                                                           |
                                                           v
                                               Glotracol_Quote_Form::handle_submit
                                                           |
                                                ┌──────────┴────────────┐
                                                | • ensure_wc_cart_loaded
                                                | • valida nonce/honeypot/rate
                                                | • sanitiza fields
                                                | • valida required + cart no vacío
                                                | • do_action('glotracol_quote_before_save')
                                                | • wp_insert_post status=glo-new
                                                | • update_post_meta (×N)
                                                | • do_action('glotracol_quote_created')
                                                |       │
                                                |       ├──► Glotracol_Quote_Emails (sync)
                                                |       │       ├─► wp_mail admin (HTML)
                                                |       │       └─► wp_mail customer (HTML)
                                                |       │
                                                |       └──► Glotracol_Quote_Webhook (async)
                                                |               wp_schedule_single_event(+5s)
                                                |                       │
                                                |                       v
                                                |             POST JSON + HMAC-SHA256
                                                |             retry once en +60s si falla
                                                |
                                                | • WC()->cart->empty_cart()
                                                | • redirect a /cotizacion-enviada?qid=<token>
                                                v
                                             [Página de gracias]
                                                           |
                                                           | • Resuelve post por _glo_qid
                                                           | • Renderiza thanks_message
                                                           | • Botones "Inicio" / "Catálogo"
                                                           v
                                                          FIN

(Admin)
[Cotizaciones → Inicio]            [Listado glo_quote]            [Edición de cotización]
  • Stats                            • Columnas custom               • Datos cliente
  • Checklist config                 • Filtros estado                • Productos
  • Guía + FAQ                       • Sortable                      • Info técnica
                                                                    • Log envíos
                                                                    • Acciones rápidas
                                                                       (mailto / wa.me)
                                                                    • Cambio de estado
                                                                       glo-new → ... → glo-closed
```

---

## 22. Dependencias externas

- **WooCommerce ≥ 8.0** (requerido).
- **PHP ≥ 7.4**.
- **WordPress ≥ 6.0**.
- **PHPMailer** (incluido en WP core, usado por la integración SMTP).
- **jQuery** (incluido en WP core, requerido por `quote.js`).

No usa Composer ni node_modules. Sin librerías de terceros distribuidas.

---

## 23. Snapshot de versión

| Item | Valor |
|---|---|
| Versión plugin | `1.1.0` |
| Versión inicial (línea base) | `1.0.0` (2026-04-30) |
| Fecha última edición | 2026-04-30 |
| Líneas de PHP | ~1.500 |
| Clases | 11 |
| Templates | 4 |
| Hooks públicos (action+filter) | 5 |
| AJAX endpoints | 2 |
| Tabs de settings | 6 |
| Estados CPT | 4 |
| Capas de defensa en rename del carrito | 3 (gettext + JS DOM + CSS pseudo) |

---

## 24. Cambios introducidos en 1.1.0 (Fase 0 — Hardening)

Esta sección detalla qué cambió respecto a 1.0.0 y por qué. Todo lo descrito antes en este documento sigue siendo válido, salvo donde se explicite lo contrario.

### 24.1. Rename del carrito en triple capa

**Problema en 1.0.0:** `class-cart-overrides.php::rename_cart_strings()` usaba un mapa hardcoded de strings literales (`'Cart' => 'Mi cotización'`, etc.). Si una actualización de WooCommerce cambiaba tildes, mayúsculas, agregaba contexto o cambiaba dominio, el rename dejaba de funcionar y aparecía "Cart" en producción sin aviso.

**Solución 1.1.0:** redundancia intencional en tres capas que se aplican en paralelo.

#### Capa 1 — `gettext` con regex y cache

- `Glotracol_Quote_Cart_Overrides::$rename_rules` reemplaza el mapa literal por reglas con regex case-insensitive y tolerantes a tildes:
  ```php
  [ 'regex' => '/^cart$/i', 'to' => 'Mi cotización', 'domains' => [ 'woocommerce', 'default' ] ],
  [ 'regex' => '/^tu carrito (est[áa]) (actualmente )?vac[íi]o[\.\!]?$/iu', 'to' => 'Tu cotización aún no tiene productos.', 'domains' => [ 'woocommerce' ] ],
  ```
- Cache estática `self::$gettext_cache` evita reprocesar la misma string en hot path.
- Domain whitelist incluye ahora `default` además de `woocommerce`.
- Helper público `self_test_gettext()` para que el dashboard verifique runtime.

#### Capa 2 — JS DOM rewrite (`assets/js/cart-rename.js`)

- Nuevo archivo independiente cargado en todo el frontend.
- Recorre el DOM en `DOMContentLoaded` y al disparar eventos WC AJAX (`wc_fragments_loaded`, `updated_cart_totals`, `removed_from_cart`, `added_to_cart`).
- Config en variable `CONFIG` con dos secciones: `exactText` (reemplazo de texto completo del nodo) y `substring` (regex sobre text nodes descendientes).
- Si tras aplicar capas 1 y 2 algún heading sigue diciendo "Cart" / "Carrito", se le añade la clase `gloq-rename-failed` para que la Capa 3 (CSS) actúe.
- Walks text nodes con `TreeWalker` — preserva HTML, no rompe links.
- Idempotente: seguro de llamar múltiples veces.
- Expone `window.GlotracolQuote.cartRename` para debug.

#### Capa 3 — CSS pseudo-element (en `inline_hide_price_columns()`)

- Inyecta CSS solo en `is_cart()`.
- Headings con clase `.gloq-rename-failed` (añadida por Capa 2) se ocultan con `font-size:0` y se reemplazan vía `::after { content: "Mi cotización" }`.
- **Nunca actúa si Capas 1 y 2 funcionaron** — es red de seguridad pura.

### 24.2. `ensure_wc_cart_loaded()` defensivo

**Problema en 1.0.0:** los `require_once` directos sobre paths internos de WC (`wc-cart-functions.php`, etc.) podían generar fatal error si WooCommerce reorganizaba archivos.

**Solución 1.1.0:**

- `try/catch (\Throwable)` alrededor de cada `require_once`.
- Validación post-load: si `wc_get_cart_item_data_hash` sigue sin existir, log y return false sin abortar.
- Try/catch también en la inicialización de session/customer/cart.
- Cambio de retorno: `void` → `bool` (true si quedó operativo).
- Nuevo método privado `log_wc_load_failure()`:
  - Escribe a `error_log()`.
  - Persiste el último fallo en transient `glotracol_quote_wc_load_failure` (24h TTL) con `{ reason, wc_version, timestamp }`.
- El dashboard (sección 24.3) detecta este transient y muestra alerta al admin.

### 24.3. Nueva sección "Estado de compatibilidad" en el dashboard

Nuevo bloque full-width antes del grid 2-col, en `class-admin-dashboard.php`. Implementa los métodos:

- `compatibility_checks()` — devuelve 8 chequeos con `level ∈ {ok, warn, fail}`:
  1. WooCommerce versión vs mínimo (8.0).
  2. Hooks críticos disponibles (`woocommerce_proceed_to_checkout`, `woocommerce_get_price_html`, `wc_add_to_cart_message_html`, `woocommerce_add_to_cart`).
  3. `WC()->cart` accesible (informativo en admin).
  4. Path interno `wc-cart-functions.php` válido.
  5. Filtro gettext interceptando "Cart" → "Mi cotización" (runtime via `self_test_gettext()`).
  6. JS de rename registered/enqueueable.
  7. Carga manual de WC fallida en últimas 24h (consulta el transient).
  8. Plugins potencialmente redundantes (detecta YITH Catalog Mode).
- `summarize_compat()` — resume en headline global: "Todo en orden" / "Revisa los avisos" / "Atención requerida".

Estilos en `assets/css/admin.css` (sección "COMPATIBILITY CHECK CARD"): borde lateral coloreado por nivel global, badge en header, iconos por nivel.

### 24.4. Nuevos archivos en 1.1.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `assets/js/cart-rename.js` | ~3 KB | Capa 2 del rename |

### 24.5. Modificados respecto a 1.0.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión bumped a 1.1.0; constante `GLOTRACOL_QUOTE_VERSION` actualizada |
| `readme.txt` | Stable tag 1.1.0; changelog ampliado |
| `includes/class-cart-overrides.php` | Mapa literal → regex en `$rename_rules`; cache estática; helper `self_test_gettext()`; CSS pseudo en `inline_hide_price_columns` |
| `includes/class-plugin.php` | Registra y enqueua `glotracol-cart-rename` script en frontend completo |
| `includes/class-quote-form.php` | `ensure_wc_cart_loaded()` defensivo + helper `log_wc_load_failure()` |
| `includes/class-admin-dashboard.php` | Sección "Estado de compatibilidad" + métodos `compatibility_checks()` y `summarize_compat()` |
| `assets/css/admin.css` | Estilos de la nueva tarjeta compatibility |

### 24.6. Política de defensa para futuras fases

A partir de 1.1.0, todo nuevo feature debe cumplir:

1. Sin strings literales de WC sin fallback.
2. Sin dependencia de HTML interno de WC sin selector estable + fallback CSS.
3. Sin dependencia de prioridades específicas de hooks de terceros.
4. JS aislado bajo `window.GlotracolQuote.*` o IIFE, sin globals colisionables.
5. CSS namespaceado con `.gloq-` o `.glotracol-quote-`.
6. Toda llamada a `WC()` con check `if ( ! function_exists('WC') || ! WC() ) return;`.

---

## 25. Cambios introducidos en 1.2.0 (Fase A — Quick UX wins)

Esta sección describe los cuatro features visibles añadidos en la Fase A del roadmap v2.0.

### 25.1. F1 — Refinamiento del mensaje post-add-to-cart

**Antes:** los dos botones ("Ver mi cotización" + "Solicitar ahora") y el texto se renderizaban en una sola línea con `style="margin-left:6px"` inline. En móvil quedaban pegados y descoordinados.

**Cambios:**

- `Glotracol_Quote_Cart_Overrides::add_to_cart_message()` ahora envuelve todo en `<div class="gloq-msg-wrap">` con tres slots: badge ✓, texto y botonera.
- Estilos en `assets/css/quote.css` (sección "Wrapper del mensaje post-add-to-cart"):
  - Layout flex con gap consistente (14px desktop, 10px móvil).
  - Fondo verde claro `#f0fff4` con borde lateral verde `#28a745`.
  - Badge circular ✓ flex-shrink:0.
  - Botones diferenciados con clases semánticas (`gloq-msg-btn-secondary` / `gloq-msg-btn-primary`).
  - Responsive: en <600px botonera ocupa 100% y botones flex 1 1 auto.
- El estilo legacy del enlace AJAX `.added_to_cart.wc-forward` también recibió mejoras (mejor padding, gap, hover con shadow).

### 25.2. F2 — Pestaña Presentación + CTA en descripción

**Cambios:**

- Nuevo archivo `includes/class-product-tabs.php` con la clase `Glotracol_Quote_Product_Tabs`.
- Filtro `woocommerce_product_tabs` (prioridad 99):
  - Renombra el `title` de la tab `additional_information` a "Presentación" (preparación para Fase C donde se modelarán las presentaciones reales).
  - Wrappea la `callback` de la tab `description` con un closure que ejecuta el original y luego renderiza un CTA al final.
- CTA `gloq-desc-cta`:
  - Caja gradiente verde con borde lateral.
  - Título del producto + subtítulo dinámico (estado en cart / si ya está en cart muestra cuántos hay).
  - Botón principal: usa `apply_filters('woocommerce_loop_add_to_cart_link', ...)` para reusar el flujo AJAX nativo (toast, badge, etc).
  - Botón secundario "Ir al formulario →" si ya hay items en cart del mismo producto.
- Defensiva: si la callback original de WC falla (`\Throwable`), se loguea pero la página no rompe — solo se muestra el CTA.
- Registro en `class-plugin.php` y require en `glotracol-quote.php`.

### 25.3. F3 — Auto-clasificación por tamaño

**Lógica:**

- Helper `glotracol_quote_size_tag( $units_total, $skus_distinct )` en `helpers.php` que devuelve `small|medium|large` aplicando la **categoría más alta** entre el match por unidades y el match por SKUs distintos.
- Helper `glotracol_quote_size_tag_label()` para etiquetas en español.
- Defaults: medium ≥ 25 unidades / 5 SKUs · large ≥ 80 unidades / 12 SKUs (configurables en settings).
- Cálculo en `Glotracol_Quote_Form::handle_submit` después de crear el post:
  - `_glo_size_tag` = small/medium/large
  - `_glo_units_total` = suma de quantities
  - `_glo_is_large_alert` = 1 si large

**UI:**

- Nueva columna "Tamaño" en la list table del CPT (`Glotracol_Quote_CPT::columns` y `render_column`).
- Badge coloreado con clase `.glo-size-{small|medium|large}`. Si es large se añade icono 🔥 con animación de pulso (`.glo-size-alert`).
- Backfill on-the-fly: cotizaciones creadas antes de 1.2.0 (sin meta `_glo_size_tag`) se calculan en el momento de mostrar la columna.

### 25.4. F4 — Alerta para pedidos grandes

**Cambios:**

- Nuevo template `templates/email-admin-large.php`:
  - Color rojo `#dc3545` (en lugar del verde corporativo) para destacar urgencia.
  - Badge "⚡ Atención prioritaria" en el header.
  - Resumen prominente: unidades + SKUs + clasificación.
  - Teléfono del cliente con link directo `https://wa.me/<digits>`.
  - Tabla de productos con tfoot que muestra "Total unidades".
  - CTA "Sugerencia: contactar directamente por WhatsApp".
- `Glotracol_Quote_Emails::send_emails()` brancheada:
  - Si `_glo_is_large_alert === 1` Y `large_alert_enabled === 'yes'`:
    - Asunto con prefix `🔥 [GRANDE]`.
    - Template `email-admin-large.php` en lugar de `email-admin.php`.
    - Si hay `large_alert_email` configurado, se añade como recipient adicional.
    - Tipo en log: `admin-large` (en lugar de `admin`).

**Settings nuevos** (tab "Reglas"):

- `size_threshold_medium_units` (default 25)
- `size_threshold_large_units` (default 80, validado > medium)
- `size_threshold_medium_skus` (default 5)
- `size_threshold_large_skus` (default 12, validado > medium)
- `large_alert_enabled` (default `yes`)
- `large_alert_email` (opcional, valida is_email)

### 25.5. Archivos nuevos en 1.2.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `includes/class-product-tabs.php` | ~5 KB | F2 |
| `templates/email-admin-large.php` | ~4 KB | F4 |

### 25.6. Modificados respecto a 1.1.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 1.2.0; nuevo require `class-product-tabs.php` |
| `readme.txt` | Stable tag 1.2.0; changelog F1-F4 |
| `includes/class-plugin.php` | Instancia `Glotracol_Quote_Product_Tabs` |
| `includes/helpers.php` | Helpers `glotracol_quote_size_tag()` + `_label()`; defaults extendidos |
| `includes/class-cart-overrides.php` | `add_to_cart_message()` con wrapper flex |
| `includes/class-quote-form.php` | Cálculo de size_tag y units_total al guardar |
| `includes/class-quote-cpt.php` | Columna "Tamaño" en list table |
| `includes/class-quote-emails.php` | Branching admin-large + override de subject + CC opcional |
| `includes/class-admin-settings.php` | Nueva tab "Reglas" + sanitize de thresholds |
| `assets/css/quote.css` | Estilos `.gloq-msg-*`, `.gloq-desc-cta`, `.glo-size-*` |

### 25.7. Hooks/filters nuevos en 1.2.0

Ninguno público. Todos los cambios usan los hooks ya documentados en §14 más:

- `woocommerce_product_tabs` (filter, prioridad 99) — F2.

---

## 26. Cambios introducidos en 1.3.0 (Fase B — Data foundation)

Esta versión sienta toda la base de datos para los flujos de cotización con precios automáticos. Todavía NO conecta el flujo del cliente final (eso es Fase D), pero deja el plumbing listo y testeable.

### 26.1. F8 — CRM clientes B2B

**CPT nuevo: `glo_client`**

Visible bajo el menú "Cotizaciones" como submenú "Clientes B2B".

| Meta key | Tipo | Contenido |
|---|---|---|
| `_glo_client_nit` | string | NIT/Cédula (con guiones, espacios — se normaliza para indexar) |
| `_glo_client_name` | string | Razón social |
| `_glo_client_email` | string | Email principal |
| `_glo_client_phone` | string | Teléfono |
| `_glo_client_contact` | string | Persona de contacto |
| `_glo_client_city` | string | Ciudad/País |
| `_glo_client_active` | `'yes'` \| `'no'` | Si `no`, los precios B2B se ignoran y se usan precios públicos |
| `_glo_client_notes` | text | Notas internas |
| `_glo_client_pricing` | array `{ sku => precio_cop }` | Lista negociada |

**Lookup O(1) por NIT** vía option `glotracol_quote_nit_index`:
- Mantenido por hooks `save_post_glo_client`, `before_delete_post`, `wp_trash_post`, `untrash_post`.
- Reconstrucción manual: `Glotracol_Quote_Client_CPT::rebuild_full_index()` (la usa el importer).
- Helper público: `glotracol_quote_find_client_by_nit( $nit )` → post_id o 0. Devuelve 0 si el cliente está marcado inactivo.
- Normalización: `Glotracol_Quote_Client_CPT::normalize_nit($nit)` quita non-alphanumerics y lowercases. Devuelve null si <5 chars.

**Pantalla de edición** con 4 metaboxes:
- "Datos del cliente" (normal/high) — formulario con validación required.
- "Lista de precios negociados" (normal/high) — tabla repetidora con JS minimal para añadir/quitar filas.
- "Estado" (side/high) — checkbox activo/inactivo.
- "Historial de cotizaciones" (side/default) — últimas 10 cotizaciones del cliente con badges de status y tamaño.

**Columnas en list table**: Razón social, NIT, Email, Teléfono, Ciudad, Precios B2B (count), Cotizaciones (count + link), Estado.

### 26.2. F9 — Sistema de precios

**Clase `Glotracol_Quote_Pricing`** con métodos estáticos:

```php
Glotracol_Quote_Pricing::resolve( $sku, $client_id = 0 )
// → [ 'price' => int|null, 'source' => 'b2b'|'publico'|'pendiente' ]

Glotracol_Quote_Pricing::resolve_items( $items, $client_id = 0 )
// → [ 'items' => [...], 'all_priced' => bool, 'total' => int, 'sources' => [b2b,publico,pendiente] ]

Glotracol_Quote_Pricing::get_public_pricing()
// → [ sku => precio_cop, ... ]

Glotracol_Quote_Pricing::set_public_price( $sku, $price )
Glotracol_Quote_Pricing::replace_public_pricing( $array )
Glotracol_Quote_Pricing::merge_public_pricing( $array )  // [updated, inserted]
Glotracol_Quote_Pricing::count_public_skus()
```

**Resolución cascada**:
1. Si `client_id > 0` y `_glo_client_pricing[sku] > 0` → `b2b`.
2. Si `option('glotracol_quote_public_pricing')[sku] > 0` → `publico`.
3. Si no → `pendiente` (price = null).

**Pantalla "Precios"** (submenú bajo Cotizaciones):
- Tabla paginada (50/página) con búsqueda por SKU.
- Edición inline + checkbox "Borrar".
- Fila final para añadir nuevo SKU.
- Sección "Zona peligrosa" con botón para vaciar la lista entera (con confirm).
- Stats: "X SKUs con precio público" + filtro activo.

**Storage**: option `glotracol_quote_public_pricing` (autoload `false` para no inflar `wp_options`).

### 26.3. F10 — Importador CSV

**Clase `Glotracol_Quote_Importer`** con 4 importers:

| Tipo | Columnas requeridas | Columnas opcionales |
|---|---|---|
| `clientes` | `nit, razon_social` | `email, telefono, contacto, ciudad, activo, notas` |
| `precios_publicos` | `sku, precio` | — |
| `precios_b2b` | `nit, sku, precio` | — |
| `presentaciones` | `sku_producto, label` | `sku_variante, peso_g, precio_publico` |

**Parser `parse_csv()`**:
- Detecta y salta BOM UTF-8.
- Detecta automáticamente delimitador (`,`, `;`, `\t` — el más frecuente en la primera línea gana).
- Headers en lowercase con trim. Orden flexible. Columnas extra ignoradas.
- Devuelve `{ headers, rows, error }`. Cada row es asociativo + tiene `__line` para mensajes de error.
- Valida que las columnas requeridas estén presentes; si faltan, error con detalle.

**Cada importer** devuelve reporte: `{ inserted, updated, skipped, errors[] }`.

**Características clave**:
- `import_clients`: lookup por NIT existente → update si match, insert si no. Llama a `rebuild_full_index()` al final (no depende de hooks save_post).
- `import_b2b_pricing`: agrupa filas por NIT antes de aplicar (1 update_post_meta por cliente). Salta filas si el NIT no existe en CRM (con error claro).
- `import_public_pricing`: usa `Glotracol_Quote_Pricing::merge_public_pricing()` para aplicar atómicamente.
- `import_presentations`: agrupa por `sku_producto`, resuelve a `product_id` vía `wc_get_product_id_by_sku()`, guarda en meta `_glo_presentaciones`. (Preparación Fase C — la UI de edición y selector de presentación llega después.)

**UI del importador** (pantalla "Importar" bajo Cotizaciones):
1. **Step upload**: 4 cards seleccionables con descripción + botón "Descargar plantilla" + file input.
2. **Step preview**: tabla con primeras 20 filas + botón "Confirmar e importar X filas".
3. **Step report**: stats grid (inserted/updated/skipped/errors) + lista de errores detallada si hay.

**Seguridad**:
- Archivo subido se mueve a `wp-content/uploads/glotracol-import/<token>.csv` con `.htaccess Deny from all` + chmod 0640.
- `wp_schedule_single_event(+1h, 'gloq_importer_cleanup')` borra archivos huérfanos.
- Nonce, capability `manage_options`, validación de `move_uploaded_file`.

**Plantillas en `templates/csv/`**: `clientes.csv`, `precios_publicos.csv`, `precios_b2b.csv`, `presentaciones.csv` (con datos de ejemplo realistas).

### 26.4. Helpers nuevos en `helpers.php`

```php
glotracol_quote_find_client_by_nit( $nit ) → int     // post_id o 0
glotracol_quote_get_client_data( $client_id ) → array|null
glotracol_quote_format_price( $amount, $currency = 'COP' ) → string
```

### 26.5. Archivos nuevos en 1.3.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `includes/class-client-cpt.php` | ~6 KB | F8 CPT + index NIT |
| `includes/class-client-admin.php` | ~9 KB | F8 metaboxes y save |
| `includes/class-pricing.php` | ~4 KB | F9 resolver |
| `includes/class-pricing-admin.php` | ~7 KB | F9 pantalla |
| `includes/class-importer.php` | ~9 KB | F10 core |
| `includes/class-importer-admin.php` | ~13 KB | F10 UI |
| `templates/csv/clientes.csv` | <1 KB | Plantilla F10 |
| `templates/csv/precios_publicos.csv` | <1 KB | Plantilla F10 |
| `templates/csv/precios_b2b.csv` | <1 KB | Plantilla F10 |
| `templates/csv/presentaciones.csv` | <1 KB | Plantilla F10 |

### 26.6. Modificados respecto a 1.2.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 1.3.0; 4 nuevos requires |
| `readme.txt` | Stable tag 1.3.0; changelog detallado |
| `includes/class-plugin.php` | Instancia 4 nuevas clases |
| `includes/class-activator.php` | Registra CPT cliente al activar |
| `includes/helpers.php` | 3 helpers nuevos |
| `uninstall.php` | Borra CPT cliente, options nuevas, transients |

### 26.7. Smoke test verificado (en staging)

```
Clientes:    insert=2, update=0, skip=0  ✓
Index NIT:   2 entradas, lookup 900123456-7=ok ✓
Precios B2B: insert=5, update=0, skip=0  ✓
Resolver ALM-250 con NIT 900123456-7 → 12000 (b2b)   ✓
Resolver NUE-250 sin cliente         → 18000 (publico) ✓
Resolver INEXISTENTE                  → null (pendiente) ✓
```

### 26.8. Lo que aún NO hace v1.3.0

Aunque toda la base de datos está lista, el flujo del cliente final **todavía no usa los precios**. Eso llega en Fase D (F6/F7):

- El submit del formulario aún no resuelve precios ni cambia status según pricing_status.
- El email al cliente sigue sin precios (template `email-customer.php` actual).
- El selector de presentación al añadir al carrito no existe (Fase C).
- La columna "Tipo" cotización/pedido y el modal pre-submit no existen aún.

Esto es **intencional**: Fase B aisla el plumbing para que pueda probarse independientemente. Fase D conecta todo.

---

## 27. Cambios introducidos en 1.4.0 (Fase C — Presentaciones + carrito)

Esta versión modela las presentaciones (250g, 500g, 1kg, etc.) como meta del producto y las expone al cliente final en single-product y en el carrito. NO usa productos variables de WC — toda la capa es propia.

### 27.1. Modelo `_glo_presentaciones`

Meta del producto (`product`) con array de presentaciones:

```php
[
  [ 'idx' => 0, 'label' => '250 g', 'sku' => 'ALM-250', 'peso_g' => 250, 'precio_publico' => 15000 ],
  [ 'idx' => 1, 'label' => '500 g', 'sku' => 'ALM-500', 'peso_g' => 500, 'precio_publico' => 28000 ],
  ...
]
```

El SKU de la presentación es el **SKU efectivo** que el resolver usa para mirar precios públicos/B2B. Se importa vía CSV (Fase B `precios_publicos.csv` con `sku=ALM-500, precio=28000`).

### 27.2. C.1 — Metabox de presentaciones

Pestaña "Presentaciones" registrada en `woocommerce_product_data_tabs` (priority 65). Renderizada en `woocommerce_product_data_panels`. Save vía `woocommerce_admin_process_product_object`.

UI:
- Tabla repetidora con columnas `label / sku / peso (g) / precio público`.
- "+ Añadir presentación" agrega fila vía JS sin recargar.
- "×" para borrar (visual; al guardar las filas vacías se descartan).
- Si el SKU de variante queda vacío, se autogenera como `<sku_padre>-<slug(label)>`.

### 27.3. C.2 — Selector en single-product + comportamiento en loop

**Single-product**:
- Hook `woocommerce_before_add_to_cart_button` con priority 9 inserta `<select name="gloq_presentacion_idx">`.
- Default: primera presentación, o `?presentacion=N` si viene en URL (deep-link desde catálogo).
- El select va **dentro** del form de add-to-cart de WC, así que llega a `$_REQUEST['gloq_presentacion_idx']` automáticamente.

**Loop (catálogo)**:
- `loop_text` filter: si tiene presentaciones → "Ver presentaciones →"
- `loop_button_html` filter: si tiene presentaciones → reescribe el HTML como `<a href="<permalink>">Ver presentaciones →</a>` (sin AJAX), forzando al cliente a elegir presentación en single-product.
- Si está en cart, se conserva el badge "✓ N en tu cotización".

### 27.4. C.3 — Cart side

**Filters/actions añadidos en `class-cart-overrides.php`**:

| Hook | Método | Propósito |
|---|---|---|
| `woocommerce_add_cart_item_data` | `capture_presentation` | Captura `gloq_presentacion_idx` del POST y guarda `{idx, label, sku, peso_g, precio_publico}` en `cart_item_data['gloq_presentacion']` |
| `woocommerce_cart_id` | `cart_id_with_presentation` | MD5 con sufijo `\|gloq:<idx>` para que dos presentaciones del mismo producto sean items separados |
| `woocommerce_get_item_data` | `display_presentation_in_cart` | Renderiza "Presentación: 500 g (ALM-500)" debajo del nombre en cart y mini-cart |
| `woocommerce_get_cart_item_from_session` | `restore_presentation` | Persiste el `gloq_presentacion` al recargar carrito desde sesión |
| `woocommerce_cart_item_name` | `append_presentation_dropdown` | Si producto tiene ≥2 presentaciones, agrega `<select class="gloq-swap-presentation">` debajo del nombre en `/carrito` |
| `wp_ajax[_nopriv]_gloq_swap_presentation` | `ajax_swap_presentation` | Cambia la presentación de un item: remove + add con nuevo `gloq_presentacion`, preservando qty |

**JS** (`assets/js/quote.js`):
- Listener `change` en `.gloq-swap-presentation` → POST a `gloq_swap_presentation` con nonce → `location.reload()` para refrescar fragments WC.
- `GloqData` localizado ahora incluye `ajaxUrl` y `swapNonce`.

### 27.5. Integración con submit y form

- `Glotracol_Quote_Form::handle_submit()` lee `cart_item['gloq_presentacion']` y guarda en cada `_glo_items[]`:
  - `sku` = SKU efectivo (de la presentación si existe, del padre si no).
  - `sku_producto` = siempre el SKU del producto padre.
  - `presentacion_idx` y `presentacion_label`.
- Esto significa que el `Glotracol_Quote_Pricing::resolve($sku)` (Fase D próxima) recibirá el SKU correcto automáticamente.
- `templates/form.php` muestra `— 500 g` después del nombre del producto en la lista editable.

### 27.6. Helpers nuevos

```php
glotracol_quote_get_presentaciones( $product_or_id ) → array
glotracol_quote_get_presentacion( $product_id, $idx ) → array|null
```

Implementados como **fachada**: si Glotracol decide en el futuro migrar a productos variables WC nativos, basta cambiar la implementación de estos dos helpers sin tocar callers.

### 27.7. Archivos nuevos en 1.4.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `includes/class-presentations-admin.php` | ~5 KB | C.1 metabox |

### 27.8. Modificados respecto a 1.3.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 1.4.0; nuevo require |
| `readme.txt` | Stable tag 1.4.0; changelog |
| `includes/class-plugin.php` | Instancia `Glotracol_Quote_Presentations_Admin` |
| `includes/helpers.php` | 2 helpers de presentaciones |
| `includes/class-product-buttons.php` | Selector single + comportamiento loop con presentaciones |
| `includes/class-cart-overrides.php` | 6 hooks nuevos + AJAX swap |
| `includes/class-quote-form.php` | Items capturan SKU efectivo + presentacion_idx |
| `templates/form.php` | Display de presentación en lista editable |
| `assets/js/quote.js` | Listener de swap dropdown |
| `assets/css/quote.css` | Estilos del selector y dropdown |

### 27.9. Smoke test verificado

```
Presentaciones cargadas: 3 (250g/500g/1kg)
Helper get_presentacion(idx=1) → label="500 g" sku="ALM-TEST-500"
Resolver ALM-TEST-500 sin precios → null (pendiente)
Resolver ALM-TEST-500 con precio público → 28000 (publico)
```

### 27.10. Lo que aún falta (Fase D)

- El form de submit aún no usa el resolver de precios (los items se guardan sin `precio_unitario`).
- No hay modal "Cotización vs Pedido" antes del envío.
- La auto-respuesta con cotización formal no está implementada (templates email-customer-priced.php inexistentes).
- Status `glo-pending-prices` y `glo-auto-priced` no registrados aún.

---

## 28. Cambios introducidos en 1.5.0 (Fase D — Cotización vs Pedido + auto-respuesta)

Esta versión es donde el plugin **cobra vida**: todo el plumbing de Fases A-C se conecta y el cliente final empieza a recibir cotizaciones formales automáticas si su NIT está identificado o si la lista pública está cargada.

### 28.1. F6 — Modal pre-submit "Cotización vs Pedido"

**Frontend:**
- `templates/form.php` incluye:
  - Hidden field `<input type="hidden" name="gloq_type" id="gloq-type-input" value="">`
  - Botón submit cambió de "Enviar solicitud de cotización" a "Enviar solicitud →"
  - Modal HTML (`<div class="gloq-modal-overlay" id="gloq-type-modal">`) con dos botones grandes seleccionables.
- JS en `quote.js`:
  - `submit` listener intercepta el envío. Si `gloq_type` está vacío → preventDefault + abre modal.
  - Click en `.gloq-modal-option` setea el value y reenvía.
  - Cierre del modal con X / overlay click / Escape.
  - Body scroll-locked mientras está abierto.
- CSS en `quote.css` (~3 KB): overlay con fade-in, modal con slide-up animation, opciones con hover/focus elevado, responsive.

**Lo que el cliente ve:**
1. Llena formulario y hace clic en "Enviar solicitud →".
2. Aparece modal con header "¿Qué deseas enviar?" + intro explicativa.
3. Dos opciones: 🔍 "Es una cotización" / 🛒 "Es un pedido".
4. Al hacer clic, modal cierra y el form se envía con `gloq_type` populado.

### 28.2. F6 — Lógica del submit conectada a Pricing

`Glotracol_Quote_Form::handle_submit` ahora hace:

1. Sanitiza `gloq_type` ∈ {`quote`, `order`}, default `quote`.
2. **Lookup B2B**: `glotracol_quote_find_client_by_nit($fields['nit'])` → `$client_id` (0 si no match).
3. **Resuelve precios**: `Glotracol_Quote_Pricing::resolve_items($items, $client_id)` → cada item ahora tiene `precio_unitario`, `precio_origen` (b2b/publico/pendiente), `precio_subtotal`. Devuelve también `all_priced` y `total`.
4. **Determina pricing_status**:
   - `priced` si todos los SKUs tienen precio
   - `partial` si al menos uno falta pero hay precios disponibles para otros
   - `none` si no hay precios cargados en absoluto
5. **Decide initial_status del CPT**:
   - `glo-auto-priced` si `pricing=priced` Y `auto_respond_enabled=yes`
   - `glo-pending-prices` si `pricing=partial`
   - `glo-new` si nada (no debería pasar tras Fase B, pero es fallback)
6. Title del post incluye el tipo: `"Cotización #123 — Juan Pérez"` o `"Pedido #123 — Juan Pérez"`.
7. Metas nuevas:
   - `_glo_type` (quote|order)
   - `_glo_client_id` (post_id del CPT cliente o 0)
   - `_glo_pricing_status` (priced|partial|none)
   - `_glo_total` (entero en COP)
   - `_glo_pricing_sources` (counter de cuántos vinieron de cada fuente)

### 28.3. F6 — Templates de email diferenciados

**Subject prefixes** (acumulables):
- `🔥 [GRANDE]` — si size_tag=large + alert enabled
- `⚠️ [PENDIENTE PRECIOS]` — si pricing_status=partial
- `✅ [AUTO-COTIZADA]` — si pricing_status=priced y auto_respond
- `🛒 [PEDIDO]` — si type=order

Ejemplo: `🔥 [GRANDE] ⚠️ [PENDIENTE PRECIOS] 🛒 [PEDIDO] Nueva cotización #123 — Juan Pérez`

**Email al admin**:
- Si `pricing=partial` → `email-admin-pending-prices.php`: header amarillo, alerta "Acción requerida", tabla con SKUs pendientes resaltados en amarillo, badge "EN CRM" o "NO EN CRM" para el NIT, CTA "Completar precios y responder →".
- Si `is_large` → `email-admin-large.php` (legacy de Fase A).
- Si nada → `email-admin.php` (legacy v1.0).

**Email al cliente**:
- Si `pricing=priced` Y `auto_respond` → `email-customer-priced.php`:
  - Header verde gradiente con badge "Cotización formal" o "Confirmación de pedido"
  - Bloque "Cliente identificado" (si NIT match, fondo verde) o "Lista pública" (si no, fondo amarillo)
  - Tabla con precio unitario + badge B2B en items con precio negociado
  - Footer del subtotal: tfoot con TOTAL prominente verde
  - Wording adaptado a quote vs order
  - Validez de 7 días en footer
- Si `pricing=partial` → `email-customer.php` (confirmación simple sin precios, igual que v1.0).

### 28.4. F7 — Convertir cotización en pedido

**Metabox lateral** "Tipo, precios y conversión" (priority high) en pantalla de edit de glo_quote.

Muestra:
- Badge tipo (Cotización/Pedido)
- Cliente B2B asociado (link al CPT)
- Pricing status con badge coloreado
- Total formateado en COP
- Si type=quote: botón "→ Convertir en pedido"

**Modal de conversión:**
- Se abre con click. Tabla con todos los items, columna de precio unitario editable inline.
- JS recalcula subtotales y total en vivo (`change`/`input` listener).
- Botón "✓ Confirmar conversión".

**AJAX `gloq_convert_to_order`**:
- Recibe `post_id` y `prices[idx]`.
- Actualiza items con nuevos precios + recalcula subtotales y total.
- Setea `_glo_type = 'order'`, `_glo_pricing_status = priced|partial`, `_glo_converted_at = mysql time`.
- Si `all_priced`: status post → `glo-auto-priced`, dispara email auto-cotizado SOLO al cliente (no reenvía al admin que ya está allí).
- Si falta precio: status → `glo-pending-prices`.
- Renombra título: `Pedido #ID — Cliente`.
- `precio_origen` cambia de `pendiente` a `manual` cuando se completa desde el modal.

### 28.5. Statuses nuevos

A los 4 originales se suman:
- `glo-pending-prices` — Pendiente de precios (badge amarillo con borde)
- `glo-auto-priced` — Auto-cotizada (badge azul con borde)

Total: **6 statuses**. Registrados en `register_statuses_static`. El dropdown del editor de cotización lista los 6.

### 28.6. Columnas admin actualizadas

| Columna | Cambio |
|---|---|
| `title` | Renombrada a "ID" (más conciso) |
| `glo_type` | **NUEVA** — Badge Cotización/Pedido + ⚡ si auto-cotizada |
| `glo_total` | **NUEVA** — Total formateado en COP |
| `glo_phone` | Removida (ya está en metabox de detalles) |
| `glo_status` | Movida al final junto a date |

### 28.7. Settings nuevos (tab "Reglas")

- `auto_respond_enabled` — Toggle global. Si off, el cliente NUNCA recibe email con precios; siempre recibe confirmación simple.

### 28.8. Helpers nuevos

```php
glotracol_quote_type_label( $type ) → 'Cotización' | 'Pedido'
glotracol_quote_status_label( $slug ) → ahora soporta los 6 statuses
```

### 28.9. Archivos nuevos en 1.5.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `templates/email-customer-priced.php` | ~6 KB | F6 cotización formal con precios al cliente |
| `templates/email-admin-pending-prices.php` | ~5 KB | F6 alerta al equipo cuando faltan precios |

### 28.10. Modificados respecto a 1.4.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 1.5.0 |
| `readme.txt` | Stable tag 1.5.0 + changelog |
| `includes/helpers.php` | Helpers `type_label`, status_label extendido, default `auto_respond_enabled` |
| `includes/class-quote-cpt.php` | 2 statuses nuevos + columna Tipo + columna Total + render_column |
| `includes/class-quote-form.php` | Sanitize `gloq_type`, lookup B2B, resolve_items, determina initial_status, guarda metas nuevas |
| `includes/class-quote-emails.php` | Branching por pricing_status × type, prefixes acumulables, decide template |
| `includes/class-admin-meta-box.php` | Nuevo metabox "Tipo, precios y conversión" + modal F7 + AJAX `gloq_convert_to_order` |
| `includes/class-admin-settings.php` | Nuevo toggle "Auto-respuesta" en tab Reglas |
| `templates/form.php` | Hidden field gloq_type + modal HTML al final |
| `assets/js/quote.js` | Listener submit con modal + handlers |
| `assets/css/quote.css` | Estilos modal + nuevos statuses + tipo + auto-priced |

### 28.11. Smoke test verificado

```
Test 1 (B2B match): all_priced=true, total=85000 (5×8000 + 3×15000)
  - SKU-A: precio=8000 source=b2b sub=40000 ✓
  - SKU-B: precio=15000 source=b2b sub=45000 ✓
Test 2 (público + missing): all_priced=false, total=24000 (parcial)
  - Sources: b2b=0, publico=1, pendiente=1 ✓
Test 3: type_label(quote)=Cotización, type_label(order)=Pedido ✓
Test 4: status pending-prices y auto-priced funcionan ✓
```

### 28.12. Flujo end-to-end ya soportado

```
[Cliente arma carrito]
       │
       ▼
[Selector de presentación si aplica (Fase C)]
       │
       ▼
[/solicitar-cotizacion → llena form + click "Enviar"]
       │
       ▼
[MODAL: ¿Cotización o Pedido?]
       │
       ▼
[handle_submit]
  ├─ find_client_by_nit($nit)
  ├─ resolve_items($items, $client_id)
  └─ ¿all_priced?
       ├─ SÍ + auto_respond → status=glo-auto-priced
       │     ├─ email cliente: cotización formal con precios y total
       │     └─ email admin: ✅ [AUTO-COTIZADA]
       └─ NO → status=glo-pending-prices
              ├─ email cliente: confirmación simple
              └─ email admin: ⚠️ [PENDIENTE PRECIOS] con SKUs faltantes resaltados
       │
       ▼
[Admin abre cotización]
  ├─ Si type=quote → botón "Convertir en pedido"
  └─ Modal: editar precios faltantes → AJAX
        └─ Si todos completos → email cliente "Confirmación de pedido"
```

### 28.13. Lo que aún falta (Fase E)

- Reportes/dashboard avanzado: filtros por mes, top clientes, top SKUs, conversión rate, exports CSV.
- QA + smoke tests end-to-end sobre datos reales del cliente Glotracol.
- Documentación de operaciones para el equipo (Diana Ruiz).

---

## 29. Cambios introducidos en 2.0.0 (Fase E — Reportes + QA)

Esta es la versión final del roadmap v2.0. Cierra el ciclo con la pantalla de Reportes y el bump mayor a 2.0.0.

### 29.1. F11 — Pantalla "Reportes"

Submenu nuevo bajo Cotizaciones. Acceso solo `manage_options`.

**Filtros** (form GET con persistencia en URL):
- Rango de fechas (default: últimos 30 días)
- Tipo: cotización / pedido / todos
- Estado: cualquiera de los 6 statuses
- Pricing status: priced / partial / none
- Tamaño: small / medium / large
- Cliente B2B (si se llega vía link)

**Stats panel** (4 tarjetas):
1. **Total** — cantidad de cotizaciones+pedidos en el rango. Detalle: cuántas son cotizaciones vs pedidos.
2. **Monto cotizado** — suma de `_glo_total` (en COP). Detalle: cuántas con precios completos vs pendientes.
3. **Tasa de conversión** — porcentaje de pedidos sobre el total. Default 0% si solo hay cotizaciones.
4. **Pedidos grandes** — cuántos dispararon la alerta destacada de Fase A.

**Top tablas**:
- Top 5 clientes por monto cotizado total + count.
- Top 5 SKUs por unidades totales pedidas + número de veces que aparecieron.

**Tabla detallada**:
- Columnas: ID, Tipo, Cliente, Items, Tamaño, Total, Estado, Fecha.
- Paginada (25 por página).
- Botones de acción a editar cotización.

### 29.2. F11 — Export CSV

Botón "↓ Exportar CSV" en la barra de filtros. Genera archivo:
- Nombre: `glotracol-cotizaciones-YYYY-MM-DD-HHMMSS.csv`
- BOM UTF-8 al inicio para que Excel reconozca el encoding correctamente.
- 22 columnas en formato **expandido**: 1 fila por item del carrito (no 1 fila por cotización), permitiendo análisis cruzado en Excel/Google Sheets.
- Cabeceras: `ID, Fecha, Tipo, Estado, Pricing, NIT, Cliente, Razón social, Email, Teléfono, Empresa, Tamaño, Items, Unidades totales, Total, SKU, Producto, Presentación, Cantidad, Precio unitario, Subtotal, Origen precio`.
- Honra los filtros activos (no limita a 25, exporta todo el rango).
- Streaming directo a `php://output` con `fputcsv` — no carga toda la BD en memoria.

### 29.3. Implementación técnica

**Clase única**: `Glotracol_Quote_Reports` (~14 KB) con tres métodos clave:
- `parse_filters()` — saneamiento de query params con defaults
- `build_query_args()` — construye `WP_Query` args con meta_query y date_query
- `compute_stats()` — recorre todos los IDs (sin paginar) y agrega contadores
- `render()` — UI completa de la pantalla
- `export_csv()` — admin-post handler que stream CSV

**Performance**:
- compute_stats hace 1 query masiva por IDs + N queries por meta. Para los volúmenes esperados (cientos de cotizaciones por semana), esto es suficiente sin necesidad de tabla custom.
- Si en el futuro crece a >5000 cotizaciones/mes, se puede mover a tabla custom con denormalización.

### 29.4. Archivos nuevos en 2.0.0

| Archivo | Tamaño | Propósito |
|---|---|---|
| `includes/class-reports.php` | ~14 KB | F11 |

### 29.5. Modificados respecto a 1.5.0

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 2.0.0 + nuevo require |
| `readme.txt` | Stable tag 2.0.0 + changelog final |
| `includes/class-plugin.php` | Instancia `Glotracol_Quote_Reports` |
| `docs/ARQUITECTURA.md` | Sección §29 + actualización general |

### 29.6. Smoke test verificado

```
Stats últimos 30 días:
  total: 2 (las 2 cotizaciones legacy del staging)
  monto: 0 (legacy sin pricing)
  cotizaciones: 2 / pedidos: 0
  conversion: 0%
```

(Cuando entren cotizaciones nuevas con Fase D activa, los stats reflejarán los pricing reales.)

### 29.7. Resumen total v2.0

| Versión | Fase | Líneas PHP añadidas | Files nuevos |
|---|---|---:|---:|
| 1.0.0 → 1.1.0 | Hardening | ~250 | 1 (cart-rename.js) |
| 1.1.0 → 1.2.0 | Quick UX wins | ~280 | 2 |
| 1.2.0 → 1.3.0 | Data foundation | ~750 | 10 |
| 1.3.0 → 1.4.0 | Presentaciones | ~250 | 1 |
| 1.4.0 → 1.5.0 | Cotización vs Pedido | ~520 | 2 |
| 1.5.0 → 2.0.0 | Reportes | ~330 | 1 |
| **Total v1.0 → v2.0** | | **~2.380 LOC** | **17 files** |

Plugin pasó de **11 clases / 4 templates / 5 hooks públicos** (v1.0) a **18 clases / 7 templates / ~10 hooks** (v2.0) sin romper retrocompatibilidad.

---

## 30. Cambios introducidos en 2.0.1 (Bug fix + Logger)

### 30.1. Bug crítico del importador (token mixed-case)

**Síntoma reportado:** al subir un CSV en *Cotizaciones → Importar*, tras hacer clic en "Subir y previsualizar", aparecía la pantalla de error **"Archivo no encontrado o expirado"**. El archivo SÍ se había guardado correctamente, pero la pantalla de preview no lo encontraba.

**Causa raíz:** en `Glotracol_Quote_Importer_Admin::handle_preview()` se generaba el token con `wp_generate_password( 16, false, false )`, que devuelve caracteres alfanuméricos **incluyendo mayúsculas** (ej. `aBc123XyZ789DEFG`). El archivo se guardaba como `<token>.csv` preservando el case.

Luego, en el redirect, el token llegaba a `?token=aBc123...`. En `render_preview()`, al leer con `sanitize_key()` (que lowercasea por contrato de WP), el token se convertía a `abc123...` → lookup en `abc123....csv` → archivo no encontrado.

**Fix** (`class-importer-admin.php` línea ~215):

```php
// ANTES
$token = wp_generate_password( 16, false, false );

// AHORA
$token = bin2hex( random_bytes( 8 ) ); // 16 chars hex, siempre lowercase
```

`bin2hex()` devuelve solo `0-9a-f` que pasa por `sanitize_key()` sin cambios.

### 30.2. Sistema de logs centralizado

**Motivación:** las llamadas a `error_log()` estaban dispersas (4 lugares) sin estructura común, los warnings del importador no quedaban auditables, y los `_glo_email_log` por cotización requerían abrir cada cotización individualmente. Diana Ruiz pidió un sistema de logs unificado.

#### 30.2.1. Clase `Glotracol_Quote_Logger`

API estática, métodos por nivel:

```php
Glotracol_Quote_Logger::debug( $cat, $msg, $context = [] );
Glotracol_Quote_Logger::info(  $cat, $msg, $context = [] );
Glotracol_Quote_Logger::warn(  $cat, $msg, $context = [] );
Glotracol_Quote_Logger::error( $cat, $msg, $context = [] );

Glotracol_Quote_Logger::get_entries( $limit, $level_filter, $cat_filter );
Glotracol_Quote_Logger::counts();      // [ total, debug, info, warn, error ]
Glotracol_Quote_Logger::categories();  // [ cat_slug => count, ... ]
Glotracol_Quote_Logger::clear();
```

**Storage:** option `glotracol_quote_log` (autoload `false`) como array rolling de **500 entradas máximo**. Append con `array_unshift` + trim. Sin cron, sin tabla custom — suficiente para los volúmenes esperados.

**Cada entrada:**
```php
[
  'ts'      => 'Y-m-d H:i:s',
  'level'   => 'debug|info|warn|error',
  'cat'     => 'import|email|webhook|wc_compat|quote_created|conversion|logger',
  'msg'     => 'Texto human-readable',
  'context' => [ 'key' => 'value', ... ],  // serializable, se renderiza JSON pretty
  'user'    => $current_user_id,
]
```

**Mirror a debug.log:** entradas de `warn` y `error` también pasan por `error_log()` con prefix `[Glotracol WARN][cat] ...` para visibilidad en `WP_DEBUG_LOG`.

**Hook público:** `do_action( 'glotracol_quote_logged', $entry )` para que otros plugins se suscriban.

#### 30.2.2. Pantalla viewer `Glotracol_Quote_Logger_Admin`

Submenu nuevo: **Cotizaciones → Logs** (capability `manage_options`).

UI:
- **Header** con descripción y mensaje flash si se acaba de vaciar.
- **Stats inline**: 5 píldoras coloreadas (total / errors / warnings / info / debug).
- **Filtros** (form GET): nivel, categoría (lista dinámica de las que existen en log), límite (50/100/200/500).
- **Botón rojo "Vaciar log"** con confirm.
- **Tabla**: Fecha · Nivel (badge color) · Categoría · Mensaje · Detalle (`<details>` con context JSON pretty-printed expandible por entrada).

#### 30.2.3. Eventos integrados en este release

| Categoría | Origen | Niveles |
|---|---|---|
| `import` | Subida CSV (info), MIME inesperado (warn), move_uploaded_file fail (error), reporte final (info/warn según errors), redirect_back (warn), file not found en preview con detalles del dir (error) | info/warn/error |
| `email` | Email admin enviado/fallado (con template usado), email cliente enviado/fallado | info/error |
| `webhook` | OK/FAIL con status_code, reintento programado | info/error |
| `wc_compat` | `ensure_wc_cart_loaded()` falló, callback de WC tab description rompió | warn/error |
| `quote_created` | Cada cotización creada con type, client_id, pricing_status, total, items, NIT, size_tag | info |
| `conversion` | Conversión cotización → pedido (all_priced, new_total) | info |
| `logger` | Vaciado del log por admin | info |

Los `error_log()` dispersos se mantienen como mirror de los logs warn/error (línea 75-78 del logger), no se eliminaron — es defensa en profundidad.

#### 30.2.4. Integración con dashboard

- **Card nuevo "Logs"** en el grid de accesos rápidos (4ª posición, reemplazando el anterior "Configuración" que se movió al hero). Muestra total de entradas y, si hay errores, muestra "X errores" con tipo `gloq-quick-alert` (rojo).
- **Banner rojo** entre el hero y los accesos rápidos cuando hay errores recientes en log: `⚠ Hay X errores recientes en el log [Revisar log →]`.

#### 30.2.5. Archivos nuevos en 2.0.1

| Archivo | Tamaño | Propósito |
|---|---|---|
| `includes/class-logger.php` | ~3 KB | API del logger |
| `includes/class-logger-admin.php` | ~6 KB | Pantalla viewer |

#### 30.2.6. Modificados en 2.0.1

| Archivo | Cambio |
|---|---|
| `glotracol-quote.php` | Versión 2.0.1 + 2 nuevos requires |
| `readme.txt` | Stable tag 2.0.1 + changelog |
| `includes/class-plugin.php` | Instancia `Glotracol_Quote_Logger_Admin` |
| `includes/class-importer-admin.php` | **Bug fix token** + 5 puntos de log |
| `includes/class-quote-form.php` | Logger en `log_wc_load_failure` y al crear cotización |
| `includes/class-quote-emails.php` | Logger por cada email enviado/fallado |
| `includes/class-webhook.php` | Logger por dispatch + reintento |
| `includes/class-product-tabs.php` | Logger en catch de Throwable |
| `includes/class-admin-meta-box.php` | Logger en conversión cotización→pedido |
| `includes/class-admin-dashboard.php` | Card "Logs" + banner alerta + variables `$logs_url, $log_counts` |
| `assets/css/admin.css` | Estilos `.gloq-log-alert`, `.gloq-quick-alert` |
| `uninstall.php` | Borra `glotracol_quote_log` |

### 30.3. Diagnóstico end-to-end ahora disponible

Si vuelve a aparecer el error original "Archivo no encontrado o expirado", la pantalla de logs registrará automáticamente:

```
[error] import :: render_preview: archivo no encontrado
{
  "token": "9a2d7cf2a8cc0725",
  "expected_path": "/wp-content/uploads/glotracol-import/9a2d7cf2a8cc0725.csv",
  "temp_dir": "/wp-content/uploads/glotracol-import",
  "existing_files": ["abc123.csv", "def456.csv"]
}
```

Esto permite ver al instante si fue mismatch de path, archivo borrado, expiración del cron, o si el dir entero se perdió.

### 30.4. Verificación final

```
Token: 9a2d7cf2a8cc0725 (lowercase hex)
Tras sanitize_key(): 9a2d7cf2a8cc0725 (sin cambios) ✓

Logger smoke test:
  Counts: {total:4, debug:1, info:1, warn:1, error:1} ✓
  Categories: {import:1, test:3} ✓
  Filtro por nivel: 1 entrada ✓
  Filtro por categoría: 3 entradas ✓
  Mirror a error_log() para warn/error: confirmed ✓
```

---

*Documento generado para Glotracol — Global Trading de Colombia. Plugin desarrollado por Neracosu (https://neracosu.com/).*
