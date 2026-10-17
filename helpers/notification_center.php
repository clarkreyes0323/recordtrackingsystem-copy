<?php
$notification_user_id = (int)($_SESSION['user_id'] ?? 0);
$notifications = getNotifications($con, $notification_user_id);
$unread_notifications = countUnreadNotifications($con, $notification_user_id);
?>
<details class="notification-center">
    <summary aria-label="Notifications" title="Notifications">
        <svg class="notification-bell" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <?php if ($unread_notifications > 0): ?>
            <span class="notification-count"><?= $unread_notifications ?></span>
        <?php endif; ?>
    </summary>
    <div class="notification-panel">
        <div class="notification-panel-header">
            <strong>Notifications</strong>
            <?php if ($unread_notifications > 0): ?>
                <form method="POST" action="notification_action.php">
                    <input type="hidden" name="action" value="read_all">
                    <button type="submit">Mark all read</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (!$notifications): ?>
            <p class="notification-empty">No notifications yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($notifications as $notification): ?>
                    <li class="<?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?>">
                        <p><?= htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8') ?></p>
                        <time datetime="<?= htmlspecialchars($notification['created_at'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(date('M j, Y g:i a', strtotime($notification['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                        </time>
                        <?php if ((int)$notification['is_read'] === 0): ?>
                            <form method="POST" action="notification_action.php">
                                <input type="hidden" name="action" value="read_one">
                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                <button type="submit">Mark read</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</details>
