# Guía de Seguridad - RADIUS Remote Client

Esta guía cubre las mejores prácticas de seguridad para el despliegue de RADIUS Remote Client en producción.

## Tabla de Contenidos

- [Resumen de Seguridad](#resumen-de-seguridad)
- [Conexión Segura al Servidor Remoto](#conexión-segura-al-servidor-remoto)
- [Autenticación y Autorización](#autenticación-y-autorización)
- [Configuración de Firewall](#configuración-de-firewall)
- [HTTPS/SSL](#httpsssl)
- [Hardening del Servidor](#hardening-del-servidor)
- [Monitoreo y Auditoría](#monitoreo-y-auditoría)
- [Respaldo y Recuperación](#respaldo-y-recuperación)

---

## Resumen de Seguridad

### Niveles de Seguridad

| Nivel | Descripción | Recomendado para |
|-------|-------------|------------------|
| **Básico** | Conexión directa MySQL | Red local/privada |
| **Intermedio** | SSH Tunnel | Internet con precaución |
| **Avanzado** | VPN + HTTPS + 2FA | Producción/Internet |

### Vectores de Ataque Comunes

1. **Intercepción de tráfico MySQL**: Credenciales en texto plano
2. **Ataques de fuerza bruta**: API Key débil
3. **Inyección SQL**: Inputs no validados
4. **Acceso no autorizado**: Sin restricción de IP
5. **Man-in-the-Middle**: Sin HTTPS

---

## Conexión Segura al Servidor Remoto

### Opción 1: Túnel SSH (Recomendado)

El túnel SSH encripta todo el tráfico MySQL.

#### Configuración Manual

En el servidor cliente, crea el túnel:

```bash
ssh -f -N -L 3307:localhost:3306 usuario@servidor-remoto
```

Parámetros:
- `-f`: Background
- `-N`: No ejecutar comandos remotos
- `-L`: Port forwarding local

Para hacer persistente, crea un servicio systemd:

```bash
sudo nano /etc/systemd/system/ssh-tunnel-radius.service
```

```ini
[Unit]
Description=SSH Tunnel for RADIUS MySQL
After=network.target

[Service]
User=www-data
ExecStart=/usr/bin/ssh -N -L 3307:localhost:3306 -i /var/www/.ssh/id_rsa usuario@servidor-remoto
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Activa el servicio:

```bash
sudo systemctl daemon-reload
sudo systemctl enable ssh-tunnel-radius
sudo systemctl start ssh-tunnel-radius
```

#### Configuración con Claves SSH

1. Genera claves sin contraseña:
   ```bash
   sudo -u www-data ssh-keygen -t ed25519 -f /var/www/.ssh/id_rsa -N ""
   ```

2. Copia la clave al servidor remoto:
   ```bash
   sudo -u www-data ssh-copy-id -i /var/www/.ssh/id_rsa.pub usuario@servidor-remoto
   ```

3. Configura en `config.php`:
   ```php
   define('USE_SSH_TUNNEL', true);
   define('SSH_HOST', '192.168.1.100');
   define('SSH_PORT', 22);
   define('SSH_USER', 'radiustunnel');
   define('SSH_KEY_PATH', '/var/www/.ssh/id_rsa');
   define('LOCAL_TUNNEL_PORT', 3307);
   ```

### Opción 2: VPN (Más Seguro)

Conecta ambos servidores mediante VPN.

#### WireGuard

**En el servidor FreeRADIUS:**

```bash
sudo apt install wireguard
sudo wg genkey | sudo tee /etc/wireguard/server_private.key
sudo cat /etc/wireguard/server_private.key | wg pubkey | sudo tee /etc/wireguard/server_public.key

sudo nano /etc/wireguard/wg0.conf
```

```ini
[Interface]
Address = 10.0.0.1/24
ListenPort = 51820
PrivateKey = CONTENIDO_DE_server_private.key

[Peer]
PublicKey = CLAVE_PUBLICA_DEL_CLIENTE
AllowedIPs = 10.0.0.2/32
```

**En el servidor cliente:**

```bash
sudo apt install wireguard
sudo wg genkey | sudo tee /etc/wireguard/client_private.key
sudo cat /etc/wireguard/client_private.key | wg pubkey | sudo tee /etc/wireguard/client_public.key

sudo nano /etc/wireguard/wg0.conf
```

```ini
[Interface]
Address = 10.0.0.2/24
PrivateKey = CONTENIDO_DE_client_private.key

[Peer]
PublicKey = CLAVE_PUBLICA_DEL_SERVIDOR
Endpoint = IP_SERVIDOR_REMOTO:51820
AllowedIPs = 10.0.0.1/32
PersistentKeepalive = 25
```

Activa en ambos:

```bash
sudo systemctl enable wg-quick@wg0
sudo systemctl start wg-quick@wg0
```

En `config.php` del cliente, usa la IP de la VPN:

```php
define('REMOTE_DB_HOST', '10.0.0.1');
```

### Opción 3: MySQL sobre SSL

Si no puedes usar SSH o VPN, habilita SSL en MySQL.

**En el servidor remoto:**

```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
```

```ini
[mysqld]
ssl-ca=/etc/mysql/ssl/ca-cert.pem
ssl-cert=/etc/mysql/ssl/server-cert.pem
ssl-key=/etc/mysql/ssl/server-key.pem
require_secure_transport=ON
```

Reinicia MySQL:

```bash
sudo systemctl restart mariadb
```

---

## Autenticación y Autorización

### API Key Fuerte

Genera API Keys criptográficamente seguras:

```bash
openssl rand -hex 32
# o
python3 -c "import secrets; print(secrets.token_hex(32))"
```

Ejemplo de API Key fuerte:
```
a7f3e9c2b4d8e1f6a9c3b7d2e5f8a1c4b6d9e2f5a8c1b4d7e0f3a6c9b2d5e8f1
```

### Rotación de API Keys

Crea un sistema de rotación:

```php
// config.php
define('API_KEYS', [
    'a7f3e9c2b4d8e1f6a9c3b7d2e5f8a1c4b6d9e2f5a8c1b4d7e0f3a6c9b2d5e8f1', // Actual
    'b8g4f0d3c5e9f2g7b0d4c8e3f6g9b2d5c7f0e3g6b9c2e5f8b1d4g7f0c3e6b9d2' // Anterior (válida por 7 días)
]);

// Modificar función de autenticación en api.php
private function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return in_array($matches[1], API_KEYS);
    }
    return false;
}
```

### Restricción por IP

Restringe el acceso solo a IPs conocidas:

```php
// config.php
define('ALLOWED_IPS', [
    '192.168.1.50',     // Oficina
    '10.0.0.100',       // VPN
    '203.0.113.45'      // IP pública de administrador
]);
```

La función `checkAllowedIP()` ya está implementada en `config.php`.

### Autenticación de Dos Factores (2FA)

Para mayor seguridad, implementa 2FA.

**Instalar Google Authenticator:**

```bash
sudo apt install libpam-google-authenticator
```

**Integración básica en PHP:**

```php
// Requiere composer require sonata-project/google-authenticator
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

$ga = new GoogleAuthenticator();

// Generar secreto (una sola vez por usuario)
$secret = $ga->generateSecret();

// Verificar código
$code = $_POST['2fa_code'];
if ($ga->checkCode($secret, $code)) {
    // Autenticado
}
```

---

## Configuración de Firewall

### En el Servidor Cliente

Permite solo lo necesario:

```bash
# Política por defecto: denegar
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH
sudo ufw allow 22/tcp

# HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Habilitar
sudo ufw enable
```

### En el Servidor FreeRADIUS Remoto

Permite solo el cliente y RADIUS:

```bash
# Política por defecto: denegar
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH
sudo ufw allow 22/tcp

# MySQL solo desde el cliente
sudo ufw allow from IP_CLIENTE to any port 3306

# RADIUS
sudo ufw allow 1812/udp
sudo ufw allow 1813/udp

# Habilitar
sudo ufw enable
```

### iptables Avanzado

Para mayor control:

```bash
# Limitar intentos de conexión SSH
sudo iptables -A INPUT -p tcp --dport 22 -m state --state NEW -m recent --set
sudo iptables -A INPUT -p tcp --dport 22 -m state --state NEW -m recent --update --seconds 60 --hitcount 4 -j DROP

# Rate limiting para HTTP
sudo iptables -A INPUT -p tcp --dport 80 -m limit --limit 25/minute --limit-burst 100 -j ACCEPT
```

---

## HTTPS/SSL

### Let's Encrypt (Gratuito)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d tu-dominio.com
sudo certbot renew --dry-run
```

### Certificado Autofirmado (Desarrollo)

```bash
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/private/radius-selfsigned.key \
  -out /etc/ssl/certs/radius-selfsigned.crt
```

### Configuración Apache SSL

```apache
<VirtualHost *:443>
    ServerName radius.ejemplo.com
    DocumentRoot /var/www/html/radius

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/radius-selfsigned.crt
    SSLCertificateKeyFile /etc/ssl/private/radius-selfsigned.key

    # Seguridad SSL moderna
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5
    SSLHonorCipherOrder on

    # Headers de seguridad
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"

    <Directory /var/www/html/radius>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Hardening del Servidor

### PHP Hardening

Edita `/etc/php/8.x/apache2/php.ini`:

```ini
; Deshabilitar funciones peligrosas
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Ocultar versión PHP
expose_php = Off

; Límites
max_execution_time = 30
max_input_time = 60
memory_limit = 128M
post_max_size = 8M
upload_max_filesize = 2M

; Sesiones seguras
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_only_cookies = 1
```

### Apache Hardening

```bash
sudo nano /etc/apache2/conf-available/security.conf
```

```apache
ServerTokens Prod
ServerSignature Off
TraceEnable Off

<Directory />
    Options -Indexes -Includes
    AllowOverride None
    Require all denied
</Directory>
```

### Fail2Ban

Protege contra ataques de fuerza bruta:

```bash
sudo apt install fail2ban
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[apache-auth]
enabled = true
port = http,https
logpath = /var/log/apache2/radius-error.log

[apache-badbots]
enabled = true
port = http,https
logpath = /var/log/apache2/radius-access.log
```

```bash
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

---

## Monitoreo y Auditoría

### Logs de Conexión

El sistema ya registra conexiones en `logs/connections.log`.

Ver logs:

```bash
tail -f /var/www/html/radius/logs/connections.log
```

### Monitoreo con Logwatch

```bash
sudo apt install logwatch
sudo logwatch --detail High --service apache --range today --format html > /tmp/apache_report.html
```

### Alertas por Email

Configura alertas para eventos críticos:

```php
// En includes/alerts.php (crear nuevo archivo)
<?php
require_once __DIR__ . '/../config.php';

function sendAlert($subject, $message) {
    $to = 'admin@ejemplo.com';
    $headers = "From: " . SMTP_FROM . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    mail($to, $subject, $message, $headers);
}

// Llamar cuando sea necesario
// sendAlert('Conexión fallida', 'Intento de conexión desde IP: ' . $_SERVER['REMOTE_ADDR']);
?>
```

### Integración con SIEM

Envía logs a un sistema SIEM (Splunk, ELK, Graylog):

```bash
# Configurar rsyslog para enviar logs
sudo nano /etc/rsyslog.d/50-radius.conf
```

```
# Enviar logs de Apache a SIEM
*.* @@siem-server:514
```

---

## Respaldo y Recuperación

### Backup Automático

Crea un script de backup:

```bash
sudo nano /usr/local/bin/backup-radius.sh
```

```bash
#!/bin/bash

BACKUP_DIR="/backup/radius"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup de archivos
tar -czf $BACKUP_DIR/radius-files-$DATE.tar.gz /var/www/html/radius/

# Backup de config
cp /var/www/html/radius/config.php $BACKUP_DIR/config-$DATE.php

# Mantener solo últimos 7 días
find $BACKUP_DIR -name "radius-files-*" -mtime +7 -delete
find $BACKUP_DIR -name "config-*" -mtime +7 -delete

echo "Backup completado: $DATE"
```

```bash
sudo chmod +x /usr/local/bin/backup-radius.sh
```

Programa con cron:

```bash
sudo crontab -e
```

```
0 2 * * * /usr/local/bin/backup-radius.sh >> /var/log/radius-backup.log 2>&1
```

### Recuperación

```bash
# Restaurar archivos
sudo tar -xzf /backup/radius/radius-files-20240101_020000.tar.gz -C /

# Restaurar config
sudo cp /backup/radius/config-20240101_020000.php /var/www/html/radius/config.php

# Permisos
sudo chown -R www-data:www-data /var/www/html/radius
```

---

## Checklist de Seguridad

### Despliegue Inicial

- [ ] Conexión segura (SSH/VPN) configurada
- [ ] API Key fuerte generada
- [ ] HTTPS habilitado
- [ ] Firewall configurado
- [ ] IPs restringidas
- [ ] Logs habilitados
- [ ] Backup automatizado
- [ ] Fail2Ban configurado

### Mantenimiento Regular

- [ ] Actualizar sistema: `sudo apt update && sudo apt upgrade`
- [ ] Revisar logs: `tail -f /var/www/html/radius/logs/connections.log`
- [ ] Verificar backups: `ls -lh /backup/radius/`
- [ ] Rotar API Keys (cada 3-6 meses)
- [ ] Revisar usuarios MySQL remotos
- [ ] Actualizar certificados SSL

---

## Contacto y Soporte

Para reportar vulnerabilidades de seguridad:

- **Email**: security@ejemplo.com
- **GitHub Issues**: https://github.com/SV-Com/RADIUS-Remote-Client/issues

**Nota**: No publiques vulnerabilidades en issues públicos. Envía un email privado primero.

---

**Última actualización**: 2024
