<?php
/**
 * Conexión a Base de Datos Remota FreeRADIUS
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $conn;
    private $sshTunnelProcess = null;

    private function __construct() {
        // Verificar IP permitida
        if (!checkAllowedIP()) {
            logConnection('ACCESS_DENIED', 'IP not in whitelist');
            throw new Exception('Access denied from your IP address');
        }

        // Si se usa túnel SSH, verificarlo
        if (USE_SSH_TUNNEL && !checkSSHTunnel()) {
            $this->startSSHTunnel();
        }

        $this->connect();
    }

    /**
     * Singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Iniciar túnel SSH si es necesario
     */
    private function startSSHTunnel() {
        if (!USE_SSH_TUNNEL) {
            return;
        }

        $cmd = sprintf(
            'ssh -f -N -L %d:localhost:%d -i %s -p %d %s@%s',
            LOCAL_TUNNEL_PORT,
            REMOTE_DB_PORT,
            escapeshellarg(SSH_KEY_PATH),
            SSH_PORT,
            escapeshellarg(SSH_USER),
            escapeshellarg(SSH_HOST)
        );

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            logConnection('SSH_TUNNEL_ERROR', implode(' ', $output));
            throw new Exception('Failed to establish SSH tunnel');
        }

        logConnection('SSH_TUNNEL_STARTED', 'Tunnel established successfully');
        sleep(1); // Esperar a que el túnel se establezca
    }

    /**
     * Conectar a MySQL
     */
    private function connect() {
        $config = getEffectiveDBConfig();

        try {
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

            $this->conn = new PDO($dsn, $config['user'], $config['pass'], $options);

            logConnection('DB_CONNECTED', "Connected to {$config['host']}:{$config['port']}");

        } catch (PDOException $e) {
            logConnection('DB_CONNECTION_ERROR', $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Obtener conexión
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        try {
            $this->conn->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reconectar si la conexión se perdió
     */
    public function reconnect() {
        $this->connect();
    }

    /**
     * Destructor - cerrar túnel SSH si existe
     */
    public function __destruct() {
        if ($this->sshTunnelProcess !== null) {
            // Cerrar túnel SSH
            logConnection('SSH_TUNNEL_CLOSED', 'Closing SSH tunnel');
        }
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}

    /**
     * Prevenir unserialize
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Función helper para obtener la conexión
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Verificar conexión y configuración
 */
function verifyConnection() {
    try {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            return ['success' => false, 'message' => 'Connection failed'];
        }

        $conn = $db->getConnection();

        // Verificar que las tablas existen
        $tables = ['radcheck', 'radreply', 'radacct', 'radgroupcheck', 'radgroupreply'];
        $missingTables = [];

        foreach ($tables as $table) {
            $tableName = TABLE_PREFIX . $table;
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$tableName]);
            $result = $stmt->fetch();
            if ($result['count'] == 0) {
                $missingTables[] = $tableName;
            }
        }

        if (!empty($missingTables)) {
            return [
                'success' => false,
                'message' => 'Missing tables: ' . implode(', ', $missingTables)
            ];
        }

        return [
            'success' => true,
            'message' => 'Connection successful',
            'host' => USE_SSH_TUNNEL ? '127.0.0.1 (via SSH tunnel)' : REMOTE_DB_HOST,
            'database' => REMOTE_DB_NAME
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

?>
