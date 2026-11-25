<?php
declare(strict_types=1);

if (!function_exists('edushare_client_ip')) {
    function edushare_client_ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || trim($ip) === '') {
            return null;
        }
        return mb_substr(trim($ip), 0, 45, 'UTF-8');
    }
}

if (!function_exists('edushare_user_agent')) {
    function edushare_user_agent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (!is_string($ua) || trim($ua) === '') {
            return null;
        }
        return mb_substr(trim($ua), 0, 255, 'UTF-8');
    }
}

if (!function_exists('log_audit')) {
    function log_audit(
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $description = null,
        array $metadata = [],
        ?int $actorIdOverride = null,
        ?string $actorRoleOverride = null
    ): void {
        try {
            $sessionUser = (isset($_SESSION['user']) && is_array($_SESSION['user'])) ? $_SESSION['user'] : [];

            $rawActorId = $actorIdOverride ?? ($sessionUser['id_user'] ?? ($sessionUser['id'] ?? null));
            $actorId = ($rawActorId !== null && (int)$rawActorId > 0) ? (int)$rawActorId : null;

            $rawActorRole = $actorRoleOverride ?? ($sessionUser['role'] ?? ($actorId === null ? 'guest' : null));
            $actorRole = ($rawActorRole !== null && $rawActorRole !== '')
                ? mb_substr((string)$rawActorRole, 0, 30, 'UTF-8')
                : null;

            $cleanAction = trim($action) !== '' ? mb_substr(trim($action), 0, 100, 'UTF-8') : 'unknown_action';
            $cleanTargetType = ($targetType !== null && trim($targetType) !== '')
                ? mb_substr(trim($targetType), 0, 50, 'UTF-8')
                : null;
            $cleanTargetId = ($targetId !== null && $targetId > 0) ? $targetId : null;

            $metaJson = null;
            if ($metadata !== []) {
                $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $metaJson = $encoded !== false ? $encoded : null;
            }

            $stmt = db()->prepare(
                'INSERT INTO audit_logs (actor_id, actor_role, action, target_type, target_id, description, metadata, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $actorId,
                $actorRole,
                $cleanAction,
                $cleanTargetType,
                $cleanTargetId,
                $description,
                $metaJson,
                edushare_client_ip(),
                edushare_user_agent(),
            ]);
        } catch (\Throwable $e) {
            error_log('EduShare audit log error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('log_activity')) {
    function log_activity(
        ?int $userId,
        string $activityType,
        string $summary,
        ?string $entityType = null,
        ?int $entityId = null
    ): void {
        try {
            $uid = ($userId !== null && $userId > 0) ? $userId : null;
            $cleanType = trim($activityType) !== '' ? mb_substr(trim($activityType), 0, 60, 'UTF-8') : 'activity';
            $cleanEntityType = ($entityType !== null && trim($entityType) !== '')
                ? mb_substr(trim($entityType), 0, 50, 'UTF-8')
                : null;
            $cleanEntityId = ($entityId !== null && $entityId > 0) ? $entityId : null;
            $cleanSummary = mb_substr($summary, 0, 255, 'UTF-8');

            $stmt = db()->prepare(
                'INSERT INTO activity_logs (id_user, activity_type, entity_type, entity_id, summary, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $uid,
                $cleanType,
                $cleanEntityType,
                $cleanEntityId,
                $cleanSummary,
                edushare_client_ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('EduShare activity log error: ' . $e->getMessage());
        }
    }
}
