<?php

require_once __DIR__ . '/db.php';

const ROLE_SUPERADMIN = 'SUPERADMIN';
const ROLE_ADMIN      = 'ADMIN';
const ROLE_USER       = 'USER';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 300;

if (session_status() === PHP_SESSION_NONE) {
    session_name('CINEMASESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}


function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_spaces(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function safe_redirect_target(?string $next, string $fallback): string
{
    if ($next !== null && preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9=&_%\-]*)?$/', $next)) {
        return $next;
    }
    return $fallback;
}

function role_label(string $role): string
{
    return match ($role) {
        ROLE_SUPERADMIN => 'Super admin',
        ROLE_ADMIN      => 'Admin',
        default         => 'User',
    };
}

function dashboard_url(string $role): string
{
    return match ($role) {
        ROLE_SUPERADMIN => 'superadmin_dashboard.php',
        ROLE_ADMIN      => 'admin_dashboard.php',
        default         => 'user_dashboard.php',
    };
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $item) {
        $html .= '<div class="flash flash-' . e($item['type']) . '" role="status">'
               . e($item['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('This form has expired. Go back, refresh the page and try again.');
    }
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function password_error(string $password): ?string
{
    $bytes = strlen($password);
    if ($bytes < 8) {
        return 'Use a password with at least 8 characters.';
    }
    if ($bytes > 72) {
        return 'Use a password up to 72 bytes.';
    }
    return null;
}

function create_user(string $name, string $email, string $password, string $role = ROLE_USER): int
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $name, $email, $hash, $role);

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $error) {
        if ((int) $error->getCode() === 1062) {
            throw new DomainException('That email is already registered.');
        }
        throw $error;
    }

    $id = (int) $stmt->insert_id;
    $stmt->close();
    return $id;
}

const ROLES = [ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_USER];

function user_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function count_superadmins(): int
{
    $row = db()->query("SELECT COUNT(*) AS n FROM users WHERE role = 'SUPERADMIN'")->fetch_assoc();
    return (int) $row['n'];
}

function user_booking_count(int $id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM bookings WHERE user_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    return $n;
}

function user_stats(): array
{
    $row = db()->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(role = 'USER'), 0) AS users,
                COALESCE(SUM(role = 'ADMIN'), 0) AS admins,
                COALESCE(SUM(role = 'SUPERADMIN'), 0) AS superadmins
           FROM users"
    )->fetch_assoc();

    return [
        'total'       => (int) $row['total'],
        'users'       => (int) $row['users'],
        'admins'      => (int) $row['admins'],
        'superadmins' => (int) $row['superadmins'],
    ];
}

function users_search(string $q, string $role, int $page, int $perPage): array
{
    $where  = ['1 = 1'];
    $types  = '';
    $params = [];

    $q = trim($q);
    if ($q !== '') {
        $like     = '%' . addcslashes($q, '%_\\') . '%';
        $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
        $types   .= 'ss';
        array_push($params, $like, $like);
    }
    if (in_array($role, ROLES, true)) {
        $where[]  = 'u.role = ?';
        $types   .= 's';
        $params[] = $role;
    }
    $whereSql = implode(' AND ', $where);

    $stmt = db()->prepare("SELECT COUNT(*) AS n FROM users u WHERE $whereSql");
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $total = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $limit  = max(1, $perPage);
    $offset = max(0, ($page - 1) * $limit);

    $stmt = db()->prepare(
        "SELECT u.id, u.name, u.email, u.role, u.created_at,
                (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS bookings
           FROM users u
          WHERE $whereSql
          ORDER BY FIELD(u.role, 'SUPERADMIN', 'ADMIN', 'USER'), u.name
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

function user_update(int $id, string $name, string $email, string $role, string $newPassword = ''): void
{
    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?');
        $stmt->bind_param('ssssi', $name, $email, $role, $hash, $id);
    } else {
        $stmt = db()->prepare('UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?');
        $stmt->bind_param('sssi', $name, $email, $role, $id);
    }

    try {
        $stmt->execute();
    } catch (mysqli_sql_exception $error) {
        if ((int) $error->getCode() === 1062) {
            throw new DomainException('That email is already used by another account.');
        }
        throw $error;
    }
    $stmt->close();
}

function user_delete(int $id): void
{
    $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}


function login_locked_seconds(): int
{
    return max(0, (int) ($_SESSION['login_locked_until'] ?? 0) - time());
}

function login_record_failure(): void
{
    $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
        $_SESSION['login_attempts'] = 0;
    }
}

function attempt_login(string $email, string $password): ?array
{
    $user = find_user_by_email($email);

    $hash = $user['password_hash'] ?? '$2y$10$JDg6oO56Tm/N1q4g5xQWDe0wq2YBwoJEu5ETNad6OA7XpF4pvkdce';
    $ok = password_verify($password, $hash);

    return ($user && $ok) ? $user : null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }

    if (empty($_SESSION['user_id'])) {
        return $cached = null;
    }

    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        logout_user();
        return $cached = null;
    }
    return $cached = $row;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role'], $roles, true);
}

function is_staff(): bool
{
    return has_role(ROLE_ADMIN, ROLE_SUPERADMIN);
}


function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $next = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $page !== ''
            ? $page . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')
            : '';
        flash_set('info', 'Log in to continue.');
        redirect('login.php' . ($next !== '' ? '?next=' . urlencode($next) : ''));
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        forbidden();
    }
    return $user;
}

function redirect_if_logged_in(): void
{
    $user = current_user();
    if ($user !== null) {
        redirect(dashboard_url($user['role']));
    }
}

function forbidden(): never
{
    require_once __DIR__ . '/navbar.php';

    http_response_code(403);
    $user = current_user();
    $role = $user['role'] ?? ROLE_USER;

    page_start('Access denied');
    ?>
<main>
    <center>
        <h1>This screening is staff only</h1>
        <p>Your <?= e(role_label($role)) ?> account can't open this page.</p>
        <p><a href="<?= e(dashboard_url($role)) ?>">Back to your dashboard</a></p>
    </center>
</main>
<?php
    page_end();
    exit;
}