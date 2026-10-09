<?php

require_once "db.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($full_name) || empty($email) || empty($password)) {
        $message = "Please complete all fields.";
        $messageType = "danger";
    } else {

        // Check if email already exists
        $check = $conn->prepare("
            SELECT id 
            FROM employees 
            WHERE email = ?
        ");

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "Email is already registered.";
            $messageType = "danger";

        } else {

            $conn->begin_transaction();

            try {

                // Generate employee ID
                $employee_id = "EMP-" . date("YmdHis");

                // Employee default information
                $position = "Staff";
                $department = "General";
                $employment_status = "Regular";
                $date_hired = date("Y-m-d");

                // Insert employee
                $stmt = $conn->prepare("
                    INSERT INTO employees
                    (
                        employee_id,
                        full_name,
                        email,
                        position,
                        department,
                        employment_status,
                        date_hired
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "sssssss",
                    $employee_id,
                    $full_name,
                    $email,
                    $position,
                    $department,
                    $employment_status,
                    $date_hired
                );

                $stmt->execute();

                $new_employee_id = $conn->insert_id;

                // Password hash
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Use email as username
                $username = $email;
                $role = "employee";

                // Insert login account
                $userStmt = $conn->prepare("
                    INSERT INTO users
                    (
                        employee_id,
                        username,
                        password_hash,
                        role
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $userStmt->bind_param(
                    "isss",
                    $new_employee_id,
                    $username,
                    $password_hash,
                    $role
                );

                $userStmt->execute();

                $conn->commit();

                header("Location: login.php?registered=1");
                exit;

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Registration failed: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | admin</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="auth-body">

<button
    class="icon-button theme-toggle auth-theme-toggle"
    type="button"
    data-theme-toggle
>
    <i class="bi bi-moon-stars"></i>
</button>

<main class="auth-page">

<section class="auth-card">

    <a class="auth-brand" href="index.php">
        <span class="brand-icon">
            <i class="bi bi-grid-1x2-fill"></i>
        </span>

        <span>
            <strong>adminHMD</strong>
            <small>Create your adminHMD account.</small>
        </span>
    </a>

    <?php if (!empty($message)): ?>

        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="register.php">

        <div class="mb-4">
            <p class="eyebrow mb-1">Secure Access</p>

            <h1 class="h3 mb-1">
                Register
            </h1>

            <p class="text-muted mb-0">
                Create your adminHMD account.
            </p>
        </div>

        <div class="mb-3">

            <label class="form-label">
                Full name
            </label>

            <input
                class="form-control"
                name="full_name"
                type="text"
                required
            >

        </div>

        <div class="mb-3">

            <label class="form-label">
                Email address
            </label>

            <input
                class="form-control"
                name="email"
                type="email"
                required
            >

        </div>

        <div class="mb-3">

            <label class="form-label">
                Password
            </label>

            <input
                class="form-control"
                name="password"
                type="password"
                minlength="6"
                required
            >

        </div>

        <div class="form-check mb-4">

            <input
                class="form-check-input"
                type="checkbox"
                required
            >

            <label class="form-check-label">
                I agree to the terms
            </label>

        </div>

        <button
            class="btn btn-primary w-100"
            type="submit"
        >
            <i class="bi bi-person-plus"></i>
            Create Account
        </button>

    </form>

    <div class="auth-footer">
        Already have an account?
        <a href="login.php">Sign in</a>
    </div>

</section>

</main>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>

</body>
</html>