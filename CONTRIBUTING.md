# Guía de Contribución

Gracias por tu interés en contribuir a RADIUS Remote Client!

## Cómo Contribuir

### Reportar Bugs

Antes de reportar un bug, verifica que:

1. No haya sido reportado previamente en [Issues](https://github.com/SV-Com/RADIUS-Remote-Client/issues)
2. Estés usando la última versión

**Información a incluir:**

- Sistema operativo y versión
- Versión de PHP
- Versión de MySQL/MariaDB
- Pasos para reproducir el bug
- Comportamiento esperado vs actual
- Logs relevantes

### Sugerir Mejoras

Abre un Issue con:

- Descripción clara de la funcionalidad
- Casos de uso
- Ejemplos si es posible
- Por qué sería útil

### Pull Requests

1. **Fork el proyecto**

2. **Crea una rama:**
   ```bash
   git checkout -b feature/nueva-funcionalidad
   ```

3. **Haz tus cambios**

4. **Prueba tus cambios:**
   - Verifica que el código funciona
   - No introduzcas errores de sintaxis
   - Prueba en diferentes escenarios

5. **Commit con mensajes descriptivos:**
   ```bash
   git commit -m "Add: Nueva funcionalidad X"
   git commit -m "Fix: Corregir error en Y"
   git commit -m "Update: Mejorar rendimiento de Z"
   ```

6. **Push y crea Pull Request:**
   ```bash
   git push origin feature/nueva-funcionalidad
   ```

## Estándares de Código

### PHP

- **PSR-12** para estilo de código
- Comentarios en español o inglés
- Nombres de variables descriptivos
- Evitar código duplicado

```php
// Bueno
function getUserByUsername($username) {
    $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

// Malo
function get($u) {
    return $this->db->query("SELECT * FROM users WHERE username = '$u'")->fetch();
}
```

### JavaScript

- ES6+ preferido
- Nombres descriptivos
- Comentarios cuando sea necesario
- Evitar variables globales

```javascript
// Bueno
async function loadUsers() {
    try {
        const response = await fetch(`${API_URL}/users`);
        const result = await response.json();
        return result.data;
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Malo
function load() {
    var x = fetch(API_URL + '/users');
    return x;
}
```

### CSS

- Clases descriptivas
- Organizar por secciones
- Usar variables CSS cuando sea posible

```css
/* Bueno */
.user-table {
    width: 100%;
    border-collapse: collapse;
}

.user-table-header {
    background: var(--primary-color);
    color: white;
}

/* Malo */
.ut {
    w: 100%;
}
```

## Seguridad

**IMPORTANTE**: No incluyas en tus commits:

- Credenciales reales
- API Keys
- Contraseñas
- IPs de producción
- Información sensible

Si encuentras una vulnerabilidad de seguridad:

1. **NO** la publiques en Issues
2. Envía un email privado a: security@ejemplo.com
3. Incluye detalles técnicos
4. Esperamos responder en 48 horas

## Estructura del Proyecto

```
RADIUS-Remote-Client/
├── config.php           # Configuración
├── index.php            # UI principal
├── login.php            # Login
├── api.php              # API REST
├── includes/
│   └── db.php           # Base de datos
├── css/
│   └── style.css        # Estilos
├── js/
│   └── app.js           # JavaScript
└── docs/                # Documentación
```

## Nuevas Funcionalidades

### Ideas Bienvenidas

- Soporte para más tipos de atributos RADIUS
- Dashboard con más estadísticas
- Exportación a diferentes formatos (PDF, JSON)
- Integración con sistemas de ticketing
- Modo oscuro
- Múltiples idiomas
- Gestión de NAS (Network Access Servers)
- Reportes programados

### Funcionalidades en Desarrollo

Ver [Projects](https://github.com/SV-Com/RADIUS-Remote-Client/projects)

## Testing

Antes de enviar un PR, verifica:

### Test Básico

1. **Instalación limpia funciona:**
   ```bash
   bash install-remote.sh
   ```

2. **Conexión a BD remota:**
   ```bash
   bash verify-connection.sh
   ```

3. **Panel web carga correctamente**

4. **API endpoints responden:**
   ```bash
   curl http://localhost/radius/api.php/users?api_key=TEST
   ```

### Test de Funcionalidades

- [ ] Login con API Key correcta
- [ ] Login falla con API Key incorrecta
- [ ] Listar usuarios
- [ ] Crear usuario
- [ ] Editar usuario
- [ ] Eliminar usuario
- [ ] Ver estadísticas
- [ ] Ver sesiones activas
- [ ] Exportar CSV

## Documentación

Si añades una nueva funcionalidad, actualiza:

- README.md (si afecta instalación/uso básico)
- INSTALL_GUIDE.md (si afecta instalación)
- SECURITY.md (si afecta seguridad)
- Comentarios en el código

## Proceso de Revisión

1. Tu PR será revisado por un mantenedor
2. Pueden solicitarse cambios
3. Una vez aprobado, será mergeado
4. Tu nombre aparecerá en los contribuidores!

## Licencia

Al contribuir, aceptas que tu código se distribuya bajo la licencia MIT del proyecto.

## Comunidad

- Sé respetuoso
- Ayuda a otros
- Acepta críticas constructivas
- Celebra los éxitos de otros

## Agradecimientos

Gracias a todos los que contribuyen a este proyecto!

### Top Contributors

- [Tu nombre aquí]

## Contacto

- **GitHub**: https://github.com/SV-Com/RADIUS-Remote-Client
- **Issues**: https://github.com/SV-Com/RADIUS-Remote-Client/issues
- **Email**: contribuciones@ejemplo.com

---

**Happy Coding!** 🚀
