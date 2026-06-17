<?php
session_start();
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$dataDir    = __DIR__ . '/../data/';
$configFile = $dataDir . 'config.json';
$reservasFile = $dataDir . 'reservas.json';

// Leer datos
function leerConfig($configFile) {
    return json_decode(file_get_contents($configFile), true);
}
function leerReservas($reservasFile) {
    $r = json_decode(file_get_contents($reservasFile), true);
    return is_array($r) ? $r : [];
}
function guardarConfig($configFile, $config) {
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function guardarReservas($reservasFile, $reservas) {
    file_put_contents($reservasFile, json_encode(array_values($reservas), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

$mensaje = '';
$mensajeTipo = 'success';

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $config  = leerConfig($configFile);
    $reservas = leerReservas($reservasFile);

    if ($action === 'update_precios') {
        $config['precios']['clase_individual'] = (int)$_POST['clase_individual'];
        $config['precios']['pack_4_clases']    = (int)$_POST['pack_4_clases'];
        $config['precios']['pack_8_clases']    = (int)$_POST['pack_8_clases'];
        $config['precios']['pack_mensual']     = (int)$_POST['pack_mensual'];
        $config['moneda']                      = htmlspecialchars(trim($_POST['moneda']), ENT_QUOTES);
        $config['cupos_por_clase']             = max(1, (int)$_POST['cupos_por_clase']);
        $config['precios_updated_at']          = date('Y-m-d H:i:s');
        guardarConfig($configFile, $config);
        $mensaje = 'Precios actualizados correctamente.';
    }

    elseif ($action === 'cancel_reserva') {
        $id = $_POST['id'] ?? '';
        foreach ($reservas as &$r) {
            if ($r['id'] === $id) { $r['estado'] = 'cancelada'; break; }
        }
        unset($r);
        guardarReservas($reservasFile, $reservas);
        $mensaje = 'Reserva cancelada.';
    }

    elseif ($action === 'confirm_reserva') {
        $id = $_POST['id'] ?? '';
        foreach ($reservas as &$r) {
            if ($r['id'] === $id) { $r['estado'] = 'confirmada'; break; }
        }
        unset($r);
        guardarReservas($reservasFile, $reservas);
        $mensaje = 'Reserva confirmada.';
    }

    elseif ($action === 'delete_reserva') {
        $id = $_POST['id'] ?? '';
        $reservas = array_filter($reservas, function($r) use ($id) { return $r['id'] !== $id; });
        guardarReservas($reservasFile, $reservas);
        $mensaje = 'Reserva eliminada.';
    }

    elseif ($action === 'add_reserva') {
        $fecha  = preg_replace('/[^0-9\-]/', '', $_POST['fecha'] ?? '');
        $hora   = preg_replace('/[^0-9:]/', '', $_POST['hora'] ?? '');
        $nombre = htmlspecialchars(trim($_POST['nombre'] ?? ''), ENT_QUOTES);
        $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $tel    = htmlspecialchars(trim($_POST['telefono'] ?? ''), ENT_QUOTES);
        $tipo   = htmlspecialchars(trim($_POST['tipo_clase'] ?? ''), ENT_QUOTES);
        $estRaw = $_POST['estado'] ?? 'confirmada';
        $estado = in_array($estRaw, ['confirmada', 'cancelada']) ? $estRaw : 'confirmada';

        if (empty($nombre) || empty($email) || empty($fecha) || empty($hora) || empty($tipo)) {
            $mensaje = 'Todos los campos requeridos deben completarse.';
            $mensajeTipo = 'danger';
        } else {
            // Verificar cupos si el estado es confirmada
            if ($estado === 'confirmada') {
                $ocupados = 0;
                foreach ($reservas as $rv) {
                    if ($rv['fecha'] === $fecha && $rv['hora'] === $hora && $rv['estado'] !== 'cancelada') {
                        $ocupados++;
                    }
                }
                $cupos = (int)$config['cupos_por_clase'];
                if ($ocupados >= $cupos) {
                    $mensaje = 'No hay cupos disponibles para ese horario.';
                    $mensajeTipo = 'danger';
                }
            }
            if (empty($mensaje)) {
                $horaFin = date('H:i', strtotime($hora) + 45 * 60);
                $nuevaReserva = [
                    'id'         => uniqid('kp_', true),
                    'nombre'     => $nombre,
                    'email'      => $email,
                    'telefono'   => $tel,
                    'fecha'      => $fecha,
                    'hora'       => $hora,
                    'hora_fin'   => $horaFin,
                    'tipo_clase' => $tipo,
                    'precio'     => (int)$config['precios']['clase_individual'],
                    'estado'     => $estado,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $reservas[] = $nuevaReserva;
                guardarReservas($reservasFile, $reservas);
                $mensaje = 'Reserva agregada correctamente.';
            }
        }
    }

    elseif ($action === 'update_pass') {
        $nueva  = $_POST['nueva_pass'] ?? '';
        $conf   = $_POST['confirmar_pass'] ?? '';
        $cupos  = max(1, (int)($_POST['cupos'] ?? $config['cupos_por_clase']));
        $config['cupos_por_clase'] = $cupos;
        if (!empty($nueva)) {
            if ($nueva !== $conf) {
                $mensaje = 'Las contraseñas no coinciden.';
                $mensajeTipo = 'danger';
            } else {
                $config['admin_pass'] = $nueva;
                guardarConfig($configFile, $config);
                $mensaje = 'Contraseña y configuración actualizadas.';
            }
        } else {
            guardarConfig($configFile, $config);
            $mensaje = 'Configuración guardada.';
        }
        if ($mensajeTipo !== 'danger') {
            // config ya guardado arriba si hubo cambio de pass
            if (!empty($nueva) && $nueva === $conf) {
                // ya guardado
            } else {
                guardarConfig($configFile, $config);
            }
        }
    }

    // Recargar datos después de modificaciones
    $config   = leerConfig($configFile);
    $reservas = leerReservas($reservasFile);
}

// Cargar datos actuales
$config   = leerConfig($configFile);
$reservas = leerReservas($reservasFile);

// Estadísticas
$hoy = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$finSemana    = date('Y-m-d', strtotime('sunday this week'));

$totalReservas     = count($reservas);
$reservasHoy       = 0;
$reservasSemana    = 0;
$ingresosEstimados = 0;

foreach ($reservas as $r) {
    if ($r['estado'] === 'cancelada') continue;
    if ($r['fecha'] === $hoy) $reservasHoy++;
    if ($r['fecha'] >= $inicioSemana && $r['fecha'] <= $finSemana) $reservasSemana++;
    $ingresosEstimados += (int)($r['precio'] ?? 0);
}

// Ordenar reservas por fecha desc
usort($reservas, function($a, $b) {
    $cmp = strcmp($b['fecha'], $a['fecha']);
    if ($cmp !== 0) return $cmp;
    return strcmp($b['hora'], $a['hora']);
});

function fp($num) {
    return '$' . number_format((int)$num, 0, ',', '.');
}
function fFecha($f) {
    if (!$f) return '';
    $partes = explode('-', $f);
    if (count($partes) !== 3) return $f;
    return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
}
$estadoBadge = function($e) {
    if ($e === 'confirmada') return '<span class="badge badge-success">Confirmada</span>';
    if ($e === 'cancelada')  return '<span class="badge badge-danger">Cancelada</span>';
    return '<span class="badge badge-secondary">' . htmlspecialchars($e) . '</span>';
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administración | Klara Pilates</title>
  <link rel="stylesheet" href="../css/bootstrap.css">
  <link rel="stylesheet" href="../css/fonts.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      background: #f0f0f0;
      font-family: 'Roboto', Arial, sans-serif;
      font-size: 0.9rem;
      color: #333;
      margin: 0;
    }
    /* Header */
    .admin-header {
      background: #1a1a1a;
      color: #fff;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 58px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .admin-header .brand-area {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .admin-header img {
      height: 28px;
      filter: brightness(0) invert(1);
    }
    .admin-header .brand-title {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #888;
      padding-left: 14px;
      border-left: 1px solid #333;
    }
    .admin-header .user-area {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .admin-header .user-name {
      font-size: 0.78rem;
      color: #aaa;
    }
    .btn-logout {
      padding: 7px 18px;
      background: transparent;
      color: #aaa;
      border: 1px solid #444;
      border-radius: 2px;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      text-decoration: none;
      transition: all 0.2s;
      font-family: inherit;
    }
    .btn-logout:hover {
      background: #333;
      color: #fff;
      border-color: #555;
    }
    /* Main */
    .admin-main {
      max-width: 1200px;
      margin: 30px auto;
      padding: 0 20px 60px;
    }
    /* Stats cards */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }
    @media (max-width: 768px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
      .stats-row { grid-template-columns: 1fr; }
    }
    .stat-card {
      background: #fff;
      border-radius: 4px;
      padding: 22px 20px;
      border-left: 3px solid #1a1a1a;
    }
    .stat-card .stat-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #888;
      margin-bottom: 8px;
    }
    .stat-card .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #1a1a1a;
      line-height: 1;
    }
    .stat-card.verde { border-left-color: #2e7d32; }
    .stat-card.verde .stat-value { color: #2e7d32; }
    .stat-card.azul { border-left-color: #1565c0; }
    .stat-card.azul .stat-value { color: #1565c0; }
    .stat-card.granate { border-left-color: #6a1a1a; }
    .stat-card.granate .stat-value { color: #6a1a1a; font-size: 1.5rem; }
    /* Alert */
    .alert-admin {
      padding: 13px 18px;
      border-radius: 3px;
      margin-bottom: 20px;
      font-size: 0.88rem;
    }
    .alert-admin.success { background: #e8f5e9; color: #2e7d32; border-left: 3px solid #2e7d32; }
    .alert-admin.danger  { background: #fdecea; color: #c62828; border-left: 3px solid #c62828; }
    /* Tabs */
    .admin-tabs {
      background: #fff;
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    }
    .tab-nav {
      display: flex;
      border-bottom: 1px solid #e0e0e0;
      background: #fafafa;
    }
    .tab-btn {
      padding: 15px 24px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #888;
      border-bottom: 2px solid transparent;
      transition: all 0.2s;
      font-family: inherit;
      font-weight: 600;
    }
    .tab-btn:hover { color: #333; }
    .tab-btn.active {
      color: #1a1a1a;
      border-bottom-color: #1a1a1a;
      background: #fff;
    }
    .tab-panel {
      display: none;
      padding: 28px;
    }
    .tab-panel.active { display: block; }
    /* Table */
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }
    .admin-table th {
      background: #f5f5f5;
      padding: 11px 12px;
      text-align: left;
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #888;
      border-bottom: 1px solid #e0e0e0;
      white-space: nowrap;
    }
    .admin-table td {
      padding: 11px 12px;
      border-bottom: 1px solid #f0f0f0;
      vertical-align: middle;
    }
    .admin-table tr:hover td { background: #fafafa; }
    .admin-table tr.cancelada td { opacity: 0.6; }
    /* Badges */
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-success { background: #e8f5e9; color: #2e7d32; }
    .badge-danger  { background: #fdecea; color: #c62828; }
    .badge-secondary { background: #f0f0f0; color: #666; }
    /* Acción botones */
    .btn-sm-admin {
      padding: 5px 10px;
      border: none;
      border-radius: 2px;
      font-size: 0.7rem;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-family: inherit;
      font-weight: 600;
      transition: opacity 0.2s;
    }
    .btn-sm-admin:hover { opacity: 0.8; }
    .btn-sm-confirmar { background: #e8f5e9; color: #2e7d32; }
    .btn-sm-cancelar  { background: #fff3e0; color: #e65100; }
    .btn-sm-eliminar  { background: #fdecea; color: #c62828; }
    /* Filtros */
    .filtros-bar {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      align-items: flex-end;
    }
    .filtro-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .filtro-group label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #888;
    }
    .filtro-group input,
    .filtro-group select {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 2px;
      font-size: 0.85rem;
      font-family: inherit;
      outline: none;
    }
    .btn-filtrar {
      padding: 8px 20px;
      background: #1a1a1a;
      color: #fff;
      border: none;
      border-radius: 2px;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      font-family: inherit;
      font-weight: 600;
    }
    .btn-limpiar {
      padding: 8px 16px;
      background: transparent;
      color: #888;
      border: 1px solid #ddd;
      border-radius: 2px;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      cursor: pointer;
      font-family: inherit;
    }
    /* Formularios admin */
    .admin-form .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    @media (max-width: 600px) { .admin-form .form-row { grid-template-columns: 1fr; } }
    .admin-form .fg {
      margin-bottom: 18px;
    }
    .admin-form label {
      display: block;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: #666;
      margin-bottom: 6px;
    }
    .admin-form input,
    .admin-form select {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid #ddd;
      border-radius: 2px;
      font-size: 0.9rem;
      font-family: inherit;
      outline: none;
      transition: border 0.2s;
    }
    .admin-form input:focus,
    .admin-form select:focus { border-color: #1a1a1a; }
    .btn-admin-save {
      padding: 12px 30px;
      background: #1a1a1a;
      color: #fff;
      border: none;
      border-radius: 2px;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      cursor: pointer;
      font-family: inherit;
      font-weight: 700;
      transition: background 0.2s;
    }
    .btn-admin-save:hover { background: #333; }
    .section-title {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #888;
      margin-bottom: 18px;
      padding-bottom: 10px;
      border-bottom: 1px solid #eee;
    }
    .table-scroll {
      overflow-x: auto;
    }
    .no-data {
      text-align: center;
      padding: 40px;
      color: #aaa;
      font-size: 0.85rem;
    }
    .updated-at {
      font-size: 0.78rem;
      color: #aaa;
      margin-top: 12px;
    }
    .pass-divider {
      margin: 30px 0;
      border: none;
      border-top: 1px solid #eee;
    }
  </style>
</head>
<body>
<!-- Header -->
<div class="admin-header">
  <div class="brand-area">
    <img src="../images/logo-default-145x38.png" alt="Klara Pilates">
    <span class="brand-title">Administración</span>
  </div>
  <div class="user-area">
    <span class="user-name">Hola, <?= htmlspecialchars($_SESSION['admin_user'] ?? 'admin') ?></span>
    <a href="logout.php" class="btn-logout">Cerrar sesión</a>
  </div>
</div>

<!-- Main -->
<div class="admin-main">

  <?php if ($mensaje): ?>
  <div class="alert-admin <?= $mensajeTipo ?>">
    <?= htmlspecialchars($mensaje) ?>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total de reservas</div>
      <div class="stat-value"><?= $totalReservas ?></div>
    </div>
    <div class="stat-card verde">
      <div class="stat-label">Reservas hoy</div>
      <div class="stat-value"><?= $reservasHoy ?></div>
    </div>
    <div class="stat-card azul">
      <div class="stat-label">Esta semana</div>
      <div class="stat-value"><?= $reservasSemana ?></div>
    </div>
    <div class="stat-card granate">
      <div class="stat-label">Ingresos estimados</div>
      <div class="stat-value"><?= fp($ingresosEstimados) ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="admin-tabs">
    <div class="tab-nav">
      <button class="tab-btn active" onclick="mostrarTab('reservas', this)">Reservas</button>
      <button class="tab-btn" onclick="mostrarTab('nueva', this)">Nueva Reserva</button>
      <button class="tab-btn" onclick="mostrarTab('precios', this)">Precios</button>
      <button class="tab-btn" onclick="mostrarTab('config', this)">Configuración</button>
    </div>

    <!-- TAB 1: RESERVAS -->
    <div class="tab-panel active" id="tab-reservas">
      <div class="section-title">Listado de Reservas</div>

      <!-- Filtros -->
      <div class="filtros-bar">
        <div class="filtro-group">
          <label>Fecha</label>
          <input type="date" id="filtro-fecha" value="">
        </div>
        <div class="filtro-group">
          <label>Estado</label>
          <select id="filtro-estado">
            <option value="">Todas</option>
            <option value="confirmada">Confirmadas</option>
            <option value="cancelada">Canceladas</option>
          </select>
        </div>
        <button class="btn-filtrar" onclick="aplicarFiltro()">Filtrar</button>
        <button class="btn-limpiar" onclick="limpiarFiltro()">Limpiar</button>
      </div>

      <div class="table-scroll">
        <table class="admin-table" id="tabla-reservas">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Alumna</th>
              <th>Email</th>
              <th>Teléfono</th>
              <th>Tipo de clase</th>
              <th>Precio</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tbody-reservas">
            <?php if (empty($reservas)): ?>
            <tr><td colspan="9" class="no-data">No hay reservas aún.</td></tr>
            <?php else: ?>
            <?php foreach ($reservas as $r): ?>
            <tr class="fila-reserva <?= $r['estado'] ?>"
                data-fecha="<?= htmlspecialchars($r['fecha']) ?>"
                data-estado="<?= htmlspecialchars($r['estado']) ?>">
              <td><?= fFecha($r['fecha']) ?></td>
              <td><?= htmlspecialchars($r['hora']) ?>–<?= htmlspecialchars($r['hora_fin'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['telefono'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['tipo_clase'] ?? '') ?></td>
              <td><?= fp($r['precio'] ?? 0) ?></td>
              <td><?= $estadoBadge($r['estado']) ?></td>
              <td style="white-space:nowrap;">
                <?php if ($r['estado'] !== 'confirmada'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="confirm_reserva">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                  <button type="submit" class="btn-sm-admin btn-sm-confirmar">Confirmar</button>
                </form>
                <?php endif; ?>
                <?php if ($r['estado'] !== 'cancelada'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="cancel_reserva">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                  <button type="submit" class="btn-sm-admin btn-sm-cancelar">Cancelar</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar esta reserva definitivamente?')">
                  <input type="hidden" name="action" value="delete_reserva">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($r['id']) ?>">
                  <button type="submit" class="btn-sm-admin btn-sm-eliminar">Eliminar</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: NUEVA RESERVA -->
    <div class="tab-panel" id="tab-nueva">
      <div class="section-title">Agregar Reserva Manual</div>
      <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="add_reserva">
        <div class="form-row">
          <div class="fg">
            <label>Nombre completo *</label>
            <input type="text" name="nombre" required placeholder="Nombre y apellido">
          </div>
          <div class="fg">
            <label>Email *</label>
            <input type="email" name="email" required placeholder="email@ejemplo.com">
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Teléfono</label>
            <input type="tel" name="telefono" placeholder="11 1234-5678">
          </div>
          <div class="fg">
            <label>Fecha *</label>
            <input type="date" name="fecha" required>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Horario *</label>
            <select name="hora" required>
              <option value="">Seleccioná un horario</option>
              <?php foreach ($config['horarios'] as $h): ?>
              <option value="<?= $h ?>"><?= $h ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label>Tipo de clase *</label>
            <select name="tipo_clase" required>
              <option value="">Seleccioná un tipo</option>
              <?php foreach ($config['tipos_clase'] as $t): ?>
              <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Estado</label>
            <select name="estado">
              <option value="confirmada">Confirmada</option>
              <option value="cancelada">Cancelada</option>
            </select>
          </div>
          <div class="fg">
            <label>Precio</label>
            <input type="text" value="<?= fp($config['precios']['clase_individual']) ?>" disabled style="background:#f5f5f5; color:#888;">
          </div>
        </div>
        <button type="submit" class="btn-admin-save">Guardar Reserva</button>
      </form>
    </div>

    <!-- TAB 3: PRECIOS -->
    <div class="tab-panel" id="tab-precios">
      <div class="section-title">Gestión de Precios</div>
      <form method="POST" class="admin-form">
        <input type="hidden" name="action" value="update_precios">
        <div class="form-row">
          <div class="fg">
            <label>Clase Individual (ARS)</label>
            <input type="number" name="clase_individual" value="<?= (int)$config['precios']['clase_individual'] ?>" min="0" step="100" required>
          </div>
          <div class="fg">
            <label>Pack 4 Clases (ARS)</label>
            <input type="number" name="pack_4_clases" value="<?= (int)$config['precios']['pack_4_clases'] ?>" min="0" step="100" required>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Pack 8 Clases (ARS)</label>
            <input type="number" name="pack_8_clases" value="<?= (int)$config['precios']['pack_8_clases'] ?>" min="0" step="100" required>
          </div>
          <div class="fg">
            <label>Pack Mensual (ARS)</label>
            <input type="number" name="pack_mensual" value="<?= (int)$config['precios']['pack_mensual'] ?>" min="0" step="100" required>
          </div>
        </div>
        <div class="form-row">
          <div class="fg">
            <label>Símbolo de moneda</label>
            <input type="text" name="moneda" value="<?= htmlspecialchars($config['moneda'] ?? '$') ?>" maxlength="5">
          </div>
          <div class="fg">
            <label>Cupos por clase</label>
            <input type="number" name="cupos_por_clase" value="<?= (int)$config['cupos_por_clase'] ?>" min="1" max="20" required>
          </div>
        </div>
        <button type="submit" class="btn-admin-save">Guardar Precios</button>
        <?php if (!empty($config['precios_updated_at'])): ?>
        <p class="updated-at">Última actualización: <?= htmlspecialchars($config['precios_updated_at']) ?></p>
        <?php endif; ?>
      </form>
    </div>

    <!-- TAB 4: CONFIGURACIÓN -->
    <div class="tab-panel" id="tab-config">
      <div class="section-title">Configuración del Sistema</div>
      <form method="POST" class="admin-form" onsubmit="return validarPass()">
        <input type="hidden" name="action" value="update_pass">
        <div style="max-width:480px;">
          <div class="fg">
            <label>Nueva contraseña (dejar vacío para no cambiar)</label>
            <input type="password" name="nueva_pass" id="nueva-pass" placeholder="Nueva contraseña" autocomplete="new-password">
          </div>
          <div class="fg">
            <label>Confirmar nueva contraseña</label>
            <input type="password" name="confirmar_pass" id="confirmar-pass" placeholder="Repetí la contraseña" autocomplete="new-password">
          </div>
          <hr class="pass-divider">
          <div class="fg">
            <label>Cupos por clase (máximo de alumnos)</label>
            <input type="number" name="cupos" value="<?= (int)$config['cupos_por_clase'] ?>" min="1" max="20">
          </div>
          <button type="submit" class="btn-admin-save">Guardar Configuración</button>
        </div>
      </form>
      <hr class="pass-divider">
      <p style="font-size:0.78rem; color:#aaa;">
        Usuario admin actual: <strong><?= htmlspecialchars($config['admin_user']) ?></strong><br>
        Versión del sistema: 1.0 &mdash; Klara Pilates Reservation System
      </p>
    </div>

  </div><!-- /admin-tabs -->
</div><!-- /admin-main -->

<script>
function mostrarTab(nombre, btn) {
  document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
  document.querySelectorAll('.tab-btn').forEach(function(b)  { b.classList.remove('active'); });
  document.getElementById('tab-' + nombre).classList.add('active');
  btn.classList.add('active');
}

function aplicarFiltro() {
  var fecha  = document.getElementById('filtro-fecha').value;
  var estado = document.getElementById('filtro-estado').value;
  var filas  = document.querySelectorAll('.fila-reserva');
  var hayResultados = false;
  filas.forEach(function(fila) {
    var matchFecha  = !fecha  || fila.dataset.fecha  === fecha;
    var matchEstado = !estado || fila.dataset.estado === estado;
    var visible = matchFecha && matchEstado;
    fila.style.display = visible ? '' : 'none';
    if (visible) hayResultados = true;
  });
  // Mostrar mensaje si no hay resultados
  var sinResultados = document.getElementById('sin-resultados');
  if (!sinResultados) {
    sinResultados = document.createElement('tr');
    sinResultados.id = 'sin-resultados';
    sinResultados.innerHTML = '<td colspan="9" class="no-data">No se encontraron reservas con los filtros aplicados.</td>';
    document.getElementById('tbody-reservas').appendChild(sinResultados);
  }
  sinResultados.style.display = hayResultados ? 'none' : '';
}

function limpiarFiltro() {
  document.getElementById('filtro-fecha').value = '';
  document.getElementById('filtro-estado').value = '';
  document.querySelectorAll('.fila-reserva').forEach(function(f) { f.style.display = ''; });
  var sinR = document.getElementById('sin-resultados');
  if (sinR) sinR.style.display = 'none';
}

function validarPass() {
  var nueva = document.getElementById('nueva-pass').value;
  var conf  = document.getElementById('confirmar-pass').value;
  if (nueva && nueva !== conf) {
    alert('Las contraseñas no coinciden.');
    return false;
  }
  return true;
}
</script>
</body>
</html>
