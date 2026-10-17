<?php
session_start();

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['student', 'admin'], true)
) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_once __DIR__ . '/connection/config.php';
require_once __DIR__ . '/helpers/NotificationService.php';

$con = connection();
$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'read_all') {
    markAllNotificationsRead($con, $userId);
} elseif ($action === 'read_one') {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if (!$notificationId || $notificationId < 1) {
        http_response_code(400);
        exit('Invalid notification.');
    }
    markNotificationRead($con, $notificationId, $userId);
} else {
    http_response_code(400);
    exit('Invalid notification action.');
}

$con->close();
$dashboard = $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'student_dashboard.php';
header("Location: $dashboard");
exit();
