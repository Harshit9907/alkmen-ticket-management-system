<?php

declare(strict_types=1);

function currentDashboardRoute(string $role): string
{
    return match ($role) {
        'super_admin', 'admin' => '/atms/super_admin/dashboard.php',
        'client_admin' => '/atms/client_admin/dashboard.php',
        'manager' => '/atms/manager/dashboard.php',
        default => '/atms/employee/dashboard.php',
    };
}

function scopeConditionForRole(string $role): string
{
    return match ($role) {
        'super_admin', 'admin' => '1 = 1',
        'client_admin' => 'owner.company_id = :company_id',
        'manager' => '(assignee.id = :user_id OR assignee.manager_id = :user_id)',
        default => '(t.user_id = :user_id OR t.assigned_to = :user_id)',
    };
}

function scopeParamsForRole(string $role, array $session): array
{
    return match ($role) {
        'client_admin' => ['company_id' => (int) ($session['company_id'] ?? 0)],
        'super_admin', 'admin' => [],
        default => ['user_id' => (int) ($session['user_id'] ?? 0)],
    };
}

function runScopedCount(PDO $pdo, string $role, array $session, string $statusCondition = ''): int
{
    $where = scopeConditionForRole($role);
    if ($statusCondition !== '') {
        $where .= ' AND ' . $statusCondition;
    }

    $sql = "SELECT COUNT(*)
            FROM tickets t
            JOIN users owner ON owner.id = t.user_id
            LEFT JOIN users assignee ON assignee.id = t.assigned_to
            WHERE {$where}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(scopeParamsForRole($role, $session));

    return (int) $stmt->fetchColumn();
}
