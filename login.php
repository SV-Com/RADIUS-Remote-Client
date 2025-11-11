<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RADIUS Remote Client</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <h1>RADIUS Remote Client</h1>
            <p class="login-subtitle">Gestión Remota de FreeRADIUS</p>

            <?php if (isset($loginError)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($loginError); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <div class="form-group">
                    <label for="api_key">API Key</label>
                    <input type="password" id="api_key" name="api_key"
                           placeholder="Ingresa tu API Key" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Iniciar Sesión
                </button>
            </form>

            <div class="login-info">
                <p>
                    <strong>Conectando a:</strong><br>
                    <?php echo htmlspecialchars(REMOTE_DB_HOST); ?>
                </p>
                <p class="login-help">
                    La API Key se configura en <code>config.php</code>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
