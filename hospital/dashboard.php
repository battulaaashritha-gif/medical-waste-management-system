
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "hospital") {
    header("Location: ../login.php");
    exit;
}

$message = "";
$error = "";
$generatedOtp = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hospital_id = $_SESSION["user_id"];
    $category = $_POST["waste_category"] ?? "";
    $quantity = $_POST["quantity"] ?? "";
    $date = $_POST["pickup_date"] ?? "";
    $time = $_POST["pickup_time"] ?? "";
    $priority = $_POST["priority"] ?? "Normal";
    $notes = trim($_POST["notes"] ?? "");

    $categories = ["Yellow", "Red", "White", "Blue"];
    $priorities = ["Normal", "Urgent"];

    if (
        !in_array($category, $categories, true) ||
        !in_array($priority, $priorities, true) ||
        !is_numeric($quantity) ||
        (float)$quantity <= 0 ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
        !preg_match('/^\d{2}:\d{2}$/', $time) ||
        $date < date("Y-m-d")
    ) {
        $error = "Please enter valid pickup details. The date cannot be in the past.";
    } else {
        $otp = (string) random_int(100000, 999999);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO pickup_requests
            (hospital_id, waste_category, quantity, pickup_date,
             pickup_time, priority, notes, status, otp_hash, otp_verified)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, 0)"
        );

        $stmt->bind_param(
            "isdsssss",
            $hospital_id,
            $category,
            $quantity,
            $date,
            $time,
            $priority,
            $notes,
            $otpHash
        );

        if ($stmt->execute()) {
            $message = "Pickup request submitted successfully!";
            $generatedOtp = $otp;
        } else {
            $error = "Could not submit the request. Please try again.";
        }

        $stmt->close();
    }
}



$stmt = $conn->prepare(
    "SELECT p.id, p.waste_category, p.quantity, p.pickup_date,
            p.pickup_time, p.priority, p.status,
            u.name AS collector_name, u.phone AS collector_phone
     FROM pickup_requests p
     LEFT JOIN users u ON p.collector_id = u.id
     WHERE p.hospital_id = ?
     ORDER BY p.id DESC"
);

$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();

$stmt->bind_result(
    $request_id,
    $waste_category,
    $quantity,
    $pickup_date,
    $pickup_time,
    $priority,
    $status,
    $collector_name,
    $collector_phone
);

$requests = [];

while ($stmt->fetch()) {
    $requests[] = [
        "id" => $request_id,
        "category" => $waste_category,
        "quantity" => $quantity,
        "date" => $pickup_date,
        "time" => $pickup_time,
        "priority" => $priority,
        "status" => $status,
        "collector_name" => $collector_name,
        "collector_phone" => $collector_phone
    ];
}


$stmt->close();

// Calculate hospital dashboard statistics
$totalRequests = count($requests);
$pendingRequests = 0;
$inProgressRequests = 0;
$completedRequests = 0;

foreach ($requests as $request) {
    if ($request["status"] === "Pending") {
        $pendingRequests++;
    } elseif ($request["status"] === "In Progress") {
        $inProgressRequests++;
    } elseif ($request["status"] === "Completed") {
        $completedRequests++;
    }
}

$categoryCounts = [
    "Yellow" => 0,
    "Red" => 0,
    "White" => 0,
    "Blue" => 0
];

foreach ($requests as $request) {
    $category = $request["category"];

    if (isset($categoryCounts[$category])) {
        $categoryCounts[$category]++;
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body {
            background: #f0f6f7;
            font-family: Arial, sans-serif;
        }
        .navbar {
            background: #087f8c;
        }
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
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

<nav class="navbar navbar-dark px-3 py-3">
    <span class="navbar-brand">Medical Waste Management</span>
    <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
</nav>



<div class="container py-4">
    
    <!-- Hospital Dashboard Statistics -->
    <div class="row g-3 mb-4">

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">Total Requests</h6>
                <h2><?= $totalRequests ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">Pending Requests</h6>
                <h2 class="text-warning"><?= $pendingRequests ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">In Progress</h6>
                <h2 class="text-primary"><?= $inProgressRequests ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">Completed</h6>
                <h2 class="text-success"><?= $completedRequests ?></h2>
            </div>
        </div>

    </div>
    
<!-- Waste Category Summary -->
<div class="card p-4 mb-4">
    <h4 class="mb-3">Waste Category Summary</h4>

    <div class="row g-3">
        <div class="col-md-3 col-sm-6">
            <div class="border rounded p-3">
                <h6>Yellow Waste</h6>
                <h3><?= $categoryCounts["Yellow"] ?></h3>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="border rounded p-3">
                <h6>Red Waste</h6>
                <h3><?= $categoryCounts["Red"] ?></h3>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="border rounded p-3">
                <h6>White Waste</h6>
                <h3><?= $categoryCounts["White"] ?></h3>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="border rounded p-3">
                <h6>Blue Waste</h6>
                <h3><?= $categoryCounts["Blue"] ?></h3>
            </div>
        </div>
    </div>
</div>


    <h2>Hospital Dashboard</h2>
    <p class="text-muted">
        Welcome, <?= htmlspecialchars($_SESSION["name"]) ?>!
    </p>

    <?php if ($message !== ""): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($message) ?>

            <?php if ($generatedOtp !== ""): ?>
                <hr>
                <strong>Demo Pickup OTP:</strong>
                <span class="fs-4">
                    <?= htmlspecialchars($generatedOtp) ?>
                </span>
                <p class="mb-0 mt-2">
                    Share this OTP with the collector when verifying pickup.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card p-4 mb-4">
        <h4 class="mb-3">Request Waste Pickup</h4>

        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Waste Category</label>
                    <select name="waste_category" class="form-select" required>
                        <option value="">Select category</option>
                        <option value="Yellow">Yellow</option>
                        <option value="Red">Red</option>
                        <option value="White">White</option>
                        <option value="Blue">Blue</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Quantity (kg)</label>
                    <input type="number" name="quantity"
                           class="form-control" min="0.01"
                           step="0.01" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Pickup Date</label>
                    <input type="date" name="pickup_date"
                           class="form-control"
                           min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Pickup Time</label>
                    <input type="time" name="pickup_time"
                           class="form-control" required>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Additional Notes</label>
                    <input type="text" name="notes"
                           class="form-control"
                           placeholder="Optional notes">
                </div>
            </div>

            <button type="submit" class="btn btn-main">
                Submit Pickup Request
            </button>
        </form>
    </div>

    <div class="card p-4">
       <h4 class="mb-3">Pickup History</h4>
<p class="text-muted">
    Track your submitted requests and check their current status.
</p>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Quantity (kg)</th>
                        <th>Date</th>
                        <th>Time</th>
                        
                        <th>Priority</th>
                        <th>Collector Name</th>
                        <th>Collector Phone</th>
                        <th>Status</th>

                    </tr>
                </thead>
                <tbody>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?= (int)$request["id"] ?></td>
                            <td><?= htmlspecialchars($request["category"]) ?></td>
                            <td><?= htmlspecialchars((string)$request["quantity"]) ?></td>
                            <td><?= htmlspecialchars($request["date"]) ?></td>
                            <td><?= htmlspecialchars($request["time"]) ?></td>
                            

<td>
    <?php if ($request["priority"] === "Urgent"): ?>
        <span class="badge text-bg-danger">Urgent</span>
    <?php else: ?>
        <span class="badge text-bg-success">Normal</span>
    <?php endif; ?>
</td>


<td>
    <?= htmlspecialchars($request["collector_name"] ?? "Not assigned") ?>
</td>

<td>
    <?= htmlspecialchars($request["collector_phone"] ?? "Not available") ?>
</td>


<td>
    <?php
        $status = $request["status"];

        $statusColors = [
            "Pending" => "text-bg-warning",
            "Approved" => "text-bg-info",
            "Assigned" => "text-bg-primary",
            "In Progress" => "text-bg-dark",
            "Completed" => "text-bg-success",
            "Rejected" => "text-bg-danger"
        ];

        $badgeColor = $statusColors[$status] ?? "text-bg-secondary";
    ?>

    <span class="badge <?= $badgeColor ?>">
        <?= htmlspecialchars($status) ?>
    </span>
</td>


                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            No pickup requests yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
