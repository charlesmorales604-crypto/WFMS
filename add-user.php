<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="adminHMD professional admin dashboard template">
  <title>Add User | admin</title>

  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
  <div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
      <div class="sidebar-header">
        <a class="brand-mark" href="index.html" aria-label="adminHMD dashboard">
          <span class="brand-icon"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i></span>
          <span class="brand-copy">
            <span class="brand-title">WFMS Admin</span>
            <span class="brand-subtitle">Workforce Management</span>
          </span>
        </a>
      </div>

         <nav class="sidebar-nav">
      <a class="nav-link " href="index.php"><span class="nav-icon"><i class="bi bi-speedometer2"></i></span><span class="nav-text">Dashboard</span></a>
      <a class="nav-link" href="users.php"><span class="nav-icon"><i class="bi bi-people"></i></span><span class="nav-text">Users</span></a>
      <a class="nav-link active" href="add-user.php"><span class="nav-icon"><i class="bi bi-person-plus"></i></span><span class="nav-text">Add User</span></a>
      <a class="nav-link" href="profile.php"><span class="nav-icon"><i class="bi bi-person-badge"></i></span><span class="nav-text">Profile</span></a>
      <a class="nav-link" href="requests.php"><span class="nav-icon"><i class="bi bi-inbox"></i></span><span class="nav-text">Requests</span></a>
      <a class="nav-link" href="attendance.php"><span class="nav-icon"><i class="bi bi-table"></i></span><span class="nav-text">Attendance</span></a>
      <a class="nav-link" href="timesheet.php"><span class="nav-icon"><i class="bi bi-clock-history"></i></span><span class="nav-text">Time Sheet</span></a>
      <a class="nav-link" href="payroll.php"><span class="nav-icon"><i class="bi bi-window-stack"></i></span><span class="nav-text">Payroll</span></a>
      <a class="nav-link" href="charts.php"><span class="nav-icon"><i class="bi bi-bar-chart-line"></i></span><span class="nav-text">Charts</span></a>
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
            <span></span>
            <span></span>
            <span></span>
          </button>

          <form class="d-none d-md-flex ms-3 flex-grow-1" role="search">
            <input class="form-control search-input" type="search" placeholder="Search users, orders, reports" aria-label="Search">
          </form>

          <div class="navbar-actions ms-auto">
            <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
              <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
            </button>
            

            <div class="dropdown">
              <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="profile-name d-none d-sm-inline">Admin Hasan</span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="login.php">Sign out</a></li>
              </ul>
            </div>
          </div>
        </div>
      </nav>

      <main class="dashboard-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
          <div class="page-heading">
            <div class="page-heading-copy">
              <span class="page-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
              <div>
                <p class="eyebrow mb-1">Management</p>
                <h1 class="h3 mb-1">Add User</h1>
                <p class="text-muted mb-0">Create a new user account with role and team assignments.</p>
              </div>
            </div>
            <div class="heading-actions"><a class="btn btn-outline-secondary btn-sm" href="users.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Users</a></div>
          </div>

          <section class="row g-3">
            <div class="col-12 col-xl-8">
              <form class="panel needs-validation" novalidate>
                <div class="panel-header"><div><h2 class="h5 mb-1 section-title"><i class="bi bi-person-plus" aria-hidden="true"></i><span>User Information</span></h2><p class="text-muted mb-0">Create a user account with validated fields.</p></div></div>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label" for="firstName">First name</label><input class="form-control" id="firstName" type="text" required><div class="invalid-feedback">First name is required.</div></div>
                  <div class="col-md-6"><label class="form-label" for="lastName">Last name</label><input class="form-control" id="lastName" type="text" required><div class="invalid-feedback">Last name is required.</div></div>
                  <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" required><div class="invalid-feedback">Enter a valid email.</div></div>
                  <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" type="tel" required><div class="invalid-feedback">Phone number is required.</div></div>
                  <div class="col-md-6"><label class="form-label" for="role">Role</label><select class="form-select" id="role" required><option value="">Choose role</option><option>Admin</option><option>Manager</option><option>Editor</option><option>Viewer</option></select><div class="invalid-feedback">Choose a role.</div></div>
                  <div class="col-md-6"><label class="form-label" for="team">Team</label><select class="form-select" id="team" required><option value="">Choose team</option><option>Operations</option><option>Sales</option><option>Content</option><option>Finance</option></select><div class="invalid-feedback">Choose a team.</div></div>
                  <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" rows="4" placeholder="Optional onboarding notes"></textarea></div>
                </div>
                <div class="d-flex flex-wrap justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="users.html">Cancel</a><button class="btn btn-primary" type="submit"><i class="bi bi-person-check" aria-hidden="true"></i> Create User</button></div>
              </form>
            </div>
            <div class="col-12 col-xl-4">
              <div class="panel h-100">
                <h2 class="h5 mb-3 section-title"><i class="bi bi-list-check" aria-hidden="true"></i><span>Access Checklist</span></h2>
                <div class="activity-list">
                  <div class="activity-item"><span class="activity-dot bg-success"></span><div><p class="mb-1 fw-semibold">Assign role</p><p class="text-muted small mb-0">Start with the least privileged role.</p></div></div>
                  <div class="activity-item"><span class="activity-dot bg-primary"></span><div><p class="mb-1 fw-semibold">Add team</p><p class="text-muted small mb-0">Team ownership controls dashboards.</p></div></div>
                  <div class="activity-item"><span class="activity-dot bg-warning"></span><div><p class="mb-1 fw-semibold">Send invite</p><p class="text-muted small mb-0">Users receive activation by email.</p></div></div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>

      <footer class="admin-footer">
      </footer>
    </div>
  </div>

  <script src="../assets/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/main.js"></script>
</body>
</html>
