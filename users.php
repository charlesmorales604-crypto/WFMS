<?php
require_once __DIR__ . '/auth.php';

$q = trim($_GET['q'] ?? '');
$department = trim($_GET['department'] ?? '');
$status = trim($_GET['status'] ?? '');

$departments = [];
$deptResult = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department <> '' ORDER BY department");
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) $departments[] = $row['department'];
}

$sql = "SELECT e.id, e.employee_id, e.full_name, e.email, e.contact_number, e.position, e.department,
               e.employment_status, e.date_hired, e.created_at, u.id AS user_id, u.role, u.last_login
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        WHERE 1=1";
$params = [];
$types = '';

if ($q !== '') {
    $sql .= " AND (e.full_name LIKE ? OR e.email LIKE ? OR e.employee_id LIKE ? OR e.position LIKE ? OR e.department LIKE ?)";
    $like = "%{$q}%";
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
if ($department !== '') {
    $sql .= " AND e.department = ?";
    $params[] = $department;
    $types .= 's';
}
if ($status !== '') {
    if ($status === 'Active') $sql .= " AND e.employment_status <> 'Inactive'";
    elseif ($status === 'Inactive') $sql .= " AND e.employment_status = 'Inactive'";
}
$sql .= " ORDER BY e.created_at DESC, e.full_name ASC";

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function userStatus(string $employmentStatus): string {
    return $employmentStatus === 'Inactive' ? 'Inactive' : 'Active';
}
function statusClass(string $status): string {
    return $status === 'Inactive' ? 'text-bg-secondary' : 'text-bg-success';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="WFMS employee and user management">
  <title>Users | WFMS Admin</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-shell">
  <div class="sidebar-backdrop" data-sidebar-close></div>
  <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
      <a class="brand-mark" href="index.php" aria-label="WFMS dashboard">
        <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
        <span class="brand-copy"><span class="brand-title">WFMS Admin</span><span class="brand-subtitle">Workforce Management</span></span>
      </a>
    </div>
        <nav class="sidebar-nav">
      <a class="nav-link " href="index.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
      <a class="nav-link active" href="users.php"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Users</span></a>
      <a class="nav-link" href="add-user.php"><span class="nav-icon"><i class="bi bi-person-plus"></i></span><span class="nav-text">Add User</span></a>
      <a class="nav-link" href="profile.php"><span class="nav-icon"><i class="bi bi-person-badge"></i></span><span class="nav-text">Profile</span></a>
      <a class="nav-link" href="requests.php"><span class="nav-icon"><i class="bi bi-inbox"></i></span><span class="nav-text">Requests</span></a>
      <a class="nav-link" href="attendance.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Attendance</span></a>
      <a class="nav-link" href="timesheet.php"><span class="nav-icon"><i class="bi bi-clock-history"></i></span><span class="nav-text">Time Sheet</span></a>
      <a class="nav-link" href="payroll.php"><span class="nav-icon"><i class="bi bi-window-stack"></i></span><span class="nav-text">Payroll</span></a>
      <a class="nav-link" href="charts.php"><span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span><span class="nav-text">Charts</span></a>
    </nav>
    <div class="sidebar-footer"><span class="status-dot"></span><span class="sidebar-footer-text">System running smoothly</span></div>
  </aside>

  <div class="admin-main">
    <nav class="navbar admin-navbar navbar-expand bg-white">
      <div class="container-fluid px-3 px-lg-4">
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Toggle sidebar"><span></span><span></span><span></span></button>
        <form class="d-none d-md-flex ms-3 flex-grow-1" method="get" action="users.php" role="search">
          <input class="form-control search-input" name="q" value="<?php echo e($q); ?>" type="search" placeholder="Search users, roles, teams" aria-label="Search">
        </form>
        <div class="navbar-actions ms-auto">
          <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme"><i class="bi bi-moon-stars" data-theme-icon></i></button>
          <div class="dropdown">
            <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><span class="profile-name d-none d-sm-inline"><?php echo e($_SESSION['full_name'] ?? 'Admin'); ?></span></button>
            <ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="profile.php">Profile</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="logout.php">Sign out</a></li></ul>
          </div>
        </div>
      </div>
    </nav>

    <main class="dashboard-content">
      <div class="container-fluid px-3 px-lg-4 py-4">
        <div class="page-heading">
          <div class="page-heading-copy">
            <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <div><p class="eyebrow mb-1">Management</p><h1 class="h3 mb-1">Users</h1><p class="text-muted mb-0">Search, review, and manage employee accounts.</p></div>
          </div>
          <div class="heading-actions"><a class="btn btn-primary btn-sm" href="add-user.php"><i class="bi bi-person-plus"></i> Add User</a></div>
        </div>

        <?php if (isset($_GET['updated'])): ?><div class="alert alert-success">User account updated successfully.</div><?php endif; ?>
        <?php if (isset($_GET['deactivated'])): ?><div class="alert alert-success">User account status updated successfully.</div><?php endif; ?>

        <section class="panel mt-3">
          <div class="panel-header">
            <div><h2 class="h5 mb-1 section-title"><i class="bi bi-table"></i><span>User List</span></h2><p class="text-muted mb-0">Manage employee accounts without changing the existing WFMS layout.</p></div>
            <form class="d-flex flex-wrap gap-2" method="get" action="users.php">
              <input class="form-control form-control-sm table-search" name="q" value="<?php echo e($q); ?>" type="search" placeholder="Search users" aria-label="Search users">
              <select class="form-select form-select-sm" name="department" aria-label="Department filter"><option value="">All teams</option><?php foreach ($departments as $d): ?><option value="<?php echo e($d); ?>" <?php echo $department === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option><?php endforeach; ?></select>
              <select class="form-select form-select-sm" name="status" aria-label="Status filter"><option value="">All status</option><option value="Active" <?php echo $status === 'Active' ? 'selected' : ''; ?>>Active</option><option value="Inactive" <?php echo $status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option></select>
              <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            </form>
          </div>

          <div class="table-responsive">
            <table class="table align-middle mb-0" id="usersTable">
              <thead><tr><th>User</th><th>Role</th><th>Team</th><th>Status</th><th>Joined</th><th class="text-end">Action</th></tr></thead>
              <tbody>
              <?php if (!$users): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No users found.</td></tr>
              <?php else: foreach ($users as $u): $displayStatus = userStatus($u['employment_status']); ?>
                <tr>
                  <td><div class="d-flex align-items-center gap-2"><img class="avatar-img avatar-sm" src="../assets/images/avatar/avatar.jpg" alt="<?php echo e($u['full_name']); ?>"><div><p class="fw-semibold mb-0"><?php echo e($u['full_name']); ?></p><p class="text-muted small mb-0"><?php echo e($u['email']); ?></p></div></div></td>
                  <td><?php echo e(ucfirst($u['role'] ?? 'employee')); ?><br><span class="text-muted small"><?php echo e($u['position']); ?></span></td>
                  <td><?php echo e($u['department']); ?></td>
                  <td><span class="badge <?php echo statusClass($displayStatus); ?>"><?php echo e($displayStatus); ?></span></td>
                  <td><?php echo $u['date_hired'] ? e(date('F j, Y', strtotime($u['date_hired']))) : e(date('F j, Y', strtotime($u['created_at']))); ?></td>
                  <td class="text-end"><div class="d-inline-flex gap-1"><a class="btn btn-light btn-sm" href="user-details.php?id=<?php echo (int)$u['id']; ?>"><i class="bi bi-eye"></i> View</a><a class="btn btn-outline-primary btn-sm" href="edit-user.php?id=<?php echo (int)$u['id']; ?>"><i class="bi bi-pencil"></i></a><form method="post" action="toggle-user.php" class="d-inline" onsubmit="return confirm('Change this user account status?');"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><button class="btn btn-outline-<?php echo $displayStatus === 'Active' ? 'danger' : 'success'; ?> btn-sm" type="submit" title="<?php echo $displayStatus === 'Active' ? 'Deactivate' : 'Activate'; ?>"><i class="bi bi-<?php echo $displayStatus === 'Active' ? 'person-dash' : 'person-check'; ?>"></i></button></form></div></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>
    <footer class="admin-footer"></footer>
  </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script><script src="../assets/js/main.js"></script>
</body>
</html>
