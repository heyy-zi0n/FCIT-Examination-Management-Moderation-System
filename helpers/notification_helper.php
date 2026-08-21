<?php
/**
 * In-App Notification Helper
 * Manages targeted alerts for lecturers, moderators, HODs, and exam officers
 */

/**
 * Dispatch an individual in-app notification
 */
function createNotification(int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (`user_id`, `title`, `message`, `link`, `is_read`, `created_at`)
            VALUES (:user_id, :title, :message, :link, 0, NOW())
        ");
        return $stmt->execute([
            'user_id' => $userId,
            'title' => substr($title, 0, 150),
            'message' => $message,
            'link' => $link ? substr($link, 0, 255) : null
        ]);
    } catch (Exception $e) {
        error_log('Notification Dispatch Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get count of unread notifications for user
 */
function getUnreadNotificationsCount(int $userId): int {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Fetch latest notifications for user
 */
function getUserNotifications(int $userId, int $limit = 20): array {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("
            SELECT id, title, message, link, is_read, created_at
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('Error fetching notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Mark a single notification as read
 */
function markNotificationRead(int $notificationId, int $userId): bool {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
        return $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mark all notifications for a user as read
 */
function markAllNotificationsRead(int $userId): bool {
    try {
        $pdo = getDb();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
        return $stmt->execute(['user_id' => $userId]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Dispatch notification to all users matching a specific role in a specific department
 */
function notifyRoleInDepartment(string $role, ?int $departmentId, string $title, string $message, ?string $link = null): int {
    try {
        $pdo = getDb();
        $sql = "SELECT id FROM users WHERE role = :role AND status = 'active'";
        $params = ['role' => $role];
        
        if ($departmentId !== null) {
            $sql .= " AND department_id = :dept_id";
            $params['dept_id'] = $departmentId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $count = 0;
        foreach ($users as $u) {
            if (createNotification((int)$u['id'], $title, $message, $link)) {
                $count++;
            }
        }
        return $count;
    } catch (Exception $e) {
        error_log('NotifyRoleInDepartment Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Dispatch notification to all Exam Officers
 */
function notifyAllExamOfficers(string $title, string $message, ?string $link = null): int {
    return notifyRoleInDepartment(ROLE_EXAM_OFFICER, null, $title, $message, $link);
}
