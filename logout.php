<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) {
    log_activity('logout', 'users', (int)current_user()['id'], 'User logged out');
}
clear_auth_session();
header('Location: /login.php');
exit;

