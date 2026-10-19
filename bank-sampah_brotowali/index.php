<?php
session_start();

$tujuan = [
    'admin'    => 'dashboard.php',
    'guru'     => 'dashboard_guru.php',
    'siswa'    => 'dashboard_siswa.php',
    'pengepul' => 'dashboard_pengepul.php',
];

$role = $_SESSION['role'] ?? '';

if (($_SESSION['login'] ?? false) === true && isset($tujuan[$role])) {
    header("Location: " . $tujuan[$role]);
} else {
    header("Location: login.php");
}
exit;
