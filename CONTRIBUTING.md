# Guía de desarrollo y publicación

Notas técnicas para quien mantiene el plugin **Glotracol Cotizador**. Para el uso del día a día
del equipo comercial, ver `docs/MANUAL_OPERATIVO.md`.

## Versionado (SemVer `X.Y.Z`)

- **X (mayor)** — rediseños grandes o cambios que rompen compatibilidad. Rara vez.
- **Y (menor)** — entró una función nueva. Ej.: `2.3.0`.
- **Z (parche)** — mejora pequeña, corrección de bug o parche de seguridad. Ej.: `2.3.1`.

La versión vive en **tres lugares que deben coincidir**:
1. Cabecera de `glotracol-quote.php` → `Version: X.Y.Z`
2. Constante en `glotracol-quote.php` → `define( 'GLOTRACOL_QUOTE_VERSION', 'X.Y.Z' );`
3. `readme.txt` → `Stable tag: X.Y.Z`

## Publicar una versión nueva

Cada vez que se ponga en producción algo que el equipo del negocio note:

1. **Sube la versión** en los tres lugares de arriba.
2. **Agrega la nota de cambios** (en lenguaje de negocio, sin tecnicismos) arriba del todo en
   `entries()` de `includes/class-changelog-admin.php`:
   ```php
   [
       'date' => 'YYYY-MM-DD', 'version' => 'X.Y.Z', 'type' => 'feature', // feature|improvement|fix|security
       'title' => 'Título corto',
       'summary' => 'Una o dos frases claras.',
       'details' => [ 'Bullet 1.', 'Bullet 2.' ], // opcional
   ],
   ```
   La versión que muestra el panel se deriva sola de la primera entrada (no hay que tocarla aparte).
3. (Opcional) Resume lo mismo en el bloque `== Changelog ==` de `readme.txt`.
4. **Commitea y empuja:**
   ```bash
   git add -A && git commit -m "feat: ..."   # sin trailers de autoría
   git push origin main
   ```
5. **Etiqueta la versión** (esto es lo que dispara la actualización en los demás sitios):
   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
6. (Opcional, recomendado) Crea un **Release** en GitHub (web → Releases → Draft a partir del tag).
   Da una ventana de "Ver detalles" más rica y hace el chequeo de versión más fiable.

Tras esto, cada sitio con el plugin verá *"Actualización disponible"* y se actualiza con un clic.
**No hay que subir ningún ZIP a mano**: se usa el ZIP que GitHub genera del tag.

## Cómo funciona la actualización remota

`includes/class-updater.php` (actualizador propio, sin librerías externas):

- Consulta la API pública de GitHub del repo `neracosu/Cotizador-Glotracol`: primero el Release
  `latest`; si no hay releases, el tag con la versión más alta.
- Si esa versión es mayor que la instalada (`GLOTRACOL_QUOTE_VERSION`), la inyecta en el flujo de
  actualizaciones de WordPress.
- Al instalar, renombra la carpeta del ZIP de GitHub al slug `glotracol-quote`.
- Cachea el chequeo 6 horas (`glotracol_quote_update_check`); WordPress además tiene su propia caché.
  Para forzar, usa **Escritorio → Actualizaciones** o `wp transient delete glotracol_quote_update_check`.

> El repo es **público**, así que no se necesita token. Si algún día pasa a privado, habría que añadir
> autenticación al updater.

### Precaución en el sitio de origen

Este directorio es a la vez el **origen** y un **sitio en producción**. Como se etiqueta *después* de
desplegar aquí, queda sincronizado. El único riesgo es etiquetar una versión mayor mientras este sitio
tenga **ediciones locales sin commitear**: WordPress ofrecería "actualizar" y podría pisarlas. Regla:
**commitea antes de etiquetar.**

## Convenciones

- **Idioma:** todo el contenido y los textos de cara al usuario en **español**.
- **Sin emojis** en panel, emails, plantillas ni documentación (decisión de la 2.0.3).
- **Seguridad:** todo endpoint admin verifica nonce y capacidad; las acciones públicas usan nonce +
  honeypot + rate limit.
- **Notas y borradores de desarrollo locales** no se versionan — quedan excluidos en
  `.git/info/exclude` (local, no se sube). No commitear esas rutas.
- **Verificación antes de publicar:** `php -l` de los archivos PHP tocados y `node --check` del JS.

## Documentación relacionada

- `docs/MANUAL_OPERATIVO.md` — guía operativa para el equipo comercial.
- `docs/ARQUITECTURA.md` — detalle técnico de subsistemas.
- `docs/ESTADO_DEL_PLUGIN.md` — snapshot de estado para arrancar la siguiente iteración.
