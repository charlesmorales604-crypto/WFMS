<?php
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: users.php'); exit; }
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: users.php'); exit; }
$stmt = $conn->prepare("UPDATE employees SET employment_status = CASE WHEN employment_status = 'Inactive' THEN 'Regular' ELSE 'Inactive' END WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
header('Location: users.php?deactivated=1');
exit;
