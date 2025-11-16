# Registro de Correcciones - RADIUS Remote Client

## Fecha: 2025-11-16

---

## 🐛 Problema 1: Error de Sintaxis SQL

### Síntoma
```
SQLSTATE[42000]: Syntax error or access violation: 1064
You have an error in your SQL syntax near '?' at line 1
```

### Causa Raíz
En `includes/db.php:172`, la consulta `SHOW TABLES LIKE ?` no soporta placeholders con prepared statements en MySQL.

### Código Incorrecto
```php
$stmt = $conn->prepare("SHOW TABLES LIKE ?");
$stmt->execute([TABLE_PREFIX . $table]);
```

### Solución Aplicada
Reemplazada por consulta a `information_schema.tables` que SÍ soporta prepared statements:

```php
$tableName = TABLE_PREFIX . $table;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
$stmt->execute([$tableName]);
$result = $stmt->fetch();
if ($result['count'] == 0) {
    $missingTables[] = $tableName;
}
```

### Archivo Modificado
- `includes/db.php` (líneas 171-179)

---

## 🐛 Problema 2: Error de Autenticación "Unauthorized"

### Síntoma
```
Error al cargar usuarios: Unauthorized
```

### Causa Raíz
El archivo `js/app.js` NO enviaba el header `Authorization: Bearer API_KEY` en las peticiones fetch a `api.php`. La API requería autenticación pero el frontend no la proporcionaba.

### Análisis
1. El usuario inicia sesión en `index.php` mediante sesión PHP
2. El JavaScript hace peticiones AJAX a `api.php`
3. `api.php` solo verificaba el header `Authorization`, no las sesiones PHP
4. Todas las peticiones eran rechazadas con código 401 Unauthorized

### Solución Aplicada
Modificado el método `authenticate()` en `api.php` para aceptar AMBOS métodos de autenticación:

**1. Agregado `session_start()` en api.php:**
```php
// Iniciar sesión para autenticación web
session_start();
```

**2. Modificado método `authenticate()`:**
```php
private function authenticate() {
    // Verificar sesión PHP (desde panel web)
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return true;
    }

    // Verificar header Authorization (API externa)
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1] === API_KEY;
    }

    // También permitir API key en query string para desarrollo
    return isset($_GET['api_key']) && $_GET['api_key'] === API_KEY;
}
```

### Archivos Modificados
- `api.php` (líneas 20-21 y 127-143)

### Ventajas de Esta Solución
✅ **Seguridad:** No expone el API_KEY en el código JavaScript
✅ **Compatibilidad:** Mantiene soporte para API externa con Bearer token
✅ **Simplicidad:** No requiere modificar el código JavaScript
✅ **Sesiones:** Aprovecha el sistema de sesiones PHP existente

---

## 📋 Archivos Nuevos Creados

### 1. `test-connection.php`
Script de diagnóstico completo para verificar:
- Configuración de `config.php`
- Conectividad TCP al servidor MySQL remoto
- Autenticación MySQL
- Existencia de tablas RADIUS
- Permisos del usuario (INSERT, SELECT, DELETE)

**Uso:**
```bash
php test-connection.php
```

### 2. `FIXES.md` (este archivo)
Documentación de todos los problemas encontrados y soluciones aplicadas.

---

## ✅ Verificación de la Solución

### Pasos para Verificar:

1. **Verificar conexión a base de datos:**
   ```bash
   php test-connection.php
   ```

2. **Acceder al panel web:**
   ```
   http://localhost/radius/index.php
   ```

3. **Iniciar sesión:**
   - Ingresar el API_KEY configurado en `config.php`

4. **Verificar funcionalidad:**
   - ✓ Cargar lista de usuarios (debe funcionar sin error "Unauthorized")
   - ✓ Crear nuevo usuario
   - ✓ Editar usuario existente
   - ✓ Eliminar usuario
   - ✓ Ver estadísticas
   - ✓ Ver sesiones activas

---

## 🔧 Configuración Necesaria

### En el Servidor MySQL Remoto

**1. Permitir conexiones remotas:**
```bash
sudo nano /etc/mysql/mariadb.conf.d/50-server.cnf
# Cambiar: bind-address = 0.0.0.0
sudo systemctl restart mysql
```

**2. Crear usuario con permisos remotos:**
```sql
mysql -u root -p
CREATE USER 'radiusremote'@'%' IDENTIFIED BY 'password_seguro';
GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'%';
FLUSH PRIVILEGES;
```

**3. Abrir firewall:**
```bash
sudo ufw allow 3306/tcp
```

### En el Cliente (Este Servidor)

**Actualizar `config.php`:**
```php
define('REMOTE_DB_HOST', '192.168.1.100');  // IP del servidor RADIUS
define('REMOTE_DB_USER', 'radiusremote');
define('REMOTE_DB_PASS', 'password_seguro');
define('API_KEY', 'clave_aleatoria_generada');  // Genera con: openssl rand -hex 32
```

---

## 📊 Resumen

| Problema | Estado | Archivo Afectado |
|----------|--------|------------------|
| Error SQL `SHOW TABLES LIKE ?` | ✅ Corregido | `includes/db.php` |
| Error "Unauthorized" en API | ✅ Corregido | `api.php` |
| Falta script de diagnóstico | ✅ Creado | `test-connection.php` |

---

## 🔐 Consideraciones de Seguridad

1. **API_KEY:** Debe ser una clave aleatoria fuerte (32+ caracteres)
   ```bash
   openssl rand -hex 32
   ```

2. **Conexión Remota:** Para producción, se recomienda usar:
   - Túnel SSH
   - VPN (WireGuard, OpenVPN)
   - Firewall restrictivo (solo IP del cliente)

3. **HTTPS:** Configurar certificado SSL en Apache para proteger las sesiones PHP

---

## 📝 Notas Adicionales

- Las sesiones PHP expiran automáticamente según la configuración de PHP
- El API_KEY sigue siendo necesario para peticiones API externas
- El sistema ahora soporta autenticación dual: sesiones web + API tokens
- Todos los cambios son retrocompatibles con el código existente

---

**Desarrollado por:** Claude Code Assistant
**Fecha:** 2025-11-16
