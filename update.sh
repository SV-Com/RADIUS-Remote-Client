#!/bin/bash
#
# Script de actualización automática para RADIUS Remote Client
# Versión: 1.0
# Uso: sudo bash update.sh
#

set -e  # Salir si hay errores

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para imprimir mensajes
print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Banner
echo "=========================================="
echo "  RADIUS Remote Client - Update Script"
echo "=========================================="
echo ""

# Verificar que se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root (sudo)"
    exit 1
fi

# Configuración
INSTALL_DIR="/var/www/html/radius"
BACKUP_DIR="$HOME/radius-backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
WEB_USER="www-data"

# Verificar que el directorio existe
if [ ! -d "$INSTALL_DIR" ]; then
    print_error "Directorio $INSTALL_DIR no existe"
    exit 1
fi

# Crear directorio de backups
mkdir -p "$BACKUP_DIR"
print_success "Directorio de backups: $BACKUP_DIR"

# Paso 1: Backup de config.php
print_info "Paso 1/7: Haciendo backup de config.php..."
if [ -f "$INSTALL_DIR/config.php" ]; then
    cp "$INSTALL_DIR/config.php" "$BACKUP_DIR/config.php.backup.$TIMESTAMP"
    print_success "Backup guardado: config.php.backup.$TIMESTAMP"
else
    print_warning "config.php no encontrado, se creará desde config.example.php"
fi

# Paso 2: Backup de archivos críticos
print_info "Paso 2/7: Haciendo backup de archivos actuales..."
BACKUP_FILES=(
    "api.php"
    "index.php"
    "js/app.js"
    "css/style.css"
)

for file in "${BACKUP_FILES[@]}"; do
    if [ -f "$INSTALL_DIR/$file" ]; then
        mkdir -p "$BACKUP_DIR/$(dirname $file)"
        cp "$INSTALL_DIR/$file" "$BACKUP_DIR/$file.backup.$TIMESTAMP"
    fi
done
print_success "Archivos respaldados en $BACKUP_DIR"

# Paso 3: Verificar Git
print_info "Paso 3/7: Verificando instalación de Git..."
if ! command -v git &> /dev/null; then
    print_warning "Git no está instalado. Instalando..."
    apt update -qq
    apt install -y git
    print_success "Git instalado"
else
    print_success "Git ya está instalado"
fi

# Paso 4: Actualizar desde GitHub
print_info "Paso 4/7: Descargando actualizaciones desde GitHub..."
cd "$INSTALL_DIR"

# Verificar si es un repositorio Git
if [ ! -d ".git" ]; then
    print_warning "No es un repositorio Git. Inicializando..."
    git init
    git remote add origin https://github.com/SV-Com/RADIUS-Remote-Client.git
    git fetch origin
    git reset --hard origin/main
    print_success "Repositorio inicializado"
else
    # Guardar estado actual
    CURRENT_COMMIT=$(git rev-parse HEAD 2>/dev/null || echo "unknown")

    # Descartar cambios locales (excepto config.php que ya respaldamos)
    git fetch origin
    git reset --hard origin/main

    NEW_COMMIT=$(git rev-parse HEAD)

    if [ "$CURRENT_COMMIT" != "$NEW_COMMIT" ]; then
        print_success "Actualizado de $CURRENT_COMMIT a $NEW_COMMIT"

        # Mostrar cambios
        echo ""
        print_info "Cambios descargados:"
        git log --oneline $CURRENT_COMMIT..$NEW_COMMIT || true
        echo ""
    else
        print_success "Ya estás en la última versión"
    fi
fi

# Paso 5: Restaurar config.php
print_info "Paso 5/7: Restaurando config.php..."
if [ -f "$BACKUP_DIR/config.php.backup.$TIMESTAMP" ]; then
    cp "$BACKUP_DIR/config.php.backup.$TIMESTAMP" "$INSTALL_DIR/config.php"
    print_success "config.php restaurado"
elif [ -f "$INSTALL_DIR/config.example.php" ]; then
    print_warning "Creando config.php desde config.example.php"
    cp "$INSTALL_DIR/config.example.php" "$INSTALL_DIR/config.php"
    print_warning "IMPORTANTE: Edita config.php con tus credenciales"
fi

# Paso 6: Ajustar permisos
print_info "Paso 6/7: Ajustando permisos..."
chown -R $WEB_USER:$WEB_USER "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 664 "$INSTALL_DIR/config.php"

# Crear directorio logs si no existe
if [ ! -d "$INSTALL_DIR/logs" ]; then
    mkdir -p "$INSTALL_DIR/logs"
    chown $WEB_USER:$WEB_USER "$INSTALL_DIR/logs"
    chmod 775 "$INSTALL_DIR/logs"
fi

print_success "Permisos ajustados"

# Paso 7: Reiniciar Apache
print_info "Paso 7/7: Reiniciando servidor web..."
if systemctl is-active --quiet apache2; then
    systemctl reload apache2
    print_success "Apache reiniciado"
elif systemctl is-active --quiet httpd; then
    systemctl reload httpd
    print_success "HTTPD reiniciado"
else
    print_warning "No se pudo detectar el servidor web"
fi

# Verificación final
echo ""
echo "=========================================="
echo "  ACTUALIZACIÓN COMPLETADA"
echo "=========================================="
echo ""
print_success "✓ Backups guardados en: $BACKUP_DIR"
print_success "✓ Archivos actualizados desde GitHub"
print_success "✓ Permisos ajustados"
print_success "✓ Servidor web reiniciado"
echo ""

# Información de versión
cd "$INSTALL_DIR"
CURRENT_VERSION=$(git log -1 --format="%h - %s" 2>/dev/null || echo "Desconocido")
print_info "Versión actual: $CURRENT_VERSION"
echo ""

# Recomendaciones
print_info "PRÓXIMOS PASOS:"
echo "  1. Abre el panel web: http://tu-servidor/radius/"
echo "  2. Verifica que carga correctamente"
echo "  3. Prueba el nuevo tab 'Planes'"
echo "  4. Revisa logs: tail -f /var/log/apache2/error.log"
echo ""

# Verificar config.php
if grep -q "genera_una_clave_aleatoria_aqui" "$INSTALL_DIR/config.php" 2>/dev/null; then
    print_warning "ADVERTENCIA: Debes cambiar la API_KEY en config.php"
    echo "  Genera una con: openssl rand -hex 32"
    echo "  Luego edita: nano $INSTALL_DIR/config.php"
fi

echo ""
print_success "Actualización finalizada con éxito!"
echo ""

exit 0
