<?php
session_start();

/* =====================================================
   KONEKSI DATABASE
===================================================== */

$koneksi = mysqli_connect(
    "localhost",
    "root",
    "",
    "banksampah_ataya"
);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}


/* =====================================================
   JIKA SUDAH LOGIN
===================================================== */

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'siswa'
) {
    header("Location: dashboard_siswa.php");
    exit;
}


/* =====================================================
   PROSES LOGIN
===================================================== */

$pesan = "";

if (isset($_POST['login'])) {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $pesan = "Username dan password wajib diisi.";

    } else {

        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT
                id_user,
                username,
                password,
                nama_user,
                role,
                id_nasabah
             FROM users
             WHERE username = ?
             AND role = 'siswa'
             LIMIT 1"
        );

        if (!$stmt) {
            die(
                "Query login gagal: " .
                mysqli_error($koneksi)
            );
        }

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $username
        );

        mysqli_stmt_execute($stmt);

        $hasil = mysqli_stmt_get_result($stmt);

        if (!$hasil) {
            die(
                "Gagal mengambil data login: " .
                mysqli_error($koneksi)
            );
        }


        /* =================================================
           USERNAME TIDAK DITEMUKAN
        ================================================= */

        if (mysqli_num_rows($hasil) === 0) {

            $pesan = "Username siswa tidak ditemukan.";

        } else {

            $siswa = mysqli_fetch_assoc($hasil);


            /* =============================================
               CEK PASSWORD
            ============================================= */

            if (
                password_verify(
                    $password,
                    $siswa['password']
                )
            ) {

                /* =========================================
                   LOGIN BERHASIL
                ========================================= */

                session_regenerate_id(true);

                $_SESSION['login'] = true;

                $_SESSION['role'] = 'siswa';

                $_SESSION['id_user'] =
                    $siswa['id_user'];

                $_SESSION['username'] =
                    $siswa['username'];

                $_SESSION['nama_user'] =
                    $siswa['nama_user'];

                $_SESSION['id_nasabah'] =
                    $siswa['id_nasabah'];


                /* =========================================
                   MASUK DASHBOARD SISWA
                ========================================= */

                header(
                    "Location: dashboard_siswa.php"
                );

                exit;

            } else {

                $pesan = "Password siswa salah.";
            }
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

<title>Login Siswa — Bank Sampah Ataya</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg: #020b09;
    --card: #071f19;
    --card2: #092920;
    --green: #19b887;
    --green-light: #68e5bf;
    --text: #effff9;
    --muted: #70978d;
    --border: rgba(91, 224, 186, .15);
}

body {

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;

    font-family:
        Inter,
        "Segoe UI",
        Arial,
        sans-serif;

    color: var(--text);

    background:
        radial-gradient(
            circle at 15% 15%,
            rgba(20, 190, 140, .15),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 85%,
            rgba(10, 120, 90, .18),
            transparent 35%
        ),
        var(--bg);

    overflow: hidden;
}


/* ==========================================
   BACKGROUND GRID
========================================== */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    background-image:
        linear-gradient(
            rgba(91,224,186,.025) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(91,224,186,.025) 1px,
            transparent 1px
        );

    background-size: 45px 45px;

    mask-image:
        linear-gradient(
            to bottom,
            transparent,
            black 25%,
            black 75%,
            transparent
        );
}


/* ==========================================
   CARD
========================================== */

.login-card {

    width: 100%;

    max-width: 950px;

    min-height: 570px;

    display: grid;

    grid-template-columns: 1fr 1fr;

    position: relative;

    z-index: 2;

    overflow: hidden;

    border-radius: 28px;

    border: 1px solid var(--border);

    background:
        linear-gradient(
            145deg,
            rgba(9,42,34,.94),
            rgba(2,17,13,.96)
        );

    box-shadow:
        0 40px 100px rgba(0,0,0,.55),
        0 0 80px rgba(18,180,135,.06);

    animation:
        cardIn .8s ease both;
}

@keyframes cardIn {

    from {
        opacity: 0;
        transform:
            translateY(25px)
            scale(.97);
    }

    to {
        opacity: 1;
        transform:
            translateY(0)
            scale(1);
    }
}


/* ==========================================
   LEFT
========================================== */

.left {

    padding: 55px;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(26,177,133,.14),
            transparent 40%
        ),
        linear-gradient(
            145deg,
            rgba(7,58,46,.9),
            rgba(3,28,23,.96)
        );

    border-right: 1px solid var(--border);
}

.logo {

    display: flex;

    align-items: center;

    gap: 13px;
}

.logo-icon {

    width: 52px;
    height: 52px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 16px;

    font-size: 25px;

    background:
        linear-gradient(
            145deg,
            #19ad82,
            #075a46
        );

    box-shadow:
        0 12px 30px
        rgba(15,165,122,.25);
}

.logo-title {

    font-size: 14px;

    font-weight: 800;

    letter-spacing: 1.8px;
}

.logo-sub {

    margin-top: 4px;

    color: #62a697;

    font-size: 8px;

    letter-spacing: 2.5px;
}

.hero {

    margin-top: 50px;
}

.badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 12px;

    border-radius: 30px;

    color: #73dabc;

    background:
        rgba(24,172,130,.09);

    border:
        1px solid
        rgba(70,211,171,.14);

    font-size: 9px;

    letter-spacing: 1.5px;
}

.dot {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #45dcb2;

    box-shadow:
        0 0 9px #45dcb2;
}

.hero h1 {

    margin-top: 22px;

    font-size: 42px;

    line-height: 1.08;

    letter-spacing: -2px;
}

.hero h1 span {

    display: block;

    color: var(--green-light);
}

.hero p {

    margin-top: 20px;

    max-width: 390px;

    color: var(--muted);

    font-size: 12px;

    line-height: 1.8;
}

.student-info {

    margin-top: 30px;

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 10px;
}

.info {

    padding: 15px;

    border-radius: 13px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid
        rgba(100,200,175,.08);
}

.info b {

    display: block;

    color: #b6ded3;

    font-size: 10px;
}

.info span {

    display: block;

    margin-top: 5px;

    color: #598279;

    font-size: 8px;
}

.footer {

    color: #416a60;

    font-size: 9px;
}


/* ==========================================
   RIGHT
========================================== */

.right {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 55px;
}

.login-box {

    width: 100%;

    max-width: 340px;

    animation:
        loginIn .8s .15s ease both;
}

@keyframes loginIn {

    from {
        opacity: 0;
        transform:
            translateX(25px);
    }

    to {
        opacity: 1;
        transform:
            translateX(0);
    }
}

.heading .mini {

    color: #4ed0a7;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 2px;

    margin-bottom: 11px;
}

.heading h2 {

    font-size: 29px;

    letter-spacing: -.8px;
}

.heading p {

    margin-top: 8px;

    color: #668b82;

    font-size: 11px;
}


/* ==========================================
   ALERT
========================================== */

.alert {

    margin-top: 22px;

    padding: 12px 14px;

    border-radius: 11px;

    color: #ef9b9b;

    background:
        rgba(180,50,50,.10);

    border:
        1px solid
        rgba(220,90,90,.2);

    font-size: 10px;
}


/* ==========================================
   FORM
========================================== */

form {

    margin-top: 28px;
}

.group {

    margin-bottom: 18px;
}

label {

    display: block;

    margin-bottom: 8px;

    color: #8fb6ac;

    font-size: 10px;

    font-weight: 700;

    letter-spacing: .7px;
}

.input {

    position: relative;
}

.input-icon {

    position: absolute;

    left: 15px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #4d8176;

    font-size: 13px;
}

input {

    width: 100%;

    height: 52px;

    padding:
        0 43px;

    outline: none;

    border-radius: 12px;

    border:
        1px solid
        #173e35;

    background:
        rgba(3,18,14,.9);

    color: white;

    font-size: 12px;

    transition: .25s;
}

input::placeholder {
    color: #45665e;
}

input:focus {

    border-color: #2ab08c;

    box-shadow:
        0 0 0 3px
        rgba(42,176,140,.08);
}


/* ==========================================
   PASSWORD TOGGLE
========================================== */

.toggle {

    position: absolute;

    right: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    border: none;

    background: none;

    color: #527d73;

    cursor: pointer;

    font-size: 13px;
}


/* ==========================================
   BUTTON
========================================== */

button.login {

    width: 100%;

    height: 52px;

    margin-top: 6px;

    border: none;

    border-radius: 12px;

    color: white;

    background:
        linear-gradient(
            110deg,
            #08775b,
            #19ad84,
            #08775b
        );

    background-size: 200% 100%;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;

    transition: .3s;

    box-shadow:
        0 12px 30px
        rgba(10,140,105,.2);
}

button.login:hover {

    background-position: 100% 0;

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 40px
        rgba(10,140,105,.3);
}


/* ==========================================
   NOTE
========================================== */

.note {

    margin-top: 23px;

    text-align: center;

    color: #41665d;

    font-size: 9px;

    line-height: 1.8;
}

.note span {
    color: #5d9084;
}


/* ==========================================
   RESPONSIVE
========================================== */

@media(max-width: 800px) {

    body {
        overflow: auto;
    }

    .login-card {

        grid-template-columns: 1fr;

        max-width: 500px;
    }

    .left {

        min-height: 430px;

        padding: 35px;

        border-right: 0;

        border-bottom:
            1px solid var(--border);
    }

    .right {
        padding: 35px;
    }

    .hero h1 {
        font-size: 35px;
    }
}

@media(max-width: 480px) {

    .left,
    .right {
        padding: 27px;
    }

    .hero h1 {
        font-size: 30px;
    }

    .student-info {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>


<div class="login-card">


    <!-- =========================================
         LEFT
    ========================================== -->

    <section class="left">

        <div>

            <div class="logo">

                <div class="logo-icon">
                    ♻
                </div>

                <div>

                    <div class="logo-title">
                        BANK SAMPAH ATAYA
                    </div>

                    <div class="logo-sub">
                        STUDENT PORTAL
                    </div>

                </div>

            </div>


            <div class="hero">

                <div class="badge">

                    <span class="dot"></span>

                    SISWA PORTAL

                </div>


                <h1>

                    Nabung Sampah.

                    <span>
                        Dapatkan Manfaat.
                    </span>

                </h1>


                <p>

                    Pantau aktivitas menabung sampah,
                    saldo, transaksi, dan riwayat
                    penarikanmu melalui Bank Sampah
                    Ataya.

                </p>


                <div class="student-info">

                    <div class="info">

                        <b>♻ Setor Sampah</b>

                        <span>
                            Pantau transaksi
                        </span>

                    </div>


                    <div class="info">

                        <b>◈ Saldo</b>

                        <span>
                            Cek saldo kamu
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <div class="footer">

            © 2026 Bank Sampah Ataya · Student Portal

        </div>

    </section>


    <!-- =========================================
         RIGHT
    ========================================== -->

    <section class="right">

        <div class="login-box">


            <div class="heading">

                <div class="mini">
                    STUDENT ACCESS
                </div>

                <h2>
                    Halo, Siswa 👋
                </h2>

                <p>
                    Masuk untuk melihat akunmu.
                </p>

            </div>


            <?php if ($pesan !== ''): ?>

                <div class="alert">

                    ⚠️
                    <?= htmlspecialchars($pesan) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                autocomplete="off"
            >


                <div class="group">

                    <label>
                        USERNAME
                    </label>

                    <div class="input">

                        <span class="input-icon">
                            ◉
                        </span>

                        <input
                            type="text"
                            name="username"
                            placeholder="Masukkan username siswa"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>


                <div class="group">

                    <label>
                        PASSWORD
                    </label>

                    <div class="input">

                        <span class="input-icon">
                            ◆
                        </span>

                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle"
                            id="toggle"
                        >
                            👁
                        </button>

                    </div>

                </div>


                <button
                    type="submit"
                    name="login"
                    class="login"
                >
                    Masuk ke Portal →
                </button>


            </form>


            <div class="note">

                Akses khusus siswa Bank Sampah Ataya.<br>

                <span>
                    Jaga username dan password kamu.
                </span>

            </div>


        </div>

    </section>


</div>


<script>

const password =
    document.getElementById("password");

const toggle =
    document.getElementById("toggle");

toggle.addEventListener(
    "click",
    function() {

        if (password.type === "password") {

            password.type = "text";

            toggle.innerHTML = "🙈";

        } else {

            password.type = "password";

            toggle.innerHTML = "👁";
        }

    }
);

</script>

</body>

</html>