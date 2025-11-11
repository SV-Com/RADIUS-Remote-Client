# Guía de Instalación - RADIUS Remote Client

Guía detallada para instalar el cliente web remoto de FreeRADIUS.

## Tabla de Contenidos

- [Requisitos Previos](#requisitos-previos)
- [Preparación del Servidor Remoto](#preparación-del-servidor-remoto)
- [Instalación Automática](#instalación-automática)
- [Instalación Manual](#instalación-manual)
- [Configuración Post-Instalación](#configuración-post-instalación)
- [Verificación](#verificación)
- [Solución de Problemas](#solución-de-problemas)

---

## Requisitos Previos

### En el Servidor Cliente (donde se instalará el panel web):

- **Sistema Operativo**: Debian 12 / Ubuntu 22.04+ / Similar
- **Acceso**: Root o sudo
- **Conexión**: Internet (para descargar paquetes)

### En el Servidor FreeRADIUS Remoto:

1. **FreeRADIUS instalado y funcionando**
2. **MySQL/MariaDB con base de datos RADIUS**
3. **Acceso de red** entre ambos servidores

---

## Preparación del Servidor Remoto

### 1. Habilitar Acceso Remoto a MySQL

Edita la configuración de MySQL:

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

Cambia:
```ini
bind-address = 127.0.0.1
```

Por:
```ini
bind-address = 0.0.0.0
```

Reinicia MySQL:
```bash
sudo systemctl restart mariadb
```

### 2. Crear Usuario MySQL Remoto

Conéctate a MySQL:
```bash
mysql -u root -p
```

Crea el usuario (reemplaza `CLIENT_IP` por la IP del servidor cliente):

```sql
-- Opción 1: Permitir desde una IP específica (más seguro)
CREATE USER 'radiusremote'@'CLIENT_IP' IDENTIFIED BY 'password_muy_seguro';
GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'CLIENT_IP';

-- Opción 2: Permitir desde cualquier IP (menos seguro)
CREATE USER 'radiusremote'@'%' IDENTIFIED BY 'password_muy_seguro';
GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'%';

FLUSH PRIVILEGES;
EXIT;
```

### 3. Configurar Firewall

Permite conexiones al puerto MySQL:

```bash
# Desde una IP específica (recomendado)
sudo ufw allow from CLIENT_IP to any port 3306

# O desde cualquier IP
sudo ufw allow 3306/tcp
```

### 4. Verificar Tablas RADIUS

Verifica que existan las tablas necesarias:

```bash
mysql -u root -p radius -e "SHOW TABLES;"
```

Deberías ver:
- `radcheck`
- `radreply`
- `radacct`
- `radgroupcheck`
- `radgroupreply`
- `radusergroup`

---

## Instalación Automática

### Paso 1: Descargar el Instalador

En el servidor cliente:

```bash
wget https://raw.githubusercontent.com/SV-Com/RADIUS-Remote-Client/main/install-remote.sh
```

O clona el repositorio:

```bash
git clone https://github.com/SV-Com/RADIUS-Remote-Client.git
cd RADIUS-Remote-Client
```

### Paso 2: Ejecutar el Instalador

```bash
chmod +x install-remote.sh
sudo bash install-remote.sh
```

### Paso 3: Proporcionar Información

El script te pedirá:

1. **IP del servidor FreeRADIUS**: `192.168.1.100`
2. **Puerto MySQL**: `3306` (default)
3. **Nombre de la BD**: `radius` (default)
4. **Usuario MySQL**: `radiusremote`
5. **Contraseña MySQL**: (tu contraseña segura)
6. **Directorio**: `/var/www/html/radius` (default)

### Paso 4: Completar

El script instalará automáticamente:
- Apache2 y PHP
- Cliente MySQL
- Todos los archivos del proyecto
- Configurará permisos y Apache

Al finalizar, mostrará:
- URL de acceso
- API Key generada
- Información de configuración

---

## Instalación Manual

### 1. Instalar Dependencias

```bash
sudo apt-get update
sudo apt-get install -y apache2 php php-mysql php-curl php-json php-mbstring php-xml libapache2-mod-php mysql-client
```

### 2. Crear Directorio

```bash
sudo mkdir -p /var/www/html/radius
cd /var/www/html/radius
```

### 3. Descargar Archivos

```bash
# Opción 1: Desde GitHub
git clone https://github.com/SV-Com/RADIUS-Remote-Client.git .

# Opción 2: Descargar y descomprimir
wget https://github.com/SV-Com/RADIUS-Remote-Client/archive/main.zip
unzip main.zip
mv RADIUS-Remote-Client-main/* .
rm -rf RADIUS-Remote-Client-main main.zip
```

### 4. Configurar config.php

```bash
sudo nano config.php
```

Edita las siguientes líneas:

```php
define('REMOTE_DB_HOST', '192.168.1.100');  // IP del servidor RADIUS
define('REMOTE_DB_PORT', 3306);
define('REMOTE_DB_NAME', 'radius');
define('REMOTE_DB_USER', 'radiusremote');
define('REMOTE_DB_PASS', 'tu_password_aqui');
```

Genera una API Key:

```bash
openssl rand -hex 32
```

Copia el resultado y pégalo en:

```php
define('API_KEY', 'tu_api_key_generada_aqui');
```

### 5. Configurar Permisos

```bash
sudo chown -R www-data:www-data /var/www/html/radius
sudo chmod -R 755 /var/www/html/radius
sudo chmod 700 /var/www/html/radius/logs
sudo chmod 600 /var/www/html/radius/config.php
```

### 6. Configurar Apache

Crea el archivo de configuración:

```bash
sudo nano /etc/apache2/sites-available/radius.conf
```

Contenido:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/radius
    ServerName radius

    <Directory /var/www/html/radius>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/radius-error.log
    CustomLog ${APACHE_LOG_DIR}/radius-access.log combined
</VirtualHost>
```

Activa el sitio:

```bash
sudo a2ensite radius.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

---

## Configuración Post-Instalación

### 1. Acceder al Panel

Abre tu navegador y ve a:

```
http://TU_IP_CLIENTE/radius/
```

### 2. Iniciar Sesión

Ingresa la API Key que generaste o que el instalador te mostró.

### 3. Verificar Conexión

1. Ve a la pestaña **"Test Conexión"**
2. Haz clic en **"Probar Conexión"**
3. Verifica que muestre: "Conectado"

### 4. Configurar Zona Horaria (Opcional)

Edita `config.php`:

```php
date_default_timezone_set('America/Buenos_Aires');
```

Cambia a tu zona horaria. Lista completa: https://www.php.net/manual/es/timezones.php

### 5. Restringir IPs (Recomendado)

Para mayor seguridad, restringe el acceso a IPs específicas:

```php
define('ALLOWED_IPS', [
    '192.168.1.50',
    '10.0.0.100'
]);
```

---

## Verificación

### Probar Conexión Manualmente

Desde el servidor cliente, ejecuta:

```bash
bash verify-connection.sh
```

Este script verificará:
1. Conectividad de red
2. Puerto MySQL abierto
3. Credenciales correctas
4. Base de datos accesible
5. Tablas RADIUS presentes

### Verificar Logs

```bash
# Logs de Apache
sudo tail -f /var/log/apache2/radius-error.log

# Logs de conexión de la aplicación
sudo tail -f /var/www/html/radius/logs/connections.log
```

### Probar API

```bash
# Test de login
curl -X POST http://localhost/radius/api.php/login \
  -H "Content-Type: application/json" \
  -d '{"api_key": "TU_API_KEY"}'

# Listar usuarios
curl http://localhost/radius/api.php/users?api_key=TU_API_KEY
```

---

## Solución de Problemas

### Error: "Can't connect to MySQL server"

**Causa**: No se puede conectar al servidor remoto.

**Solución**:

1. Verifica que MySQL esté corriendo:
   ```bash
   ssh usuario@servidor-remoto
   sudo systemctl status mariadb
   ```

2. Verifica el bind-address:
   ```bash
   mysql -u root -p -e "SHOW VARIABLES LIKE 'bind_address';"
   ```
   Debería mostrar `0.0.0.0`

3. Prueba la conexión manualmente:
   ```bash
   mysql -h IP_REMOTA -u radiusremote -p
   ```

4. Verifica el firewall:
   ```bash
   sudo ufw status
   ```

### Error: "Access denied for user"

**Causa**: Usuario sin permisos o credenciales incorrectas.

**Solución**:

1. Verifica los permisos del usuario:
   ```sql
   mysql -u root -p
   SELECT user, host FROM mysql.user WHERE user='radiusremote';
   SHOW GRANTS FOR 'radiusremote'@'%';
   ```

2. Si no existen los permisos, créalos:
   ```sql
   GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'%';
   FLUSH PRIVILEGES;
   ```

### Error: "Missing tables"

**Causa**: Base de datos RADIUS incompleta.

**Solución**:

En el servidor FreeRADIUS, recrea las tablas:

```bash
cd /etc/freeradius/3.0/mods-config/sql/main/mysql/
mysql -u root -p radius < schema.sql
```

### Panel Muestra Página en Blanco

**Causa**: Error de PHP.

**Solución**:

1. Verifica los logs de PHP:
   ```bash
   sudo tail -f /var/log/apache2/radius-error.log
   ```

2. Habilita el modo debug en `config.php`:
   ```php
   define('DEBUG_MODE', true);
   ```

3. Verifica permisos:
   ```bash
   ls -la /var/www/html/radius/
   ```

### Conexión Muy Lenta

**Causa**: Latencia de red alta.

**Soluciones**:

1. **Usar túnel SSH comprimido**:
   ```bash
   ssh -C -f -N -L 3307:localhost:3306 usuario@servidor-remoto
   ```

   En `config.php`:
   ```php
   define('USE_SSH_TUNNEL', true);
   define('LOCAL_TUNNEL_PORT', 3307);
   ```

2. **Usar VPN**: Conecta ambos servidores mediante VPN (WireGuard, OpenVPN)

3. **Optimizar MySQL**: Ajusta timeouts en `config.php`:
   ```php
   define('DB_CONNECT_TIMEOUT', 5);
   define('DB_READ_TIMEOUT', 15);
   ```

---

## Seguridad Avanzada

### Túnel SSH Automático

Configura túnel SSH sin contraseña:

1. Genera claves SSH (en servidor cliente):
   ```bash
   ssh-keygen -t rsa -b 4096
   ssh-copy-id usuario@servidor-remoto
   ```

2. Configura en `config.php`:
   ```php
   define('USE_SSH_TUNNEL', true);
   define('SSH_HOST', '192.168.1.100');
   define('SSH_PORT', 22);
   define('SSH_USER', 'usuario');
   define('SSH_KEY_PATH', '/home/usuario/.ssh/id_rsa');
   ```

### HTTPS con Let's Encrypt

```bash
sudo apt-get install certbot python3-certbot-apache
sudo certbot --apache -d tu-dominio.com
```

### Autenticación de Dos Factores

Puedes integrar Google Authenticator u otro sistema 2FA editando `login.php` e `index.php`.

---

## Backup y Mantenimiento

### Backup del Panel

```bash
# Backup de archivos
sudo tar -czf radius-backup-$(date +%Y%m%d).tar.gz /var/www/html/radius/

# Backup solo de config
sudo cp /var/www/html/radius/config.php /root/config.php.backup
```

### Actualización

```bash
cd /var/www/html/radius
sudo git pull origin main
sudo systemctl reload apache2
```

---

## Soporte

- **Issues**: https://github.com/SV-Com/RADIUS-Remote-Client/issues
- **Documentación**: https://github.com/SV-Com/RADIUS-Remote-Client
- **Proyecto Principal**: https://github.com/SV-Com/RADIUS

---

**Nota**: Siempre usa conexiones seguras (SSH tunnel o VPN) en entornos de producción.
