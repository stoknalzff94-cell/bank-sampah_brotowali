<?php

session_start();
include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    die("Akses ditolak. Halaman ini khusus siswa.");
}

if (!isset($_SESSION['id_user'])) {
    die("Sesi pengguna tidak ditemukan.");
}

$id_user = (int) $_SESSION['id_user'];

$pesan = "";
$error = "";

if (isset($_POST['ubah_password'])) {

    $password_lama = $_POST['password_lama'] ?? "";
    $password_baru = $_POST['password_baru'] ?? "";
    $konfirmasi = $_POST['konfirmasi'] ?? "";

    /* =========================
       VALIDASI
    ========================= */

    if (
        $password_lama === "" ||
        $password_baru === "" ||
        $konfirmasi === ""
    ) {

        $error = "Semua kolom password wajib diisi.";

    } elseif ($password_baru !== $konfirmasi) {

        $error = "Konfirmasi password baru tidak sama.";

    } elseif (strlen($password_baru) < 5) {

        $error = "Password baru minimal 5 karakter.";

    } else {

        /* =========================
           AMBIL DATA USER
        ========================= */

        $query_user = mysqli_query(
            $koneksi,
            "SELECT password
             FROM users
             WHERE id_user = $id_user
             LIMIT 1"
        );

        if (!$query_user) {

            $error = "Terjadi kesalahan database.";

        } elseif (mysqli_num_rows($query_user) === 0) {

            $error = "Data pengguna tidak ditemukan.";

        } else {

            $user = mysqli_fetch_assoc($query_user);

            /* =========================
               CEK PASSWORD LAMA
            ========================= */

            $passwordValid = password_verify(
                $password_lama,
                $user['password']
            );

            /* Kompatibel dengan password plaintext lama */
            if (
                !$passwordValid &&
                $password_lama === $user['password']
            ) {
                $passwordValid = true;
            }

            if (!$passwordValid) {

                $error = "Password lama salah!";

            } else {

                /* =========================
                   HASH PASSWORD BARU
                ========================= */

                $password_baru_hash = password_hash(
                    $password_baru,
                    PASSWORD_DEFAULT
                );

                $password_baru_aman = mysqli_real_escape_string(
                    $koneksi,
                    $password_baru_hash
                );

                $update = mysqli_query(
                    $koneksi,
                    "UPDATE users
                     SET password = '$password_baru_aman'
                     WHERE id_user = $id_user"
                );

                if ($update) {

                    $pesan = "Password berhasil diubah.";

                } else {

                    $error = "Password gagal diubah: " .
                             mysqli_error($koneksi);

                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Keamanan Akun - Bank Sampah Ataya</title>

    <style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: "Segoe UI", Arial, sans-serif;
    background: #101827;
    color: #f1f5f9;
}

/* SIDEBAR */

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 255px;
    height: 100vh;
    padding: 28px 20px;
    background: linear-gradient(180deg, #172033, #0d1422);
    border-right: 1px solid #334155;
    display: flex;
    flex-direction: column;
    z-index: 10;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 8px 25px;
    border-bottom: 1px solid #334155;
}

.brand-icon {
    width: 48px;
    height: 48px;
    border-radius: 16px;
    background: linear-gradient(135deg, #22c55e, #06b6d4);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
}

.brand h2 {
    margin: 0;
    color: #ffffff;
    font-size: 17px;
}

.brand p {
    margin: 4px 0 0;
    font-size: 11px;
    color: #94a3b8;
}

.menu {
    margin-top: 28px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: #94a3b8;
    padding: 14px;
    margin-bottom: 8px;
    border-radius: 14px;
    transition: .25s;
}

.menu a span {
    font-size: 20px;
}

.menu a strong {
    display: block;
    font-size: 13px;
}

.menu a small {
    display: block;
    margin-top: 4px;
    font-size: 11px;
    color: #64748b;
}

.menu a:hover,
.menu a.active {
    background: linear-gradient(90deg, #166534, #15803d);
    color: #ffffff;
    transform: translateX(5px);
}

.menu a.active small {
    color: #bbf7d0;
}

.sidebar-bottom {
    margin-top: auto;
}

.profile-mini {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 15px 8px;
    border-top: 1px solid #334155;
}

.mini-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #064e3b;
    display: flex;
    align-items: center;
    justify-content: center;
}

.profile-mini strong,
.profile-mini small {
    display: block;
}

.profile-mini strong {
    font-size: 12px;
    color: #e2e8f0;
}

.profile-mini small {
    margin-top: 3px;
    font-size: 11px;
    color: #86efac;
}

.logout {
    display: block;
    text-align: center;
    text-decoration: none;
    color: #fda4af;
    background: #450a0a;
    padding: 12px;
    border-radius: 12px;
    font-size: 13px;
    transition: .2s;
}

.logout:hover {
    background: #7f1d1d;
}

/* MAIN */

.main {
    margin-left: 255px;
    padding: 35px;
    min-height: 100vh;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}

.topbar h1 {
    margin: 0;
    color: #ffffff;
    font-size: 28px;
}

.topbar p {
    margin: 8px 0 0;
    color: #94a3b8;
    font-size: 13px;
}

.status {
    padding: 10px 15px;
    background: #064e3b;
    color: #86efac;
    border: 1px solid #166534;
    border-radius: 30px;
    font-size: 12px;
    font-weight: bold;
}

/* CONTENT */

.content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 280px;
    gap: 25px;
    align-items: start;
}

.card {
    background: #1e293b;
    border: 1px solid #334155;
    border-radius: 23px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 25px;
}

.card-icon {
    width: 50px;
    height: 50px;
    border-radius: 15px;
    background: linear-gradient(135deg, #166534, #0891b2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
}

.card-header h2 {
    margin: 0;
    font-size: 20px;
    color: #f8fafc;
}

.card-header p {
    margin: 5px 0 0;
    color: #94a3b8;
    font-size: 12px;
}

/* ALERT */

.alert-success,
.alert-error {
    padding: 14px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
}

.alert-success {
    background: #052e16;
    color: #86efac;
    border: 1px solid #166534;
}

.alert-error {
    background: #450a0a;
    color: #fca5a5;
    border: 1px solid #991b1b;
}

/* FORM */

.form-group {
    margin-bottom: 21px;
}

.form-group label {
    display: block;
    margin-bottom: 9px;
    font-size: 13px;
    font-weight: 600;
    color: #cbd5e1;
}

.form-group input {
    width: 100%;
    padding: 14px 15px;
    border: 1px solid #475569;
    border-radius: 12px;
    background: #172033;
    color: #ffffff;
    font-size: 13px;
    outline: none;
    transition: .2s;
}

.form-group input:focus {
    border-color: #22c55e;
    box-shadow: 0 0 0 4px rgba(34,197,94,.12);
}

.form-group input::placeholder {
    color: #64748b;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 28px;
}

.btn {
    border: none;
    border-radius: 12px;
    padding: 13px 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    text-align: center;
    transition: .25s;
}

.btn-primary {
    background: linear-gradient(90deg, #16a34a, #0891b2);
    color: #ffffff;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(34,197,94,.25);
}

.btn-secondary {
    background: #334155;
    color: #cbd5e1;
}

.btn-secondary:hover {
    background: #475569;
}

/* INFO CARD */

.info-card {
    background: linear-gradient(135deg, #422006, #713f12);
    border: 1px solid #a16207;
    border-radius: 22px;
    padding: 25px;
}

.info-card h3 {
    margin: 0 0 15px;
    color: #fef3c7;
    font-size: 16px;
}

.info-card p {
    margin: 0 0 15px;
    color: #fde68a;
    font-size: 12px;
    line-height: 1.8;
}

.info-card ul {
    margin: 0;
    padding-left: 18px;
    color: #fde68a;
    font-size: 12px;
    line-height: 2;
}

/* RESPONSIVE */

@media (max-width: 900px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        border-right: none;
        border-bottom: 1px solid #334155;
    }

    .menu {
        margin-top: 20px;
    }

    .sidebar-bottom {
        margin-top: 15px;
    }

    .main {
        margin-left: 0;
        padding: 22px 15px;
    }

    .topbar {
        display: block;
    }

    .status {
        display: inline-block;
        margin-top: 15px;
    }

    .card {
        padding: 22px;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
}
</style>
</head>

<body>

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">♻️</div>

            <div>
                <h2>Bank Sampah</h2>
                <p>Ataya • Ruang Siswa</p>
            </div>
        </div>

        <nav class="menu">

            <a href="dashboard_siswa.php">
                <span>🏠</span>
                <div>
                    <strong>Dashboard</strong>
                    <small>Halaman utama</small>
                </div>
            </a>

            <a href="profil_siswa.php">
                <span>👤</span>
                <div>
                    <strong>Profil Saya</strong>
                    <small>Data pribadi</small>
                </div>
            </a>

            <a href="ganti_password.php" class="active">
                <span>🔐</span>
                <div>
                    <strong>Keamanan Akun</strong>
                    <small>Ganti password</small>
                </div>
            </a>

        </nav>

        <div class="sidebar-bottom">

            <div class="profile-mini">
                <div class="mini-avatar">👤</div>

                <div>
                    <strong>
                        <?= htmlspecialchars($_SESSION['nama_user']); ?>
                    </strong>

                    <small>Siswa Aktif</small>
                </div>
            </div>

            <a href="logout.php" class="logout">
                🚪 Keluar dari Akun
            </a>

        </div>

    </aside>


    <!-- MAIN -->
    <main class="main">

        <div class="topbar">

            <div>
                <h1>Keamanan Akun 🔐</h1>

                <p>
                    Kelola password untuk menjaga keamanan akunmu.
                </p>
            </div>

            <div class="status">
                🛡️ Akun Siswa
            </div>

        </div>


        <div class="content-grid">

            <!-- FORM PASSWORD -->
            <section class="card">

                <div class="card-header">

                    <div class="card-icon">🔑</div>

                    <div>
                        <h2>Ubah Password</h2>

                        <p>
                            Masukkan password lama dan password baru.
                        </p>
                    </div>

                </div>


                <?php if ($pesan !== ""): ?>

                    <div class="alert-success">
                        ✅ <?= htmlspecialchars($pesan); ?>
                    </div>

                <?php endif; ?>


                <?php if ($error !== ""): ?>

                    <div class="alert-error">
                        ❌ <?= htmlspecialchars($error); ?>
                    </div>

                <?php endif; ?>


                <form method="POST" action="">

                    <div class="form-group">
                        <label for="password_lama">
                            Password Lama
                        </label>

                        <input
                            type="password"
                            id="password_lama"
                            name="password_lama"
                            placeholder="Masukkan password lama"
                            required
                        >
                    </div>


                    <div class="form-group">
                        <label for="password_baru">
                            Password Baru
                        </label>

                        <input
                            type="password"
                            id="password_baru"
                            name="password_baru"
                            placeholder="Minimal 5 karakter"
                            minlength="5"
                            required
                        >
                    </div>


                    <div class="form-group">
                        <label for="konfirmasi">
                            Konfirmasi Password Baru
                        </label>

                        <input
                            type="password"
                            id="konfirmasi"
                            name="konfirmasi"
                            placeholder="Ulangi password baru"
                            minlength="5"
                            required
                        >
                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            name="ubah_password"
                            class="btn btn-primary"
                        >
                            🔐 Simpan Password
                        </button>

                        <a
                            href="profil_siswa.php"
                            class="btn btn-secondary"
                        >
                            ← Kembali
                        </a>

                    </div>

                </form>

            </section>


            <!-- INFORMASI -->
            <aside class="info-card">

                <h3>💡 Tips Keamanan</h3>

                <p>
                    Lindungi akunmu dengan password yang aman
                    dan jangan membagikannya kepada orang lain.
                </p>

                <ul>
                    <li>Minimal 5 karakter.</li>
                    <li>Jangan gunakan password yang mudah ditebak.</li>
                    <li>Jangan berikan password kepada teman.</li>
                    <li>Gunakan password yang mudah diingat.</li>
                </ul>

            </aside>

        </div>

    </main>

</body>

</html>