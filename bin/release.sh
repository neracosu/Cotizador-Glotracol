#!/bin/bash
#
# Publica una versión nueva del plugin en un solo paso.
#
#   bin/release.sh            → autoincrementa el parche (2.3.0 → 2.3.1)
#   bin/release.sh 2.4.0      → publica esa versión exacta
#
# Hace: sube la versión en los 3 lugares (cabecera, constante, readme), lintea,
# commitea, crea el tag vX.Y.Z y empuja main + el tag. Eso dispara la
# actualización en todos los sitios donde esté instalado el plugin.
#
# Requisito: el resto de tus cambios ya commiteados (el árbol debe estar limpio).
# Si quieres destacar algo en la sección "Novedades", agrega antes la entrada en
# includes/class-changelog-admin.php y commitéala con tu cambio.
#
set -euo pipefail
cd "$(dirname "$0")/.."

MAIN="glotracol-quote.php"
README="readme.txt"

# --- versión actual (de la constante) ---
current=$(grep -oE "GLOTRACOL_QUOTE_VERSION', '[0-9]+\.[0-9]+\.[0-9]+'" "$MAIN" | grep -oE "[0-9]+\.[0-9]+\.[0-9]+") || true
[ -z "${current:-}" ] && { echo "No pude leer la versión actual en $MAIN"; exit 1; }

# --- versión nueva ---
if [ $# -ge 1 ]; then
  new="$1"
else
  IFS=. read -r MA MI PA <<< "$current"
  new="$MA.$MI.$((PA + 1))"
fi

echo "$new" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$' || { echo "Versión inválida: '$new' (usa X.Y.Z)"; exit 1; }

# --- validaciones ---
if [ "$(printf '%s\n%s\n' "$current" "$new" | sort -V | tail -1)" != "$new" ] || [ "$current" = "$new" ]; then
  echo "La versión nueva ($new) debe ser mayor que la actual ($current)."; exit 1
fi
if git rev-parse "v$new" >/dev/null 2>&1; then echo "El tag v$new ya existe."; exit 1; fi
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Hay cambios sin commitear en archivos versionados. Commitéalos o descártalos antes de publicar."; exit 1
fi

echo "Publicando v$current → v$new ..."

# --- subir la versión en los 3 lugares ---
sed -i "s/^ \* Version: $current\$/ * Version: $new/" "$MAIN"
sed -i "s/GLOTRACOL_QUOTE_VERSION', '$current'/GLOTRACOL_QUOTE_VERSION', '$new'/" "$MAIN"
sed -i "s/^Stable tag: $current\$/Stable tag: $new/" "$README"

# --- verificar que sí cambió ---
grep -q "GLOTRACOL_QUOTE_VERSION', '$new'" "$MAIN" || { echo "No se pudo actualizar la constante; revisa $MAIN."; git checkout -- "$MAIN" "$README"; exit 1; }

# --- lint ---
php -l "$MAIN" >/dev/null

# --- commit + tag + push ---
git add "$MAIN" "$README"
git commit -m "chore(release): v$new"
git tag "v$new"
git push origin main
git push origin "v$new"

echo ""
echo "Listo: v$new publicada."
echo "Los sitios con el plugin verán la actualización (al revisar Escritorio → Actualizaciones, o en ~12 h)."
