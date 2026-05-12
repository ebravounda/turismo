<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config_file = __DIR__ . '/images_config.json';
$upload_dir  = __DIR__ . '/../assets/images/uploads/';

$allowed_slots = [
    'hero_bg',
    'cta_bg', 'packages_bg', 'booking_bg', 'pagetitle_bg',
    'dest_1','dest_2','dest_3','dest_4','dest_5','dest_6','dest_7','dest_8',
    'service_1','service_2','service_3','service_4',
    'package_1','package_2','package_3','package_4',
    'gallery_1','gallery_2','gallery_3','gallery_4','gallery_5','gallery_6','gallery_7',
    'guide_1','guide_2','guide_3',
];

function load_config(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function save_config(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(load_config($config_file));
    exit;
}

if ($method === 'POST') {
    if (!isset($_SESSION['admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $slot   = $_POST['slot']   ?? '';

    if (!in_array($slot, $allowed_slots, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Slot inválido']);
        exit;
    }

    $cfg = load_config($config_file);

    if ($action === 'reset') {
        if (isset($cfg[$slot])) {
            $old = __DIR__ . '/../' . $cfg[$slot];
            if (file_exists($old)) unlink($old);
            unset($cfg[$slot]);
            save_config($config_file, $cfg);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'upload') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $php_err = $_FILES['file']['error'] ?? 'sin archivo';
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Error en la subida (código: ' . $php_err . ')']);
            exit;
        }

        $file = $_FILES['file'];

        if ($file['size'] > 8 * 1024 * 1024) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Archivo demasiado grande (máx 8 MB)']);
            exit;
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mime     = $finfo->file($file['tmp_name']);
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($mime_map[$mime])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Tipo no permitido: ' . $mime]);
            exit;
        }

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (!is_writable($upload_dir)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Directorio uploads sin permisos de escritura']);
            exit;
        }

        $ext      = $mime_map[$mime];
        $filename = $slot . '_' . uniqid() . '.' . $ext;
        $dest     = $upload_dir . $filename;

        if (isset($cfg[$slot])) {
            $old = __DIR__ . '/../' . $cfg[$slot];
            if (file_exists($old)) unlink($old);
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'No se pudo mover el archivo a: ' . $dest]);
            exit;
        }

        $url        = 'assets/images/uploads/' . $filename;
        $cfg[$slot] = $url;
        save_config($config_file, $cfg);

        echo json_encode(['success' => true, 'url' => $url]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
