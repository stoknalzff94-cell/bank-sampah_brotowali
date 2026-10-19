<?php
session_start();
if (($_SESSION['login'] ?? false) !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/koneksi.php';
$nama_admin = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Administrator';
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function set_flash($p, $t = 'success') { $_SESSION['flash_akun'] = [$p, $t]; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        set_flash('Sesi tidak valid. Muat ulang halaman lalu coba lagi.', 'error');
    } elseif ($aksi === 'tambah') {
        $role = $_POST['role'] ?? '';
        $nama = trim($_POST['nama_user'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $id_nasabah = (int) ($_POST['id_nasabah'] ?? 0);
        $id_nasabah = ($role === 'pengepul' || $id_nasabah <= 0) ? null : $id_nasabah;

        if (!in_array($role, ['guru', 'siswa', 'pengepul'], true)) {
            set_flash('Pilih peran akun.', 'error');
        } elseif ($nama === '' || !preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
            set_flash('Isi nama, dan username 3-30 karakter (huruf, angka, titik, garis bawah, atau strip).', 'error');
        } elseif (strlen($password) < 6) {
            set_flash('Password minimal 6 karakter.', 'error');
        } elseif ($role !== 'pengepul' && $id_nasabah === null) {
            set_flash('Akun guru dan siswa harus dihubungkan ke data nasabah. Tambah nasabah dulu jika belum ada.', 'error');
        } else {
            $stmt = mysqli_prepare($koneksi, "SELECT id_user FROM users WHERE username = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $ada = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($ada) {
                set_flash('Username sudah dipakai. Pilih username lain.', 'error');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($koneksi,
                    "INSERT INTO users (username, password, role, nama_user, id_nasabah) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssssi", $username, $hash, $role, $nama, $id_nasabah);
                if (mysqli_stmt_execute($stmt)) {
                    set_flash('Akun ' . $username . ' berhasil dibuat.');
                } else {
                    set_flash('Akun gagal disimpan. Coba lagi.', 'error');
                }
                mysqli_stmt_close($stmt);
            }
        }
    } elseif ($aksi === 'reset') {
        $id = (int) ($_POST['id_user'] ?? 0);
        $password = $_POST['password_baru'] ?? '';
        if ($id <= 0 || strlen($password) < 6) {
            set_flash('Password baru minimal 6 karakter.', 'error');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id_user = ?");
            mysqli_stmt_bind_param($stmt, "si", $hash, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            set_flash('Password berhasil diganti.');
        }
    } elseif ($aksi === 'hapus') {
        $id = (int) ($_POST['id_user'] ?? 0);
        $stmt = mysqli_prepare($koneksi, "DELETE FROM users WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        set_flash('Akun dihapus. Riwayat transaksinya tetap tersimpan.');
    }
    header("Location: akun.php");
    exit;
}

$flash = $_SESSION['flash_akun'] ?? null;
unset($_SESSION['flash_akun']);

$nasabah = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id_nasabah, nama FROM nasabah ORDER BY nama ASC"), MYSQLI_ASSOC);
$akun = mysqli_fetch_all(mysqli_query($koneksi,
    "SELECT u.id_user, u.username, u.role, u.nama_user, n.nama AS nama_nasabah
     FROM users u LEFT JOIN nasabah n ON n.id_nasabah = u.id_nasabah
     ORDER BY u.role, u.username"), MYSQLI_ASSOC);
$label = ['guru' => 'Guru', 'siswa' => 'Siswa', 'pengepul' => 'Pengepul'];
?>
<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Kelola Akun - Bank Sampah</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', sans-serif;

    background:
        radial-gradient(
            circle at top right,
            rgba(0,190,140,.07),
            transparent 28%
        ),
        #031f18;

    color: #f3fffb;

    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
}

button,
input,
select,
textarea {
    font-family: inherit;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {
    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 260px;

    background:
        linear-gradient(
            180deg,
            #062c24 0%,
            #05271f 55%,
            #03221b 100%
        );

    border-right: 1px solid #16483b;

    z-index: 1000;

    padding: 30px 17px 22px;

    display: flex;
    flex-direction: column;
}

.brand {
    padding: 0 11px 28px;

    display: flex;
    align-items: center;

    gap: 14px;
}

.brand-icon {
    width: 45px;
    height: 45px;

    border-radius: 13px;

    background:
        linear-gradient(
            135deg,
            #18d49b,
            #0ab87e
        );

    display: flex;
    align-items: center;
    justify-content: center;

    color: #05271f;

    font-size: 21px;
    font-weight: 800;

    box-shadow:
        0 10px 25px rgba(0,205,150,.17);
}

.brand-text h2 {
    font-size: 18px;
    letter-spacing: .5px;
}

.brand-text span {
    color: #69a99a;

    font-size: 10px;

    margin-top: 3px;

    display: block;
}

.menu-title {
    color: #5c998a;

    font-size: 10px;
    font-weight: 700;

    letter-spacing: 2px;

    margin: 4px 11px 13px;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.menu a {
    min-height: 49px;

    display: flex;
    align-items: center;

    gap: 14px;

    padding: 0 20px;

    color: #77afa2;

    font-size: 13px;
    font-weight: 500;

    border: 1px solid transparent;

    border-radius: 13px;

    position: relative;

    transition: .2s;
}

.menu a:hover {
    color: #eafff9;

    background:
        rgba(18,163,123,.09);
}

.menu a.active {
    color: #ffffff;

    background:
        linear-gradient(
            90deg,
            rgba(16,159,120,.25),
            rgba(13,110,87,.20)
        );

    border-color: #14755e;
}

.menu a.active::before {
    content: "";

    position: absolute;

    left: -1px;

    top: 7px;
    bottom: 7px;

    width: 3px;

    background: #21e0a4;

    border-radius:
        0 4px 4px 0;

    box-shadow:
        0 0 12px rgba(33,224,164,.5);
}

.menu-icon {
    width: 17px;

    text-align: center;

    font-size: 15px;
}

.sidebar-line {
    height: 1px;

    background: #124237;

    margin: 23px 0 20px;
}

.account-title {
    color: #5c998a;

    font-size: 10px;
    font-weight: 700;

    letter-spacing: 2px;

    margin: 0 11px 11px;
}

.account-card {
    background:
        rgba(18,103,81,.35);

    border: 1px solid #12634f;

    border-radius: 14px;

    padding: 12px;

    display: flex;
    align-items: center;

    gap: 11px;
}

.account-avatar {
    width: 35px;
    height: 35px;

    border-radius: 50%;

    background: #49dfa8;

    color: #073125;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: 800;

    font-size: 14px;
}

.account-info small {
    color: #5fa394;

    font-size: 8px;

    display: block;

    margin-bottom: 2px;
}

.account-info strong {
    font-size: 11px;

    color: #ffffff;
}

.logout {
    display: block;

    margin: 17px 11px 0;

    color: #69a99a;

    font-size: 11px;
}

.logout:hover {
    color: #ffffff;
}


/* =========================================================
   MAIN
========================================================= */

.main {
    margin-left: 260px;

    min-height: 100vh;
}


/* =========================================================
   TOPBAR
========================================================= */

.topbar {
    height: 82px;

    border-bottom: 1px solid #123d32;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 35px;

    background:
        rgba(3,31,24,.75);
}

.top-title h3 {
    font-size: 14px;

    font-weight: 700;
}

.top-title p {
    margin-top: 6px;

    color: #63988c;

    font-size: 9px;
}

.system-status {
    border: 1px solid #145b49;

    background:
        rgba(8,74,58,.28);

    border-radius: 30px;

    padding: 9px 14px;

    color: #72b5a4;

    font-size: 9px;

    display: flex;

    align-items: center;

    gap: 8px;
}

.status-dot {
    width: 6px;
    height: 6px;

    border-radius: 50%;

    background: #22e3a3;

    box-shadow:
        0 0 10px rgba(34,227,163,.8);
}


/* =========================================================
   CONTENT
========================================================= */

.content {
    padding: 32px 36px 28px;
}

.page-head {
    display: flex;

    justify-content: space-between;

    align-items: flex-end;

    margin-bottom: 25px;
}

.breadcrumb {
    color: #35c89a;

    font-size: 9px;

    letter-spacing: 2px;

    font-weight: 700;

    margin-bottom: 10px;
}

.page-head h1 {
    font-size: 29px;

    line-height: 1.1;

    font-weight: 800;

    letter-spacing: -.8px;
}

.page-description {
    color: #65998d;

    font-size: 11px;

    margin-top: 9px;
}

.btn-primary {
    border: 0;

    cursor: pointer;

    background:
        linear-gradient(
            135deg,
            #17c993,
            #0b9d72
        );

    color: #ffffff;

    padding: 12px 18px;

    border-radius: 11px;

    font-size: 11px;

    font-weight: 700;

    box-shadow:
        0 9px 22px rgba(0,185,133,.14);

    transition: .2s;
}

.btn-primary:hover {
    transform: translateY(-1px);

    filter: brightness(1.08);
}


/* =========================================================
   STATS
========================================================= */

.stats {
    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 14px;

    margin-bottom: 20px;
}

.stat-card {
    min-height: 138px;

    position: relative;

    overflow: hidden;

    background:
        linear-gradient(
            135deg,
            rgba(8,52,42,.90),
            rgba(5,42,34,.88)
        );

    border: 1px solid #155341;

    border-radius: 16px;

    padding: 24px 21px;
}

.stat-card::after {
    content: "";

    position: absolute;

    right: -15px;
    bottom: -28px;

    width: 85px;
    height: 85px;

    border-radius: 50%;

    background:
        rgba(16,121,91,.12);
}

.stat-label {
    color: #61a698;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: 1.5px;
}

.stat-number {
    font-size: 23px;

    font-weight: 800;

    margin-top: 14px;
}

.stat-info {
    color: #4f8d80;

    font-size: 8px;

    margin-top: 8px;
}


/* =========================================================
   SEARCH
========================================================= */

.search-box {
    background:
        rgba(5,47,37,.72);

    border: 1px solid #124d3d;

    border-radius: 14px;

    padding: 9px;

    display: flex;

    margin-bottom: 18px;
}

.search-box form {
    display: flex;

    width: 100%;

    gap: 9px;
}

.search-input {
    flex: 1;

    height: 39px;

    border: 1px solid #195743;

    border-radius: 10px;

    background: #03271f;

    color: #dffff5;

    padding: 0 13px;

    outline: none;

    font-size: 10px;
}

.search-input::placeholder {
    color: #477c70;
}

.search-input:focus {
    border-color: #20a981;
}

.btn-search {
    width: 72px;

    border: 1px solid #17604c;

    border-radius: 10px;

    background: #0b4939;

    color: #83c8b8;

    cursor: pointer;

    font-size: 10px;

    font-weight: 600;
}

.btn-search:hover {
    background: #0e5945;

    color: #ffffff;
}


/* =========================================================
   TABLE
========================================================= */

.table-card {
    background:
        rgba(4,45,35,.78);

    border: 1px solid #15513f;

    border-radius: 17px;

    overflow: hidden;
}

.table-head {
    min-height: 55px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 21px;

    border-bottom: 1px solid #12483a;
}

.table-head h3 {
    font-size: 12px;
}

.table-head span {
    color: #57998b;

    font-size: 9px;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 1000px;
}

thead {
    background:
        rgba(12,88,68,.43);
}

th {
    text-align: left;

    padding: 12px 17px;

    color: #65a999;

    font-size: 8px;

    font-weight: 700;

    letter-spacing: .8px;
}

td {
    padding: 15px 17px;

    border-top: 1px solid #103f33;

    color: #74a99e;

    font-size: 9px;
}

tbody tr:hover {
    background:
        rgba(20,110,85,.06);
}

.number {
    color: #49887a;
}

.id-badge {
    display: inline-block;

    padding: 3px 7px;

    margin-bottom: 6px;

    background:
        rgba(16,124,92,.28);

    color: #52d4a7;

    border-radius: 5px;

    font-size: 7px;

    font-weight: 700;
}

.nasabah-name {
    color: #ffffff;

    font-size: 10px;

    font-weight: 700;
}

.alamat {
    max-width: 260px;

    color: #75a99d;

    line-height: 1.5;
}

.no-hp {
    color: #9ad4c6;
}

.tanggal {
    color: #74aaa0;
}


/* =========================================================
   ACTION
========================================================= */

.actions {
    display: flex;

    gap: 6px;

    flex-wrap: wrap;
}

.btn-action {
    border-radius: 7px;

    padding: 7px 10px;

    font-size: 8px;

    font-weight: 700;

    display: inline-flex;

    align-items: center;

    gap: 4px;

    transition: .2s;
}

.btn-edit {
    background:
        rgba(16,112,88,.20);

    border: 1px solid #17644f;

    color: #61d6ad;
}

.btn-edit:hover {
    background:
        rgba(20,137,105,.32);
}

.btn-delete {
    background:
        rgba(128,25,55,.25);

    border: 1px solid #703044;

    color: #e86b8a;
}

.btn-delete:hover {
    background:
        rgba(158,30,65,.38);
}


/* =========================================================
   ALERT
========================================================= */

.alert {
    margin-bottom: 18px;

    padding: 12px 15px;

    border-radius: 10px;

    font-size: 10px;

    font-weight: 600;
}

.alert.success {
    color: #8ae7c5;

    background:
        rgba(15,132,96,.18);

    border: 1px solid #17684f;
}

.alert.error {
    color: #ff9caf;

    background:
        rgba(144,27,59,.16);

    border: 1px solid #713246;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    text-align: center;

    padding: 45px 20px;

    color: #5c9588;

    font-size: 11px;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    text-align: center;

    color: #397467;

    font-size: 8px;

    padding: 25px 0 3px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {
    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(0,0,0,.65);

    z-index: 2000;

    align-items: center;

    justify-content: center;

    padding: 20px;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;

    max-width: 500px;

    max-height: 90vh;

    overflow-y: auto;

    background:
        linear-gradient(
            145deg,
            #073127,
            #04251e
        );

    border: 1px solid #19604b;

    border-radius: 17px;

    box-shadow:
        0 25px 70px rgba(0,0,0,.5);

    padding: 24px;
}

.modal-head {
    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 22px;
}

.modal-head h2 {
    font-size: 16px;
}

.modal-close {
    width: 30px;
    height: 30px;

    border-radius: 8px;

    border: 1px solid #1a5745;

    background: #073b2f;

    color: #7bb5a7;

    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;

    color: #71aa9d;

    font-size: 9px;

    font-weight: 600;

    margin-bottom: 7px;
}

.form-control {
    width: 100%;

    min-height: 40px;

    border: 1px solid #195743;

    border-radius: 9px;

    background: #03271f;

    color: #e9fff8;

    padding: 0 12px;

    outline: none;

    font-size: 10px;
}

textarea.form-control {
    padding: 11px 12px;

    resize: vertical;

    min-height: 80px;
}

.form-control:focus {
    border-color: #20a981;
}

.modal-actions {
    display: flex;

    justify-content: flex-end;

    gap: 8px;

    margin-top: 22px;
}

.btn-cancel {
    border: 1px solid #1a5745;

    background: transparent;

    color: #72a99c;

    border-radius: 9px;

    padding: 10px 15px;

    cursor: pointer;

    font-size: 10px;
}

.btn-save {
    border: 0;

    background:
        linear-gradient(
            135deg,
            #16c892,
            #0b9f74
        );

    color: #ffffff;

    border-radius: 9px;

    padding: 10px 17px;

    cursor: pointer;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .stats {
        grid-template-columns:
            repeat(2,1fr);
    }
}

@media (max-width: 1050px) {

    .sidebar {
        width: 225px;
    }

    .main {
        margin-left: 225px;
    }

    .content {
        padding: 28px 25px;
    }
}

@media (max-width: 850px) {

    .sidebar {
        position: relative;

        width: 100%;
        height: auto;

        border-right: 0;

        border-bottom: 1px solid #16483b;
    }

    .main {
        margin-left: 0;
    }

    .menu {
        display: grid;

        grid-template-columns:
            repeat(2,1fr);
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .topbar {
        padding: 0 20px;
    }
}

@media (max-width: 600px) {

    .menu {
        grid-template-columns: 1fr;
    }

    .page-head {
        flex-direction: column;

        align-items: flex-start;

        gap: 18px;
    }

    .btn-primary {
        width: 100%;
    }

    .content {
        padding: 22px 15px;
    }

    .topbar {
        height: auto;

        padding: 17px 15px;

        gap: 15px;

        flex-direction: column;

        align-items: flex-start;
    }
}

</style>

<link rel="stylesheet" href="assets/admin-theme.css">
</head>

<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include __DIR__ . '/admin_sidebar.php'; ?>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


    <!-- TOPBAR -->

    <?php $tb_judul = 'Kelola Akun'; $tb_sub = 'Buat dan atur akun guru, siswa, dan pengepul'; include __DIR__ . '/admin_topbar.php'; ?>


    <!-- CONTENT -->

    <section class="content">
        <?php if ($flash): ?>
            <div class="alert <?= $flash[1] === 'error' ? 'error' : 'success' ?>"><?= e($flash[0]) ?></div>
        <?php endif; ?>

        <div class="table-card" style="padding:22px 24px;margin-bottom:24px">
            <h3 style="margin:0 0 4px">Buat akun baru</h3>
            <p style="margin:0 0 16px;opacity:.75">Akun guru dan siswa dihubungkan ke data nasabah agar setoran dan saldonya tercatat.</p>
            <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:end">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="aksi" value="tambah">
                <div class="form-group"><label for="role">Peran</label>
                    <select id="role" name="role" required onchange="document.getElementById('nsb').disabled = this.value === 'pengepul'">
                        <option value="">Pilih peran</option>
                        <option value="siswa">Siswa</option>
                        <option value="guru">Guru</option>
                        <option value="pengepul">Pengepul</option>
                    </select></div>
                <div class="form-group"><label for="nama_user">Nama lengkap</label>
                    <input id="nama_user" name="nama_user" required maxlength="100"></div>
                <div class="form-group"><label for="username">Username</label>
                    <input id="username" name="username" required maxlength="30" autocomplete="off"></div>
                <div class="form-group"><label for="password">Password</label>
                    <input id="password" name="password" type="password" required minlength="6" autocomplete="new-password"></div>
                <div class="form-group"><label for="nsb">Nasabah terkait</label>
                    <select id="nsb" name="id_nasabah">
                        <option value="">Pilih nasabah</option>
                        <?php foreach ($nasabah as $n): ?>
                            <option value="<?= (int) $n['id_nasabah'] ?>"><?= e($n['nama']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><button class="btn-primary" type="submit">Buat akun</button></div>
            </form>
        </div>

        <div class="table-card">
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Nasabah</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php if (!$akun): ?>
                    <tr><td colspan="5">Belum ada akun. Buat akun pertama lewat form di atas.</td></tr>
                <?php else: foreach ($akun as $a): ?>
                    <tr>
                        <td><?= e($a['nama_user']) ?></td>
                        <td><?= e($a['username']) ?></td>
                        <td><?= e($label[$a['role']] ?? $a['role']) ?></td>
                        <td><?= e($a['nama_nasabah'] ?? '-') ?></td>
                        <td>
                            <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="id_user" value="<?= (int) $a['id_user'] ?>">
                                <input type="password" name="password_baru" placeholder="Password baru" minlength="6" style="width:150px" aria-label="Password baru untuk <?= e($a['username']) ?>">
                                <button class="btn-primary" type="submit" name="aksi" value="reset">Ganti password</button>
                                <button class="btn-primary" type="submit" name="aksi" value="hapus" style="background:#7a2b3a"
                                        onclick="return confirm('Hapus akun <?= e($a['username']) ?>?')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </section>
</main>
</body>
</html>
