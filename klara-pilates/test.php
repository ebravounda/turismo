<?php
echo "PHP funciona OK. Version: " . phpversion();
echo "<br>Directorio: " . __DIR__;
echo "<br>Existe data/: " . (is_dir(__DIR__.'/data') ? 'SI' : 'NO');
echo "<br>Existe data/config.json: " . (file_exists(__DIR__.'/data/config.json') ? 'SI' : 'NO');
echo "<br>Existe data/reservas.json: " . (file_exists(__DIR__.'/data/reservas.json') ? 'SI' : 'NO');
