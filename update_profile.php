<?php
require_once __DIR__ . '/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: profile.php'); exit; }
$fullName = trim($_POST['fullName'] ?? '');
$contact = trim($_POST['contactNumber'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { die('Please enter a valid name and email address.'); }
$check = $conn->prepare('SELECT id FROM employees WHERE email=? AND id<>? LIMIT 1');
$check->bind_param('si', $email, $employeeId); $check->execute();
if ($check->get_result()->fetch_assoc()) { die('That email address is already in use.'); }
$stmt = $conn->prepare('UPDATE employees SET full_name=?, contact_number=?, email=?, address=? WHERE id=? LIMIT 1');
$stmt->bind_param('ssssi', $fullName, $contact, $email, $address, $employeeId);
if (!$stmt->execute()) { die('Profile update failed: ' . $stmt->error); }
$_SESSION['full_name'] = $fullName; $_SESSION['email'] = $email;
header('Location: profile.php?success=1'); exit;
