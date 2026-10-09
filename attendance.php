<?php
require_once __DIR__ . "/auth.php";

/*
|--------------------------------------------------------------------------
| PHILIPPINE / MANILA TIMEZONE
|--------------------------------------------------------------------------
| This makes PHP use Asia/Manila time (UTC+8).
*/
date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| MYSQL TIMEZONE    
|--------------------------------------------------------------------------
| This makes CURTIME(), NOW(), etc. use Manila time for this connection.
| No database structure changes are required.
*/
$conn->query("SET time_zone = '+08:00'");

$message = "";
$type = "success";

/*
|--------------------------------------------------------------------------
| AUTOMATIC MANILA DATE
|--------------------------------------------------------------------------
| Format for database: 2026-10-09
*/
$today = date("Y-m-d");

/*
|--------------------------------------------------------------------------
| CURRENT MANILA DATE/TIME FOR DISPLAY
|--------------------------------------------------------------------------
*/
$displayDate = date("F d, Y");
$currentTime = date("h:i A");


/*
|--------------------------------------------------------------------------
| ATTENDANCE ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | TIME IN
    |--------------------------------------------------------------------------
    */
    if ($action === "in") {

        $stmt = $conn->prepare("
            INSERT INTO attendance
                (employee_id, attendance_date, time_in, status)
            VALUES
                (?, ?, CURTIME(), 'Present')
            ON DUPLICATE KEY UPDATE
                time_in = IF(time_in IS NULL, CURTIME(), time_in),
                status = IF(status = 'On Leave', 'On Leave', 'Present')
        ");

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $type = "danger";
        } else {

            $stmt->bind_param("is", $employeeId, $today);

            if ($stmt->execute()) {
                $message = "Attendance time-in recorded successfully.";
                $type = "success";
            } else {
                $message = "Unable to record time-in.";
                $type = "danger";
            }

            $stmt->close();
        }

    /*
    |--------------------------------------------------------------------------
    | TIME OUT
    |--------------------------------------------------------------------------
    */
    } elseif ($action === "out") {

        $stmt = $conn->prepare("
            UPDATE attendance
            SET
                time_out = CURTIME(),
                total_hours = IF(
                    time_in IS NULL,
                    0,
                    ROUND(
                        TIME_TO_SEC(
                            TIMEDIFF(CURTIME(), time_in)
                        ) / 3600,
                        2
                    )
                )
            WHERE
                employee_id = ?
                AND attendance_date = ?
                AND time_in IS NOT NULL
        ");

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $type = "danger";
        } else {

            $stmt->bind_param("is", $employeeId, $today);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = "Attendance time-out recorded successfully.";
                $type = "success";
            } else {
                $message = "Please time-in first before timing out.";
                $type = "warning";
            }

            $stmt->close();
        }

    } else {
        $message = "Invalid attendance action.";
        $type = "danger";
    }
}


/*
|--------------------------------------------------------------------------
| GET ATTENDANCE HISTORY
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT *
    FROM attendance
    WHERE employee_id = ?
    ORDER BY attendance_date DESC
    LIMIT 30
");

$stmt->bind_param("i", $employeeId);
$stmt->execute();

$rows = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| GET TODAY'S ATTENDANCE
|--------------------------------------------------------------------------
*/
$todayStmt = $conn->prepare("
    SELECT *
    FROM attendance
    WHERE employee_id = ?
      AND attendance_date = ?
    LIMIT 1
");

$todayStmt->bind_param("is", $employeeId, $today);
$todayStmt->execute();

$todayRow = $todayStmt->get_result()->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Attendance | WFMS Employee</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="admin-shell">

    <!-- SIDEBAR BACKDROP -->
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">

        <div class="sidebar-header">
            <a class="brand-mark" href="index.php">

                <span class="brand-icon">
                    <i class="bi bi-grid-1x2-fill"></i>
                </span>

                <span class="brand-copy">
                    <span class="brand-title">WFMS Employee</span>
                    <span class="brand-subtitle">
                        Workforce Management
                    </span>
                </span>

            </a>
        </div>

        <nav class="sidebar-nav">

            <a class="nav-link" href="index.php">
                <span class="nav-icon">
                    <i class="bi bi-speedometer2"></i>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>

            <a class="nav-link" href="timesheet.php">
                <span class="nav-icon">
                    <i class="bi bi-clock-history"></i>
                </span>
                <span class="nav-text">Time Sheet</span>
            </a>

            <a class="nav-link active" href="attendance.php">
                <span class="nav-icon">
                    <i class="bi bi-calendar-check"></i>
                </span>
                <span class="nav-text">Attendance Record</span>
            </a>
    
            <a class="nav-link" href="request.php">
                <span class="nav-icon">
                    <i class="bi bi-send"></i>
                </span>
                <span class="nav-text">Requests</span>
            </a>
             <a class="nav-link" href="payslip.php"><span class="nav-icon">
                <i class="bi bi-receipt"></i></span>
                <span class="nav-text">Payslip</span>
            </a>

            <a class="nav-link" href="profile.php">
                <span class="nav-icon">
                    <i class="bi bi-person-badge"></i>
                </span>
                <span class="nav-text">Profile</span>
            </a>

        </nav>

        <div class="sidebar-footer">
            <span class="status-dot"></span>
            <span class="sidebar-footer-text">
                System running smoothly
            </span>
        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <div class="admin-main">

        <!-- NAVBAR -->
        <nav class="navbar admin-navbar navbar-expand bg-white">

            <div class="container-fluid px-3 px-lg-4">

                <button
                    class="sidebar-toggle"
                    type="button"
                    data-sidebar-toggle
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="navbar-actions ms-auto">

                    <button
                        class="icon-button theme-toggle"
                        type="button"
                        data-theme-toggle
                    >
                        <i
                            class="bi bi-moon-stars"
                            data-theme-icon
                        ></i>
                    </button>

                    <div class="dropdown">

                        <button
                            class="profile-button dropdown-toggle"
                            data-bs-toggle="dropdown"
                        >
                            <span class="profile-name d-none d-sm-inline">
                                <?= e($fullName) ?>
                            </span>
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end">

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="profile.php"
                                >
                                    Profile
                                </a>
                            </li>

                            <li>
                                <a
                                    class="dropdown-item"
                                    href="logout.php"
                                >
                                    Sign out
                                </a>
                            </li>

                        </ul>

                    </div>

                </div>

            </div>

        </nav>


        <!-- PAGE CONTENT -->
        <main class="dashboard-content">

            <div class="container-fluid px-3 px-lg-4 py-4">

                <!-- PAGE HEADER -->
                <div class="page-heading">

                    <div>

                        <h1 class="h3 mb-1">
                            Attendance
                        </h1>

                        <p class="text-muted mb-0">
                            Record your daily attendance and view your history.
                        </p>

                    </div>

                </div>


                <!-- MESSAGE -->
                <?php if ($message): ?>

                    <div class="alert alert-<?= e($type) ?>">

                        <?= e($message) ?>

                    </div>

                <?php endif; ?>


                <!-- TODAY + BUTTONS -->
                <div class="row g-3 mb-3">

                    <!-- TODAY CARD -->
                    <div class="col-md-4">

                        <div class="panel h-100">

                            <div class="p-4">

                                <div class="text-muted small">
                                    Today
                                </div>

                                <!-- AUTOMATIC MANILA DATE -->
                                <div class="h4 mb-0">
                                    <?= e($displayDate) ?>
                                </div>

                                <div class="mt-2 text-muted">

                                    Time in:

                                    <strong>
                                        <?= e($todayRow["time_in"] ?? "—") ?>
                                    </strong>

                                </div>

                                <div class="text-muted">

                                    Time out:

                                    <strong>
                                        <?= e($todayRow["time_out"] ?? "—") ?>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- DAILY ATTENDANCE -->
                    <div class="col-md-8">

                        <div class="panel h-100">

                            <div class="p-4">

                                <h2 class="h5">
                                    Daily Attendance
                                </h2>

                                <p class="text-muted">
                                    Use the buttons below to record your attendance.
                                </p>

                                <form
                                    method="post"
                                    class="d-flex gap-2 flex-wrap"
                                >

                                    <!-- TIME IN -->
                                    <button
                                        class="btn btn-success"
                                        name="action"
                                        value="in"
                                        <?= !empty($todayRow["time_in"]) ? "disabled" : "" ?>
                                    >

                                        <i class="bi bi-box-arrow-in-right me-1"></i>

                                        Time In

                                    </button>


                                    <!-- TIME OUT -->
                                    <button
                                        class="btn btn-outline-primary"
                                        name="action"
                                        value="out"
                                        <?= empty($todayRow["time_in"]) || !empty($todayRow["time_out"]) ? "disabled" : "" ?>
                                    >

                                        <i class="bi bi-box-arrow-right me-1"></i>

                                        Time Out

                                    </button>

                                </form>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- ATTENDANCE HISTORY -->
                <div class="panel">

                    <div class="panel-header">

                        <div>

                            <h2 class="h5 mb-1">
                                Attendance History
                            </h2>

                            <p class="text-muted mb-0">
                                Your latest attendance records.
                            </p>

                        </div>

                    </div>


                    <div class="table-responsive">

                        <table class="table align-middle mb-0">

                            <thead>

                                <tr>

                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if ($rows->num_rows > 0): ?>

                                    <?php while ($r = $rows->fetch_assoc()): ?>

                                        <tr>

                                            <td>
                                                <?= e($r["attendance_date"]) ?>
                                            </td>

                                            <td>
                                                <?= e($r["time_in"] ?? "—") ?>
                                            </td>

                                            <td>
                                                <?= e($r["time_out"] ?? "—") ?>
                                            </td>

                                            <td>
                                                <?= e($r["total_hours"] ?? "0") ?>
                                                hrs
                                            </td>

                                            <td>

                                                <span
                                                    class="badge text-bg-<?=
                                                        in_array(
                                                            $r["status"],
                                                            ["Present", "Late"],
                                                            true
                                                        )
                                                        ? "success"
                                                        : (
                                                            $r["status"] === "On Leave"
                                                            ? "info"
                                                            : "secondary"
                                                        )
                                                    ?>"
                                                >

                                                    <?= e($r["status"]) ?>

                                                </span>

                                            </td>

                                        </tr>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <tr>

                                        <td
                                            colspan="5"
                                            class="text-center text-muted py-4"
                                        >
                                            No attendance records yet.
                                        </td>

                                    </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </main>

    </div>

</div>


<!-- JAVASCRIPT -->
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>

</body>

</html>