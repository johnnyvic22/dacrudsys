<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/footer.php';

function render_navbar(): void
{
    $navUser = current_user();
    $navPage = basename($_SERVER['SCRIPT_NAME'] ?? '');

    $navLink = function (string $href, string $label) use ($navPage): string {
        $active = ($navPage === $href) ? ' class="active" aria-current="page"' : '';
        return '<a href="' . e($href) . '"' . $active . '>' . e($label) . '</a>';
    };

    $avatar = function (string $label, string $extraClass = ''): string {
        return '<span class="nav-avatar' . $extraClass . '" role="img" aria-label="' . e($label) . '" title="' . e($label) . '">'
             . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="4.2"/><path d="M3.5 21c0-4.6 3.8-7.5 8.5-7.5s8.5 2.9 8.5 7.5z"/></svg>'
             . '</span>';
    };
    ?>
<header>
    <nav>
        <h2>Movie Cinema Sale</h2>

        <div class="nav-links">
            <?= $navLink('index.php', 'Home') ?>
            <?= $navLink('movies.php', 'Movies') ?>
            <?php if ($navUser): ?>
                <?= $navLink(dashboard_url($navUser['role']), 'Dashboard') ?>
            <?php endif; ?>
        </div>

        <div class="nav-user">
            <?php if ($navUser): ?>
                <span class="nav-user-info">
                    <span class="nav-user-name"><?= e($navUser['name']) ?></span>
                    <span class="role-badge role-<?= e(strtolower($navUser['role'])) ?>"><?= e(role_label($navUser['role'])) ?></span>
                </span>
                <?= $avatar('Signed in as ' . $navUser['name']) ?>
                <form method="post" action="logout.php" class="nav-logout">
                    <?= csrf_field() ?>
                    <button type="submit">Log out</button>
                </form>
            <?php else: ?>
                <?= $navLink('login.php', 'Log in') ?>
                <a href="login.php?mode=register">Register</a>
                <span class="role-badge role-guest">Guest</span>
                <?= $avatar('Not signed in', ' is-guest') ?>
            <?php endif; ?>
        </div>
    </nav>
</header>
<?php
}

function page_start(string $title): void
{
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | Movie Cinema Sale</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
<?php
    render_navbar();
}