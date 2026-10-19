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
   JIKA SUDAH LOGIN SEBAGAI PENGEPUL
===================================================== */

if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'pengepul'
) {
    header("Location: dashboard_pengepul.php");
    exit;
}


/* =====================================================
   PROSES LOGIN PENGEPUL
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
             AND role = 'pengepul'
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


        /* =================================================
           CEK USERNAME
        ================================================= */

        if (!$hasil || mysqli_num_rows($hasil) === 0) {

            $pesan = "Username pengepul tidak ditemukan.";

        } else {

            $pengepul = mysqli_fetch_assoc($hasil);


            /* =================================================
               CEK PASSWORD
            ================================================= */

            if (
                password_verify(
                    $password,
                    $pengepul['password']
                )
            ) {

                /* =============================================
                   LOGIN BERHASIL
                ============================================= */

                session_regenerate_id(true);

                $_SESSION['login'] = true;

                $_SESSION['role'] = 'pengepul';

                $_SESSION['id_user'] =
                    $pengepul['id_user'];

                $_SESSION['username'] =
                    $pengepul['username'];

                $_SESSION['nama_user'] =
                    $pengepul['nama_user'];

                $_SESSION['id_nasabah'] =
                    $pengepul['id_nasabah'];


                /* =============================================
                   MASUK DASHBOARD PENGEPUL
                ============================================= */

                header(
                    "Location: dashboard_pengepul.php"
                );

                exit;

            } else {

                $pesan = "Password pengepul salah.";
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

<title>Login Pengepul — Bank Sampah Ataya</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --bg: #0b0200;
    --green: #b82807;
    --green-light: #e53a21;
    --text: #ff4a47;
    --muted: #972a21;
    --border: rgba(224,55,27,.15);
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
            rgba(190,42,6,.15),
            transparent 30%
        ),
        radial-gradient(
            circle at 85% 85%,
            rgba(120,27,3,.18),
            transparent 35%
        ),
        var(--bg);

    overflow: hidden;
}


/* =====================================================
   GRID
===================================================== */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    background-image:
        linear-gradient(
            rgba(224,55,27,.025) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(224,55,27,.025) 1px,
            transparent 1px
        );

    background-size: 45px 45px;
}


/* =====================================================
   CARD
===================================================== */

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
            rgba(42,10,2,.94),
            rgba(17,3,0,.96)
        );

    box-shadow:
        0 40px 100px .55,
        0 0 80px rgba(180,40,5,.06);

    animation: cardIn .8s ease both;
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


/* =====================================================
   LEFT
===================================================== */

.left {

    padding: 55px;

    display: flex;

    flex-direction: column;

    justify-content: space-between;

    background:
        radial-gradient(
            circle at 10% 20%,
            rgba(177,39,7,.14),
            transparent 40%
        ),
        linear-gradient(
            145deg,
            rgba(58,13,2,.9),
            rgba(28,6,0,.96)
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
            #ad2707,
            #5a1502
        );

    box-shadow:
        0 12px 30px
        rgba(165,36,4,.25);
}

.logo-title {

    font-size: 14px;

    font-weight: 800;

    letter-spacing: 1.8px;
}

.logo-sub {

    margin-top: 4px;

    color: #a62d1d;

    font-size: 8px;

    letter-spacing: 2.5px;
}


/* =====================================================
   HERO
===================================================== */

.hero {
    margin-top: 50px;
}

.badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 8px 12px;

    border-radius: 30px;

    color: #da3822;

    background:
        rgba(172,39,7,.09);

    border:
        1px solid
        rgba(211,51,21,.14);

    font-size: 9px;

    letter-spacing: 1.5px;
}

.dot {

    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #dc3514;

    box-shadow:
        0 0 9px #dc3514;
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


/* =====================================================
   INFO
===================================================== */

.info-grid {

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
        .025;

    border:
        1px solid
        rgba(200,52,30,.08);

    transition: .3s;
}

.info:hover {

    transform: translateY(-4px);

    background:
        rgba(160,36,7,.07);

    border-color:
        rgba(210,51,24,.2);
}

.info b {

    display: block;

    color: #de3f36;

    font-size: 10px;
}

.info span {

    display: block;

    margin-top: 5px;

    color: #82241a;

    font-size: 8px;
}


/* =====================================================
   FOOTER
===================================================== */

.footer {

    color: #6a1c13;

    font-size: 9px;
}


/* =====================================================
   RIGHT
===================================================== */

.right {

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 55px;

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(142,32,7,.08),
            transparent 40%
        );
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


/* =====================================================
   HEADING
===================================================== */

.heading .mini {

    color: #d03217;

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

    color: #8b271e;

    font-size: 11px;
}


/* =====================================================
   ALERT
===================================================== */

.alert {

    margin-top: 22px;

    padding: 12px 14px;

    border-radius: 11px;

    color: #ef9b9b;

    background:
        .10;

    border:
        1px solid
        .2;

    font-size: 10px;

    animation: shake .35s ease;
}

@keyframes shake {

    0%,100% {
        transform: translateX(0);
    }

    25% {
        transform: translateX(-4px);
    }

    75% {
        transform: translateX(4px);
    }
}


/* =====================================================
   FORM
===================================================== */

form {
    margin-top: 28px;
}

.group {
    margin-bottom: 18px;
}

label {

    display: block;

    margin-bottom: 8px;

    color: #b6332a;

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

    color: #812317;

    font-size: 13px;

    z-index: 2;
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
        #3e0f06;

    background:
        rgba(18,4,0,.9);

    color: white;

    font-size: 12px;

    transition: .25s;
}

input::placeholder {
    color: #661c14;
}

input:hover {
    border-color: #591609;
}

input:focus {

    border-color: #b02a0c;

    box-shadow:
        0 0 0 3px
        rgba(176,42,12,.08);

    transform:
        translateY(-1px);
}


/* =====================================================
   PASSWORD
===================================================== */

.toggle {

    position: absolute;

    right: 13px;

    top: 50%;

    transform:
        translateY(-50%);

    border: none;

    background: none;

    color: #7d2218;

    cursor: pointer;

    font-size: 13px;
}

.toggle:hover {
    color: #d93821;
}


/* =====================================================
   BUTTON
===================================================== */

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
            #771b02,
            #ad2707,
            #771b02
        );

    background-size: 200% 100%;

    font-size: 11px;

    font-weight: 800;

    cursor: pointer;

    transition: .3s;

    box-shadow:
        0 12px 30px
        rgba(140,31,3,.2);
}

button.login:hover {

    background-position: 100% 0;

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 40px
        rgba(140,31,3,.3);
}

button.login:active {
    transform: translateY(0);
}


/* =====================================================
   NOTE
===================================================== */

.note {

    margin-top: 23px;

    text-align: center;

    color: #661b13;

    font-size: 9px;

    line-height: 1.8;
}

.note span {
    color: #90271b;
}


/* =====================================================
   RESPONSIVE
===================================================== */

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

    .info-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>


<div class="login-card">


    <!-- =================================================
         LEFT
    ================================================= -->

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
                        COLLECTOR PORTAL
                    </div>

                </div>

            </div>


            <div class="hero">

                <div class="badge">

                    <span class="dot"></span>

                    PENGEPUL PORTAL

                </div>


                <h1>

                    Kelola Sampah.

                    <span>
                        Jadi Lebih Bernilai.
                    </span>

                </h1>


                <p>

                    Portal khusus pengepul untuk
                    mengelola aktivitas pengumpulan
                    dan pemantauan sampah Bank Sampah
                    Ataya.

                </p>


                <div class="info-grid">

                    <div class="info">

                        <b>♻️ Data Sampah</b>

                        <span>
                            Pantau jenis sampah
                        </span>

                    </div>


                    <div class="info">

                        <b>📦 Pengumpulan</b>

                        <span>
                            Kelola aktivitas
                        </span>

                    </div>

                </div>

            </div>

        </div>


        <div class="footer">

            © 2026 Bank Sampah Ataya · Collector Portal

        </div>

    </section>


    <!-- =================================================
         RIGHT
    ================================================= -->

    <section class="right">

        <div class="login-box">


            <div class="heading">

                <div class="mini">
                    COLLECTOR ACCESS
                </div>

                <h2>
                    Halo, Pengepul 👋
                </h2>

                <p>
                    Masuk untuk melanjutkan ke portal.
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
                            placeholder="Masukkan username pengepul"
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

                Akses khusus pengepul Bank Sampah Ataya.<br>

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