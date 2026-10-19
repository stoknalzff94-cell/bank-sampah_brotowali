<?php
/* Pemasangan sekali jalan: membuat database, tabel, dan akun admin pertama.
   Setelah berhasil, file ini menghapus dirinya sendiri. */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB = "banksampah_ataya";
$pesan = "";
$sukses = false;
$sudah = false;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

try {
    $db = new mysqli("localhost", "root", "");
    $db->set_charset("utf8mb4");
    $db->query("CREATE DATABASE IF NOT EXISTS `$DB` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $db->select_db($DB);

    $tabel = [
        "CREATE TABLE IF NOT EXISTS admin (
            id_admin INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS nasabah (
            id_nasabah INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(100) NOT NULL,
            alamat TEXT NULL,
            no_hp VARCHAR(20) NULL,
            tanggal_daftar DATE NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS users (
            id_user INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            nama_user VARCHAR(100) NOT NULL,
            id_nasabah INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS sampah (
            id_sampah INT AUTO_INCREMENT PRIMARY KEY,
            nama_sampah VARCHAR(100) NOT NULL,
            harga DECIMAL(12,2) NOT NULL DEFAULT 0,
            satuan VARCHAR(20) NOT NULL DEFAULT 'kg',
            status VARCHAR(20) NOT NULL DEFAULT 'pending'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS transaksi (
            id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
            id_nasabah INT NOT NULL,
            id_sampah INT NOT NULL,
            tanggal DATETIME NOT NULL,
            berat DECIMAL(12,2) NOT NULL,
            harga DECIMAL(12,2) NOT NULL,
            total DECIMAL(14,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            INDEX (id_nasabah), INDEX (id_sampah), INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS tarik_saldo (
            id_tarik INT AUTO_INCREMENT PRIMARY KEY,
            id_nasabah INT NOT NULL,
            tanggal DATETIME NOT NULL,
            jumlah DECIMAL(14,2) NOT NULL,
            keterangan VARCHAR(255) NULL,
            INDEX (id_nasabah)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS pembelian_pengepul (
            id_pembelian INT AUTO_INCREMENT PRIMARY KEY,
            id_user INT NOT NULL,
            id_sampah INT NOT NULL,
            tanggal DATETIME NOT NULL,
            berat DECIMAL(12,2) NOT NULL,
            harga DECIMAL(12,2) NOT NULL,
            total DECIMAL(14,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            INDEX (id_user), INDEX (id_sampah), INDEX (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tabel as $sql) {
        $db->query($sql);
    }

    $sudah = $db->query("SELECT COUNT(*) AS n FROM admin")->fetch_assoc()['n'] > 0;

    if (!$sudah && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $nama = trim($_POST['nama'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $user) || $nama === '' || strlen($pass) < 6) {
            $pesan = "Isi nama, username 3-30 karakter, dan password minimal 6 karakter.";
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $db->prepare("INSERT INTO admin (username, password, nama) VALUES (?, ?, ?)");
            $st->bind_param("sss", $user, $hash, $nama);
            $st->execute();

            /* Jenis sampah dasar (langsung disetujui) supaya bisa langsung dipakai */
            if (!empty($_POST['contoh'])) {
                $jenis = [["Plastik PET", 3000], ["Kertas / Kardus", 1500], ["Botol Kaca", 500], ["Kaleng", 4000]];
                $st = $db->prepare("INSERT INTO sampah (nama_sampah, harga, satuan, status) VALUES (?, ?, 'kg', 'disetujui')");
                foreach ($jenis as [$n, $hg]) {
                    $st->bind_param("sd", $n, $hg);
                    $st->execute();
                }
            }
            $sukses = true;
            $sudah = true;
        }
    }
} catch (Throwable $ex) {
    $pesan = "Tidak bisa terhubung ke MySQL. Pastikan MySQL di XAMPP sudah Start. (" . $ex->getMessage() . ")";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pasang Bank Sampah</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f2f5f3;font:16px/1.55 "Segoe UI",system-ui,sans-serif;color:#1b2a27}
.box{width:min(440px,92vw);background:#fff;border:1px solid #d8e0dc;border-radius:12px;padding:28px}
h1{margin:0 0 6px;font-size:24px} p{margin:0 0 16px;color:#5d726c}
label{display:block;margin:12px 0 4px;font-weight:600;font-size:14px}
input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #d8e0dc;border-radius:8px;font:inherit}
button,.b{display:inline-block;margin-top:18px;padding:11px 18px;border:0;border-radius:8px;background:#12332e;color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.err{background:#f6d9d6;color:#b3372f;padding:10px 14px;border-radius:8px;margin-bottom:14px}
.ok{background:#d6efe1;color:#1f7a4d;padding:10px 14px;border-radius:8px;margin-bottom:14px}
.chk{display:flex;gap:8px;align-items:center;margin-top:14px}
</style>
</head>
<body>
<div class="box">
<?php if ($sukses): ?>
  <h1>Terpasang</h1>
  <div class="ok">Database dan akun admin sudah siap.</div>
  <p>Masuk sebagai admin, lalu buat akun guru, siswa, dan pengepul di menu Kelola Akun.</p>
  <?php
    $terhapus = @unlink(__FILE__);
    echo $terhapus ? '<p>File pemasangan sudah dihapus otomatis.</p>'
                   : '<p><strong>Hapus file install.php secara manual</strong> supaya tidak disalahgunakan.</p>';
  ?>
  <a class="b" href="login.php">Ke halaman login</a>
<?php elseif ($sudah): ?>
  <h1>Sudah terpasang</h1>
  <p>Database sudah punya akun admin. Hapus file <strong>install.php</strong> dari folder web, lalu buka halaman login.</p>
  <a class="b" href="login.php">Ke halaman login</a>
<?php elseif ($pesan !== "" && !isset($db)): ?>
  <h1>Pasang Bank Sampah</h1>
  <div class="err"><?= h($pesan) ?></div>
<?php else: ?>
  <h1>Pasang Bank Sampah</h1>
  <p>Buat akun admin pertama. Database <strong><?= h($DB) ?></strong> dan tabelnya dibuat otomatis, data yang sudah ada tidak dihapus.</p>
  <?php if ($pesan): ?><div class="err"><?= h($pesan) ?></div><?php endif; ?>
  <form method="post">
    <label for="nama">Nama admin</label>
    <input type="text" id="nama" name="nama" required value="Administrator">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autocomplete="off">
    <label for="password">Password (minimal 6 karakter)</label>
    <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
    <label class="chk"><input type="checkbox" name="contoh" value="1" checked> Isi jenis sampah dasar (plastik, kertas, kaca, kaleng)</label>
    <button type="submit">Pasang sekarang</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
