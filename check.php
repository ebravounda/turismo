<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

function listDir($dir, $depth = 0) {
    if ($depth > 3 || !is_dir($dir)) return;
    $items = @scandir($dir);
    if (!$items) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . '/' . $item;
        $pad = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        if (is_dir($full)) {
            echo "<li>$pad<strong>📁 $item/</strong></li>";
            if ($depth < 2) listDir($full, $depth + 1);
        } else {
            $size = round(filesize($full) / 1024) . 'KB';
            echo "<li>$pad📄 $item <small>($size)</small></li>";
        }
    }
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Check</title>
<style>body{font-family:monospace;padding:20px;font-size:13px} li{list-style:none;line-height:1.6} .ok{color:green;font-weight:bold} .no{color:red;font-weight:bold}</style>
</head><body>
<h2>Diagnóstico del servidor</h2>
<p><strong>Document root:</strong> <?= htmlspecialchars($root) ?></p>
<p><strong>URL base:</strong> <?= htmlspecialchars($base) ?></p>
<p><strong>PHP:</strong> <?= phpversion() ?></p>

<h3>Prueba de rutas:</h3>
<table border="1" cellpadding="6">
<tr><th>Ruta</th><th>¿Existe?</th></tr>
<?php
$tests = [
    'images/malaga_alcazaba.jpg',
    'images/tuktuk_playa.webp',
    'assets/images/uploads/malaga_alcazaba.jpg',
    'assets/images/uploads/tuktuk_playa.webp',
    'upload/malaga_alcazaba.jpg',
    'uploads/malaga_alcazaba.jpg',
    'img/malaga_alcazaba.jpg',
    'fotos/malaga_alcazaba.jpg',
];
foreach ($tests as $p) {
    $exists = file_exists($root . '/' . $p);
    echo "<tr><td>$p</td><td class='" . ($exists ? 'ok' : 'no') . "'>" . ($exists ? '✓ SÍ' : '✗ NO') . "</td></tr>";
}
?>
</table>

<h3>Contenido completo de <?= htmlspecialchars($root) ?> :</h3>
<ul>
<?php listDir($root); ?>
</ul>
</body></html>
