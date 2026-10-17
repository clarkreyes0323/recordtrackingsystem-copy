<?php

function addNotification(mysqli $con, int $recipientId, string $message, ?int $requestId = null): void
{
    $stmt = $con->prepare(
        "INSERT INTO notifications (recipient_id, request_id, message)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $recipientId, $requestId, $message);
    $stmt->execute();
    $stmt->close();
}

function getNotifications(mysqli $con, int $recipientId, int $limit = 8): array
{
    $limit = max(1, min($limit, 50));
    $stmt = $con->prepare(
        "SELECT id, request_id, message, is_read, created_at
         FROM notifications
         WHERE recipient_id = ?
         ORDER BY created_at DESC, id DESC
         LIMIT ?"
    );
    $stmt->bind_param("ii", $recipientId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notifications;
}

function countUnreadNotifications(mysqli $con, int $recipientId): int
{
    $stmt = $con->prepare(
        "SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE recipient_id = ? AND is_read = 0"
    );
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['unread_count'];
    $stmt->close();
    return $count;
}

function markNotificationRead(mysqli $con, int $notificationId, int $recipientId): void
{
    $stmt = $con->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE id = ? AND recipient_id = ?"
    );
    $stmt->bind_param("ii", $notificationId, $recipientId);
    $stmt->execute();
    $stmt->close();
}

function markAllNotificationsRead(mysqli $con, int $recipientId): void
{
    $stmt = $con->prepare(
        "UPDATE notifications SET is_read = 1
         WHERE recipient_id = ? AND is_read = 0"
    );
    $stmt->bind_param("i", $recipientId);
    $stmt->execute();
    $stmt->close();
}
