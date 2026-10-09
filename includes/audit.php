<?php

/**
 * Record an admin or system action. Never throws: auditing must not break the request.
 */
function hj_audit(string $action, ?string $entityType = null, ?int $entityId = null, string $details = ''): void
{
    static $tableExists = null;
    try {
        if ($tableExists === null) {
            $tableExists = hj_table_exists('audit_log');
        }
        if (!$tableExists) {
            return;
        }
        $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
        hj_db_exec(
            'INSERT INTO audit_log (admin_id, action, entity_type, entity_id, details, ip_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$adminId, $action, $entityType, $entityId, hj_substr($details, 0, 1000), hj_ip_hash(), hj_now()]
        );
    } catch (Throwable $e) {
        error_log('[hubjob] Audit log failed: ' . $e->getMessage());
    }
}
