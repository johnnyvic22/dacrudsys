<?php
require_once __DIR__ . '/includes/navbar.php';

$do = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['do'] ?? '') : '';

redirect_if_logged_in();

$register = ($_GET['mode'] ?? '') === 'register';
$errors   = [];
$name     = '';
$email    = '';

if ($do === 'login' || $do === 'register') {
    csrf_verify();
    $name     = normalize_spaces((string) ($_POST['name'] ?? ''));
    $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
}

if ($do === 'login') {
    $wait = login_locked_seconds();

    if ($wait > 0) {
        $errors[] = 'Too many wrong attempts. Try again in ' . (int) ceil($wait / 60) . ' minute(s).';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Enter your email and password.';
    } elseif ($found = attempt_login($email, $password)) {
        login_user($found);
        flash_set('success', 'Welcome back, ' . $found['name'] . '.');
        redirect(safe_redirect_target($_GET['next'] ?? null, dashboard_url($found['role'])));
    } else {
        login_record_failure();
        $errors[] = 'Incorrect email or password.';
    }
}

if ($do === 'register') {
    $register = true;
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $length = mb_strlen($name);
    if ($length < 2 || $length > 80) {
        $errors[] = 'Enter your full name (2 to 80 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }
    if (($problem = password_error($password)) !== null) {
        $errors[] = $problem;
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            login_user(['id' => create_user($name, $email, $password, ROLE_USER)]);
            flash_set('success', 'Account created. Welcome, ' . $name . '.');
            redirect(dashboard_url(ROLE_USER));
        } catch (DomainException $problem) {
            $errors[] = $problem->getMessage();
        }
    }
}

$next = isset($_GET['next']) ? '&next=' . urlencode((string) $_GET['next']) : '';
page_start($register ? 'Create an account' : 'Log in');
?>

<main class="auth-main">
    <section class="auth-card">
        <?= flash_render() ?>

        <h1><?= $register ? 'Create an account' : 'Log in' ?></h1>
        <p><?= $register ? 'Register to book seats. New accounts start as a User.' : 'Sign in to book seats and manage your tickets.' ?></p>

        <?php if ($errors): ?>
            <div class="flash flash-error" role="alert">
                <ul>
                    <?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="login.php<?= $register ? '?mode=register' : ($next !== '' ? '?' . ltrim($next, '&') : '') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="<?= $register ? 'register' : 'login' ?>">

            <?php if ($register): ?>
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>" autocomplete="name" required>
            <?php endif; ?>

            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" required>
            <?php if ($register): ?>
                <small>At least 8 characters, up to 72 bytes.</small>

                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            <?php endif; ?>

            <button type="submit" class="auth-submit"><?= $register ? 'Create account' : 'Log in' ?></button>
        </form>

        <?php if (!$register): ?>
            <div class="demo-accounts">
                <p>Demo accounts. Click one to fill the form, then log in. Password: <code>Demo123!</code></p>
                <button type="button" data-email="superadmin@moviecinema.demo">Super admin</button>
                <button type="button" data-email="admin@moviecinema.demo">Admin</button>
                <button type="button" data-email="user@moviecinema.demo">User</button>
            </div>
        <?php endif; ?>

        <p class="auth-switch">
            <?= $register ? 'Already registered? <a href="login.php">Log in</a>' : 'No account yet? <a href="login.php?mode=register">Create an account</a>' ?>
        </p>
    </section>
</main>

<?php if (!$register): ?>
<script>
    document.querySelectorAll('[data-email]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('email').value = button.dataset.email;
            document.getElementById('password').value = 'Demo123!';
        });
    });
</script>
<?php endif; ?>

<?php page_end(); ?>