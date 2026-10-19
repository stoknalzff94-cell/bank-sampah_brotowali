<?php
session_start();
require_once __DIR__ . '/koneksi.php';

/* ===== CEK ADMIN ===== */
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$nama_admin = $_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Administrator';

/* ===== FUNGSI ===== */
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function ambil($koneksi, $sql)
{
    $r = mysqli_query($koneksi, $sql);
    if (!$r) { return 0; }
    $d = mysqli_fetch_assoc($r);
    return $d['total'] ?? 0;
}
function rupiah($n) { return 'Rp ' . number_format((float) $n, 0, ',', '.'); }
function angka($n)
{
    $s = number_format((float) $n, 2, ',', '.');
    return rtrim(rtrim($s, '0'), ',');
}
function persen($skrg, $lalu)
{
    if ($lalu > 0) { return (int) round(($skrg - $lalu) / $lalu * 100); }
    return $skrg > 0 ? 100 : 0;
}
function panah($p)
{
    if ($p > 0) { return '<em class="naik">▲ ' . $p . '% dari kemarin</em>'; }
    if ($p < 0) { return '<em class="turun">▼ ' . abs($p) . '% dari kemarin</em>'; }
    return '<em class="datar">– sama dengan kemarin</em>';
}
$bln = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
function tgl_pendek($ts) { global $bln; return date('j', $ts) . ' ' . $bln[(int) date('n', $ts) - 1]; }

/* ===== STATISTIK HARI INI vs KEMARIN (hanya setoran disetujui) ===== */
$kgHariIni    = ambil($koneksi, "SELECT COALESCE(SUM(berat),0) AS total FROM transaksi WHERE status='disetujui' AND DATE(tanggal)=CURDATE()");
$kgKemarin    = ambil($koneksi, "SELECT COALESCE(SUM(berat),0) AS total FROM transaksi WHERE status='disetujui' AND DATE(tanggal)=CURDATE() - INTERVAL 1 DAY");
$nilaiHariIni = ambil($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi WHERE status='disetujui' AND DATE(tanggal)=CURDATE()");
$nilaiKemarin = ambil($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi WHERE status='disetujui' AND DATE(tanggal)=CURDATE() - INTERVAL 1 DAY");

$totalNasabah  = ambil($koneksi, "SELECT COUNT(*) AS total FROM nasabah");
$totalPengepul = ambil($koneksi, "SELECT COUNT(*) AS total FROM users WHERE role='pengepul'");
$jenisSampah   = ambil($koneksi, "SELECT COUNT(*) AS total FROM sampah WHERE status='disetujui'");

$namaJenis = [];
$qj = mysqli_query($koneksi, "SELECT nama_sampah FROM sampah WHERE status='disetujui' ORDER BY id_sampah ASC LIMIT 4");
while ($qj && $r = mysqli_fetch_assoc($qj)) { $namaJenis[] = $r['nama_sampah']; }

/* ===== KESELURUHAN ===== */
$kgSemua      = ambil($koneksi, "SELECT COALESCE(SUM(berat),0) AS total FROM transaksi WHERE status='disetujui'");
$nilaiSemua   = ambil($koneksi, "SELECT COALESCE(SUM(total),0) AS total FROM transaksi WHERE status='disetujui'");
$totalTarik   = ambil($koneksi, "SELECT COALESCE(SUM(jumlah),0) AS total FROM tarik_saldo");
$saldo        = $nilaiSemua - $totalTarik;
$nasabahAktif = ambil($koneksi, "SELECT COUNT(DISTINCT id_nasabah) AS total FROM transaksi WHERE status='disetujui'");

/* ===== ANTREAN VERIFIKASI ===== */
$pendSetoran   = ambil($koneksi, "SELECT COUNT(*) AS total FROM transaksi WHERE status='pending'");
$pendSampah    = ambil($koneksi, "SELECT COUNT(*) AS total FROM sampah WHERE status='pending'");
$pendPembelian = ambil($koneksi, "SELECT COUNT(*) AS total FROM pembelian_pengepul WHERE status='pending'");
$pendTotal     = $pendSetoran + $pendSampah + $pendPembelian;

/* ===== GRAFIK 7 HARI (kg) ===== */
$perHari = [];
$qg = mysqli_query($koneksi, "SELECT DATE(tanggal) AS d, SUM(berat) AS kg FROM transaksi
                              WHERE status='disetujui' AND DATE(tanggal) >= CURDATE() - INTERVAL 6 DAY
                              GROUP BY DATE(tanggal)");
while ($qg && $r = mysqli_fetch_assoc($qg)) { $perHari[$r['d']] = (float) $r['kg']; }

$labelsGrafik = [];
$dataGrafik   = [];
for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-$i days");
    $labelsGrafik[] = tgl_pendek($ts);
    $dataGrafik[]   = round($perHari[date('Y-m-d', $ts)] ?? 0, 2);
}

/* ===== DATA TERBARU ===== */
$q_transaksi = mysqli_query($koneksi,
    "SELECT t.id_transaksi, t.tanggal, t.berat, t.total, t.status, n.nama, s.nama_sampah, s.satuan
     FROM transaksi t
     LEFT JOIN nasabah n ON t.id_nasabah = n.id_nasabah
     LEFT JOIN sampah  s ON t.id_sampah  = s.id_sampah
     ORDER BY t.id_transaksi DESC LIMIT 5");

/* ===== TOP 5 NASABAH ===== */
$q_top = mysqli_query($koneksi,
    "SELECT n.nama, SUM(t.berat) AS kg
     FROM transaksi t JOIN nasabah n ON t.id_nasabah = n.id_nasabah
     WHERE t.status='disetujui'
     GROUP BY n.id_nasabah, n.nama
     ORDER BY kg DESC LIMIT 5");

$labelStatus = ['disetujui' => ['Diterima', 'ok'], 'pending' => ['Menunggu', 'wait'], 'ditolak' => ['Ditolak', 'no']];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Bank Sampah</title>
<link rel="stylesheet" href="assets/admin-theme.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; }
</style>
</head>
<body>

<?php include __DIR__ . '/admin_sidebar.php'; ?>

<main class="main">

<?php
$tb_judul = 'Dashboard Admin';
$tb_sub   = 'Pusat kontrol Bank Sampah';
include __DIR__ . '/admin_topbar.php';
?>

<section class="content">
<div class="bs-grid">

    <!-- ================= KOLOM KIRI ================= -->
    <div class="bs-col">

        <!-- HERO -->
        <div class="bs-hero">
            <small><?= strtoupper(date('d')) ?> <?= strtoupper($bln[(int) date('n') - 1]) ?> <?= date('Y') ?></small>
            <h1>Selamat Datang,<br><span><?= e($nama_admin) ?></span></h1>
            <p>Kelola data Bank Sampah dengan mudah, transparan, dan berdampak untuk lingkungan.</p>
            <div class="bs-hero-tags">
                <div><i>♻️</i><span>Lingkungan<br>Lebih Bersih</span></div>
                <div><i>💰</i><span>Ekonomi<br>Masyarakat</span></div>
                <div><i>🌱</i><span>Masa Depan<br>Lebih Baik</span></div>
            </div>
            <?php if ($pendTotal > 0): ?>
                <a class="bs-hero-chip" href="verifikasi_transaksi.php">🔔 <?= $pendTotal ?> item menunggu verifikasi</a>
            <?php endif; ?>
        </div>

        <!-- STATISTIK -->
        <div class="bs-stats">
            <div class="bs-panel bs-stat">
                <div class="bs-stat-ico">♻️</div>
                <div>
                    <span>Setoran Hari Ini</span>
                    <b><?= angka($kgHariIni) ?> kg</b>
                    <?= panah(persen($kgHariIni, $kgKemarin)) ?>
                </div>
            </div>
            <div class="bs-panel bs-stat merah">
                <div class="bs-stat-ico">💵</div>
                <div>
                    <span>Nilai Hari Ini</span>
                    <b><?= rupiah($nilaiHariIni) ?></b>
                    <?= panah(persen($nilaiHariIni, $nilaiKemarin)) ?>
                </div>
            </div>
            <div class="bs-panel bs-stat">
                <div class="bs-stat-ico">👥</div>
                <div>
                    <span>Total Nasabah</span>
                    <b><?= number_format($totalNasabah, 0, ',', '.') ?></b>
                    <small>terdaftar</small>
                </div>
            </div>
            <div class="bs-panel bs-stat">
                <div class="bs-stat-ico">🚚</div>
                <div>
                    <span>Total Pengepul</span>
                    <b><?= number_format($totalPengepul, 0, ',', '.') ?></b>
                    <small>akun pengepul</small>
                </div>
            </div>
            <div class="bs-panel bs-stat">
                <div class="bs-stat-ico">🍃</div>
                <div>
                    <span>Jenis Sampah</span>
                    <b><?= (int) $jenisSampah ?> jenis</b>
                    <small><?= e(implode(', ', $namaJenis) ?: 'Belum ada') ?></small>
                </div>
            </div>
        </div>

        <!-- GRAFIK + DATA TERBARU -->
        <div class="bs-two">
            <div class="bs-panel">
                <h4>📦 Data Setoran Sampah <span style="font-size:12px;color:var(--muted);font-weight:500">7 hari (kg)</span></h4>
                <div class="bs-chart-box"><canvas id="grafikSetoran"></canvas></div>
            </div>

            <div class="bs-panel">
                <h4>📋 Data Terbaru <a href="transaksi.php">Lihat Semua →</a></h4>
                <div class="bs-table-wrap">
                    <table class="bs-table">
                        <thead>
                            <tr><th>No</th><th>Tanggal</th><th>Nasabah</th><th>Jenis</th><th>Berat</th><th>Nilai</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; $ada = false; ?>
                        <?php while ($q_transaksi && $t = mysqli_fetch_assoc($q_transaksi)): $ada = true; ?>
                            <?php $st = $labelStatus[$t['status']] ?? [ucfirst($t['status']), 'wait']; ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="nw"><?= e(date('d/m/y', strtotime($t['tanggal']))) ?></td>
                                <td><?= e($t['nama'] ?? '-') ?></td>
                                <td><?= e($t['nama_sampah'] ?? '-') ?></td>
                                <td class="nw"><?= angka($t['berat']) ?> <?= e($t['satuan'] ?? 'kg') ?></td>
                                <td class="nw"><?= rupiah($t['total']) ?></td>
                                <td class="nw"><span class="bs-badge <?= $st[1] ?>"><?= $st[0] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if (!$ada): ?>
                            <tr><td colspan="7" class="bs-empty">Belum ada transaksi.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AKSI CEPAT -->
        <div class="bs-actions">
            <a class="bs-panel bs-act" href="nasabah.php"><i>🧑‍🤝‍🧑</i><div><b>Tambah Nasabah</b><small>Daftarkan nasabah baru</small></div></a>
            <a class="bs-panel bs-act" href="akun.php"><i>🚚</i><div><b>Tambah Pengepul</b><small>Buat akun pengepul</small></div></a>
            <a class="bs-panel bs-act" href="transaksi.php"><i>📝</i><div><b>Input Setoran</b><small>Catat transaksi sampah</small></div></a>
            <a class="bs-panel bs-act" href="laporan.php"><i>📊</i><div><b>Lihat Laporan</b><small>Rekap data &amp; laporan</small></div></a>
        </div>

    </div>

    <!-- ================= KOLOM KANAN ================= -->
    <div class="bs-col">

        <div class="bs-panel bs-saldo">
            <div class="lbl"><span class="coin">🪙</span><span>Saldo Bank Sampah</span></div>
            <div class="nilai"><?= rupiah($saldo) ?></div>
            <a class="bs-btn-gold" href="tarik_saldo.php">Tarik Saldo</a>
        </div>

        <div class="bs-promo"><span>Sampah Bukan Akhir, Tapi Awal Perubahan</span></div>

        <div class="bs-mini">
            <div><span>Total Setoran</span><b><?= angka($kgSemua) ?> kg</b></div>
            <div><span>Total Nilai</span><b><?= rupiah($nilaiSemua) ?></b></div>
            <div><span>Nasabah Aktif</span><b><?= (int) $nasabahAktif ?></b></div>
            <div><span>Pengepul</span><b><?= (int) $totalPengepul ?></b></div>
        </div>

        <div class="bs-panel">
            <h4>🏆 Top 5 Nasabah <a href="laporan.php">Lihat Semua →</a></h4>
            <ul class="bs-top5">
                <?php $r = 1; $adaTop = false; ?>
                <?php while ($q_top && $n = mysqli_fetch_assoc($q_top)): $adaTop = true; ?>
                    <li>
                        <span class="bs-rank"><?= $r++ ?></span>
                        <span class="nm"><?= e($n['nama']) ?></span>
                        <span class="kg"><?= angka($n['kg']) ?> kg</span>
                    </li>
                <?php endwhile; ?>
                <?php if (!$adaTop): ?><li class="bs-empty" style="display:block">Belum ada setoran disetujui.</li><?php endif; ?>
            </ul>
        </div>

    </div>

    <!-- TICKER -->
    <div class="bs-panel bs-ticker">
        <div class="bs-ticker-run">
            🔊 Selamat datang di Sistem Bank Sampah! <i>|</i>
            Bersama kita wujudkan lingkungan yang lebih bersih dan sehat <i>|</i>
            Setiap sampah bernilai, setiap langkah berarti! <i>|</i>
            Selamat datang di Sistem Bank Sampah! <i>|</i>
            Bersama kita wujudkan lingkungan yang lebih bersih dan sehat
        </div>
    </div>

</div>
</section>
</main>

<script>
(function () {
    var el = document.getElementById('grafikSetoran');
    if (!el || typeof Chart === 'undefined') { return; }

    var labels = <?= json_encode($labelsGrafik) ?>;
    var data   = <?= json_encode($dataGrafik) ?>;
    var ctx    = el.getContext('2d');

    var grad = ctx.createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, '#2fe081');
    grad.addColorStop(1, '#0a7a3f');

    new Chart(ctx, {
        data: {
            labels: labels,
            datasets: [
                { type: 'bar',  label: 'Setoran (kg)', data: data, backgroundColor: grad, borderColor: '#4be08a', borderWidth: 1, borderRadius: 4, order: 2 },
                { type: 'line', label: 'Tren', data: data, borderColor: '#e6b84a', backgroundColor: '#e6b84a', tension: .35, pointRadius: 4, borderWidth: 2, order: 1 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: '#c9d6c8', font: { size: 11 } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: '#c9d6c8', font: { size: 11 } }, grid: { color: 'rgba(230,184,74,.12)' } }
            }
        }
    });
})();
</script>

</body>
</html>
