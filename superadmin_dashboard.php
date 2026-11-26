<?php
require_once __DIR__ . '/includes/bookings_panel.php';
require_once __DIR__ . '/includes/navbar.php';

$user = require_role(ROLE_SUPERADMIN);
$me   = (int) $user['id'];

$view = (($_GET['view'] ?? '') === 'accounts' || $_SERVER['REQUEST_METHOD'] === 'POST') ? 'accounts' : 'bookings';
$self = 'superadmin_dashboard.php';

$formMode   = null;
$formErrors = [];
$formValues = ['id' => 0, 'name' => '', 'email' => '', 'role' => ROLE_USER];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'create' || $do === 'update') {
        $id       = (int) ($_POST['id'] ?? 0);
        $target   = $do === 'update' ? user_find($id) : null;
        $name     = normalize_spaces((string) ($_POST['name'] ?? ''));
        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role     = (string) ($_POST['role'] ?? ROLE_USER);
        $password = (string) ($_POST['password'] ?? '');

        if ($do === 'update' && $target === null) {
            flash_set('error', 'That account could not be found.');
            redirect($self . '?view=accounts');
        }
        if ($do === 'update' && $id === $me) {
            $role = $target['role'];
        }

        $nameLength = mb_strlen($name);
        if ($nameLength < 2 || $nameLength > 80) {
            $formErrors[] = 'Enter a full name (2 to 80 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $formErrors[] = 'Enter a valid email address.';
        }
        if (!in_array($role, ROLES, true)) {
            $formErrors[] = 'Choose a role.';
        }
        if ($do === 'create' || $password !== '') {
            $problem = password_error($password);
            if ($problem !== null) {
                $formErrors[] = $problem;
            }
        }
        if ($do === 'update' && $target['role'] === ROLE_SUPERADMIN && $role !== ROLE_SUPERADMIN && count_superadmins() <= 1) {
            $formErrors[] = 'The site must keep at least one Super admin.';
        }

        if (!$formErrors) {
            try {
                if ($do === 'create') {
                    create_user($name, $email, $password, $role);
                    flash_set('success', 'Account created for ' . $name . ' (' . role_label($role) . ').');
                } else {
                    user_update($id, $name, $email, $role, $password);
                    flash_set('success', 'Account updated for ' . $name . '.');
                }
                redirect($self . '?view=accounts');
            } catch (DomainException $problem) {
                $formErrors[] = $problem->getMessage();
            }
        }

        $formMode   = $do === 'create' ? 'new' : 'edit';
        $formValues = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role];

    } elseif ($do === 'delete') {
        $id     = (int) ($_POST['id'] ?? 0);
        $target = user_find($id);

        if ($target === null) {
            flash_set('error', 'That account could not be found.');
        } elseif ($id === $me) {
            flash_set('error', 'You cannot delete your own account.');
        } elseif ($target['role'] === ROLE_SUPERADMIN && count_superadmins() <= 1) {
            flash_set('error', 'The site must keep at least one Super admin.');
        } else {
            $kept = user_booking_count($id);
            user_delete($id);
            flash_set('success', $target['name'] . ' was deleted.' . ($kept > 0 ? ' Their ' . $kept . ' booking(s) were kept.' : ''));
        }
        redirect($self . '?view=accounts');
    } else {
        redirect($self . '?view=accounts');
    }
}

if ($formMode === null && $view === 'accounts') {
    if (($_GET['action'] ?? '') === 'new') {
        $formMode = 'new';
    } elseif (isset($_GET['edit'])) {
        $target = user_find((int) $_GET['edit']);
        if ($target === null) {
            flash_set('error', 'That account could not be found.');
            redirect($self . '?view=accounts');
        }
        $formMode   = 'edit';
        $formValues = ['id' => (int) $target['id'], 'name' => $target['name'], 'email' => $target['email'], 'role' => $target['role']];
    }
}

$tabs = '<div class="dash-tabs" role="navigation" aria-label="Super admin sections">'
      . '<a href="' . $self . '"' . ($view === 'bookings' ? ' aria-current="page"' : '') . '>Bookings</a>'
      . '<a href="' . $self . '?view=accounts"' . ($view === 'accounts' ? ' aria-current="page"' : '') . '>Accounts</a>'
      . '</div>';

if ($view === 'accounts') {
    $aq      = trim((string) ($_GET['q'] ?? ''));
    $arole   = (string) ($_GET['role'] ?? '');
    $perPage = 10;
    $page    = max(1, (int) ($_GET['page'] ?? 1));

    $accountStats = user_stats();
    $found        = users_search($aq, $arole, $page, $perPage);
    $accounts     = $found['rows'];
    $accTotal     = $found['total'];
    $totalPages   = max(1, (int) ceil($accTotal / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $isFiltered = $aq !== '' || in_array($arole, ROLES, true);
    $from = $accTotal === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to   = min($accTotal, $page * $perPage);

    $pageLink = function (int $n) use ($aq, $arole, $self): string {
        $query = ['view' => 'accounts', 'page' => $n];
        if ($aq !== '') {
            $query['q'] = $aq;
        }
        if (in_array($arole, ROLES, true)) {
            $query['role'] = $arole;
        }
        return $self . '?' . http_build_query($query);
    };
}

page_start('Super Admin Dashboard');
?>
<main>
<?php if ($view === 'bookings'): ?>

    <?php render_bookings_dashboard([
        'mode'   => 'staff',
        'user'   => $user,
        'action' => $self,
        'title'  => 'Super Admin Dashboard',
        'intro'  => 'Welcome back, ' . $user['name'] . '. Manage every booking and every account.',
        'tabs'   => $tabs,
    ]); ?>

<?php else: ?>

<div class="bookings-page">

    <?= flash_render() ?>

    <div class="page-head">
        <div>
            <h1>Super Admin Dashboard</h1>
            <p class="page-intro">Create accounts, change roles and remove people who no longer need access.</p>
        </div>
        <a class="btn-showtimes" href="<?= e($self) ?>?view=accounts&amp;action=new">New account</a>
    </div>

    <?= $tabs ?>

    <section class="summary-grid" aria-label="Account summary">
        <div class="summary-card"><h4>All accounts</h4><p><?= $accountStats['total'] ?></p></div>
        <div class="summary-card"><h4>Users</h4><p><?= $accountStats['users'] ?></p></div>
        <div class="summary-card"><h4>Admins</h4><p><?= $accountStats['admins'] ?></p></div>
        <div class="summary-card"><h4>Super admins</h4><p><?= $accountStats['superadmins'] ?></p></div>
    </section>

    <?php if ($formMode !== null): ?>
        <?php $isNew = $formMode === 'new'; $isSelf = !$isNew && (int) $formValues['id'] === $me; ?>
        <section class="auth-card account-card" aria-labelledby="account-form-title">
            <h2 id="account-form-title"><?= $isNew ? 'New account' : 'Edit account' ?></h2>

            <?php if ($formErrors): ?>
                <div class="flash flash-error" role="alert">
                    <strong>Please check the following:</strong>
                    <ul>
                        <?php foreach ($formErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e($self) ?>?view=accounts" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="<?= $isNew ? 'create' : 'update' ?>">
                <input type="hidden" name="id" value="<?= (int) $formValues['id'] ?>">

                <div class="form-row">
                    <div>
                        <label for="acc-name">Full name</label>
                        <input type="text" id="acc-name" name="name" value="<?= e($formValues['name']) ?>" required>
                    </div>
                    <div>
                        <label for="acc-email">Email address</label>
                        <input type="email" id="acc-email" name="email" value="<?= e($formValues['email']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label for="acc-role">Role</label>
                        <select id="acc-role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                            <?php foreach (ROLES as $roleOption): ?>
                                <option value="<?= e($roleOption) ?>" <?= $formValues['role'] === $roleOption ? 'selected' : '' ?>><?= e(role_label($roleOption)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isSelf): ?><small>Your own role is protected.</small><?php endif; ?>
                    </div>
                    <div>
                        <label for="acc-password"><?= $isNew ? 'Password' : 'New password (optional)' ?></label>
                        <input type="password" id="acc-password" name="password" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                        <small><?= $isNew ? 'At least 8 characters, up to 72 bytes.' : 'Leave empty to keep the current password.' ?></small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="auth-submit"><?= $isNew ? 'Create account' : 'Save changes' ?></button>
                    <a class="btn-clear" href="<?= e($self) ?>?view=accounts">Cancel</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <form class="bookings-controls" method="get" action="<?= e($self) ?>" role="search" aria-label="Find an account">
        <input type="hidden" name="view" value="accounts">

        <div class="filter-field filter-search">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e($aq) ?>" placeholder="Name or email">
        </div>

        <div class="filter-field">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                <?php foreach (ROLES as $roleOption): ?>
                    <option value="<?= e($roleOption) ?>" <?= $arole === $roleOption ? 'selected' : '' ?>><?= e(role_label($roleOption)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-search">Search</button>
            <?php if ($isFiltered): ?>
                <a class="btn-clear" href="<?= e($self) ?>?view=accounts">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <p class="bookings-result-count" aria-live="polite">
        <?php if ($accTotal === 0): ?>
            No accounts to show.
        <?php else: ?>
            Showing <?= $from ?>-<?= $to ?> of <?= $accTotal ?> account<?= $accTotal === 1 ? '' : 's' ?><?= $isFiltered ? ' that match your search' : '' ?>.
        <?php endif; ?>
    </p>

    <div class="bookings-table-wrap">
        <table id="bookings-table">
            <thead>
                <tr>
                    <th scope="col">Member</th>
                    <th scope="col">Role</th>
                    <th scope="col">Bookings</th>
                    <th scope="col">Joined</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$accounts): ?>
                <tr><td colspan="5" class="empty-state">No accounts match that search. Try a different word or clear the filters.</td></tr>
            <?php endif; ?>

            <?php foreach ($accounts as $a): ?>
                <?php $isMe = (int) $a['id'] === $me; ?>
                <tr>
                    <td data-label="Member" class="customer-cell">
                        <strong><?= e($a['name']) ?></strong><?= $isMe ? ' (you)' : '' ?>
                        <small><?= e($a['email']) ?></small>
                    </td>
                    <td data-label="Role"><span class="role-badge role-<?= e(strtolower($a['role'])) ?>"><?= e(role_label($a['role'])) ?></span></td>
                    <td data-label="Bookings"><?= (int) $a['bookings'] ?></td>
                    <td data-label="Joined"><?= e(date('M j, Y', strtotime($a['created_at']))) ?></td>
                    <td data-label="Actions">
                        <div class="row-actions">
                            <a class="action-link" href="<?= e($self) ?>?view=accounts&amp;edit=<?= (int) $a['id'] ?>">Edit</a>
                            <?php if (!$isMe): ?>
                                <form method="post" action="<?= e($self) ?>?view=accounts"
                                      onsubmit="return confirm('Delete <?= e(addslashes($a['name'])) ?>? Their <?= (int) $a['bookings'] ?> booking(s) will be kept.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" data-action="cancel">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php render_pagination($page, $totalPages, $pageLink, 'Pages of accounts'); ?>

</div>

<?php endif; ?>
</main>

<?php page_end(); ?>