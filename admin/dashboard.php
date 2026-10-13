
<?php
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

$message = "";

// Assign a collector to a request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $request_id = filter_input(INPUT_POST, "request_id", FILTER_VALIDATE_INT);
    $collector_id = filter_input(INPUT_POST, "collector_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    
if ($request_id && $action === "assign" && $collector_id) {
    $check = $conn->prepare(
        "SELECT id FROM users WHERE id = ? AND role = 'collector'"
    );
    $check->bind_param("i", $collector_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 1) {
        $check->close();

        // Check for another pickup at the same date and time.
        $conflict = $conn->prepare(
            "SELECT id
             FROM pickup_requests
             WHERE collector_id = ?
             AND pickup_date = (
                 SELECT pickup_date
                 FROM pickup_requests
                 WHERE id = ?
             )
             AND pickup_time = (
                 SELECT pickup_time
                 FROM pickup_requests
                 WHERE id = ?
             )
             AND id != ?
             AND status IN ('Assigned', 'In Progress')"
        );

        $conflict->bind_param(
            "iiii",
            $collector_id,
            $request_id,
            $request_id,
            $request_id
        );

        $conflict->execute();
        $conflict->store_result();

        if ($conflict->num_rows > 0) {
            $message = "Scheduling conflict! This collector already has a pickup at the same date and time.";
            $conflict->close();
        } else {
            $conflict->close();

            $update = $conn->prepare(
                "UPDATE pickup_requests
                 SET collector_id = ?, status = 'Assigned'
                 WHERE id = ?
                 AND status IN ('Pending', 'Approved', 'Assigned')"
            );

            $update->bind_param("ii", $collector_id, $request_id);
            $update->execute();

            $message = $update->affected_rows > 0
                ? "Collector assigned successfully."
                : "Request not updated. Check its current status.";

            $update->close();
        }
    
    } else {
        $check->close();
        $message = "Please select a valid collector.";
    }
} elseif ($request_id && in_array($action, ["approve", "reject"], true)) {



        $new_status = $action === "approve" ? "Approved" : "Rejected";

        $update = $conn->prepare(
            "UPDATE pickup_requests
             SET status = ?
             WHERE id = ? AND status = 'Pending'"
        );
        $update->bind_param("si", $new_status, $request_id);
        $update->execute();

        $message = $update->affected_rows > 0
            ? "Request " . strtolower($new_status) . " successfully."
            : "Request not updated. It may no longer be pending.";
        $update->close();
    }
}

// Get registered collectors
$collectors = [];
$result = $conn->query(
    "SELECT id, name FROM users WHERE role = 'collector' ORDER BY name"
);

while ($row = $result->fetch_assoc()) {
    $collectors[] = $row;
}

// Get all pickup requests

$sql = "SELECT p.id, p.waste_category, p.quantity, p.pickup_date,
               p.pickup_time, p.priority, p.status,
               u.name AS hospital_name, c.name AS collector_name,
               p.collector_id,
               CASE
                   WHEN TIMESTAMP(p.pickup_date, p.pickup_time) < NOW()
                   AND p.status NOT IN ('Completed', 'Rejected')
                   THEN 1
                   ELSE 0
               END AS is_overdue
        FROM pickup_requests p
        JOIN users u ON p.hospital_id = u.id
        LEFT JOIN users c ON p.collector_id = c.id
        ORDER BY
            CASE
                WHEN p.priority = 'Urgent' THEN 0
                ELSE 1
            END,
            is_overdue DESC,
            p.id DESC";

               

$result = $conn->query($sql);
$requests = [];


while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Calculate dashboard statistics
$totalRequests = count($requests);
$pendingRequests = 0;
$inProgressRequests = 0;
$completedRequests = 0;

$overdueRequests = 0;



foreach ($requests as $r) {
    if ($r["status"] === "Pending") {
        $pendingRequests++;
    } elseif ($r["status"] === "In Progress") {
        $inProgressRequests++;
    } elseif ($r["status"] === "Completed") {
        $completedRequests++;
    }

    if ((int)$r["is_overdue"] === 1) {
        $overdueRequests++;
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
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
    <span class="navbar-brand">Medical Waste Management — Admin</span>
    <a href="../logout.php" class="btn btn-light btn-sm">Logout</a>
</nav>

<div class="container py-4">
    <h2>Admin Dashboard</h2>
    <p class="text-muted">
        Welcome, <?= htmlspecialchars($_SESSION["name"]) ?>!
    </p>
    
<?php if ($overdueRequests > 0): ?>
    <div class="alert alert-danger">
        <strong>Missed Pickup Alert!</strong>
        There are <?= $overdueRequests ?> overdue pickup requests
        that need attention.
    </div>
<?php endif; ?>

    <!-- Dashboard Statistics -->
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
                <h2><?= $pendingRequests ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">In Progress</h6>
                <h2><?= $inProgressRequests ?></h2>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card p-3">
                <h6 class="text-muted">Completed</h6>
                <h2><?= $completedRequests ?></h2>
            </div>
        </div>
        
<div class="col-md-3 col-sm-6">
    <div class="card p-3">
        <h6 class="text-muted">Overdue Pickups</h6>
        <h2 class="text-danger"><?= $overdueRequests ?></h2>
    </div>
</div>


    </div>


    <?php if ($message !== ""): ?>
        <div class="alert alert-info">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card p-4">
        <h4 class="mb-3">Manage Pickup Requests</h4>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Hospital</th>
                        <th>Waste</th>
                        <th>Quantity (kg)</th>
                        <th>Date & Time</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Collector</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><?= (int)$r["id"] ?></td>
                            <td><?= htmlspecialchars($r["hospital_name"]) ?></td>
                            <td><?= htmlspecialchars($r["waste_category"]) ?></td>
                            <td><?= htmlspecialchars((string)$r["quantity"]) ?></td>
                            <td>
                                <?= htmlspecialchars($r["pickup_date"]) ?><br>
                                <?= htmlspecialchars($r["pickup_time"]) ?>
                            </td>
                            
<td>
    <?php if ($r["priority"] === "Urgent"): ?>
        <span class="badge bg-danger">Urgent</span>
    <?php else: ?>
        <span class="badge bg-success">Normal</span>
    <?php endif; ?>
</td>

                            
<td>
    <span class="badge bg-secondary">
        <?= htmlspecialchars($r["status"]) ?>
    </span>

    <?php if ((int)$r["is_overdue"] === 1): ?>
        <br>
        <span class="badge bg-danger mt-1">Overdue</span>
    <?php endif; ?>
</td>

                            <td><?= htmlspecialchars($r["collector_name"] ?? "Not assigned") ?></td>
                            <td style="min-width: 240px;">
                                <?php if ($r["status"] === "Pending"): ?>
                                    <form method="POST" class="d-flex gap-2 mb-2">
                                        <input type="hidden" name="request_id"
                                               value="<?= (int)$r["id"] ?>">
                                        <button name="action" value="approve"
                                                class="btn btn-success btn-sm">
                                            Approve
                                        </button>
                                        <button name="action" value="reject"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Reject this request?')">
                                            Reject
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($r["status"], ["Pending", "Approved", "Assigned"], true)): ?>
                                    <form method="POST">
                                        <input type="hidden" name="request_id"
                                               value="<?= (int)$r["id"] ?>">
                                        <select name="collector_id"
                                                class="form-select form-select-sm mb-2"
                                                required>
                                            <option value="">Select collector</option>
                                            <?php foreach ($collectors as $c): ?>
                                                <option value="<?= (int)$c["id"] ?>"
                                                    <?= (int)$r["collector_id"] === (int)$c["id"] ? "selected" : "" ?>>
                                                    <?= htmlspecialchars($c["name"]) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button name="action" value="assign"
                                                class="btn btn-main btn-sm">
                                            Assign Collector
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            No pickup requests found.
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
