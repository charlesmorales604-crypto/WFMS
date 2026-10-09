<?php
require_once __DIR__ . "/auth.php";

$message = ""; $type = "success";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "shift") {
        $current = $_POST["current_shift"] ?? "";
        $requested = $_POST["requested_shift"] ?? "";
        $currentSchedule = trim($_POST["current_schedule"] ?? "Monday - Friday");
        $requestedSchedule = trim($_POST["requested_schedule"] ?? "");
        $start = $_POST["start_date"] ?? "";
        $until = $_POST["until_date"] !== "" ? ($_POST["until_date"] ?? null) : null;
        $reason = trim($_POST["reason"] ?? "");
        if (!in_array($current, ["morning","afternoon","night"], true) || !in_array($requested, ["morning","afternoon","night"], true) || !$requestedSchedule || !$start) {
            $message="Please complete the shift request."; $type="danger";
        } else {
            $stmt=$conn->prepare("INSERT INTO shift_requests (employee_id,current_shift,requested_shift,current_schedule,requested_schedule,start_date,until_date,reason) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss",$employeeId,$current,$requested,$currentSchedule,$requestedSchedule,$start,$until,$reason);
            if($stmt->execute()) $message="Shift/schedule request submitted for admin approval."; else {$message="Unable to submit request."; $type="danger";}
        }
    } elseif ($action === "leave") {
        $leave = $_POST["leave_type"] ?? ""; $duration=$_POST["leave_duration"] ?? "";
        $from=$_POST["date_from"] ?? ""; $until=$_POST["date_until"] ?? ""; $reason=trim($_POST["leave_reason"] ?? "");
        if(!in_array($leave,["vacation","sick","emergency","personal","other"],true)||!in_array($duration,["whole-day","half-day"],true)||!$from||!$until){
            $message="Please complete the leave request."; $type="danger";
        } else {
            $stmt=$conn->prepare("INSERT INTO leave_requests (employee_id,leave_type,leave_duration,date_from,date_until,reason) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss",$employeeId,$leave,$duration,$from,$until,$reason);
            if($stmt->execute()) $message="Leave request submitted for admin approval."; else {$message="Unable to submit request."; $type="danger";}
        }
    }
}
$sh=$conn->prepare("SELECT id,current_shift,requested_shift,requested_schedule,start_date,until_date,status,created_at FROM shift_requests WHERE employee_id=? ORDER BY created_at DESC");
$sh->bind_param("i",$employeeId); $sh->execute(); $shiftRequests=$sh->get_result();
$lv=$conn->prepare("SELECT id,leave_type,leave_duration,date_from,date_until,reason,status,created_at FROM leave_requests WHERE employee_id=? ORDER BY created_at DESC");
$lv->bind_param("i",$employeeId); $lv->execute(); $leaveRequests=$lv->get_result();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Requests | WFMS Employee</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css"><link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css"><link rel="stylesheet" href="../assets/css/style.css">
</head><body>
<div class="admin-shell"><div class="sidebar-backdrop" data-sidebar-close></div>
<aside class="admin-sidebar" id="adminSidebar"><div class="sidebar-header"><a class="brand-mark" href="index.php"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-copy"><span class="brand-title">WFMS Employee</span><span class="brand-subtitle">Workforce Management</span></span></a></div>
<nav class="sidebar-nav">
<a class="nav-link" href="index.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
<a class="nav-link" href="timesheet.php"><span class="nav-icon"><i class="bi bi-clock-history"></i></span><span class="nav-text">Time Sheet</span></a><a class="nav-link" href="attendance.php"><span class="nav-icon"><i class="bi bi-calendar-check"></i></span><span class="nav-text">Attendance Record</span></a>
<a class="nav-link active" href="request.php"><span class="nav-icon"><i class="bi bi-send"></i></span><span class="nav-text">Requests</span></a>
<a class="nav-link" href="payslip.php"><span class="nav-icon"><i class="bi bi-receipt"></i></span><span class="nav-text">Payslip</span></a><a class="nav-link" href="profile.php"><span class="nav-icon"><i class="bi bi-person-badge"></i></span><span class="nav-text">Profile</span></a>
</nav><div class="sidebar-footer"><span class="status-dot"></span><span class="sidebar-footer-text">System running smoothly</span></div></aside>
<div class="admin-main"><nav class="navbar admin-navbar navbar-expand bg-white"><div class="container-fluid px-3 px-lg-4"><button class="sidebar-toggle" type="button" data-sidebar-toggle><span></span><span></span><span></span></button><div class="navbar-actions ms-auto"><button class="icon-button theme-toggle" type="button" data-theme-toggle><i class="bi bi-moon-stars" data-theme-icon></i></button><div class="dropdown"><button class="profile-button dropdown-toggle" data-bs-toggle="dropdown"><span class="profile-name d-none d-sm-inline"><?=e($fullName)?></span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php">Profile</a></li><li><a class="dropdown-item" href="logout.php">Sign out</a></li></ul></div></div></div></nav>
<main class="dashboard-content"><div class="container-fluid px-3 px-lg-4 py-4"><div class="page-heading"><div><h1 class="h3 mb-1">Requests</h1><p class="text-muted mb-0">Request a shift change or leave. Admin approval is required.</p></div></div>
<?php if($message):?><div class="alert alert-<?=$type?>"><?=$message?></div><?php endif;?>
<div class="row g-3">
<div class="col-12 col-xl-6"><div class="panel h-100"><div class="panel-header"><div><h2 class="h5 mb-1"><i class="bi bi-clock"></i> Shift & Schedule</h2><p class="text-muted mb-0">Submit a schedule change request.</p></div></div><div class="p-4"><form method="post"><input type="hidden" name="action" value="shift"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Current Shift</label><select class="form-select" name="current_shift" required><option value="">Select</option><option value="morning">Morning — 8:00 AM - 5:00 PM</option><option value="afternoon">Afternoon — 1:00 PM - 10:00 PM</option><option value="night">Night — 10:00 PM - 7:00 AM</option></select></div>
<div class="col-md-6"><label class="form-label">Requested Shift</label><select class="form-select" name="requested_shift" required><option value="">Select</option><option value="morning">Morning — 8:00 AM - 5:00 PM</option><option value="afternoon">Afternoon — 1:00 PM - 10:00 PM</option><option value="night">Night — 10:00 PM - 7:00 AM</option></select></div>
<div class="col-md-6"><label class="form-label">Current Schedule</label><input class="form-control" name="current_schedule" value="Monday - Friday"></div>
<div class="col-md-6"><label class="form-label">Requested Schedule</label><input class="form-control" name="requested_schedule" placeholder="e.g. Tuesday - Saturday" required></div>
<div class="col-md-6"><label class="form-label">Start Date</label><input class="form-control" type="date" name="start_date" required></div>
<div class="col-md-6"><label class="form-label">Until Date <span class="text-muted">(optional)</span></label><input class="form-control" type="date" name="until_date"></div>
<div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="reason" rows="3" placeholder="Explain your request"></textarea></div>
<div class="col-12"><button class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit Shift Request</button></div>
</div></form></div></div></div>
<div class="col-12 col-xl-6"><div class="panel h-100"><div class="panel-header"><div><h2 class="h5 mb-1"><i class="bi bi-calendar2-x"></i> Leave Request</h2><p class="text-muted mb-0">Submit leave for admin approval.</p></div></div><div class="p-4"><form method="post"><input type="hidden" name="action" value="leave"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Leave Type</label><select class="form-select" name="leave_type" required><option value="">Select</option><option value="vacation">Vacation</option><option value="sick">Sick</option><option value="emergency">Emergency</option><option value="personal">Personal</option><option value="other">Other</option></select></div>
<div class="col-md-6"><label class="form-label">Duration</label><select class="form-select" name="leave_duration" required><option value="whole-day">Whole Day</option><option value="half-day">Half Day</option></select></div>
<div class="col-md-6"><label class="form-label">Date From</label><input class="form-control" type="date" name="date_from" required></div>
<div class="col-md-6"><label class="form-label">Date Until</label><input class="form-control" type="date" name="date_until" required></div>
<div class="col-12"><label class="form-label">Reason</label><textarea class="form-control" name="leave_reason" rows="3" placeholder="Explain your leave request"></textarea></div>
<div class="col-12"><button class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit Leave Request</button></div>
</div></form></div></div></div>
</div>
<div class="row g-3 mt-1"><div class="col-12"><div class="panel"><div class="panel-header"><div><h2 class="h5 mb-1">My Request History</h2><p class="text-muted mb-0">Track the status of your submitted requests.</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Type</th><th>Details</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php while($r=$shiftRequests->fetch_assoc()):?><tr><td><span class="badge text-bg-light">Shift</span></td><td><?=e(ucfirst($r["current_shift"]))?> → <?=e(ucfirst($r["requested_shift"]))?><br><small class="text-muted"><?=e($r["requested_schedule"])?></small></td><td><?=e($r["created_at"])?></td><td><span class="badge text-bg-<?= $r["status"]==="Approved"?"success":($r["status"]==="Rejected"?"danger":"warning")?>"><?=e($r["status"])?></span></td></tr><?php endwhile;?>
<?php while($r=$leaveRequests->fetch_assoc()):?><tr><td><span class="badge text-bg-light">Leave</span></td><td><?=e(ucfirst($r["leave_type"]))?> · <?=e($r["date_from"])?> to <?=e($r["date_until"])?></td><td><?=e($r["created_at"])?></td><td><span class="badge text-bg-<?= $r["status"]==="Approved"?"success":($r["status"]==="Rejected"?"danger":"warning")?>"><?=e($r["status"])?></span></td></tr><?php endwhile;?>
</tbody></table></div></div></div></div>
</div></main></div></div>
<script src="../assets/js/bootstrap.bundle.min.js"></script><script src="../assets/js/main.js"></script></body></html>