<?php
/**
 * booking_action.php
 * Handles the Cancel, status-change and Delete buttons (POST only).
 *   cancel : the owner (or staff) cancels a booking
 *   status : Admin / Super admin move a booking along the workflow
 *   delete : remove a finished booking
 */
require_once __DIR__ . '/includes/booking_lib.php';

$user = require_login();
$home = dashboard_url($user['role']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($home);
}
csrf_verify();

$action  = (string) ($_POST['action'] ?? '');
$id      = (string) ($_POST['id'] ?? '');
$booking = $id !== '' ? booking_find($id) : null;

if ($booking === null) {
    flash_set('error', 'That booking could not be found.');
    redirect($home);
}

// Users only touch their own bookings. Staff can touch any.
$isOwner = $booking['user_id'] !== null && (int) $booking['user_id'] === (int) $user['id'];
if (!$isOwner && !is_staff()) {
    forbidden();
}

if ($action === 'cancel') {
    $reason = transition_guard($booking, 'CANCELLED');
    if ($reason !== '') {
        flash_set('error', 'Booking ' . $id . ': ' . $reason);
    } else {
        booking_set_status($id, 'CANCELLED');
        flash_set('success', 'Booking ' . $id . ' was cancelled.');
    }

} elseif ($action === 'status') {
    if (!is_staff()) {
        forbidden();
    }
    $to = (string) ($_POST['to'] ?? '');
    if (!isset(BOOKING_STATUSES[$to])) {
        flash_set('error', 'Unknown status.');
    } else {
        $reason = transition_guard($booking, $to);
        if ($reason !== '') {
            flash_set('error', 'Booking ' . $id . ': ' . $reason);
        } else {
            booking_set_status($id, $to);
            flash_set('success', 'Booking ' . $id . ' is now ' . strtolower(status_label($to)) . '.');
        }
    }

} elseif ($action === 'delete') {
    $allowed = is_staff() ? staff_can_delete($booking) : user_can_delete($booking);
    if (!$allowed) {
        flash_set('error', is_staff()
            ? 'Booking ' . $id . ' is still active. Only completed, cancelled or no-show bookings can be deleted.'
            : 'Cancel booking ' . $id . ' first. Only cancelled bookings can be deleted.');
    } else {
        booking_delete($id);
        flash_set('success', 'Booking ' . $id . ' was deleted.');
    }

} else {
    flash_set('error', 'Unknown action.');
}

redirect($home);