<?php
/**
 * Audit Logging Helper
 * Creates immutable, tamper-evident audit records for all security and workflow events
 */

/**
 * Record an event in the system audit log
 */
function logAudit(?int $userId, string $action, string $entityType, ?int $entityId = null, mixed $details = null): bool {
    try {
        $pdo = getDb();

        // Sanitize details to ensure sensitive data is not leaked into audit logs
        $cleanDetails = null;
        if ($details !== null) {
            if (is_array($details)) {
                // Filter out blacklisted fields
                $sensitiveKeys = ['password', 'password_hash', 'csrf_token', 'encryption_key', 'encryption_iv', 'encryption_tag', 'raw_content'];
                $filtered = array_filter($details, function($key) use ($sensitiveKeys) {
                    return !in_array(strtolower($key), $sensitiveKeys);
                }, ARRAY_FILTER_USE_KEY);
                $cleanDetails = json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $cleanDetails = (string)$details;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (`user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `user_agent`, `created_at`)
            VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent, NOW())
        ");

        $stmt->execute([
            'user_id' => $userId,
            'action' => substr($action, 0, 100),
            'entity_type' => substr($entityType, 0, 50),
            'entity_id' => $entityId,
            'details' => $cleanDetails,
            'ip_address' => getClientIp(),
            'user_agent' => getUserAgent()
        ]);

        return true;
    } catch (Exception $e) {
        error_log('Audit Logging Error: ' . $e->getMessage());
        return false;
    }
}
