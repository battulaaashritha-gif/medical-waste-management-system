
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "collector") {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Update pickup status

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = filter_input(
        INPUT_POST, "request_id", FILTER_VALIDATE_INT
    );
    $new_status = $_POST["status"] ?? "";

    if ($request_id && $new_status === "In Progress") {
        $stmt = $conn->prepare(
            "UPDATE pickup_requests
             SET status = 'In Progress'
             WHERE id = ? AND collector_id = ?
             AND status = 'Assigned'"
        );

        $stmt->bind_param(
            "ii", $request_id, $_SESSION["user_id"]
        );
        $stmt->execute();

        $message = $stmt->affected_rows > 0
            ? "Pickup marked In Progress."
            : "Could not update this pickup.";

        $stmt->close();

    } elseif ($request_id && $new_status === "Completed") {
        $otp = trim($_POST["otp"] ?? "");

        if (!preg_match('/^\d{6}$/', $otp)) {
            $message = "Enter a valid 6-digit OTP.";
        } else {
            $stmt = $conn->prepare(
                "SELECT otp_hash FROM pickup_requests
                 WHERE id = ? AND collector_id = ?
                 AND status = 'In Progress'
                 AND otp_verified = 0"
            );

            $stmt->bind_param(
                "ii", $request_id, $_SESSION["user_id"]
            );
            $stmt->execute();
            $stmt->store_result();
            $stmt->bind_result($otpHash);

            if (
                $stmt->num_rows === 1 &&
                $stmt->fetch() &&
                password_verify($otp, $otpHash)
            ) {
                $stmt->close();

                $update = $conn->prepare(
                    "UPDATE pickup_requests
                     SET status = 'Completed', otp_verified = 1
                     WHERE id = ? AND collector_id = ?
                     AND status = 'In Progress'
                     AND otp_verified = 0"
                );

                $update->bind_param(
                    "ii", $request_id, $_SESSION["user_id"]
                );
                $update->execute();

                $message = $update->affected_rows === 1
                    ? "OTP verified. Pickup completed successfully."
                    : "Pickup could not be completed. Please retry.";

                $update->close();
            } else {
                $stmt->close();
                $message = "Incorrect OTP or pickup is not eligible for verification.";
            }
        }
    }
}


// Fetch pickups assigned to this collector
$stmt = $conn->prepare(
    "SELECT p.id, p.waste_category, p.quantity,
            p.pickup_date, p.pickup_time, p.priority,
            p.status, u.name AS hospital_name, u.address
     FROM pickup_requests p
     JOIN users u ON p.hospital_id = u.id
     WHERE p.collector_id = ?
     ORDER BY p.pickup_date ASC, p.pickup_time ASC"
);

$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();

$stmt->bind_result(
    $request_id,
    $category,
    $quantity,
    $pickup_date,
    $pickup_time,
    $priority,
    $status,
    $hospital_name,
    $address
);

$requests = [];

 while($stmt->fetch()) {
    $requests[] = [
        "id" => $request_id,
        "category" => $category,
        "quantity" => $quantity,
        "date" => $pickup_date,
        "time" => $pickup_time,
        "priority" => $priority,
        "status" => $status,
        "hospital" => $hospital_name,
        "address" => $address
    ];
}

$stmt->close();

$totalAssigned = count($requests);
$inProgressCount = 0;
$completedCount = 0;
$upcomingCount = 0;

foreach ($requests as $r) {
    if ($r["status"] === "In Progress") {
        $inProgressCount++;
    }

    if ($r["status"] === "Completed") {
        $completedCount++;
    }

    if (
        in_array($r["status"], ["Assigned", "Approved"], true) &&
        $r["date"] >= date("Y-m-d")
    ) {
        $upcomingCount++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Collector Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <style>
        body { background: #f0f6f7; font-family: Arial, sans-serif; }
        .navbar { background: #087f8c; }
        .card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,.06);
        }
        .btn-main { background: #087f8c; color: white; }
        .btn-main:hover { background: #06646e; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark px-3 py-3">
    <span class="navbar-brand">Medical Waste Management — Collector</span>
    <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
</nav>

<div class="container py-4">
    <h2>Collector Dashboard</h2>
    <p class="text-muted">
        Welcome, <?= htmlspecialchars($_SESSION["name"]) ?>!
    </p>
    ```html
<div class="row g-3 mb-4">

    <div class="col-md-3 col-sm-6">
        <div class="card p-3">
            <h6 class="text-muted">Total Assigned</h6>
            <h2><?= $totalAssigned ?></h2>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card p-3">
            <h6 class="text-muted">In Progress</h6>
            <h2><?= $inProgressCount ?></h2>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card p-3">
            <h6 class="text-muted">Completed</h6>
            <h2><?= $completedCount ?></h2>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="card p-3">
            <h6 class="text-muted">Upcoming Pickups</h6>
            <h2><?= $upcomingCount ?></h2>
        </div>
    </div>

</div>
```

    <?php if ($message !== ""): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <h4 class="mb-3">My Pickup History</h4>
<p class="text-muted">
    View assigned, ongoing, and completed medical waste pickups.
</p>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Hospital</th>
                        <th>Address</th>
                        <th>Waste Category</th>
                        <th>Quantity (kg)</th>
                        <th>Pickup Date & Time</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= (int)$r["id"] ?></td>
                            <td><?= htmlspecialchars($r["hospital"]) ?></td>
                            <td><?= htmlspecialchars($r["address"] ?? "Not provided") ?></td>
                            <td><?= htmlspecialchars($r["category"]) ?></td>
                            <td><?= htmlspecialchars((string)$r["quantity"]) ?></td>
                            <td>
                                <?= htmlspecialchars($r["date"]) ?><br>
                                <?= htmlspecialchars($r["time"]) ?>
                            </td>
                            <td><?= htmlspecialchars($r["priority"]) ?></td>
                            
<td>
    <?php
        $status = $r["status"];

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

                            <td style="min-width: 170px;">
                                <?php if (in_array($r["status"], ["Assigned", "In Progress"], true)): ?>
                                    <form method="POST">
                                        <input type="hidden" name="request_id"
                                               value="<?= (int)$r["id"] ?>">

                                        <select name="status" class="form-select form-select-sm mb-2">
                                            <?php if ($r["status"] === "Assigned"): ?>
                                                <option value="In Progress">In Progress</option>
                                            <?php endif; ?>
                                            <option value="Completed">Completed</option>
                                        </select>
                                        
<?php if ($r["status"] === "In Progress"): ?>
    <input type="text"
           name="otp"
           class="form-control form-control-sm mb-2"
           placeholder="Enter 6-digit OTP"
           inputmode="numeric"
           pattern="[0-9]{6}"
           maxlength="6"
           required>
<?php endif; ?>


                                        <button type="submit" class="btn btn-main btn-sm">
                                            Update Status
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">No action available</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            No pickups assigned to you yet.
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
