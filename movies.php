<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title> Movie Cinema Sale <- Movies</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php render_navbar(); ?>

    <main class="movies-main">

    <hr>

    <h1>Movie List</h1>

    <div class="movie-grid">

    <div>
        <img src="assets/avgd.jpg" alt="Avengers: Doomsday" width="200">
        <h2>Avengers: Doomsday</h2>
        <a href="booking.php?movie=avgd">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/avgen.jpg" alt="Avengers: Endgame (Encore)" width="200">
        <h2>Avengers: Endgame (Encore)</h2>
        <a href="booking.php?movie=avgen">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/cf.jpg" alt="Clayface" width="200">
        <h2>Clayface</h2>
        <a href="booking.php?movie=cf">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/chkw.jpg" alt="Chiikawa the Movie: The Secret of Mermaid Island" width="200">
        <h2>Chiikawa the Movie: The Secret of Mermaid Island</h2>
        <a href="booking.php?movie=chkw">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/dconan.png" alt="Detective Conan: Fallen Angel of the Highway" width="200">
        <h2>Detective Conan: Fallen Angel of the Highway</h2>
        <a href="booking.php?movie=dconan">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/f2.jpg" alt="Fall 2: Deadpoint" width="200">
        <h2>Fall 2: Deadpoint</h2>
        <a href="booking.php?movie=f2">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/insid.jpg" alt="Insidious: Out of the Further" width="200">
        <h2>Insidious: Out of the Further</h2>
        <a href="booking.php?movie=insid">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/rsd.jpg" alt="Resident Evil" width="200">
        <h2>Resident Evil</h2>
        <a href="booking.php?movie=rsd">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/sbnd.jpg" alt="Spiderman: Brand New Day" width="200">
        <h2>Spiderman: Brand New Day</h2>
        <a href="booking.php?movie=sbnd">Book Now</a>
    </div>

    <hr>

    <div>
        <img src="assets/dnp3.jpg" alt="Dune: Part Three" width="200">
        <h2>Dune: Part Three</h2>
        <a href="booking.php?movie=dnp3">Book Now</a>
    </div>

    </div>

    <hr class="footer-divider">

    </main>


<?php render_footer(); ?>