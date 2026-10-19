<?php
session_start();

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'siswa') {
    die("Akses ditolak. Halaman ini khusus siswa.");
}

include "koneksi.php";

/*
|--------------------------------------------------------------------------
| IDENTITAS SISWA
|--------------------------------------------------------------------------
*/
$id_nasabah = $_SESSION['id_nasabah'] ?? 0;

$nama_siswa = $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Siswa';

if (!$id_nasabah) {
    die("
        <div style='font-family:Arial;padding:40px'>
            <h2>Data siswa belum terhubung</h2>
            <p>Session <b>id_nasabah</b> belum tersedia.</p>
            <p>Silakan hubungkan akun siswa dengan data nasabah terlebih dahulu.</p>
        </div>
    ");
}

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/
function rupiah($angka)
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function angka($angka)
{
    return number_format((float)$angka, 2, ',', '.');
}

/*
|--------------------------------------------------------------------------
| DATA NASABAH
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_nasabah, nama, alamat, no_hp, tanggal_daftar
     FROM nasabah
     WHERE id_nasabah = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result_nasabah = mysqli_stmt_get_result($stmt);
$data_nasabah = mysqli_fetch_assoc($result_nasabah);

mysqli_stmt_close($stmt);

if (!$data_nasabah) {
    die("
        <div style='font-family:Arial;padding:40px'>
            <h2>Data nasabah tidak ditemukan</h2>
            <p>ID Nasabah: <b>" . htmlspecialchars($id_nasabah) . "</b></p>
        </div>
    ");
}

$nama_siswa = $data_nasabah['nama'];

/*
|--------------------------------------------------------------------------
| TOTAL SETORAN DISETUJUI
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(berat), 0) AS total_berat,
        COALESCE(SUM(total), 0) AS total_nilai
     FROM transaksi
     WHERE id_nasabah = ?
     AND status = 'disetujui'"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_setoran = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$jumlah_transaksi = (int)($data_setoran['jumlah_transaksi'] ?? 0);
$total_berat      = (float)($data_setoran['total_berat'] ?? 0);
$total_setoran    = (int)($data_setoran['total_nilai'] ?? 0);

/*
|--------------------------------------------------------------------------
| TOTAL PENARIKAN
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT COALESCE(SUM(jumlah), 0) AS total_tarik
     FROM tarik_saldo
     WHERE id_nasabah = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_tarik = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$total_tarik = (int)($data_tarik['total_tarik'] ?? 0);

/*
|--------------------------------------------------------------------------
| SALDO
|--------------------------------------------------------------------------
*/
$saldo = $total_setoran - $total_tarik;

if ($saldo < 0) {
    $saldo = 0;
}

/*
|--------------------------------------------------------------------------
| SETORAN PENDING
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi
     WHERE id_nasabah = ?
     AND status = 'pending'"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_pending = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$jumlah_pending = (int)($data_pending['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| SETORAN DITOLAK
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT COUNT(*) AS total
     FROM transaksi
     WHERE id_nasabah = ?
     AND status = 'ditolak'"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$data_ditolak = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

$jumlah_ditolak = (int)($data_ditolak['total'] ?? 0);

/*
|--------------------------------------------------------------------------
| DATA GRAFIK 7 HARI TERAKHIR
|--------------------------------------------------------------------------
*/
$chart_data = [];

for ($i = 6; $i >= 0; $i--) {
    $tanggal = date('Y-m-d', strtotime("-$i days"));

    $chart_data[$tanggal] = [
        'label' => date('d M', strtotime($tanggal)),
        'total' => 0
    ];
}

$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        tanggal,
        COALESCE(SUM(total), 0) AS total
     FROM transaksi
     WHERE id_nasabah = ?
     AND status = 'disetujui'
     AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY tanggal
     ORDER BY tanggal ASC"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    if (isset($chart_data[$row['tanggal']])) {
        $chart_data[$row['tanggal']]['total'] = (int)$row['total'];
    }
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| SETORAN TERBARU
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        transaksi.id_transaksi,
        transaksi.tanggal,
        transaksi.berat,
        transaksi.total,
        transaksi.status,
        sampah.nama_sampah,
        sampah.satuan
     FROM transaksi
     INNER JOIN sampah
        ON sampah.id_sampah = transaksi.id_sampah
     WHERE transaksi.id_nasabah = ?
     ORDER BY transaksi.id_transaksi DESC
     LIMIT 6"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result_terbaru = mysqli_stmt_get_result($stmt);

$data_terbaru = [];

while ($row = mysqli_fetch_assoc($result_terbaru)) {
    $data_terbaru[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| PENARIKAN TERBARU
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT
        id_tarik,
        tanggal,
        jumlah,
        keterangan
     FROM tarik_saldo
     WHERE id_nasabah = ?
     ORDER BY id_tarik DESC
     LIMIT 5"
);

mysqli_stmt_bind_param($stmt, "i", $id_nasabah);
mysqli_stmt_execute($stmt);

$result_tarik = mysqli_stmt_get_result($stmt);

$data_tarik_terbaru = [];

while ($row = mysqli_fetch_assoc($result_tarik)) {
    $data_tarik_terbaru[] = $row;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| SAPAAN
|--------------------------------------------------------------------------
*/
$jam = (int)date('H');

if ($jam < 11) {
    $sapaan = "Selamat pagi";
} elseif ($jam < 15) {
    $sapaan = "Selamat siang";
} elseif ($jam < 18) {
    $sapaan = "Selamat sore";
} else {
    $sapaan = "Selamat malam";
}


/*
|--------------------------------------------------------------------------
| DATA TAMBAHAN UNTUK TAMPILAN BARU
|--------------------------------------------------------------------------
*/
$id_n = (int)$id_nasabah;

function satu_angka($koneksi, $sql, $kolom = 'total')
{
    $q = mysqli_query($koneksi, $sql);
    $d = $q ? mysqli_fetch_assoc($q) : [];
    return (float)($d[$kolom] ?? 0);
}

/* Setoran disetujui milik siswa: hari ini vs kemarin (kg) */
$kg_hari_ini = satu_angka($koneksi, "SELECT COALESCE(SUM(berat),0) AS total FROM transaksi WHERE id_nasabah = $id_n AND status = 'disetujui' AND tanggal = CURDATE()");
$kg_kemarin  = satu_angka($koneksi, "SELECT COALESCE(SUM(berat),0) AS total FROM transaksi WHERE id_nasabah = $id_n AND status = 'disetujui' AND tanggal = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$selisih_kg  = $kg_kemarin > 0 ? round((($kg_hari_ini - $kg_kemarin) / $kg_kemarin) * 100) : null;

/* Angka umum Bank Sampah */
$total_nasabah  = (int)satu_angka($koneksi, "SELECT COUNT(*) AS total FROM nasabah");
$total_pengepul = (int)satu_angka($koneksi, "SELECT COUNT(*) AS total FROM users WHERE role = 'pengepul'");

$jenis_sampah = [];
$q_jenis = mysqli_query($koneksi, "SELECT nama_sampah FROM sampah WHERE status = 'disetujui' ORDER BY nama_sampah ASC");
while ($q_jenis && ($rj = mysqli_fetch_assoc($q_jenis))) {
    $jenis_sampah[] = $rj['nama_sampah'];
}
$jumlah_jenis = count($jenis_sampah);

/* Grafik 7 hari: berat (kg) dan nilai (Rp) setoran disetujui milik siswa */
$g_label = [];
$g_kg    = [];
$g_rp    = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $q = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(berat),0) AS kg, COALESCE(SUM(total),0) AS rp
        FROM transaksi
        WHERE id_nasabah = $id_n AND status = 'disetujui' AND tanggal = '$tgl'
    ");
    $d = $q ? mysqli_fetch_assoc($q) : [];
    $g_label[] = date('d M', strtotime($tgl));
    $g_kg[]    = (float)($d['kg'] ?? 0);
    $g_rp[]    = (float)($d['rp'] ?? 0);
}

function fmt_berat($v)
{
    $s = number_format((float)$v, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Siswa - Bank Sampah Ataya</title>
    <link rel="stylesheet" href="assets/siswa-theme.css">
</head>
<body>

<?php include __DIR__ . '/siswa_sidebar.php'; ?>

<main class="main">

    <?php include __DIR__ . '/siswa_topbar.php'; ?>

    <section class="sw-hero">
        <div class="sw-hero-art"></div>
        <div class="sw-hero-text">
            <h1>Hai, <?= htmlspecialchars($nama_siswa) ?>! 👋</h1>
            <p>Terus semangat menabung sampah<br>untuk masa depan yang lebih baik!</p>
            <div class="sw-chips">
                <div class="sw-chip"><i>🌿</i><span>Lingkungan<br>Lebih Bersih</span></div>
                <div class="sw-chip"><i>🏫</i><span>Sekolah<br>Lebih Asri</span></div>
                <div class="sw-chip"><i>🌍</i><span>Masa Depan<br>Lebih Baik</span></div>
            </div>
        </div>
    </section>

    <section class="sw-stats">

        <div class="sw-stat hijau">
            <img class="sw-sico" src="assets/sw-st1.png" alt="">
            <div>
                <small>Total Setoran<br>Hari Ini</small>
                <strong><?= fmt_berat($kg_hari_ini) ?> kg</strong>
                <span>
                    <?php if ($selisih_kg === null): ?>
                        <?= $jumlah_pending > 0 ? $jumlah_pending . ' setoran menunggu' : 'Belum ada data kemarin' ?>
                    <?php elseif ($selisih_kg >= 0): ?>
                        <b class="sw-up">↑ <?= $selisih_kg ?>%</b> dari kemarin
                    <?php else: ?>
                        <b class="sw-down">↓ <?= abs($selisih_kg) ?>%</b> dari kemarin
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="sw-stat kuning">
            <img class="sw-sico" src="assets/sw-st2.png" alt="">
            <div>
                <small>Total Saldo</small>
                <strong><?= rupiah($saldo) ?></strong>
                <span>Setoran disetujui dikurangi penarikan</span>
            </div>
        </div>

        <div class="sw-stat ungu">
            <img class="sw-sico" src="assets/sw-st3.png" alt="">
            <div>
                <small>Total Nasabah</small>
                <strong><?= number_format($total_nasabah, 0, ',', '.') ?></strong>
                <span>Terdaftar di Bank Sampah</span>
            </div>
        </div>

        <div class="sw-stat biru">
            <img class="sw-sico" src="assets/sw-st4.png" alt="">
            <div>
                <small>Total Pengepul</small>
                <strong><?= number_format($total_pengepul, 0, ',', '.') ?></strong>
                <span>Mitra penampung sampah</span>
            </div>
        </div>

        <div class="sw-stat pink">
            <img class="sw-sico" src="assets/sw-st5.png" alt="">
            <div>
                <small>Jenis Sampah</small>
                <strong><?= $jumlah_jenis ?> jenis</strong>
                <span><?= $jumlah_jenis ? htmlspecialchars(implode(', ', array_slice($jenis_sampah, 0, 5))) : 'Belum ada jenis sampah' ?></span>
            </div>
        </div>

    </section>

    <section class="sw-mid">

        <div class="sw-panel">
            <div class="sw-ph"><h3>📈 Grafik Setoran Sampah (7 Hari Terakhir)</h3></div>
            <div class="sw-legend">
                <span><i style="background:#35b87a"></i>Berat (kg)</span>
                <span><i style="background:#9a73ea"></i>Nilai (Rp)</span>
            </div>
            <?php
            $W = 640; $H = 250; $kiri = 40; $kanan = 10; $atas = 14; $bawah = 34;
            $lebar = $W - $kiri - $kanan; $tinggi = $H - $atas - $bawah;
            $maks_kg = max(max($g_kg), 1);
            $langkah = $maks_kg <= 4 ? 1 : ($maks_kg <= 10 ? 2 : ($maks_kg <= 50 ? 10 : ($maks_kg <= 100 ? 25 : 50)));
            $skala_kg = ceil($maks_kg / $langkah) * $langkah;
            $maks_rp = max(max($g_rp), 1);
            $n = count($g_kg);
            $slot = $lebar / $n;
            $titik = [];
            ?>
            <div class="sw-chart">
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Grafik setoran 7 hari terakhir">
                    <defs>
                        <linearGradient id="swHijau" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4fd29a"/><stop offset="1" stop-color="#a9eccf"/></linearGradient>
                        <linearGradient id="swUngu" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8f6ee8"/><stop offset="1" stop-color="#c9b6f5"/></linearGradient>
                    </defs>
                    <?php for ($v = 0; $v <= $skala_kg; $v += $langkah):
                        $y = $atas + $tinggi - ($v / $skala_kg) * $tinggi; ?>
                        <line x1="<?= $kiri ?>" x2="<?= $W - $kanan ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>" stroke="#e1ece8" stroke-width="1"/>
                        <text x="<?= $kiri - 8 ?>" y="<?= round($y + 4, 1) ?>" fill="#5b7185" font-size="12" text-anchor="end"><?= $v ?></text>
                    <?php endfor; ?>
                    <?php foreach ($g_kg as $i => $kg):
                        $bw = min($slot * 0.28, 26);
                        $x0 = $kiri + $slot * $i + $slot / 2 - $bw - 1;
                        $x1 = $x0 + $bw + 2;
                        $h1 = ($kg / $skala_kg) * $tinggi;
                        $h2 = ($g_rp[$i] / $maks_rp) * $tinggi * 0.9;
                        $cx = $x1 + $bw / 2;
                        $titik[] = round($cx, 1) . ',' . round($atas + $tinggi - $h2, 1); ?>
                        <rect x="<?= round($x0, 1) ?>" y="<?= round($atas + $tinggi - $h1, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($h1, 1) ?>" rx="3" fill="url(#swHijau)"/>
                        <rect x="<?= round($x1, 1) ?>" y="<?= round($atas + $tinggi - $h2, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($h2, 1) ?>" rx="3" fill="url(#swUngu)"/>
                        <text x="<?= round($kiri + $slot * $i + $slot / 2, 1) ?>" y="<?= $H - 10 ?>" fill="#3b5568" font-size="12" text-anchor="middle"><?= htmlspecialchars($g_label[$i]) ?></text>
                    <?php endforeach; ?>
                    <polyline points="<?= implode(' ', $titik) ?>" fill="none" stroke="#9a73ea" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <?php foreach ($titik as $t): [$px, $py] = explode(',', $t); ?>
                        <circle cx="<?= $px ?>" cy="<?= $py ?>" r="4" fill="#9a73ea" stroke="#fff" stroke-width="1.5"/>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <div class="sw-panel">
            <div class="sw-ph">
                <h3>🧾 Transaksi Terbaru</h3>
                <a href="setor_sampah_siswa.php">Lihat Semua →</a>
            </div>
            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead>
                        <tr><th>No</th><th>Tanggal</th><th>Jenis Sampah</th><th>Berat</th><th>Nilai (Rp)</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (count($data_terbaru) > 0): ?>
                        <?php foreach ($data_terbaru as $no => $row):
                            $status = $row['status'] ?? 'pending'; ?>
                            <tr>
                                <td><?= $no + 1 ?></td>
                                <td><?= htmlspecialchars($row['tanggal'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_sampah'] ?? '-') ?></td>
                                <td><?= fmt_berat($row['berat'] ?? 0) ?> <?= htmlspecialchars($row['satuan'] ?? 'kg') ?></td>
                                <td><?= number_format((int)($row['total'] ?? 0), 0, ',', '.') ?></td>
                                <td><span class="sw-pill <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status === 'disetujui' ? 'Diterima' : $status) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="sw-empty">Belum ada setoran. Yuk mulai setor sampah pertamamu!</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>

    <section class="sw-bawah">
        <a href="setor_sampah_siswa.php" class="sw-q hijau">
            <img src="assets/sw-q1.png" alt="">
            <div><h4>Setor Sampah</h4><p>Catat transaksi setoran sampah dari nasabah.</p></div>
            <span class="sw-go">→</span>
        </a>
        <a href="tarik_saldo_siswa.php" class="sw-q kuning">
            <img src="assets/sw-q2.png" alt="">
            <div><h4>Tarik Saldo</h4><p>Lakukan penarikan saldo dari hasil setoran.</p></div>
            <span class="sw-go">→</span>
        </a>
        <a href="profil_siswa.php" class="sw-q ungu">
            <img src="assets/sw-q3.png" alt="">
            <div><h4>Profil Saya</h4><p>Lihat data nasabah dan informasi akunmu.</p></div>
            <span class="sw-go">→</span>
        </a>
        <div class="sw-bumi" aria-hidden="true"></div>
    </section>

</main>

<script src="assets/siswa.js"></script>
</body>
</html>
