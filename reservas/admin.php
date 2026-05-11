<?php
require_once __DIR__ . '/config.php';
session_start();

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

// ── Eliminar reserva ───────────────────────────────────────────
if (isset($_SESSION['admin']) && isset($_GET['delete'])) {
    $db = getDB();
    $db->prepare("DELETE FROM reservas WHERE id = ?")->execute([$_GET['delete']]);
    $qs = http_build_query(array_filter(['vista' => $_GET['vista'] ?? null, 'fecha' => $_GET['fecha'] ?? null]));
    header('Location: admin.php' . ($qs ? '?' . $qs : ''));
    exit;
}

// ── Parámetros de vista ────────────────────────────────────────
$filter_date = $_GET['fecha'] ?? date('Y-m-d');
$view_mode   = $_GET['vista'] ?? 'dia';

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

    // Reservas según vista
    if ($view_mode === 'semana') {
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
        $week_end   = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));
        $stmt = $db->prepare("SELECT * FROM reservas WHERE fecha BETWEEN ? AND ? ORDER BY fecha, hora");
        $stmt->execute([$week_start, $week_end]);
    } elseif ($view_mode === 'todas') {
        $stmt = $db->query("SELECT * FROM reservas ORDER BY fecha DESC, hora ASC");
    } else { // día
        $stmt = $db->prepare("SELECT * FROM reservas WHERE fecha = ? ORDER BY hora");
        $stmt->execute([$filter_date]);
    }
    $filtered = $stmt->fetchAll();

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
@media(max-width:768px){.sidebar{display:none}.main_content{margin-left:0;padding:16px}.stats_grid{grid-template-columns:repeat(2,1fr)}.week_grid{grid-template-columns:repeat(4,1fr)}}
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
<div class="sidebar">
    <div class="brand"><h3>🛺 TukTuk Norris</h3><p>Panel Admin</p></div>
    <nav>
        <a href="admin.php" class="<?= $view_mode !== 'todas' ? 'active' : '' ?>">
            <i class="fa fa-calendar"></i> Horario
        </a>
        <a href="admin.php?vista=todas" class="<?= $view_mode === 'todas' ? 'active' : '' ?>">
            <i class="fa fa-list"></i> Todas las reservas
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
            <a href="admin.php?vista=dia&fecha=<?= $filter_date ?>" class="<?= $view_mode === 'dia' ? 'active' : '' ?>"><i class="fa fa-calendar-o"></i> Día</a>
            <a href="admin.php?vista=semana&fecha=<?= $filter_date ?>" class="<?= $view_mode === 'semana' ? 'active' : '' ?>"><i class="fa fa-th"></i> Semana</a>
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
        <input type="date" value="<?= $filter_date ?>"
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
                    <a href="admin.php?delete=<?= $r['id'] ?><?= $qs ? '&'.$qs : '' ?>"
                       onclick="return confirm('¿Eliminar esta reserva definitivamente?')"
                       class="action_btn btn_delete" style="text-decoration:none" title="Eliminar">
                        <i class="fa fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
</body>
</html>
