<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$items = db()->query('SELECT l.*, u.name
                      FROM activity_logs l
                      LEFT JOIN users u ON u.id = l.user_id
                      ORDER BY l.created_at DESC
                      LIMIT 500')->fetchAll();

$pageTitle = 'Activity Logs';
include __DIR__ . '/../includes/header.php';
?>

<section class="px-4 py-8 sm:px-6 lg:px-8">
    <div>
        <p class="text-sm font-black uppercase tracking-[0.22em] text-red-600">Admin</p>
        <h1 class="mt-1 text-3xl font-black text-neutral-950">Activity Logs</h1>
    </div>

    <div class="stock-card mt-6 overflow-x-auto rounded-[1.5rem] p-5">
        <table class="datatable w-full text-sm">
            <thead>
                <tr>
                    <th>เวลา</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record</th>
                    <th>IP</th>
                    <th>User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $log): ?>
                    <tr>
                        <td><?= h($log['created_at']) ?></td>
                        <td><?= h($log['name'] ?: '-') ?></td>
                        <td class="font-black"><?= h($log['action']) ?></td>
                        <td><?= h($log['table_name']) ?></td>
                        <td><?= h($log['record_id']) ?></td>
                        <td><?= h($log['ip_address']) ?></td>
                        <td><?= h($log['user_agent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
