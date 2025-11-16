# Guía de Actualización en Producción

## 🎯 Objetivo

Actualizar tu servidor RADIUS Remote Client en producción con las últimas mejoras:
- ✅ Correcciones de errores SQL y autenticación
- ✅ Sistema completo de gestión de Planes
- ✅ Mejoras en la interfaz web

---

## ⚠️ IMPORTANTE: Antes de Comenzar

### 1. **Hacer Backup Completo**

```bash
# Conectarse al servidor de producción
ssh usuario@tu-servidor-produccion

# Backup de la base de datos
mysqldump -u root -p radius > ~/radius_backup_$(date +%Y%m%d_%H%M%S).sql

# Backup de los archivos del panel
sudo tar -czf ~/radius-panel-backup_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/html/radius/

# Verificar que los backups se crearon
ls -lh ~/*.sql ~/*.tar.gz
```

### 2. **Verificar que tienes Git instalado**

```bash
git --version
# Si no está instalado:
sudo apt install git -y
```

---

## 🚀 Método 1: Actualización con Git (Recomendado)

### Paso 1: Conectarse al Servidor

```bash
ssh usuario@tu-servidor-produccion
```

### Paso 2: Navegar al Directorio del Panel

```bash
cd /var/www/html/radius/
pwd  # Verificar que estás en el directorio correcto
```

### Paso 3: Verificar Estado Actual

```bash
# Ver archivos modificados localmente (si los hay)
git status

# Ver qué versión tienes
git log --oneline -3
```

### Paso 4: Guardar Cambios Locales (si los hay)

Si hiciste cambios en producción (como editar `config.php`):

```bash
# Guardar config.php temporalmente
cp config.php ~/config.php.backup

# Descartar cambios en archivos rastreados
git reset --hard

# Restaurar config.php
cp ~/config.php.backup config.php
```

### Paso 5: Descargar Actualizaciones

```bash
# Descargar últimos cambios
git pull origin main
```

**Salida esperada:**
```
remote: Counting objects: X, done.
Updating 669be7c..ea46656
Fast-forward
 PLANES.md     | 338 ++++++++++++++++++++++++++++++++
 api.php       | 252 ++++++++++++++++++++++++
 css/style.css | 102 ++++++++++
 index.php     |  57 ++++++
 js/app.js     | 201 +++++++++++++++++++
 5 files changed, 950 insertions(+)
```

### Paso 6: Verificar Permisos

```bash
# Asegurar que Apache puede leer los archivos
sudo chown -R www-data:www-data /var/www/html/radius/
sudo chmod -R 755 /var/www/html/radius/

# Permisos especiales para logs y config
sudo chmod 664 /var/www/html/radius/config.php
```

### Paso 7: Reiniciar Apache

```bash
sudo systemctl reload apache2
# o
sudo service apache2 reload
```

### Paso 8: Verificar que Funciona

```bash
# Abrir en navegador
# http://tu-servidor/radius/

# O hacer curl desde el servidor
curl -I http://localhost/radius/
```

---

## 🔄 Método 2: Actualización Manual (Sin Git)

Si el servidor en producción NO tiene Git o no fue instalado con Git:

### Paso 1: Descargar Archivos en tu PC Local

Ya los tienes sincronizados en:
```
E:\PROYECTOS\RADIUS-Remote-Client\
```

### Paso 2: Comprimir Archivos Actualizados

En tu PC local (Windows):

```bash
# Comprimir solo los archivos modificados
# Puedes usar 7-Zip, WinRAR, o PowerShell:

# PowerShell:
Compress-Archive -Path PLANES.md,api.php,css,index.php,js -DestinationPath radius-update.zip
```

O simplemente selecciona estos archivos y comprime con click derecho → "Comprimir".

### Paso 3: Subir al Servidor

**Opción A: Con SCP (desde PowerShell/CMD en Windows)**
```bash
scp radius-update.zip usuario@tu-servidor:/tmp/
```

**Opción B: Con FileZilla o WinSCP**
1. Conectarte al servidor por SFTP
2. Subir `radius-update.zip` a `/tmp/`

### Paso 4: Descomprimir y Aplicar en el Servidor

```bash
# Conectarse al servidor
ssh usuario@tu-servidor-produccion

# Navegar al directorio temporal
cd /tmp/

# Descomprimir
unzip radius-update.zip -d radius-update/

# Hacer backup de archivos actuales
sudo cp /var/www/html/radius/api.php /var/www/html/radius/api.php.bak
sudo cp /var/www/html/radius/index.php /var/www/html/radius/index.php.bak
sudo cp /var/www/html/radius/js/app.js /var/www/html/radius/js/app.js.bak
sudo cp /var/www/html/radius/css/style.css /var/www/html/radius/css/style.css.bak

# Copiar archivos nuevos
sudo cp -r radius-update/* /var/www/html/radius/

# Ajustar permisos
sudo chown -R www-data:www-data /var/www/html/radius/
sudo chmod -R 755 /var/www/html/radius/

# Reiniciar Apache
sudo systemctl reload apache2
```

---

## 🔍 Verificación Post-Actualización

### 1. **Verificar que el Panel Carga**

```bash
curl -I http://localhost/radius/
```

Debería devolver `HTTP/1.1 200 OK` o redirigir a login.

### 2. **Verificar Logs de Errores**

```bash
sudo tail -f /var/log/apache2/error.log
```

No deberían aparecer errores PHP.

### 3. **Probar Funcionalidades**

Desde el navegador:

1. ✅ Iniciar sesión con API_KEY
2. ✅ Cargar lista de usuarios (sin error "Unauthorized")
3. ✅ Abrir tab "Planes" (nuevo)
4. ✅ Crear un plan de prueba
5. ✅ Ver estadísticas
6. ✅ Ver sesiones activas

### 4. **Verificar Base de Datos**

```bash
mysql -u root -p radius -e "SHOW TABLES LIKE 'radgroup%';"
```

Deberías ver:
```
+---------------------------+
| Tables_in_radius (radgroup%) |
+---------------------------+
| radgroupcheck             |
| radgroupreply             |
+---------------------------+
```

---

## 🛠️ Resolución de Problemas

### Error: "Permission denied"

```bash
sudo chown -R www-data:www-data /var/www/html/radius/
sudo chmod -R 755 /var/www/html/radius/
```

### Error: "500 Internal Server Error"

```bash
# Ver logs
sudo tail -50 /var/log/apache2/error.log

# Verificar sintaxis PHP
php -l /var/www/html/radius/api.php
php -l /var/www/html/radius/index.php
```

### Error: "Could not open input file: config.php"

```bash
# Verificar que config.php existe
ls -la /var/www/html/radius/config.php

# Si no existe, copiar desde ejemplo
sudo cp /var/www/html/radius/config.example.php /var/www/html/radius/config.php
sudo nano /var/www/html/radius/config.php
# Configurar credenciales
```

### Error: "Unauthorized" después de actualizar

```bash
# Limpiar sesiones
sudo rm -rf /var/lib/php/sessions/sess_*

# Cerrar sesión y volver a iniciar sesión en el navegador
```

### Error: Planes no aparecen

```bash
# Verificar que las tablas existen
mysql -u root -p radius -e "SELECT * FROM radgroupcheck LIMIT 1;"
mysql -u root -p radius -e "SELECT * FROM radgroupreply LIMIT 1;"

# Si no existen, FreeRADIUS no está completamente instalado
# Revisar schema de FreeRADIUS
```

---

## 🔐 Configuración Post-Actualización

### Verificar config.php

```bash
sudo nano /var/www/html/radius/config.php
```

Asegúrate que tenga:

```php
// Servidor remoto
define('REMOTE_DB_HOST', 'IP_DEL_SERVIDOR_RADIUS');
define('REMOTE_DB_USER', 'radiusremote');
define('REMOTE_DB_PASS', 'tu_password');

// API Key (debe ser diferente al ejemplo)
define('API_KEY', 'tu_clave_aleatoria_segura');
```

**Si API_KEY es el valor por defecto:**

```bash
# Generar nueva clave
openssl rand -hex 32

# Editar config.php y poner la nueva clave
sudo nano /var/www/html/radius/config.php
```

---

## 📊 Verificar Nuevas Funcionalidades

### 1. Sistema de Planes

```bash
# Desde el navegador, ir a tab "Planes"
# Crear un plan de prueba:
# - Nombre: Plan Test
# - Subida: 10M
# - Bajada: 10M

# Verificar en base de datos:
mysql -u root -p radius -e "SELECT * FROM radgroupreply WHERE groupname = 'Plan Test';"
```

### 2. Asignar Plan a Usuario

```bash
# Crear o editar un usuario
# En el campo "Plan", escribir: Plan Test

# Verificar en BD:
mysql -u root -p radius -e "SELECT username, groupname FROM radusergroup;"
```

---

## 🔄 Rollback (Deshacer Cambios)

Si algo sale mal, puedes volver a la versión anterior:

### Rollback con Git

```bash
cd /var/www/html/radius/
git log --oneline -5  # Ver commits
git reset --hard 669be7c  # Volver al commit anterior
sudo systemctl reload apache2
```

### Rollback Manual

```bash
# Restaurar archivos desde backup
sudo cp /var/www/html/radius/api.php.bak /var/www/html/radius/api.php
sudo cp /var/www/html/radius/index.php.bak /var/www/html/radius/index.php
sudo cp /var/www/html/radius/js/app.js.bak /var/www/html/radius/js/app.js
sudo cp /var/www/html/radius/css/style.css.bak /var/www/html/radius/css/style.css

sudo systemctl reload apache2
```

### Restaurar Base de Datos (solo si modificaste la BD)

```bash
mysql -u root -p radius < ~/radius_backup_FECHA.sql
```

---

## 📝 Checklist de Actualización

```
Pre-actualización:
☐ Backup de base de datos
☐ Backup de archivos del panel
☐ Verificar versión actual (git log)

Actualización:
☐ git pull origin main (o subir archivos manualmente)
☐ Verificar permisos (chown www-data)
☐ Reiniciar Apache

Post-actualización:
☐ Panel web carga correctamente
☐ Login funciona
☐ Usuarios se listan sin error
☐ Tab "Planes" aparece
☐ Crear plan de prueba funciona
☐ No hay errores en logs

Documentación:
☐ Leer PLANES.md
☐ Leer FIXES.md
☐ Configurar planes según tu ISP
```

---

## 🎓 Mejores Prácticas para Producción

### 1. **Usar Git en Producción**

```bash
# Primera vez (si no lo hiciste)
cd /var/www/html/radius/
sudo git init
sudo git remote add origin https://github.com/SV-Com/RADIUS-Remote-Client.git
sudo git fetch origin
sudo git reset --hard origin/main
```

### 2. **Actualizaciones Automáticas (Opcional)**

Crear script de actualización:

```bash
sudo nano /usr/local/bin/update-radius-panel.sh
```

Contenido:

```bash
#!/bin/bash
echo "=== Actualizando RADIUS Panel ==="
cd /var/www/html/radius/
cp config.php /tmp/config.php.backup
git fetch origin
git reset --hard origin/main
cp /tmp/config.php.backup config.php
chown -R www-data:www-data /var/www/html/radius/
systemctl reload apache2
echo "✓ Actualización completada"
```

Dar permisos:

```bash
sudo chmod +x /usr/local/bin/update-radius-panel.sh
```

Usar:

```bash
sudo /usr/local/bin/update-radius-panel.sh
```

### 3. **Monitoreo de Logs**

```bash
# Agregar a crontab para alertas
sudo crontab -e

# Agregar línea:
*/30 * * * * tail -100 /var/log/apache2/error.log | grep -i "radius" | mail -s "RADIUS Panel Errors" tu@email.com
```

---

## 📞 Soporte

Si tienes problemas durante la actualización:

1. Revisa los logs: `/var/log/apache2/error.log`
2. Verifica permisos de archivos
3. Comprueba que `config.php` tiene las credenciales correctas
4. Consulta `FIXES.md` y `PLANES.md` para más detalles

---

**Versión de esta guía:** 1.0
**Fecha:** 2025-11-16
**Commits incluidos:** 669be7c → ea46656
