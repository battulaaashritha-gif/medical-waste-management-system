
<?php
session_start();
require_once "config/db.php";

$message = "";
$messageType = "danger";

$name = "";
$email = "";
$phone = "";
$facility = "";
$address = "";
$role = "hospital";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $facility = trim($_POST["facility_name"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $role = $_POST["role"] ?? "hospital";
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (
        $name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        $phone === "" || $password === "" ||
        !in_array($role, ["hospital", "collector"], true)
    ) {
        $message = "Please fill all required fields correctly.";
    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "This email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, password, role, phone, facility_name, address)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "sssssss",
                $name,
                $email,
                $hashedPassword,
                $role,
                $phone,
                $facility,
                $address
            );

            if ($stmt->execute()) {
                $messageType = "success";
                $message = "Registration successful! You can now log in.";
                $name = $email = $phone = $facility = $address = "";
                $role = "hospital";
            } else {
                $message = "Registration failed. Please try again.";
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Medical Waste Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body {
            background: #eef5f6;
            font-family: Arial, sans-serif;
        }
        .register-card {
            max-width: 650px;
            margin: 40px auto;
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
    <div class="register-card">
        <h2 class="text-center mb-2">Create an Account</h2>
        <p class="text-center text-muted mb-4">
            Medical Waste Management System
        </p>

        <?php if ($message !== ""): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control"
                       value="<?php echo htmlspecialchars($name); ?>"
                       maxlength="100" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>"
                       maxlength="150" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($phone); ?>"
                       maxlength="20" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Account Type *</label>
                <select name="role" class="form-select" required>
                    <option value="hospital"
                        <?php echo $role === "hospital" ? "selected" : ""; ?>>
                        Hospital / Healthcare Facility
                    </option>
                    <option value="collector"
                        <?php echo $role === "collector" ? "selected" : ""; ?>>
                        Waste Collector
                    </option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    Hospital / Facility Name (if applicable)
                </label>
                <input type="text" name="facility_name" class="form-control"
                       value="<?php echo htmlspecialchars($facility); ?>"
                       maxlength="150">
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control"
                          rows="2"><?php echo htmlspecialchars($address); ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Password * (minimum 8 characters)</label>
                <input type="password" name="password" class="form-control"
                       minlength="8" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password *</label>
                <input type="password" name="confirm_password"
                       class="form-control" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-main w-100">
                Register
            </button>
        </form>

        <p class="text-center mt-3 mb-0">
            Already have an account?
            <a href="login.php">Login here</a>
        </p>

        <p class="text-center mt-3">
            <a href="index.php">Back to Home</a>
        </p>
    </div>
</div>

</body>
</html>
