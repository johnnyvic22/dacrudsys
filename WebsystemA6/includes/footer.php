<?php

require_once __DIR__ . '/auth.php';

function render_footer(): void
{
    ?>
    <footer>
        <center>
            <p class="footer-links">
                <a href="index.php">Home</a> |
                <a href="movies.php">Movies</a> |
                <a href="<?= e(current_user() ? dashboard_url(current_user()['role']) : 'login.php') ?>">My Bookings</a>
            </p>
            <p>&copy; <?= date('Y') ?> MovieCinemaSale . All Rights Reserved.</p>
        </center>
    </footer>

</body>
</html>
<?php
}

function page_end(): void
{
    render_footer();
}