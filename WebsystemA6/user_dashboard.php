<?php
require_once __DIR__ . '/includes/bookings_panel.php';
require_once __DIR__ . '/includes/navbar.php';

$user = require_role(ROLE_USER);

page_start('My Tickets');
?>
<main>
<?php render_bookings_dashboard([
    'mode'   => 'user',
    'user'   => $user,
    'action' => 'user_dashboard.php',
    'title'  => 'My Tickets',
    'intro'  => 'Welcome back, ' . $user['name'] . '. Book seats, change a pending booking or cancel one.',
]); ?>
</main>
<?php page_end(); ?>