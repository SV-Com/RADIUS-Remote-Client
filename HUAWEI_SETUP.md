# Configuración para Equipos Huawei

## 🎯 Descripción

Esta guía es para usar RADIUS Remote Client con equipos **Huawei**, específicamente:
- **ONUs** Huawei conectadas por FTTH
- **NE8000-F1A** como BRAS/BNG
- **PPPoE** para autenticación
- **FreeRADIUS** en servidor remoto

---

## ⚙️ Configuración del Panel

### Paso 1: Editar `config.php`

```bash
nano /var/www/html/radius/config.php
```

Busca la línea `NAS_TYPE` y asegúrate que esté en **'huawei'**:

```php
// Tipo de equipo NAS (Network Access Server)
// Opciones: 'mikrotik', 'huawei', 'cisco'
define('NAS_TYPE', 'huawei');
```

**¡IMPORTANTE!** Ya está configurado por defecto en 'huawei'.

---

## 📊 Atributos RADIUS para Huawei

El panel usa automáticamente estos atributos según el `NAS_TYPE`:

| Atributo RADIUS | Uso | Formato |
|-----------------|-----|---------|
| `Huawei-Input-Average-Rate` | Velocidad promedio de subida | bps (bits por segundo) |
| `Huawei-Output-Average-Rate` | Velocidad promedio de bajada | bps (bits por segundo) |
| `Huawei-Input-Peak-Rate` | Velocidad pico de subida | bps |
| `Huawei-Output-Peak-Rate` | Velocidad pico de bajada | bps |

### Conversión de Velocidades

El panel convierte automáticamente entre formatos:

| Formato Humano | Valor en bps | Ejemplo en BD |
|----------------|--------------|---------------|
| 10M | 10,000,000 | `10000000` |
| 50M | 52,428,800 | `52428800` |
| 100M | 104,857,600 | `104857600` |
| 1G | 1,073,741,824 | `1073741824` |

**En el panel escribes:** `50M`
**Se guarda en la BD como:** `52428800` (bps)

---

## 🚀 Crear un Plan para Huawei

### Desde el Panel Web

1. Ir a: http://tu-servidor/radius/
2. Click en tab **"Planes"**
3. Click **"+ Crear Plan"**
4. Completar:
   ```
   Nombre: Plan 50MB
   Subida: 50M
   Bajada: 50M
   Pool: (opcional)
   ```
5. Guardar

### Lo que se Guarda en la BD

```sql
-- radgroupreply
INSERT INTO radgroupreply (groupname, attribute, op, value) VALUES
('Plan 50MB', 'Huawei-Input-Average-Rate', ':=', '52428800'),
('Plan 50MB', 'Huawei-Output-Average-Rate', ':=', '52428800'),
('Plan 50MB', 'Huawei-Input-Peak-Rate', ':=', '52428800'),
('Plan 50MB', 'Huawei-Output-Peak-Rate', ':=', '52428800');

-- radgroupcheck
INSERT INTO radgroupcheck (groupname, attribute, op, value) VALUES
('Plan 50MB', 'Auth-Type', ':=', 'Accept');
```

---

## 👥 Asignar Plan a Usuario

### Opción 1: Desde el Panel

1. Editar usuario existente
2. En campo **"Plan"** escribir: `Plan 50MB`
3. Guardar

### Opción 2: SQL Directo

```sql
-- Asignar usuario a plan
INSERT INTO radusergroup (username, groupname, priority)
VALUES ('cliente@fibra', 'Plan 50MB', 1);
```

---

## 🔧 Configuración en FreeRADIUS

### Verificar Diccionario Huawei

```bash
# Verificar que FreeRADIUS tiene el diccionario Huawei
ls /usr/share/freeradius/dictionary.huawei

# Si no existe, descargarlo
wget -O /usr/share/freeradius/dictionary.huawei \
  https://raw.githubusercontent.com/FreeRADIUS/freeradius-server/v3.0.x/share/dictionary.huawei
```

### Incluir Diccionario

Editar `/etc/freeradius/3.0/dictionary`:

```bash
nano /etc/freeradius/3.0/dictionary
```

Agregar al final:

```
$INCLUDE dictionary.huawei
```

Reiniciar FreeRADIUS:

```bash
systemctl restart freeradius
```

---

## 🌐 Configuración en NE8000-F1A

### Configurar RADIUS Client

```cisco
# Entrar en modo configuración
system-view

# Configurar servidor RADIUS
radius-server template RADIUS_TEMPLATE
  radius-server authentication IP_FREERADIUS 1812
  radius-server accounting IP_FREERADIUS 1813
  radius-server shared-key cipher TU_SECRET_KEY
  radius-server retransmit 3
  radius-server timeout 5

# Aplicar en dominio PPPoE
aaa
  domain pppoe
    authentication-scheme radius
    accounting-scheme radius
    radius-server RADIUS_TEMPLATE
```

### Configurar Template de Subscripción

```cisco
# Crear subscriber profile para aplicar rate-limit
subscriber-profile name DEFAULT_PROFILE
  user-queue cir 52428800    # 50 Mbps bajada
  user-queue pir 52428800    # 50 Mbps bajada (pico)
  user-queue inbound cir 52428800   # 50 Mbps subida
  user-queue inbound pir 52428800   # 50 Mbps subida (pico)
```

**IMPORTANTE:** Los valores de rate-limit se reciben desde RADIUS y sobrescriben el profile por defecto.

---

## 📋 Verificación

### 1. Probar Creación de Plan

```bash
# Desde el panel:
# - Crear plan "Test 10MB" con 10M/10M

# Verificar en BD:
mysql -u root -p radius

SELECT * FROM radgroupreply WHERE groupname = 'Test 10MB';
```

Deberías ver:

```
+----+------------+-----------------------------+----+----------+
| id | groupname  | attribute                   | op | value    |
+----+------------+-----------------------------+----+----------+
|  1 | Test 10MB  | Huawei-Input-Average-Rate   | := | 10485760 |
|  2 | Test 10MB  | Huawei-Output-Average-Rate  | := | 10485760 |
|  3 | Test 10MB  | Huawei-Input-Peak-Rate      | := | 10485760 |
|  4 | Test 10MB  | Huawei-Output-Peak-Rate     | := | 10485760 |
+----+------------+-----------------------------+----+----------+
```

### 2. Probar Asignación a Usuario

```sql
-- Crear usuario test
INSERT INTO radcheck (username, attribute, op, value)
VALUES ('test@fibra', 'Cleartext-Password', ':=', 'test123');

-- Asignar a plan
INSERT INTO radusergroup (username, groupname, priority)
VALUES ('test@fibra', 'Test 10MB', 1);

-- Verificar
SELECT rc.username, ug.groupname, gr.attribute, gr.value
FROM radcheck rc
JOIN radusergroup ug ON rc.username = ug.username
JOIN radgroupreply gr ON ug.groupname = gr.groupname
WHERE rc.username = 'test@fibra';
```

### 3. Probar Autenticación PPPoE

```bash
# En servidor FreeRADIUS, modo debug
freeradius -X

# En el NE8000, verificar que usuario se autentica
# Desde ONU cliente, conectar PPPoE con test@fibra/test123
```

Deberías ver en el debug de FreeRADIUS:

```
Sending Access-Accept
  Huawei-Input-Average-Rate = 10485760
  Huawei-Output-Average-Rate = 10485760
  Huawei-Input-Peak-Rate = 10485760
  Huawei-Output-Peak-Rate = 10485760
```

---

## 🔄 Migrar Usuarios Existentes

Si ya tienes usuarios configurados con otros atributos:

### Paso 1: Ver Distribución Actual

```sql
SELECT attribute, value, COUNT(*) as usuarios
FROM radreply
WHERE attribute LIKE '%Rate%'
GROUP BY attribute, value
ORDER BY usuarios DESC;
```

### Paso 2: Crear Planes en el Panel

Según lo que viste, crea planes:
- Plan 10MB → 10M/10M
- Plan 50MB → 50M/50M
- Plan 100MB → 100M/100M

### Paso 3: Asignar Usuarios a Planes

```sql
-- Ejemplo: Usuarios con Input-Average-Rate de 52428800 (50M)
INSERT INTO radusergroup (username, groupname, priority)
SELECT DISTINCT rr.username, 'Plan 50MB', 1
FROM radreply rr
WHERE rr.attribute = 'Huawei-Input-Average-Rate'
  AND rr.value = '52428800'
  AND NOT EXISTS (
    SELECT 1 FROM radusergroup ug WHERE ug.username = rr.username
  );
```

### Paso 4: Limpiar Atributos Individuales (Opcional)

```sql
-- CUIDADO: Hacer backup primero
DELETE FROM radreply
WHERE attribute IN (
  'Huawei-Input-Average-Rate',
  'Huawei-Output-Average-Rate',
  'Huawei-Input-Peak-Rate',
  'Huawei-Output-Peak-Rate'
)
AND username IN (SELECT username FROM radusergroup);
```

---

## ⚠️ Troubleshooting

### Error: "Atributos no aplicados en NE8000"

**Causa:** NE8000 no reconoce los atributos Huawei.

**Solución:**
1. Verificar diccionario en FreeRADIUS: `/usr/share/freeradius/dictionary.huawei`
2. Reiniciar FreeRADIUS: `systemctl restart freeradius`
3. En modo debug, verificar que se envían los atributos

### Error: "Velocidad no limitada"

**Causa:** NE8000 no está aplicando los rate-limits.

**Solución:**

```cisco
# Verificar configuración de QoS en NE8000
display subscriber session username test@fibra

# Debe mostrar:
# User Queue: CIR 52428800, PIR 52428800
```

Si no aparecen, verificar:
1. Que el dominio PPPoE esté configurado para usar RADIUS
2. Que el profile de subscriptor permita override desde RADIUS

### Error: "Planes no cargan en el panel"

**Causa:** `config.php` no tiene `NAS_TYPE` definido.

**Solución:**

```bash
# Actualizar panel
cd /var/www/html/radius
git pull origin main

# Verificar config.php tiene:
grep "NAS_TYPE" config.php

# Si no aparece, agregar:
echo "define('NAS_TYPE', 'huawei');" >> config.php
```

---

## 📊 Diferencias con Mikrotik

| Aspecto | Huawei | Mikrotik |
|---------|--------|----------|
| Atributo | `Huawei-Input-Average-Rate` | `Mikrotik-Rate-Limit` |
| Formato | Separado (4 atributos) | Combinado (upload/download) |
| Unidad | bps (bits por segundo) | String (50M/50M) |
| Conversión | Automática por el panel | Directa |

---

## 📝 Ejemplo Completo

### Crear Plan para 100MB simétrico

**1. Desde el panel:**
```
Nombre: Plan 100MB Fibra
Subida: 100M
Bajada: 100M
```

**2. Se guarda en BD:**
```sql
-- En radgroupreply:
('Plan 100MB Fibra', 'Huawei-Input-Average-Rate', ':=', '104857600')
('Plan 100MB Fibra', 'Huawei-Output-Average-Rate', ':=', '104857600')
('Plan 100MB Fibra', 'Huawei-Input-Peak-Rate', ':=', '104857600')
('Plan 100MB Fibra', 'Huawei-Output-Peak-Rate', ':=', '104857600')
```

**3. Asignar a 50 usuarios:**
```sql
-- Insertar usuarios
INSERT INTO radusergroup (username, groupname, priority)
SELECT username, 'Plan 100MB Fibra', 1
FROM radcheck
WHERE username LIKE 'cliente%'
LIMIT 50;
```

---

## 🔐 Seguridad

### Comunicación RADIUS

```cisco
# En NE8000, usar shared-key fuerte
radius-server shared-key cipher %$#@SecureKey2024!

# En FreeRADIUS clients.conf:
client 192.168.1.1 {
    secret = %$#@SecureKey2024!
    nastype = huawei
}
```

### Limitar Acceso al Panel

En `config.php`:

```php
// Solo permitir IP del administrador
define('ALLOWED_IPS', ['192.168.1.100', '10.0.0.50']);
```

---

**Documentación creada:** 2025-11-16
**Compatible con:** Huawei NE8000-F1A, NE40E, ME60
**Versión FreeRADIUS:** 3.0+
**Versión Panel:** 2.1+
