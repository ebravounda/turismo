<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$tours_file = __DIR__ . '/tours_data.json';

function load_tours(string $file): array {
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $tours = load_tours($tours_file);
    $activos = array_values(array_filter($tours, fn($t) => !empty($t['activo'])));
    echo json_encode($activos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method === 'POST') {
    if (!isset($_SESSION['admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token inválido']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $raw = $_POST['tours_json'] ?? '';
        $tours = json_decode($raw, true);
        if (!is_array($tours)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'JSON inválido']);
            exit;
        }
        // Sanitize
        $clean = [];
        foreach ($tours as $t) {
            $clean[] = [
                'id'      => (int)($t['id'] ?? 0),
                'emoji'   => htmlspecialchars(strip_tags(trim($t['emoji']   ?? '')), ENT_QUOTES, 'UTF-8'),
                'nombre'  => htmlspecialchars(strip_tags(trim($t['nombre']  ?? '')), ENT_QUOTES, 'UTF-8'),
                'desc'    => htmlspecialchars(strip_tags(trim($t['desc']    ?? '')), ENT_QUOTES, 'UTF-8'),
                'duracion'=> htmlspecialchars(strip_tags(trim($t['duracion']?? '')), ENT_QUOTES, 'UTF-8'),
                'precio'  => htmlspecialchars(strip_tags(trim($t['precio']  ?? '')), ENT_QUOTES, 'UTF-8'),
                'activo'  => !empty($t['activo']),
            ];
        }
        file_put_contents($tours_file, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['id'] ?? 0);
        $tours  = load_tours($tours_file);
        $tours  = array_values(array_filter($tours, fn($t) => (int)$t['id'] !== $del_id));
        file_put_contents($tours_file, json_encode($tours, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
