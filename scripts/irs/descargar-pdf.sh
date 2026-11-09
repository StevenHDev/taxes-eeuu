#!/bin/sh
# Uso: scripts/irs/descargar-pdf.sh <directorio-nuevo-y-vacio>
# Baja los PDF del IRS de 2025 a un directorio vacío. No ejecuta nada de lo descargado.
set -eu
dir="$1"
[ -z "$(ls -A "$dir")" ] || { echo "El directorio debe estar vacío" >&2; exit 1; }
for f in f1040 f1040s1 f1040s1a f1040s2 f1040s3 f1040sc f1040sse f1040s8 f2441 f1040sei f8962 f8995 i1040gi i1040s8 i8962 i2441 i1040sc i1040sse i8995 p1040; do
  curl -sSL --max-time 90 -A 'Mozilla/5.0' -o "$dir/$f.pdf" "https://www.irs.gov/pub/irs-prior/$f--2025.pdf" || echo "FALLO $f"
done
