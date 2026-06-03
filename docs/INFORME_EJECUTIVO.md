# Informe ejecutivo — Sistema de Cotizaciones Glotracol

**Versión:** 2.0.3
**Fecha:** 2026-06-02

## Resumen

El sistema de cotizaciones es una tienda en línea adaptada al modelo de negocio de Glotracol: en lugar de vender con pago inmediato, recibe solicitudes de cotización y las gestiona de principio a fin. Está pensado para el equipo comercial de Glotracol (Global Trading de Colombia), que arma, responde y da seguimiento a cada solicitud desde un único lugar. El sistema se encuentra operativo y en uso en producción.

## El problema que resuelve

El negocio mayorista no funciona como una tienda de venta directa: el precio depende de quién es el cliente y de cuánto pide. Por eso el modelo habitual de carrito y pago no aplica, lo que hace falta es cotizar.

Antes, ese proceso ocurría de forma manual por correo, con respuestas dispersas y tiempos largos. Ahora todo está centralizado: las solicitudes llegan a un mismo lugar, cada cliente recibe los precios que le corresponden y las respuestas salen más rápido y con menos margen de error.

## Qué hace el sistema

- El cliente arma su lista de productos sin ver precios y envía sus datos de contacto.
- Si el cliente ya está registrado, identificado por su NIT o cédula, se le aplican automáticamente los precios que se negociaron con él.
- Si todos los productos solicitados tienen precio cargado, el cliente recibe al instante una cotización formal por correo. Si falta el precio de algún producto, el equipo recibe un aviso para completarla.
- El equipo gestiona todas las solicitudes desde un panel central: consultarlas, responder por correo o por WhatsApp, y convertir una cotización en pedido.
- El sistema distingue entre "cotización" (el cliente todavía está explorando) y "pedido" (intención firme de compra), desde el momento mismo del envío.
- Los pedidos de gran volumen se señalan con una alerta destacada para que se atiendan con prioridad.

## Capacidades de gestión comercial

- **Registro de clientes con precios negociados.** Cada cliente puede tener su propia lista de precios acordados, que se aplican de forma automática cuando hace una solicitud.
- **Carga periódica de precios y clientes.** El equipo actualiza la lista pública de precios y la base de clientes subiendo un archivo de Excel o CSV, sin depender de soporte técnico para cada cambio.
- **Reportes comerciales.** Resúmenes con el total cotizado, los clientes que más piden y los productos más solicitados, filtrables por fecha y exportables a Excel para su análisis.
- **Registro de actividad.** El sistema deja constancia de los eventos relevantes (envíos de correo, cargas de archivos, conversiones a pedido), lo que permite revisar qué ocurrió y cuándo ante cualquier duda.

## Beneficios

- **Respuesta más rápida al cliente.** En muchos casos la cotización formal sale de inmediato, sin intervención manual.
- **Consistencia de precios.** Cada cliente recibe siempre el precio que le corresponde, sin depender de la memoria de quien atiende.
- **Menos trabajo manual.** Las tareas repetitivas se automatizan y la carga de información se hace por archivo.
- **Trazabilidad.** Queda registro de cada solicitud y de las acciones realizadas sobre ella.
- **Imagen profesional.** El cliente recibe cotizaciones formales y ordenadas, con una presentación cuidada.

## Estado actual

El sistema está operativo en producción. Ha pasado por revisiones de seguridad y de rendimiento que reforzaron la protección de los datos y agilizaron el funcionamiento del panel. Recientemente la interfaz de gestión se unificó para que todas las pantallas tengan un aspecto y un comportamiento coherentes, lo que la hace más clara y cómoda de usar para el equipo.

## Próximos pasos sugeridos

Estas son mejoras posibles, no compromisos, que pueden valorarse según las prioridades del negocio:

- Ofrecer el contenido de cara al cliente en otros idiomas, si se abre a mercados que lo requieran.
- Reforzar la protección frente a envíos automatizados no deseados, en caso de detectar tráfico indeseado.
- Pulir detalles de uso del panel a partir de la experiencia del día a día del equipo.

## Contacto

Desarrollado por Neracosu — [https://neracosu.com/](https://neracosu.com/)
