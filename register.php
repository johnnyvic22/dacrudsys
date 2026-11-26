<?php
require_once __DIR__ . '/includes/auth.php';
redirect_if_logged_in();

$errors = [];
$name   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $name     = trim(preg_replace('/\s+/', ' ', (string) ($_POST['name'] ?? '')));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $nameLength = mb_strlen($name);
    if ($nameLength < 2 || $nameLength > 80) {
        $errors[] = 'Enter your full name (2 to 80 characters).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Enter a valid email address.';
    }

    $passwordProblem = password_error($password);
    if ($passwordProblem !== null) {
        $errors[] = $passwordProblem;
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        try {
            // Registration always creates a normal User account.
            $newId = create_user($name, $email, $password, ROLE_USER);
            login_user(['id' => $newId]);
            flash_set('success', 'Account created. Welcome, ' . $name . '.');
            redirect(dashboard_url(ROLE_USER));
        } catch (DomainException $problem) {
            $errors[] = $problem->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create an account | Movie Cinema Sale</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="auth-main">
    <section class="auth-card">
        <?= flash_render() ?>
        <h1>Create an account</h1>
        <p>Register to book seats. New accounts start as a User.</p>

        <?php if ($errors): ?>
            <div class="flash flash-error" role="alert">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $message): ?>
                        <li><?= e($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php" novalidate>
            <?= csrf_field() ?>

            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?= e($name) ?>" autocomplete="name" required>

            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
            <small>At least 8 characters, up to 72 bytes.</small>

            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>

            <button type="submit" class="auth-submit">Create account</button>
        </form>

        <p class="auth-switch">Already registered? <a href="login.php">Log in</a></p>
    </section>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>