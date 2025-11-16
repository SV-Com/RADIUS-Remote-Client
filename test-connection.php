<?php
/**
 * Script de prueba de conexión a la base de datos remota
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test de Conexión RADIUS Remote Client ===\n\n";

// Cargar configuración
if (!file_exists(__DIR__ . '/config.php')) {
    die("ERROR: No se encontró el archivo config.php\n");
}

require_once __DIR__ . '/config.php';

echo "1. Verificando configuración...\n";
echo "   - Host: " . REMOTE_DB_HOST . "\n";
echo "   - Puerto: " . REMOTE_DB_PORT . "\n";
echo "   - Base de datos: " . REMOTE_DB_NAME . "\n";
echo "   - Usuario: " . REMOTE_DB_USER . "\n";
echo "   - Túnel SSH: " . (USE_SSH_TUNNEL ? 'Habilitado' : 'Deshabilitado') . "\n\n";

// Test de conexión básica
echo "2. Probando conexión TCP al servidor...\n";
$testHost = USE_SSH_TUNNEL ? '127.0.0.1' : REMOTE_DB_HOST;
$testPort = USE_SSH_TUNNEL ? LOCAL_TUNNEL_PORT : REMOTE_DB_PORT;

$connection = @fsockopen($testHost, $testPort, $errno, $errstr, 5);
if ($connection) {
    echo "   ✓ Puerto $testPort accesible\n\n";
    fclose($connection);
} else {
    echo "   ✗ ERROR: No se puede conectar a $testHost:$testPort\n";
    echo "   Error: $errstr ($errno)\n\n";
    die("Verifica el firewall y que el servidor MySQL esté escuchando en 0.0.0.0\n");
}

// Test de conexión MySQL
echo "3. Probando conexión MySQL...\n";
try {
    $config = getEffectiveDBConfig();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => DB_CONNECT_TIMEOUT,
    ];

    $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    echo "   ✓ Conexión MySQL exitosa\n\n";

} catch (PDOException $e) {
    echo "   ✗ ERROR de conexión MySQL:\n";
    echo "   " . $e->getMessage() . "\n\n";

    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "Solución:\n";
        echo "En el servidor MySQL remoto, ejecuta:\n";
        echo "mysql -u root -p\n";
        echo "GRANT ALL PRIVILEGES ON " . REMOTE_DB_NAME . ".* TO '" . REMOTE_DB_USER . "'@'%' IDENTIFIED BY 'password';\n";
        echo "FLUSH PRIVILEGES;\n";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "Solución:\n";
        echo "1. Verifica que MySQL esté corriendo: systemctl status mysql\n";
        echo "2. Verifica bind-address en /etc/mysql/mariadb.conf.d/50-server.cnf\n";
        echo "   Debe ser: bind-address = 0.0.0.0\n";
    }

    die();
}

// Test de tablas
echo "4. Verificando tablas RADIUS...\n";
$tables = ['radcheck', 'radreply', 'radacct', 'radgroupcheck', 'radgroupreply'];
$missingTables = [];

foreach ($tables as $table) {
    $tableName = TABLE_PREFIX . $table;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tableName]);
        $result = $stmt->fetch();

        if ($result['count'] == 0) {
            echo "   ✗ Tabla '$tableName' no existe\n";
            $missingTables[] = $tableName;
        } else {
            echo "   ✓ Tabla '$tableName' existe\n";
        }
    } catch (PDOException $e) {
        echo "   ✗ Error verificando tabla '$tableName': " . $e->getMessage() . "\n";
    }
}

if (!empty($missingTables)) {
    echo "\n⚠ ADVERTENCIA: Faltan tablas: " . implode(', ', $missingTables) . "\n";
    echo "Ejecuta el script de instalación de FreeRADIUS en el servidor remoto.\n\n";
} else {
    echo "\n";
}

// Test de consulta simple
echo "5. Probando consulta de datos...\n";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "radcheck");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "   ✓ Total de usuarios en radcheck: " . $result['total'] . "\n\n";
} catch (PDOException $e) {
    echo "   ✗ Error en consulta: " . $e->getMessage() . "\n\n";
}

// Test de permisos
echo "6. Verificando permisos del usuario...\n";
try {
    // Test INSERT
    $testUsername = 'test_' . uniqid();
    $stmt = $pdo->prepare("INSERT INTO " . TABLE_PREFIX . "radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', 'test')");
    $stmt->execute([$testUsername]);
    echo "   ✓ Permiso INSERT\n";

    // Test SELECT
    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_PREFIX . "radcheck WHERE username = ?");
    $stmt->execute([$testUsername]);
    $stmt->fetch();
    echo "   ✓ Permiso SELECT\n";

    // Test DELETE
    $stmt = $pdo->prepare("DELETE FROM " . TABLE_PREFIX . "radcheck WHERE username = ?");
    $stmt->execute([$testUsername]);
    echo "   ✓ Permiso DELETE\n\n";

} catch (PDOException $e) {
    echo "   ✗ Error de permisos: " . $e->getMessage() . "\n\n";
}

echo "=== ✓ TODAS LAS PRUEBAS COMPLETADAS ===\n";
echo "\nLa conexión está funcionando correctamente.\n";
echo "Puedes acceder al panel web desde tu navegador.\n";
?>
