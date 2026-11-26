<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movie Cinema Sale</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <?php render_navbar(); ?>

    <main>

    <hr>

    <section class="poster-carousel">
        <div class="carousel-track">
            <img src="assets/avgd.jpg" alt="Avengers: Doomsday">
            <img src="assets/avgen.jpg" alt="Avengers: Endgame (Encore)">
            <img src="assets/cf.jpg" alt="Clayface">
            <img src="assets/chkw.jpg" alt="Chiikawa the Movie: The Secret of Mermaid Island">
            <img src="assets/dconan.png" alt="Detective Conan: Fallen Angel of the Highway">
            <img src="assets/f2.jpg" alt="Fall 2: Deadpoint">
            <img src="assets/insid.jpg" alt="Insidious: Out of the Further">
            <img src="assets/rsd.jpg" alt="Resident Evil">
            <img src="assets/sbnd.jpg" alt="Spiderman: Brand New Day">
            <img src="assets/dnp3.jpg" alt="Dune: Part Three">

            <img src="assets/avgd.jpg" alt="" aria-hidden="true">
            <img src="assets/avgen.jpg" alt="" aria-hidden="true">
            <img src="assets/cf.jpg" alt="" aria-hidden="true">
            <img src="assets/chkw.jpg" alt="" aria-hidden="true">
            <img src="assets/dconan.png" alt="" aria-hidden="true">
            <img src="assets/f2.jpg" alt="" aria-hidden="true">
            <img src="assets/insid.jpg" alt="" aria-hidden="true">
            <img src="assets/rsd.jpg" alt="" aria-hidden="true">
            <img src="assets/sbnd.jpg" alt="" aria-hidden="true">
            <img src="assets/dnp3.jpg" alt="" aria-hidden="true">
        </div>
    </section>

    <hr>

    <center>

        <h1>Absolute Cinema</h1>

        <h3>Watch Your Favorite Movies Now!</h3>

        <p>Enjoy the latest movies and book your seats now.</p>

        <br>

        <form action="movies.php">
            <input type="submit" value="Book Now!">
        </form>

        <br>

    </center>

    <hr>

    </main>


<?php render_footer(); ?>