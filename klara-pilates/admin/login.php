<?php
session_start();

// Si ya está logueado, redirigir al panel
if (!empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $contrasena = isset($_POST['contrasena']) ? trim($_POST['contrasena']) : '';

    $configFile = __DIR__ . '/../data/config.json';
    if (!file_exists($configFile)) {
        $error = 'Error de configuración del sistema.';
    } else {
        $config = json_decode(file_get_contents($configFile), true);
        if ($usuario === $config['admin_user'] && $contrasena === $config['admin_pass']) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_user'] = $usuario;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Klara Pilates</title>
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/fonts.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      background: #111;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Roboto', 'Roboto Condensed', Arial, sans-serif;
    }
    .login-wrapper {
      width: 100%;
      max-width: 420px;
      padding: 20px;
    }
    .login-logo {
      text-align: center;
      margin-bottom: 10px;
    }
    .login-logo img {
      max-width: 160px;
      filter: brightness(0) invert(1);
    }
    .login-subtitle {
      text-align: center;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: #666;
      margin-bottom: 35px;
    }
    .login-card {
      background: #1c1c1c;
      border: 1px solid #2a2a2a;
      border-radius: 4px;
      padding: 40px 36px;
    }
    .login-card h2 {
      font-size: 1rem;
      text-transform: uppercase;
      letter-spacing: 3px;
      color: #fff;
      font-weight: 300;
      text-align: center;
      margin-bottom: 30px;
    }
    .form-group-login {
      margin-bottom: 20px;
    }
    .form-group-login label {
      display: block;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #888;
      margin-bottom: 8px;
    }
    .form-group-login input {
      width: 100%;
      padding: 13px 16px;
      background: #111;
      border: 1px solid #333;
      border-radius: 2px;
      color: #fff;
      font-size: 0.95rem;
      font-family: inherit;
      outline: none;
      transition: border 0.2s;
    }
    .form-group-login input:focus {
      border-color: #fff;
    }
    .form-group-login input::placeholder {
      color: #444;
    }
    .btn-login {
      width: 100%;
      padding: 14px;
      background: #fff;
      color: #111;
      border: none;
      border-radius: 2px;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 8px;
      transition: background 0.2s, color 0.2s;
      font-family: inherit;
    }
    .btn-login:hover {
      background: #ddd;
    }
    .error-msg {
      background: #3a1111;
      color: #e57373;
      border: 1px solid #5a1a1a;
      border-radius: 2px;
      padding: 12px 16px;
      font-size: 0.85rem;
      margin-bottom: 20px;
      text-align: center;
    }
    .back-link {
      text-align: center;
      margin-top: 20px;
    }
    .back-link a {
      color: #555;
      font-size: 0.75rem;
      text-decoration: none;
      letter-spacing: 1px;
      text-transform: uppercase;
      transition: color 0.2s;
    }
    .back-link a:hover {
      color: #aaa;
    }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="login-logo">
      <img src="../images/logo-default-145x38.png" alt="Klara Pilates">
    </div>
    <p class="login-subtitle">Panel de Administración</p>

    <div class="login-card">
      <h2>Iniciar sesión</h2>
      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="">
        <div class="form-group-login">
          <label for="usuario">Usuario</label>
          <input type="text" id="usuario" name="usuario" placeholder="Tu usuario" autocomplete="username" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="form-group-login">
          <label for="contrasena">Contraseña</label>
          <input type="password" id="contrasena" name="contrasena" placeholder="Tu contraseña" autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">Ingresar</button>
      </form>
    </div>

    <div class="back-link">
      <a href="../index.html">&#8592; Volver al sitio</a>
    </div>
  </div>
</body>
</html>
