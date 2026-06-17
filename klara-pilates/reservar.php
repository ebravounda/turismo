<?php
$dataDir      = __DIR__ . '/data/';
$configFile   = $dataDir . 'config.json';
$reservasFile = $dataDir . 'reservas.json';

// Valores por defecto si no existe el archivo de configuración
$defaultConfig = [
    'precios' => [
        'clase_individual' => 5000,
        'pack_4_clases'    => 18000,
        'pack_8_clases'    => 32000,
        'pack_mensual'     => 45000,
    ],
    'moneda'          => '$',
    'cupos_por_clase' => 4,
    'horarios'        => ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'],
    'tipos_clase'     => ['Pilates Reformer Clásico','Reformer con Tower','Pilates Prenatal','Reformer Avanzado'],
    'admin_user'      => 'admin',
    'admin_pass'      => 'klara2025',
];

// Crear directorio data/ si no existe
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0755, true);
}

// Crear config.json si no existe
if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Crear reservas.json si no existe
if (!file_exists($reservasFile)) {
    file_put_contents($reservasFile, '[]', LOCK_EX);
}

// Leer configuración con fallback
$configRaw = @file_get_contents($configFile);
$config    = $configRaw ? json_decode($configRaw, true) : null;
if (!is_array($config)) {
    $config = $defaultConfig;
}

$precios    = $config['precios'];
$horarios   = $config['horarios'];
$tiposClase = $config['tipos_clase'];
$cupos      = $config['cupos_por_clase'];
$moneda     = $config['moneda'];

// Manejar peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $reservas = json_decode(file_get_contents($reservasFile), true);
    if (!is_array($reservas)) $reservas = [];

    $action = $_POST['action'];

    if ($action === 'get_slots') {
        $fecha = isset($_POST['fecha']) ? preg_replace('/[^0-9\-]/', '', $_POST['fecha']) : '';
        if (empty($fecha) || strtotime($fecha) < strtotime(date('Y-m-d'))) {
            echo json_encode(['error' => 'Fecha inválida']);
            exit;
        }

        // Recargar config por si cambió
        $cfg = json_decode(file_get_contents($configFile), true);
        $cuposPorClase = (int)$cfg['cupos_por_clase'];
        $horariosDisp = $cfg['horarios'];

        $slots = [];
        foreach ($horariosDisp as $hora) {
            $ocupados = 0;
            foreach ($reservas as $r) {
                if ($r['fecha'] === $fecha && $r['hora'] === $hora && $r['estado'] !== 'cancelada') {
                    $ocupados++;
                }
            }
            $disponibles = $cuposPorClase - $ocupados;
            $slots[] = [
                'hora' => $hora,
                'disponibles' => max(0, $disponibles),
                'cupos_total' => $cuposPorClase,
            ];
        }
        echo json_encode(['slots' => $slots]);
        exit;
    }

    if ($action === 'reservar') {
        $nombre   = isset($_POST['nombre'])     ? htmlspecialchars(trim($_POST['nombre']), ENT_QUOTES)     : '';
        $email    = isset($_POST['email'])      ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL)  : '';
        $telefono = isset($_POST['telefono'])   ? htmlspecialchars(trim($_POST['telefono']), ENT_QUOTES)   : '';
        $fecha    = isset($_POST['fecha'])      ? preg_replace('/[^0-9\-]/', '', $_POST['fecha'])           : '';
        $hora     = isset($_POST['hora'])       ? preg_replace('/[^0-9:]/', '', $_POST['hora'])             : '';
        $tipo     = isset($_POST['tipo_clase']) ? htmlspecialchars(trim($_POST['tipo_clase']), ENT_QUOTES)  : '';

        // Validaciones
        if (empty($nombre)) { echo json_encode(['success' => false, 'error' => 'El nombre es requerido']); exit; }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success' => false, 'error' => 'El email es inválido']); exit; }
        if (empty($fecha) || strtotime($fecha) < strtotime(date('Y-m-d'))) { echo json_encode(['success' => false, 'error' => 'La fecha no puede ser anterior a hoy']); exit; }

        $cfg = json_decode(file_get_contents($configFile), true);
        $horariosValidos = $cfg['horarios'];
        $cuposPorClase   = (int)$cfg['cupos_por_clase'];
        $tiposValidos    = $cfg['tipos_clase'];
        $precio          = (int)$cfg['precios']['clase_individual'];

        if (!in_array($hora, $horariosValidos)) { echo json_encode(['success' => false, 'error' => 'Horario no válido']); exit; }
        if (!in_array($tipo, $tiposValidos))    { echo json_encode(['success' => false, 'error' => 'Tipo de clase no válido']); exit; }

        // Verificar cupos
        $reservas = json_decode(file_get_contents($reservasFile), true);
        if (!is_array($reservas)) $reservas = [];
        $ocupados = 0;
        foreach ($reservas as $r) {
            if ($r['fecha'] === $fecha && $r['hora'] === $hora && $r['estado'] !== 'cancelada') {
                $ocupados++;
            }
        }
        if ($ocupados >= $cuposPorClase) {
            echo json_encode(['success' => false, 'error' => 'No hay cupos disponibles para ese horario']);
            exit;
        }

        // Calcular hora fin (+45 min)
        $horaFin = date('H:i', strtotime($hora) + 45 * 60);

        $nuevaReserva = [
            'id'         => uniqid('kp_', true),
            'nombre'     => $nombre,
            'email'      => $email,
            'telefono'   => $telefono,
            'fecha'      => $fecha,
            'hora'       => $hora,
            'hora_fin'   => $horaFin,
            'tipo_clase' => $tipo,
            'precio'     => $precio,
            'estado'     => 'confirmada',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $reservas[] = $nuevaReserva;
        file_put_contents($reservasFile, json_encode($reservas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        echo json_encode([
            'success'  => true,
            'reserva'  => $nuevaReserva,
            'mensaje'  => 'Reserva confirmada exitosamente',
        ]);
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']);
    exit;
}

// Formatear precio para mostrar
function formatPrecio($num) {
    return '$' . number_format($num, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html class="wide wow-animation" lang="es">
<head>
  <title>Reservar Clase | Klara Pilates</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <link rel="icon" href="images/favicon.ico" type="image/x-icon">
  <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Roboto+Condensed:300,400,700,300i%7CRoboto:400,300i">
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/fonts.css">
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ===== RESERVAS CUSTOM STYLES ===== */
    .reservas-hero {
      background: #1a1a1a;
      padding: 80px 0 40px;
      text-align: center;
      color: #fff;
    }
    .reservas-hero h2 {
      font-size: 2.6rem;
      font-weight: 300;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-bottom: 10px;
    }
    .reservas-hero p {
      color: #aaa;
      font-size: 1rem;
      letter-spacing: 1px;
    }
    /* Precios */
    .section-precios {
      background: #f7f7f7;
      padding: 60px 0;
    }
    .section-precios h3 {
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 3px;
      font-weight: 300;
      color: #1a1a1a;
      margin-bottom: 40px;
      font-size: 1.5rem;
    }
    .precio-card {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
      padding: 35px 25px;
      text-align: center;
      transition: all 0.3s ease;
      height: 100%;
    }
    .precio-card:hover {
      border-color: #1a1a1a;
      box-shadow: 0 6px 30px rgba(0,0,0,0.12);
      transform: translateY(-4px);
    }
    .precio-card.destacado {
      background: #1a1a1a;
      color: #fff;
      border-color: #1a1a1a;
    }
    .precio-card.destacado .precio-valor {
      color: #fff;
    }
    .precio-card.destacado .precio-label {
      color: #ccc;
    }
    .precio-card.destacado .precio-descripcion {
      color: #aaa;
    }
    .precio-icon {
      font-size: 2rem;
      margin-bottom: 15px;
      display: block;
    }
    .precio-nombre {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #888;
      margin-bottom: 15px;
    }
    .precio-card.destacado .precio-nombre {
      color: #ccc;
    }
    .precio-valor {
      font-size: 2.4rem;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1;
      margin-bottom: 5px;
    }
    .precio-label {
      font-size: 0.75rem;
      color: #999;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 15px;
    }
    .precio-descripcion {
      font-size: 0.85rem;
      color: #666;
      border-top: 1px solid #eee;
      padding-top: 15px;
      margin-top: 10px;
    }
    .precio-card.destacado .precio-descripcion {
      border-color: #333;
    }
    /* Sección de reserva */
    .section-reserva {
      background: #fff;
      padding: 60px 0 80px;
    }
    .section-reserva h3 {
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 3px;
      font-weight: 300;
      color: #1a1a1a;
      margin-bottom: 10px;
      font-size: 1.5rem;
    }
    .section-reserva .subtitulo {
      text-align: center;
      color: #888;
      margin-bottom: 40px;
      font-size: 0.9rem;
      letter-spacing: 1px;
    }
    /* Steps */
    .steps-nav {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 40px;
      gap: 0;
    }
    .step-indicator {
      display: flex;
      flex-direction: column;
      align-items: center;
      flex: 1;
      max-width: 160px;
    }
    .step-circle {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #e0e0e0;
      color: #999;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
      margin-bottom: 6px;
      transition: all 0.3s;
    }
    .step-circle.active {
      background: #1a1a1a;
      color: #fff;
    }
    .step-circle.done {
      background: #4a4a4a;
      color: #fff;
    }
    .step-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #999;
      text-align: center;
    }
    .step-label.active {
      color: #1a1a1a;
      font-weight: 700;
    }
    .step-line {
      flex: 1;
      height: 1px;
      background: #e0e0e0;
      max-width: 80px;
      margin-bottom: 26px;
    }
    /* Pasos */
    .paso {
      display: none;
      animation: fadeInUp 0.3s ease;
    }
    .paso.activo {
      display: block;
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    /* Paso 1 */
    .fecha-selector {
      max-width: 480px;
      margin: 0 auto;
      text-align: center;
    }
    .fecha-selector label {
      display: block;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #555;
      margin-bottom: 10px;
    }
    .fecha-selector input[type="date"] {
      width: 100%;
      padding: 14px 18px;
      border: 1px solid #ddd;
      border-radius: 2px;
      font-size: 1rem;
      margin-bottom: 20px;
      outline: none;
      transition: border 0.2s;
      font-family: inherit;
    }
    .fecha-selector input[type="date"]:focus {
      border-color: #1a1a1a;
    }
    /* Slots */
    .slots-header {
      text-align: center;
      margin-bottom: 25px;
      font-size: 0.85rem;
      color: #666;
    }
    .slots-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 12px;
      max-width: 700px;
      margin: 0 auto 30px;
    }
    .slot-btn {
      border: 1px solid #ddd;
      border-radius: 3px;
      padding: 16px 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: #fff;
    }
    .slot-btn:hover:not(.slot-lleno) {
      border-color: #1a1a1a;
      background: #f5f5f5;
    }
    .slot-btn.slot-lleno {
      opacity: 0.45;
      cursor: not-allowed;
      background: #f9f9f9;
    }
    .slot-btn.slot-seleccionado {
      background: #1a1a1a;
      border-color: #1a1a1a;
      color: #fff;
    }
    .slot-hora {
      font-size: 1.1rem;
      font-weight: 700;
      display: block;
      margin-bottom: 5px;
    }
    .slot-cupos {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .slot-cupos.disponible { color: #2e7d32; }
    .slot-cupos.lleno { color: #c62828; }
    .slot-btn.slot-seleccionado .slot-cupos.disponible { color: #a5d6a7; }
    /* Paso 3 - Formulario */
    .reserva-form {
      max-width: 560px;
      margin: 0 auto;
    }
    .resumen-seleccion {
      background: #f7f7f7;
      border-left: 3px solid #1a1a1a;
      padding: 16px 20px;
      margin-bottom: 28px;
      border-radius: 0 3px 3px 0;
    }
    .resumen-seleccion p {
      margin: 0;
      font-size: 0.88rem;
      color: #444;
      line-height: 1.8;
    }
    .resumen-seleccion strong {
      color: #1a1a1a;
    }
    .form-group-kp label {
      display: block;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #555;
      margin-bottom: 6px;
    }
    .form-group-kp {
      margin-bottom: 20px;
    }
    .form-group-kp input,
    .form-group-kp select {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #ddd;
      border-radius: 2px;
      font-size: 0.95rem;
      font-family: inherit;
      outline: none;
      transition: border 0.2s;
      background: #fff;
    }
    .form-group-kp input:focus,
    .form-group-kp select:focus {
      border-color: #1a1a1a;
    }
    .precio-indicador {
      background: #1a1a1a;
      color: #fff;
      padding: 14px 20px;
      border-radius: 2px;
      text-align: center;
      margin-bottom: 24px;
      font-size: 0.85rem;
      letter-spacing: 1px;
    }
    .precio-indicador span {
      font-size: 1.3rem;
      font-weight: 700;
      display: block;
      margin-top: 4px;
    }
    /* Botones */
    .btn-kp {
      display: inline-block;
      padding: 14px 36px;
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      transition: all 0.25s;
      border-radius: 2px;
      font-family: inherit;
    }
    .btn-kp-primary {
      background: #1a1a1a;
      color: #fff;
    }
    .btn-kp-primary:hover {
      background: #333;
    }
    .btn-kp-outline {
      background: transparent;
      color: #1a1a1a;
      border: 1px solid #1a1a1a;
    }
    .btn-kp-outline:hover {
      background: #1a1a1a;
      color: #fff;
    }
    /* Confirmación */
    .confirmacion-box {
      max-width: 520px;
      margin: 0 auto;
      text-align: center;
    }
    .confirmacion-icon {
      width: 70px;
      height: 70px;
      background: #1a1a1a;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 25px;
      font-size: 2rem;
    }
    .confirmacion-box h4 {
      font-size: 1.4rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-weight: 300;
      margin-bottom: 15px;
    }
    .detalle-reserva {
      background: #f7f7f7;
      padding: 20px 25px;
      border-radius: 3px;
      text-align: left;
      margin: 20px 0 30px;
      font-size: 0.88rem;
    }
    .detalle-reserva dl {
      margin: 0;
    }
    .detalle-reserva dt {
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #888;
      margin-top: 12px;
    }
    .detalle-reserva dt:first-child {
      margin-top: 0;
    }
    .detalle-reserva dd {
      color: #1a1a1a;
      font-weight: 600;
      margin: 0;
    }
    /* Error / Alert */
    .alert-kp {
      padding: 12px 18px;
      border-radius: 2px;
      font-size: 0.88rem;
      margin-bottom: 18px;
      display: none;
    }
    .alert-kp.error {
      background: #fdecea;
      color: #c62828;
      border-left: 3px solid #c62828;
    }
    .alert-kp.visible {
      display: block;
    }
    .loading-spinner {
      display: none;
      text-align: center;
      padding: 30px;
      color: #888;
    }
    .loading-spinner.visible {
      display: block;
    }
    /* Separador visual entre secciones */
    .section-divider {
      width: 50px;
      height: 2px;
      background: #1a1a1a;
      margin: 0 auto 20px;
    }
  </style>
</head>
<body>
<div class="preloader">
  <div class="preloader-body">
    <div class="cssload-container">
      <div class="cssload-speeding-wheel"></div>
    </div>
    <p>Loading...</p>
  </div>
</div>
<div class="page">
  <!-- Navbar -->
  <header class="section page-header" id="home">
    <div class="rd-navbar-wrap">
      <nav class="rd-navbar rd-navbar-classic" data-layout="rd-navbar-fixed" data-sm-layout="rd-navbar-fixed" data-md-layout="rd-navbar-fixed" data-md-device-layout="rd-navbar-fixed" data-lg-layout="rd-navbar-static" data-lg-device-layout="rd-navbar-static" data-xl-layout="rd-navbar-static" data-xl-device-layout="rd-navbar-static" data-lg-stick-up-offset="46px" data-xl-stick-up-offset="46px" data-xxl-stick-up-offset="46px" data-lg-stick-up="true" data-xl-stick-up="true" data-xxl-stick-up="true">
        <div class="rd-navbar-collapse-toggle rd-navbar-fixed-element-1" data-rd-navbar-toggle=".rd-navbar-collapse"><span></span></div>
        <div class="rd-navbar-main-outer">
          <div class="rd-navbar-main">
            <div class="rd-navbar-panel">
              <button class="rd-navbar-toggle" data-rd-navbar-toggle=".rd-navbar-nav-wrap"><span></span></button>
              <div class="rd-navbar-brand"><a class="brand" href="index.html"><img src="images/logo-klara.png" alt="Klara Pilates" style="height:50px;width:auto"/></a></div>
              <div class="rd-navbar-collapse">
                <div class="navbar-collapse-content">
                  <div class="logo-caption"><span>estudio de</span><span>pilates reformer</span></div>
                  <ul class="social-links">
                    <li><a class="icon mdi mdi-facebook" href="#"></a></li>
                    <li><a class="icon mdi-instagram mdi" href="#"></a></li>
                    <li><a class="icon icon-sm mdi mdi-youtube-play" href="#"></a></li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="rd-navbar-main-element">
              <div class="rd-navbar-nav-wrap">
                <ul class="rd-navbar-nav">
                  <li class="rd-nav-item"><a class="rd-nav-link" href="index.html">Inicio</a></li>
                  <li class="rd-nav-item"><a class="rd-nav-link" href="index.html#services">Clases</a></li>
                  <li class="rd-nav-item"><a class="rd-nav-link" href="index.html#gallery">Galería</a></li>
                  <li class="rd-nav-item active"><a class="rd-nav-link" href="reservar.php">Reservar</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <!-- Hero de reservas -->
  <div class="reservas-hero">
    <div class="container">
      <h2>Reserva tu clase</h2>
      <p>Elige el horario que más te acomode y asegura tu lugar</p>
    </div>
  </div>

  <!-- Sección de Precios -->
  <section class="section-precios">
    <div class="container">
      <h3>Nuestros Paquetes</h3>
      <div class="section-divider"></div>
      <br>
      <div class="row justify-content-center" style="row-gap:20px;">
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 d-flex">
          <div class="precio-card">
            <span class="precio-icon">&#9675;</span>
            <p class="precio-nombre">Clase Individual</p>
            <div class="precio-valor"><?= formatPrecio($precios['clase_individual']) ?></div>
            <div class="precio-label">por clase</div>
            <div class="precio-descripcion">Prueba una clase sin compromiso. Ideal para principiantes.</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 d-flex">
          <div class="precio-card">
            <span class="precio-icon">&#9675;&#9675;&#9675;&#9675;</span>
            <p class="precio-nombre">Pack 4 Clases</p>
            <div class="precio-valor"><?= formatPrecio($precios['pack_4_clases']) ?></div>
            <div class="precio-label"><?= formatPrecio(intval($precios['pack_4_clases']/4)) ?> por clase</div>
            <div class="precio-descripcion">Comienza tu práctica con regularidad. Válido por 30 días.</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 d-flex">
          <div class="precio-card destacado">
            <span class="precio-icon">&#11088;</span>
            <p class="precio-nombre">Pack 8 Clases</p>
            <div class="precio-valor"><?= formatPrecio($precios['pack_8_clases']) ?></div>
            <div class="precio-label"><?= formatPrecio(intval($precios['pack_8_clases']/8)) ?> por clase</div>
            <div class="precio-descripcion">El más elegido. Mayor ahorro y continuidad en tu práctica.</div>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-6 col-12 d-flex">
          <div class="precio-card">
            <span class="precio-icon">&#8734;</span>
            <p class="precio-nombre">Pack Mensual</p>
            <div class="precio-valor"><?= formatPrecio($precios['pack_mensual']) ?></div>
            <div class="precio-label">clases ilimitadas</div>
            <div class="precio-descripcion">Viene cuando quieras durante todo el mes. Máximo compromiso.</div>
          </div>
        </div>
      </div>
      <p style="text-align:center; margin-top:30px; font-size:0.8rem; color:#999; letter-spacing:1px;">* Las reservas online se realizan por clase individual. Para adquirir packs, contáctanos.</p>
    </div>
  </section>

  <!-- Sección de Reserva -->
  <section class="section-reserva">
    <div class="container">
      <h3>Reserva tu lugar</h3>
      <div class="section-divider"></div>
      <p class="subtitulo">Selecciona fecha, horario y completa tus datos</p>

      <!-- Indicador de pasos -->
      <div class="steps-nav">
        <div class="step-indicator">
          <div class="step-circle active" id="circle-1">1</div>
          <div class="step-label active" id="label-1">Elige fecha</div>
        </div>
        <div class="step-line"></div>
        <div class="step-indicator">
          <div class="step-circle" id="circle-2">2</div>
          <div class="step-label" id="label-2">Elige horario</div>
        </div>
        <div class="step-line"></div>
        <div class="step-indicator">
          <div class="step-circle" id="circle-3">3</div>
          <div class="step-label" id="label-3">Tus datos</div>
        </div>
      </div>

      <!-- Paso 1: Fecha -->
      <div class="paso activo" id="paso-1">
        <div class="fecha-selector">
          <label for="input-fecha">Selecciona una fecha</label>
          <input type="date" id="input-fecha" min="<?= date('Y-m-d') ?>">
          <div class="alert-kp error" id="error-fecha"></div>
          <button class="btn-kp btn-kp-primary" onclick="verHorarios()">Ver horarios disponibles</button>
        </div>
      </div>

      <!-- Paso 2: Slots -->
      <div class="paso" id="paso-2">
        <div class="slots-header">
          <span id="slots-fecha-label"></span>
        </div>
        <div class="loading-spinner" id="loading-slots">Cargando horarios...</div>
        <div class="alert-kp error" id="error-slots"></div>
        <div class="slots-grid" id="slots-grid"></div>
        <div style="text-align:center;">
          <button class="btn-kp btn-kp-outline" onclick="volverPaso(1)">&#8592; Cambiar fecha</button>
        </div>
      </div>

      <!-- Paso 3: Formulario -->
      <div class="paso" id="paso-3">
        <div class="reserva-form">
          <div class="resumen-seleccion" id="resumen-seleccion">
            <p>
              <strong>Fecha:</strong> <span id="resumen-fecha"></span><br>
              <strong>Horario:</strong> <span id="resumen-hora"></span> – <span id="resumen-hora-fin"></span><br>
            </p>
          </div>

          <div class="alert-kp error" id="error-formulario"></div>

          <div class="form-group-kp">
            <label for="campo-nombre">Nombre completo <span style="color:#c00">*</span></label>
            <input type="text" id="campo-nombre" placeholder="Tu nombre y apellido">
          </div>
          <div class="form-group-kp">
            <label for="campo-email">Email <span style="color:#c00">*</span></label>
            <input type="email" id="campo-email" placeholder="tu@email.com">
          </div>
          <div class="form-group-kp">
            <label for="campo-telefono">Teléfono</label>
            <input type="tel" id="campo-telefono" placeholder="11 1234-5678">
          </div>
          <div class="form-group-kp">
            <label for="campo-tipo">Tipo de clase <span style="color:#c00">*</span></label>
            <select id="campo-tipo">
              <option value="">Selecciona un tipo de clase</option>
              <?php foreach ($tiposClase as $tipo): ?>
              <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipo) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="precio-indicador">
            Precio clase individual
            <span><?= formatPrecio($precios['clase_individual']) ?></span>
          </div>

          <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button class="btn-kp btn-kp-outline" onclick="volverPaso(2)">&#8592; Volver</button>
            <button class="btn-kp btn-kp-primary" onclick="confirmarReserva()" id="btn-confirmar">Confirmar reserva</button>
          </div>
        </div>
      </div>

      <!-- Confirmación -->
      <div class="paso" id="paso-confirmacion">
        <div class="confirmacion-box">
          <div class="confirmacion-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <h4>¡Reserva confirmada!</h4>
          <p style="color:#666; font-size:0.9rem;">Te esperamos en el estudio. Recibirás un recordatorio antes de tu clase.</p>
          <div class="detalle-reserva" id="detalle-confirmacion">
            <dl id="detalle-lista"></dl>
          </div>
          <button class="btn-kp btn-kp-primary" onclick="nuevaReserva()">Hacer otra reserva</button>
          <br><br>
          <a href="index.html" style="font-size:0.8rem; color:#888; text-decoration:none; letter-spacing:1px;">&#8592; Volver al inicio</a>
        </div>
      </div>

    </div>
  </section>

  <!-- Footer -->
  <footer class="section footer-classic context-light">
    <div class="container">
      <div class="row row-30 justify-content-center">
        <div class="col-md-4 col-sm-6">
          <a class="brand" href="index.html"><img src="images/logo-klara.png" alt="Klara Pilates" style="height:50px;width:auto"/></a>
          <ul>
            <li>Lun-Vie: 8am – 20pm</li>
            <li>Sáb: 9am – 13pm</li>
          </ul>
        </div>
        <div class="col-md-4 col-sm-6">
          <h6>Dirección</h6>
          <ul>
            <li>Tu dirección aquí</li>
            <li>Ciudad, País</li>
            <li><a class="map-link" href="#">Ver en el mapa</a></li>
          </ul>
        </div>
        <div class="col-md-4 col-sm-6">
          <div class="centered-content">
            <h6>Contacto</h6>
            <ul class="contact-links">
              <li><span>Teléfono</span><a href="tel:#">Tu número aquí</a></li>
              <li><span>E-Mail</span><a href="mailto:#">info@klarapilates.com</a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
    <div class="devider"><hr></div>
    <div class="container">
      <div class="row row-30 justify-content-xl-between justify-content-center flex-column-reverse flex-md-row">
        <div class="col-12 col-md-7">
          <p class="rights"><span>&copy;&nbsp;</span><span class="copyright-year"></span><span>&nbsp;</span><span>Klara Pilates</span><span>.&nbsp;</span><a href="#">Política de Privacidad</a></p>
        </div>
        <div class="col-12 col-md-4">
          <div class="centered-content">
            <ul class="social-links">
              <li><a class="icon mdi mdi-facebook" href="#"></a></li>
              <li><a class="icon mdi-instagram mdi" href="#"></a></li>
              <li><a class="icon icon-sm mdi mdi-youtube-play" href="#"></a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </footer>
</div>

<script src="js/core.min.js"></script>
<script src="js/script.js"></script>
<script>
// Estado global
var estado = {
  fechaSeleccionada: '',
  horaSeleccionada: '',
  horaFinSeleccionada: ''
};

// Formatear fecha para mostrar
function formatearFecha(fechaStr) {
  var partes = fechaStr.split('-');
  var d = new Date(parseInt(partes[0]), parseInt(partes[1]) - 1, parseInt(partes[2]));
  var dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  var meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  return dias[d.getDay()] + ', ' + d.getDate() + ' de ' + meses[d.getMonth()] + ' de ' + d.getFullYear();
}

// Ir a paso
function irAPaso(n) {
  document.querySelectorAll('.paso').forEach(function(p) { p.classList.remove('activo'); });
  var el = document.getElementById('paso-' + n);
  if (el) el.classList.add('activo');
  // Actualizar circles
  for (var i = 1; i <= 3; i++) {
    var circle = document.getElementById('circle-' + i);
    var label = document.getElementById('label-' + i);
    if (!circle) continue;
    circle.classList.remove('active', 'done');
    label.classList.remove('active');
    if (i < n) { circle.classList.add('done'); }
    else if (i === n) { circle.classList.add('active'); label.classList.add('active'); }
  }
}

function volverPaso(n) {
  irAPaso(n);
}

function mostrarError(id, msg) {
  var el = document.getElementById(id);
  el.textContent = msg;
  el.classList.add('visible');
}
function ocultarError(id) {
  var el = document.getElementById(id);
  el.textContent = '';
  el.classList.remove('visible');
}

// Paso 1: Ver horarios
function verHorarios() {
  ocultarError('error-fecha');
  var fecha = document.getElementById('input-fecha').value;
  if (!fecha) {
    mostrarError('error-fecha', 'Por favor selecciona una fecha.');
    return;
  }
  var hoy = new Date();
  hoy.setHours(0,0,0,0);
  var selDate = new Date(fecha + 'T00:00:00');
  if (selDate < hoy) {
    mostrarError('error-fecha', 'La fecha no puede ser anterior a hoy.');
    return;
  }
  estado.fechaSeleccionada = fecha;
  irAPaso(2);
  cargarSlots(fecha);
}

function cargarSlots(fecha) {
  ocultarError('error-slots');
  document.getElementById('loading-slots').classList.add('visible');
  document.getElementById('slots-grid').innerHTML = '';
  document.getElementById('slots-fecha-label').textContent = 'Horarios para el ' + formatearFecha(fecha);

  var formData = new FormData();
  formData.append('action', 'get_slots');
  formData.append('fecha', fecha);

  fetch('reservar.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      document.getElementById('loading-slots').classList.remove('visible');
      if (data.error) {
        mostrarError('error-slots', data.error);
        return;
      }
      renderSlots(data.slots);
    })
    .catch(function() {
      document.getElementById('loading-slots').classList.remove('visible');
      mostrarError('error-slots', 'Error al cargar los horarios. Intenta de nuevo.');
    });
}

function renderSlots(slots) {
  var grid = document.getElementById('slots-grid');
  grid.innerHTML = '';
  slots.forEach(function(slot) {
    var div = document.createElement('div');
    div.className = 'slot-btn' + (slot.disponibles === 0 ? ' slot-lleno' : '');
    div.innerHTML =
      '<span class="slot-hora">' + slot.hora + '</span>' +
      '<span class="slot-cupos ' + (slot.disponibles > 0 ? 'disponible' : 'lleno') + '">' +
        (slot.disponibles > 0 ? slot.disponibles + ' lugar' + (slot.disponibles > 1 ? 'es' : '') : 'Sin cupos') +
      '</span>';
    if (slot.disponibles > 0) {
      div.onclick = function() { seleccionarSlot(div, slot); };
    }
    grid.appendChild(div);
  });
}

function seleccionarSlot(el, slot) {
  document.querySelectorAll('.slot-btn').forEach(function(b) { b.classList.remove('slot-seleccionado'); });
  el.classList.add('slot-seleccionado');
  // Calcular hora fin
  var partes = slot.hora.split(':');
  var totalMin = parseInt(partes[0]) * 60 + parseInt(partes[1]) + 45;
  var hFin = Math.floor(totalMin / 60);
  var mFin = totalMin % 60;
  var horaFin = (hFin < 10 ? '0' : '') + hFin + ':' + (mFin < 10 ? '0' : '') + mFin;

  estado.horaSeleccionada = slot.hora;
  estado.horaFinSeleccionada = horaFin;

  // Actualizar resumen y pasar al paso 3
  document.getElementById('resumen-fecha').textContent = formatearFecha(estado.fechaSeleccionada);
  document.getElementById('resumen-hora').textContent = slot.hora;
  document.getElementById('resumen-hora-fin').textContent = horaFin;

  setTimeout(function() { irAPaso(3); }, 220);
}

// Paso 3: Confirmar reserva
function confirmarReserva() {
  ocultarError('error-formulario');
  var nombre = document.getElementById('campo-nombre').value.trim();
  var email  = document.getElementById('campo-email').value.trim();
  var tel    = document.getElementById('campo-telefono').value.trim();
  var tipo   = document.getElementById('campo-tipo').value;

  if (!nombre) { mostrarError('error-formulario', 'El nombre es requerido.'); return; }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    mostrarError('error-formulario', 'Ingresa un email válido.'); return;
  }
  if (!tipo) { mostrarError('error-formulario', 'Selecciona un tipo de clase.'); return; }

  var btn = document.getElementById('btn-confirmar');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  var formData = new FormData();
  formData.append('action', 'reservar');
  formData.append('nombre', nombre);
  formData.append('email', email);
  formData.append('telefono', tel);
  formData.append('fecha', estado.fechaSeleccionada);
  formData.append('hora', estado.horaSeleccionada);
  formData.append('tipo_clase', tipo);

  fetch('reservar.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.textContent = 'Confirmar reserva';
      if (data.success) {
        mostrarConfirmacion(data.reserva);
      } else {
        mostrarError('error-formulario', data.error || 'Ocurrió un error. Intenta de nuevo.');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.textContent = 'Confirmar reserva';
      mostrarError('error-formulario', 'Error de conexión. Intenta de nuevo.');
    });
}

function mostrarConfirmacion(reserva) {
  // Formatear precio
  var precioFmt = '$' + parseInt(reserva.precio).toLocaleString('es-CL');
  var lista = document.getElementById('detalle-lista');
  lista.innerHTML =
    '<dt>Nombre</dt><dd>' + reserva.nombre + '</dd>' +
    '<dt>Email</dt><dd>' + reserva.email + '</dd>' +
    (reserva.telefono ? '<dt>Teléfono</dt><dd>' + reserva.telefono + '</dd>' : '') +
    '<dt>Fecha</dt><dd>' + formatearFecha(reserva.fecha) + '</dd>' +
    '<dt>Horario</dt><dd>' + reserva.hora + ' – ' + reserva.hora_fin + '</dd>' +
    '<dt>Tipo de clase</dt><dd>' + reserva.tipo_clase + '</dd>' +
    '<dt>Precio</dt><dd>' + precioFmt + '</dd>' +
    '<dt>N° de reserva</dt><dd style="font-size:0.75rem; color:#888;">' + reserva.id + '</dd>';

  document.querySelectorAll('.paso').forEach(function(p) { p.classList.remove('activo'); });
  document.getElementById('paso-confirmacion').classList.add('activo');
  // Ocultar steps nav
  document.querySelector('.steps-nav').style.display = 'none';
}

function nuevaReserva() {
  estado = { fechaSeleccionada: '', horaSeleccionada: '', horaFinSeleccionada: '' };
  document.getElementById('input-fecha').value = '';
  document.getElementById('campo-nombre').value = '';
  document.getElementById('campo-email').value = '';
  document.getElementById('campo-telefono').value = '';
  document.getElementById('campo-tipo').value = '';
  document.getElementById('slots-grid').innerHTML = '';
  document.querySelector('.steps-nav').style.display = '';
  irAPaso(1);
}

// Copyright year
document.addEventListener('DOMContentLoaded', function() {
  var els = document.querySelectorAll('.copyright-year');
  els.forEach(function(el) { el.textContent = new Date().getFullYear(); });
});
</script>
</body>
</html>
