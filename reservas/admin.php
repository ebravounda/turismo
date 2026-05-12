<?php
require_once __DIR__ . '/config.php';
session_start();

// ── Security headers ───────────────────────────────────────────
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: same-origin');

// ── Session timeout (30 min inactivity) ────────────────────────
if (isset($_SESSION['admin'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_destroy();
        header('Location: admin.php?expired=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ── CSRF token ─────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Logout ─────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Login ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: admin.php');
        exit;
    }
    $login_error = 'Usuario o contraseña incorrectos';
}

// ── Cambiar estado ─────────────────────────────────────────────
if (isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $allowed = ['pendiente', 'confirmada', 'cancelada'];
    $new_status = $_POST['new_status'] ?? '';
    $res_id     = $_POST['res_id']     ?? '';
    if ($res_id && in_array($new_status, $allowed)) {
        $db = getDB();
        $db->prepare("UPDATE reservas SET estado = ? WHERE id = ?")->execute([$new_status, $res_id]);
    }
    $qs = http_build_query(array_filter(['vista' => $_GET['vista'] ?? null, 'fecha' => $_GET['fecha'] ?? null]));
    header('Location: admin.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ── Eliminar reserva (POST + CSRF) ────────────────────────────
if (isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); die('Token inválido');
    }
    $db = getDB();
    $db->prepare("DELETE FROM reservas WHERE id = ?")->execute([$_POST['delete_id']]);
    $qs = http_build_query(array_filter(['vista' => $_GET['vista'] ?? null, 'fecha' => $_GET['fecha'] ?? null]));
    header('Location: admin.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ── Actualizar precio ──────────────────────────────────────────
if (isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_precio'])) {
    $tour_id  = (int)($_POST['tour_id']    ?? 0);
    $precio   = max(0, round((float)str_replace(',', '.', $_POST['precio_base'] ?? '0'), 2));
    $duracion = htmlspecialchars(strip_tags(trim($_POST['duracion'] ?? '')), ENT_QUOTES, 'UTF-8');
    $activo   = isset($_POST['activo']) ? 1 : 0;
    if ($tour_id > 0) {
        $db = getDB();
        $db->prepare("UPDATE precios SET precio_base = ?, duracion = ?, activo = ? WHERE id = ?")
           ->execute([$precio, $duracion, $activo, $tour_id]);
    }
    header('Location: admin.php?vista=precios&ok=1');
    exit;
}

// ── Guardar configuración ──────────────────────────────────────
if (isset($_SESSION['admin']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        die('Token inválido');
    }
    $settings_file = __DIR__ . '/site_settings.json';
    $defaults = ['punto_encuentro' => 'Calle Larios 5, Málaga', 'telefono' => '+34 951 234 567', 'cancelacion' => 'Gratuita hasta 24h antes', 'whatsapp' => '', 'logo_size' => 72];
    $existing = file_exists($settings_file) ? (json_decode(file_get_contents($settings_file), true) ?: []) : [];
    $settings = array_merge($defaults, $existing);
    foreach (['punto_encuentro', 'telefono', 'cancelacion', 'whatsapp'] as $key) {
        if (isset($_POST[$key])) {
            $settings[$key] = htmlspecialchars(strip_tags(trim($_POST[$key])), ENT_QUOTES, 'UTF-8');
        }
    }
    if (isset($_POST['logo_size'])) {
        $settings['logo_size'] = max(30, min(300, (int)$_POST['logo_size']));
    }
    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    header('Location: admin.php?vista=config&ok=1');
    exit;
}

// ── Parámetros de vista ────────────────────────────────────────
$raw_date    = $_GET['fecha'] ?? '';
$filter_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) ? $raw_date : date('Y-m-d');
$filter_date_esc = htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8');
$view_mode   = $_GET['vista'] ?? 'dia';
$precio_ok   = isset($_GET['ok']) && $view_mode === 'precios';
$img_ok      = isset($_GET['ok']) && $view_mode === 'imagenes';
$config_ok   = isset($_GET['ok']) && $view_mode === 'config';

// ── Consultas MySQL ────────────────────────────────────────────
$filtered = [];
$stats    = ['hoy' => 0, 'pendientes' => 0, 'confirmadas' => 0, 'total' => 0];

if (isset($_SESSION['admin'])) {
    $db = getDB();

    // Estadísticas globales
    $stats['total']      = (int)$db->query("SELECT COUNT(*) FROM reservas")->fetchColumn();
    $stats['hoy']        = (int)$db->query("SELECT COUNT(*) FROM reservas WHERE fecha = CURDATE()")->fetchColumn();
    $stats['pendientes'] = (int)$db->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'")->fetchColumn();
    $stats['confirmadas']= (int)$db->query("SELECT COUNT(*) FROM reservas WHERE estado = 'confirmada'")->fetchColumn();

    // Precios
    $all_precios = [];
    if ($view_mode === 'precios' || $view_mode === 'imagenes') {
        $all_precios = $db->query("SELECT * FROM precios ORDER BY id")->fetchAll();
    }

    // Reservas según vista
    if ($view_mode === 'semana') {
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
        $week_end   = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));
        $stmt = $db->prepare("SELECT * FROM reservas WHERE fecha BETWEEN ? AND ? ORDER BY fecha, hora");
        $stmt->execute([$week_start, $week_end]);
        $filtered = $stmt->fetchAll();
    } elseif ($view_mode === 'todas') {
        $filtered = $db->query("SELECT * FROM reservas ORDER BY fecha DESC, hora ASC")->fetchAll();
    } elseif ($view_mode === 'precios') {
        $filtered = [];
    } else { // día
        $stmt = $db->prepare("SELECT * FROM reservas WHERE fecha = ? ORDER BY hora");
        $stmt->execute([$filter_date]);
        $filtered = $stmt->fetchAll();
    }

    // Conteo por día para la semana (puntos calendario)
    $week_start_nav = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
    $week_end_nav   = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));
    $stmt_week = $db->prepare("
        SELECT fecha, estado, COUNT(*) as cnt
        FROM reservas
        WHERE fecha BETWEEN ? AND ?
        GROUP BY fecha, estado
    ");
    $stmt_week->execute([$week_start_nav, $week_end_nav]);
    $week_counts = [];
    foreach ($stmt_week->fetchAll() as $row) {
        $week_counts[$row['fecha']][$row['estado']] = $row['cnt'];
    }
}

// ── Agrupar por fecha ──────────────────────────────────────────
$by_date = [];
foreach ($filtered as $r) {
    $by_date[$r['fecha']][] = $r;
}

$days_of_week = [];
$ws_ts = strtotime('monday this week', strtotime($filter_date));
for ($i = 0; $i < 7; $i++) {
    $days_of_week[] = date('Y-m-d', $ws_ts + $i * 86400);
}

$status_colors = ['pendiente' => '#f0a500', 'confirmada' => '#28a745', 'cancelada' => '#dc3545'];
$status_labels = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Admin — TukTuk Norris</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/font-awesome.min.css">
<style>
*{box-sizing:border-box}
body{background:#f4f6fb;font-family:'Segoe UI',sans-serif;margin:0}
/* LOGIN */
.login_wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#f0a500,#e07b00)}
.login_card{background:#fff;border-radius:16px;padding:48px 40px;width:360px;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.login_card .logo{text-align:center;margin-bottom:28px}
.login_card .logo h2{font-size:22px;font-weight:800;color:#f0a500;margin:0}
.login_card .logo p{color:#888;font-size:14px;margin:4px 0 0}
.login_card input{width:100%;padding:12px 16px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:15px;margin-bottom:14px;outline:none}
.login_card input:focus{border-color:#f0a500}
.login_card button{width:100%;padding:13px;background:#f0a500;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer}
.login_card button:hover{background:#d4940a}
.login_error{background:#f8d7da;color:#721c24;padding:10px 14px;border-radius:6px;font-size:14px;margin-bottom:14px}
/* LAYOUT */
.sidebar{width:230px;background:#1a1a2e;min-height:100vh;position:fixed;top:0;left:0}
.sidebar .brand{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.sidebar .brand h3{color:#f0a500;font-size:18px;font-weight:800;margin:0}
.sidebar .brand p{color:rgba(255,255,255,.4);font-size:12px;margin:2px 0 0}
.sidebar nav{padding:16px 0}
.sidebar nav a{display:flex;align-items:center;gap:10px;padding:11px 20px;color:rgba(255,255,255,.6);text-decoration:none;font-size:14px;transition:all .2s}
.sidebar nav a:hover,.sidebar nav a.active{background:rgba(240,165,0,.15);color:#f0a500}
.sidebar nav a i{width:18px;text-align:center}
.sidebar .logout{position:absolute;bottom:20px;left:0;right:0;padding:0 20px}
.sidebar .logout a{display:block;text-align:center;padding:10px;background:rgba(220,53,69,.2);color:#ff6b7a;border-radius:8px;text-decoration:none;font-size:13px}
.main_content{margin-left:230px;padding:28px}
/* TOPBAR */
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.topbar h1{font-size:22px;font-weight:700;color:#1a1a2e;margin:0}
.topbar .date_info{color:#888;font-size:14px}
/* STATS */
.stats_grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat_card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.stat_card .number{font-size:32px;font-weight:800;color:#1a1a2e;line-height:1}
.stat_card .label{font-size:13px;color:#888;margin-top:6px}
.stat_card .icon{float:right;font-size:28px;opacity:.2;margin-top:-4px}
.stat_card.orange .number{color:#f0a500}
.stat_card.green .number{color:#28a745}
.stat_card.blue .number{color:#007bff}
/* CONTROLS */
.controls{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.controls input[type=date]{padding:8px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:14px;outline:none}
.controls input[type=date]:focus{border-color:#f0a500}
.view_btns a{padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;border:1.5px solid #e0e0e0;color:#666;margin-right:4px}
.view_btns a.active{background:#f0a500;border-color:#f0a500;color:#fff}
.controls .nav_arrows a{padding:8px 12px;border-radius:8px;border:1.5px solid #e0e0e0;color:#666;text-decoration:none;font-size:13px}
/* WEEK GRID */
.week_grid{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:20px}
.week_day{background:#fff;border-radius:10px;padding:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);min-height:80px;cursor:pointer;transition:.2s}
.week_day:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.1)}
.week_day .day_header{font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px}
.week_day .day_num{font-size:22px;font-weight:800;color:#1a1a2e;line-height:1.2}
.week_day.today .day_num{color:#f0a500}
.week_day .day_count{font-size:11px;color:#888;margin-top:4px}
.res_dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin:1px}
/* TABLE */
.table_card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden}
.table_card .table_header{padding:16px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;justify-content:space-between}
.table_card .table_header h4{margin:0;font-size:16px;font-weight:700;color:#1a1a2e}
.count_badge{background:#f0f0f0;color:#666;padding:3px 10px;border-radius:20px;font-size:13px}
table{width:100%;border-collapse:collapse}
table th{background:#fafafa;padding:12px 16px;text-align:left;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #f0f0f0}
table td{padding:14px 16px;border-bottom:1px solid #f8f8f8;font-size:14px;color:#333;vertical-align:middle}
table tr:last-child td{border-bottom:none}
table tr:hover td{background:#fafafa}
.badge_status{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}
.badge_pendiente{background:#fff8e6;color:#f0a500}
.badge_confirmada{background:#e8f5e9;color:#28a745}
.badge_cancelada{background:#fde8e8;color:#dc3545}
.action_btn{border:none;padding:6px 11px;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;margin-right:3px;transition:.15s}
.action_btn:hover{opacity:.8}
.btn_confirm{background:#e8f5e9;color:#28a745}
.btn_cancel{background:#fde8e8;color:#dc3545}
.btn_pending{background:#fff8e6;color:#f0a500}
.btn_delete{background:#f0f0f0;color:#888}
.tour_pill{display:inline-block;background:#f0f7ff;color:#007bff;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600}
.empty_state{text-align:center;padding:48px 20px;color:#bbb}
.empty_state i{font-size:48px;display:block;margin-bottom:12px}
.section_date{padding:10px 20px;background:#f9f9f9;border-bottom:1px solid #f0f0f0;font-size:13px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px}
/* ── Mobile top bar ── */
.mobile_topbar{display:none;position:fixed;top:0;left:0;right:0;height:54px;background:#1a1a2e;z-index:1001;align-items:center;justify-content:space-between;padding:0 16px;box-shadow:0 2px 8px rgba(0,0,0,.3)}
.mobile_topbar .m_brand{color:#f0a500;font-weight:800;font-size:16px;letter-spacing:.3px}
.mobile_topbar .m_hamburger{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;padding:6px 4px;line-height:1}
.sidebar_overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:999;cursor:pointer}
.sidebar_overlay.open{display:block}
/* ── Responsive ── */
@media(max-width:900px){
  .mobile_topbar{display:flex}
  .sidebar{transform:translateX(-100%);transition:transform .28s ease;z-index:1000;top:0;min-height:100vh}
  .sidebar.open{transform:translateX(0)}
  .sidebar .logout{position:relative;bottom:auto;margin:20px 20px 24px}
  .main_content{margin-left:0;padding:16px;padding-top:70px}
  .stats_grid{grid-template-columns:repeat(2,1fr);gap:12px}
  .week_grid{grid-template-columns:repeat(4,1fr);gap:8px}
  .table_card{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table{min-width:640px}
  .topbar{flex-direction:column;align-items:flex-start;gap:6px;margin-bottom:20px}
  .topbar h1{font-size:18px}
  .controls{flex-wrap:wrap;gap:8px}
}
@media(max-width:600px){
  .week_grid{grid-template-columns:repeat(2,1fr)}
  .stats_grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .stat_card{padding:14px}
  .stat_card .number{font-size:26px}
  .controls input[type=date]{width:100%}
  .view_btns{display:flex;flex-wrap:wrap;gap:6px}
  .view_btns a{flex:1;text-align:center}
  .main_content{padding:12px;padding-top:66px}
}
@media(max-width:400px){
  .login_card{width:100%;border-radius:12px;padding:36px 20px;margin:16px}
  .stats_grid{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>
<?php if (!isset($_SESSION['admin'])): ?>

<!-- ════ LOGIN ════ -->
<div class="login_wrap">
    <div class="login_card">
        <div class="logo">
            <h2>🛺 TukTuk Norris</h2>
            <p>Panel de Administración</p>
        </div>
        <?php if (isset($login_error)): ?>
            <div class="login_error"><i class="fa fa-exclamation-circle"></i> <?= $login_error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required autofocus>
            <input type="hidden" name="login" value="1">
            <button type="submit">Entrar al panel →</button>
        </form>
    </div>
</div>

<?php else: ?>

<!-- ════ DASHBOARD ════ -->
<!-- Mobile top bar -->
<div class="mobile_topbar">
    <span class="m_brand">🛺 TukTuk Norris</span>
    <button class="m_hamburger" onclick="toggleSidebar()" aria-label="Menú">
        <i class="fa fa-bars"></i>
    </button>
</div>
<div class="sidebar_overlay" id="sidebar_overlay" onclick="closeSidebar()"></div>

<div class="sidebar" id="admin_sidebar">
    <div class="brand"><h3>🛺 TukTuk Norris</h3><p>Panel Admin</p></div>
    <nav>
        <a href="admin.php" class="<?= $view_mode !== 'todas' ? 'active' : '' ?>">
            <i class="fa fa-calendar"></i> Horario
        </a>
        <a href="admin.php?vista=todas" class="<?= $view_mode === 'todas' ? 'active' : '' ?>">
            <i class="fa fa-list"></i> Todas las reservas
        </a>
        <a href="admin.php?vista=precios" class="<?= $view_mode === 'precios' ? 'active' : '' ?>">
            <i class="fa fa-tag"></i> Precios
        </a>
        <a href="admin.php?vista=imagenes" class="<?= $view_mode === 'imagenes' ? 'active' : '' ?>">
            <i class="fa fa-picture-o"></i> Imágenes
        </a>
        <a href="admin.php?vista=config" class="<?= $view_mode === 'config' ? 'active' : '' ?>">
            <i class="fa fa-cog"></i> Configuración
        </a>
        <a href="../index.html" target="_blank"><i class="fa fa-globe"></i> Ver web</a>
        <a href="../reservar.html" target="_blank"><i class="fa fa-plus-circle"></i> Nueva reserva</a>
    </nav>
    <div class="logout"><a href="admin.php?logout=1"><i class="fa fa-sign-out"></i> Cerrar sesión</a></div>
</div>

<div class="main_content">
    <div class="topbar">
        <div>
            <h1>
                <?php if ($view_mode === 'dia'): ?>
                    <?= date('l, j \d\e F \d\e Y', strtotime($filter_date)) ?>
                <?php elseif ($view_mode === 'semana'): ?>
                    Semana del <?= date('j M', strtotime($week_start_nav)) ?> al <?= date('j M Y', strtotime($week_end_nav)) ?>
                <?php else: ?>
                    Todas las reservas
                <?php endif; ?>
            </h1>
            <div class="date_info">Hoy es <?= date('l, j \d\e F \d\e Y') ?></div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats_grid">
        <div class="stat_card"><i class="fa fa-calendar-check-o icon"></i><div class="number"><?= $stats['hoy'] ?></div><div class="label">Reservas hoy</div></div>
        <div class="stat_card orange"><i class="fa fa-clock-o icon"></i><div class="number"><?= $stats['pendientes'] ?></div><div class="label">Pendientes</div></div>
        <div class="stat_card green"><i class="fa fa-check-circle icon"></i><div class="number"><?= $stats['confirmadas'] ?></div><div class="label">Confirmadas</div></div>
        <div class="stat_card blue"><i class="fa fa-users icon"></i><div class="number"><?= $stats['total'] ?></div><div class="label">Total reservas</div></div>
    </div>

    <?php if ($view_mode !== 'todas'): ?>
    <!-- Controls -->
    <div class="controls">
        <div class="view_btns">
            <a href="admin.php?vista=dia&fecha=<?= $filter_date_esc ?>" class="<?= $view_mode === 'dia' ? 'active' : '' ?>"><i class="fa fa-calendar-o"></i> Día</a>
            <a href="admin.php?vista=semana&fecha=<?= $filter_date_esc ?>" class="<?= $view_mode === 'semana' ? 'active' : '' ?>"><i class="fa fa-th"></i> Semana</a>
        </div>
        <div class="nav_arrows">
            <?php
            $prev = ($view_mode === 'dia')
                ? date('Y-m-d', strtotime($filter_date . ' -1 day'))
                : date('Y-m-d', strtotime($week_start_nav . ' -7 days'));
            $next = ($view_mode === 'dia')
                ? date('Y-m-d', strtotime($filter_date . ' +1 day'))
                : date('Y-m-d', strtotime($week_start_nav . ' +7 days'));
            ?>
            <a href="admin.php?vista=<?= $view_mode ?>&fecha=<?= $prev ?>">← Anterior</a>
            <a href="admin.php?vista=<?= $view_mode ?>&fecha=<?= date('Y-m-d') ?>" style="margin:0 4px;background:#f0a500;color:#fff;border-color:#f0a500">Hoy</a>
            <a href="admin.php?vista=<?= $view_mode ?>&fecha=<?= $next ?>">Siguiente →</a>
        </div>
        <input type="date" value="<?= $filter_date_esc ?>"
               onchange="window.location='admin.php?vista=<?= $view_mode ?>&fecha='+this.value">
    </div>

    <?php if ($view_mode === 'semana'): ?>
    <div class="week_grid">
        <?php foreach ($days_of_week as $day): ?>
        <?php
            $day_data  = $week_counts[$day] ?? [];
            $day_total = array_sum($day_data);
            $is_today  = ($day === date('Y-m-d'));
        ?>
        <div class="week_day <?= $is_today ? 'today' : '' ?>"
             onclick="window.location='admin.php?vista=dia&fecha=<?= $day ?>'">
            <div class="day_header"><?= date('D', strtotime($day)) ?></div>
            <div class="day_num"><?= date('j', strtotime($day)) ?></div>
            <div class="day_count"><?= $day_total ?> reserva<?= $day_total !== 1 ? 's' : '' ?></div>
            <div style="margin-top:4px">
                <?php foreach (['confirmada','pendiente','cancelada'] as $st): ?>
                    <?php for ($x = 0; $x < min(5, $day_data[$st] ?? 0); $x++): ?>
                        <span class="res_dot" style="background:<?= $status_colors[$st] ?>"></span>
                    <?php endfor; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($view_mode === 'precios'): ?>
    <!-- ════ GESTIÓN DE PRECIOS ════ -->
    <style>
    .precios_grid{display:grid;gap:16px}
    .precio_card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:24px 28px;display:grid;grid-template-columns:1fr auto;gap:20px;align-items:center}
    .precio_card .tour_info .nombre{font-size:16px;font-weight:700;color:#1a1a2e;margin-bottom:2px}
    .precio_card .tour_info .tour_id{font-size:12px;color:#aaa}
    .precio_card .campos{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .precio_campo{display:flex;flex-direction:column;gap:4px}
    .precio_campo label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#888}
    .precio_campo input[type=text],
    .precio_campo input[type=number]{padding:9px 12px;border:1.5px solid #e0e0e0;border-radius:8px;font-size:15px;font-weight:600;width:110px;outline:none;color:#1a1a2e}
    .precio_campo input[type=text]{width:130px}
    .precio_campo input:focus{border-color:#f0a500;box-shadow:0 0 0 3px rgba(240,165,0,.1)}
    .precio_campo .toggle{display:flex;align-items:center;gap:8px;padding-top:4px;font-size:13px;color:#555}
    .precio_campo .toggle input[type=checkbox]{width:18px;height:18px;accent-color:#f0a500;cursor:pointer}
    .btn_guardar_precio{background:#f0a500;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.15s}
    .btn_guardar_precio:hover{background:#d4940a}
    .alerta_precios_ok{background:#d4edda;border:1px solid #b8dfc6;color:#155724;padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:14px}
    @media(max-width:768px){.precio_card{grid-template-columns:1fr}.precio_card .campos{gap:10px}}
    </style>

    <div class="topbar" style="margin-bottom:20px">
        <div>
            <h1><i class="fa fa-tag"></i> Gestión de precios</h1>
            <div class="date_info">Modifica el precio base por persona de cada tour</div>
        </div>
    </div>

    <?php if ($precio_ok): ?>
    <div class="alerta_precios_ok"><i class="fa fa-check-circle"></i> <strong>¡Precio actualizado!</strong> Los cambios ya son visibles en el formulario de reservas.</div>
    <?php endif; ?>

    <div class="precios_grid">
    <?php
    $iconos = [
        'Tour Centro Histórico' => '🏛️',
        'Tour Playa y Puerto'   => '🏖️',
        'Tour Ruta Picasso'     => '🎨',
        'Tour Atardecer'        => '🌅',
        'Tour Personalizado'    => '✨',
    ];
    foreach ($all_precios as $p):
        $icono = $iconos[$p['tour_nombre']] ?? '🛺';
    ?>
    <div class="precio_card">
        <div class="tour_info">
            <div class="nombre"><?= $icono ?> <?= htmlspecialchars($p['tour_nombre']) ?></div>
            <div class="tour_id">ID #<?= (int)$p['id'] ?></div>
        </div>
        <form method="POST" action="admin.php?vista=precios" style="display:contents">
            <input type="hidden" name="update_precio" value="1">
            <input type="hidden" name="tour_id" value="<?= (int)$p['id'] ?>">
            <div class="campos">
                <div class="precio_campo">
                    <label>Precio base (€/persona)</label>
                    <input type="number" name="precio_base" value="<?= number_format((float)$p['precio_base'], 2, '.', '') ?>"
                           min="0" step="0.50"
                           <?= $p['tour_nombre'] === 'Tour Personalizado' ? 'placeholder="0 = a consultar"' : '' ?>>
                </div>
                <div class="precio_campo">
                    <label>Duración</label>
                    <input type="text" name="duracion" value="<?= htmlspecialchars($p['duracion']) ?>" placeholder="p.ej. 90 min">
                </div>
                <div class="precio_campo">
                    <label>Visible</label>
                    <div class="toggle">
                        <input type="checkbox" name="activo" <?= $p['activo'] ? 'checked' : '' ?>>
                        <span><?= $p['activo'] ? 'Activo' : 'Oculto' ?></span>
                    </div>
                </div>
                <button type="submit" class="btn_guardar_precio"><i class="fa fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
    </div>

    <?php elseif ($view_mode === 'imagenes'): ?>
    <!-- ════ GESTIÓN DE IMÁGENES ════ -->
    <style>
    .img_grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;margin-bottom:32px}
    .img_slot_card{background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06);overflow:hidden;display:flex;flex-direction:column}
    .img_slot_card .slot_preview{width:100%;height:150px;object-fit:cover;background:#f0f0f0;display:block}
    .img_slot_card .slot_preview_placeholder{width:100%;height:150px;background:#f4f6fb;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:36px}
    .img_slot_card .slot_body{padding:14px 16px;flex:1;display:flex;flex-direction:column;gap:10px}
    .img_slot_card .slot_label{font-size:13px;font-weight:700;color:#1a1a2e;margin:0}
    .img_slot_card .slot_id{font-size:11px;color:#aaa;font-family:monospace}
    .img_slot_card .slot_actions{display:flex;gap:8px;flex-wrap:wrap}
    .img_slot_card .slot_actions label{display:inline-block;padding:7px 14px;background:#f0a500;color:#fff;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap}
    .img_slot_card .slot_actions label:hover{background:#d4940a}
    .img_slot_card .slot_actions input[type=file]{display:none}
    .btn_reset_slot{padding:7px 12px;background:#fde8e8;color:#dc3545;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap}
    .btn_reset_slot:hover{background:#f5c0c0}
    .img_section_title{font-size:15px;font-weight:800;color:#1a1a2e;margin:0 0 14px;padding-bottom:8px;border-bottom:2px solid #f0a500;display:flex;align-items:center;gap:8px}
    .alerta_img_ok{background:#d4edda;border:1px solid #b8dfc6;color:#155724;padding:12px 18px;border-radius:8px;margin-bottom:20px;font-size:14px}
    </style>

    <div class="topbar" style="margin-bottom:20px">
        <div>
            <h1><i class="fa fa-picture-o"></i> Gestión de imágenes</h1>
            <div class="date_info">Sube imágenes personalizadas para cada sección de la web</div>
        </div>
    </div>

    <?php if ($img_ok): ?>
    <div class="alerta_img_ok"><i class="fa fa-check-circle"></i> <strong>Imagen actualizada.</strong></div>
    <?php endif; ?>

    <?php
    $img_cfg_file = __DIR__ . '/images_config.json';
    $img_cfg = file_exists($img_cfg_file) ? (json_decode(file_get_contents($img_cfg_file), true) ?: []) : [];

    $img_slots = [
        'Logo' => [
            'site_logo' => 'Logo del sitio (header)',
        ],
        'Fondos de sección' => [
            'hero_bg'      => 'Banner principal (hero)',
            'cta_bg'       => 'Fondo: Call to Action',
            'packages_bg'  => 'Fondo: Sección Paquetes',
            'booking_bg'   => 'Fondo: Sección Reservas',
            'pagetitle_bg' => 'Fondo: Título de página',
        ],
        'Destinos' => [
            'dest_1' => 'Casco Antiguo',
            'dest_2' => 'La Malagueta',
            'dest_3' => 'El Soho',
            'dest_4' => 'Pedregalejo',
            'dest_5' => 'Teatinos',
            'dest_6' => 'Destino slide 3-2',
            'dest_7' => 'Destino slide 4-1',
            'dest_8' => 'Destino slide 4-2',
        ],
        'Tours' => [
            'service_1' => 'Tour: Centro Histórico',
            'service_2' => 'Tour: Ruta Picasso',
            'service_3' => 'Tour: Playa y Puerto',
            'service_4' => 'Tour: Atardecer',
        ],
        'Paquetes' => [
            'package_1' => 'Paquete: Centro Histórico',
            'package_2' => 'Paquete: Malagueta y Puerto',
            'package_3' => 'Paquete: Ruta Picasso',
            'package_4' => 'Paquete: Tour Atardecer',
        ],
        'Galería' => [
            'gallery_1' => 'Galería 1',
            'gallery_2' => 'Galería 2',
            'gallery_3' => 'Galería 3',
            'gallery_4' => 'Galería 4',
            'gallery_5' => 'Galería 5',
            'gallery_6' => 'Galería 6',
            'gallery_7' => 'Galería 7',
        ],
        'Guías' => [
            'guide_1' => 'Guía: Sebastian Mateo',
            'guide_2' => 'Guía: Theodore Aiden',
            'guide_3' => 'Guía: Lincoln Anthony',
        ],
    ];
    foreach ($img_slots as $cat => $slots):
    ?>
    <div class="img_section_title"><i class="fa fa-folder-open-o"></i> <?= htmlspecialchars($cat) ?></div>
    <div class="img_grid">
    <?php foreach ($slots as $slot_id => $slot_label):
        $has_img = isset($img_cfg[$slot_id]);
        $img_url = $has_img ? '../' . $img_cfg[$slot_id] : '';
    ?>
    <div class="img_slot_card">
        <?php if ($has_img): ?>
            <img class="slot_preview" src="<?= htmlspecialchars($img_url) ?>" alt="" id="prev_<?= $slot_id ?>">
        <?php else: ?>
            <div class="slot_preview_placeholder" id="prev_<?= $slot_id ?>"><i class="fa fa-image"></i></div>
        <?php endif; ?>
        <div class="slot_body">
            <div>
                <div class="slot_label"><?= htmlspecialchars($slot_label) ?></div>
                <div class="slot_id"><?= htmlspecialchars($slot_id) ?></div>
            </div>
            <div class="slot_actions" id="actions_<?= $slot_id ?>">
                <input type="file" id="file_<?= $slot_id ?>" accept="image/jpeg,image/png,image/webp"
                       onchange="doUpload(this,'<?= $slot_id ?>')" style="display:none">
                <label for="file_<?= $slot_id ?>"><i class="fa fa-upload"></i> Subir</label>
                <?php if ($has_img): ?>
                <button type="button" class="btn_reset_slot" onclick="doReset('<?= $slot_id ?>')"><i class="fa fa-undo"></i> Reset</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <script>
    function setPreview(slot, url) {
        var prev = document.getElementById('prev_' + slot);
        if (!prev) return;
        if (url) {
            var img = document.createElement('img');
            img.className = 'slot_preview';
            img.src = url;
            img.id = 'prev_' + slot;
            prev.parentNode.replaceChild(img, prev);
        } else {
            var ph = document.createElement('div');
            ph.className = 'slot_preview_placeholder';
            ph.id = 'prev_' + slot;
            ph.innerHTML = '<i class="fa fa-image"></i>';
            prev.parentNode.replaceChild(ph, prev);
        }
    }
    function setResetBtn(slot, show) {
        var actions = document.getElementById('actions_' + slot);
        if (!actions) return;
        var btn = actions.querySelector('.btn_reset_slot');
        if (show && !btn) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'btn_reset_slot';
            b.innerHTML = '<i class="fa fa-undo"></i> Reset';
            b.onclick = function(){ doReset(slot); };
            actions.appendChild(b);
        } else if (!show && btn) {
            btn.remove();
        }
    }
    function doUpload(input, slot) {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        var reader = new FileReader();
        reader.onload = function(e){ setPreview(slot, e.target.result); };
        reader.readAsDataURL(file);
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('slot', slot);
        fd.append('file', file);
        fetch('imagenes.php', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    setPreview(slot, '../' + d.url);
                    setResetBtn(slot, true);
                } else {
                    alert(d.error || 'Error al subir');
                }
            }).catch(function(){ alert('Error de conexión'); });
        input.value = '';
    }
    function doReset(slot) {
        if (!confirm('¿Restablecer imagen por defecto?')) return;
        var fd = new FormData();
        fd.append('action', 'reset');
        fd.append('slot', slot);
        fetch('imagenes.php', {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success) {
                    setPreview(slot, null);
                    setResetBtn(slot, false);
                } else {
                    alert(d.error || 'Error al restablecer');
                }
            }).catch(function(){ alert('Error de conexión'); });
    }
    </script>

    <?php elseif ($view_mode === 'config'): ?>
    <!-- Configuración General -->
    <?php
    $settings_file_adm = __DIR__ . '/site_settings.json';
    $defaults_adm = ['punto_encuentro' => 'Calle Larios 5, Málaga', 'telefono' => '+34 951 234 567', 'cancelacion' => 'Gratuita hasta 24h antes', 'whatsapp' => '', 'logo_size' => 72];
    $existing_adm = file_exists($settings_file_adm) ? (json_decode(file_get_contents($settings_file_adm), true) ?: []) : [];
    $cfg_adm = array_merge($defaults_adm, $existing_adm);
    ?>
    <?php if ($config_ok): ?>
        <div style="background:#d4edda;border:1px solid #b8dfc6;color:#155724;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:14px;">
            <i class="fa fa-check-circle"></i> <strong>Configuración guardada correctamente.</strong>
        </div>
    <?php endif; ?>
    <div style="max-width:600px;">
        <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:32px 36px;">
            <h3 style="font-size:18px;font-weight:800;color:#1a1a2e;margin:0 0 6px;">Información para el formulario de reserva</h3>
            <p style="color:#888;font-size:13px;margin:0 0 28px;">Estos datos se muestran al cliente en la página de reserva.</p>
            <form method="POST" action="admin.php?vista=config">
                <input type="hidden" name="update_settings" value="1">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:700;color:#444;margin-bottom:7px;">
                        <i class="fa fa-map-marker" style="color:#f0a500;margin-right:6px;"></i> Punto de encuentro
                    </label>
                    <input type="text" name="punto_encuentro"
                        value="<?= htmlspecialchars($cfg_adm['punto_encuentro'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: Calle Larios 5, Málaga"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e2e5eb;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:700;color:#444;margin-bottom:7px;">
                        <i class="fa fa-phone" style="color:#f0a500;margin-right:6px;"></i> Teléfono de contacto
                    </label>
                    <input type="text" name="telefono"
                        value="<?= htmlspecialchars($cfg_adm['telefono'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: +34 951 234 567"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e2e5eb;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>

                <div style="margin-bottom:28px;">
                    <label style="display:block;font-size:13px;font-weight:700;color:#444;margin-bottom:7px;">
                        <i class="fa fa-times-circle-o" style="color:#f0a500;margin-right:6px;"></i> Política de cancelación
                    </label>
                    <input type="text" name="cancelacion"
                        value="<?= htmlspecialchars($cfg_adm['cancelacion'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: Gratuita hasta 24h antes"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e2e5eb;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                </div>

                <?php $logo_sz = max(30, min(300, (int)($cfg_adm['logo_size'] ?? 72))); ?>
                <div style="margin-bottom:28px;">
                    <label style="display:block;font-size:13px;font-weight:700;color:#444;margin-bottom:10px;">
                        <i class="fa fa-picture-o" style="color:#f0a500;margin-right:6px;"></i> Tamaño del logo
                    </label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" onclick="changeLogoSize(-8)"
                            style="width:38px;height:38px;border:1.5px solid #e2e5eb;border-radius:8px;background:#f9f9f9;font-size:20px;font-weight:700;cursor:pointer;color:#444;line-height:1;display:flex;align-items:center;justify-content:center;">−</button>
                        <span id="logo_size_display"
                            style="min-width:64px;text-align:center;font-size:15px;font-weight:700;color:#1a1a2e;"><?= $logo_sz ?> px</span>
                        <button type="button" onclick="changeLogoSize(+8)"
                            style="width:38px;height:38px;border:1.5px solid #e2e5eb;border-radius:8px;background:#f9f9f9;font-size:20px;font-weight:700;cursor:pointer;color:#444;line-height:1;display:flex;align-items:center;justify-content:center;">+</button>
                        <input type="hidden" name="logo_size" id="logo_size_input" value="<?= $logo_sz ?>">
                    </div>
                    <div style="margin-top:14px;background:#f5f6fa;border-radius:10px;padding:18px;text-align:center;border:1.5px dashed #e2e5eb;">
                        <img src="../assets/images/logo-tuktuk.svg" id="logo_preview"
                            style="height:<?= $logo_sz ?>px;width:auto;max-width:100%;"
                            onerror="this.style.display='none'">
                        <p style="font-size:11px;color:#aaa;margin:8px 0 0;">Vista previa del logo</p>
                    </div>
                </div>
                <script>
                function changeLogoSize(delta){
                    var inp  = document.getElementById('logo_size_input');
                    var disp = document.getElementById('logo_size_display');
                    var prev = document.getElementById('logo_preview');
                    var val  = Math.max(30, Math.min(300, parseInt(inp.value) + delta));
                    inp.value       = val;
                    disp.textContent = val + ' px';
                    if(prev) prev.style.height = val + 'px';
                }
                </script>

                <div style="margin-bottom:28px;">
                    <label style="display:block;font-size:13px;font-weight:700;color:#444;margin-bottom:7px;">
                        <i class="fa fa-whatsapp" style="color:#25d366;margin-right:6px;"></i> Número de WhatsApp (botón flotante)
                    </label>
                    <input type="text" name="whatsapp"
                        value="<?= htmlspecialchars($cfg_adm['whatsapp'], ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="Ej: 34612345678 (sin + ni espacios)"
                        style="width:100%;padding:11px 14px;border:1.5px solid #e2e5eb;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">
                    <span style="font-size:11px;color:#aaa;margin-top:4px;display:block;">Incluye el código de país sin el +. Ej: 34612345678</span>
                </div>

                <button type="submit" style="background:#f0a500;color:#fff;border:none;border-radius:8px;padding:13px 28px;font-size:15px;font-weight:800;cursor:pointer;transition:background .2s;">
                    <i class="fa fa-save"></i> Guardar cambios
                </button>
            </form>
        </div>
    </div>

    <?php elseif ($view_mode !== 'precios' && $view_mode !== 'imagenes'): ?>
    <!-- Tabla -->
    <div class="table_card">
        <div class="table_header">
            <h4>
                <?php if ($view_mode === 'dia'): ?><i class="fa fa-calendar-o"></i> Reservas del día
                <?php elseif ($view_mode === 'semana'): ?><i class="fa fa-th"></i> Reservas de la semana
                <?php else: ?><i class="fa fa-list"></i> Todas las reservas
                <?php endif; ?>
            </h4>
            <span class="count_badge"><?= count($filtered) ?> reserva<?= count($filtered) !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($filtered)): ?>
            <div class="empty_state"><i class="fa fa-calendar-times-o"></i><p>No hay reservas para este período</p></div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha / Hora</th>
                    <th>Cliente</th>
                    <th>Tour</th>
                    <th style="text-align:center">Personas</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $section_date = null;
            foreach ($filtered as $r):
                if ($view_mode !== 'dia' && $r['fecha'] !== $section_date):
                    $section_date = $r['fecha'];
            ?>
            <tr>
                <td colspan="8" class="section_date">
                    📅 <?= date('l, j \d\e F \d\e Y', strtotime($r['fecha'])) ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><code style="font-size:11px;color:#aaa"><?= htmlspecialchars($r['id']) ?></code></td>
                <td>
                    <strong><?= date('d/m/Y', strtotime($r['fecha'])) ?></strong><br>
                    <span style="color:#f0a500;font-weight:700;font-size:16px"><?= substr($r['hora'], 0, 5) ?></span>
                </td>
                <td>
                    <strong><?= htmlspecialchars($r['nombre']) ?></strong>
                    <?php if ($r['idioma']): ?><br><span style="color:#888;font-size:12px"><?= htmlspecialchars($r['idioma']) ?></span><?php endif; ?>
                </td>
                <td><span class="tour_pill"><?= htmlspecialchars($r['tipo_tour']) ?></span></td>
                <td style="text-align:center">
                    <strong style="font-size:18px"><?= (int)$r['personas'] ?></strong><br>
                    <span style="color:#aaa;font-size:11px">personas</span>
                </td>
                <td>
                    <a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:#007bff;font-size:13px;display:block">
                        <i class="fa fa-envelope"></i> <?= htmlspecialchars($r['email']) ?>
                    </a>
                    <a href="tel:<?= htmlspecialchars($r['telefono']) ?>" style="color:#28a745;font-size:13px;display:block">
                        <i class="fa fa-phone"></i> <?= htmlspecialchars($r['telefono']) ?>
                    </a>
                    <?php if (!empty($r['mensaje'])): ?>
                        <span style="color:#aaa;font-size:11px" title="<?= htmlspecialchars($r['mensaje']) ?>">
                            💬 <?= mb_strimwidth(htmlspecialchars($r['mensaje']), 0, 35, '...') ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge_status badge_<?= $r['estado'] ?>">
                        <?= $status_labels[$r['estado']] ?>
                    </span>
                    <br><small style="color:#ccc;font-size:10px">
                        <?= date('d/m H:i', strtotime($r['created_at'])) ?>
                    </small>
                </td>
                <td>
                    <?php
                    $qs = http_build_query(array_filter(['vista' => $_GET['vista'] ?? null, 'fecha' => $_GET['fecha'] ?? null]));
                    $back = 'admin.php' . ($qs ? '?' . $qs : '');
                    ?>
                    <form method="POST" action="<?= $back ?>" style="display:inline">
                        <input type="hidden" name="res_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="change_status" value="1">
                        <?php if ($r['estado'] !== 'confirmada'): ?>
                            <button type="submit" name="new_status" value="confirmada" class="action_btn btn_confirm" title="Confirmar"><i class="fa fa-check"></i></button>
                        <?php endif; ?>
                        <?php if ($r['estado'] !== 'pendiente'): ?>
                            <button type="submit" name="new_status" value="pendiente" class="action_btn btn_pending" title="Pendiente"><i class="fa fa-clock-o"></i></button>
                        <?php endif; ?>
                        <?php if ($r['estado'] !== 'cancelada'): ?>
                            <button type="submit" name="new_status" value="cancelada" class="action_btn btn_cancel" title="Cancelar"><i class="fa fa-times"></i></button>
                        <?php endif; ?>
                    </form>
                    <form method="post" action="admin.php<?= $qs ? '?'.$qs : '' ?>" style="display:inline" onsubmit="return confirm('¿Eliminar esta reserva definitivamente?')">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($r['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <button type="submit" class="action_btn btn_delete" title="Eliminar"><i class="fa fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; // end elseif ($view_mode !== 'precios') ?>
</div>

<?php endif; ?>
<script>
function toggleSidebar(){
    document.getElementById('admin_sidebar').classList.toggle('open');
    document.getElementById('sidebar_overlay').classList.toggle('open');
}
function closeSidebar(){
    document.getElementById('admin_sidebar').classList.remove('open');
    document.getElementById('sidebar_overlay').classList.remove('open');
}
// Close sidebar when a nav link is tapped on mobile
document.querySelectorAll('#admin_sidebar nav a').forEach(function(a){
    a.addEventListener('click', function(){ if(window.innerWidth <= 900) closeSidebar(); });
});
</script>
</body>
</html>
