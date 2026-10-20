<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Bad CSRF token. Reload the page.']));
}

$repo = new AttendanceRepository();
$action = $_GET['action'] ?? $in['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
            $data = $repo->dailyShifts($date, trim($_GET['q'] ?? ''));
            break;
        case 'punches':
            $data = $repo->punches((string)$_GET['empCode'], (string)$_GET['date']);
            break;
        case 'saveShift':
            $repo->updateShift($in['id'], $in['checkIn'] ?? null, $in['checkOut'] ?? null, $in['status'] ?? null);
            $data = true; break;
        case 'recalc':
            $repo->recalcShift($in['id']);
            $data = true; break;
        case 'savePunch':
            $repo->updatePunch($in['id'], $in['time']);
            $data = true; break;
        case 'addPunch':
            $repo->addPunch($in['empCode'], $in['time']);
            $data = true; break;
        case 'deletePunch':
            $repo->deletePunch($in['id']);
            $data = true; break;
        default:
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'Unknown action']));
    }
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
