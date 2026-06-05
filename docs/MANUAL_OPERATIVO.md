# Manual operativo — Cotizador Glotracol v2.0

**Para:** equipo comercial Glotracol (Diana Ruiz y operadores)
**Plugin versión:** 2.0.1 · **Fecha:** 2026-04-30

Este documento es una guía paso a paso de cómo **operar el cotizador en el día a día**. No incluye detalles técnicos — para eso hay un documento aparte (`ARQUITECTURA.md`).

---

## 1. Lo nuevo respecto a la versión inicial

El cotizador ya **no es solo un formulario que llega por correo**. Ahora:

1. **Identifica clientes B2B por NIT/Cédula** y les aplica precios negociados automáticamente.
2. **Auto-responde con cotización formal** si todos los productos solicitados tienen precio cargado.
3. **Diferencia "Cotización" de "Pedido"** desde el momento del envío (el cliente elige).
4. **Importa precios y clientes vía CSV** (tu equipo carga semanalmente la lista pública sin código).
5. **Genera reportes filtrables y exportables** para análisis comercial.
6. **Sistema de logs auditable** — cualquier evento del plugin (envío de email, importación, webhook, etc.) queda registrado y se puede consultar en *Cotizaciones → Logs* con filtros por nivel y categoría.

---

## 2. Tareas semanales recomendadas

### 2.1. Actualizar la lista pública de precios (todos los lunes)

1. Ve a **Cotizaciones → Importar**.
2. Selecciona **"Lista de precios públicos"**.
3. Haz clic en **"↓ Descargar plantilla"** la primera vez. Es un CSV de ejemplo.
4. Edita el archivo en Excel (o Google Sheets) con los SKUs y precios actualizados de la semana. Guarda como CSV UTF-8.
5. Vuelve a la pantalla de Importar y sube el archivo.
6. Revisa el preview (primeras 20 filas) y haz clic en **"✓ Confirmar e importar X filas"**.
7. Te aparecerá un reporte: cuántos se actualizaron, cuántos son nuevos, cuántos errores.

> 💡 **Tip:** la importación es aditiva — solo actualiza/inserta. Si quieres borrar precios viejos, ve a **Cotizaciones → Precios** y borra manualmente o usa la "Zona peligrosa" para vaciar todo y empezar de cero.

### 2.2. Revisar cotizaciones del día

1. Ve a **Cotizaciones → Todas las cotizaciones**.
2. Las nuevas tienen badge:
   - ✅ verde **"Auto-cotizada"** — el cliente ya recibió precios. Solo monitorea.
   - ⚠ amarillo **"Pendiente de precios"** — *requiere tu acción*.
   - 🔥 rojo en **Tamaño = Grande** — pedido grande, contactar directo.
3. Para las pendientes:
   - Abre la cotización.
   - Si falta el SKU porque aún no lo cargaste públicamente, ve a **Cotizaciones → Precios** y agrégalo.
   - Si es un cliente B2B con precio negociado, ve al cliente B2B y agrega el precio en su tabla.
   - Vuelve a la cotización y usa **"→ Convertir en pedido"** para completar precios manualmente y notificar al cliente.

### 2.3. Pedidos grandes (alerta 🔥)

Cuando llega un correo con **"🔥 [GRANDE]"** en el asunto:
- Es un cliente que pasó tus umbrales (configurables en **Configuración → Reglas**).
- El correo destacado resalta el WhatsApp del cliente.
- **Recomendación**: contactar directo por WhatsApp en menos de 4 horas.

### 2.4. Cargar el catálogo por ID (precios y productos nuevos)

Cuando recibas el catálogo en el formato del **export de WooCommerce** (columnas
`ID, Nombre, Inventario, Peso (kg), Precio normal, Imágenes`), úsalo así:

1. Ve a **Cotizaciones → Importar** y elige **"Precios del catálogo (por ID)"**.
2. Sube el archivo y, según de qué archivo se trate, marca los toggles:

| Archivo | Tipo de lista | Sincronizar stock | Crear faltantes |
|---|---|---|---|
| Catálogo **con ID** (refresco de precios) | Lista pública | ✅ | ❌ |
| Catálogo de **productos nuevos** (columna ID vacía) | Lista pública | ✅ | ✅ **obligatorio** |

3. Revisa el preview (te dice modo, si sincroniza stock y si crea faltantes) y confirma.

**Qué hace cada toggle:**
- **Sincronizar stock** — pone cada producto como *disponible* o *agotado* según la columna
  `Inventario` (o `Disponibilidad`). Sin marcar, solo toca el precio.
- **Crear faltantes** — para las filas **sin ID**: crea el producto (publicado, con su peso, stock y
  precio interno). **Si el nombre ya existe en el catálogo, actualiza el existente en vez de duplicarlo.**
  Solo aplica en *Lista pública*.

> El **precio que cargas aquí nunca se muestra en la tienda** — es el precio interno de Lista A que el
> sistema usa para armar la cotización. Los productos nuevos salen sin foto/categoría/descripción: complétalos
> después en **Productos → editar**.

> Las filas **sin precio** se omiten (el producto queda pendiente). Aparecen en el reporte final para que sepas
> cuáles cargar luego.

**Dudas frecuentes:**
- **¿Y si importo el mismo archivo dos veces?** No pasa nada: actualiza en vez de duplicar. Los productos
  nuevos se reconocen **por nombre**, así que no se crean de nuevo. *(Salvedad: si renombras un producto en WC
  después de crearlo, una reimportación lo crearía como uno aparte.)*
- **¿Y si subo el archivo en el tipo equivocado?** El importador valida las columnas antes de escribir: si no
  coinciden, lo rechaza en el preview y **no toca nada**. Aun así, **elige siempre el tipo correcto** — si dos
  listas tienen las mismas columnas (p. ej. una lista con descuento), el validador no puede distinguirlas por
  el contenido.

---

## 3. Gestionar clientes B2B

### 3.1. Crear un cliente nuevo

1. **Cotizaciones → Clientes B2B → Añadir cliente**.
2. Llena NIT, Razón social, email, teléfono, contacto, ciudad.
3. **Lista de precios negociados**: agrega SKUs con su precio especial. Estos precios solo se aplican cuando el NIT del cliente coincida exactamente al enviar una cotización.
4. **Estado**: si lo desactivas, el cliente seguirá pidiendo pero recibirá precios públicos en lugar de negociados.

### 3.2. Importar muchos clientes a la vez

1. **Cotizaciones → Importar → "Clientes B2B"**.
2. Descarga la plantilla, llénala con NIT, razón_social, email, etc.
3. Sube y confirma.

### 3.3. Importar precios negociados de un cliente B2B

1. Asegúrate de que el cliente ya exista en el CRM (paso 3.1 o 3.2).
2. **Cotizaciones → Importar → "Precios negociados por cliente"**.
3. La plantilla tiene 3 columnas: `nit, sku, precio`.
4. Una fila por (cliente, SKU). Si quieres negociar 50 SKUs con un cliente, son 50 filas con el mismo NIT.

---

## 4. Configurar presentaciones (250g, 500g, 1kg)

Si un producto se vende en varias presentaciones:

1. Ve al producto en WordPress (**Productos → editar**).
2. Baja a la sección de datos del producto y haz clic en la pestaña **"Presentaciones"**.
3. Agrega cada presentación con:
   - **Etiqueta visible** (ej. "250 g") — lo que ve el cliente.
   - **SKU variante** (ej. ALM-250) — el SKU único que usarán los precios.
   - **Peso (g)** — informativo.
   - **Precio público** — opcional, fallback.
4. Guarda el producto.

> Cuando un cliente vea ese producto, **no podrá añadirlo directo desde el catálogo** — verá un botón "Ver presentaciones →" que lo lleva al producto donde elige cuál.

> En el carrito puede **cambiar la presentación de cada item** sin tener que borrar y agregar.

---

## 5. Reportes y exportes

1. **Cotizaciones → Reportes**.
2. Filtra por rango de fechas (default: últimos 30 días), tipo, estado, etc.
3. Stats panel arriba muestra: total cotizado, conversión, top clientes, top SKUs.
4. Botón **"↓ Exportar CSV"** descarga un archivo con todas las filas que coinciden con tus filtros, en formato expandido (1 fila por producto solicitado, con datos del cliente repetidos).
5. Abre el CSV en Excel — ya viene con BOM UTF-8 para que las tildes y la ñ se vean bien.

**Casos típicos:**
- "¿Cuánto vendimos de almendra esta semana?" → filtra por fecha + busca SKU "ALM" en el CSV.
- "¿Qué clientes están pidiendo más?" → top 5 clientes en el panel.
- "¿Qué cotizaciones quedaron pendientes en marzo?" → filtra Pricing = "Pendiente" + rango de marzo.

---

## 6. Glosario rápido

| Término | Significado |
|---|---|
| **Cotización** | El cliente pide precios; aún no decidió comprar. |
| **Pedido** | El cliente confirmó que quiere comprar; espera factura. |
| **Auto-cotizada** | Sistema envió automáticamente los precios al cliente porque tenía todos los SKUs cargados. |
| **Pendiente de precios** | Falta cargar precio para uno o más SKUs; requiere tu acción manual. |
| **NIT identificado** | El cliente puso un NIT que existe en el CRM B2B → recibió precios negociados. |
| **NIT no identificado** | El NIT no estaba en CRM → recibió precios públicos. |
| **Precio público** | Lista general que se actualiza semanalmente y se aplica a quien no tenga acuerdo B2B. |
| **Precio B2B** | Precio negociado para un cliente específico, identificado por NIT. |
| **Presentación** | Variante de empaque (250g, 500g, 1kg). El SKU efectivo es el de la presentación. |

---

## 7. Configuración avanzada

Todo bajo **Cotizaciones → Configuración**:

- **General**: emails destino, BCC, remitente.
- **Emails**: textos de los correos al admin y al cliente.
- **Formulario**: textos del formulario `/solicitar-cotizacion` y página de gracias.
- **SMTP**: si quieres usar SMTP propio (no recomendado si Site Mailer está activo, que es lo actual).
- **Integraciones**: webhook para automatizaciones tipo Make/Zapier (opcional).
- **Reglas**: umbrales para clasificar tamaño (Mediana/Grande) y email destacado para pedidos grandes. **Toggle de auto-respuesta**.
- **Avanzado**: rate limit, borrado al desinstalar.

---

## 8. ¿Algo se rompió?

1. **El cliente ve "Cart" en el carrito**: ve a **Cotizaciones → Inicio → Estado de compatibilidad** → revisa qué item está en rojo. Probablemente WooCommerce se actualizó y el plugin necesita ajuste menor.
2. **No llegan emails**: revisa **Cotizaciones → Configuración → SMTP**. Si Site Mailer está activo, verifica en su dashboard.
3. **Importador falla**: descarga la plantilla nuevamente y compara columnas. El delimitador ideal es coma; si tu CSV usa punto y coma, también lo detecta.
4. **Precios no aplican**: verifica que el SKU en la lista de precios coincide EXACTAMENTE con el SKU de la presentación (no del producto padre). Mayúsculas y guiones cuentan.

---

## 9. Diagnóstico vía logs

Si algo no funciona como esperas, lo primero es revisar el **log del plugin**:

1. Ve a **Cotizaciones → Logs**.
2. Si hay errores recientes, también verás un banner rojo en la pantalla de Inicio del cotizador y un badge en el card "Logs" del dashboard.
3. Filtra por **nivel** (Error / Warning / Info) y por **categoría**:
   - `import` — todo lo relacionado con la subida y procesamiento de CSV
   - `email` — envío de emails al cliente y al equipo
   - `webhook` — disparos al webhook configurado
   - `wc_compat` — compatibilidad con WooCommerce (carga del cart, callbacks de pestañas)
   - `quote_created` — cotizaciones creadas (con todos los detalles)
   - `conversion` — cotización convertida a pedido
4. Cada entrada tiene un botón **"Ver"** en la columna *Detalle* que expande el contexto completo en JSON (URLs, errores específicos, IDs, etc.). Útil cuando quieras enviarle a Neracosu información para diagnosticar.

**Casos típicos:**

| Síntoma | Categoría a filtrar | Qué buscar |
|---|---|---|
| Cotización no llega por email | `email` | entrada con nivel `error` y `success: false` |
| Webhook no llega a Make/Zapier | `webhook` | `status_code` y `error` en el contexto |
| Importador rechaza un CSV | `import` | mensaje del `redirect_back` y archivos disponibles en temp |
| Cliente B2B no fue identificado | `quote_created` | campo `client_id` debe ser > 0 si el NIT existía |
| Falta precio para un SKU | `quote_created` | `pricing_status: "partial"` y `units_total` |

**El log conserva las últimas 500 entradas** y se puede vaciar manualmente desde el botón "Vaciar log" en la pantalla. Las entradas de `warn` y `error` también se escriben al log de WordPress (`debug.log` si tienes `WP_DEBUG_LOG` activo) por defensa en profundidad.

---

## 10. Soporte

Para cambios o problemas técnicos: **Neracosu** ([https://neracosu.com/](https://neracosu.com/)).

---

*Documento elaborado para Diana Ruiz / Glotracol. Mantenido junto al plugin en `wp-content/plugins/glotracol-quote/docs/`.*
