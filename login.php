
<?php
session_start();
require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare(
        "SELECT id, name, email, password, role
         FROM users WHERE email = ?"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result(
            $id, $name, $userEmail, $hashedPassword, $role
        );
        $stmt->fetch();

        if (password_verify($password, $hashedPassword)) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = $id;
            $_SESSION["name"] = $name;
            $_SESSION["email"] = $userEmail;
            $_SESSION["role"] = $role;

            if ($role === "hospital") {
                header("Location: hospital/dashboard.php");
            } elseif ($role === "collector") {
                header("Location: collector/dashboard.php");
            } elseif ($role === "admin") {
                header("Location: admin/dashboard.php");
            }

            exit;
        }
    }

    $error = "Invalid email or password.";
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Medical Waste Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #eef5f6;
            font-family: Arial, sans-serif;
        }

        .login-card {
            max-width: 450px;
            margin: 70px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 18px rgba(0,0,0,.08);
        }

        .btn-main {
            background: #087f8c;
            color: white;
        }

        .btn-main:hover {
            background: #06646e;
            color: white;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="login-card">
        <h2 class="text-center mb-2">Welcome Back!</h2>
        <p class="text-center text-muted mb-4">
            Login to your account
        </p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email"
                       class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password"
                       class="form-control" required>
            </div>

            <button type="submit" class="btn btn-main w-100">
                Login
            </button>
        </form>

        <p class="text-center mt-3">
            Don't have an account?
            <a href="register.php">Register here</a>
        </p>

        <p class="text-center">
            <a href="index.php">Back to Home</a>
        </p>
    </div>
</div>

</body>
</html>
