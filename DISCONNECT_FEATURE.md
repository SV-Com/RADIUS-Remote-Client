# Funcionalidad de Reconexión de Dispositivos

## 🔌 Descripción

La funcionalidad de **Reconectar** permite desconectar forzosamente a un usuario PPPoE desde el panel web, obligando al dispositivo (ONU) a reconectarse automáticamente.

**Casos de uso:**
- ✅ Aplicar cambios de velocidad inmediatamente
- ✅ Aplicar cambios de plan sin esperar a que el usuario se desconecte
- ✅ Resolver problemas de conexión (forzar renegociación PPPoE)
- ✅ Verificar que el usuario puede reconectarse correctamente
- ✅ Aplicar cambios de configuración de FreeRADIUS

---

## 🎯 Cómo Funciona

### Flujo de Desconexión

```
1. Usuario hace click en botón "🔌 Reconectar"
   ↓
2. Panel envía petición POST /api.php/disconnect
   ↓
3. Backend busca sesión activa en radacct
   ↓
4. Se cierra la sesión con acctterminatecause='Admin-Disconnect'
   ↓
5. [Opcional] Se envía paquete RADIUS Disconnect (DM)
   ↓
6. NE8000 recibe la señal y cierra la sesión PPPoE
   ↓
7. ONU detecta desconexión y reintenta automáticamente
   ↓
8. Usuario se reautentica con FreeRADIUS
   ↓
9. Se aplican nuevos parámetros (velocidad, plan, etc.)
```

---

## 💻 Uso en el Panel Web

### Reconectar Usuario

1. Ir a: http://tu-servidor/radius/
2. En la lista de usuarios, buscar el usuario conectado
3. Click en botón **"🔌 Reconectar"** (solo visible si está activo)
4. Confirmar en el diálogo
5. El usuario se desconectará y reconectará automáticamente (5-15 segundos)

**Nota:** El botón solo aparece en usuarios que tienen sesión activa (`last_connection` != null).

---

## 🔧 Métodos de Desconexión

El sistema soporta dos métodos:

### Método 1: Soft Disconnect (Por Defecto)

**Cómo funciona:**
- Cierra la sesión en la tabla `radacct`
- Marca `acctterminatecause = 'Admin-Disconnect'`
- NO envía paquete RADIUS al NAS

**Ventajas:**
- ✅ No requiere configuración adicional
- ✅ Funciona en cualquier entorno
- ✅ Actualiza el estado en la BD inmediatamente

**Desventajas:**
- ⚠️ La desconexión real depende del timeout del NAS
- ⚠️ Puede tardar hasta 5-10 minutos en desconectarse realmente

**Uso:**
- Por defecto habilitado
- No requiere configuración

---

### Método 2: RADIUS Disconnect (DM) - Recomendado

**Cómo funciona:**
- Envía paquete RADIUS Disconnect-Message (DM) al NAS
- El NAS desconecta inmediatamente al usuario
- Requiere `radclient` instalado

**Ventajas:**
- ✅ Desconexión inmediata (1-5 segundos)
- ✅ Más confiable
- ✅ Permite aplicar cambios en tiempo real

**Desventajas:**
- ⚠️ Requiere configurar FreeRADIUS con CoA/DM
- ⚠️ Requiere `radclient` instalado en el servidor web

---

## ⚙️ Configuración para RADIUS Disconnect (Recomendado)

### Paso 1: Instalar radclient

En el servidor del panel web:

```bash
# Debian/Ubuntu
sudo apt update
sudo apt install freeradius-utils

# Verificar instalación
which radclient
radclient -v
```

### Paso 2: Configurar en config.php

Editar `/var/www/html/radius/config.php`:

```php
// Habilitar RADIUS Disconnect (DM)
define('ENABLE_RADCLIENT', true);

// IP del servidor FreeRADIUS (o del NAS directamente)
define('RADIUS_SERVER', '192.168.1.100');

// Puerto CoA/DM (default: 3799)
define('RADIUS_PORT', 3799);

// Secret compartido con FreeRADIUS
define('RADIUS_SECRET', 'tu_secret_radius');
```

### Paso 3: Configurar FreeRADIUS para CoA/DM

En el servidor FreeRADIUS, editar `/etc/freeradius/3.0/sites-enabled/default`:

```apache
# Habilitar servidor CoA/DM
listen {
    type = coa
    ipaddr = *
    port = 3799
    server = coa
}
```

Editar `/etc/freeradius/3.0/sites-enabled/coa`:

```apache
server coa {
    listen {
        type = coa
        ipaddr = *
        port = 3799
    }

    recv-coa {
        ok
    }

    send-coa {
        ok
    }
}
```

Configurar cliente autorizado en `/etc/freeradius/3.0/clients.conf`:

```apache
# Panel web autorizado para enviar DM
client panel-web {
    ipaddr = 192.168.1.50  # IP del servidor del panel
    secret = tu_secret_radius
    shortname = panel-radius
    nas_type = other
    coa_server = coa
}
```

Reiniciar FreeRADIUS:

```bash
sudo systemctl restart freeradius
```

### Paso 4: Configurar NE8000 para Aceptar CoA/DM

```cisco
# En NE8000, habilitar CoA
aaa
  domain pppoe
    radius-server coa enable
    radius-server coa port 3799

# Configurar servidor RADIUS como CoA server
radius-server template RADIUS_TEMPLATE
  radius-server coa-server IP_FREERADIUS 3799 shared-key cipher TU_SECRET
```

### Paso 5: Probar Desconexión

```bash
# Desde el servidor del panel, probar manualmente
echo 'Acct-Session-Id="SESSION_ID_AQUI"' | \
  radclient -x 192.168.1.100:3799 disconnect tu_secret_radius

# Debería responder:
# Received Disconnect-ACK Id 123
```

---

## 🧪 Verificación

### 1. Probar desde Panel Web

1. Conectar un usuario PPPoE
2. Verificar que aparece en "Usuarios" como "Activo"
3. Click en "🔌 Reconectar"
4. Observar:
   - Mensaje de confirmación
   - Mensaje de éxito
   - Usuario debería reconectarse en 5-15 segundos

### 2. Verificar en Base de Datos

```sql
-- Ver sesiones cerradas por Admin
SELECT username, acctstarttime, acctstoptime, acctterminatecause
FROM radacct
WHERE acctterminatecause = 'Admin-Disconnect'
ORDER BY acctstoptime DESC
LIMIT 10;
```

### 3. Verificar Logs de FreeRADIUS

```bash
# En servidor FreeRADIUS
tail -f /var/log/freeradius/radius.log

# Buscar:
# Received Disconnect-Request
# Sending Disconnect-ACK
```

### 4. Verificar en NE8000

```cisco
# Ver sesiones activas
display subscriber session username cliente@fibra

# Ver logs de CoA
display logbuffer | include CoA
```

---

## 📊 API Endpoint

### POST /api.php/disconnect

**Headers:**
```
Authorization: Bearer TU_API_KEY
Content-Type: application/json
```

**Body:**
```json
{
  "username": "cliente@fibra"
}
```

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "data": {
    "message": "User disconnected successfully",
    "username": "cliente@fibra",
    "session_closed": true,
    "reconnect_required": true
  }
}
```

**Error - Usuario no conectado (404):**
```json
{
  "success": false,
  "error": "User is not connected"
}
```

**Ejemplo con cURL:**
```bash
curl -X POST "http://tu-servidor/radius/api.php/disconnect" \
  -H "Authorization: Bearer TU_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"username": "cliente@fibra"}'
```

---

## ⚠️ Troubleshooting

### Botón "Reconectar" no aparece

**Causa:** Usuario no tiene sesión activa en `radacct`.

**Solución:**
```sql
-- Verificar si hay sesión
SELECT * FROM radacct
WHERE username = 'cliente@fibra'
  AND acctstoptime IS NULL;
```

Si no hay sesión pero el usuario está conectado:
- FreeRADIUS no está registrando accounting
- Verificar módulo `sql` en FreeRADIUS

---

### Error: "User is not connected"

**Causa:** No hay sesión activa en `radacct`.

**Solución:**
1. Verificar que el usuario esté realmente conectado
2. Verificar accounting en FreeRADIUS:
```bash
tail -f /var/log/freeradius/radius.log | grep Accounting
```

---

### Desconexión no funciona (Método 2)

**Causa:** `radclient` no está instalado o mal configurado.

**Solución:**
```bash
# Verificar instalación
which radclient

# Probar manualmente
echo 'Test-Message="hello"' | \
  radclient IP_RADIUS:3799 disconnect SECRET
```

---

### Usuario no se reconecta automáticamente

**Causa:** ONT/ONU no está configurada para reconexión automática.

**Solución:**
- En Huawei ONT: Verificar que tiene "auto-reconnect" habilitado
- Esperar timeout de PPPoE (usualmente 30-60 segundos)
- Reiniciar manualmente la ONU

---

## 🔐 Seguridad

### Consideraciones

1. **Solo administradores:** La función de desconectar debe ser solo para usuarios autenticados
2. **Rate limiting:** Evitar desconexiones masivas (DoS)
3. **Audit log:** Registrar quién desconectó a quién

### Implementar Audit Log

Editar `config.php` para loggear desconexiones:

```php
// En función logConnection():
logConnection('USER_DISCONNECT', "Admin disconnected user: $username");
```

Ver logs:

```bash
tail -f /var/www/html/radius/logs/connections.log | grep DISCONNECT
```

---

## 📈 Casos de Uso Avanzados

### 1. Aplicar Cambio de Velocidad Inmediatamente

```
1. Editar usuario → Cambiar velocidad 50M → 100M
2. Guardar
3. Click "🔌 Reconectar"
4. Usuario se reconecta con nueva velocidad (100M)
```

### 2. Migrar Usuario de Plan

```
1. Editar usuario → Cambiar plan "Plan 50MB" → "Plan 100MB"
2. Guardar
3. Click "🔌 Reconectar"
4. Usuario se reconecta con velocidades del nuevo plan
```

### 3. Desconexión Masiva (API)

```bash
# Script para desconectar múltiples usuarios
for user in cliente1@fibra cliente2@fibra cliente3@fibra; do
  curl -X POST "http://panel/radius/api.php/disconnect" \
    -H "Authorization: Bearer API_KEY" \
    -H "Content-Type: application/json" \
    -d "{\"username\": \"$user\"}"
  sleep 2
done
```

---

## 📝 Notas Importantes

1. **Sin CoA habilitado:** La desconexión puede tardar 5-10 minutos
2. **Con CoA habilitado:** La desconexión es inmediata (1-5 segundos)
3. **Reconexión automática:** Depende de la configuración de la ONU
4. **Logs:** Todas las desconexiones se registran en `radacct` con `acctterminatecause='Admin-Disconnect'`

---

**Documentación creada:** 2025-11-16
**Compatible con:** FreeRADIUS 3.0+, Huawei NE8000-F1A
**Requiere:** radclient (opcional pero recomendado)
