<?php
require_once("db.php");
require_once __DIR__.'/check_auth.php';

if(isset($_POST['name']) && isset($_POST['email'])) {
    $name = htmlspecialchars($_POST['name'] ?? '');
    $email = htmlspecialchars($_POST['email'] ?? '');

    $errors = [];

    if (empty($name) || empty($email)) {
        $errors[] = 'Email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (empty($errors)) {
        $query = "INSERT INTO client_requests (name, email) VALUES ('$name', '$email')";
        mysqli_query($conn, $query);
        header('Location: index.php?application=1#join');
        exit;

    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epoka Clinic</title>
    <link rel="stylesheet" href="style/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" href="logo1.png.jpeg" type="image/x-icon" style="border-radius: 10px ;">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQN+PtmoHDEXuppvnDJzQIu9" crossorigin="anonymous">
</head>
<body>

    <!--Navbar Section-->
    <nav class="navbar navbar-light bg-darker navbar-expand-md" >
         <a href="index.php" class="navbar__logo">Epoka Clinic</a>
        <div class="navbar__toggle" id="mobile-menu">
            <span class="bar"></span> <span class="bar"></span>
            <span class="bar"></span>
        </div>
        <div class="navbar__menu navbar-collapse justify-content-end align-center" id="main-nav">
            <a href="index.php" class="navbar__link" >Home</a>
            <a href="contactUs.php" class="navbar__link">Contact Us</a>
            <a href="login.php" class="navbar__link"><button class="button btn-danger btn-lg ">Login</button></a>
        </div> 
    </nav>

    <!--Hero Section-->
    <div class="hero">
        <div class="hero__content">
            <h1 class="animate-hero fw-bold">Epoka Clinic</h1>
        </div>
    </div>

    <!--Service section-->
    <div id="services" class="services">
        <div class="services__container">
            <div>
                <p class="topline animate-services">Features</p>
                <h1 class="services__heading animate-services fw-bold">What we offer</h1>
                <div class="services__features">
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        Veneers</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        Root Canal Extraction</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        Dental Implants</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        Dental Filling</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        General Dentistry</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        +Many more of the services offered to you...</p>
                    <p class="services__feature animate-services">
                        <i class="fa-solid fa-circle-check"></i>
                        Open 6 days a week </p>
                </div>
            </div>
            <div>
                <img src="style/images/services.jpg" alt="Clinic" class="services__img animate-img">
            </div>
        </div>
    </div>

        <!-- Team Section -->
    <div id="team" class="team">
        <div class="team__text animate-team">
            <h1 class="fw-bold">Meet our Staff</h1>
            <p class="team__desc">
                Meet our staff who have over 50 years of experience combined.
                Each dentist specializes in unique services adjustable to your benefit.
            </p>
        </div>
        <div class="team__wrapper">
            <div class="team__card animate-team fw-semibold">
                <img src="style/images/house.jpg" alt="person" class="team__img">
                <p>Goni</p>
            </div>
            <div class="team__card animate-team fw-semibold">
                <img src="style/images/Gloria.jpg" alt="person" class="team__img">
                <p>Gloria</p>
            </div>
            <div class="team__card animate-team fw-semibold">
                <img src="style/images/Boel.jpg" alt="person" class="team__img">
                <p>Ardjan</p>
            </div>
            <div class="team__card animate-team fw-semibold">
                <img src="style/images/Manjola.jpg" alt="person" class="team__img">
                <p>Manjola</p>
            </div>
        </div>
    </div>
     
    <!--Contact Us-->
    <section class="contact" id="join">
        <div class="content ">
            <h2 class="animate-marova">Join Us </h2>
            <h3 class="animate-marova"> Request an account in our system</h3>
        </div>
        <div class="container">
            <div class="contactInfo">
                <div class="box animate-add">
                    <div class="icon"><i class="fa-solid fa-location-dot"></i></div>
                    <div class="text">
                        <h3 class="fw-bold">Address</h3>
                        <a href="https://www.google.com/maps/place/Muscle+%26+Fitness/@41.3357169,19.8186325,17z/data=!3m1!4b1!4m6!3m5!1s0x1350310bb7396c69:0xc7fee7b16294a06!8m2!3d41.3357129!4d19.8212074!16s%2Fg%2F11nmqdm94y?entry=ttu" target="_blank">Rruga Siri Kodra,Tirana 1017,Albania</a>
                    </div>
                </div>
            <div class="box animate-add">
                <div class="icon"><i class="fa-solid fa-phone"></i></div>
                <div class="text ">
                        <h3 class="fw-bold">Phone</h3>
                        <a href="tel:0685778875" target="_blank">+355 068 577 8876</a>
                    </div>
                </div>
            <div class="box animate-add">
                <div class="icon"><i class="fa-solid fa-envelope"></i></div>
                <div class="text">
                        <h3 class="fw-bold">Email</h3>
                        <a href="mailto:klinika.dentare311@gmail.com" target="_blank">klinika.dentare311@gmail.com</a>
                    </div>
                    </div>
                </div>

                <div class="contactForm animate-box">
                    <form action="#join" method="post" class="animate-fjal">
                        <h2 class="fw-bold">Request Account</h2>
                        <div class="inputBox">
                            <input type="text" name="name" required="required">
                            <span>Full Name</span>
                        </div>
                        <div class="inputBox">
                            <input type="text" name="email" required="required">
                            <span>Email</span>
                        </div>
                        <div class="inputBox">
                            <button class="button btn-danger fw-bold" type="submit" fdprocessedid="ypirrj">Submit</button>
                        </div>
                    </form>
                    <br>
                    <?php if (isset($_GET['application'])): ?>
                        <br>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <h4>Submitted!</h4>
                                <p>Your application will be reviewed shortly.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <!-- Footer Section -->
    <footer class="footer">
        <div class="footer__container">
            <div class="footer__desc">
                <h1>Epoka Clinic</h1>
                <p>Dental Clinic located in Siri Kodra street</p>
            </div>

            <div class="footer__group">
                <div class="footer__links">
                    <h2 class="footer__title">Contact Us</h2>
                    <a href="contactUs.php" class="footer__link">Contact</a>
                    <a href="contactUs.php" class="footer__link">Support</a>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="style/jsClinic.js"></script>
</body>
</html>
