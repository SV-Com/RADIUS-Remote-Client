#!/bin/bash

###############################################################################
# RADIUS Remote Client - Script de Verificación de Conexión
#
# Este script verifica que puedes conectarte al servidor FreeRADIUS remoto
###############################################################################

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════╗
║                                                           ║
║     RADIUS Remote - Verificación de Conexión             ║
║                                                           ║
╚═══════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}\n"

# Solicitar datos
read -p "$(echo -e ${GREEN})IP del servidor FreeRADIUS: $(echo -e ${NC})" HOST
read -p "$(echo -e ${GREEN})Puerto MySQL (default: 3306): $(echo -e ${NC})" PORT
PORT=${PORT:-3306}
read -p "$(echo -e ${GREEN})Usuario MySQL: $(echo -e ${NC})" USER
read -sp "$(echo -e ${GREEN})Contraseña MySQL: $(echo -e ${NC})" PASS
echo
read -p "$(echo -e ${GREEN})Nombre de la base de datos (default: radius): $(echo -e ${NC})" DB
DB=${DB:-radius}

echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  EJECUTANDO PRUEBAS${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"

# Test 1: Ping
echo -e "${YELLOW}[1/5]${NC} Probando conectividad (ping)..."
if ping -c 3 "$HOST" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ El servidor responde a ping${NC}"
else
    echo -e "${RED}✗ El servidor no responde a ping${NC}"
    echo -e "${YELLOW}  Esto podría ser normal si el firewall bloquea ICMP${NC}"
fi

# Test 2: Puerto MySQL
echo -e "\n${YELLOW}[2/5]${NC} Probando conectividad al puerto MySQL ($PORT)..."
if timeout 5 bash -c "cat < /dev/null > /dev/tcp/$HOST/$PORT" 2>/dev/null; then
    echo -e "${GREEN}✓ El puerto $PORT está abierto${NC}"
else
    echo -e "${RED}✗ No se puede conectar al puerto $PORT${NC}"
    echo -e "${YELLOW}  Verifica que:${NC}"
    echo "    - MySQL esté corriendo en el servidor remoto"
    echo "    - El firewall permita conexiones al puerto $PORT"
    echo "    - MySQL acepte conexiones remotas (bind-address = 0.0.0.0)"
fi

# Test 3: Cliente MySQL instalado
echo -e "\n${YELLOW}[3/5]${NC} Verificando cliente MySQL..."
if command -v mysql &> /dev/null; then
    echo -e "${GREEN}✓ Cliente MySQL instalado${NC}"
else
    echo -e "${RED}✗ Cliente MySQL no está instalado${NC}"
    echo -e "${YELLOW}  Instala con: sudo apt-get install mysql-client${NC}"
    exit 1
fi

# Test 4: Conexión MySQL
echo -e "\n${YELLOW}[4/5]${NC} Probando conexión MySQL..."
if mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Conexión MySQL exitosa${NC}"
else
    echo -e "${RED}✗ Error al conectar a MySQL${NC}"
    echo -e "${YELLOW}  Verifica que:${NC}"
    echo "    - Las credenciales sean correctas"
    echo "    - El usuario tenga permisos desde tu IP"
    echo ""
    echo "  Crea el usuario remoto con:"
    echo "    mysql -u root -p"
    echo "    CREATE USER '$USER'@'%' IDENTIFIED BY 'password';"
    echo "    GRANT ALL PRIVILEGES ON $DB.* TO '$USER'@'%';"
    echo "    FLUSH PRIVILEGES;"
    exit 1
fi

# Test 5: Verificar base de datos y tablas
echo -e "\n${YELLOW}[5/5]${NC} Verificando base de datos RADIUS..."
if mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" -e "USE $DB; SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Base de datos '$DB' accesible${NC}"

    # Verificar tablas
    echo -e "\n${BLUE}Verificando tablas...${NC}"

    TABLES=("radcheck" "radreply" "radacct" "radgroupcheck" "radgroupreply")
    MISSING_TABLES=()

    for table in "${TABLES[@]}"; do
        if mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" -e "USE $DB; DESCRIBE $table;" > /dev/null 2>&1; then
            echo -e "${GREEN}  ✓ Tabla '$table' existe${NC}"
        else
            echo -e "${RED}  ✗ Tabla '$table' no existe${NC}"
            MISSING_TABLES+=("$table")
        fi
    done

    if [ ${#MISSING_TABLES[@]} -gt 0 ]; then
        echo -e "\n${RED}Faltan ${#MISSING_TABLES[@]} tabla(s) de RADIUS${NC}"
        echo -e "${YELLOW}Verifica que FreeRADIUS esté correctamente instalado en el servidor remoto${NC}"
    else
        echo -e "\n${GREEN}✓ Todas las tablas RADIUS están presentes${NC}"
    fi

    # Contar usuarios
    USER_COUNT=$(mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" -N -e "USE $DB; SELECT COUNT(DISTINCT username) FROM radcheck;" 2>/dev/null)
    if [ ! -z "$USER_COUNT" ]; then
        echo -e "\n${BLUE}Estadísticas:${NC}"
        echo -e "  Total usuarios: ${GREEN}$USER_COUNT${NC}"
    fi

else
    echo -e "${RED}✗ No se puede acceder a la base de datos '$DB'${NC}"
    echo -e "${YELLOW}  Verifica que:${NC}"
    echo "    - La base de datos existe"
    echo "    - El usuario tiene permisos en esa base de datos"
    exit 1
fi

# Resumen
echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
if [ ${#MISSING_TABLES[@]} -eq 0 ]; then
    echo -e "${GREEN}  ✓ VERIFICACIÓN COMPLETADA EXITOSAMENTE${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
    echo -e "${GREEN}Tu servidor remoto está listo para usar con RADIUS Remote Client${NC}\n"
else
    echo -e "${YELLOW}  ! VERIFICACIÓN COMPLETADA CON ADVERTENCIAS${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
    echo -e "${YELLOW}La conexión funciona pero hay problemas con algunas tablas${NC}\n"
fi

echo -e "${BLUE}Configuración para config.php:${NC}"
echo ""
echo "define('REMOTE_DB_HOST', '$HOST');"
echo "define('REMOTE_DB_PORT', $PORT);"
echo "define('REMOTE_DB_NAME', '$DB');"
echo "define('REMOTE_DB_USER', '$USER');"
echo "define('REMOTE_DB_PASS', 'TU_PASSWORD');"
echo ""
