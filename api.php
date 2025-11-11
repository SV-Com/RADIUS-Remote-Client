<?php
/**
 * API REST para RADIUS Remote Client
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'config.php';
require_once 'includes/db.php';

// Clase para manejar la API
class RadiusAPI {
    private $db;

    public function __construct() {
        try {
            $this->db = getDB();
        } catch (Exception $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Procesar la petición
     */
    public function handleRequest() {
        // Autenticación
        if (!$this->authenticate()) {
            $this->sendError('Unauthorized', 401);
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['PATH_INFO'] ?? '/';

        // Routing
        switch ($path) {
            case '/login':
                if ($method === 'POST') {
                    $this->login();
                }
                break;

            case '/users':
                if ($method === 'GET') {
                    $this->getUsers();
                } elseif ($method === 'POST') {
                    $this->createUser();
                }
                break;

            case '/user':
                if ($method === 'GET') {
                    $this->getUser();
                } elseif ($method === 'PUT') {
                    $this->updateUser();
                } elseif ($method === 'DELETE') {
                    $this->deleteUser();
                }
                break;

            case '/stats':
                if ($method === 'GET') {
                    $this->getStats();
                }
                break;

            case '/sessions':
                if ($method === 'GET') {
                    $this->getActiveSessions();
                }
                break;

            case '/history':
                if ($method === 'GET') {
                    $this->getUserHistory();
                }
                break;

            case '/bandwidth-stats':
                if ($method === 'GET') {
                    $this->getBandwidthStats();
                }
                break;

            case '/export':
                if ($method === 'GET') {
                    $this->exportCSV();
                }
                break;

            case '/webhooks':
                if ($method === 'GET') {
                    $this->getWebhooks();
                } elseif ($method === 'POST') {
                    $this->addWebhook();
                } elseif ($method === 'DELETE') {
                    $this->deleteWebhook();
                }
                break;

            case '/test-connection':
                if ($method === 'GET') {
                    $this->testConnection();
                }
                break;

            default:
                $this->sendError('Endpoint not found', 404);
        }
    }

    /**
     * Autenticación
     */
    private function authenticate() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1] === API_KEY;
        }

        // También permitir API key en query string para desarrollo
        return isset($_GET['api_key']) && $_GET['api_key'] === API_KEY;
    }

    /**
     * Login
     */
    private function login() {
        $data = $this->getJsonInput();

        if (!isset($data['api_key'])) {
            $this->sendError('API key required', 400);
        }

        if ($data['api_key'] === API_KEY) {
            $this->sendSuccess(['message' => 'Authenticated', 'token' => API_KEY]);
        } else {
            $this->sendError('Invalid API key', 401);
        }
    }

    /**
     * Obtener lista de usuarios
     */
    private function getUsers() {
        $search = $_GET['search'] ?? '';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        try {
            // Query con búsqueda
            $sql = "SELECT DISTINCT rc.username,
                           MAX(CASE WHEN rc.attribute = 'Cleartext-Password' THEN rc.value END) as password,
                           MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) as rate_limit,
                           MAX(CASE WHEN rr.attribute = 'Framed-Pool' THEN rr.value END) as plan
                    FROM " . TABLE_PREFIX . "radcheck rc
                    LEFT JOIN " . TABLE_PREFIX . "radreply rr ON rc.username = rr.username
                    WHERE 1=1";

            $params = [];

            if ($search) {
                $sql .= " AND rc.username LIKE ?";
                $params[] = "%$search%";
            }

            $sql .= " GROUP BY rc.username ORDER BY rc.username ASC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            // Contar total
            $countSql = "SELECT COUNT(DISTINCT username) as total FROM " . TABLE_PREFIX . "radcheck WHERE 1=1";
            if ($search) {
                $countSql .= " AND username LIKE ?";
                $countStmt = $this->db->prepare($countSql);
                $countStmt->execute(["%$search%"]);
            } else {
                $countStmt = $this->db->query($countSql);
            }
            $total = $countStmt->fetch()['total'];

            // Obtener última conexión de cada usuario
            foreach ($users as &$user) {
                $lastConn = $this->db->prepare(
                    "SELECT acctstarttime, nasipaddress
                     FROM " . TABLE_PREFIX . "radacct
                     WHERE username = ?
                     ORDER BY acctstarttime DESC
                     LIMIT 1"
                );
                $lastConn->execute([$user['username']]);
                $conn = $lastConn->fetch();

                $user['last_connection'] = $conn ? $conn['acctstarttime'] : null;
                $user['nas_ip'] = $conn ? $conn['nasipaddress'] : null;

                // Parsear rate limit
                if ($user['rate_limit']) {
                    $parts = explode('/', $user['rate_limit']);
                    $user['bandwidth_up'] = $parts[0] ?? '';
                    $user['bandwidth_down'] = $parts[1] ?? '';
                }
            }

            $this->sendSuccess([
                'users' => $users,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            $this->sendError('Error fetching users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener un usuario específico
     */
    private function getUser() {
        $username = $_GET['username'] ?? '';

        if (!$username) {
            $this->sendError('Username required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT rc.username,
                        MAX(CASE WHEN rc.attribute = 'Cleartext-Password' THEN rc.value END) as password,
                        MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) as rate_limit,
                        MAX(CASE WHEN rr.attribute = 'Framed-Pool' THEN rr.value END) as plan
                 FROM " . TABLE_PREFIX . "radcheck rc
                 LEFT JOIN " . TABLE_PREFIX . "radreply rr ON rc.username = rr.username
                 WHERE rc.username = ?
                 GROUP BY rc.username"
            );

            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user) {
                $this->sendError('User not found', 404);
            }

            // Parsear rate limit
            if ($user['rate_limit']) {
                $parts = explode('/', $user['rate_limit']);
                $user['bandwidth_up'] = $parts[0] ?? '';
                $user['bandwidth_down'] = $parts[1] ?? '';
            }

            $this->sendSuccess($user);

        } catch (Exception $e) {
            $this->sendError('Error fetching user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Crear usuario
     */
    private function createUser() {
        $data = $this->getJsonInput();

        // Validación
        $required = ['username', 'password', 'bandwidth_up', 'bandwidth_down'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->sendError("Field '$field' is required", 400);
            }
        }

        try {
            $this->db->beginTransaction();

            // Verificar si el usuario ya existe
            $check = $this->db->prepare("SELECT username FROM " . TABLE_PREFIX . "radcheck WHERE username = ?");
            $check->execute([$data['username']]);
            if ($check->fetch()) {
                $this->db->rollBack();
                $this->sendError('User already exists', 409);
            }

            // Insertar en radcheck (autenticación)
            $stmt = $this->db->prepare(
                "INSERT INTO " . TABLE_PREFIX . "radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)"
            );
            $stmt->execute([$data['username'], $data['password']]);

            // Insertar en radreply (rate limit)
            $rateLimit = $data['bandwidth_up'] . '/' . $data['bandwidth_down'];
            $stmt = $this->db->prepare(
                "INSERT INTO " . TABLE_PREFIX . "radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', '=', ?)"
            );
            $stmt->execute([$data['username'], $rateLimit]);

            // Plan (opcional)
            if (!empty($data['plan'])) {
                $stmt = $this->db->prepare(
                    "INSERT INTO " . TABLE_PREFIX . "radreply (username, attribute, op, value) VALUES (?, 'Framed-Pool', '=', ?)"
                );
                $stmt->execute([$data['username'], $data['plan']]);
            }

            $this->db->commit();

            // Webhook
            $this->triggerWebhook('user.created', $data);

            $this->sendSuccess(['message' => 'User created successfully', 'username' => $data['username']], 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->sendError('Error creating user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar usuario
     */
    private function updateUser() {
        $data = $this->getJsonInput();

        if (!isset($data['username'])) {
            $this->sendError('Username required', 400);
        }

        try {
            $this->db->beginTransaction();

            // Actualizar password si se proporciona
            if (!empty($data['password'])) {
                $stmt = $this->db->prepare(
                    "UPDATE " . TABLE_PREFIX . "radcheck SET value = ? WHERE username = ? AND attribute = 'Cleartext-Password'"
                );
                $stmt->execute([$data['password'], $data['username']]);
            }

            // Actualizar rate limit
            if (!empty($data['bandwidth_up']) && !empty($data['bandwidth_down'])) {
                $rateLimit = $data['bandwidth_up'] . '/' . $data['bandwidth_down'];

                // Intentar actualizar
                $stmt = $this->db->prepare(
                    "UPDATE " . TABLE_PREFIX . "radreply SET value = ? WHERE username = ? AND attribute = 'Mikrotik-Rate-Limit'"
                );
                $stmt->execute([$rateLimit, $data['username']]);

                // Si no existía, insertar
                if ($stmt->rowCount() === 0) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO " . TABLE_PREFIX . "radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', '=', ?)"
                    );
                    $stmt->execute([$data['username'], $rateLimit]);
                }
            }

            // Actualizar plan
            if (isset($data['plan'])) {
                $stmt = $this->db->prepare(
                    "UPDATE " . TABLE_PREFIX . "radreply SET value = ? WHERE username = ? AND attribute = 'Framed-Pool'"
                );
                $stmt->execute([$data['plan'], $data['username']]);

                if ($stmt->rowCount() === 0 && !empty($data['plan'])) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO " . TABLE_PREFIX . "radreply (username, attribute, op, value) VALUES (?, 'Framed-Pool', '=', ?)"
                    );
                    $stmt->execute([$data['username'], $data['plan']]);
                }
            }

            $this->db->commit();

            // Webhook
            $this->triggerWebhook('user.updated', $data);

            $this->sendSuccess(['message' => 'User updated successfully']);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->sendError('Error updating user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar usuario
     */
    private function deleteUser() {
        $username = $_GET['username'] ?? '';

        if (!$username) {
            $this->sendError('Username required', 400);
        }

        try {
            $this->db->beginTransaction();

            // Eliminar de radcheck
            $stmt = $this->db->prepare("DELETE FROM " . TABLE_PREFIX . "radcheck WHERE username = ?");
            $stmt->execute([$username]);

            // Eliminar de radreply
            $stmt = $this->db->prepare("DELETE FROM " . TABLE_PREFIX . "radreply WHERE username = ?");
            $stmt->execute([$username]);

            // Eliminar de radgroupcheck
            $stmt = $this->db->prepare("DELETE FROM " . TABLE_PREFIX . "radusergroup WHERE username = ?");
            $stmt->execute([$username]);

            $this->db->commit();

            // Webhook
            $this->triggerWebhook('user.deleted', ['username' => $username]);

            $this->sendSuccess(['message' => 'User deleted successfully']);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->sendError('Error deleting user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas
     */
    private function getStats() {
        try {
            // Total usuarios
            $stmt = $this->db->query("SELECT COUNT(DISTINCT username) as total FROM " . TABLE_PREFIX . "radcheck");
            $totalUsers = $stmt->fetch()['total'];

            // Sesiones activas
            $stmt = $this->db->query(
                "SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "radacct WHERE acctstoptime IS NULL"
            );
            $activeSessions = $stmt->fetch()['total'];

            // Conexiones hoy
            $stmt = $this->db->query(
                "SELECT COUNT(*) as total FROM " . TABLE_PREFIX . "radacct WHERE DATE(acctstarttime) = CURDATE()"
            );
            $todayConnections = $stmt->fetch()['total'];

            // Tráfico hoy (en bytes)
            $stmt = $this->db->query(
                "SELECT SUM(acctinputoctets + acctoutputoctets) as total
                 FROM " . TABLE_PREFIX . "radacct
                 WHERE DATE(acctstarttime) = CURDATE()"
            );
            $todayTraffic = $stmt->fetch()['total'] ?? 0;

            $this->sendSuccess([
                'total_users' => $totalUsers,
                'active_sessions' => $activeSessions,
                'today_connections' => $todayConnections,
                'today_traffic' => $todayTraffic,
                'today_traffic_formatted' => $this->formatBytes($todayTraffic)
            ]);

        } catch (Exception $e) {
            $this->sendError('Error fetching stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener sesiones activas
     */
    private function getActiveSessions() {
        try {
            $stmt = $this->db->query(
                "SELECT username, framedipaddress, acctstarttime,
                        TIMESTAMPDIFF(SECOND, acctstarttime, NOW()) as duration,
                        acctinputoctets, acctoutputoctets, nasipaddress
                 FROM " . TABLE_PREFIX . "radacct
                 WHERE acctstoptime IS NULL
                 ORDER BY acctstarttime DESC"
            );

            $sessions = $stmt->fetchAll();

            foreach ($sessions as &$session) {
                $session['download_formatted'] = $this->formatBytes($session['acctinputoctets']);
                $session['upload_formatted'] = $this->formatBytes($session['acctoutputoctets']);
                $session['duration_formatted'] = $this->formatDuration($session['duration']);
            }

            $this->sendSuccess($sessions);

        } catch (Exception $e) {
            $this->sendError('Error fetching sessions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener historial de usuario
     */
    private function getUserHistory() {
        $username = $_GET['username'] ?? '';

        if (!$username) {
            $this->sendError('Username required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT acctstarttime, acctstoptime,
                        TIMESTAMPDIFF(SECOND, acctstarttime, acctstoptime) as duration,
                        acctinputoctets, acctoutputoctets, framedipaddress, nasipaddress
                 FROM " . TABLE_PREFIX . "radacct
                 WHERE username = ?
                 ORDER BY acctstarttime DESC
                 LIMIT 100"
            );

            $stmt->execute([$username]);
            $history = $stmt->fetchAll();

            foreach ($history as &$record) {
                $record['download_formatted'] = $this->formatBytes($record['acctinputoctets']);
                $record['upload_formatted'] = $this->formatBytes($record['acctoutputoctets']);
                $record['duration_formatted'] = $this->formatDuration($record['duration']);
            }

            $this->sendSuccess($history);

        } catch (Exception $e) {
            $this->sendError('Error fetching history: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener estadísticas de ancho de banda
     */
    private function getBandwidthStats() {
        try {
            $stmt = $this->db->query(
                "SELECT DATE_FORMAT(acctstarttime, '%Y-%m-%d %H:00:00') as hour,
                        SUM(acctinputoctets) as download,
                        SUM(acctoutputoctets) as upload
                 FROM " . TABLE_PREFIX . "radacct
                 WHERE acctstarttime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY hour
                 ORDER BY hour ASC"
            );

            $stats = $stmt->fetchAll();

            $this->sendSuccess($stats);

        } catch (Exception $e) {
            $this->sendError('Error fetching bandwidth stats: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Exportar a CSV
     */
    private function exportCSV() {
        try {
            $stmt = $this->db->query(
                "SELECT DISTINCT rc.username,
                        MAX(CASE WHEN rc.attribute = 'Cleartext-Password' THEN rc.value END) as password,
                        MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) as rate_limit,
                        MAX(CASE WHEN rr.attribute = 'Framed-Pool' THEN rr.value END) as plan
                 FROM " . TABLE_PREFIX . "radcheck rc
                 LEFT JOIN " . TABLE_PREFIX . "radreply rr ON rc.username = rr.username
                 GROUP BY rc.username
                 ORDER BY rc.username ASC"
            );

            $users = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="radius_users_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Usuario', 'Password', 'Subida', 'Bajada', 'Plan']);

            foreach ($users as $user) {
                $rateLimit = explode('/', $user['rate_limit'] ?? '');
                fputcsv($output, [
                    $user['username'],
                    $user['password'],
                    $rateLimit[0] ?? '',
                    $rateLimit[1] ?? '',
                    $user['plan'] ?? ''
                ]);
            }

            fclose($output);
            exit;

        } catch (Exception $e) {
            $this->sendError('Error exporting CSV: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener webhooks
     */
    private function getWebhooks() {
        if (!WEBHOOKS_ENABLED) {
            $this->sendSuccess([]);
        }

        if (file_exists(WEBHOOKS_FILE)) {
            $webhooks = json_decode(file_get_contents(WEBHOOKS_FILE), true);
            $this->sendSuccess($webhooks ?? []);
        } else {
            $this->sendSuccess([]);
        }
    }

    /**
     * Agregar webhook
     */
    private function addWebhook() {
        $data = $this->getJsonInput();

        if (!isset($data['url']) || !isset($data['event'])) {
            $this->sendError('URL and event are required', 400);
        }

        $webhooks = [];
        if (file_exists(WEBHOOKS_FILE)) {
            $webhooks = json_decode(file_get_contents(WEBHOOKS_FILE), true) ?? [];
        }

        $webhooks[] = [
            'id' => uniqid(),
            'url' => $data['url'],
            'event' => $data['event'],
            'created' => date('Y-m-d H:i:s')
        ];

        file_put_contents(WEBHOOKS_FILE, json_encode($webhooks, JSON_PRETTY_PRINT));

        $this->sendSuccess(['message' => 'Webhook added successfully'], 201);
    }

    /**
     * Eliminar webhook
     */
    private function deleteWebhook() {
        $id = $_GET['id'] ?? '';

        if (!$id) {
            $this->sendError('Webhook ID required', 400);
        }

        if (file_exists(WEBHOOKS_FILE)) {
            $webhooks = json_decode(file_get_contents(WEBHOOKS_FILE), true) ?? [];
            $webhooks = array_filter($webhooks, function($w) use ($id) {
                return $w['id'] !== $id;
            });

            file_put_contents(WEBHOOKS_FILE, json_encode(array_values($webhooks), JSON_PRETTY_PRINT));
        }

        $this->sendSuccess(['message' => 'Webhook deleted successfully']);
    }

    /**
     * Test de conexión
     */
    private function testConnection() {
        $result = verifyConnection();
        $this->sendSuccess($result);
    }

    /**
     * Disparar webhook
     */
    private function triggerWebhook($event, $data) {
        if (!WEBHOOKS_ENABLED || !file_exists(WEBHOOKS_FILE)) {
            return;
        }

        $webhooks = json_decode(file_get_contents(WEBHOOKS_FILE), true) ?? [];

        foreach ($webhooks as $webhook) {
            if ($webhook['event'] === $event || $webhook['event'] === '*') {
                $payload = json_encode([
                    'event' => $event,
                    'data' => $data,
                    'timestamp' => date('c')
                ]);

                // Enviar webhook de forma asíncrona
                $ch = curl_init($webhook['url']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        }
    }

    /**
     * Helpers
     */
    private function getJsonInput() {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function sendSuccess($data, $code = 200) {
        http_response_code($code);
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function formatDuration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}

// Ejecutar API
try {
    $api = new RadiusAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
