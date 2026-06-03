=== Glotracol Cotizador ===
Contributors: neracosu
Author URI: https://neracosu.com/
Tags: woocommerce, quote, request-a-quote, b2b
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.0.2
WC requires at least: 8.0

Convierte WooCommerce en un sistema de solicitud de cotizaciones (RFQ) sin checkout ni pago.

== Description ==

Plugin propio para Glotracol (Global Trading de Colombia). Reemplaza el flujo de checkout/pago de WooCommerce por un formulario de solicitud de cotización. El cliente arma su lista de productos + cantidades, envía sus datos (nombre, email, teléfono, empresa, NIT, ciudad, mensaje), y recibe una confirmación de recepción. El equipo comercial recibe la solicitud por email con todos los datos y responde manualmente con la cotización formal.

= Características =

* Reemplaza "Añadir al carrito" por "Solicitar cotización" en todo el catálogo.
* Oculta precios en catálogo, producto individual, carrito y emails (decisión de negocio).
* Bloquea el acceso a /finalizar-compra (checkout) y redirige al formulario de cotización.
* Página `/solicitar-cotizacion` autocreada con shortcode `[glotracol_quote_form]`.
* Página `/cotizacion-enviada` autocreada con shortcode `[glotracol_quote_thanks]`.
* CPT propio `glo_quote` con estados (Nueva / En proceso / Respondida / Cerrada) y panel admin.
* Doble email: confirmación al cliente + notificación al equipo (configurable).
* Webhook con HMAC-SHA256 listo para Make/Zapier/n8n/Goja (integración WhatsApp).
* Hooks expuestos: `glotracol_quote_before_save`, `glotracol_quote_created`, `glotracol_quote_email_admin_body`, `glotracol_quote_email_customer_body`, `glotracol_quote_webhook_payload`.
* Anti-spam: nonce + honeypot + rate limit por IP.
* Templates sobreescribibles desde el tema en `glotracol-quote/`.

== Changelog ==

= 2.0.2 =
* **Seguridad (autorización)**: `ajax_convert_to_order` ahora valida `current_user_can('edit_post', $post_id)` (capacidad a nivel de objeto) en vez del genérico `edit_posts`. Evita que Autores/Colaboradores conviertan cotizaciones ajenas, reescriban precios y disparen el reenvío de email al cliente.
* **Seguridad (CSV injection)**: la exportación de reportes neutraliza celdas que empiezan por `= + - @` (antepone apóstrofo) para que Excel/Sheets no ejecuten fórmulas inyectadas vía campos del cliente.
* **Rendimiento**: se elimina el patrón N+1 en Reportes (stats + export) y en el resumen del dashboard, primeando post/meta cache en una sola consulta (`_prime_post_caches`).
* **Robustez**: el importador de clientes sanitiza razón social y NIT al guardar; el resolver de pricing del formulario guarda la llamada estática tras `class_exists`; webhook con reintentos por backoff (1m/5m/15m) en vez de un único reintento; el logger acota el tamaño del `context` para no inflar el option.
* **Limpieza**: se elimina un condicional muerto con precedencia rota en `cart_page_title`.

= 2.0.1 =
* **Fix**: bug en el importador donde tras subir un CSV salía "Archivo no encontrado o expirado" — el token generado tenía mayúsculas pero `sanitize_key()` lo lowercaseaba causando mismatch del path. Token ahora es `bin2hex(random_bytes(8))` (siempre lowercase hex).
* **Sistema de logs centralizado**: nueva clase `Glotracol_Quote_Logger` con niveles (debug/info/warn/error) y categorías. Storage en option rolling (últimas 500 entradas). Pantalla "Logs" en el admin con filtros (nivel/categoría) y vista detallada por entrada (incluyendo context JSON expandible).
* Logger integrado en: imports (subida, parse, run), emails (admin/cliente con template usado), webhooks (status code + reintentos), conversión cotización → pedido, fallas de carga de WC, callbacks de tabs WC.
* Banner de alerta en el dashboard si hay errores recientes en log + acceso rápido al log con counter.
* Las llamadas dispersas a `error_log()` ahora también pasan por el logger central, manteniendo la salida a debug.log de WP para warnings y errores.

= 2.0.0 =
* Release final del roadmap v2.0 (RFQ + B2B + Pricing). Incluye Fase E: pantalla de Reportes.
* F11: Nueva pantalla "Reportes" bajo Cotizaciones. Filtros: rango fechas, tipo, estado, pricing_status, tamaño. Stats panel con 4 tarjetas (total, monto, conversión, pedidos grandes). Top 5 clientes por monto y top 5 SKUs por unidades pedidas. Tabla detallada con paginación.
* F11 export: descarga CSV con BOM UTF-8 (compatible Excel) en formato expandido (1 fila por item con datos del cliente, totales y pricing). Honra los filtros aplicados.
* Bump mayor a 2.0.0: el plugin pasa de cotizador simple (v1.0.0) a sistema RFQ + B2B + pricing automático completo. 6 fases entregadas en una sola sesión.

= 1.5.0 =
* F6: Modal pre-submit "¿Cotización o Pedido?" con dos opciones grandes (lupa para cotización, carrito para pedido). El cliente elige antes de enviar — el form no se manda hasta que se selecciona tipo.
* Conexión completa: el submit del formulario ahora usa `Glotracol_Quote_Pricing::resolve_items()` para resolver precios de cada SKU contra B2B (si NIT match) o lista pública. Calcula total, marca pricing_status (priced/partial/none) y guarda metas.
* Statuses nuevos: `glo-pending-prices` (falta precio para ≥1 SKU) y `glo-auto-priced` (todos con precio, cliente recibió cotización formal automática).
* Email "Cotización formal" / "Confirmación de pedido" al cliente (template email-customer-priced.php) cuando todos los SKUs tienen precio. Tabla con precio unitario, badge B2B en cada item, total prominente, validez 7 días.
* Email "Pendiente de precios" al admin (template email-admin-pending-prices.php) cuando faltan precios. Resalta los SKUs sin precio en amarillo. Subject prefix "⚠️ [PENDIENTE PRECIOS]".
* Subject prefixes según contexto: "🔥 [GRANDE]", "⚠️ [PENDIENTE PRECIOS]", "✅ [AUTO-COTIZADA]", "🛒 [PEDIDO]".
* F7: Botón "Convertir en pedido" en metabox lateral de la cotización. Modal admin permite editar precios faltantes inline con cálculo de subtotales en vivo. Al confirmar, cambia tipo a "order", recalcula pricing_status, dispara email auto-cotizado al cliente y actualiza el título a "Pedido #...".
* Columnas admin: nueva columna "Tipo" (badge Cotización/Pedido) + "Total" (formateado en COP). Columna "Estado" reordenada al final.
* Setting nuevo "Auto-respuesta" en tab Reglas: toggle para activar/desactivar el envío automático con precios.

= 1.4.0 =
* C.1: Pestaña "Presentaciones" en la pantalla de edición de producto WC. Tabla repetidora con label / SKU variante / peso / precio público. Save vía woocommerce_admin_process_product_object.
* C.2: Selector de presentación en single-product (debajo del título, antes del botón). En catálogo, productos con presentaciones muestran "Ver presentaciones →" que linkea al producto en lugar de añadir directo.
* C.3: Cart soporta múltiples presentaciones del mismo producto como items separados (filtro woocommerce_cart_id). Display de la presentación en cada línea. Dropdown inline para cambiar presentación sin recargar (AJAX gloq_swap_presentation).
* SKU efectivo: cuando el item tiene presentación, su SKU efectivo es el de la variante (no el del producto padre). Esto permite que el resolver de precios encuentre los precios públicos/B2B importados con el SKU correcto.
* Helpers nuevos: glotracol_quote_get_presentaciones() y glotracol_quote_get_presentacion(). Fachada lista para migrar a productos variables WC en el futuro sin tocar callers.

= 1.3.0 =
* F8: CRM de clientes B2B. Nuevo CPT `glo_client` con admin completa (datos básicos, precios negociados, historial de cotizaciones, estado activo/inactivo). Index NIT en option para lookup O(1).
* F9: Sistema de resolución de precios `Glotracol_Quote_Pricing::resolve($sku, $client_id)`. Lista pública (option) + override B2B por cliente (meta). Pantalla "Precios" con búsqueda, paginación, edición inline.
* F10: Importador CSV completo con 4 hojas (clientes, precios públicos, precios B2B, presentaciones). Plantillas descargables, preview de 20 filas, reporte detallado de inserted/updated/skipped/errors. Detección automática de delimitador y BOM UTF-8.
* Helpers nuevos: `glotracol_quote_find_client_by_nit()`, `glotracol_quote_get_client_data()`, `glotracol_quote_format_price()`.

= 1.2.0 =
* F1: Refinamiento visual del mensaje post-add-to-cart con wrapper flex (`.gloq-msg-wrap`), badge ✓ y dos botones bien diferenciados (Ver mi cotización + Solicitar ahora). Responsive en móvil.
* F2: Pestaña "Información adicional" renombrada a "Presentación" en single-product. CTA destacado al final de la pestaña Descripción con botón "Añadir a mi cotización" y, si ya está en cart, botón secundario "Ir al formulario".
* F3: Etiqueta automática de tamaño (Pequeña/Mediana/Grande) calculada en el submit basada en unidades totales y SKUs distintos. Nueva columna "Tamaño" en la list table con badge coloreado. Backfill on-the-fly para cotizaciones antiguas.
* F4: Pedidos grandes disparan email destacado con template diferenciado (`email-admin-large.php`, color rojo, badge "Atención prioritaria") y prefix "🔥 [GRANDE]" en el asunto. Email destacado opcional (CC adicional para gerencia).
* Nueva tab "Reglas" en settings con thresholds configurables y toggle de alerta.

= 1.1.0 =
* Hardening contra updates de WooCommerce, tema y plugins externos.
* Renombrado del carrito reescrito en triple capa (gettext con regex + JS DOM rewrite + CSS pseudo-element como red de seguridad).
* `ensure_wc_cart_loaded()` ahora es defensivo: try/catch alrededor de los requires y fallback con admin notice si WC reorganiza archivos.
* Nueva sección "Estado de compatibilidad" en el dashboard que verifica versión de WC, hooks críticos, paths internos, plugins en conflicto y health del rename.
* Nuevo helper estático `Glotracol_Quote_Cart_Overrides::self_test_gettext()` para validar runtime del filtro.

= 1.0.0 =
* Versión inicial.
