<?php

require_once __DIR__ . '/auth.php';

date_default_timezone_set('Asia/Manila');

const MAX_TICKETS         = 10;
const BOOKING_WINDOW_DAYS = 90;
const SHOWTIMES           = ['8:00 AM', '10:00 AM', '1:00 PM', '4:00 PM', '7:00 PM', '10:00 PM'];
const SEAT_ROWS           = ['A', 'B', 'C', 'D'];
const SEATS_PER_ROW       = 5;

const TICKET_TYPES = [
    'REGULAR' => ['label' => 'Regular',        'price' => 350, 'id_label' => ''],
    'STUDENT' => ['label' => 'Student',        'price' => 280, 'id_label' => 'Student ID number'],
    'SENIOR'  => ['label' => 'Senior Citizen', 'price' => 280, 'id_label' => 'Senior Citizen ID number'],
    'PWD'     => ['label' => 'PWD',            'price' => 280, 'id_label' => 'PWD ID number'],
];

const BOOKING_STATUSES = [
    'PENDING'    => 'Pending',
    'CONFIRMED'  => 'Confirmed',
    'CHECKED_IN' => 'Checked in',
    'COMPLETED'  => 'Completed',
    'CANCELLED'  => 'Cancelled',
    'NO_SHOW'    => 'No show',
];

function all_seats(): array
{
    $seats = [];
    foreach (SEAT_ROWS as $row) {
        for ($n = 1; $n <= SEATS_PER_ROW; $n++) {
            $seats[] = $row . $n;
        }
    }
    return $seats;
}

function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function status_label(string $status): string
{
    return BOOKING_STATUSES[$status] ?? $status;
}

function status_class(string $status): string
{
    return strtolower($status);
}

function booking_total(array $booking): float
{
    return (float) $booking['price_per_ticket'] * (int) $booking['ticket_count'];
}

function booking_seats(string $seats): array
{
    return array_values(array_filter(array_map('trim', explode(',', $seats)), 'strlen'));
}

function movies_all(): array
{
    $movies = [];
    $result = db()->query('SELECT id, title, poster, release_date FROM movies ORDER BY title');
    while ($row = $result->fetch_assoc()) {
        $movies[$row['id']] = $row;
    }
    return $movies;
}

function movie_find(string $id): ?array
{
    return movies_all()[$id] ?? null;
}

function screen_for_movie(string $movieId): string
{
    return 'Screen ' . ((crc32($movieId) % 4) + 1);
}

function booking_find(string $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function bookings_search(array $filters, ?int $userId, int $page, int $perPage): array
{
    $where  = ['1 = 1'];
    $types  = '';
    $params = [];

    if ($userId !== null) {
        $where[]  = 'b.user_id = ?';
        $types   .= 'i';
        $params[] = $userId;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like     = '%' . addcslashes($q, '%_\\') . '%';
        $where[]  = '(b.id LIKE ? OR b.movie_title LIKE ? OR b.seats LIKE ? OR b.customer_name LIKE ? OR b.email LIKE ? OR b.mobile LIKE ?)';
        $types   .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    $status = (string) ($filters['status'] ?? '');
    if (isset(BOOKING_STATUSES[$status])) {
        $where[]  = 'b.status = ?';
        $types   .= 's';
        $params[] = $status;
    }

    $movie = (string) ($filters['movie'] ?? '');
    if ($movie !== '') {
        $where[]  = 'b.movie_id = ?';
        $types   .= 's';
        $params[] = $movie;
    }

    $date = (string) ($filters['date'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $where[]  = 'b.show_date = ?';
        $types   .= 's';
        $params[] = $date;
    }

    $whereSql = implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) AS n FROM bookings b WHERE $whereSql");
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $offset = max(0, ($page - 1) * $perPage);
    $limit  = max(1, $perPage);

    $stmt = db()->prepare(
        "SELECT b.*, m.poster
           FROM bookings b
           LEFT JOIN movies m ON m.id = b.movie_id
          WHERE $whereSql
          ORDER BY b.created_at DESC, CAST(SUBSTRING(b.id, 4) AS UNSIGNED) DESC
          LIMIT $limit OFFSET $offset"
    );
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return ['rows' => $rows, 'total' => $total];
}

function user_booking_stats(int $userId): array
{
    $today = date('Y-m-d');
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(status IN ('PENDING','CONFIRMED') AND show_date >= ?), 0) AS upcoming,
            COALESCE(SUM(CASE WHEN status <> 'CANCELLED' THEN ticket_count ELSE 0 END), 0) AS tickets,
            COALESCE(SUM(CASE WHEN status <> 'CANCELLED' THEN ticket_count * price_per_ticket ELSE 0 END), 0) AS total,
            COALESCE(SUM(status = 'PENDING'), 0) AS pending
           FROM bookings WHERE user_id = ?"
    );
    $stmt->bind_param('si', $today, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'upcoming' => (int) $row['upcoming'],
        'tickets'  => (int) $row['tickets'],
        'total'    => (float) $row['total'],
        'pending'  => (int) $row['pending'],
    ];
}


function booking_input(array $post): array
{
    $seats = array_filter(array_map('trim', explode(',', (string) ($post['seats'] ?? ''))), 'strlen');
    $seats = array_values(array_unique(array_map('strtoupper', $seats)));

    return [
        'name'        => normalize_spaces((string) ($post['name'] ?? '')),
        'email'       => strtolower(trim((string) ($post['email'] ?? ''))),
        'mobile'      => trim((string) ($post['mobile'] ?? '')),
        'date'        => trim((string) ($post['date'] ?? '')),
        'showtime'    => trim((string) ($post['showtime'] ?? '')),
        'tickets'     => trim((string) ($post['tickets'] ?? '')),
        'ticket_type' => strtoupper(trim((string) ($post['ticketType'] ?? 'REGULAR'))),
        'concession'  => trim((string) ($post['concession'] ?? '')),
        'notes'       => trim((string) ($post['notes'] ?? '')),
        'seats'       => $seats,
        'terms'       => !empty($post['terms']),
    ];
}

function validate_booking(array $d, array $movie, ?string $ignoreId, bool $requireTerms, array $extraShowtimes = []): array
{
    $errors = [];
    $today  = new DateTimeImmutable('today');

    $nameLen = mb_strlen($d['name']);
    if ($nameLen < 2 || $nameLen > 60) {
        $errors[] = 'Name: use 2 to 60 characters.';
    } elseif (!preg_match("/^\p{L}[\p{L}\s.'’-]*$/u", $d['name'])) {
        $errors[] = 'Name: use letters only. Numbers and symbols are not allowed.';
    }

    if ($d['email'] === '' || strlen($d['email']) > 80
        || !preg_match('/^[^\s@]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$/', $d['email'])) {
        $errors[] = 'Email: enter a full address such as name@example.com.';
    }

    if (!preg_match('/^(09|\+639)\d{9}$/', $d['mobile'])) {
        $errors[] = 'Mobile number: use 09XXXXXXXXX or +639XXXXXXXXX.';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $d['date']);
    $dateOk = $date !== false && $date->format('Y-m-d') === $d['date'];
    if (!$dateOk) {
        $errors[] = 'Date: choose a real calendar date.';
    } elseif ($date < $today) {
        $errors[] = 'Date: that date has already passed.';
    } elseif ($date > $today->modify('+' . BOOKING_WINDOW_DAYS . ' days')) {
        $errors[] = 'Date: bookings open up to ' . BOOKING_WINDOW_DAYS . ' days ahead.';
    } elseif (!empty($movie['release_date']) && $date < new DateTimeImmutable($movie['release_date'])) {
        $errors[] = 'Date: ' . $movie['title'] . ' opens on ' . date('j F Y', strtotime($movie['release_date'])) . '. Choose that date or later.';
    }

    $showtimeOk = in_array($d['showtime'], array_merge(SHOWTIMES, $extraShowtimes), true);
    if (!$showtimeOk) {
        $errors[] = 'Showtime: choose one from the list.';
    } elseif ($dateOk && $date == $today) {
        $time = DateTimeImmutable::createFromFormat('g:i A', $d['showtime']);
        if ($time !== false && $time->format('H:i') <= date('H:i')) {
            $errors[] = 'Showtime: that showtime has already started. Choose a later showtime or another date.';
        }
    }

    $tickets   = filter_var($d['tickets'], FILTER_VALIDATE_INT);
    $ticketsOk = $tickets !== false && $tickets >= 1 && $tickets <= MAX_TICKETS;
    if (!$ticketsOk) {
        $errors[] = 'Tickets: enter a whole number from 1 to ' . MAX_TICKETS . '.';
    }

    if (!isset(TICKET_TYPES[$d['ticket_type']])) {
        $errors[] = 'Ticket type: choose Regular, Student, Senior Citizen or PWD.';
    } elseif ($d['ticket_type'] !== 'REGULAR') {
        if ($d['concession'] === '') {
            $errors[] = 'ID number: enter your ' . TICKET_TYPES[$d['ticket_type']]['id_label'] . ' to get the discounted price.';
        } elseif (!preg_match('/^[A-Za-z0-9-]{5,20}$/', $d['concession'])) {
            $errors[] = 'ID number: use 5 to 20 letters, numbers or hyphens.';
        }
    }

    if (mb_strlen($d['notes']) > 200) {
        $errors[] = 'Special requests: up to 200 characters.';
    } elseif (preg_match('/[<>]/', $d['notes'])) {
        $errors[] = 'Special requests: remove the < and > characters.';
    }

    if ($requireTerms && !$d['terms']) {
        $errors[] = 'Terms: tick the box to agree to the booking terms.';
    }

    $validSeats = all_seats();
    $badSeats   = array_diff($d['seats'], $validSeats);
    if ($badSeats) {
        $errors[] = 'Seats: ' . implode(', ', $badSeats) . ' do not exist.';
    } elseif ($ticketsOk && count($d['seats']) !== $tickets) {
        $errors[] = count($d['seats']) === 0
            ? "Seats: select $tickets seat(s) to match your tickets."
            : 'Seats: you booked ' . $tickets . ' ticket(s) but picked ' . count($d['seats']) . ' seat(s). Pick exactly ' . $tickets . '.';
    }

    if ($dateOk && $showtimeOk) {
        $sql = "SELECT id, seats, email FROM bookings
                 WHERE movie_id = ? AND show_date = ? AND showtime = ? AND status <> 'CANCELLED'";
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
        }
        $stmt = db()->prepare($sql);
        if ($ignoreId !== null) {
            $stmt->bind_param('ssss', $movie['id'], $d['date'], $d['showtime'], $ignoreId);
        } else {
            $stmt->bind_param('sss', $movie['id'], $d['date'], $d['showtime']);
        }
        $stmt->execute();
        $sameShow = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $taken = [];
        foreach ($sameShow as $other) {
            foreach (booking_seats($other['seats']) as $seat) {
                if (in_array($seat, $d['seats'], true)) {
                    $taken[$seat] = true;
                }
            }
            if ($d['email'] !== '' && strtolower((string) $other['email']) === $d['email']) {
                $errors[] = 'Email: this email already has a booking for this showing (' . $other['id'] . ').';
            }
        }
        if ($taken) {
            $list = implode(', ', array_keys($taken));
            $errors[] = 'Seats: ' . $list . (count($taken) === 1 ? ' is' : ' are') . ' already booked for this showing. Choose different seats.';
        }
    }

    return array_values(array_unique($errors));
}

function next_booking_id(): string
{
    $row = db()->query("SELECT COALESCE(MAX(CAST(SUBSTRING(id, 4) AS UNSIGNED)), 1000) AS n FROM bookings")->fetch_assoc();
    return 'BK-' . ((int) $row['n'] + 1);
}

function booking_create(?int $userId, array $movie, array $d): string
{
    $price       = (float) TICKET_TYPES[$d['ticket_type']]['price'];
    $seats       = implode(',', $d['seats']);
    $count       = count($d['seats']);
    $screen      = screen_for_movie($movie['id']);
    $concession  = $d['ticket_type'] === 'REGULAR' ? null : $d['concession'];
    $notes       = $d['notes'] !== '' ? $d['notes'] : null;

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $id = next_booking_id();
        $stmt = db()->prepare(
            "INSERT INTO bookings
               (id, user_id, movie_id, movie_title, show_date, showtime, screen, seats, ticket_count,
                customer_name, price_per_ticket, status, email, mobile, ticket_type, concession_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sissssssisdsssss',
            $id, $userId, $movie['id'], $movie['title'], $d['date'], $d['showtime'], $screen, $seats, $count,
            $d['name'], $price, $d['email'], $d['mobile'], $d['ticket_type'], $concession, $notes
        );
        try {
            $stmt->execute();
            $stmt->close();
            return $id;
        } catch (mysqli_sql_exception $error) {
            $stmt->close();
            if ((int) $error->getCode() !== 1062) {
                throw $error;
            }
        }
    }
    throw new RuntimeException('Could not create a booking id. Please try again.');
}

function booking_update(string $id, array $d): void
{
    $price      = (float) TICKET_TYPES[$d['ticket_type']]['price'];
    $seats      = implode(',', $d['seats']);
    $count      = count($d['seats']);
    $concession = $d['ticket_type'] === 'REGULAR' ? null : $d['concession'];
    $notes      = $d['notes'] !== '' ? $d['notes'] : null;

    $stmt = db()->prepare(
        'UPDATE bookings
            SET show_date = ?, showtime = ?, seats = ?, ticket_count = ?, customer_name = ?,
                price_per_ticket = ?, email = ?, mobile = ?, ticket_type = ?, concession_id = ?, notes = ?
          WHERE id = ?'
    );
    $stmt->bind_param(
        'sssisdssssss',
        $d['date'], $d['showtime'], $seats, $count, $d['name'],
        $price, $d['email'], $d['mobile'], $d['ticket_type'], $concession, $notes, $id
    );
    $stmt->execute();
    $stmt->close();
}

function booking_set_status(string $id, string $status): void
{
    $stmt = db()->prepare('UPDATE bookings SET status = ? WHERE id = ?');
    $stmt->bind_param('ss', $status, $id);
    $stmt->execute();
    $stmt->close();
}

function booking_delete(string $id): void
{
    $stmt = db()->prepare('DELETE FROM bookings WHERE id = ?');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $stmt->close();
}

const BOOKING_TRANSITIONS = [
    'PENDING'    => ['CONFIRMED', 'CANCELLED'],
    'CONFIRMED'  => ['CHECKED_IN', 'CANCELLED', 'NO_SHOW'],
    'CHECKED_IN' => ['COMPLETED'],
    'COMPLETED'  => [],
    'CANCELLED'  => [],
    'NO_SHOW'    => [],
];

const TRANSITION_LABELS = [
    'PENDING>CONFIRMED'    => 'Confirm',
    'PENDING>CANCELLED'    => 'Cancel',
    'CONFIRMED>CHECKED_IN' => 'Check in',
    'CONFIRMED>CANCELLED'  => 'Cancel',
    'CONFIRMED>NO_SHOW'    => 'No-show',
    'CHECKED_IN>COMPLETED' => 'Complete',
];

const CHECKIN_OPENS_MIN  = 60;
const CHECKIN_CLOSES_MIN = 30;
const CANCEL_CUTOFF_MIN  = 120;
const NO_SHOW_AFTER_MIN  = 30;

function booking_show_time(array $booking): ?DateTimeImmutable
{
    $time = DateTimeImmutable::createFromFormat('Y-m-d g:i A', $booking['show_date'] . ' ' . $booking['showtime']);
    return $time === false ? null : $time;
}

function minutes_until_show(array $booking, ?DateTimeImmutable $now = null): ?float
{
    $show = booking_show_time($booking);
    if ($show === null) {
        return null;
    }
    $now = $now ?? new DateTimeImmutable('now');
    return ($show->getTimestamp() - $now->getTimestamp()) / 60;
}

function transition_guard(array $booking, string $to, ?DateTimeImmutable $now = null): string
{
    $from = $booking['status'];

    if (!in_array($to, BOOKING_TRANSITIONS[$from] ?? [], true)) {
        return 'A ' . strtolower(status_label($from)) . ' booking cannot become ' . strtolower(status_label($to)) . '.';
    }

    $m = minutes_until_show($booking, $now);
    if ($m === null) {
        return 'The show time of this booking could not be read.';
    }

    switch ($from . '>' . $to) {
        case 'PENDING>CONFIRMED':
            return $m > 0 ? '' : 'The show has already started, so this booking can no longer be confirmed.';
        case 'PENDING>CANCELLED':
            return '';
        case 'CONFIRMED>CHECKED_IN':
            if ($m > CHECKIN_OPENS_MIN) {
                return 'Check-in opens ' . CHECKIN_OPENS_MIN . ' minutes before the show.';
            }
            return $m < -CHECKIN_CLOSES_MIN ? 'Check-in closed ' . CHECKIN_CLOSES_MIN . ' minutes after the show started.' : '';
        case 'CONFIRMED>CANCELLED':
            return $m >= CANCEL_CUTOFF_MIN ? '' : 'Confirmed bookings can only be cancelled at least ' . (CANCEL_CUTOFF_MIN / 60) . ' hours before the show.';
        case 'CONFIRMED>NO_SHOW':
            return $m < -NO_SHOW_AFTER_MIN ? '' : 'A booking can only be marked no-show more than ' . NO_SHOW_AFTER_MIN . ' minutes after the show started.';
        case 'CHECKED_IN>COMPLETED':
            return $m <= 0 ? '' : 'The show has not started yet, so it cannot be completed.';
    }
    return 'That move is not available.';
}

function booking_is_final(array $booking): bool
{
    return (BOOKING_TRANSITIONS[$booking['status']] ?? []) === [];
}


function can_edit(array $booking, bool $staff): bool
{
    return $staff
        ? in_array($booking['status'], ['PENDING', 'CONFIRMED'], true)
        : $booking['status'] === 'PENDING';
}

function can_delete(array $booking, bool $staff): bool
{
    return $staff ? booking_is_final($booking) : $booking['status'] === 'CANCELLED';
}

function handle_booking_action(array $user): never
{
    csrf_verify();

    $staff   = is_staff();
    $home    = dashboard_url($user['role']);
    $do      = (string) ($_POST['do'] ?? '');
    $id      = (string) ($_POST['id'] ?? '');
    $booking = $id !== '' ? booking_find($id) : null;

    if ($booking === null) {
        flash_set('error', 'That booking could not be found.');
        redirect($home);
    }

    $isOwner = $booking['user_id'] !== null && (int) $booking['user_id'] === (int) $user['id'];
    if (!$isOwner && !$staff) {
        forbidden();
    }

    if ($do === 'status') {
        $to = (string) ($_POST['to'] ?? '');
        if (!isset(BOOKING_STATUSES[$to])) {
            flash_set('error', 'Unknown status.');
        } elseif (!$staff && $to !== 'CANCELLED') {
            forbidden();
        } elseif (($reason = transition_guard($booking, $to)) !== '') {
            flash_set('error', 'Booking ' . $id . ': ' . $reason);
        } else {
            booking_set_status($id, $to);
            flash_set('success', 'Booking ' . $id . ($to === 'CANCELLED' ? ' was cancelled.' : ' is now ' . strtolower(status_label($to)) . '.'));
        }
    } elseif ($do === 'delete') {
        if (can_delete($booking, $staff)) {
            booking_delete($id);
            flash_set('success', 'Booking ' . $id . ' was deleted.');
        } else {
            flash_set('error', $staff
                ? 'Booking ' . $id . ' is still active. Only completed, cancelled or no-show bookings can be deleted.'
                : 'Cancel booking ' . $id . ' first. Only cancelled bookings can be deleted.');
        }
    } else {
        flash_set('error', 'Unknown action.');
    }

    redirect($home);
}

function staff_booking_stats(): array
{
    $today = date('Y-m-d');
    $stmt = db()->prepare(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'PENDING'), 0) AS pending,
            COALESCE(SUM(show_date = ? AND status <> 'CANCELLED'), 0) AS today_shows,
            COALESCE(SUM(CASE WHEN status IN ('CONFIRMED','CHECKED_IN','COMPLETED')
                              THEN ticket_count * price_per_ticket ELSE 0 END), 0) AS revenue
           FROM bookings"
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return [
        'total'       => (int) $row['total'],
        'pending'     => (int) $row['pending'],
        'today_shows' => (int) $row['today_shows'],
        'revenue'     => (float) $row['revenue'],
    ];
}