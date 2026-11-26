<?php
require_once __DIR__ . '/includes/bookings_panel.php';
require_once __DIR__ . '/includes/navbar.php';

$user = require_role(ROLE_ADMIN);

page_start('Admin Dashboard');
?>
<main>
<?php render_bookings_dashboard([
    'mode'   => 'staff',
    'user'   => $user,
    'action' => 'admin_dashboard.php',
    'title'  => 'Admin Dashboard',
    'intro'  => 'Welcome back, ' . $user['name'] . '. Manage every booking, confirm tickets and check customers in.',
]); ?>
</main>
<?php page_end(); ?>