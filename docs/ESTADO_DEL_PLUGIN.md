# Estado del plugin — Glotracol Cotizador

**Versión:** 2.2.0 · **Fecha del snapshot:** 2026-06-03

Plugin propio que convierte WooCommerce en un sistema de solicitud de cotizaciones (RFQ): reemplaza el checkout por un formulario que arma la lista de productos y la envía al equipo comercial de Glotracol y al cliente, con resolución de precios público/B2B, CRM, reportes e integraciones.

Este documento es un resumen de estado para arrancar la siguiente iteración de desarrollo. Para el detalle de cada subsistema (flujo público completo, capas del rename del carrito, payload del webhook, etc.) ver `docs/ARQUITECTURA.md`; aquí no se duplica.

---

## Metadatos y compatibilidad

| Campo | Valor |
|---|---|
| Versión | 2.2.0 |
| Desarrollado por | [Neracosu](https://neracosu.com/) para [eagencia](https://www.eagencia.co/) |
| Cliente final | Glotracol — Global Trading de Colombia |
| Licencia | GPL-3.0 |
| Requiere WordPress | 6.0+ |
| Requiere PHP | 7.4+ |
| Requiere WooCommerce | 8.0+ |
| WC tested up to | 10.7 |
| HPOS (`custom_order_tables`) | Declarado compatible |
| Cart/Checkout Blocks (`cart_checkout_blocks`) | Declarado compatible |
| Text domain | `glotracol-quote` (Domain Path `/languages`) |

Las declaraciones de HPOS y bloques son nominales: el plugin no crea órdenes WC ni usa endpoints de los bloques de cart/checkout. Se declaran para evitar el aviso de "incompatible" en el listado de plugins.

---

## Madurez

El plugin está operativo en producción (glotracol.neracosu.com). Completó el roadmap v2.0 (RFQ + CRM B2B + pricing público/B2B + reportes), entregado por fases hasta la 2.0.0. La 2.0.1 corrigió un bug del importador (token con mayúsculas que rompía la resolución del archivo) y añadió un sistema de logs centralizado. La 2.0.2 aplicó ocho correcciones derivadas de un code-review (autorización a nivel de objeto, neutralización de inyección de fórmulas en CSV, eliminación de patrones N+1, robustez del importador y del webhook). La 2.0.3 unificó la UI del panel admin en `assets/css/admin.css` y `assets/js/admin.js`, añadió estados de carga y empty states, y retiró los emojis de panel, asuntos de email, plantillas y documentación. La 2.1.0 añade carrito flotante persistente (mini-cotización), herencia de tipografía y color del kit global de Elementor (con toggle en Apariencia), semáforo por peso real (pequeño/grande/toneladas con umbrales configurables) y webhook enriquecido con re-disparo al convertir en pedido, listo para GoHighLevel.

---

## Arquitectura por clases

Bootstrap en `glotracol-quote.php` (constantes, requires, hooks de activación, declaración HPOS/Blocks). `helpers.php` aporta la librería de funciones globales (settings, placeholders, IP, templates, resolución de presentaciones, clasificación por tamaño, lookup de clientes por NIT). Las 24 clases viven en `includes/`:

| Clase | Archivo | Responsabilidad |
|---|---|---|
| `Glotracol_Quote_Plugin` | `class-plugin.php` | Singleton de arranque; instancia las clases y encola los assets |
| `Glotracol_Quote_Activator` | `class-activator.php` | Crea páginas form/thanks y settings, registra CPT/estados, flush rewrite |
| `Glotracol_Quote_Logger` | `class-logger.php` | Log centralizado por niveles/categorías en option rolling (últimas 500) |
| `Glotracol_Quote_Logger_Admin` | `class-logger-admin.php` | Pantalla "Logs" con filtros por nivel/categoría y vista detallada |
| `Glotracol_Quote_Rate_Limit` | `class-rate-limit.php` | Límite de envíos por IP vía transients |
| `Glotracol_Quote_Product_Buttons` | `class-product-buttons.php` | Reemplaza "añadir al carrito" y oculta precios en catálogo y producto |
| `Glotracol_Quote_Product_Tabs` | `class-product-tabs.php` | Renombra la pestaña "Información adicional" a "Presentación" y añade CTA en Descripción |
| `Glotracol_Quote_Cart_Overrides` | `class-cart-overrides.php` | Oculta precios y checkout en el carrito, rename de strings, swap de presentación |
| `Glotracol_Quote_CPT` | `class-quote-cpt.php` | Registro del CPT `glo_quote`, estados y columnas de la list table |
| `Glotracol_Quote_Client_CPT` | `class-client-cpt.php` | Registro del CPT `glo_client` (CRM B2B) e índice NIT |
| `Glotracol_Quote_Client_Admin` | `class-client-admin.php` | Admin del CRM: datos, precios negociados, estado activo/inactivo |
| `Glotracol_Quote_Pricing` | `class-pricing.php` | Resolución de precio por SKU contra lista pública u override B2B |
| `Glotracol_Quote_Pricing_Admin` | `class-pricing-admin.php` | Pantalla "Precios" con búsqueda, paginación y edición inline |
| `Glotracol_Quote_Importer` | `class-importer.php` | Parser CSV de 4 hojas (clientes, precios públicos, precios B2B, presentaciones) |
| `Glotracol_Quote_Importer_Admin` | `class-importer-admin.php` | UI del importador: plantillas, preview y reporte de resultados |
| `Glotracol_Quote_Presentations_Admin` | `class-presentations-admin.php` | Pestaña "Presentaciones" en la edición de producto WC |
| `Glotracol_Quote_Form` | `class-quote-form.php` | Shortcodes form/thanks, submit del formulario y AJAX de cantidad |
| `Glotracol_Quote_Emails` | `class-quote-emails.php` | Envío de email al admin y al cliente con plantillas HTML |
| `Glotracol_Quote_SMTP` | `class-smtp.php` | Override de PHPMailer, detección de SMTP externo, test de envío |
| `Glotracol_Quote_Webhook` | `class-webhook.php` | Despacho async del webhook firmado HMAC con reintentos por backoff |
| `Glotracol_Quote_Reports` | `class-reports.php` | Pantalla "Reportes": filtros, stats, tops y export CSV |
| `Glotracol_Quote_Admin_Meta_Box` | `class-admin-meta-box.php` | Metaboxes de la cotización y conversión cotización → pedido |
| `Glotracol_Quote_Admin_Settings` | `class-admin-settings.php` | Página de ajustes con sus pestañas y el sanitize |
| `Glotracol_Quote_Admin_Dashboard` | `class-admin-dashboard.php` | Pantalla "Inicio": stats, checklist, estado de compatibilidad, guía |

---

## Modelo de datos

Dos custom post types, ambos privados (`public=false`, `show_ui=true`):

- **`glo_quote`** — cada solicitud de cotización o pedido.
- **`glo_client`** — ficha de cliente B2B (CRM), con índice por NIT en option para lookup O(1).

### Estados de `glo_quote`

| Estado | Significado |
|---|---|
| `glo-new` | Cotización recién recibida, sin procesar |
| `glo-pending-prices` | Falta precio para al menos un SKU; requiere intervención comercial |
| `glo-auto-priced` | Todos los SKUs tienen precio; el cliente recibió la cotización formal automática |
| `glo-processing` | En gestión por el equipo comercial |
| `glo-responded` | Respondida al cliente |
| `glo-closed` | Cerrada |

### Meta keys principales de `glo_quote`

| Meta key | Contenido |
|---|---|
| `_glo_qid` | Token público (16 chars) para el deep-link a la página de gracias |
| `_glo_customer_name` / `_email` / `_phone` / `_company` / `_nit` / `_city` / `_message` | Datos del cliente |
| `_glo_items` | Lista de items `[ {product_id, name, sku, quantity}, ... ]` |
| `_glo_type` | `quote` o `order` (cotización vs pedido) |
| `_glo_client_id` | ID del `glo_client` vinculado si hubo match por NIT |
| `_glo_pricing_status` | `priced` / `partial` / `none` según resolución de precios |
| `_glo_pricing_sources` | Origen del precio por SKU (público / B2B) |
| `_glo_size_tag` | `small` / `medium` / `large` (clasificación automática) |
| `_glo_units_total` | Suma de cantidades |
| `_glo_is_large_alert` | `1` si dispara la alerta de pedido grande |
| `_glo_total` | Total calculado |
| `_glo_presentaciones` | Presentaciones asociadas a los items |
| `_glo_meta` | `{ ip, user_agent, referer, lang, timestamp }` |
| `_glo_email_log` | Log apilado de envíos `{type, to, sent_at, success}`; el webhook también se registra aquí |
| `_glo_webhook_attempts` | Contador de intentos del webhook (control del backoff) |
| `_glo_converted_at` | Timestamp de conversión a pedido |

`glo_client` usa el prefijo `_glo_client_*` (`_nit`, `_name`, `_email`, `_phone`, `_contact`, `_city`, `_notes`, `_active`, `_pricing`).

---

## Capacidades funcionales

- **RFQ.** Reemplaza "Añadir al carrito" por "Añadir a la cotización", oculta precios en todo el frontend y bloquea el checkout. El cliente arma su lista, llena sus datos y envía; se crea una `glo_quote`.
- **CRM B2B.** CPT `glo_client` con datos de la empresa, precios negociados, estado activo/inactivo e índice por NIT. Al enviar una cotización, si el NIT coincide se vincula el cliente.
- **Pricing público / B2B.** Resolución por ID de producto (con respaldo por SKU para datos antiguos): precio de lista pública (meta privado `_glo_price`) u override negociado del cliente por ID. Calcula total y marca `pricing_status`.
- **Importador CSV.** Clientes, precios públicos, precios B2B, presentaciones y **Precios del catálogo (por ID)** — este último acepta el export de WooCommerce (`ID, Nombre, Peso, Precio normal, Disponibilidad`), guarda el precio público en `_glo_price`, soporta modo B2B (cliente elegido al subir) y sincronización opcional de stock. Plantillas descargables, detección de delimitador y BOM, preview de 20 filas y reporte de inserted/updated/skipped/errors.
- **Presentaciones.** Pestaña en la edición de producto (label / SKU variante / peso / precio). En el frontend se eligen como items separados en el carrito, con swap inline por AJAX. El SKU efectivo de la presentación alimenta al resolver de precios.
- **Cotización vs pedido + auto-respuesta.** Modal pre-submit donde el cliente elige tipo. Si todos los SKUs tienen precio, se envía cotización/confirmación formal automática. Desde el admin se puede convertir una cotización en pedido editando precios faltantes inline.
- **Emails + SMTP.** Doble email (equipo + cliente) con plantillas HTML responsive, multi-destinatario y BCC. Asuntos con prefijos según contexto. Integración SMTP propia con detección de plugins SMTP externos y test de envío.
- **Webhook HMAC.** Despacho asíncrono (cron) con firma HMAC-SHA256 y reintentos por backoff (1m / 5m / 15m), listo para Make/Zapier/n8n/Goja.
- **Reportes + export.** Filtros por fecha, tipo, estado, pricing_status y tamaño; tarjetas de stats, top 5 clientes y top 5 SKUs, tabla paginada y export CSV (BOM UTF-8) honrando los filtros.
- **Logger.** Sistema central por niveles (debug/info/warn/error) y categorías; viewer en el admin con filtros y banner de alerta en el dashboard ante errores recientes.
- **Anti-spam.** Nonce + honeypot + rate limit por IP en el submit del formulario.
- **Carrito flotante persistente.** Widget mini-cotización visible en todo el sitio; persiste entre páginas sin recargar.
- **Herencia de tipografía y color de Elementor.** Toggle en Apariencia para que el plugin adopte la fuente y el color de marca del kit global de Elementor.
- **Semáforo por peso real.** Clasificación en pequeño/grande/toneladas basada en el peso real de los items, con umbrales configurables en Ajustes > Reglas.
- **Webhook enriquecido + re-disparo en conversión.** El payload incluye tipo, total, precios por línea, peso y datos del cliente B2B; se re-dispara automáticamente al convertir la cotización en pedido. Listo para GoHighLevel.

---

## Configuración

Option principal `glotracol_quote_settings` (array). Pestañas de la página de ajustes:

- **General** — destinatarios internos, BCC, nombre y email del remitente.
- **Emails** — asuntos e intros de los emails al admin y al cliente.
- **Formulario** — intro del formulario, texto de términos y mensaje de la página de gracias.
- **SMTP** — activación, host, puerto, encriptación, usuario, contraseña, From propio y test de envío; muestra aviso si detecta un plugin SMTP externo.
- **Integraciones** — URL y secret del webhook.
- **Reglas** — umbrales de clasificación por tamaño (unidades y SKUs para medium/large), alerta de pedido grande y su destinatario opcional, toggle de auto-respuesta con precios.
- **Avanzado** — rate limit por hora y borrado de datos al desinstalar.

La contraseña SMTP conserva su valor anterior si el campo entra vacío (la UI nunca expone la guardada).

---

## Puntos de integración

**Shortcodes**

- `[glotracol_quote_form]` — formulario de cotización (lista editable de items + datos del cliente).
- `[glotracol_quote_thanks]` — página de gracias; resuelve la cotización por `?qid=<token>`.

**Actions**

- `glotracol_quote_before_save` — antes de insertar la cotización.
- `glotracol_quote_created` ( `$quote_id, $payload` ) — tras guardar el CPT y sus metas, antes del redirect.
- `glotracol_quote_logged` ( `$entry` ) — cada vez que el logger registra una entrada.

**Filters**

- `glotracol_quote_email_admin_body` — HTML del email al admin.
- `glotracol_quote_email_customer_body` — HTML del email al cliente.
- `glotracol_quote_webhook_payload` — array del webhook antes de serializar.

**Endpoints AJAX**

| Action | Auth | Descripción |
|---|---|---|
| `gloq_update_qty` | nopriv + priv | Actualiza la cantidad de un item del carrito desde el formulario |
| `gloq_swap_presentation` | nopriv + priv | Cambia la presentación de un item sin recargar |
| `gloq_convert_to_order` | priv (capacidad por objeto) | Convierte una cotización en pedido editando precios inline |
| `gloq_smtp_test` | priv (`manage_options`) | Envía un email de prueba SMTP |

**Plantillas sobreescribibles** — `form.php`, `thanks.php`, `email-admin.php`, `email-customer.php`, `email-admin-large.php`, `email-admin-pending-prices.php`, `email-customer-priced.php`. Se resuelven primero desde `wp-content/themes/<tema>/glotracol-quote/<archivo>` y luego desde el plugin.

---

## Postura de seguridad

- **Nonce + capacidad en endpoints admin.** Todos los endpoints AJAX y POST verifican nonce; los de panel exigen capacidad.
- **Capacidad a nivel de objeto en convertir-en-pedido.** `ajax_convert_to_order` valida `current_user_can('edit_post', $post_id)` en vez del genérico `edit_posts`, evitando que un autor/colaborador convierta cotizaciones ajenas, reescriba precios o dispare el reenvío de email al cliente.
- **Neutralización de inyección de fórmulas en CSV.** El export antepone apóstrofo a las celdas que empiezan por `= + - @`, evitando que Excel/Sheets ejecuten fórmulas inyectadas vía campos del cliente.
- **Sanitización de entrada en el importador.** Razón social y NIT se sanitizan al guardar; el resolver de pricing valida `class_exists` antes de la llamada estática.
- **Anti-spam.** Nonce + honeypot + rate limit por IP en el submit público.
- **Webhook firmado.** HMAC-SHA256 en la cabecera `X-Glotracol-Signature`.

Las cuatro primeras se introdujeron o reforzaron en la 2.0.2 a partir del code-review.

---

## Rendimiento

- **Primeado de caché.** Reportes (stats y export) y el resumen del dashboard primean post/meta cache en una sola consulta (`_prime_post_caches`) para eliminar el patrón N+1.
- **Webhook asíncrono.** El despacho se programa en cron (no bloquea el redirect del submit) y reintenta por backoff (1m / 5m / 15m) en vez de un único reintento.
- **Logger acotado.** Storage rolling de 500 entradas con el tamaño del `context` limitado para no inflar el option; la option de logs no usa autoload.

---

## Limitaciones conocidas / backlog

- **Sin archivos i18n.** El text domain `glotracol-quote` está declarado y `/languages` existe, pero no se distribuyen `.pot`/`.po`/`.mo`. El rename del carrito vía `gettext` pisa el dominio `woocommerce`, no es traducible por el dominio del plugin.
- **Contenido solo en español.** Strings hardcoded; no hay soporte multilingüe real.
- **Concurrencia del logger.** Al basarse en una única option, bajo alta carga simultánea pueden perderse entradas (aceptable para logs, no para datos transaccionales).
- **`smtp_password` en texto plano** en `wp_options`, sin cifrado (práctica habitual en plugins SMTP de WP, conviene tenerlo presente).
- **Sin reCAPTCHA / Turnstile.** El anti-spam se apoya en nonce + honeypot + rate limit.
- **Sin migración versionada.** No se persiste la versión del plugin en una option; no hay routine de upgrade entre versiones.

Mejoras de UX ya implementadas en 2.0.3: assets de admin unificados en todas las pantallas del plugin, spinner/estado en el test de SMTP y en la conversión a pedido, y empty states con llamada a la acción en Precios, Logs y Reportes.

---

## Historial de versiones

| Versión | Resumen |
|---|---|
| 1.0.0 | Release inicial: cotizador RFQ básico (form, CPT, doble email, webhook, anti-spam). |
| 1.1.0 | Hardening: rename del carrito en triple capa, `ensure_wc_cart_loaded()` defensivo, estado de compatibilidad en el dashboard. |
| 1.2.0 | UX wins: mensaje post-add-to-cart refinado, CTA en Descripción, auto-clasificación por tamaño, alerta de pedidos grandes. |
| 1.3.0 | Data foundation: CRM clientes B2B, sistema de precios público + B2B, importador CSV de 4 hojas. |
| 1.4.0 | Presentaciones: pestaña en producto, selector en single-product, swap inline en el carrito, SKU efectivo de la variante. |
| 1.5.0 | Cotización vs pedido: modal pre-submit, conexión al resolver de precios, estados pending-prices/auto-priced, emails diferenciados, convertir en pedido desde admin. |
| 2.0.0 | Cierre del roadmap v2.0: pantalla de Reportes con filtros, stats, tops y export CSV. |
| 2.0.1 | Fix del importador (token mixed-case) + sistema de logs centralizado con viewer y banner de alerta. |
| 2.0.2 | Ocho correcciones de code-review: autorización por objeto, CSV injection, eliminación de N+1, robustez de importador/webhook/logger. |
| 2.0.3 | UI de admin unificada, estados de carga y empty states, retiro de emojis en panel/emails/plantillas/docs. |
| 2.1.0 | Carrito flotante persistente, herencia de tipografía/color de Elementor, semáforo por peso (toneladas), webhook enriquecido + re-disparo en conversión (GHL). |
| 2.1.2 | Fixes del carrito flotante: la "X" elimina correctamente (refresco directo por AJAX), badge sin rojo y campo de cantidad acotado. |
| 2.2.0 | Precios por ID de producto: importador "Precios del catálogo (por ID)" (export WC), precio público en `_glo_price` (sin tocar `regular_price`), tarifas B2B por ID, sincronización opcional de stock; resolver por ID con respaldo SKU; pantalla "Precios" reorientada a producto. |
