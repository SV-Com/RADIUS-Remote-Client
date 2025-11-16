#!/bin/bash
#
# Fix para actualización de RADIUS Remote Client
# Ejecutar: sudo bash fix-update.sh
#

set -e

echo "=== Solucionando problema de permisos Git ==="

# Agregar directorio como seguro
git config --global --add safe.directory /var/www/html/radius

# Continuar con la actualización
cd /var/www/html/radius

# Configurar Git
git init
git remote add origin https://github.com/SV-Com/RADIUS-Remote-Client.git
git fetch origin
git branch -m main
git reset --hard origin/main

echo "✓ Actualización completada"
echo ""
echo "Ahora ejecuta de nuevo:"
echo "  sudo bash update.sh"
