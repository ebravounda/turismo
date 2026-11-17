<?php
// Página de diagnóstico — borra este archivo después de usarlo
$tests = [
    'images/malaga_alcazaba.jpg',
    'assets/images/uploads/malaga_alcazaba.jpg',
    'images/tuktuk_playa.webp',
    'assets/images/uploads/tuktuk_playa.webp',
];
$root = $_SERVER['DOCUMENT_ROOT'];
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
?><!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Diagnóstico TukTuk</title>
<style>body{font-family:sans-serif;padding:20px} .ok{color:green} .err{color:red} img{max-width:200px;border:2px solid green;margin:5px} .broken{border:2px solid red;width:200px;height:60px;background:#fee;display:flex;align-items:center;justify-content:center;color:red;font-size:12px}</style>
</head>
<body>
<h2>Diagnóstico de imágenes</h2>
<p><strong>Document root:</strong> <?= htmlspecialchars($root) ?></p>
<p><strong>Base URL:</strong> <?= htmlspecialchars($base) ?></p>
<table border="1" cellpadding="8">
<tr><th>Ruta</th><th>¿Existe?</th><th>Vista previa</th></tr>
<?php foreach ($tests as $path):
    $full = rtrim($root, '/') . '/' . $path;
    $exists = file_exists($full);
?>
<tr>
  <td><?= htmlspecialchars($path) ?></td>
  <td class="<?= $exists ? 'ok' : 'err' ?>"><?= $exists ? '✓ SÍ' : '✗ NO' ?></td>
  <td>
    <?php if ($exists): ?>
      <img src="<?= htmlspecialchars($base . '/' . $path) ?>" alt="test">
    <?php else: ?>
      <div class="broken">No encontrada</div>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<h3>Archivos en /images/ (si existe):</h3>
<ul>
<?php
$imgDir = rtrim($root, '/') . '/images/';
if (is_dir($imgDir)) {
    foreach (scandir($imgDir) as $f) {
        if ($f !== '.' && $f !== '..') echo "<li>$f</li>";
    }
} else {
    echo "<li style='color:red'>La carpeta /images/ NO existe</li>";
}
?>
</ul>
<h3>Archivos en /assets/images/uploads/ (si existe):</h3>
<ul>
<?php
$uploadDir = rtrim($root, '/') . '/assets/images/uploads/';
if (is_dir($uploadDir)) {
    foreach (scandir($uploadDir) as $f) {
        if ($f !== '.' && $f !== '..') echo "<li>$f</li>";
    }
} else {
    echo "<li style='color:red'>La carpeta /assets/images/uploads/ NO existe</li>";
}
?>
</ul>
</body></html>
