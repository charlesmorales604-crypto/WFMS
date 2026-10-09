<?php
session_start();
require_once __DIR__ . "/db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "employee") {
    header("Location: login.php");
    exit;
}
$employeeId = (int)$_SESSION["employee_id"];
$fullName = $_SESSION["full_name"] ?? "Employee";
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); }
?>