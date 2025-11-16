#!/bin/bash
#
# Script seguro de actualización - Preserva config.php
# Uso: sudo bash safe-update.sh
#

set -e

echo "========================================"
echo "  Actualización Segura - RADIUS Panel"
echo "========================================"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_info() { echo -e "${BLUE}ℹ${NC} $1"; }
print_success() { echo -e "${GREEN}✓${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
print_error() { echo -e "${RED}✗${NC} $1"; }

# Variables
INSTALL_DIR="/var/www/html/radius"
BACKUP_DIR="/root/radius-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Verificar que estamos en el directorio correcto
if [ ! -d "$INSTALL_DIR" ]; then
    print_error "Directorio $INSTALL_DIR no existe"
    exit 1
fi

cd "$INSTALL_DIR"

# Crear directorio de backups
mkdir -p "$BACKUP_DIR"

print_info "Paso 1/6: Guardando config.php..."
if [ -f "config.php" ]; then
    cp config.php "$BACKUP_DIR/config.php.$TIMESTAMP"
    print_success "config.php respaldado"
else
    print_warning "config.php no encontrado"
fi

print_info "Paso 2/6: Guardando cambios locales..."
# Stash de cambios locales (excepto config.php que ignoramos)
git stash push -m "Auto-stash before update $TIMESTAMP"
print_success "Cambios locales guardados temporalmente"

print_info "Paso 3/6: Descargando actualizaciones..."
git fetch origin

# Ver qué se va a actualizar
CURRENT=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)

if [ "$CURRENT" != "$REMOTE" ]; then
    echo ""
    print_info "Cambios que se van a descargar:"
    git log --oneline $CURRENT..$REMOTE | head -10
    echo ""
fi

git pull origin main

print_success "Código actualizado"

print_info "Paso 4/6: Restaurando config.php..."
if [ -f "$BACKUP_DIR/config.php.$TIMESTAMP" ]; then
    cp "$BACKUP_DIR/config.php.$TIMESTAMP" config.php
    print_success "config.php restaurado"
elif [ -f "config.example.php" ]; then
    print_warning "Creando config.php desde config.example.php"
    cp config.example.php config.php
    print_warning "IMPORTANTE: Edita config.php con tus credenciales"
fi

print_info "Paso 5/6: Ajustando permisos..."
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 664 "$INSTALL_DIR/config.php" 2>/dev/null || true

print_success "Permisos ajustados"

print_info "Paso 6/6: Reiniciando servidor web..."
if systemctl is-active --quiet apache2; then
    systemctl reload apache2
    print_success "Apache reiniciado"
elif systemctl is-active --quiet httpd; then
    systemctl reload httpd
    print_success "HTTPD reiniciado"
fi

echo ""
echo "========================================"
echo "  ✓ ACTUALIZACIÓN COMPLETADA"
echo "========================================"
echo ""

# Mostrar versión actual
CURRENT_VERSION=$(git log -1 --format="%h - %s")
print_info "Versión actual: $CURRENT_VERSION"
echo ""

# Nuevas funcionalidades
echo "NUEVAS FUNCIONALIDADES:"
echo "  ✓ Soporte para Huawei NE8000 (atributos específicos)"
echo "  ✓ Sistema de Planes mejorado"
echo "  ✓ Botón 'Reconectar' para usuarios activos"
echo ""

# Verificar config.php
if grep -q "genera_una_clave_aleatoria_aqui" config.php 2>/dev/null; then
    print_warning "ADVERTENCIA: Debes cambiar la API_KEY en config.php"
    echo "  nano $INSTALL_DIR/config.php"
fi

# Verificar NAS_TYPE
if ! grep -q "NAS_TYPE" config.php 2>/dev/null; then
    print_warning "ADVERTENCIA: config.php no tiene NAS_TYPE configurado"
    echo "  Agrega: define('NAS_TYPE', 'huawei');"
    echo "  nano $INSTALL_DIR/config.php"
fi

echo ""
print_success "Actualización finalizada con éxito"
echo ""

exit 0
