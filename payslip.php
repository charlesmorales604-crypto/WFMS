<?php
require_once __DIR__ . "/auth.php";

$selectedId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = $conn->prepare("SELECT id, payroll_period_start, payroll_period_end, basic_pay, overtime_pay, leave_pay, holiday_pay, gross_pay, sss, pagibig, philhealth, tax, other_deductions, total_deductions, net_pay FROM payslips WHERE employee_id = ? ORDER BY payroll_period_end DESC, id DESC");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$payslips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$empStmt = $conn->prepare("SELECT employee_id FROM employees WHERE id = ?");
$empStmt->bind_param("i", $employeeId);
$empStmt->execute();
$empRow = $empStmt->get_result()->fetch_assoc();
$empStmt->close();
$employeeNumber = $empRow['employee_id'] ?? '';

$selected = $payslips[0] ?? null;
if ($selectedId > 0) {
    foreach ($payslips as $row) {
        if ((int)$row['id'] === $selectedId) {
            $selected = $row;
            break;
        }
    }
}

function money($value) { return '₱' . number_format((float)$value, 2); }
function periodText($start, $end) { return date('F j, Y', strtotime($start)) . ' – ' . date('F j, Y', strtotime($end)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="WFMS employee payslip">
  <title>Payslip | User</title>

  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">

  <style>
    @media print {
      .admin-sidebar, .admin-navbar, .no-print, .payslip-history { display:none !important; }
      .admin-main { margin:0 !important; width:100% !important; }
      .dashboard-content { padding:0 !important; }
      .panel { box-shadow:none !important; }
    }
  </style>
</head>
<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="index.php" aria-label="WFMS dashboard">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
          <span class="brand-copy">
            <span class="brand-title">WFMS Employee</span>
            <span class="brand-subtitle">Workforce Management</span>
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
        <span class="sidebar-footer-text">System running smoothly</span>
      </div>
    </aside>

    <div class="admin-main">
      <nav class="navbar admin-navbar navbar-expand bg-white">
        <div class="container-fluid px-3 px-lg-4">
          <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar">
            <span></span><span></span><span></span>
          </button>

          <div class="navbar-actions ms-auto">
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img class="avatar-img avatar-sm" src="../assets/images/avatar/avatar.jpg" alt="User">
                <span class="profile-name d-none d-sm-inline"><?php echo e($fullName ?? 'User'); ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <div>
                <h1 class="h3 mb-1">Payslip</h1>
                <p class="text-muted mb-0">View your payroll statement and salary information.</p>
              </div>
            </div>
          </div>

          <section class="row g-3 mt-1">
            <div class="col-12">
              <div class="panel">
                <div class="panel-header">
                  <div>
                    <h2 class="h5 mb-1 section-title">
                      <i class="bi bi-receipt" aria-hidden="true"></i>
                      <span>Payslip Statement</span>
                    </h2>
                    <p class="text-muted mb-0">Your payroll details, earnings, deductions, and net salary.</p>
                  </div>
                  <?php if ($selected): ?>
                    <button type="button" class="btn btn-light btn-sm no-print" onclick="window.print()">
                      <i class="bi bi-printer me-1"></i> Print Payslip
                    </button>
                  <?php endif; ?>
                </div>

                <?php if (!$selected): ?>
                  <div class="p-5 text-center">
                    <i class="bi bi-receipt fs-1 text-muted"></i>
                    <h3 class="h5 mt-3">No payslip available</h3>
                    <p class="text-muted mb-0">Your payslip will appear here once payroll has been released by the administrator.</p>
                  </div>
                <?php else: ?>
                  <div class="p-4 border-bottom">
                    <div class="row g-4">
                      <div class="col-12 col-md-4">
                        <div class="text-muted small">Employee Name</div>
                        <div class="fw-semibold mt-1"><?php echo e($fullName ?? 'User'); ?></div>
                      </div>
                      <div class="col-12 col-md-4">
                        <div class="text-muted small">Employee ID Number</div>
                        <div class="fw-semibold mt-1"><?php echo e($employeeNumber); ?></div>
                      </div>
                      <div class="col-12 col-md-4">
                        <div class="text-muted small">Payroll Period</div>
                        <div class="fw-semibold mt-1"><?php echo e(periodText($selected['payroll_period_start'], $selected['payroll_period_end'])); ?></div>
                      </div>
                    </div>
                  </div>

                  <div class="p-4 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div>
                        <h3 class="h6 mb-1">Earnings</h3>
                        <p class="text-muted small mb-0">Employee compensation for this payroll period.</p>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr><th>Description</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                          <tr><td>Basic Pay</td><td class="text-end"><?php echo money($selected['basic_pay']); ?></td></tr>
                          <tr><td>Overtime Pay</td><td class="text-end"><?php echo money($selected['overtime_pay']); ?></td></tr>
                          <tr><td>Leave Pay</td><td class="text-end"><?php echo money($selected['leave_pay']); ?></td></tr>
                          <tr><td>Holiday Pay</td><td class="text-end"><?php echo money($selected['holiday_pay']); ?></td></tr>
                          <tr><td class="fw-semibold">Gross Pay</td><td class="text-end fw-semibold"><?php echo money($selected['gross_pay']); ?></td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="p-4 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                      <div>
                        <h3 class="h6 mb-1">Deductions</h3>
                        <p class="text-muted small mb-0">Statutory and other payroll deductions.</p>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                          <tr><th>Description</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                          <tr><td>SSS</td><td class="text-end"><?php echo money($selected['sss']); ?></td></tr>
                          <tr><td>Pag-IBIG</td><td class="text-end"><?php echo money($selected['pagibig']); ?></td></tr>
                          <tr><td>PhilHealth</td><td class="text-end"><?php echo money($selected['philhealth']); ?></td></tr>
                          <tr><td>Tax</td><td class="text-end"><?php echo money($selected['tax']); ?></td></tr>
                          <tr><td>Other Deductions</td><td class="text-end"><?php echo money($selected['other_deductions']); ?></td></tr>
                          <tr><td class="fw-semibold">Total Deduction</td><td class="text-end fw-semibold"><?php echo money($selected['total_deductions']); ?></td></tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="p-4">
                    <div class="row g-4 align-items-center">
                      <div class="col-12 col-md-8">
                        <div class="text-muted small">NET SALARY</div>
                        <div class="h3 mb-0 text-success"><?php echo money($selected['net_pay']); ?></div>
                      </div>
                      <div class="col-12 col-md-4 text-md-end">
                        <span class="badge bg-success">Payroll Released</span>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </section>

          <?php if (count($payslips) > 1): ?>
          <section class="row g-3 mt-1 payslip-history no-print">
            <div class="col-12">
              <div class="panel">
                <div class="panel-header">
                  <div>
                    <h2 class="h5 mb-1 section-title"><i class="bi bi-clock-history" aria-hidden="true"></i><span>Payroll History</span></h2>
                    <p class="text-muted mb-0">Select another payroll period to view its payslip.</p>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Payroll Period</th><th>Gross Pay</th><th>Total Deduction</th><th>Net Salary</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($payslips as $row): ?>
                      <tr>
                        <td class="fw-semibold"><?php echo e(periodText($row['payroll_period_start'], $row['payroll_period_end'])); ?></td>
                        <td><?php echo money($row['gross_pay']); ?></td>
                        <td><?php echo money($row['total_deductions']); ?></td>
                        <td class="fw-semibold text-success"><?php echo money($row['net_pay']); ?></td>
                        <td class="text-end"><a class="btn btn-light btn-sm" href="payslip.php?id=<?php echo (int)$row['id']; ?>"><i class="bi bi-eye me-1"></i> View</a></td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>
          <?php endif; ?>

        </div>
      </main>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
