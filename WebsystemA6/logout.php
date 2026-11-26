<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_verify();
logout_user();

session_start();
session_regenerate_id(true);
flash_set('info', 'You have been logged out.');

redirect('login.php');