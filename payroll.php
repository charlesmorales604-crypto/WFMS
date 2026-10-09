<?php
require_once __DIR__ . '/auth.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Payslip table
|--------------------------------------------------------------------------
| This keeps the current project compatible even if the payslip table
| was not included in the original database import.
*/
$conn->query("CREATE TABLE IF NOT EXISTS payslips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    payroll_period_start DATE NOT NULL,
    payroll_period_end DATE NOT NULL,
    basic_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    overtime_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    leave_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    holiday_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gross_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sss DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pagibig DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    philhealth DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    other_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    net_pay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payslip_employee_period (employee_id, payroll_period_start, payroll_period_end)
) ENGINE=InnoDB");

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function money($v) {
    return '₱' . number_format((float)$v, 2);
}

function post_num($key) {
    return isset($_POST[$key]) && $_POST[$key] !== ''
        ? (float)$_POST[$key]
        : 0.0;
}

/*
 * Detect the salary column used by the current employees table.
 * The uploaded payroll page expects employees.id/full_name/position.
 */
function employeeSalaryColumn(mysqli $conn) {
    static $column = null;
    if ($column !== null) return $column;

    $candidates = ['basic_salary', 'basic_pay', 'salary', 'monthly_salary'];
    $result = $conn->query("SHOW COLUMNS FROM employees");

    if ($result) {
        $available = [];
        while ($row = $result->fetch_assoc()) {
            $available[] = $row['Field'];
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                $column = $candidate;
                return $column;
            }
        }
    }

    $column = null;
    return null;
}

/*
|--------------------------------------------------------------------------
| Calculate attendance-based payroll
|--------------------------------------------------------------------------
| Assumptions used by this project:
| - 22 working days/month
| - 8 regular hours/day
| - overtime is paid at 125% of the hourly rate
| - regular pay is based on completed attendance hours
|
| Leave/holiday pay and deductions remain editable because the current
| database does not contain enough information to safely infer those
| amounts from attendance alone.
|--------------------------------------------------------------------------
*/
function calculatePayrollFromAttendance(mysqli $conn, int $employeeId, string $start, string $end) {
    $result = [
        'ok' => false,
        'basic_pay' => 0.00,
        'overtime_pay' => 0.00,
        'regular_hours' => 0.00,
        'overtime_hours' => 0.00,
        'monthly_salary' => 0.00,
        'hourly_rate' => 0.00,
        'message' => ''
    ];

    if ($employeeId <= 0 || !$start || !$end || $start > $end) {
        $result['message'] = 'Invalid employee or payroll period.';
        return $result;
    }

    $salaryColumn = employeeSalaryColumn($conn);
    if (!$salaryColumn) {
        $result['message'] = 'No salary column was found in the employees table.';
        return $result;
    }

    $salaryColumnSafe = '`' . str_replace('`', '``', $salaryColumn) . '`';

    $employeeStmt = $conn->prepare(
        "SELECT COALESCE($salaryColumnSafe, 0) AS monthly_salary
         FROM employees
         WHERE id = ?
         LIMIT 1"
    );

    if (!$employeeStmt) {
        $result['message'] = 'Unable to read employee salary: ' . $conn->error;
        return $result;
    }

    $employeeStmt->bind_param('i', $employeeId);
    $employeeStmt->execute();
    $employeeRow = $employeeStmt->get_result()->fetch_assoc();
    $employeeStmt->close();

    if (!$employeeRow) {
        $result['message'] = 'Employee not found.';
        return $result;
    }

    $monthlySalary = (float)$employeeRow['monthly_salary'];

    /*
     * time_in/time_out are treated as DATETIME values.
     * The regular portion is capped at 8 hours per attendance day.
     * Anything beyond 8 hours becomes overtime.
     */
    $attendanceStmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(
                LEAST(
                    8,
                    GREATEST(
                        0,
                        TIMESTAMPDIFF(SECOND, time_in, time_out) / 3600
                    )
                )
            ), 0) AS regular_hours,
            COALESCE(SUM(
                GREATEST(
                    0,
                    (TIMESTAMPDIFF(SECOND, time_in, time_out) / 3600) - 8
                )
            ), 0) AS overtime_hours
         FROM attendance
         WHERE employee_id = ?
           AND attendance_date BETWEEN ? AND ?
           AND time_in IS NOT NULL
           AND time_out IS NOT NULL"
    );

    if (!$attendanceStmt) {
        $result['message'] = 'Unable to read attendance: ' . $conn->error;
        return $result;
    }

    $attendanceStmt->bind_param('iss', $employeeId, $start, $end);
    $attendanceStmt->execute();
    $attendanceRow = $attendanceStmt->get_result()->fetch_assoc();
    $attendanceStmt->close();

    $regularHours = round((float)($attendanceRow['regular_hours'] ?? 0), 2);
    $overtimeHours = round((float)($attendanceRow['overtime_hours'] ?? 0), 2);

    /*
     * Monthly salary -> daily rate -> hourly rate.
     * This gives a straightforward attendance-based computation.
     */
    $dailyRate = $monthlySalary / 22;
    $hourlyRate = $dailyRate / 8;

    $basicPay = $regularHours * $hourlyRate;
    $overtimePay = $overtimeHours * $hourlyRate * 1.25;

    $result['ok'] = true;
    $result['basic_pay'] = round($basicPay, 2);
    $result['overtime_pay'] = round($overtimePay, 2);
    $result['regular_hours'] = $regularHours;
    $result['overtime_hours'] = $overtimeHours;
    $result['monthly_salary'] = round($monthlySalary, 2);
    $result['hourly_rate'] = round($hourlyRate, 2);

    if ($regularHours <= 0 && $overtimeHours <= 0) {
        $result['message'] = 'No completed Time In/Time Out records were found for this period yet.';
    }

    return $result;
}

/*
|--------------------------------------------------------------------------
| AJAX: calculate payroll from attendance
|--------------------------------------------------------------------------
| The admin page calls this automatically when the employee/period changes
| and periodically while the page is open.
*/
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'calculate') {
    header('Content-Type: application/json; charset=utf-8');

    $employeeId = (int)($_GET['employee_id'] ?? 0);
    $start = $_GET['start'] ?? '';
    $end = $_GET['end'] ?? '';

    echo json_encode(
        calculatePayrollFromAttendance($conn, $employeeId, $start, $end)
    );
    exit;
}

$errors = [];
$success = '';
$editing = null;

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | SAVE / RELEASE PAYSLIP
    |--------------------------------------------------------------------------
    | Basic pay and overtime pay are recalculated from attendance here,
    | so the saved values cannot become stale after the employee clocks out.
    */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $start = $_POST['payroll_period_start'] ?? '';
        $end = $_POST['payroll_period_end'] ?? '';

        // These remain manual because the current schema does not safely
        // provide enough data to infer their amounts.
        $leave = post_num('leave_pay');
        $holiday = post_num('holiday_pay');
        $sss = post_num('sss');
        $pagibig = post_num('pagibig');
        $philhealth = post_num('philhealth');
        $tax = post_num('tax');
        $other = post_num('other_deductions');

        if ($employeeId <= 0) $errors[] = 'Please select an employee.';
        if (!$start || !$end) $errors[] = 'Please enter the payroll period.';
        if ($start && $end && $start > $end) {
            $errors[] = 'Payroll period end must be on or after the start date.';
        }

        foreach ([$leave,$holiday,$sss,$pagibig,$philhealth,$tax,$other] as $n) {
            if ($n < 0) {
                $errors[] = 'Payroll amounts cannot be negative.';
                break;
            }
        }

        $calculated = null;

        if (!$errors) {
            $calculated = calculatePayrollFromAttendance(
                $conn,
                $employeeId,
                $start,
                $end
            );

            if (!$calculated['ok']) {
                $errors[] = $calculated['message'] ?: 'Unable to calculate attendance payroll.';
            }
        }

        $basic = (float)($calculated['basic_pay'] ?? 0);
        $overtime = (float)($calculated['overtime_pay'] ?? 0);

        $gross = $basic + $overtime + $leave + $holiday;
        $total = $sss + $pagibig + $philhealth + $tax + $other;
        $net = $gross - $total;

        if (!$errors) {
            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE payslips
                     SET employee_id=?,
                         payroll_period_start=?,
                         payroll_period_end=?,
                         basic_pay=?,
                         overtime_pay=?,
                         leave_pay=?,
                         holiday_pay=?,
                         gross_pay=?,
                         sss=?,
                         pagibig=?,
                         philhealth=?,
                         tax=?,
                         other_deductions=?,
                         total_deductions=?,
                         net_pay=?
                     WHERE id=?"
                );

                $stmt->bind_param(
                    'issddddddddddddi',
                    $employeeId,
                    $start,
                    $end,
                    $basic,
                    $overtime,
                    $leave,
                    $holiday,
                    $gross,
                    $sss,
                    $pagibig,
                    $philhealth,
                    $tax,
                    $other,
                    $total,
                    $net,
                    $id
                );

                if ($stmt->execute()) {
                    $success = 'Payroll record updated using the latest attendance.';
                } else {
                    $errors[] = 'Unable to update payroll record: ' . $stmt->error;
                }

                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO payslips
                    (
                        employee_id,
                        payroll_period_start,
                        payroll_period_end,
                        basic_pay,
                        overtime_pay,
                        leave_pay,
                        holiday_pay,
                        gross_pay,
                        sss,
                        pagibig,
                        philhealth,
                        tax,
                        other_deductions,
                        total_deductions,
                        net_pay
                    )
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );

                $stmt->bind_param(
                    'issdddddddddddd',
                    $employeeId,
                    $start,
                    $end,
                    $basic,
                    $overtime,
                    $leave,
                    $holiday,
                    $gross,
                    $sss,
                    $pagibig,
                    $philhealth,
                    $tax,
                    $other,
                    $total,
                    $net
                );

                if ($stmt->execute()) {
                    $success = 'Payroll record created and released using the employee attendance.';
                } else {
                    $errors[] = 'Unable to create payroll record: ' . $stmt->error;
                }

                $stmt->close();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM payslips WHERE id=?');
            $stmt->bind_param('i', $id);

            if ($stmt->execute()) {
                $success = 'Payroll record deleted.';
            } else {
                $errors[] = 'Unable to delete payroll record.';
            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];

    $stmt = $conn->prepare('SELECT * FROM payslips WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Employees
|--------------------------------------------------------------------------
*/
$employees = [];

$res = $conn->query(
    "SELECT id, employee_id, full_name, position
     FROM employees
     WHERE employment_status <> 'Inactive'
     ORDER BY full_name"
);

if ($res) {
    $employees = $res->fetch_all(MYSQLI_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Released payslips
|--------------------------------------------------------------------------
*/
$rows = [];

$res = $conn->query(
    "SELECT
        p.*,
        e.employee_id AS employee_number,
        e.full_name,
        e.position
     FROM payslips p
     JOIN employees e ON e.id = p.employee_id
     ORDER BY p.payroll_period_end DESC, p.id DESC"
);

if ($res) {
    $rows = $res->fetch_all(MYSQLI_ASSOC);
}

$totalRecords = count($rows);
$totalNet = array_sum(array_map(
    fn($r) => (float)$r['net_pay'],
    $rows
));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payroll | WFMS Admin</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <div class="sidebar-backdrop" data-sidebar-close></div>
  <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header"><a class="brand-mark" href="index.php"><span class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="brand-copy"><span class="brand-title">WFMS Admin</span><span class="brand-subtitle">Workforce Management</span></span></a></div>
   <nav class="sidebar-nav">
      <a class="nav-link " href="index.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
      <a class="nav-link" href="users.php"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Users</span></a>
      <a class="nav-link" href="add-user.php"><span class="nav-icon"><i class="bi bi-person-plus"></i></span><span class="nav-text">Add User</span></a>
      <a class="nav-link" href="profile.php"><span class="nav-icon"><i class="bi bi-person-badge"></i></span><span class="nav-text">Profile</span></a>
      <a class="nav-link" href="requests.php"><span class="nav-icon"><i class="bi bi-inbox"></i></span><span class="nav-text">Requests</span></a>
      <a class="nav-link" href="attendance.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Attendance</span></a>
      <a class="nav-link" href="timesheet.php"><span class="nav-icon"><i class="bi bi-clock-history"></i></span><span class="nav-text">Time Sheet</span></a>
      <a class="nav-link active" href="payroll.php"><span class="nav-icon"><i class="bi bi-window-stack"></i></span><span class="nav-text">Payroll</span></a>
      <a class="nav-link" href="charts.php"><span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span><span class="nav-text">Charts</span></a>
    </nav>
    <div class="sidebar-footer"><span class="status-dot"></span><span class="sidebar-footer-text">System running smoothly</span></div>
  </aside>

  <div class="admin-main">
    <nav class="navbar admin-navbar navbar-expand bg-white"><div class="container-fluid px-3 px-lg-4"><button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar"><span></span><span></span><span></span></button><div class="navbar-actions ms-auto"><button class="icon-button theme-toggle" type="button" data-theme-toggle><i class="bi bi-moon-stars" data-theme-icon></i></button><div class="dropdown"><button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown"><span class="profile-name d-none d-sm-inline">Admin</span></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php">Profile</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="logout.php">Sign out</a></li></ul></div></div></div></nav>

    <main class="dashboard-content"><div class="container-fluid px-3 px-lg-4 py-4">
      <div class="page-heading"><div class="page-heading-copy"><div><h1 class="h3 mb-1">Payroll Management</h1><p class="text-muted mb-0">Create, edit, and release employee payslips.</p></div></div></div>

      <?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo e($success); ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo e($error); ?></div><?php endforeach; ?>

      <div class="row g-3 mb-4">
        <div class="col-12 col-md-6"><div class="panel p-4"><div class="text-muted small">Payroll Records</div><div class="h3 mb-0 mt-1"><?php echo $totalRecords; ?></div></div></div>
        <div class="col-12 col-md-6"><div class="panel p-4"><div class="text-muted small">Total Net Pay Released</div><div class="h3 mb-0 mt-1 text-success"><?php echo money($totalNet); ?></div></div></div>
      </div>

      <section class="panel mb-4">
        <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-plus-circle"></i><span><?php echo $editing ? 'Edit Payroll' : 'Create Payroll'; ?></span></h2><p class="text-muted mb-0">The employee will see this record in User → Payslip after it is saved.</p></div></div>
        <form method="post" class="p-4">
          <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int)($editing['id'] ?? 0); ?>">
          <div class="row g-3">
            <div class="col-12 col-md-6"><label class="form-label">Employee</label><select name="employee_id" id="employeeSelect" class="form-select" required><option value="">Select employee</option><?php foreach ($employees as $emp): ?><option value="<?php echo (int)$emp['id']; ?>" <?php echo ((int)($editing['employee_id'] ?? 0) === (int)$emp['id']) ? 'selected' : ''; ?>><?php echo e($emp['full_name'] . ' — ' . $emp['employee_id']); ?></option><?php endforeach; ?></select></div>
            <div class="col-12 col-md-3"><label class="form-label">Period Start</label><input type="date" name="payroll_period_start" id="periodStart" class="form-control" value="<?php echo e($editing['payroll_period_start'] ?? date('Y-m-01')); ?>" required></div>
            <div class="col-12 col-md-3"><label class="form-label">Period End</label><input type="date" name="payroll_period_end" id="periodEnd" class="form-control" value="<?php echo e($editing['payroll_period_end'] ?? date('Y-m-t')); ?>" required></div>

            <div class="col-12"><hr><h3 class="h6">Earnings</h3></div>
            <?php
            $earningFields = [
                'basic_pay' => 'Basic Pay',
                'overtime_pay' => 'Overtime Pay',
                'leave_pay' => 'Leave Pay',
                'holiday_pay' => 'Holiday Pay'
            ];
            foreach ($earningFields as $name=>$label):
            ?>
              <div class="col-12 col-md-3">
                <label class="form-label"><?php echo $label; ?></label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  name="<?php echo $name; ?>"
                  class="form-control payroll-input <?php echo in_array($name, ['basic_pay','overtime_pay'], true) ? 'auto-pay-field' : ''; ?>"
                  value="<?php echo e($editing[$name] ?? '0.00'); ?>"
                  <?php echo in_array($name, ['basic_pay','overtime_pay'], true) ? 'readonly' : ''; ?>
                >
              </div>
            <?php endforeach; ?>

            <div class="col-12">
              <div class="alert alert-info py-2 mb-0" id="attendanceCalcStatus">
                <i class="bi bi-calculator me-2"></i>
                Select an employee and payroll period. Basic Pay and Overtime Pay will be calculated automatically from completed Time In/Time Out records.
              </div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Regular Hours from Attendance</label>
              <input id="regularHoursPreview" class="form-control" value="0.00 hrs" readonly>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Overtime Hours from Attendance</label>
              <input id="overtimeHoursPreview" class="form-control" value="0.00 hrs" readonly>
            </div>
            <div class="col-12"><hr><h3 class="h6">Deductions</h3></div>
            <?php $deductionFields = ['sss'=>'SSS','pagibig'=>'Pag-IBIG','philhealth'=>'PhilHealth','tax'=>'Tax','other_deductions'=>'Other Deductions']; foreach ($deductionFields as $name=>$label): ?><div class="col-12 col-md-3"><label class="form-label"><?php echo $label; ?></label><input type="number" step="0.01" min="0" name="<?php echo $name; ?>" class="form-control payroll-input" value="<?php echo e($editing[$name] ?? '0.00'); ?>"></div><?php endforeach; ?>
            <div class="col-12 col-md-4"><label class="form-label">Gross Pay</label><input id="grossPreview" class="form-control" value="<?php echo money($editing['gross_pay'] ?? 0); ?>" readonly></div>
            <div class="col-12 col-md-4"><label class="form-label">Total Deductions</label><input id="deductionPreview" class="form-control" value="<?php echo money($editing['total_deductions'] ?? 0); ?>" readonly></div>
            <div class="col-12 col-md-4"><label class="form-label">Net Pay</label><input id="netPreview" class="form-control fw-bold" value="<?php echo money($editing['net_pay'] ?? 0); ?>" readonly></div>
            <div class="col-12">
              <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Basic Pay and Overtime Pay are locked because they are calculated from employee attendance.
              </small>
            </div>
            <div class="col-12 d-flex gap-2 mt-2">
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-save me-1"></i>
                <?php echo $editing ? 'Update Payroll' : 'Create & Release Payslip'; ?>
              </button>
              <?php if ($editing): ?>
                <a href="payroll.php" class="btn btn-outline-secondary">Cancel</a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </section>

      <section class="panel">
        <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-receipt"></i><span>Released Payslips</span></h2><p class="text-muted mb-0">All payroll records currently visible to employees.</p></div><input class="form-control form-control-sm table-search" type="search" placeholder="Search employee" data-table-search="payrollTable"></div>
        <div class="table-responsive"><table class="table align-middle mb-0" id="payrollTable" data-searchable-table><thead><tr><th>Employee</th><th>ID No.</th><th>Period</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Actions</th></tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No payroll records yet. Create the first payslip above.</td></tr><?php else: foreach ($rows as $row): ?><tr><td class="fw-semibold"><?php echo e($row['full_name']); ?></td><td><?php echo e($row['employee_number']); ?></td><td><?php echo e(date('M d, Y', strtotime($row['payroll_period_start'])) . ' – ' . date('M d, Y', strtotime($row['payroll_period_end']))); ?></td><td><?php echo money($row['gross_pay']); ?></td><td><?php echo money($row['total_deductions']); ?></td><td class="fw-semibold text-success"><?php echo money($row['net_pay']); ?></td><td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="payroll.php?edit=<?php echo (int)$row['id']; ?>"><i class="bi bi-pencil"></i></a><form method="post" onsubmit="return confirm('Delete this payroll record? The employee will no longer see this payslip.');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form></div></td></tr><?php endforeach; endif; ?>
        </tbody></table></div>
      </section>
    </div></main>
  </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
<script>
(function () {
  const nums = [...document.querySelectorAll('.payroll-input')];

  const val = (name) => {
    const el = document.querySelector('[name="' + name + '"]');
    return parseFloat(el?.value || 0) || 0;
  };

  const peso = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  const employeeSelect = document.getElementById('employeeSelect');
  const periodStart = document.getElementById('periodStart');
  const periodEnd = document.getElementById('periodEnd');
  const statusBox = document.getElementById('attendanceCalcStatus');
  const regularHoursPreview = document.getElementById('regularHoursPreview');
  const overtimeHoursPreview = document.getElementById('overtimeHoursPreview');

  let calculationTimer = null;

  function calcTotals() {
    const gross =
      val('basic_pay') +
      val('overtime_pay') +
      val('leave_pay') +
      val('holiday_pay');

    const deductions =
      val('sss') +
      val('pagibig') +
      val('philhealth') +
      val('tax') +
      val('other_deductions');

    document.getElementById('grossPreview').value = peso(gross);
    document.getElementById('deductionPreview').value = peso(deductions);
    document.getElementById('netPreview').value = peso(gross - deductions);
  }

  async function calculateFromAttendance() {
    const employeeId = employeeSelect?.value || '';
    const start = periodStart?.value || '';
    const end = periodEnd?.value || '';

    if (!employeeId || !start || !end) {
      if (regularHoursPreview) regularHoursPreview.value = '0.00 hrs';
      if (overtimeHoursPreview) overtimeHoursPreview.value = '0.00 hrs';
      calcTotals();
      return;
    }

    if (start > end) {
      statusBox.className = 'alert alert-danger py-2 mb-0';
      statusBox.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i>Period end must be on or after the start date.';
      return;
    }

    statusBox.className = 'alert alert-info py-2 mb-0';
    statusBox.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Calculating from attendance...';

    try {
      const url =
        'payroll.php?action=calculate' +
        '&employee_id=' + encodeURIComponent(employeeId) +
        '&start=' + encodeURIComponent(start) +
        '&end=' + encodeURIComponent(end);

      const response = await fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
      });

      const data = await response.json();

      if (!data.ok) {
        statusBox.className = 'alert alert-warning py-2 mb-0';
        statusBox.innerHTML =
          '<i class="bi bi-exclamation-triangle me-2"></i>' +
          (data.message || 'Unable to calculate attendance.');
        return;
      }

      document.querySelector('[name="basic_pay"]').value =
        Number(data.basic_pay || 0).toFixed(2);

      document.querySelector('[name="overtime_pay"]').value =
        Number(data.overtime_pay || 0).toFixed(2);

      regularHoursPreview.value =
        Number(data.regular_hours || 0).toFixed(2) + ' hrs';

      overtimeHoursPreview.value =
        Number(data.overtime_hours || 0).toFixed(2) + ' hrs';

      if (Number(data.regular_hours || 0) === 0 &&
          Number(data.overtime_hours || 0) === 0) {
        statusBox.className = 'alert alert-warning py-2 mb-0';
        statusBox.innerHTML =
          '<i class="bi bi-clock me-2"></i>' +
          'No completed Time In/Time Out records found for this period yet. Payroll will update automatically after Time Out.';
      } else {
        statusBox.className = 'alert alert-success py-2 mb-0';
        statusBox.innerHTML =
          '<i class="bi bi-check-circle me-2"></i>' +
          'Payroll updated from attendance. Regular: ' +
          Number(data.regular_hours || 0).toFixed(2) +
          ' hrs, Overtime: ' +
          Number(data.overtime_hours || 0).toFixed(2) + ' hrs.';
      }

      calcTotals();
    } catch (error) {
      statusBox.className = 'alert alert-danger py-2 mb-0';
      statusBox.innerHTML =
        '<i class="bi bi-exclamation-circle me-2"></i>' +
        'Could not connect to the payroll calculator.';
    }
  }

  nums.forEach((el) => {
    el.addEventListener('input', calcTotals);
  });

  employeeSelect?.addEventListener('change', calculateFromAttendance);
  periodStart?.addEventListener('change', calculateFromAttendance);
  periodEnd?.addEventListener('change', calculateFromAttendance);

  // Re-check every 15 seconds so an admin page left open can see a
  // newly completed Time Out without manually refreshing the page.
  calculationTimer = setInterval(calculateFromAttendance, 15000);

  calcTotals();

  // If editing an existing record, also refresh its attendance-based
  // basic/overtime values from the selected period.
  calculateFromAttendance();
})();
</script>
</body>
</html>
