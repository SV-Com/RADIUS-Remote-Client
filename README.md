# Cliente Web Remoto para FreeRADIUS

Sistema web ligero para gestionar usuarios PPPoE de un servidor FreeRADIUS **remoto**. No requiere instalar FreeRADIUS localmente.

## 🎯 Propósito

Este proyecto es ideal para:
- 📡 Gestionar FreeRADIUS que está en otro servidor
- 🌐 Acceder remotamente a la base de datos RADIUS
- 💻 Instalar el panel web en un servidor separado
- 🔒 Mantener separados el servidor RADIUS y el panel de administración
- 🚀 Instalación más rápida (no instala FreeRADIUS)

## 🆚 Diferencias con el Proyecto Principal

| Característica | Proyecto Principal | Cliente Remoto |
|----------------|-------------------|----------------|
| Instala FreeRADIUS | ✅ Sí | ❌ No |
| Instala Base de Datos | ✅ Sí | ❌ No (usa remota) |
| Conexión | Local (localhost) | Remota (IP/Host) |
| Peso | ~500MB | ~100MB |
| Instalación | ~5 min | ~2 min |
| Uso ideal | Servidor todo-en-uno | Cliente separado |

## 📋 Requisitos Previos

### En el Servidor FreeRADIUS (remoto):

1. **FreeRADIUS instalado y funcionando**
2. **MySQL/MariaDB con acceso remoto habilitado:**

```bash
# En el servidor FreeRADIUS, editar MySQL
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf

# Cambiar:
bind-address = 127.0.0.1
# Por:
bind-address = 0.0.0.0

# Reiniciar
sudo systemctl restart mariadb
```

3. **Usuario MySQL con acceso remoto:**

```sql
-- En el servidor FreeRADIUS
mysql -u root -p

-- Crear usuario remoto (reemplaza CLIENT_IP por la IP del cliente)
CREATE USER 'radiusremote'@'CLIENT_IP' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'CLIENT_IP';
FLUSH PRIVILEGES;

-- O permitir desde cualquier IP (menos seguro)
CREATE USER 'radiusremote'@'%' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'%';
FLUSH PRIVILEGES;
```

4. **Firewall permitir puerto 3306:**

```bash
# En el servidor FreeRADIUS
sudo ufw allow from CLIENT_IP to any port 3306
# O abrir para todas las IPs (menos seguro)
sudo ufw allow 3306/tcp
```

### En el Cliente Web (este servidor):

- Debian 12 o Ubuntu 22.04+
- Apache2 y PHP 8.x
- Conexión de red al servidor FreeRADIUS

## 🚀 Instalación Rápida

### Método 1: Instalación Automática (Recomendado)

```bash
# Descargar instalador
wget https://raw.githubusercontent.com/SV-Com/RADIUS-Remote-Client/main/install-remote.sh

# Ejecutar
chmod +x install-remote.sh
sudo bash install-remote.sh
```

El script te preguntará:
- 🖥️ **IP del servidor FreeRADIUS**
- 🗄️ **Puerto MySQL** (default: 3306)
- 📊 **Nombre de la base de datos** (default: radius)
- 👤 **Usuario MySQL**
- 🔑 **Contraseña MySQL**

### Método 2: Instalación Manual

Ver [INSTALL_GUIDE.md](INSTALL_GUIDE.md) para instrucciones detalladas.

## ✨ Características

### Gestión de Usuarios
- ✅ Crear usuarios PPPoE
- ✅ Editar usuarios existentes
- ✅ Eliminar usuarios
- ✅ Buscar usuarios
- ✅ Exportar a CSV/Excel

### Monitoreo
- 📊 Estadísticas en tiempo real
- 📈 Gráficos de uso de ancho de banda
- 📜 Historial de conexiones por usuario
- 🔴 Sesiones activas

### Avanzado
- 🔗 Webhooks para integraciones
- 👥 Sistema de roles (Admin, Operator, Viewer)
- 📧 Notificaciones por email
- 📝 Audit log completo
- 🔐 Autenticación con API Key

### Compatible con
- 🔧 Equipos Huawei (NE8000-F1A)
- 🌐 Cualquier servidor FreeRADIUS con MySQL/MariaDB

## 🔧 Configuración

### Archivo de Configuración: `config.php`

```php
<?php
// Servidor FreeRADIUS Remoto
define('REMOTE_DB_HOST', '192.168.1.100');  // IP del servidor RADIUS
define('REMOTE_DB_PORT', 3306);             // Puerto MySQL
define('REMOTE_DB_NAME', 'radius');         // Nombre BD
define('REMOTE_DB_USER', 'radiusremote');   // Usuario remoto
define('REMOTE_DB_PASS', 'password');       // Contraseña

// Autenticación del Panel
define('API_KEY', 'tu_api_key_segura');

// Opcional: Configurar túnel SSH para mayor seguridad
define('USE_SSH_TUNNEL', false);
define('SSH_HOST', '192.168.1.100');
define('SSH_PORT', 22);
define('SSH_USER', 'usuario_ssh');
define('SSH_KEY_PATH', '/path/to/private_key');
?>
```

## 🔒 Seguridad

### Conexión SSH Tunnel (Recomendado)

Para mayor seguridad, usa túnel SSH en lugar de conexión directa:

```bash
# En el cliente, crear túnel SSH
ssh -f -N -L 3307:localhost:3306 usuario@servidor-radius

# En config.php
define('REMOTE_DB_HOST', '127.0.0.1');
define('REMOTE_DB_PORT', 3307);
define('USE_SSH_TUNNEL', true);
```

### VPN (Más Seguro)

Conecta ambos servidores mediante VPN:
- WireGuard
- OpenVPN
- IPSec

### Firewall

Restringe acceso solo a tu IP:

```bash
# En servidor FreeRADIUS
sudo ufw allow from CLIENT_IP to any port 3306
```

## 📊 Uso

1. **Acceder al panel:**
   ```
   http://tu-servidor-cliente/radius/
   ```

2. **Ingresar API Key**

3. **Gestionar usuarios:**
   - Crear: Click en "➕ Crear Usuario"
   - Editar: Click en "✏️ Editar"
   - Eliminar: Click en "🗑️ Eliminar"
   - Ver historial: Click en "📊"

4. **Exportar datos:**
   - Click en "📥 Exportar CSV"

## 🔌 API Endpoints

Todos los endpoints funcionan igual que en el proyecto principal:

```bash
POST   /api.php/login          # Autenticación
GET    /api.php/users          # Listar usuarios
POST   /api.php/users          # Crear usuario
GET    /api.php/user           # Obtener usuario
PUT    /api.php/user           # Actualizar usuario
DELETE /api.php/user           # Eliminar usuario
GET    /api.php/stats          # Estadísticas
GET    /api.php/export         # Exportar CSV
GET    /api.php/history        # Historial conexiones
GET    /api.php/bandwidth-stats # Estadísticas bandwidth
GET    /api.php/webhooks       # Gestionar webhooks
```

### Ejemplo: Crear Usuario

```bash
curl -X POST "http://tu-cliente/radius/api.php/users" \
  -H "Authorization: Bearer tu_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "cliente@fibra",
    "password": "pass123",
    "bandwidth_up": "50M",
    "bandwidth_down": "50M"
  }'
```

## 🧪 Verificar Conexión

### Probar conexión al servidor remoto:

```bash
# Desde el cliente web
mysql -h IP_SERVIDOR_RADIUS -u radiusremote -p radius

# Si funciona, verás:
mysql> SELECT COUNT(*) FROM radcheck;
```

### Script de verificación:

```bash
wget https://raw.githubusercontent.com/SV-Com/RADIUS-Remote-Client/main/verify-connection.sh
bash verify-connection.sh
```

## 🛠️ Mantenimiento

### Backup (En servidor FreeRADIUS)

```bash
# Crear backup
mysqldump -u root -p radius > radius_backup_$(date +%Y%m%d).sql
```

### Monitoreo de Conexión

```bash
# Ver conexiones activas desde el cliente
mysql -h IP_RADIUS -u radiusremote -p -e "SHOW PROCESSLIST;"
```

### Logs

```bash
# Cliente web
sudo tail -f /var/log/apache2/radius-error.log

# Servidor FreeRADIUS (remoto)
ssh usuario@servidor-radius 'tail -f /var/log/freeradius/radius.log'
```

## ⚠️ Troubleshooting

### Error: "Can't connect to MySQL server"

**Causas:**
1. MySQL no acepta conexiones remotas
2. Firewall bloqueando puerto 3306
3. Credenciales incorrectas
4. IP incorrecta

**Solución:**
```bash
# 1. Verificar que MySQL escucha en 0.0.0.0
mysql -u root -p -e "SHOW VARIABLES LIKE 'bind_address';"

# 2. Verificar firewall
sudo ufw status

# 3. Probar conexión
mysql -h IP_SERVIDOR -u usuario -p

# 4. Ver logs MySQL
sudo tail -f /var/log/mysql/error.log
```

### Error: "Access denied for user"

**Solución:**
```sql
-- En servidor FreeRADIUS
GRANT ALL PRIVILEGES ON radius.* TO 'usuario'@'IP_CLIENTE';
FLUSH PRIVILEGES;
```

### Conexión lenta

**Solución:**
1. Usar túnel SSH comprimido:
```bash
ssh -C -f -N -L 3307:localhost:3306 usuario@servidor
```

2. Verificar latencia:
```bash
ping IP_SERVIDOR_RADIUS
```

## 🔄 Actualizar

```bash
cd /var/www/html/radius
git pull origin main
sudo systemctl reload apache2
```

## 📚 Documentación Adicional

- [Guía de Instalación Completa](INSTALL_GUIDE.md)
- [Configuración de Seguridad](SECURITY.md)
- [Configurar Túnel SSH](SSH_TUNNEL.md)
- [API Reference](API.md)

## 🤝 Contribuir

1. Fork el repositorio
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit: `git commit -am 'Agregar funcionalidad'`
4. Push: `git push origin feature/nueva-funcionalidad`
5. Pull Request

## 📄 Licencia

Código abierto. Libre para usar y modificar.

## 🆘 Soporte

- **Issues**: https://github.com/SV-Com/RADIUS-Remote-Client/issues
- **Proyecto Principal**: https://github.com/SV-Com/RADIUS
- **Documentación FreeRADIUS**: https://freeradius.org/

---

**Desarrollado para facilitar la gestión remota de servidores FreeRADIUS**

⚠️ **Nota de Seguridad**: Siempre usa conexiones seguras (SSH tunnel o VPN) en entornos de producción.
