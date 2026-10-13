
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Waste Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f8fb;
            margin: 0;
        }

        .navbar {
            background: #087f8c;
        }

        .navbar-brand, .nav-link {
            color: white !important;
        }

        .hero {
            background: linear-gradient(120deg, #087f8c, #13a89e);
            color: white;
            padding: 85px 20px;
            text-align: center;
        }

        .hero h1 {
            font-size: 42px;
            font-weight: bold;
        }

        .hero p {
            font-size: 18px;
            margin: 20px auto;
            max-width: 700px;
        }

        .feature-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            height: 100%;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
        }

        footer {
            background: #075b64;
            color: white;
            text-align: center;
            padding: 18px;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">
            Medical Waste Management
        </a>

        <button class="navbar-toggler bg-light" type="button"
                data-bs-toggle="collapse" data-bs-target="#menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="guide/guide.php">Waste Guide</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="login.php">Login</a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-light text-success fw-bold"
                       href="register.php">Register</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container">
        <h1>Medical Waste Management System</h1>
        <p>
            A simple platform for hospitals and healthcare facilities
            to request medical waste pickups, manage collections,
            and monitor pickup status.
        </p>
        <a href="register.php" class="btn btn-light btn-lg me-2">
            Get Started
        </a>
        <a href="login.php" class="btn btn-outline-light btn-lg">
            Login
        </a>
    </div>
</section>

<section class="container py-5">
    <h2 class="text-center mb-4">Our Main Features</h2>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="feature-card">
                <h4>Pickup Requests</h4>
                <p>Submit medical waste pickup requests with a preferred
                date, time, waste category, and priority.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <h4>Pickup Verification</h4>
                <p>Use OTP verification to confirm that a scheduled
                pickup has taken place.</p>
            </div>
        </div>

        <div class="col-md-4">
            <div class="feature-card">
                <h4>Smart Scheduling</h4>
                <p>Check collector schedules and identify overdue
                pickups so the admin can take action.</p>
            </div>
        </div>
    </div>
</section>

<footer>
    <p class="mb-0">
        Medical Waste Management System | College Project
    </p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
