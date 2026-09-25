# Glotracol Cotizador

Convierte una tienda WooCommerce en un sistema de solicitud de cotización (RFQ) para B2B: catálogo sin checkout ni pago, donde el cliente arma su lista de productos y pide que le coticen.

![Versión](https://img.shields.io/badge/versión-2.17.2-f2a649) ![Licencia](https://img.shields.io/badge/licencia-GPL--3.0-blue) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b) ![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4)

## Qué resuelve

En muchos negocios mayoristas el precio no es público: depende del cliente, del volumen o de un acuerdo previo. Este plugin retira los precios y el checkout del catálogo de WooCommerce y los reemplaza por un flujo de cotización. El visitante navega el catálogo, agrega productos a "Mi cotización", llena sus datos y envía la solicitud; el equipo comercial responde con los precios, ya sean los de la lista pública o los negociados por NIT para clientes B2B.

Cuando todos los productos de una solicitud ya tienen precio registrado, el plugin puede generar y enviar una cotización formal al cliente de forma automática, sin intervención manual, con el PDF adjunto.

## Características

| Área | Qué hace |
|---|---|
| Flujo RFQ | Reemplaza "Añadir al carrito" por "Añadir a mi cotización" y el checkout por un formulario de solicitud. Oculta precios en catálogo, producto, carrito y emails. |
| CRM de clientes B2B | CPT propio de clientes con NIT, razón social, correo, contacto y precios negociados. Índice por NIT para búsqueda directa. El correo registrado es a donde llega el código de verificación y la cotización con precios negociados. |
| Precios en niveles (A/B + B2B) | Precio individual negociado por cliente, **Lista B** (mayoreo) y **Lista A** (pública). Cada cliente puede asignarse a la Lista B; si un producto no tiene precio B, ese cliente cae automáticamente a la Lista A. Cascada del resolver: individual → Lista B → Lista A → pendiente. Los precios negociados se guardan por ID de producto, o por `sku:<SKU>` cuando son de una presentación; un valor que es a la vez ID de un producto y SKU de otro se rechaza por ambiguo. |
| Precio en vivo por NIT | En el formulario, al escribir el NIT, el cliente pide un código que llega al **correo registrado** de su empresa en el CRM; con el código correcto ve al instante sus precios negociados (sello "Precios acordados aplicados"). Sin verificar ve la lista pública y la cotización queda marcada como "Cliente sin verificar". El cuadro muestra presentación, tipo de empaque, cantidad con botones − / +, valor por línea y total. En móvil cada producto se muestra como tarjeta. |
| Auto-cotización | Distingue entre cotización y pedido. Si todos los productos tienen precio, calcula el total y envía la cotización formal de forma automática. |
| PDF de la cotización | Se genera al crear la solicitud, va adjunto a los correos y se descarga desde la ficha en el panel. Lleva logo, color de marca y los productos con empaque, presentación, precio unitario y subtotal. El archivo temporal se crea en una carpeta privada y se borra tras el envío. |
| Reenvío al cliente | Botón "Reenviar correo al cliente" en cada cotización: vuelve a mandar el correo con el PDF y los precios actuales, y lo anota en el registro. |
| Panel web para el equipo | Página `/panel-cotizaciones` con el shortcode `[glotracol_quote_dashboard]`: resumen del mes, conteo por estado y últimas cotizaciones, desde el frontend y con usuario de WordPress (editores y administradores). |
| Importador tolerante | Acepta **Excel (.xlsx) y CSV** aunque las columnas tengan otros nombres: los reconoce por sinónimos, detecta el tipo de hoja, corrige formatos (precio, peso, NIT) y muestra una vista previa de qué entendió antes de guardar. Cuando un producto no coincide por ID o nombre, sugiere candidatos para resolverlo a mano; nada se escribe sin confirmar. Las decisiones del cotejo viajan en un solo campo, así que los catálogos grandes no pierden filas; si el cotejo llega incompleto, no se importa nada. Plantillas descargables en **Excel** (con hoja de instrucciones) y CSV. |
| Guía interactiva | Recorrido guiado paso a paso en el panel (Inicio, Precios e Importar) que resalta y explica cada sección, construido sobre driver.js. |
| Presentaciones de producto | Variantes por producto (etiqueta, SKU, peso, precio) con selector en la ficha y soporte en el carrito como líneas separadas. Cada presentación se cotiza con su propio precio (negociado por su SKU, o su precio público); sin precio propio queda "A cotizar", nunca al precio del producto base. |
| Carrito flotante | Burbuja persistente visible en todo el sitio con lo añadido a la cotización, panel con edición de cantidades y bottom-sheet en móvil. Funciona también en páginas guardadas en caché (LiteSpeed y similares). En la página del formulario se oculta, igual que las barras fijas del pie de página de Elementor, para no tapar el botón de enviar. |
| Semáforo por peso | Clasificación por peso total de la solicitud: pequeño (verde), grande (amarillo) y toneladas (rojo), con umbrales configurables. |
| Marca configurable | Color de marca y logo en Ajustes → Apariencia; correos, PDF, formulario, carrito flotante y botones del catálogo toman el mismo color, y el texto encima se elige solo (oscuro o blanco) para que se lea. Opcionalmente hereda cualquier color global del kit de Elementor. |
| Emails + SMTP | Doble email configurable (equipo y cliente) con plantillas HTML de marca. SMTP propio opcional y detección de plugins SMTP externos. |
| GoHighLevel por API | Cada cotización crea el contacto y abre una oportunidad con su valor en el pipeline y la etapa elegidos, con una nota con el detalle de productos y el enlace al panel. Si el contacto ya existe (mismo correo), no se modifican sus datos: el detalle queda en la nota. Prueba de conexión al guardar, avisos en pantalla cuando falta configuración, reintentos si la API falla y un bloqueo por cotización para no duplicar oportunidades. |
| Webhook firmado | Envío a integraciones externas (Make, Zapier, n8n) firmado con HMAC-SHA256 y con reintentos por backoff. Payload enriquecido (tipo, total, precios por línea, peso, cliente B2B) y re-disparo al convertir una cotización en pedido. |
| Reportes + export CSV | Pantalla de reportes con filtros, estadísticas y top de clientes y SKU. Exportación a CSV con BOM UTF-8. |
| Logger auditable | Registro centralizado con niveles y categorías, visor con filtros y aviso en el panel ante errores recientes. |
| Anti-spam | Nonce de WordPress, honeypot oculto y límites por hora con contador atómico: por visitante (IP), por correo del cliente y un tope total del sitio. Un mismo formulario enviado dos veces crea una sola cotización. |
| Seguridad | La tienda no crea pedidos por ninguna vía (checkout clásico, `wc-ajax` ni Store API) y solo se cotizan productos publicados. Cotizaciones y clientes tienen permisos propios: los ven administradores, gerentes de tienda y editores, no autores ni colaboradores. El token de GoHighLevel y el secreto del webhook son de solo escritura en Ajustes. Los datos del formulario no viajan en la dirección de la página cuando hay un error. |
| Actualización automática | El plugin comprueba los tags de este repositorio (con caché de 6 horas) y ofrece la versión nueva en Escritorio → Actualizaciones, como cualquier plugin del directorio. "Comprobar de nuevo" consulta GitHub en el momento. Si hay más de una copia instalada, solo carga una y avisa cuál sobra. Los cambios de datos o permisos de cada versión corren solos una vez tras actualizar. |

## Arquitectura

```mermaid
graph TD
    subgraph Frontend
        A[Catálogo sin precios] --> B[Carrito Mi cotización]
        B --> C[Formulario de solicitud]
    end

    subgraph Núcleo
        D[CPT glo_quote]
        E[Resolver de precios]
        F[Emails + PDF]
        G[GoHighLevel / Webhook]
    end

    subgraph Admin
        H[Dashboard y panel web]
        I[Reportes]
        J[Importador]
        K[Logs]
        L[Ajustes]
    end

    C --> E
    E --> D
    D --> F
    D --> G
    D --> H
    D --> I
    J --> E
    K --> D
    L --> F
    L --> G
```

## Flujo de una solicitud

```mermaid
sequenceDiagram
    participant Cliente
    participant Formulario
    participant Resolver as Resolver de precios
    participant WordPress
    participant Equipo

    Cliente->>Formulario: Arma el carrito y envía sus datos
    Formulario->>Resolver: Solicita precios de cada SKU
    Resolver-->>Formulario: Precio B2B por NIT o lista pública
    Formulario->>WordPress: Crea la cotización (CPT) con su estado
    WordPress->>Equipo: Email de notificación + GoHighLevel / webhook
    WordPress->>Cliente: Email de confirmación con el PDF
    alt Todos los SKU tienen precio
        WordPress->>Cliente: Cotización formal automática
    end
```

## Requisitos

- WordPress 6.0 o superior
- WooCommerce 8.0 o superior
- PHP 7.4 o superior

Compatible con HPOS (almacenamiento de pedidos en tablas propias) y con los bloques de carrito y checkout de WooCommerce.

## Instalación

1. Descarga el ZIP del [último tag](https://github.com/neracosu/Cotizador-Glotracol/tags) y súbelo desde Plugins → Añadir nuevo → Subir plugin (o copia la carpeta `glotracol-quote` dentro de `wp-content/plugins`).
2. Activa el plugin desde Plugins en el panel de WordPress. Requiere que WooCommerce esté activo.
3. Al activarse crea las páginas `/solicitar-cotizacion` (formulario), `/cotizacion-enviada` (confirmación) y `/panel-cotizaciones` (panel web del equipo), con sus respectivos shortcodes. Si ya existe una página con ese slug y lleva el shortcode (también dentro de Elementor), la reutiliza; si es otra página, no la toca y crea una nueva.

Las versiones siguientes no se suben a mano: el plugin las detecta desde este repositorio y se actualizan desde Escritorio → Actualizaciones (con "Comprobar de nuevo" si la versión recién publicada todavía no aparece). No subas el ZIP que descarga GitHub: su carpeta interna tiene otro nombre y crea una segunda copia del plugin.

## Configuración

La configuración vive en **Cotizaciones → Configuración**, organizada en pestañas:

- **General** — destinatarios internos, copia oculta y remitente.
- **Emails** — asuntos e introducciones de los correos al equipo y al cliente.
- **Formulario** — textos del formulario, términos y mensaje de la página de gracias.
- **SMTP** — envío por servidor propio y detección de plugins SMTP externos.
- **Integraciones** — GoHighLevel (token de integración privada, Location ID, pipeline y etapas, con prueba de conexión) y webhook genérico (URL, secreto y formato).
- **Reglas** — umbrales de clasificación por tamaño (unidades y peso del semáforo) y auto-respuesta con precios.
- **Apariencia** — color de marca y logo, herencia opcional desde Elementor y opciones del carrito flotante (activación y posición).
- **Avanzado** — límite de envíos por visitante (por IP y por correo), límite total por hora y borrado de datos al desinstalar.

## Extensibilidad para desarrolladores

### Shortcodes

| Shortcode | Función |
|---|---|
| `[glotracol_quote_form]` | Renderiza el formulario de cotización con la lista editable de productos. |
| `[glotracol_quote_thanks]` | Renderiza la página de confirmación a partir del token `?qid=`. |
| `[glotracol_quote_dashboard recientes="10"]` | Panel web del equipo: pide sesión de WordPress y muestra el resumen del cotizador con las últimas cotizaciones. |

### Hooks — actions

| Hook | Argumentos | Cuándo se dispara |
|---|---|---|
| `glotracol_quote_before_save` | `$payload` | Antes de guardar la cotización. |
| `glotracol_quote_created` | `$post_id`, `$payload` | Tras crear el CPT y sus metadatos. |
| `glotracol_quote_logged` | `$entry` | Cada vez que se registra una entrada en el log. |

### Hooks — filters

| Hook | Devuelve |
|---|---|
| `glotracol_quote_email_admin_body` | HTML del email al equipo. |
| `glotracol_quote_email_customer_body` | HTML del email al cliente. |
| `glotracol_quote_webhook_payload` | Arreglo del payload antes de enviar el webhook. |
| `glotracol_quote_brand` | Arreglo de marca antes de usarlo: paleta (`color`, `dark`, `tint`, `line`, `text`, `rgb`) y logo (`logo_id`, `logo_url`, `logo_path`). |
| `glotracol_quote_dashboard_cap` | Capacidad exigida para ver el panel web (por defecto `edit_others_glo_quotes`). |
| `glotracol_quote_max_qty` | Cantidad máxima por línea (por defecto 100000). |
| `glotracol_quote_hide_fixed_footer` | `false` para no ocultar los elementos fijos del pie de Elementor en la página del formulario. |
| `gloq_otp_cooldown` | Segundos entre pedidos de código de verificación (por defecto 60). |
| `glotracol_quote_setting` | Valor de cualquier ajuste antes de usarlo; recibe la clave como segundo argumento. |

### Capacidades

Las cotizaciones (`glo_quote`) y los clientes (`glo_client`) usan capacidades propias (`edit_glo_quotes`, `edit_others_glo_quotes`, `edit_glo_clients`…). Se asignan a administrator, shop_manager y editor al activar el plugin y en la rutina de actualización. Para dar acceso a otro rol, agrégale esas capacidades.

### Plantillas

Las plantillas se pueden sobrescribir desde el tema colocando el archivo correspondiente en `wp-content/themes/<tema>/glotracol-quote/`.

## Publicar una versión

```bash
bin/release.sh          # sube el parche (2.17.1 → 2.17.2)
bin/release.sh 2.18.0   # o una versión exacta
```

Sube la versión en los tres lugares (cabecera, constante y `readme.txt`), commitea, crea el tag y lo empuja. Ese tag es lo que ven los sitios instalados. Antes de correrlo, agrega la entrada de la versión en el changelog de `readme.txt` y en la pantalla Novedades (`includes/class-changelog-admin.php`), y corre `bin/tests.sh`. Si la versión cambia datos o permisos, agrega la rutina en `includes/class-upgrade.php`: corre una sola vez en cada sitio al actualizar, porque una actualización no dispara el hook de activación.

## Pruebas

Los tests corren contra un WordPress real con wp-cli. Para correrlos todos y ver un resumen:

```bash
bin/tests.sh                          # todos
bin/tests.sh tests/test-nit-verify.php  # uno o varios
```

O uno a mano:

```bash
wp eval-file wp-content/plugins/glotracol-quote/tests/test-settings-tabs.php
```

Cada test crea sus propios datos, restaura el registro y limpia los crons que deja; ninguno toca cotizaciones reales.

## Documentación

- [Arquitectura técnica](docs/ARQUITECTURA.md)
- [Manual operativo](docs/MANUAL_OPERATIVO.md)
- [Estado del plugin](docs/ESTADO_DEL_PLUGIN.md)
- [Informe ejecutivo](docs/INFORME_EJECUTIVO.md)
- [Webhook para GoHighLevel](docs/WEBHOOK_GHL.md)

## Licencia

Distribuido bajo licencia GPL-3.0 (ver [LICENSE](LICENSE)).

Incluye [driver.js](https://driverjs.com/) (licencia MIT) para la guía interactiva del panel y [FPDF](http://www.fpdf.org/) (licencia permisiva, en `vendor/fpdf`) para el PDF de la cotización, ambos empaquetados localmente; el resto del plugin no usa librerías externas.

Plugin desarrollado por [Neracosu](https://neracosu.com/) para [eagencia](https://www.eagencia.co/).
Cliente final: Glotracol — Global Trading de Colombia.
