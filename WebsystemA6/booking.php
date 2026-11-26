<?php
require_once __DIR__ . '/includes/booking_lib.php';
require_once __DIR__ . '/includes/navbar.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do'])) {
    handle_booking_action($user);
}

$editId  = (string) ($_GET['edit'] ?? '');
$isEdit  = $editId !== '';
$booking = null;

if ($isEdit) {
    $booking = booking_find($editId);
    $isOwner = $booking !== null && (int) $booking['user_id'] === (int) $user['id'];

    if ($booking === null || (!$isOwner && !is_staff()) || !can_edit($booking, is_staff())) {
        flash_set('error', 'That booking cannot be edited.');
        redirect(dashboard_url($user['role']));
    }
    $movieId = $booking['movie_id'];
} else {
    $movieId = (string) ($_GET['movie'] ?? '');
}

$movie = $movieId !== '' ? movie_find($movieId) : null;
if ($movie === null) {
    flash_set('error', 'Choose a movie first.');
    redirect('movies.php');
}

$formAction = 'booking.php?' . ($isEdit ? 'edit=' . urlencode($editId) . '&' : '') . 'movie=' . urlencode($movie['id']);

if ($booking !== null) {
    $v = [
        'name'        => $booking['customer_name'],
        'email'       => (string) $booking['email'],
        'mobile'      => (string) $booking['mobile'],
        'date'        => $booking['show_date'],
        'showtime'    => $booking['showtime'],
        'tickets'     => (string) $booking['ticket_count'],
        'ticket_type' => $booking['ticket_type'],
        'concession'  => (string) $booking['concession_id'],
        'notes'       => (string) $booking['notes'],
        'seats'       => booking_seats($booking['seats']),
        'terms'       => true,
    ];
} else {
    $v = [
        'name' => is_staff() ? '' : $user['name'], 'email' => is_staff() ? '' : $user['email'], 'mobile' => '', 'date' => '',
        'showtime' => '', 'tickets' => '', 'ticket_type' => 'REGULAR', 'concession' => '',
        'notes' => '', 'seats' => [], 'terms' => false,
    ];
}

$extraShowtimes  = ($booking !== null && !in_array($booking['showtime'], SHOWTIMES, true)) ? [$booking['showtime']] : [];
$showtimeOptions = array_merge(SHOWTIMES, $extraShowtimes);

$serverErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $v = booking_input($_POST);
    $serverErrors = validate_booking($v, $movie, $isEdit ? $editId : null, !$isEdit, $extraShowtimes);

    if (!$serverErrors) {
        if ($isEdit) {
            booking_update($editId, $v);
            flash_set('success', 'Booking ' . $editId . ' was updated.');
        } else {
            $newId = booking_create(is_staff() ? null : (int) $user['id'], $movie, $v);
            flash_set('success', 'Booking ' . $newId . ' saved for ' . $movie['title'] . '. Status: Pending.');
        }
        redirect(dashboard_url($user['role']));
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MovieCinemaSale - Booking</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php render_navbar(); ?>

    <main>

    <section class="movie-hero" id="movie-hero">
        <img id="hero-backdrop" class="hero-backdrop" src="assets/avgd.jpg" alt="">
        <div class="hero-overlay"></div>

        <a id="trailer-link" class="trailer-play" href="#" target="_blank" rel="noopener" aria-label="Watch trailer">
            <span class="trailer-play-icon">&#9658;</span>
        </a>

        <div class="hero-content">
            <p id="movie-status" class="hero-status">Coming soon</p>
            <h1 id="movie-title">Movie Title <span id="movie-rating" class="rating-badge">TBC</span></h1>
            <a href="#booking-details" class="btn-showtimes">View showtimes</a>
        </div>
    </section>

    <section class="movie-details">

        <div class="poster-col">
            <img id="movie-poster" src="assets/avgd.jpg" alt="Movie Poster">
            <a href="#" class="watchlist-link" onclick="return false;">
                <span class="watchlist-icon">&#128278;</span> Add to watchlist
            </a>
        </div>

        <div class="info-col">

            <div class="info-main">
                <h3>Cast</h3>
                <p id="movie-cast">Cast information</p>

                <h3>Synopsis</h3>
                <p id="movie-description">Movie description</p>
            </div>

            <div class="info-side">
                <h4>Runtime</h4>
                <p id="movie-runtime">-</p>

                <h4>Release date</h4>
                <p id="movie-release">-</p>

                <p><span id="movie-genre" class="genre-tag">Genre</span></p>

                <p id="movie-certificate" class="certificate-note">To be confirmed</p>
            </div>

        </div>

    </section>

    <hr>

    <h2 id="booking-details"><?= $isEdit ? 'Edit booking ' . e($editId) : 'Booking Details' ?></h2>

    <form id="booking-form" method="post" action="<?= e($formAction) ?>" novalidate aria-labelledby="booking-details">

        <input type="hidden" id="movie-id" name="movie">
        <?= csrf_field() ?>
        <input type="hidden" id="seats-value" name="seats" value="">
        <?php if ($isEdit): ?><input type="hidden" name="edit" value="<?= e($editId) ?>"><?php endif; ?>

        <p class="required-note">Fields marked <span aria-hidden="true">*</span><span class="sr-only">with an asterisk</span> are required.</p>

        <div id="error-summary" class="error-summary" tabindex="-1" aria-labelledby="error-summary-title" hidden>
            <h3 id="error-summary-title" class="error-summary-title"></h3>
            <ul id="error-summary-list"></ul>
        </div>

        <p class="field-error form-level-error" id="error-movie" data-error-for="movie"></p>

        <?php if ($serverErrors): ?>
        <div class="error-summary" role="alert">
            <h3 class="error-summary-title">Your booking was not saved. Please fix the following:</h3>
            <ul>
                <?php foreach ($serverErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div id="booking-success" class="form-success" tabindex="-1" role="status" hidden></div>

        <section class="booking-fields" aria-labelledby="sec-customer">
            <h3 class="form-section-title" id="sec-customer">Your details</h3>

            <div class="field-group">
                <label for="customer-name">Full name <span aria-hidden="true">*</span></label>
                <input type="text" id="customer-name" name="name" value="<?= e($v['name']) ?>" required minlength="2" maxlength="60"
                       autocomplete="name" placeholder="e.g. Charlyn Usabal"
                       aria-describedby="hint-name error-name">
                <small class="field-hint" id="hint-name">2-60 letters. Spaces, apostrophes and hyphens are fine.</small>
                <p class="field-error" id="error-name" data-error-for="name"></p>
            </div>

            <div class="field-group">
                <label for="email">Email <span aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" value="<?= e($v['email']) ?>" required maxlength="80"
                       autocomplete="email" placeholder="name@example.com"
                       aria-describedby="hint-email error-email">
                <small class="field-hint" id="hint-email">Your booking confirmation is tied to this address.</small>
                <p class="field-error" id="error-email" data-error-for="email"></p>
            </div>

            <div class="field-group">
                <label for="mobile">Mobile number <span aria-hidden="true">*</span></label>
                <input type="tel" id="mobile" name="mobile" value="<?= e($v['mobile']) ?>" required maxlength="13" inputmode="numeric"
                       pattern="(09|\+639)\d{9}" autocomplete="tel" placeholder="09171234567"
                       aria-describedby="hint-mobile error-mobile">
                <small class="field-hint" id="hint-mobile">09XXXXXXXXX or +639XXXXXXXXX, no spaces or dashes.</small>
                <p class="field-error" id="error-mobile" data-error-for="mobile"></p>
            </div>
        </section>

        <section class="booking-fields" aria-labelledby="sec-show">
            <h3 class="form-section-title" id="sec-show">Show and tickets</h3>

            <div class="field-group">
                <label for="date">Date <span aria-hidden="true">*</span></label>
                <input type="date" id="date" name="date" value="<?= e($v['date']) ?>" required
                       aria-describedby="hint-date error-date">
                <small class="field-hint" id="hint-date">Today up to 90 days ahead.</small>
                <p class="field-error" id="error-date" data-error-for="date"></p>
            </div>

            <div class="field-group">
                <label for="showtime">Showtime <span aria-hidden="true">*</span></label>
                <select id="showtime" name="showtime" required aria-describedby="error-showtime">
                    <option value="">Select a showtime</option>
                    <?php foreach ($showtimeOptions as $time): ?>
                    <option <?= $v['showtime'] === $time ? 'selected' : '' ?>><?= e($time) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="field-error" id="error-showtime" data-error-for="showtime"></p>
            </div>

            <div class="field-group">
                <label for="tickets">Number of tickets <span aria-hidden="true">*</span></label>
                <input type="number" id="tickets" name="tickets" value="<?= e($v['tickets']) ?>" required min="1" max="10" step="1"
                       inputmode="numeric" aria-describedby="hint-tickets error-tickets">
                <small class="field-hint" id="hint-tickets">1 to 10 per booking.</small>
                <p class="field-error" id="error-tickets" data-error-for="tickets"></p>
            </div>

            <fieldset class="ticket-type field-wide" aria-describedby="hint-type">
                <legend>Ticket type <span aria-hidden="true">*</span></legend>
                <div class="radio-row">
                    <label class="radio-option"><input type="radio" id="type-regular" name="ticketType" value="REGULAR" <?= $v['ticket_type'] === 'REGULAR' ? 'checked' : '' ?>> Regular</label>
                    <label class="radio-option"><input type="radio" id="type-student" name="ticketType" value="STUDENT" <?= $v['ticket_type'] === 'STUDENT' ? 'checked' : '' ?>> Student</label>
                    <label class="radio-option"><input type="radio" id="type-senior" name="ticketType" value="SENIOR" <?= $v['ticket_type'] === 'SENIOR' ? 'checked' : '' ?>> Senior Citizen</label>
                    <label class="radio-option"><input type="radio" id="type-pwd" name="ticketType" value="PWD" <?= $v['ticket_type'] === 'PWD' ? 'checked' : '' ?>> PWD</label>
                </div>
                <small class="field-hint" id="hint-type">Student, Senior Citizen and PWD tickets are discounted and need a valid ID number.</small>
            </fieldset>

            <div class="field-group field-wide conditional-field" id="concession-wrap" hidden>
                <label for="concession-id" id="concession-label">ID number *</label>
                <input type="text" id="concession-id" name="concession" value="<?= e($v['concession']) ?>" maxlength="20" autocomplete="off"
                       disabled aria-required="false" aria-describedby="hint-concession error-concession">
                <small class="field-hint" id="hint-concession">5-20 letters, numbers or hyphens.</small>
                <p class="field-error" id="error-concession" data-error-for="concession"></p>
            </div>

            <div class="field-group">
                <label for="est-total">Estimated total</label>
                <output id="est-total" class="total-output" for="tickets type-regular type-student type-senior type-pwd">&mdash;</output>
            </div>
        </section>

        <div class="seat-section" id="seat-section" role="group" aria-labelledby="seat-heading"
             aria-describedby="seat-counter error-seats">

            <h2 id="seat-heading">Select Your Seat</h2>
            <p class="seat-counter" id="seat-counter" aria-live="polite"></p>

            <table border="1">
            
                            <tr>
                                <td><input type="checkbox" id="seat-A1" name="seat" value="A1"<?= in_array('A1', $v['seats'], true) ? ' checked' : '' ?>> A1</td>
                                <td><input type="checkbox" id="seat-A2" name="seat" value="A2"<?= in_array('A2', $v['seats'], true) ? ' checked' : '' ?>> A2</td>
                                <td><input type="checkbox" id="seat-A3" name="seat" value="A3"<?= in_array('A3', $v['seats'], true) ? ' checked' : '' ?>> A3</td>
                                <td><input type="checkbox" id="seat-A4" name="seat" value="A4"<?= in_array('A4', $v['seats'], true) ? ' checked' : '' ?>> A4</td>
                                <td><input type="checkbox" id="seat-A5" name="seat" value="A5"<?= in_array('A5', $v['seats'], true) ? ' checked' : '' ?>> A5</td>
                            </tr>
            
                            <tr>
                                <td><input type="checkbox" id="seat-B1" name="seat" value="B1"<?= in_array('B1', $v['seats'], true) ? ' checked' : '' ?>> B1</td>
                                <td><input type="checkbox" id="seat-B2" name="seat" value="B2"<?= in_array('B2', $v['seats'], true) ? ' checked' : '' ?>> B2</td>
                                <td><input type="checkbox" id="seat-B3" name="seat" value="B3"<?= in_array('B3', $v['seats'], true) ? ' checked' : '' ?>> B3</td>
                                <td><input type="checkbox" id="seat-B4" name="seat" value="B4"<?= in_array('B4', $v['seats'], true) ? ' checked' : '' ?>> B4</td>
                                <td><input type="checkbox" id="seat-B5" name="seat" value="B5"<?= in_array('B5', $v['seats'], true) ? ' checked' : '' ?>> B5</td>
                            </tr>
            
                            <tr>
                                <td><input type="checkbox" id="seat-C1" name="seat" value="C1"<?= in_array('C1', $v['seats'], true) ? ' checked' : '' ?>> C1</td>
                                <td><input type="checkbox" id="seat-C2" name="seat" value="C2"<?= in_array('C2', $v['seats'], true) ? ' checked' : '' ?>> C2</td>
                                <td><input type="checkbox" id="seat-C3" name="seat" value="C3"<?= in_array('C3', $v['seats'], true) ? ' checked' : '' ?>> C3</td>
                                <td><input type="checkbox" id="seat-C4" name="seat" value="C4"<?= in_array('C4', $v['seats'], true) ? ' checked' : '' ?>> C4</td>
                                <td><input type="checkbox" id="seat-C5" name="seat" value="C5"<?= in_array('C5', $v['seats'], true) ? ' checked' : '' ?>> C5</td>
                            </tr>
            
                            <tr>
                                <td><input type="checkbox" id="seat-D1" name="seat" value="D1"<?= in_array('D1', $v['seats'], true) ? ' checked' : '' ?>> D1</td>
                                <td><input type="checkbox" id="seat-D2" name="seat" value="D2"<?= in_array('D2', $v['seats'], true) ? ' checked' : '' ?>> D2</td>
                                <td><input type="checkbox" id="seat-D3" name="seat" value="D3"<?= in_array('D3', $v['seats'], true) ? ' checked' : '' ?>> D3</td>
                                <td><input type="checkbox" id="seat-D4" name="seat" value="D4"<?= in_array('D4', $v['seats'], true) ? ' checked' : '' ?>> D4</td>
                                <td><input type="checkbox" id="seat-D5" name="seat" value="D5"<?= in_array('D5', $v['seats'], true) ? ' checked' : '' ?>> D5</td>
                            </tr>
            
                        </table>

            <p class="field-error" id="error-seats" data-error-for="seats"></p>

        </div>

        <section class="booking-fields" aria-labelledby="sec-extras">
            <h3 class="form-section-title" id="sec-extras">Extras</h3>

            <div class="field-group field-wide">
                <label for="notes">Special requests (optional)</label>
                <textarea id="notes" name="notes" rows="3" maxlength="200"
                          placeholder="e.g. Wheelchair-accessible seating"
                          aria-describedby="hint-notes error-notes"><?= e($v['notes']) ?></textarea>
                <small class="field-hint" id="hint-notes">Up to 200 characters.</small>
                <p class="field-error" id="error-notes" data-error-for="notes"></p>
            </div>

            <div class="field-group field-wide">
                <label class="check-option" for="terms">
                    <input type="checkbox" id="terms" name="terms" required <?= $v['terms'] ? 'checked' : '' ?> aria-describedby="error-terms">
                    <span>I agree to the booking terms. Tickets are non-refundable once the movie has started. <span aria-hidden="true">*</span></span>
                </label>
                <p class="field-error" id="error-terms" data-error-for="terms"></p>
            </div>
        </section>

        <input type="submit" value="<?= $isEdit ? 'Save changes' : 'Confirm Booking' ?>">

    </form>

    <hr class="footer-divider">

    </main>


    <script>
        const movies = {
            avgd: {
                title: "Avengers: Doomsday",
                poster: "assets/avgd.jpg",
                description: "Heroes from three different worlds must unite when they're thrust together to confront a catastrophic danger that could destroy everything they know.",
                cast: "Pedro Pascal, Chris Hemsworth, Robert Downey Jr.",
                runtime: "2h 50m",
                release: "18 December 2026",
                genre: "Action/Adventure",
                rating: "TBC",
                certificate: "To be confirmed",
                status: "Coming soon",
                trailer: "https://www.youtube.com/results?search_query=Avengers+Doomsday+Official+Trailer"
            },
            avgen: {
                title: "Avengers: Endgame (Encore)",
                poster: "assets/avgen.jpg",
                description: "The epic conclusion to the Infinity Saga returns to theaters with new footage and a post-credits scene that bridges directly into Avengers: Doomsday.",
                cast: "Robert Downey Jr., Chris Evans, Chris Hemsworth",
                runtime: "3h 5m",
                release: "25 September 2026",
                genre: "Sci-Fi/Action",
                rating: "PG-13",
                certificate: "PG-13",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Avengers+Endgame+Encore+Official+Trailer"
            },
            cf: {
                title: "Clayface",
                poster: "assets/cf.jpg",
                description: "A rising Hollywood actor's horrifying descent into a revenge-driven monster, exploring the loss of identity and humanity, the ravages of toxic love, and the dark side of scientific ambition.",
                cast: "Tom Rhys Harries, Naomi Ackie, Max Minghella",
                runtime: "To be confirmed",
                release: "23 October 2026",
                genre: "Horror",
                rating: "R",
                certificate: "R",
                status: "Coming soon",
                trailer: "https://www.youtube.com/results?search_query=Clayface+Official+Trailer+2026"
            },
            chkw: {
                title: "Chiikawa the Movie: The Secret of Mermaid Island",
                poster: "assets/chkw.jpg",
                description: "Enticed by rumours of a glittering island filled with rare treats and easy rewards, Chiikawa and friends set off on a trip that quickly takes an unexpected turn, as a quiet mystery involving a mermaid presence lurks beneath the sparkling waters.",
                cast: "Haruka Aoki, Makoto Tanaka, Airi Ozawa",
                runtime: "To be confirmed",
                release: "24 July 2026",
                genre: "Animation/Family",
                rating: "G",
                certificate: "G",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Chiikawa+the+Movie+Secret+of+Mermaid+Island+Trailer"
            },
            dconan: {
                title: "Detective Conan: Fallen Angel of the Highway",
                poster: "assets/dconan.png",
                description: "On the way to a motorcycle festival in Kanagawa, Conan and his friends encounter a mysterious black motorcycle being pursued by traffic officer Chihaya Hagiwara, drawing the group into a high-speed mystery tangled with unresolved memories and personal loss.",
                cast: "Minami Takayama, Wakana Yamazaki, Rikiya Koyama",
                runtime: "1h 49m",
                release: "10 April 2026",
                genre: "Animation/Mystery",
                rating: "PG",
                certificate: "PG",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Detective+Conan+Fallen+Angel+of+the+Highway+Trailer"
            },
            f2: {
                title: "Fall 2: Deadpoint",
                poster: "assets/f2.jpg",
                description: "Grieving her sister Shiloh's death, Jax Hunter bonds with Shiloh's bold friend Luce and attempts the infamous plank walk of Thailand's Mount Kwan. When a rockslide traps them on a narrow plank thousands of feet up, Jax must face her fears to survive.",
                cast: "Harriet Slater, Arsema Thomas, Tom Brittney",
                runtime: "1h 40m",
                release: "7 August 2026",
                genre: "Thriller",
                rating: "PG-13",
                certificate: "PG-13",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Fall+2+Deadpoint+Official+Trailer"
            },
            insid: {
                title: "Insidious: Out of the Further",
                poster: "assets/insid.jpg",
                description: "Gemma, a young mother raising her daughter in the house she grew up in, discovers she can travel into The Further, the purgatorial realm of lost souls. When something evil comes after her, she learns her power isn't just to enter The Further, but to bring what lives there back — and once the demons realise, our world becomes their playground.",
                cast: "Amelia Eve, Lin Shaye, Brandon Perea",
                runtime: "1h 46m",
                release: "21 August 2026",
                genre: "Horror",
                rating: "PG-13",
                certificate: "PG-13",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Insidious+Out+of+the+Further+Official+Trailer"
            },
            rsd: {
                title: "Resident Evil",
                poster: "assets/rsd.jpg",
                description: "Bryan, a medical courier, unwittingly finds himself in an action-packed, non-stop race for survival as one fateful, horrifying night collapses around him into an outbreak of mutated creatures.",
                cast: "Austin Abrams, Zach Cherry, Kali Reis",
                runtime: "1h 35m",
                release: "18 September 2026",
                genre: "Horror/Action",
                rating: "R",
                certificate: "R",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Resident+Evil+2026+Official+Trailer+Zach+Cregger"
            },
            sbnd: {
                title: "Spiderman: Brand New Day",
                poster: "assets/sbnd.jpg",
                description: "Four years after No Way Home, Peter Parker fights crime full-time in a New York that no longer knows his name. As the pressure of protecting his city intensifies, it sparks a change he may not have the power to control — one that might be the only thing able to stop a shocking new threat no one can even see.",
                cast: "Tom Holland, Zendaya, Sadie Sink",
                runtime: "2h 30m",
                release: "31 July 2026",
                genre: "Action/Adventure",
                rating: "PG-13",
                certificate: "PG-13",
                status: "Now showing",
                trailer: "https://www.youtube.com/results?search_query=Spider-Man+Brand+New+Day+Official+Trailer"
            },
            dnp3: {
                title: "Dune: Part Three",
                poster: "assets/dnp3.jpg",
                description: "Nearly two decades after Paul Atreides seized control of the Imperium, the now-ruthless Emperor must face the consequences of his reign as old allies return, terrifying new threats emerge, and betrayal lurks in every shadow.",
                cast: "Timothée Chalamet, Zendaya, Jason Momoa",
                runtime: "2h 20m",
                release: "18 December 2026",
                genre: "Adventure/Sci-Fi",
                rating: "TBC",
                certificate: "To be confirmed",
                status: "Coming soon",
                trailer: "https://www.youtube.com/results?search_query=Dune+Part+Three+Official+Trailer"
            }
        };

        const params = new URLSearchParams(window.location.search);
        const id = params.get("movie");
        const movie = movies[id];

        if (movie) {
            document.getElementById("movie-title").firstChild.textContent = movie.title + " ";
            document.getElementById("movie-rating").textContent = movie.rating;
            document.getElementById("movie-status").textContent = movie.status;

            document.getElementById("hero-backdrop").src = movie.poster;
            document.getElementById("hero-backdrop").alt = movie.title;

            document.getElementById("movie-poster").src = movie.poster;
            document.getElementById("movie-poster").alt = movie.title;

            document.getElementById("movie-cast").textContent = movie.cast;
            document.getElementById("movie-description").textContent = movie.description;
            document.getElementById("movie-runtime").textContent = movie.runtime;
            document.getElementById("movie-release").textContent = movie.release;
            document.getElementById("movie-genre").textContent = movie.genre;
            document.getElementById("movie-certificate").textContent = movie.certificate;

            document.getElementById("trailer-link").href = movie.trailer;

            document.getElementById("movie-id").value = id;
            document.title = "MovieCinemaSale - Booking - " + movie.title;
        }
    </script>

    <script src="booking-validation.js" defer></script>

<?php render_footer(); ?>