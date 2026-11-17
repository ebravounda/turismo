<?php
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

// Test HTTP response for an image
function httpCode($url) {
    $h = @get_headers($url, 1);
    return $h ? substr($h[0], 9, 3) : 'ERR';
}

// File permissions
function perms($path) {
    if (!file_exists($path)) return 'NO EXISTE';
    $p = fileperms($path);
    return decoct($p & 0777) . ' owner:' . posix_getpwuid(fileowner($path))['name'];
}

$img1 = $root . '/images/malaga_alcazaba.jpg';
$img2 = $root . '/assets/images/uploads/malaga_alcazaba.jpg';
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Check</title>
<style>body{font-family:monospace;padding:20px;font-size:13px} .ok{color:green;font-weight:bold} .no{color:red;font-weight:bold} pre{background:#f5f5f5;padding:10px;overflow:auto}</style>
</head><body>
<h2>Diagnóstico avanzado</h2>
<p><strong>Document root:</strong> <?= htmlspecialchars($root) ?></p>
<p><strong>PHP user:</strong> <?= get_current_user() ?> / uid=<?= getmyuid() ?></p>

<h3>Permisos de archivos:</h3>
<ul>
  <li>images/ dir: <?= perms($root.'/images') ?></li>
  <li>images/malaga_alcazaba.jpg: <?= perms($img1) ?></li>
  <li>assets/images/uploads/ dir: <?= perms($root.'/assets/images/uploads') ?></li>
  <li>assets/images/uploads/malaga_alcazaba.jpg: <?= perms($img2) ?></li>
</ul>

<h3>Respuesta HTTP del servidor para las imágenes:</h3>
<ul>
  <li><?= $base ?>/images/malaga_alcazaba.jpg → <strong><?= httpCode($base.'/images/malaga_alcazaba.jpg') ?></strong></li>
  <li><?= $base ?>/assets/images/uploads/malaga_alcazaba.jpg → <strong><?= httpCode($base.'/assets/images/uploads/malaga_alcazaba.jpg') ?></strong></li>
</ul>

<h3>.htaccess en raíz (si existe):</h3>
<pre><?= file_exists($root.'/.htaccess') ? htmlspecialchars(file_get_contents($root.'/.htaccess')) : '(no existe)' ?></pre>

<h3>.htaccess en images/ (si existe):</h3>
<pre><?= file_exists($root.'/images/.htaccess') ? htmlspecialchars(file_get_contents($root.'/images/.htaccess')) : '(no existe)' ?></pre>

<h3>.htaccess en assets/images/uploads/ :</h3>
<pre><?= file_exists($root.'/assets/images/uploads/.htaccess') ? htmlspecialchars(file_get_contents($root.'/assets/images/uploads/.htaccess')) : '(no existe)' ?></pre>

<h3>Imagen de prueba directa:</h3>
<img src="<?= $base ?>/images/malaga_alcazaba.jpg" style="max-width:300px;border:3px solid red" alt="Si ves esto, la imagen NO carga">
<img src="<?= $base ?>/assets/images/uploads/malaga_alcazaba.jpg" style="max-width:300px;border:3px solid blue" alt="Si ves esto, la imagen NO carga (uploads)">

</body></html>
