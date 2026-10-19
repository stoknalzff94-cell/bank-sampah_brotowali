<?php

session_start();
include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    die("Akses ditolak. Halaman ini khusus siswa.");
}

if (!isset($_SESSION['id_nasabah'])) {
    die("Akun siswa belum terhubung dengan data nasabah.");
}

$id_nasabah = (int) $_SESSION['id_nasabah'];

$query = mysqli_query(
    $koneksi,
    "SELECT * FROM nasabah
     WHERE id_nasabah = $id_nasabah
     LIMIT 1"
);

if (!$query || mysqli_num_rows($query) === 0) {
    die("Data nasabah tidak ditemukan.");
}

$nasabah = mysqli_fetch_assoc($query);

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profil Saya - Ruang Siswa</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #101827;
    color: #f1f5f9;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 255px;
    padding: 28px 20px;
    background: linear-gradient(180deg, #172033, #0d1422);
    border-right: 1px solid #263449;
}

.logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 45px;
}

.logo-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #22c55e, #06b6d4);
    border-radius: 16px;
    font-size: 25px;
    box-shadow: 0 0 22px rgba(34,197,94,.3);
}

.logo h2 {
    color: #ffffff;
    font-size: 17px;
}

.logo p {
    color: #94a3b8;
    font-size: 10px;
    margin-top: 4px;
}

.menu-label {
    margin: 22px 10px 12px;
    color: #64748b;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border-radius: 14px;
    color: #94a3b8;
    text-decoration: none;
    font-size: 13px;
    transition: .25s;
}

.menu a:hover,
.menu a.active {
    background: linear-gradient(90deg, #166534, #15803d);
    color: #ffffff;
    transform: translateX(5px);
    box-shadow: 0 5px 18px rgba(34,197,94,.2);
}

.user-box {
    position: absolute;
    left: 20px;
    right: 20px;
    bottom: 28px;
    padding: 15px;
    border: 1px solid #334155;
    border-radius: 16px;
    background: #1e293b;
}

.user-box small {
    color: #94a3b8;
    font-size: 10px;
}

.user-box strong {
    display: block;
    margin-top: 6px;
    color: #86efac;
    font-size: 12px;
    overflow-wrap: anywhere;
}

/* MAIN */

.main {
    width: calc(100% - 255px);
    margin-left: 255px;
    padding: 35px;
}

.topbar {
    margin-bottom: 28px;
}

.topbar h1 {
    color: #ffffff;
    font-size: 28px;
}

.topbar p {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 12px;
}

/* PROFILE HERO */

.profile-hero {
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 32px;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
    border: 1px solid #34d399;
    border-radius: 28px;
    background:
        radial-gradient(circle at 90% 10%, rgba(34,211,238,.35), transparent 30%),
        linear-gradient(135deg, #14532d, #047857, #0e7490);
    box-shadow: 0 15px 40px rgba(16,185,129,.2);
}

.profile-hero::after {
    content: "♻";
    position: absolute;
    right: 35px;
    top: 5px;
    color: white;
    font-size: 150px;
    opacity: .08;
}

.avatar {
    width: 95px;
    height: 95px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255,255,255,.4);
    border-radius: 28px;
    background: rgba(255,255,255,.15);
    font-size: 44px;
    position: relative;
    z-index: 1;
}

.profile-hero h2 {
    color: #ffffff;
    font-size: 27px;
    margin-bottom: 8px;
    position: relative;
    z-index: 1;
}

.profile-hero p {
    color: #d1fae5;
    font-size: 12px;
    position: relative;
    z-index: 1;
}

.profile-hero span {
    display: inline-block;
    margin-top: 13px;
    padding: 7px 12px;
    border-radius: 20px;
    background: rgba(255,255,255,.18);
    color: #ffffff;
    font-size: 10px;
    position: relative;
    z-index: 1;
}

/* DATA CARD */

.card {
    padding: 28px;
    border: 1px solid #334155;
    border-radius: 23px;
    background: #1e293b;
    box-shadow: 0 8px 25px rgba(0,0,0,.12);
}

.card-heading {
    margin-bottom: 23px;
}

.card-heading h3 {
    color: #f8fafc;
    font-size: 18px;
}

.card-heading p {
    margin-top: 7px;
    color: #94a3b8;
    font-size: 11px;
}

.data-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 17px;
}

.data-item {
    padding: 20px;
    border: 1px solid #334155;
    border-radius: 17px;
    background: #172033;
    transition: .25s;
}

.data-item:hover {
    border-color: #22c55e;
    transform: translateY(-3px);
}

.data-item.full {
    grid-column: 1 / -1;
}

.data-item label {
    display: block;
    margin-bottom: 10px;
    color: #64748b;
    font-size: 11px;
}

.data-item strong {
    color: #e2e8f0;
    font-size: 14px;
    overflow-wrap: anywhere;
}

/* INFO */

.info {
    margin-top: 22px;
    padding: 22px;
    border: 1px solid #a16207;
    border-radius: 20px;
    background: linear-gradient(135deg, #422006, #713f12);
}

.info h3 {
    color: #fef3c7;
    font-size: 15px;
    margin-bottom: 9px;
}

.info p {
    color: #fde68a;
    font-size: 11px;
    line-height: 1.8;
}

footer {
    padding: 30px 0 5px;
    color: #64748b;
    text-align: center;
    font-size: 10px;
}

/* RESPONSIVE */

@media (max-width: 700px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 20px;
    }

    .logo {
        margin-bottom: 20px;
    }

    .user-box {
        position: static;
        margin-top: 22px;
    }

    .main {
        width: 100%;
        margin-left: 0;
        padding: 22px 15px;
    }

    .topbar h1 {
        font-size: 21px;
    }

    .profile-hero {
        padding: 24px;
    }

    .profile-hero h2 {
        font-size: 21px;
    }

    .avatar {
        width: 72px;
        height: 72px;
        font-size: 33px;
    }

    .data-grid {
        grid-template-columns: 1fr;
    }

    .data-item.full {
        grid-column: auto;
    }

    .card {
        padding: 20px;
    }
}

@media (max-width: 450px) {
    .profile-hero {
        flex-direction: column;
        text-align: center;
    }
}
</style>
    <link rel="stylesheet" href="assets/siswa-theme.css">
</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->
    <?php include __DIR__ . '/siswa_sidebar.php'; ?>


    <!-- MAIN -->
    <main class="main">

        <?php include __DIR__ . '/siswa_topbar.php'; ?>
<span id="clock" hidden></span>


        <!-- PROFILE HERO -->
        <section class="profile-hero">

            <div class="avatar">
                👨‍🎓
            </div>

            <div>
                <h2>
                    <?= htmlspecialchars($nasabah['nama']); ?>
                </h2>

                <p>
                    Nasabah Bank Sampah Ataya
                </p>

                <span>
                    🟢 Akun Siswa Aktif
                </span>
            </div>

        </section>


        <!-- DATA PROFIL -->
        <section class="card">

            <div class="card-heading">
                <h3>Informasi Pribadi</h3>

                <p>
                    Data yang terdaftar pada sistem Bank Sampah Ataya.
                </p>
            </div>

            <div class="data-grid">

                <div class="data-item">
                    <label>Nama Lengkap</label>

                    <strong>
                        <?= htmlspecialchars($nasabah['nama']); ?>
                    </strong>
                </div>


                <div class="data-item">
                    <label>ID Nasabah</label>

                    <strong>
                        #<?= htmlspecialchars($nasabah['id_nasabah']); ?>
                    </strong>
                </div>


                <div class="data-item">
                    <label>Nomor HP</label>

                    <strong>
                        <?= htmlspecialchars($nasabah['no_hp'] ?: '-'); ?>
                    </strong>
                </div>


                <div class="data-item">
                    <label>Tanggal Terdaftar</label>

                    <strong>
                        <?= htmlspecialchars($nasabah['tanggal_daftar'] ?: '-'); ?>
                    </strong>
                </div>


                <div class="data-item full">
                    <label>Alamat</label>

                    <strong>
                        <?= nl2br(htmlspecialchars($nasabah['alamat'] ?: '-')); ?>
                    </strong>
                </div>

            </div>

        </section>


        <!-- INFORMASI -->
        <div class="info">

            <h3>💡 Informasi Profil</h3>

            <p>
                Data profil ini ditampilkan berdasarkan akun siswa
                yang sedang login. Jika terdapat data yang salah,
                silakan hubungi administrator Bank Sampah Ataya.
            </p>

        </div>


        <footer>
            © <?= date('Y'); ?> Bank Sampah Ataya — Ruang Siswa
        </footer>

    </main>

</div>

<script src="assets/siswa.js"></script>
</body>
</html>