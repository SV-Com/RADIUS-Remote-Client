#!/bin/bash

###############################################################################
# RADIUS Remote Client - Instalador Automático
#
# Este script instala el cliente web remoto para gestionar un servidor
# FreeRADIUS que está en otra ubicación/servidor.
#
# NO instala FreeRADIUS ni MySQL localmente - solo conecta a un servidor remoto
###############################################################################

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Banner
echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║        RADIUS REMOTE CLIENT - INSTALADOR                  ║
║        Cliente Web para FreeRADIUS Remoto                 ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Verificar que se ejecuta como root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}Error: Este script debe ejecutarse como root${NC}"
   echo "Ejecuta: sudo bash install-remote.sh"
   exit 1
fi

# Detectar sistema operativo
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}No se pudo detectar el sistema operativo${NC}"
    exit 1
fi

echo -e "${GREEN}Sistema detectado: $OS $VERSION${NC}\n"

# Verificar compatibilidad
if [[ ! "$OS" =~ ^(debian|ubuntu)$ ]]; then
    echo -e "${YELLOW}Advertencia: Este script está optimizado para Debian/Ubuntu${NC}"
    read -p "¿Deseas continuar de todos modos? (s/n): " continue
    if [[ ! "$continue" =~ ^[Ss]$ ]]; then
        exit 0
    fi
fi

# Función para mostrar progreso
progress() {
    echo -e "${BLUE}[*]${NC} $1"
}

success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

error() {
    echo -e "${RED}[✗]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

# Función para verificar comando
check_command() {
    if command -v $1 &> /dev/null; then
        success "$1 está instalado"
        return 0
    else
        warning "$1 no está instalado"
        return 1
    fi
}

###############################################################################
# RECOLECTAR INFORMACIÓN
###############################################################################

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  CONFIGURACIÓN DEL SERVIDOR REMOTO${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# IP del servidor FreeRADIUS
read -p "$(echo -e ${GREEN})IP del servidor FreeRADIUS remoto: $(echo -e ${NC})" REMOTE_HOST
while [[ -z "$REMOTE_HOST" ]]; do
    error "La IP es requerida"
    read -p "$(echo -e ${GREEN})IP del servidor FreeRADIUS remoto: $(echo -e ${NC})" REMOTE_HOST
done

# Puerto MySQL
read -p "$(echo -e ${GREEN})Puerto MySQL (default: 3306): $(echo -e ${NC})" REMOTE_PORT
REMOTE_PORT=${REMOTE_PORT:-3306}

# Nombre de la base de datos
read -p "$(echo -e ${GREEN})Nombre de la base de datos (default: radius): $(echo -e ${NC})" DB_NAME
DB_NAME=${DB_NAME:-radius}

# Usuario MySQL
read -p "$(echo -e ${GREEN})Usuario MySQL remoto: $(echo -e ${NC})" DB_USER
while [[ -z "$DB_USER" ]]; do
    error "El usuario es requerido"
    read -p "$(echo -e ${GREEN})Usuario MySQL remoto: $(echo -e ${NC})" DB_USER
done

# Contraseña MySQL
read -sp "$(echo -e ${GREEN})Contraseña MySQL: $(echo -e ${NC})" DB_PASS
echo
while [[ -z "$DB_PASS" ]]; do
    error "La contraseña es requerida"
    read -sp "$(echo -e ${GREEN})Contraseña MySQL: $(echo -e ${NC})" DB_PASS
    echo
done

# Generar API Key
API_KEY=$(openssl rand -hex 32)

# Directorio de instalación
read -p "$(echo -e ${GREEN})Directorio de instalación (default: /var/www/html/radius): $(echo -e ${NC})" INSTALL_DIR
INSTALL_DIR=${INSTALL_DIR:-/var/www/html/radius}

echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

###############################################################################
# RESUMEN DE CONFIGURACIÓN
###############################################################################

echo -e "${BLUE}Resumen de la configuración:${NC}"
echo -e "  Servidor remoto: ${GREEN}$REMOTE_HOST:$REMOTE_PORT${NC}"
echo -e "  Base de datos:   ${GREEN}$DB_NAME${NC}"
echo -e "  Usuario:         ${GREEN}$DB_USER${NC}"
echo -e "  Instalación:     ${GREEN}$INSTALL_DIR${NC}"
echo -e "  API Key:         ${GREEN}${API_KEY:0:20}...${NC}\n"

read -p "$(echo -e ${YELLOW})¿Continuar con la instalación? (s/n): $(echo -e ${NC})" confirm
if [[ ! "$confirm" =~ ^[Ss]$ ]]; then
    echo "Instalación cancelada"
    exit 0
fi

###############################################################################
# INSTALACIÓN
###############################################################################

echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  INICIANDO INSTALACIÓN${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# Actualizar repositorios
progress "Actualizando repositorios..."
apt-get update -qq
success "Repositorios actualizados"

# Instalar Apache2
progress "Instalando Apache2..."
if ! check_command apache2; then
    apt-get install -y apache2 > /dev/null 2>&1
    success "Apache2 instalado"
fi

# Instalar PHP
progress "Instalando PHP y extensiones..."
apt-get install -y php php-mysql php-curl php-json php-mbstring php-xml libapache2-mod-php > /dev/null 2>&1
success "PHP instalado"

# Instalar MySQL Client (para pruebas)
progress "Instalando cliente MySQL..."
apt-get install -y mysql-client > /dev/null 2>&1
success "Cliente MySQL instalado"

# Crear directorio de instalación
progress "Creando directorio de instalación..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/includes"
mkdir -p "$INSTALL_DIR/css"
mkdir -p "$INSTALL_DIR/js"
mkdir -p "$INSTALL_DIR/logs"
success "Directorios creados"

# Descargar archivos (si es desde GitHub) o copiar
progress "Instalando archivos..."

# Aquí copiamos los archivos (asumiendo que están en el mismo directorio)
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

if [ -f "$SCRIPT_DIR/config.php" ]; then
    cp -r "$SCRIPT_DIR"/* "$INSTALL_DIR/" 2>/dev/null || true
    success "Archivos copiados"
else
    # Si no están en el mismo directorio, descargar desde GitHub
    progress "Descargando desde GitHub..."
    apt-get install -y git > /dev/null 2>&1

    TMP_DIR=$(mktemp -d)
    git clone https://github.com/SV-Com/RADIUS-Remote-Client.git "$TMP_DIR" > /dev/null 2>&1
    cp -r "$TMP_DIR"/* "$INSTALL_DIR/"
    rm -rf "$TMP_DIR"
    success "Archivos descargados"
fi

# Configurar config.php
progress "Configurando config.php..."
cat > "$INSTALL_DIR/config.php" << EOF
<?php
/**
 * Configuración del Cliente Remoto para FreeRADIUS
 * Generado automáticamente por install-remote.sh
 */

// ========================================
// SERVIDOR FREERADIUS REMOTO
// ========================================

define('REMOTE_DB_HOST', '$REMOTE_HOST');
define('REMOTE_DB_PORT', $REMOTE_PORT);
define('REMOTE_DB_NAME', '$DB_NAME');
define('REMOTE_DB_USER', '$DB_USER');
define('REMOTE_DB_PASS', '$DB_PASS');

// ========================================
// AUTENTICACIÓN DEL PANEL WEB
// ========================================

define('API_KEY', '$API_KEY');

// ========================================
// TÚNEL SSH (OPCIONAL)
// ========================================

define('USE_SSH_TUNNEL', false);
define('SSH_HOST', '$REMOTE_HOST');
define('SSH_PORT', 22);
define('SSH_USER', 'usuario_ssh');
define('SSH_KEY_PATH', '/home/user/.ssh/id_rsa');
define('LOCAL_TUNNEL_PORT', 3307);

// ========================================
// TIMEOUT Y CONEXIÓN
// ========================================

define('DB_CONNECT_TIMEOUT', 10);
define('DB_READ_TIMEOUT', 30);
define('DB_CHARSET', 'utf8mb4');

// ========================================
// CONFIGURACIÓN DE EMAIL
// ========================================

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@gmail.com');
define('SMTP_PASS', 'tu_password_app');
define('SMTP_FROM', 'tu_email@gmail.com');
define('SMTP_FROM_NAME', 'RADIUS Remote Client');

// ========================================
// WEBHOOKS
// ========================================

define('WEBHOOKS_ENABLED', true);
define('WEBHOOKS_FILE', __DIR__ . '/webhooks.json');

// ========================================
// SEGURIDAD
// ========================================

define('LOG_CONNECTIONS', true);
define('CONNECTION_LOG_FILE', __DIR__ . '/logs/connections.log');
define('ALLOWED_IPS', []);

// ========================================
// CONFIGURACIÓN AVANZADA
// ========================================

define('DEBUG_MODE', false);
define('TABLE_PREFIX', '');
date_default_timezone_set('America/Argentina/Buenos_Aires');

// ========================================
// VALIDACIÓN
// ========================================

if (!defined('REMOTE_DB_HOST') || REMOTE_DB_HOST === '') {
    die('Error: REMOTE_DB_HOST no está configurado');
}

if (!defined('REMOTE_DB_NAME') || REMOTE_DB_NAME === '') {
    die('Error: REMOTE_DB_NAME no está configurado');
}

if (!defined('REMOTE_DB_USER') || REMOTE_DB_USER === '') {
    die('Error: REMOTE_DB_USER no está configurado');
}

// ========================================
// FUNCIONES AUXILIARES
// ========================================

function checkSSHTunnel() {
    if (!USE_SSH_TUNNEL) return true;
    \$connection = @fsockopen('127.0.0.1', LOCAL_TUNNEL_PORT, \$errno, \$errstr, 1);
    if (\$connection) {
        fclose(\$connection);
        return true;
    }
    return false;
}

function getEffectiveDBConfig() {
    if (USE_SSH_TUNNEL) {
        return [
            'host' => '127.0.0.1',
            'port' => LOCAL_TUNNEL_PORT,
            'name' => REMOTE_DB_NAME,
            'user' => REMOTE_DB_USER,
            'pass' => REMOTE_DB_PASS
        ];
    }
    return [
        'host' => REMOTE_DB_HOST,
        'port' => REMOTE_DB_PORT,
        'name' => REMOTE_DB_NAME,
        'user' => REMOTE_DB_USER,
        'pass' => REMOTE_DB_PASS
    ];
}

function logConnection(\$event, \$details = '') {
    if (!LOG_CONNECTIONS) return;
    \$logDir = dirname(CONNECTION_LOG_FILE);
    if (!is_dir(\$logDir)) @mkdir(\$logDir, 0755, true);
    \$timestamp = date('Y-m-d H:i:s');
    \$ip = \$_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    \$logEntry = "[\$timestamp] [\$ip] \$event: \$details\n";
    @file_put_contents(CONNECTION_LOG_FILE, \$logEntry, FILE_APPEND);
}

function checkAllowedIP() {
    if (empty(ALLOWED_IPS)) return true;
    \$clientIP = \$_SERVER['REMOTE_ADDR'] ?? '';
    return in_array(\$clientIP, ALLOWED_IPS);
}
?>
EOF

success "config.php configurado"

# Permisos
progress "Configurando permisos..."
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 700 "$INSTALL_DIR/logs"
chmod 600 "$INSTALL_DIR/config.php"
success "Permisos configurados"

# Configurar Apache
progress "Configurando Apache..."
cat > /etc/apache2/sites-available/radius.conf << EOF
<VirtualHost *:80>
    DocumentRoot $INSTALL_DIR
    ServerName radius

    <Directory $INSTALL_DIR>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/radius-error.log
    CustomLog \${APACHE_LOG_DIR}/radius-access.log combined
</VirtualHost>
EOF

a2ensite radius.conf > /dev/null 2>&1
a2enmod rewrite > /dev/null 2>&1
systemctl reload apache2
success "Apache configurado"

# Probar conexión al servidor remoto
progress "Probando conexión al servidor remoto..."
if mysql -h "$REMOTE_HOST" -P "$REMOTE_PORT" -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME; SELECT 1;" > /dev/null 2>&1; then
    success "Conexión al servidor remoto exitosa"
else
    warning "No se pudo conectar al servidor remoto"
    echo -e "${YELLOW}Verifica que:${NC}"
    echo "  1. El servidor MySQL remoto acepte conexiones externas"
    echo "  2. El firewall permita conexiones al puerto $REMOTE_PORT"
    echo "  3. El usuario '$DB_USER' tenga permisos desde esta IP"
fi

###############################################################################
# FINALIZACIÓN
###############################################################################

echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✓ INSTALACIÓN COMPLETADA${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# Obtener IP del servidor
SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "${GREEN}Instalación exitosa!${NC}\n"
echo -e "${BLUE}Información de acceso:${NC}"
echo -e "  URL:     ${GREEN}http://$SERVER_IP/radius/${NC}"
echo -e "  API Key: ${GREEN}$API_KEY${NC}"
echo -e ""
echo -e "${YELLOW}IMPORTANTE: Guarda la API Key en un lugar seguro${NC}\n"

echo -e "${BLUE}Próximos pasos:${NC}"
echo "  1. Accede a http://$SERVER_IP/radius/"
echo "  2. Inicia sesión con la API Key mostrada arriba"
echo "  3. Verifica la conexión en la pestaña 'Test Conexión'"
echo ""

echo -e "${YELLOW}Configuración del servidor remoto:${NC}"
echo "  Si no puedes conectar, verifica en el servidor FreeRADIUS:"
echo ""
echo "  1. MySQL acepta conexiones remotas:"
echo "     sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf"
echo "     bind-address = 0.0.0.0"
echo ""
echo "  2. Usuario con permisos remotos:"
echo "     mysql -u root -p"
echo "     CREATE USER '$DB_USER'@'$SERVER_IP' IDENTIFIED BY 'password';"
echo "     GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'$SERVER_IP';"
echo "     FLUSH PRIVILEGES;"
echo ""
echo "  3. Firewall permite el puerto:"
echo "     sudo ufw allow from $SERVER_IP to any port $REMOTE_PORT"
echo ""

echo -e "${GREEN}Documentación completa:${NC}"
echo "  https://github.com/SV-Com/RADIUS-Remote-Client"
echo ""

# Guardar información en un archivo
INFO_FILE="$INSTALL_DIR/install-info.txt"
cat > "$INFO_FILE" << EOF
RADIUS Remote Client - Información de Instalación
================================================

Fecha: $(date)
Servidor Cliente: $SERVER_IP
Servidor Remoto: $REMOTE_HOST:$REMOTE_PORT
Base de Datos: $DB_NAME
Usuario: $DB_USER

API Key: $API_KEY

URL de Acceso: http://$SERVER_IP/radius/

================================================
EOF

chmod 600 "$INFO_FILE"
success "Información guardada en: $INFO_FILE"

echo -e "\n${GREEN}¡Instalación finalizada!${NC}\n"
