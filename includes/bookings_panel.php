<?php

require_once __DIR__ . '/booking_lib.php';

function render_pagination(int $page, int $totalPages, callable $link, string $label): void
{
    if ($totalPages <= 1) {
        return;
    }
    ?>
    <div class="bookings-pagination" role="navigation" aria-label="<?= e($label) ?>">
        <?php if ($page > 1): ?>
            <a href="<?= e($link($page - 1)) ?>">Previous</a>
        <?php else: ?>
            <span class="is-disabled">Previous</span>
        <?php endif; ?>

        <?php for ($n = 1; $n <= $totalPages; $n++): ?>
            <?php if ($n === $page): ?>
                <span aria-current="page"><?= $n ?></span>
            <?php else: ?>
                <a href="<?= e($link($n)) ?>"><?= $n ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?= e($link($page + 1)) ?>">Next</a>
        <?php else: ?>
            <span class="is-disabled">Next</span>
        <?php endif; ?>
    </div>
    <?php
}


function action_form(string $id, string $do, string $label, string $kind, string $to = '', string $confirm = ''): void
{
    $onsubmit = $confirm !== '' ? ' onsubmit="return confirm(\'' . e(addslashes($confirm)) . '\');"' : '';
    ?>
    <form method="post" action="booking.php"<?= $onsubmit ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
        <input type="hidden" name="do" value="<?= e($do) ?>">
        <?php if ($to !== ''): ?><input type="hidden" name="to" value="<?= e($to) ?>"><?php endif; ?>
        <button type="submit" data-action="<?= e($kind) ?>"><?= e($label) ?></button>
    </form>
    <?php
}

function render_row_actions(array $b, bool $staff, DateTimeImmutable $now): void
{
    ob_start();

    if (can_edit($b, $staff)) {
        echo '<a class="action-link" href="booking.php?edit=' . e(urlencode($b['id'])) . '&amp;movie=' . e(urlencode($b['movie_id'])) . '">Edit</a>';
    }

    $moves = $staff ? BOOKING_TRANSITIONS[$b['status']] : array_intersect(BOOKING_TRANSITIONS[$b['status']], ['CANCELLED']);
    foreach ($moves as $to) {
        $label  = TRANSITION_LABELS[$b['status'] . '>' . $to];
        $reason = transition_guard($b, $to, $now);
        $kind   = in_array($to, ['CANCELLED', 'NO_SHOW'], true) ? 'cancel' : 'advance';

        if ($reason === '') {
            action_form($b['id'], 'status', $label, $kind, $to, $kind === 'cancel' ? $label . ' booking ' . $b['id'] . '?' : '');
        } elseif ($staff) {
            echo '<button type="button" data-action="' . e($kind) . '" disabled title="' . e($reason)
               . '" aria-label="' . e($label . ' unavailable. ' . $reason) . '">' . e($label) . '</button>';
        }
    }

    if (can_delete($b, $staff)) {
        action_form($b['id'], 'delete', 'Delete', 'cancel', '', 'Delete booking ' . $b['id'] . ' permanently?');
    }

    $buttons = ob_get_clean();
    echo '<div class="row-actions">', $buttons !== '' ? $buttons : '<span class="no-actions">No actions</span>', '</div>';
}

function render_bookings_dashboard(array $o): void
{
    $staff  = $o['mode'] === 'staff';
    $user   = $o['user'];
    $action = $o['action'];

    $filters = [
        'q'      => trim((string) ($_GET['q'] ?? '')),
        'status' => (string) ($_GET['status'] ?? ''),
        'movie'  => (string) ($_GET['movie'] ?? ''),
        'date'   => (string) ($_GET['date'] ?? ''),
    ];
    $perPage = 8;
    $page    = max(1, (int) ($_GET['page'] ?? 1));

    $movies = movies_all();
    $result = bookings_search($filters, $staff ? null : (int) $user['id'], $page, $perPage);
    $rows   = $result['rows'];
    $total  = $result['total'];

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = min($page, $totalPages);
    $isFiltered = $filters['q'] !== '' || $filters['status'] !== '' || $filters['movie'] !== '' || $filters['date'] !== '';
    $from       = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to         = min($total, $page * $perPage);
    $now        = new DateTimeImmutable('now');

    if ($staff) {
        $s = staff_booking_stats();
        $cards = [
            'All bookings'            => $s['total'],
            'Waiting for confirmation' => $s['pending'],
            'Shows today'             => $s['today_shows'],
            'Revenue'                 => money($s['revenue']),
        ];
    } else {
        $s = user_booking_stats((int) $user['id']);
        $cards = [
            'Upcoming shows'          => $s['upcoming'],
            'Tickets booked'          => $s['tickets'],
            'Total value'             => money($s['total']),
            'Waiting for confirmation' => $s['pending'],
        ];
    }

    $pageLink = function (int $n) use ($filters, $action): string {
        return $action . '?' . http_build_query(array_filter($filters, 'strlen') + ['page' => $n]);
    };
    $columns = $staff
        ? ['Booking', 'Customer', 'Movie', 'Show', 'Seats', 'Total', 'Status', 'Actions']
        : ['Booking', 'Movie', 'Show', 'Seats', 'Total', 'Status', 'Actions'];
    ?>
<div class="bookings-page">

    <?= flash_render() ?>

    <div class="page-head">
        <div>
            <h1><?= e($o['title']) ?></h1>
            <p class="page-intro"><?= e($o['intro']) ?></p>
        </div>
        <a class="btn-showtimes" href="movies.php"><?= $staff ? 'New booking' : 'Book tickets' ?></a>
    </div>

    <?= $o['tabs'] ?? '' ?>

    <section class="summary-grid" aria-label="Booking summary">
        <?php foreach ($cards as $label => $value): ?>
            <div class="summary-card"><h4><?= e($label) ?></h4><p><?= e((string) $value) ?></p></div>
        <?php endforeach; ?>
    </section>

    <form class="bookings-controls" method="get" action="<?= e($action) ?>" role="search" aria-label="Find a booking">
        <div class="filter-field filter-search">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>"
                   placeholder="<?= $staff ? 'ID, customer, email, mobile, movie or seat' : 'Booking ID, movie or seat' ?>">
        </div>

        <div class="filter-field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (BOOKING_STATUSES as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="movie">Movie</label>
            <select id="movie" name="movie">
                <option value="">All movies</option>
                <?php foreach ($movies as $movie): ?>
                    <option value="<?= e($movie['id']) ?>" <?= $filters['movie'] === $movie['id'] ? 'selected' : '' ?>><?= e($movie['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-field">
            <label for="date">Show date</label>
            <input type="date" id="date" name="date" value="<?= e($filters['date']) ?>">
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-search">Search</button>
            <?php if ($isFiltered): ?><a class="btn-clear" href="<?= e($action) ?>">Clear</a><?php endif; ?>
        </div>
    </form>

    <p class="bookings-result-count" aria-live="polite">
        <?php if ($total === 0): ?>
            No bookings to show.
        <?php else: ?>
            Showing <?= $from ?>-<?= $to ?> of <?= $total ?> booking<?= $total === 1 ? '' : 's' ?><?= $isFiltered ? ' that match your search' : '' ?>.
        <?php endif; ?>
    </p>

    <div class="bookings-table-wrap">
        <table id="bookings-table">
            <thead>
                <tr><?php foreach ($columns as $heading): ?><th scope="col"><?= e($heading) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="<?= count($columns) ?>" class="empty-state">
                    <?= $isFiltered
                        ? 'No bookings match that search. Try a different word or clear the filters.'
                        : ($staff ? 'There are no bookings yet.' : 'You have no bookings yet. Pick a movie and reserve your seats.') ?>
                </td></tr>
            <?php endif; ?>

            <?php foreach ($rows as $b): ?>
                <tr>
                    <td data-label="Booking"><strong><?= e($b['id']) ?></strong></td>

                    <?php if ($staff): ?>
                        <td data-label="Customer" class="customer-cell">
                            <?= e($b['customer_name']) ?>
                            <small><?= e($b['email'] ?: ($b['mobile'] ?: '')) ?><?= $b['user_id'] === null ? ' &middot; walk-in' : '' ?></small>
                        </td>
                    <?php endif; ?>

                    <td data-label="Movie">
                        <span class="movie-cell">
                            <?php if (!empty($b['poster'])): ?><img class="booking-thumb" src="<?= e($b['poster']) ?>" alt=""><?php endif; ?>
                            <span><?= e($b['movie_title']) ?></span>
                        </span>
                    </td>
                    <td data-label="Show">
                        <?= e(date('M j, Y', strtotime($b['show_date']))) ?>
                        <small class="cell-sub"><?= e($b['showtime']) ?> &middot; <?= e($b['screen']) ?></small>
                    </td>
                    <td data-label="Seats"><?= e(str_replace(',', ', ', $b['seats'])) ?></td>
                    <td data-label="Total"><?= e(money(booking_total($b))) ?></td>
                    <td data-label="Status"><span class="status-pill <?= e(status_class($b['status'])) ?>"><?= e(status_label($b['status'])) ?></span></td>
                    <td data-label="Actions"><?php render_row_actions($b, $staff, $now); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($staff): ?>
        <p class="table-hint">Greyed-out buttons are not available yet. Hover over one to see why.</p>
    <?php endif; ?>

    <?php render_pagination($page, $totalPages, $pageLink, 'Pages of bookings'); ?>

</div>
<?php
}