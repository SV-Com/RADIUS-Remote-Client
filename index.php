<?php
/**
 * Cliente Web Remoto para FreeRADIUS
 * Panel de administración de usuarios PPPoE
 */

require_once 'config.php';
require_once 'includes/db.php';

// Verificar autenticación
session_start();

$isAuthenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_key'])) {
    if ($_POST['api_key'] === API_KEY) {
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'API Key incorrecta';
    }
}

// Procesar logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Si no está autenticado, mostrar formulario de login
if (!$isAuthenticated) {
    include 'login.php';
    exit;
}

// Verificar conexión a la base de datos
$connectionStatus = verifyConnection();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RADIUS Remote Client</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1>RADIUS Remote Client</h1>
                <div class="header-info">
                    <span class="connection-status <?php echo $connectionStatus['success'] ? 'connected' : 'disconnected'; ?>">
                        <?php echo $connectionStatus['success'] ? '✓ Conectado' : '✗ Desconectado'; ?>
                    </span>
                    <span class="server-info">
                        <?php echo USE_SSH_TUNNEL ? 'SSH Tunnel' : 'Direct'; ?> |
                        <?php echo REMOTE_DB_HOST; ?>
                    </span>
                    <a href="?logout=1" class="logout-btn">Cerrar Sesión</a>
                </div>
            </div>
        </header>

        <?php if (!$connectionStatus['success']): ?>
        <div class="alert alert-error">
            <strong>Error de conexión:</strong> <?php echo htmlspecialchars($connectionStatus['message']); ?>
            <br><small>Verifica la configuración en config.php y que el servidor remoto esté accesible.</small>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <nav class="nav-tabs">
            <button class="tab-btn active" data-tab="users">Usuarios</button>
            <button class="tab-btn" data-tab="plans">Planes</button>
            <button class="tab-btn" data-tab="stats">Estadísticas</button>
            <button class="tab-btn" data-tab="sessions">Sesiones Activas</button>
            <button class="tab-btn" data-tab="webhooks">Webhooks</button>
            <button class="tab-btn" data-tab="test">Test Conexión</button>
        </nav>

        <!-- Users Tab -->
        <div id="users-tab" class="tab-content active">
            <div class="toolbar">
                <button id="btn-create-user" class="btn btn-primary">+ Crear Usuario</button>
                <button id="btn-export-csv" class="btn btn-secondary">Exportar CSV</button>
                <div class="search-box">
                    <input type="text" id="search-users" placeholder="Buscar usuario...">
                </div>
            </div>

            <div id="users-table-container">
                <table id="users-table" class="data-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Plan</th>
                            <th>Subida</th>
                            <th>Bajada</th>
                            <th>Estado</th>
                            <th>Última Conexión</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <tr>
                            <td colspan="7" class="loading">Cargando usuarios...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div id="pagination" class="pagination"></div>
        </div>

        <!-- Plans Tab -->
        <div id="plans-tab" class="tab-content">
            <div class="toolbar">
                <button id="btn-create-plan" class="btn btn-primary">+ Crear Plan</button>
            </div>

            <div class="plans-grid" id="plans-grid">
                <p class="loading">Cargando planes...</p>
            </div>
        </div>

        <!-- Stats Tab -->
        <div id="stats-tab" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Usuarios</div>
                    <div class="stat-value" id="stat-total-users">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Sesiones Activas</div>
                    <div class="stat-value" id="stat-active-sessions">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Conexiones (hoy)</div>
                    <div class="stat-value" id="stat-today-connections">-</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Tráfico Total (hoy)</div>
                    <div class="stat-value" id="stat-today-traffic">-</div>
                </div>
            </div>

            <div class="chart-container">
                <h3>Tráfico por Hora (últimas 24h)</h3>
                <canvas id="traffic-chart"></canvas>
            </div>
        </div>

        <!-- Sessions Tab -->
        <div id="sessions-tab" class="tab-content">
            <div class="toolbar">
                <button id="btn-refresh-sessions" class="btn btn-secondary">Actualizar</button>
            </div>

            <table id="sessions-table" class="data-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>IP Asignada</th>
                        <th>Inicio Sesión</th>
                        <th>Duración</th>
                        <th>Descarga</th>
                        <th>Subida</th>
                        <th>NAS</th>
                    </tr>
                </thead>
                <tbody id="sessions-tbody">
                    <tr>
                        <td colspan="7" class="loading">Cargando sesiones...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Webhooks Tab -->
        <div id="webhooks-tab" class="tab-content">
            <div class="toolbar">
                <button id="btn-add-webhook" class="btn btn-primary">+ Agregar Webhook</button>
            </div>

            <div id="webhooks-list"></div>
        </div>

        <!-- Test Connection Tab -->
        <div id="test-tab" class="tab-content">
            <div class="test-container">
                <h3>Verificación de Conexión</h3>

                <div class="test-result">
                    <div class="test-item">
                        <span class="test-label">Estado:</span>
                        <span class="test-value <?php echo $connectionStatus['success'] ? 'success' : 'error'; ?>">
                            <?php echo $connectionStatus['success'] ? 'Conectado' : 'Error'; ?>
                        </span>
                    </div>

                    <?php if ($connectionStatus['success']): ?>
                    <div class="test-item">
                        <span class="test-label">Host:</span>
                        <span class="test-value"><?php echo htmlspecialchars($connectionStatus['host']); ?></span>
                    </div>
                    <div class="test-item">
                        <span class="test-label">Base de Datos:</span>
                        <span class="test-value"><?php echo htmlspecialchars($connectionStatus['database']); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="test-item">
                        <span class="test-label">Error:</span>
                        <span class="test-value error"><?php echo htmlspecialchars($connectionStatus['message']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <button id="btn-test-connection" class="btn btn-primary">Probar Conexión</button>
            </div>
        </div>
    </div>

    <!-- Modal: Crear/Editar Usuario -->
    <div id="modal-user" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 id="modal-user-title">Crear Usuario</h2>

            <form id="form-user">
                <input type="hidden" id="user-id" name="id">

                <div class="form-group">
                    <label for="username">Usuario *</label>
                    <input type="text" id="username" name="username" required
                           placeholder="cliente@fibra">
                </div>

                <div class="form-group">
                    <label for="password">Contraseña *</label>
                    <input type="text" id="password" name="password" required>
                    <button type="button" id="btn-generate-password" class="btn-link">Generar</button>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bandwidth_up">Subida *</label>
                        <input type="text" id="bandwidth_up" name="bandwidth_up"
                               placeholder="10M" required>
                    </div>

                    <div class="form-group">
                        <label for="bandwidth_down">Bajada *</label>
                        <input type="text" id="bandwidth_down" name="bandwidth_down"
                               placeholder="10M" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="plan">Plan (opcional)</label>
                    <input type="text" id="plan" name="plan" placeholder="Plan 10MB">
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Crear/Editar Plan -->
    <div id="modal-plan" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 id="modal-plan-title">Crear Plan</h2>

            <form id="form-plan">
                <input type="hidden" id="plan-original-name" name="original_name">

                <div class="form-group">
                    <label for="plan-name">Nombre del Plan *</label>
                    <input type="text" id="plan-name" name="name" required
                           placeholder="Plan 50MB">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="plan-upload">Subida *</label>
                        <input type="text" id="plan-upload" name="upload_speed"
                               placeholder="50M" required>
                        <small>Ejemplos: 10M, 50M, 100M, 1G</small>
                    </div>

                    <div class="form-group">
                        <label for="plan-download">Bajada *</label>
                        <input type="text" id="plan-download" name="download_speed"
                               placeholder="50M" required>
                        <small>Ejemplos: 10M, 50M, 100M, 1G</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="plan-pool">Pool IP (opcional)</label>
                    <input type="text" id="plan-pool" name="pool" placeholder="pool-clientes">
                    <small>Nombre del pool de IPs en FreeRADIUS</small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="js/app.js"></script>
</body>
</html>
