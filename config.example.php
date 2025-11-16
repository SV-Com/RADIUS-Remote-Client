<?php
/**
 * Configuración del Cliente Remoto para FreeRADIUS
 *
 * Este archivo contiene la configuración para conectarse a un servidor
 * FreeRADIUS remoto (en otro servidor/ubicación)
 */

// ========================================
// SERVIDOR FREERADIUS REMOTO
// ========================================

// Host del servidor FreeRADIUS (IP o dominio)
define('REMOTE_DB_HOST', '192.168.1.100');

// Puerto MySQL del servidor remoto
define('REMOTE_DB_PORT', 3306);

// Nombre de la base de datos RADIUS
define('REMOTE_DB_NAME', 'radius');

// Usuario MySQL con permisos remotos
// NOTA: Este usuario debe estar creado en el servidor remoto con:
// CREATE USER 'radiusremote'@'CLIENT_IP' IDENTIFIED BY 'password';
// GRANT ALL PRIVILEGES ON radius.* TO 'radiusremote'@'CLIENT_IP';
define('REMOTE_DB_USER', 'radiusremote');

// Contraseña del usuario MySQL
define('REMOTE_DB_PASS', 'password_seguro_aqui');

// ========================================
// AUTENTICACIÓN DEL PANEL WEB
// ========================================

// API Key para acceder al panel web
// Genera una clave segura con: openssl rand -hex 32
define('API_KEY', 'genera_una_clave_aleatoria_aqui');

// ========================================
// TÚNEL SSH (OPCIONAL - RECOMENDADO)
// ========================================

// Usar túnel SSH para mayor seguridad
// Si está habilitado, la conexión a MySQL irá a través de SSH
define('USE_SSH_TUNNEL', false);

// Configuración del túnel SSH
define('SSH_HOST', '192.168.1.100');      // IP del servidor FreeRADIUS
define('SSH_PORT', 22);                    // Puerto SSH
define('SSH_USER', 'usuario_ssh');         // Usuario SSH
define('SSH_KEY_PATH', '/home/user/.ssh/id_rsa');  // Ruta a clave privada

// Puerto local para el túnel SSH
// Cuando USE_SSH_TUNNEL es true, conecta a localhost:LOCAL_TUNNEL_PORT
define('LOCAL_TUNNEL_PORT', 3307);

// ========================================
// TIMEOUT Y CONEXIÓN
// ========================================

// Timeout de conexión MySQL (segundos)
define('DB_CONNECT_TIMEOUT', 10);

// Timeout de lectura/escritura (segundos)
define('DB_READ_TIMEOUT', 30);

// Charset de la conexión
define('DB_CHARSET', 'utf8mb4');

// ========================================
// CONFIGURACIÓN DE EMAIL (NOTIFICACIONES)
// ========================================

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@gmail.com');
define('SMTP_PASS', 'tu_password_app');
define('SMTP_FROM', 'tu_email@gmail.com');
define('SMTP_FROM_NAME', 'RADIUS Remote Client');

// ========================================
// WEBHOOKS
// ========================================

define('WEBHOOKS_ENABLED', true);
define('WEBHOOKS_FILE', __DIR__ . '/webhooks.json');

// ========================================
// SEGURIDAD
// ========================================

// Habilitar logging de conexiones
define('LOG_CONNECTIONS', true);

// Archivo de log de conexiones
define('CONNECTION_LOG_FILE', __DIR__ . '/logs/connections.log');

// IPs permitidas para acceder al panel (dejar vacío para permitir todas)
// Ejemplo: ['192.168.1.100', '10.0.0.50']
define('ALLOWED_IPS', []);

// ========================================
// CONFIGURACIÓN AVANZADA
// ========================================

// Habilitar modo debug (solo para desarrollo)
define('DEBUG_MODE', false);

// Prefijo para las tablas (si tu instalación usa prefijo)
define('TABLE_PREFIX', '');

// Tipo de equipo NAS (Network Access Server)
// Opciones: 'mikrotik', 'huawei', 'cisco'
define('NAS_TYPE', 'huawei');

// Atributos RADIUS según tipo de NAS
// No modificar a menos que sepas lo que haces
define('RATE_LIMIT_ATTRIBUTES', [
    'mikrotik' => [
        'upload' => 'Mikrotik-Rate-Limit',
        'download' => 'Mikrotik-Rate-Limit',
        'format' => 'combined',  // Formato: "upload/download"
        'unit' => 'string'       // Ej: "50M/50M"
    ],
    'huawei' => [
        'upload' => 'Huawei-Input-Average-Rate',
        'download' => 'Huawei-Output-Average-Rate',
        'upload_peak' => 'Huawei-Input-Peak-Rate',
        'download_peak' => 'Huawei-Output-Peak-Rate',
        'format' => 'separate',  // Atributos separados
        'unit' => 'bps'          // Bits por segundo
    ],
    'cisco' => [
        'upload' => 'Cisco-AVPair',
        'download' => 'Cisco-AVPair',
        'format' => 'avpair',
        'unit' => 'bps'
    ]
]);

// Zona horaria
date_default_timezone_set('America/Argentina/Buenos_Aires');

// ========================================
// VALIDACIÓN DE CONFIGURACIÓN
// ========================================

// Verificar que las constantes críticas estén definidas
if (!defined('REMOTE_DB_HOST') || REMOTE_DB_HOST === '') {
    die('Error: REMOTE_DB_HOST no está configurado en config.php');
}

if (!defined('REMOTE_DB_NAME') || REMOTE_DB_NAME === '') {
    die('Error: REMOTE_DB_NAME no está configurado en config.php');
}

if (!defined('REMOTE_DB_USER') || REMOTE_DB_USER === '') {
    die('Error: REMOTE_DB_USER no está configurado en config.php');
}

if (API_KEY === 'genera_una_clave_aleatoria_aqui') {
    die('Error: Debes cambiar la API_KEY en config.php. Genera una con: openssl rand -hex 32');
}

// ========================================
// FUNCIONES AUXILIARES
// ========================================

/**
 * Verificar si el túnel SSH está activo
 */
function checkSSHTunnel() {
    if (!USE_SSH_TUNNEL) {
        return true;
    }

    $connection = @fsockopen('127.0.0.1', LOCAL_TUNNEL_PORT, $errno, $errstr, 1);

    if ($connection) {
        fclose($connection);
        return true;
    }

    return false;
}

/**
 * Obtener la configuración de conexión efectiva
 */
function getEffectiveDBConfig() {
    if (USE_SSH_TUNNEL) {
        return [
            'host' => '127.0.0.1',
            'port' => LOCAL_TUNNEL_PORT,
            'name' => REMOTE_DB_NAME,
            'user' => REMOTE_DB_USER,
            'pass' => REMOTE_DB_PASS
        ];
    }

    return [
        'host' => REMOTE_DB_HOST,
        'port' => REMOTE_DB_PORT,
        'name' => REMOTE_DB_NAME,
        'user' => REMOTE_DB_USER,
        'pass' => REMOTE_DB_PASS
    ];
}

/**
 * Registrar evento de conexión
 */
function logConnection($event, $details = '') {
    if (!LOG_CONNECTIONS) {
        return;
    }

    $logDir = dirname(CONNECTION_LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $logEntry = "[$timestamp] [$ip] $event: $details\n";

    @file_put_contents(CONNECTION_LOG_FILE, $logEntry, FILE_APPEND);
}

/**
 * Verificar IP permitida
 */
function checkAllowedIP() {
    if (empty(ALLOWED_IPS)) {
        return true; // Sin restricción
    }

    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

    return in_array($clientIP, ALLOWED_IPS);
}

/**
 * ========================================
 * FUNCIONES PARA GESTIÓN DE NAS
 * ========================================
 */

/**
 * Convertir velocidad en formato humano (50M) a bps
 */
function speedToBps($speed) {
    $speed = strtoupper(trim($speed));

    if (preg_match('/^(\d+(?:\.\d+)?)\s*([KMGT]?)B?P?S?$/i', $speed, $matches)) {
        $value = floatval($matches[1]);
        $unit = strtoupper($matches[2]);

        $multipliers = [
            '' => 1000000,      // Por defecto Mbps
            'K' => 1000,        // Kbps
            'M' => 1000000,     // Mbps
            'G' => 1000000000,  // Gbps
            'T' => 1000000000000 // Tbps
        ];

        return intval($value * ($multipliers[$unit] ?? 1000000));
    }

    // Si ya es un número, asumir que es bps
    if (is_numeric($speed)) {
        return intval($speed);
    }

    return 0;
}

/**
 * Convertir bps a formato humano (50M)
 */
function bpsToSpeed($bps) {
    $bps = intval($bps);

    if ($bps == 0) return '0';

    if ($bps >= 1000000000) {
        return round($bps / 1000000000, 2) . 'G';
    } elseif ($bps >= 1000000) {
        return round($bps / 1000000, 2) . 'M';
    } elseif ($bps >= 1000) {
        return round($bps / 1000, 2) . 'K';
    }

    return $bps . 'bps';
}

/**
 * Obtener atributos RADIUS según tipo de NAS
 */
function getNasAttributes() {
    $nasType = defined('NAS_TYPE') ? NAS_TYPE : 'mikrotik';
    $attributes = RATE_LIMIT_ATTRIBUTES[$nasType] ?? RATE_LIMIT_ATTRIBUTES['mikrotik'];
    return $attributes;
}

/**
 * Obtener nombre del atributo de upload según NAS
 */
function getUploadAttribute() {
    $attrs = getNasAttributes();
    return $attrs['upload'];
}

/**
 * Obtener nombre del atributo de download según NAS
 */
function getDownloadAttribute() {
    $attrs = getNasAttributes();
    return $attrs['download'];
}

/**
 * Formatear velocidad para guardar en BD según tipo de NAS
 */
function formatSpeedForDb($upload, $download, $forGroup = false) {
    $attrs = getNasAttributes();
    $nasType = defined('NAS_TYPE') ? NAS_TYPE : 'mikrotik';

    if ($nasType === 'mikrotik') {
        // Mikrotik: formato "upload/download"
        return [
            [
                'attribute' => 'Mikrotik-Rate-Limit',
                'op' => ':=',
                'value' => $upload . '/' . $download
            ]
        ];
    } elseif ($nasType === 'huawei') {
        // Huawei: atributos separados en bps
        $uploadBps = speedToBps($upload);
        $downloadBps = speedToBps($download);

        $result = [
            [
                'attribute' => 'Huawei-Input-Average-Rate',
                'op' => ':=',
                'value' => $uploadBps
            ],
            [
                'attribute' => 'Huawei-Output-Average-Rate',
                'op' => ':=',
                'value' => $downloadBps
            ]
        ];

        // Agregar peak rates (mismo valor que average)
        if ($forGroup) {
            $result[] = [
                'attribute' => 'Huawei-Input-Peak-Rate',
                'op' => ':=',
                'value' => $uploadBps
            ];
            $result[] = [
                'attribute' => 'Huawei-Output-Peak-Rate',
                'op' => ':=',
                'value' => $downloadBps
            ];
        }

        return $result;
    }

    return [];
}

/**
 * Parsear velocidades desde BD según tipo de NAS
 */
function parseSpeedFromDb($attributes) {
    $nasType = defined('NAS_TYPE') ? NAS_TYPE : 'mikrotik';
    $upload = '';
    $download = '';

    if ($nasType === 'mikrotik') {
        // Buscar Mikrotik-Rate-Limit
        foreach ($attributes as $attr) {
            if ($attr['attribute'] === 'Mikrotik-Rate-Limit') {
                $parts = explode('/', $attr['value']);
                $upload = $parts[0] ?? '';
                $download = $parts[1] ?? '';
                break;
            }
        }
    } elseif ($nasType === 'huawei') {
        // Buscar atributos Huawei separados
        foreach ($attributes as $attr) {
            if ($attr['attribute'] === 'Huawei-Input-Average-Rate') {
                $upload = bpsToSpeed($attr['value']);
            } elseif ($attr['attribute'] === 'Huawei-Output-Average-Rate') {
                $download = bpsToSpeed($attr['value']);
            }
        }
    }

    return ['upload' => $upload, 'download' => $download];
}

?>
