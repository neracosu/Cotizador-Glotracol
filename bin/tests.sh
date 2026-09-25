#!/usr/bin/env bash
# Corre los tests (wp eval-file) contra el WordPress de desarrollo.
# Uso: bin/tests.sh              -> todos
#      bin/tests.sh tests/x.php  -> solo esos
set -u
WP_PATH="${WP_PATH:-/home/neracosu/public_html/glotracol.neracosu.com}"
DIR="$(cd "$(dirname "$0")/.." && pwd)"
if [ "$#" -gt 0 ]; then files=("$@"); else files=("$DIR"/tests/test-*.php); fi
fallos=0
for t in "${files[@]}"; do
  out=$(php -d memory_limit=1024M /usr/local/bin/wp --path="$WP_PATH" eval-file "$t" 2>&1)
  if echo "$out" | command grep -qE "\[FAIL\]|Fatal error|PHP Fatal|Uncaught"; then
    echo "FALLA  $(basename "$t")"; echo "$out" | command grep -E "\[FAIL\]|Fatal|Uncaught|Warning" | sed 's/^/       /'
    fallos=$((fallos+1))
  else
    echo "ok     $(basename "$t") ($(echo "$out" | command grep -c '\[OK\]') chequeos)"
  fi
done
echo "---"; echo "$fallos archivo(s) con fallas"
[ "$fallos" -eq 0 ]
