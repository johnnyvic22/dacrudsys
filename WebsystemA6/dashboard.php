<?php
// Sends each person to the dashboard for their role.
require_once __DIR__ . '/includes/auth.php';

$user = require_login();
redirect(dashboard_url($user['role']));