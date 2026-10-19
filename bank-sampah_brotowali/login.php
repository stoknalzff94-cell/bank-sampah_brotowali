<?php
session_start();

/* =========================================================
   KONEKSI DATABASE
========================================================= */
$koneksi = mysqli_connect(
    "localhost",
    "root",
    "",
    "banksampah_ataya"
);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

/* =========================================================
   JIKA SUDAH LOGIN
========================================================= */
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {

    switch ($_SESSION['role'] ?? '') {

        case 'admin':
            header("Location: dashboard.php");
            exit;

        case 'guru':
            header("Location: dashboard_guru.php");
            exit;

        case 'siswa':
            header("Location: dashboard_siswa.php");
            exit;

        case 'pengepul':
            header("Location: dashboard_pengepul.php");
            exit;
    }
}

/* =========================================================
   PROSES LOGIN
========================================================= */
$pesan = "";
$username_lama = "";
$role_lama = "";

if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = trim($_POST['role'] ?? '');

    $username_lama = $username;
    $role_lama = $role;

    $role_valid = [
        'admin',
        'guru',
        'siswa',
        'pengepul'
    ];

    if ($username === '' || $password === '' || $role === '') {

        $pesan = "Lengkapi semua data akses.";

    } elseif (!in_array($role, $role_valid, true)) {

        $pesan = "Role tidak valid.";

    }

    /* =====================================================
       LOGIN ADMIN
    ===================================================== */
    elseif ($role === 'admin') {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT * FROM admin
             WHERE username = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $username
        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($hasil);

        if ($admin) {

            if (password_verify($password, $admin['password'])) {

                session_regenerate_id(true);

                $_SESSION['login'] = true;
                $_SESSION['role'] = 'admin';
                $_SESSION['id_admin'] = $admin['id_admin'] ?? null;
                $_SESSION['id_user'] = $admin['id_admin'] ?? null;
                $_SESSION['nama_user'] = $admin['nama'] ?? $admin['nama_user'] ?? $admin['username'] ?? $username;
                $_SESSION['username'] =
                    $admin['username'] ?? $username;

                $_SESSION['nama'] =
                    $admin['nama']
                    ?? $admin['nama_user']
                    ?? $admin['username']
                    ?? $username;

                header("Location: dashboard.php");
                exit;

            } else {

                $pesan = "Password yang kamu masukkan salah.";

            }

        } else {

            $pesan = "Username admin tidak ditemukan.";
        }

        mysqli_stmt_close($stmt);
    }

    /* =====================================================
       LOGIN GURU / SISWA / PENGEPUL
    ===================================================== */
    else {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT *
             FROM users
             WHERE username = ?
             AND role = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $username,
            $role
        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($hasil);

        if ($user) {

            if (password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                $_SESSION['login'] = true;
                $_SESSION['role'] = $user['role'];

                $_SESSION['id_user'] =
                    $user['id_user'] ?? null;

                $_SESSION['username'] =
                    $user['username'] ?? $username;

                $_SESSION['nama_user'] =
                    $user['nama_user']
                    ?? $user['nama']
                    ?? $user['username']
                    ?? $username;

                $_SESSION['id_nasabah'] =
                    $user['id_nasabah'] ?? null;

                switch ($role) {

                    case 'guru':
                        header("Location: dashboard_guru.php");
                        exit;

                    case 'siswa':
                        header("Location: dashboard_siswa.php");
                        exit;

                    case 'pengepul':
                        header("Location: dashboard_pengepul.php");
                        exit;
                }

            } else {

                $pesan = "Password yang kamu masukkan salah.";

            }

        } else {

            $pesan =
                "Username tidak ditemukan pada role yang dipilih.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>ATAYA CYBER ECO | Login</title>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {

    --green:
        #00ff9d;

    --green2:
        #00d084;

    --cyan:
        #00eaff;

    --dark:
        #020807;

    --dark2:
        #061712;

    --glass:
        rgba(6, 28, 23, .72);

    --border:
        rgba(0, 255, 157, .22);

    --text:
        #ecfffa;

    --muted:
        #83a69b;

    --danger:
        #ff5577;
}

/* =========================================================
   BODY
========================================================= */

body {

    min-height: 100vh;

    font-family:
        Inter,
        "Segoe UI",
        Arial,
        sans-serif;

    color: var(--text);

    overflow-x: hidden;

    background:

        radial-gradient(
            circle at 10% 20%,
            rgba(0,255,157,.13),
            transparent 28%
        ),

        radial-gradient(
            circle at 90% 15%,
            rgba(0,234,255,.10),
            transparent 25%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(0,180,100,.08),
            transparent 35%
        ),

        #020807;
}

/* =========================================================
   GRID
========================================================= */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    opacity: .20;

    background-image:

        linear-gradient(
            rgba(0,255,157,.055) 1px,
            transparent 1px
        ),

        linear-gradient(
            90deg,
            rgba(0,255,157,.055) 1px,
            transparent 1px
        );

    background-size: 42px 42px;

    animation:
        gridMove 18s linear infinite;
}

@keyframes gridMove {

    from {
        background-position: 0 0, 0 0;
    }

    to {
        background-position: 42px 42px, 42px 42px;
    }
}

/* =========================================================
   AURORA
========================================================= */

.aurora {

    position: fixed;

    width: 520px;

    height: 520px;

    border-radius: 50%;

    filter: blur(90px);

    opacity: .16;

    pointer-events: none;
}

.aurora.one {

    background: #00ff9d;

    top: -220px;

    left: -180px;

    animation:
        auroraOne 12s ease-in-out infinite alternate;
}

.aurora.two {

    background: #00eaff;

    right: -220px;

    top: 10%;

    animation:
        auroraTwo 15s ease-in-out infinite alternate;
}

.aurora.three {

    background: #00b86b;

    bottom: -260px;

    left: 30%;

    animation:
        auroraThree 18s ease-in-out infinite alternate;
}

@keyframes auroraOne {

    to {
        transform:
            translate(180px, 160px)
            scale(1.25);
    }
}

@keyframes auroraTwo {

    to {
        transform:
            translate(-160px, 100px)
            scale(1.3);
    }
}

@keyframes auroraThree {

    to {
        transform:
            translate(100px, -130px)
            scale(1.25);
    }
}

/* =========================================================
   PARTICLES
========================================================= */

#particles {

    position: fixed;

    inset: 0;

    pointer-events: none;

    overflow: hidden;
}

.particle {

    position: absolute;

    width: 3px;

    height: 3px;

    border-radius: 50%;

    background: var(--green);

    box-shadow:
        0 0 8px var(--green);

    opacity: .5;

    animation:
        particleFloat linear infinite;
}

@keyframes particleFloat {

    from {
        transform:
            translateY(110vh)
            scale(.3);
        opacity: 0;
    }

    20% {
        opacity: .7;
    }

    80% {
        opacity: .5;
    }

    to {
        transform:
            translateY(-10vh)
            scale(1);
        opacity: 0;
    }
}

/* =========================================================
   CURSOR GLOW
========================================================= */

.cursor-glow {

    position: fixed;

    width: 280px;

    height: 280px;

    border-radius: 50%;

    pointer-events: none;

    background:
        radial-gradient(
            circle,
            rgba(0,255,157,.11),
            transparent 68%
        );

    transform:
        translate(-50%, -50%);

    z-index: 1;

    transition:
        left .08s linear,
        top .08s linear;
}

/* =========================================================
   MAIN
========================================================= */

.page {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 35px;

    position: relative;

    z-index: 5;
}

/* =========================================================
   LOGIN CARD
========================================================= */

.login-card {

    width: min(1150px, 100%);

    min-height: 650px;

    position: relative;

    padding: 1px;

    border-radius: 32px;

    overflow: hidden;

    background:

        conic-gradient(
            from var(--angle),
            transparent,
            var(--green),
            var(--cyan),
            transparent 32%
        );

    animation:
        rotateBorder 8s linear infinite;

    box-shadow:

        0 0 0 1px rgba(0,255,157,.06),

        0 25px 100px
        rgba(0,0,0,.7),

        0 0 80px
        rgba(0,255,157,.09);

    transform-style: preserve-3d;

    transition:
        transform .15s ease;
}

@property --angle {

    syntax: "<angle>";

    initial-value: 0deg;

    inherits: false;
}

@keyframes rotateBorder {

    to {
        --angle: 360deg;
    }
}

.login-card-inner {

    min-height: 648px;

    border-radius: 31px;

    overflow: hidden;

    position: relative;

    background:

        linear-gradient(
            135deg,
            rgba(7,30,25,.96),
            rgba(2,12,10,.97)
        );

    backdrop-filter:
        blur(25px);
}

/* scanline */

.login-card-inner::after {

    content: "";

    position: absolute;

    left: 0;

    right: 0;

    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(0,255,157,.5),
            transparent
        );

    box-shadow:
        0 0 15px rgba(0,255,157,.5);

    animation:
        scan 6s linear infinite;

    pointer-events: none;
}

@keyframes scan {

    from {
        top: -5%;
    }

    to {
        top: 105%;
    }
}

/* =========================================================
   CONTENT GRID
========================================================= */

.content {

    display: grid;

    grid-template-columns:
        1.05fr
        .95fr;

    min-height: 648px;

    position: relative;

    z-index: 3;
}

/* =========================================================
   LEFT
========================================================= */

.left {

    padding: 58px;

    position: relative;

    display: flex;

    flex-direction: column;

    justify-content: center;

    border-right:
        1px solid rgba(0,255,157,.10);
}

.system {

    display: inline-flex;

    align-items: center;

    gap: 9px;

    width: fit-content;

    padding: 8px 13px;

    border-radius: 999px;

    font-size: 10px;

    letter-spacing: 2px;

    color: var(--green);

    background:
        rgba(0,255,157,.055);

    border:
        1px solid rgba(0,255,157,.17);

    margin-bottom: 25px;
}

.system-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: var(--green);

    box-shadow:
        0 0 12px var(--green);

    animation:
        onlinePulse 1.3s infinite;
}

@keyframes onlinePulse {

    50% {
        opacity: .35;
        transform: scale(.7);
    }
}

/* =========================================================
   LOGO
========================================================= */

.logo-area {

    width: 110px;

    height: 110px;

    position: relative;

    display: grid;

    place-items: center;

    margin-bottom: 28px;
}

.logo-ring {

    position: absolute;

    inset: 0;

    border-radius: 50%;

    border:
        1px solid rgba(0,255,157,.3);

    box-shadow:
        0 0 25px rgba(0,255,157,.12);

    animation:
        ringRotate 7s linear infinite;
}

.logo-ring::before,
.logo-ring::after {

    content: "";

    position: absolute;

    width: 9px;

    height: 9px;

    border-radius: 50%;

    background: var(--green);

    box-shadow:
        0 0 15px var(--green);
}

.logo-ring::before {

    top: -4px;

    left: 50%;
}

.logo-ring::after {

    bottom: -4px;

    right: 10%;
}

.ring-two {

    inset: 12px;

    border-color:
        rgba(0,234,255,.35);

    animation:
        ringRotateReverse 5s linear infinite;
}

@keyframes ringRotate {

    to {
        transform: rotate(360deg);
    }
}

@keyframes ringRotateReverse {

    to {
        transform: rotate(-360deg);
    }
}

.logo-core {

    width: 70px;

    height: 70px;

    border-radius: 22px;

    display: grid;

    place-items: center;

    font-size: 34px;

    background:

        linear-gradient(
            135deg,
            rgba(0,255,157,.20),
            rgba(0,234,255,.08)
        );

    border:
        1px solid rgba(0,255,157,.35);

    box-shadow:

        inset 0 0 25px
        rgba(0,255,157,.08),

        0 0 30px
        rgba(0,255,157,.14);

    animation:
        logoFloat 3s ease-in-out infinite;
}

@keyframes logoFloat {

    50% {
        transform:
            translateY(-7px)
            rotate(2deg);
    }
}

/* =========================================================
   TITLE
========================================================= */

.brand {

    font-size: 12px;

    font-weight: 700;

    letter-spacing: 5px;

    color: #8baea3;

    margin-bottom: 13px;
}

h1 {

    font-size:
        clamp(38px, 5vw, 66px);

    line-height: .98;

    letter-spacing: -3px;

    margin-bottom: 22px;

    max-width: 570px;

    background:

        linear-gradient(
            100deg,
            #ffffff 10%,
            #cffff0 38%,
            var(--green) 65%,
            var(--cyan)
        );

    -webkit-background-clip: text;

    background-clip: text;

    color: transparent;

    background-size: 200%;

    animation:
        gradientText 5s ease infinite;
}

@keyframes gradientText {

    50% {
        background-position: 100% 50%;
    }
}

.subtitle {

    color: #78988f;

    line-height: 1.7;

    font-size: 14px;

    max-width: 500px;

    margin-bottom: 30px;
}

/* =========================================================
   FEATURES
========================================================= */

.features {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    max-width: 560px;
}

.feature {

    padding: 15px;

    border-radius: 15px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid rgba(255,255,255,.055);

    transition:
        .3s ease;
}

.feature:hover {

    transform:
        translateY(-5px);

    border-color:
        rgba(0,255,157,.28);

    background:
        rgba(0,255,157,.045);

    box-shadow:
        0 12px 35px
        rgba(0,0,0,.25);
}

.feature-icon {

    font-size: 21px;

    margin-bottom: 9px;
}

.feature strong {

    display: block;

    font-size: 11px;

    margin-bottom: 5px;
}

.feature span {

    color: #6d8b83;

    font-size: 10px;

    line-height: 1.5;
}

/* =========================================================
   RIGHT
========================================================= */

.right {

    padding: 48px;

    display: flex;

    align-items: center;

    justify-content: center;

    background:
        linear-gradient(
            135deg,
            rgba(0,255,157,.025),
            transparent
        );
}

.form-box {

    width: 100%;

    max-width: 420px;
}

.access {

    font-size: 10px;

    letter-spacing: 3px;

    color: var(--green);

    margin-bottom: 9px;

    font-weight: 700;
}

.form-title {

    font-size: 31px;

    margin-bottom: 8px;

    letter-spacing: -1px;
}

.form-desc {

    font-size: 12px;

    color: #718e86;

    margin-bottom: 25px;
}

/* =========================================================
   ROLE
========================================================= */

.role-label {

    font-size: 10px;

    color: #7e9c94;

    text-transform: uppercase;

    letter-spacing: 1.5px;

    margin-bottom: 10px;

    display: block;
}

.roles {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 7px;

    margin-bottom: 21px;
}

.role-option {

    position: relative;

    cursor: pointer;
}

.role-option input {

    position: absolute;

    opacity: 0;

}

.role-card {

    min-height: 68px;

    border-radius: 13px;

    border:
        1px solid rgba(255,255,255,.07);

    background:
        rgba(255,255,255,.025);

    display: flex;

    flex-direction: column;

    justify-content: center;

    align-items: center;

    gap: 4px;

    transition:
        .25s ease;

    font-size: 10px;

    color: #75928a;
}

.role-icon {

    font-size: 21px;

    filter:
        grayscale(1);

    transition:
        .25s ease;
}

.role-option:hover .role-card {

    transform:
        translateY(-3px);

    border-color:
        rgba(0,255,157,.3);
}

.role-option input:checked + .role-card {

    color: #eafff8;

    border-color:
        var(--green);

    background:
        linear-gradient(
            145deg,
            rgba(0,255,157,.13),
            rgba(0,255,157,.035)
        );

    box-shadow:
        0 0 22px
        rgba(0,255,157,.10),

        inset 0 0 18px
        rgba(0,255,157,.04);
}

.role-option input:checked + .role-card .role-icon {

    filter:
        grayscale(0);

    transform:
        scale(1.12);
}

/* =========================================================
   INPUT
========================================================= */

.field {

    margin-bottom: 17px;
}

.field-label {

    display: block;

    font-size: 10px;

    color: #7f9e95;

    margin-bottom: 8px;

    letter-spacing: 1px;
}

.input-wrap {

    position: relative;
}

.input-icon {

    position: absolute;

    left: 15px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #66877e;

    font-size: 15px;

    pointer-events: none;
}

.input {

    width: 100%;

    height: 53px;

    border-radius: 14px;

    border:
        1px solid rgba(255,255,255,.075);

    outline: none;

    background:
        rgba(0,0,0,.25);

    color: #f1fffb;

    padding:
        0 45px;

    font-size: 13px;

    transition:
        .25s ease;
}

.input::placeholder {

    color: #4e6b64;
}

.input:focus {

    border-color:
        rgba(0,255,157,.48);

    box-shadow:
        0 0 0 4px
        rgba(0,255,157,.055),

        0 0 25px
        rgba(0,255,157,.06);

    background:
        rgba(0,255,157,.025);
}

.password-toggle {

    position: absolute;

    right: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    border: 0;

    background: transparent;

    color: #608078;

    cursor: pointer;

    font-size: 16px;
}

/* =========================================================
   ERROR
========================================================= */

.alert {

    display: flex;

    gap: 9px;

    align-items: center;

    padding: 12px 14px;

    margin-bottom: 18px;

    border-radius: 12px;

    color: #ffb7c6;

    font-size: 11px;

    background:
        rgba(255,60,100,.065);

    border:
        1px solid rgba(255,70,110,.18);

    animation:
        alertIn .3s ease;
}

@keyframes alertIn {

    from {
        opacity: 0;
        transform:
            translateY(-7px);
    }

    to {
        opacity: 1;
        transform:
            translateY(0);
    }
}

/* =========================================================
   BUTTON
========================================================= */

.login-btn {

    width: 100%;

    height: 56px;

    border: 0;

    border-radius: 15px;

    cursor: pointer;

    position: relative;

    overflow: hidden;

    color: #00130d;

    font-weight: 900;

    letter-spacing: 1px;

    font-size: 12px;

    background:

        linear-gradient(
            100deg,
            var(--green),
            #73ffc5,
            var(--cyan),
            var(--green)
        );

    background-size: 300%;

    animation:
        buttonGradient 5s linear infinite;

    box-shadow:
        0 10px 35px
        rgba(0,255,157,.17);

    transition:
        .25s ease;
}

@keyframes buttonGradient {

    to {
        background-position:
            300% 50%;
    }
}

.login-btn::before {

    content: "";

    position: absolute;

    top: 0;

    left: -80%;

    width: 50%;

    height: 100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.55),
            transparent
        );

    transform:
        skewX(-20deg);

    animation:
        shine 3.2s infinite;
}

@keyframes shine {

    0% {
        left: -80%;
    }

    45%,
    100% {
        left: 140%;
    }
}

.login-btn:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 15px 45px
        rgba(0,255,157,.27);
}

.login-btn:active {

    transform:
        translateY(0)
        scale(.985);
}

.btn-content {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 10px;
}

.spinner {

    width: 16px;

    height: 16px;

    border:
        2px solid
        rgba(0,0,0,.25);

    border-top-color:
        #00130d;

    border-radius: 50%;

    animation:
        spin .7s linear infinite;

    display: none;
}

@keyframes spin {

    to {
        transform:
            rotate(360deg);
    }
}

/* =========================================================
   SECURITY
========================================================= */

.security {

    margin-top: 20px;

    padding-top: 17px;

    border-top:
        1px solid rgba(255,255,255,.055);

    display: flex;

    justify-content: space-between;

    color: #526e66;

    font-size: 9px;

    letter-spacing: .4px;
}

.security span {

    display: flex;

    align-items: center;

    gap: 6px;
}

/* =========================================================
   DECORATIVE CORNERS
========================================================= */

.corner {

    position: absolute;

    width: 35px;

    height: 35px;

    border-color:
        rgba(0,255,157,.3);

    z-index: 5;

    pointer-events: none;
}

.corner.tl {

    top: 16px;

    left: 16px;

    border-top: 1px solid;

    border-left: 1px solid;
}

.corner.tr {

    top: 16px;

    right: 16px;

    border-top: 1px solid;

    border-right: 1px solid;
}

.corner.bl {

    bottom: 16px;

    left: 16px;

    border-bottom: 1px solid;

    border-left: 1px solid;
}

.corner.br {

    bottom: 16px;

    right: 16px;

    border-bottom: 1px solid;

    border-right: 1px solid;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .page {
        padding: 18px;
    }

    .content {
        grid-template-columns: 1fr;
    }

    .left {
        padding: 40px 30px;
        border-right: 0;
        border-bottom:
            1px solid rgba(0,255,157,.1);
    }

    .right {
        padding: 35px 30px 40px;
    }

    .login-card,
    .login-card-inner {
        min-height: auto;
    }

    h1 {
        font-size: 47px;
    }
}

@media (max-width: 520px) {

    .page {
        padding: 10px;
    }

    .login-card {
        border-radius: 23px;
    }

    .login-card-inner {
        border-radius: 22px;
    }

    .left {
        padding: 34px 22px;
    }

    .right {
        padding: 30px 22px;
    }

    .features {
        grid-template-columns: 1fr;
    }

    .feature {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .feature-icon {
        margin: 0;
    }

    h1 {
        font-size: 40px;
        letter-spacing: -2px;
    }

    .roles {
        grid-template-columns:
            repeat(2, 1fr);
    }

    .logo-area {
        width: 90px;
        height: 90px;
    }

    .logo-core {
        width: 60px;
        height: 60px;
    }

    .security {
        flex-direction: column;
        gap: 8px;
    }
}

/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration: .01ms !important;

        animation-iteration-count: 1 !important;

        scroll-behavior: auto !important;
    }
}

</style>

</head>

<body>

<!-- AURORA -->

<div class="aurora one"></div>
<div class="aurora two"></div>
<div class="aurora three"></div>

<!-- PARTICLES -->

<div id="particles"></div>

<!-- CURSOR -->

<div class="cursor-glow" id="cursorGlow"></div>


<div class="page">

    <div class="login-card" id="loginCard">

        <div class="login-card-inner">

            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="corner bl"></div>
            <div class="corner br"></div>


            <div class="content">

                <!-- =================================================
                     LEFT PANEL
                ================================================== -->

                <section class="left">

                    <div class="system">

                        <span class="system-dot"></span>

                        ATAYA CORE SYSTEM ONLINE

                    </div>


                    <div class="logo-area">

                        <div class="logo-ring"></div>

                        <div class="logo-ring ring-two"></div>

                        <div class="logo-core">
                            ♻️
                        </div>

                    </div>


                    <div class="brand">
                        BANK SAMPAH ATAYA
                    </div>


                    <h1>
                        Kelola Sampah.<br>
                        Kelola Masa Depan.
                    </h1>


                    <p class="subtitle">

                        Sistem manajemen Bank Sampah Ataya
                        untuk mengelola nasabah, transaksi,
                        saldo, guru, siswa, hingga pengepul
                        dalam satu ekosistem digital.

                    </p>


                    <div class="features">

                        <div class="feature">

                            <div class="feature-icon">
                                ♻️
                            </div>

                            <div>
                                <strong>
                                    ECO MANAGEMENT
                                </strong>

                                <span>
                                    Kelola data sampah
                                    secara terstruktur.
                                </span>
                            </div>

                        </div>


                        <div class="feature">

                            <div class="feature-icon">
                                ⚡
                            </div>

                            <div>
                                <strong>
                                    SMART FINANCE
                                </strong>

                                <span>
                                    Pantau transaksi
                                    dan saldo secara realtime.
                                </span>
                            </div>

                        </div>


                        <div class="feature">

                            <div class="feature-icon">
                                📊
                            </div>

                            <div>
                                <strong>
                                    DIGITAL SYSTEM
                                </strong>

                                <span>
                                    Satu akses untuk
                                    seluruh pengguna.
                                </span>
                            </div>

                        </div>

                    </div>

                </section>


                <!-- =================================================
                     RIGHT PANEL
                ================================================== -->

                <section class="right">

                    <div class="form-box">

                        <div class="access">
                            ACCESS GATE / AUTHENTICATION
                        </div>

                        <div class="form-title">
                            Selamat Datang 👋
                        </div>

                        <div class="form-desc">
                            Pilih identitas akses lalu masuk
                            ke sistem Bank Sampah Ataya.
                        </div>


                        <?php if ($pesan !== ""): ?>

                            <div class="alert">

                                <span>⚠️</span>

                                <span>
                                    <?= htmlspecialchars($pesan) ?>
                                </span>

                            </div>

                        <?php endif; ?>


                        <form
                            method="POST"
                            id="loginForm"
                        >

                            <!-- ROLE -->

                            <label class="role-label">
                                Pilih akses pengguna
                            </label>


                            <div class="roles">


                                <label class="role-option">

                                    <input
                                        type="radio"
                                        name="role"
                                        value="admin"
                                        <?= $role_lama === 'admin' ? 'checked' : '' ?>
                                        required
                                    >

                                    <div class="role-card">

                                        <span class="role-icon">
                                            👑
                                        </span>

                                        <span>
                                            Admin
                                        </span>

                                    </div>

                                </label>


                                <label class="role-option">

                                    <input
                                        type="radio"
                                        name="role"
                                        value="guru"
                                        <?= $role_lama === 'guru' ? 'checked' : '' ?>
                                    >

                                    <div class="role-card">

                                        <span class="role-icon">
                                            👨‍🏫
                                        </span>

                                        <span>
                                            Guru
                                        </span>

                                    </div>

                                </label>


                                <label class="role-option">

                                    <input
                                        type="radio"
                                        name="role"
                                        value="siswa"
                                        <?= $role_lama === 'siswa' ? 'checked' : '' ?>
                                    >

                                    <div class="role-card">

                                        <span class="role-icon">
                                            🧑‍🎓
                                        </span>

                                        <span>
                                            Siswa
                                        </span>

                                    </div>

                                </label>


                                <label class="role-option">

                                    <input
                                        type="radio"
                                        name="role"
                                        value="pengepul"
                                        <?= $role_lama === 'pengepul' ? 'checked' : '' ?>
                                    >

                                    <div class="role-card">

                                        <span class="role-icon">
                                            ♻️
                                        </span>

                                        <span>
                                            Pengepul
                                        </span>

                                    </div>

                                </label>

                            </div>


                            <!-- USERNAME -->

                            <div class="field">

                                <label class="field-label">
                                    USERNAME
                                </label>

                                <div class="input-wrap">

                                    <span class="input-icon">
                                        ◉
                                    </span>

                                    <input
                                        type="text"
                                        name="username"
                                        class="input"
                                        placeholder="Masukkan username"
                                        autocomplete="username"
                                        value="<?= htmlspecialchars($username_lama) ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- PASSWORD -->

                            <div class="field">

                                <label class="field-label">
                                    PASSWORD
                                </label>

                                <div class="input-wrap">

                                    <span class="input-icon">
                                        ◆
                                    </span>

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="input"
                                        placeholder="Masukkan password"
                                        autocomplete="current-password"
                                        required
                                    >

                                    <button
                                        type="button"
                                        class="password-toggle"
                                        id="togglePassword"
                                        aria-label="Tampilkan password"
                                    >
                                        ◉
                                    </button>

                                </div>

                            </div>


                            <!-- LOGIN -->

                            <button
                                type="submit"
                                name="login"
                                class="login-btn"
                                id="loginBtn"
                            >

                                <span class="btn-content">

                                    <span
                                        class="spinner"
                                        id="spinner"
                                    ></span>

                                    <span id="btnText">
                                        ENTER ATAYA SYSTEM
                                    </span>

                                    <span id="btnArrow">
                                        →
                                    </span>

                                </span>

                            </button>


                            <!-- SECURITY -->

                            <div class="security">

                                <span>
                                    🔒 Secure Authentication
                                </span>

                                <span>
                                    ● ATAYA CORE v2.0
                                </span>

                            </div>

                        </form>

                    </div>

                </section>

            </div>

        </div>

    </div>

</div>


<script>

/* =========================================================
   PARTICLES
========================================================= */

const particleContainer =
    document.getElementById("particles");

for (let i = 0; i < 55; i++) {

    const particle =
        document.createElement("span");

    particle.className =
        "particle";

    particle.style.left =
        Math.random() * 100 + "%";

    particle.style.animationDuration =
        (7 + Math.random() * 15) + "s";

    particle.style.animationDelay =
        (-Math.random() * 15) + "s";

    particle.style.opacity =
        .2 + Math.random() * .6;

    const size =
        1 + Math.random() * 3;

    particle.style.width =
        size + "px";

    particle.style.height =
        size + "px";

    particleContainer.appendChild(
        particle
    );
}


/* =========================================================
   CURSOR GLOW
========================================================= */

const cursorGlow =
    document.getElementById("cursorGlow");

document.addEventListener(
    "mousemove",
    function(e) {

        cursorGlow.style.left =
            e.clientX + "px";

        cursorGlow.style.top =
            e.clientY + "px";
    }
);


/* =========================================================
   3D CARD TILT
========================================================= */

const card =
    document.getElementById("loginCard");

const isTouch =
    window.matchMedia(
        "(hover: none)"
    ).matches;

const reducedMotion =
    window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    ).matches;

if (!isTouch && !reducedMotion) {

    document.addEventListener(
        "mousemove",
        function(e) {

            const rect =
                card.getBoundingClientRect();

            const x =
                e.clientX - rect.left;

            const y =
                e.clientY - rect.top;

            const centerX =
                rect.width / 2;

            const centerY =
                rect.height / 2;

            const rotateX =
                ((y - centerY) / centerY) * -2.5;

            const rotateY =
                ((x - centerX) / centerX) * 2.5;

            card.style.transform =
                `perspective(1600px)
                 rotateX(${rotateX}deg)
                 rotateY(${rotateY}deg)`;
        }
    );

    card.addEventListener(
        "mouseleave",
        function() {

            card.style.transform =
                "perspective(1600px) rotateX(0deg) rotateY(0deg)";
        }
    );
}


/* =========================================================
   PASSWORD TOGGLE
========================================================= */

const password =
    document.getElementById("password");

const togglePassword =
    document.getElementById("togglePassword");

togglePassword.addEventListener(
    "click",
    function() {

        if (password.type === "password") {

            password.type =
                "text";

            togglePassword.textContent =
                "◎";

        } else {

            password.type =
                "password";

            togglePassword.textContent =
                "◉";
        }
    }
);


/* =========================================================
   ROLE INTERACTION
========================================================= */

const roleInputs =
    document.querySelectorAll(
        'input[name="role"]'
    );

const roleIcons = {

    admin: "👑",

    guru: "👨‍🏫",

    siswa: "🧑‍🎓",

    pengepul: "♻️"
};

roleInputs.forEach(
    function(input) {

        input.addEventListener(
            "change",
            function() {

                const role =
                    this.value;

                document.body.dataset.role =
                    role;

                const title =
                    document.querySelector(
                        ".form-title"
                    );

                if (role === "admin") {

                    title.textContent =
                        "Administrator 👑";

                } else if (role === "guru") {

                    title.textContent =
                        "Portal Guru 👨‍🏫";

                } else if (role === "siswa") {

                    title.textContent =
                        "Portal Siswa 🧑‍🎓";

                } else if (role === "pengepul") {

                    title.textContent =
                        "Portal Pengepul ♻️";
                }
            }
        );
    }
);


/* =========================================================
   LOGIN LOADING
========================================================= */

const form =
    document.getElementById("loginForm");

const loginBtn =
    document.getElementById("loginBtn");

const spinner =
    document.getElementById("spinner");

const btnText =
    document.getElementById("btnText");

const btnArrow =
    document.getElementById("btnArrow");

form.addEventListener(
    "submit",
    function() {

        loginBtn.style.pointerEvents =
            "none";

        spinner.style.display =
            "block";

        btnText.textContent =
            "AUTHENTICATING...";

        btnArrow.style.display =
            "none";
    }
);


/* =========================================================
   AUTO SELECT ROLE
========================================================= */

const checkedRole =
    document.querySelector(
        'input[name="role"]:checked'
    );

if (checkedRole) {

    document.body.dataset.role =
        checkedRole.value;
}

</script>

</body>

</html>