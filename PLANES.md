# Gestión de Planes - RADIUS Remote Client

## 📋 ¿Qué son los Planes?

Los **Planes** son plantillas de configuración que agrupan velocidades de subida/bajada predefinidas para asignar fácilmente a múltiples usuarios. En lugar de configurar manualmente la velocidad de cada usuario, creas un Plan y lo asignas.

---

## 🎯 Ventajas de usar Planes

✅ **Estandarización:** Todos los usuarios del mismo plan tienen la misma configuración
✅ **Facilidad:** Cambias el plan y todos los usuarios se actualizan automáticamente
✅ **Organización:** Agrupa usuarios por tipo de servicio (Plan 50MB, Plan 100MB, etc.)
✅ **Escalabilidad:** Gestiona cientos de usuarios de forma eficiente

---

## 🚀 Cómo Crear un Plan

### Paso 1: Acceder a la sección Planes

1. Inicia sesión en el panel web
2. Click en la pestaña **"Planes"**
3. Click en el botón **"+ Crear Plan"**

### Paso 2: Completar el formulario

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Nombre del Plan** | Identificador único del plan | `Plan 50MB` |
| **Subida** | Velocidad de upload | `50M` |
| **Bajada** | Velocidad de download | `50M` |
| **Pool IP** (opcional) | Pool de IPs de FreeRADIUS | `pool-clientes` |

**Formatos de velocidad:**
- `10M` = 10 Mbps
- `50M` = 50 Mbps
- `100M` = 100 Mbps
- `1G` = 1 Gbps

### Paso 3: Guardar

Click en **"Guardar"** y el plan se creará en la base de datos.

---

## 👥 Cómo Asignar un Plan a Usuarios

### Opción 1: Al crear un usuario nuevo

1. Click en **"+ Crear Usuario"**
2. Completa username y password
3. En el campo **"Plan"**, escribe el **nombre exacto** del plan
4. Los campos de Subida/Bajada se pueden dejar vacíos (se usará el del plan)
5. Guardar

### Opción 2: Editar usuario existente

1. Click en **"✏️ Editar"** del usuario
2. En el campo **"Plan"**, escribe el nombre del plan
3. Guardar

### Opción 3: Directamente en la Base de Datos

```sql
-- Asignar usuario a un plan
INSERT INTO radusergroup (username, groupname, priority)
VALUES ('cliente@fibra', 'Plan 50MB', 1);
```

---

## 🔄 Cómo Migrar Usuarios Existentes a Planes

Si ya tienes usuarios creados con velocidades individuales, puedes migrarlos a planes:

### Paso 1: Crear los planes

Crea un plan por cada velocidad común que tengas. Por ejemplo:

```
Plan 10MB  → 10M/10M
Plan 50MB  → 50M/50M
Plan 100MB → 100M/100M
```

### Paso 2: Script SQL para migrar usuarios

```sql
-- Ver usuarios que tienen 50M/50M
SELECT rc.username, rr.value
FROM radcheck rc
JOIN radreply rr ON rc.username = rr.username
WHERE rr.attribute = 'Mikrotik-Rate-Limit'
  AND rr.value = '50M/50M';

-- Asignar esos usuarios al Plan 50MB
INSERT INTO radusergroup (username, groupname, priority)
SELECT rc.username, 'Plan 50MB', 1
FROM radcheck rc
JOIN radreply rr ON rc.username = rr.username
WHERE rr.attribute = 'Mikrotik-Rate-Limit'
  AND rr.value = '50M/50M'
  AND NOT EXISTS (
    SELECT 1 FROM radusergroup ug
    WHERE ug.username = rc.username
  );

-- Repetir para cada velocidad
```

### Paso 3: Limpiar configuraciones individuales (opcional)

Una vez asignados los planes, puedes eliminar las configuraciones individuales:

```sql
-- CUIDADO: Hacer backup antes de ejecutar esto
DELETE FROM radreply
WHERE attribute = 'Mikrotik-Rate-Limit'
  AND username IN (
    SELECT username FROM radusergroup
  );
```

---

## 📊 Ver Estadísticas de Planes

En la pestaña **Planes**, cada tarjeta muestra:

- **Nombre del plan**
- **Velocidad de subida**
- **Velocidad de bajada**
- **Pool IP** (si está configurado)
- **Cantidad de usuarios** asignados al plan

---

## ✏️ Editar un Plan

1. En la pestaña **Planes**, click en **"✏️ Editar"**
2. Modifica las velocidades
3. Guardar

**IMPORTANTE:** Al editar un plan, **todos los usuarios** asignados a ese plan se actualizan automáticamente en la próxima conexión.

---

## 🗑️ Eliminar un Plan

1. En la pestaña **Planes**, click en **"🗑️ Eliminar"**
2. Confirmar

**Restricción:** No se puede eliminar un plan que tiene usuarios asignados. Primero debes reasignar esos usuarios a otro plan o eliminarlos.

---

## 🔧 Estructura en Base de Datos

Los planes utilizan las tablas de **grupos** de FreeRADIUS:

### Tabla `radgroupcheck`

Almacena condiciones de autenticación del grupo:

```sql
groupname    | attribute   | op  | value
-------------|-------------|-----|-------
Plan 50MB    | Auth-Type   | :=  | Accept
```

### Tabla `radgroupreply`

Almacena atributos de respuesta (velocidades):

```sql
groupname    | attribute           | op  | value
-------------|---------------------|-----|-------
Plan 50MB    | Mikrotik-Rate-Limit | :=  | 50M/50M
Plan 50MB    | Framed-Pool         | =   | pool-clientes
```

### Tabla `radusergroup`

Relaciona usuarios con grupos:

```sql
username        | groupname  | priority
----------------|------------|----------
cliente@fibra   | Plan 50MB  | 1
```

---

## 🌐 API Endpoints para Planes

### Listar todos los planes
```bash
GET /api.php/plans

# Respuesta:
{
  "success": true,
  "data": [
    {
      "name": "Plan 50MB",
      "upload_speed": "50M",
      "download_speed": "50M",
      "pool": "pool-clientes",
      "users_count": 15
    }
  ]
}
```

### Obtener un plan específico
```bash
GET /api.php/plan?name=Plan%2050MB
```

### Crear un plan
```bash
POST /api.php/plans
Content-Type: application/json

{
  "name": "Plan 100MB",
  "upload_speed": "100M",
  "download_speed": "100M",
  "pool": "pool-premium"
}
```

### Actualizar un plan
```bash
PUT /api.php/plan?name=Plan%2050MB
Content-Type: application/json

{
  "upload_speed": "60M",
  "download_speed": "60M"
}
```

### Eliminar un plan
```bash
DELETE /api.php/plan?name=Plan%2050MB
```

---

## ❓ Preguntas Frecuentes

### ¿Puedo tener usuarios con velocidad personalizada Y usuarios con planes?

**Sí.** Puedes mezclar ambos métodos:
- Usuarios sin plan: Tienen velocidades configuradas individualmente en `radreply`
- Usuarios con plan: Heredan velocidades del grupo

### ¿Qué pasa si un usuario tiene velocidad individual Y está en un plan?

FreeRADIUS prioriza según la configuración. Generalmente:
1. Si el atributo está en `radreply` (individual), se usa ese
2. Si no, se usa el del grupo (`radgroupreply`)

**Recomendación:** Usa UNA sola estrategia (planes O velocidades individuales).

### ¿Cómo veo qué usuarios están en un plan?

```sql
SELECT u.username, u.groupname, rc.value as password
FROM radusergroup u
JOIN radcheck rc ON u.username = rc.username
WHERE u.groupname = 'Plan 50MB';
```

### ¿Los planes funcionan con Mikrotik?

**Sí.** El atributo `Mikrotik-Rate-Limit` es compatible con equipos Mikrotik. El formato `50M/50M` significa:
- `50M` subida / `50M` bajada

Para otros vendors (Cisco, Huawei), puede que necesites usar atributos diferentes.

---

## 📝 Ejemplo Práctico Completo

### Escenario: ISP con 3 tipos de planes

**1. Crear los planes:**

```
Plan Básico    → 10M / 10M
Plan Estándar  → 50M / 50M
Plan Premium   → 100M / 100M
```

**2. Asignar usuarios:**

- `cliente001@fibra` → Plan Básico
- `cliente002@fibra` → Plan Estándar
- `cliente003@fibra` → Plan Premium

**3. Cambiar un usuario de plan:**

Si `cliente001` se upgradea a Plan Estándar:

```sql
UPDATE radusergroup
SET groupname = 'Plan Estándar'
WHERE username = 'cliente001@fibra';
```

**4. Subir velocidad del Plan Estándar:**

Si aumentas el Plan Estándar de 50M a 60M, **todos** los usuarios de ese plan se actualizan automáticamente:

```sql
UPDATE radgroupreply
SET value = '60M/60M'
WHERE groupname = 'Plan Estándar'
  AND attribute = 'Mikrotik-Rate-Limit';
```

---

## 🎨 Mejores Prácticas

1. **Nombres descriptivos:** Usa nombres claros (`Plan 50MB` en vez de `p1`)
2. **Estandarización:** Define planes fijos en vez de velocidades custom
3. **Documentación:** Mantén una lista de tus planes y sus características
4. **Backup:** Haz backup antes de cambios masivos de planes
5. **Testing:** Prueba cambios en un usuario antes de aplicar masivamente

---

**Documentación actualizada:** 2025-11-16
**Versión:** 1.0
