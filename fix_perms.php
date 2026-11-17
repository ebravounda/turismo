<?php
// Corrige permisos de imágenes — borra este archivo después de ejecutarlo
$root = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$dirs = [
    $root . '/images',
    $root . '/assets/images/uploads',
    $root . '/assets/images',
    $root . '/assets/css',
    $root . '/assets/js',
];

$fixed = 0;
$errors = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iter as $file) {
        $path = $file->getPathname();
        if ($file->isDir()) {
            if (!chmod($path, 0755)) $errors[] = "dir: $path";
        } else {
            if (chmod($path, 0644)) {
                $fixed++;
            } else {
                $errors[] = $path;
            }
        }
    }
    chmod($dir, 0755);
}
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Fix permisos</title>
<style>body{font-family:sans-serif;padding:30px;max-width:700px} .ok{color:green} .err{color:red}</style>
</head><body>
<h2>Corrección de permisos</h2>
<?php if (empty($errors)): ?>
  <p class="ok">✓ Se corrigieron <strong><?= $fixed ?> archivos</strong> a permisos 644.</p>
  <p>Ahora prueba: <a href="https://tuktuknorris.es/" target="_blank">https://tuktuknorris.es/</a></p>
  <p><strong>Borra este archivo del servidor cuando todo funcione.</strong></p>
<?php else: ?>
  <p class="ok">✓ <?= $fixed ?> archivos corregidos.</p>
  <p class="err">✗ Errores en <?= count($errors) ?> archivos:</p>
  <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
<?php endif; ?>

<h3>Verificación:</h3>
<?php
$test = $root . '/images/malaga_alcazaba.jpg';
$p = file_exists($test) ? decoct(fileperms($test) & 0777) : 'no existe';
echo "<p>images/malaga_alcazaba.jpg → permisos: <strong>$p</strong></p>";
$headers = @get_headers('https://tuktuknorris.es/images/malaga_alcazaba.jpg');
$code = $headers ? substr($headers[0], 9, 3) : 'ERR';
echo "<p>HTTP response: <strong>$code</strong> " . ($code === '200' ? '✓ OK — imagen accesible' : '✗ Sigue con error') . "</p>";
echo "<img src='https://tuktuknorris.es/images/malaga_alcazaba.jpg' style='max-width:300px;border:3px solid green' alt='IMAGEN DE PRUEBA'>";
?>
</body></html>
