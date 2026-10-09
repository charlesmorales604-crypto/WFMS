<?php

session_start();

require_once "db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } else {

        $stmt = $conn->prepare("
            SELECT
                u.id,
                u.employee_id,
                u.username,
                u.password_hash,
                u.role,
                e.full_name,
                e.email,
                e.employment_status
            FROM users u
            INNER JOIN employees e
                ON u.employee_id = e.id
            WHERE u.username = ? AND e.employment_status <> 'Inactive'
        ");

        $stmt->bind_param("s", $email);

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user["password_hash"])) {

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["employee_id"] = $user["employee_id"];
                $_SESSION["username"] = $user["username"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];

                // Update last login
                $update = $conn->prepare("
                    UPDATE users
                    SET last_login = NOW()
                    WHERE id = ?
                ");

                $update->bind_param("i", $user["id"]);
                $update->execute();

                if ($user["role"] === "admin") { header("Location: ../admin/index.php"); } else { header("Location: ../user/index.php"); }
                exit;

            } else {

                $error = "Incorrect password.";

            }

        } else {

            $error = "Account not found.";

        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | admin</title>

    <link
        rel="stylesheet"
        href="../assets/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/vendors/bootstrap-icons/bootstrap-icons.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

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
            <strong>employee</strong>
            <small>
                Sign in to your Employee workspace.
            </small>
        </span>

    </a>


    <?php if (!empty($error)): ?>

        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["registered"])): ?>

        <div class="alert alert-success">
            Account created successfully.
            You can now login.
        </div>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="mb-4">

            <p class="eyebrow mb-1">
                Secure Access
            </p>

            <h1 class="h3 mb-1">
                Login
            </h1>

            <p class="text-muted mb-0">
                Sign in to your employee workspace.
            </p>

        </div>

        <div class="mb-3">

            <label
                class="form-label"
                for="loginEmail"
            >
                Email address
            </label>

            <input
                class="form-control"
                id="loginEmail"
                name="email"
                type="email"
                required
            >

        </div>

        <div class="mb-3">

            <div class="d-flex justify-content-between">

                <label
                    class="form-label"
                    for="loginPassword"
                >
                    Password
                </label>

                <a
                    class="small fw-semibold"
                    href="forgot-password.php"
                >
                    Forgot?
                </a>

            </div>

            <input
                class="form-control"
                id="loginPassword"
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
                id="rememberMe"
            >

            <label
                class="form-check-label"
                for="rememberMe"
            >
                Remember me
            </label>

        </div>

        <button
            class="btn btn-primary w-100"
            type="submit"
        >

            <i class="bi bi-box-arrow-in-right"></i>
            Sign In

        </button>

    </form>

    <div class="auth-footer">

        New here?

        <a href="register.php">
            Create an account
        </a>

    </div>

</section>

</main>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>

</body>
</html>