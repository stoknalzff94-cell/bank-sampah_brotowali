<?php
session_start();

/* =========================================================
   LOGOUT
========================================================= */
if (isset($_GET['logout'])) {

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['id_user'])) {
    header("Location: login.php");
    exit;
}

include "koneksi.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guru') {
    die("Akses ditolak. Halaman ini khusus guru.");
}

$nama_guru = $_SESSION['nama_user']
    ?? $_SESSION['nama']
    ?? $_SESSION['username']
    ?? 'Guru';

/* =========================================================
   DATA DASHBOARD
========================================================= */

/* Total Nasabah */
$q_nasabah = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM nasabah
");

$data_nasabah = mysqli_fetch_assoc($q_nasabah);
$total_nasabah = (int)($data_nasabah['total'] ?? 0);


/* Total Transaksi */
$q_transaksi = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM transaksi
");

$data_transaksi = mysqli_fetch_assoc($q_transaksi);
$total_transaksi = (int)($data_transaksi['total'] ?? 0);


/* Total Transaksi Pending */
$q_pending = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM transaksi
    WHERE status = 'pending'
");

$data_pending = mysqli_fetch_assoc($q_pending);
$total_pending = (int)($data_pending['total'] ?? 0);


/* Total Saldo */
$q_saldo = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(total), 0) AS total
    FROM transaksi
    WHERE status = 'disetujui'
");

$data_saldo = mysqli_fetch_assoc($q_saldo);
$total_saldo = (int)($data_saldo['total'] ?? 0);


/* =========================================================
   DATA GRAFIK 7 HARI
========================================================= */

$chart_labels = [];
$chart_values = [];

for ($i = 6; $i >= 0; $i--) {

    $tanggal = date('Y-m-d', strtotime("-$i days"));

    $chart_labels[] = date('d M', strtotime($tanggal));

    $q_chart = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(total),0) AS total
        FROM transaksi
        WHERE tanggal = '$tanggal'
        AND status = 'disetujui'
    ");

    $d_chart = mysqli_fetch_assoc($q_chart);

    $chart_values[] = (int)($d_chart['total'] ?? 0);
}


/* =========================================================
   TRANSAKSI PENDING
========================================================= */

$q_pending_list = mysqli_query($koneksi, "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.berat,
        t.total,
        n.nama,
        s.nama_sampah
    FROM transaksi t
    LEFT JOIN nasabah n
        ON t.id_nasabah = n.id_nasabah
    LEFT JOIN sampah s
        ON t.id_sampah = s.id_sampah
    WHERE t.status = 'pending'
    ORDER BY t.id_transaksi DESC
    LIMIT 5
");


/* =========================================================
   TOP NASABAH
========================================================= */

$q_top_nasabah = mysqli_query($koneksi, "
    SELECT
        n.nama,
        COALESCE(SUM(t.total),0) AS total
    FROM nasabah n
    LEFT JOIN transaksi t
        ON n.id_nasabah = t.id_nasabah
        AND t.status = 'disetujui'
    GROUP BY n.id_nasabah
    ORDER BY total DESC
    LIMIT 5
");


/* =========================================================
   TRANSAKSI TERBARU
========================================================= */

$q_latest = mysqli_query($koneksi, "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.berat,
        t.total,
        t.status,
        n.nama,
        s.nama_sampah
    FROM transaksi t
    LEFT JOIN nasabah n
        ON t.id_nasabah = n.id_nasabah
    LEFT JOIN sampah s
        ON t.id_sampah = s.id_sampah
    ORDER BY t.id_transaksi DESC
    LIMIT 7
");


/* =========================================================
   FORMAT RUPIAH
========================================================= */

function rupiah($angka)
{
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
}


/* =========================================================
   DATA TAMBAHAN UNTUK TAMPILAN BARU
========================================================= */

/* Total Pengepul */
$q_pengepul = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users WHERE role = 'pengepul'");
$total_pengepul = (int)(($q_pengepul ? mysqli_fetch_assoc($q_pengepul)['total'] : 0) ?? 0);

/* Setoran hari ini vs kemarin (kg), tidak termasuk yang ditolak */
function kg_pada($koneksi, $tanggal)
{
    $q = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(berat),0) AS kg
        FROM transaksi
        WHERE tanggal = '" . mysqli_real_escape_string($koneksi, $tanggal) . "'
        AND status <> 'ditolak'
    ");
    $d = $q ? mysqli_fetch_assoc($q) : [];
    return (float)($d['kg'] ?? 0);
}
$kg_hari_ini = kg_pada($koneksi, date('Y-m-d'));
$kg_kemarin  = kg_pada($koneksi, date('Y-m-d', strtotime('-1 day')));
$selisih_kg  = $kg_kemarin > 0
    ? round((($kg_hari_ini - $kg_kemarin) / $kg_kemarin) * 100)
    : null;

/* Grafik 7 hari: berat (kg) dan nilai (Rp) */
$g_label = [];
$g_kg    = [];
$g_rp    = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $q = mysqli_query($koneksi, "
        SELECT COALESCE(SUM(berat),0) AS kg, COALESCE(SUM(total),0) AS rp
        FROM transaksi
        WHERE tanggal = '$tgl' AND status <> 'ditolak'
    ");
    $d = $q ? mysqli_fetch_assoc($q) : [];
    $g_label[] = date('d M', strtotime($tgl));
    $g_kg[]    = (float)($d['kg'] ?? 0);
    $g_rp[]    = (float)($d['rp'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - Bank Sampah Ataya</title>
    <link rel="stylesheet" href="assets/guru-theme.css">
</head>
<body>

<?php include __DIR__ . '/guru_sidebar.php'; ?>

<main class="main">

    <?php include __DIR__ . '/guru_topbar.php'; ?>

    <section class="gs-hero">
        <div class="gs-hero-text">
            <h1>Selamat Datang, <em><?= htmlspecialchars($nama_guru) ?></em></h1>
            <p>Kelola dan pantau aktivitas Bank Sampah dengan mudah, transparan, dan bermanfaat untuk lingkungan.</p>
            <div class="gs-chips">
                <div class="gs-chip"><i>♻️</i><span>Peduli<br>Lingkungan</span></div>
                <div class="gs-chip"><i>💰</i><span>Manfaat<br>Ekonomi</span></div>
                <div class="gs-chip"><i>💻</i><span>Berbasis<br>Teknologi</span></div>
                <div class="gs-chip"><i>🌱</i><span>Untuk<br>Masa Depan</span></div>
            </div>
        </div>
        <div class="gs-hero-art">
            <div class="gs-date" id="gsJam">Memuat waktu...</div>
            <div class="gs-rpl-badge"><b>RPL</b><span>Rekayasa Perangkat Lunak</span></div>
        </div>
    </section>

    <section class="gs-stats">

        <div class="gs-stat hijau">
            <div class="gs-sico">♻️</div>
            <div>
                <small>Total Setoran Hari Ini</small>
                <strong><?= rtrim(rtrim(number_format($kg_hari_ini, 1, ',', '.'), '0'), ',') ?> kg</strong>
                <span>
                    <?php if ($selisih_kg === null): ?>
                        Belum ada data kemarin
                    <?php elseif ($selisih_kg >= 0): ?>
                        <span class="gs-up">↑ <?= $selisih_kg ?>%</span> dari kemarin
                    <?php else: ?>
                        <span class="gs-down">↓ <?= abs($selisih_kg) ?>%</span> dari kemarin
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="gs-stat ungu">
            <div class="gs-sico">🪙</div>
            <div>
                <small>Total Saldo</small>
                <strong><?= rupiah($total_saldo) ?></strong>
                <span>Dari transaksi disetujui</span>
            </div>
        </div>

        <div class="gs-stat biru">
            <div class="gs-sico">👥</div>
            <div>
                <small>Total Nasabah</small>
                <strong><?= number_format($total_nasabah, 0, ',', '.') ?></strong>
                <span><?= number_format($total_pending, 0, ',', '.') ?> transaksi menunggu</span>
            </div>
        </div>

        <div class="gs-stat biru">
            <div class="gs-sico">🚚</div>
            <div>
                <small>Total Pengepul</small>
                <strong><?= number_format($total_pengepul, 0, ',', '.') ?></strong>
                <span><?= number_format($total_transaksi, 0, ',', '.') ?> transaksi tercatat</span>
            </div>
        </div>

    </section>

    <section class="gs-mid">

        <div class="gs-panel">
            <div class="gs-ph"><h3>Grafik Setoran Sampah (7 Hari Terakhir)</h3></div>
            <div class="gs-legend">
                <span><i style="background:#2f8bff"></i>Berat (kg)</span>
                <span><i style="background:#7b6cf6"></i>Nilai (Rp)</span>
            </div>
            <?php
            $W = 640; $H = 250; $kiri = 44; $kanan = 12; $atas = 14; $bawah = 34;
            $lebar = $W - $kiri - $kanan; $tinggi = $H - $atas - $bawah;
            $maks_kg = max(max($g_kg), 1);
            $langkah = $maks_kg <= 10 ? 2 : ($maks_kg <= 50 ? 10 : ($maks_kg <= 100 ? 25 : 50));
            $skala_kg = ceil($maks_kg / $langkah) * $langkah;
            $maks_rp = max(max($g_rp), 1);
            $n = count($g_kg);
            $slot = $lebar / $n;
            $titik = [];
            ?>
            <div class="gs-chart">
                <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Grafik setoran 7 hari terakhir">
                    <defs>
                        <linearGradient id="gsBar" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#6d63f2"/><stop offset="1" stop-color="#2f8bff"/>
                        </linearGradient>
                        <linearGradient id="gsArea" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#7b6cf6" stop-opacity=".35"/><stop offset="1" stop-color="#7b6cf6" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <?php for ($v = 0; $v <= $skala_kg; $v += $langkah):
                        $y = $atas + $tinggi - ($v / $skala_kg) * $tinggi; ?>
                        <line x1="<?= $kiri ?>" x2="<?= $W - $kanan ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>" stroke="#16275c" stroke-width="1"/>
                        <text x="<?= $kiri - 8 ?>" y="<?= round($y + 4, 1) ?>" fill="#93a4cf" font-size="12" text-anchor="end"><?= $v ?></text>
                    <?php endfor; ?>
                    <?php foreach ($g_kg as $i => $kg):
                        $bw = min($slot * 0.46, 46);
                        $x  = $kiri + $slot * $i + ($slot - $bw) / 2;
                        $bh = ($kg / $skala_kg) * $tinggi;
                        $cx = $kiri + $slot * $i + $slot / 2;
                        $cy = $atas + $tinggi - ($g_rp[$i] / $maks_rp) * $tinggi * 0.92;
                        $titik[] = round($cx, 1) . ',' . round($cy, 1); ?>
                        <rect x="<?= round($x, 1) ?>" y="<?= round($atas + $tinggi - $bh, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($bh, 1) ?>" rx="4" fill="url(#gsBar)" opacity=".9"/>
                        <text x="<?= round($cx, 1) ?>" y="<?= $H - 10 ?>" fill="#dbe5ff" font-size="12" text-anchor="middle"><?= htmlspecialchars($g_label[$i]) ?></text>
                    <?php endforeach; ?>
                    <polygon points="<?= $kiri + $slot / 2 ?>,<?= $atas + $tinggi ?> <?= implode(' ', $titik) ?> <?= round($kiri + $slot * ($n - 0.5), 1) ?>,<?= $atas + $tinggi ?>" fill="url(#gsArea)"/>
                    <polyline points="<?= implode(' ', $titik) ?>" fill="none" stroke="#6f7cf8" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    <?php foreach ($titik as $t): [$px, $py] = explode(',', $t); ?>
                        <circle cx="<?= $px ?>" cy="<?= $py ?>" r="4.5" fill="#3b9bff" stroke="#050b1d" stroke-width="2"/>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <div class="gs-panel">
            <div class="gs-ph">
                <h3>Transaksi Terbaru</h3>
                <a href="laporan_guru.php">Lihat Semua →</a>
            </div>
            <div class="gs-table-wrap">
                <table class="gs-table">
                    <thead>
                        <tr><th>No</th><th>Tanggal</th><th>Nama Nasabah</th><th>Jenis Sampah</th><th>Berat (kg)</th><th>Nilai (Rp)</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php $no = 1; if ($q_latest && mysqli_num_rows($q_latest) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($q_latest)):
                            $status = $row['status'] ?? 'pending'; ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['tanggal'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_sampah'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(rtrim(rtrim(number_format((float)($row['berat'] ?? 0), 2, ',', '.'), '0'), ',')) ?></td>
                                <td><?= number_format((int)($row['total'] ?? 0), 0, ',', '.') ?></td>
                                <td><span class="gs-pill <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="gs-empty">Belum ada transaksi.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </section>

    <section class="gs-quick">
        <a href="setor_sampah_guru.php" class="gs-q ungu">
            <div class="gs-sico">♻️</div>
            <div><h4>Setor Sampah</h4><p>Catat transaksi setoran sampah dari nasabah.</p></div>
            <div class="gs-go">→</div>
        </a>
        <a href="tarik_saldo_guru.php" class="gs-q hijau">
            <div class="gs-sico">👛</div>
            <div><h4>Tarik Saldo</h4><p>Lakukan penarikan saldo dari hasil setoran.</p></div>
            <div class="gs-go">→</div>
        </a>
        <a href="laporan_guru.php" class="gs-q biru">
            <div class="gs-sico">📄</div>
            <div><h4>Laporan</h4><p>Lihat laporan transaksi, setoran dan saldo.</p></div>
            <div class="gs-go">→</div>
        </a>
    </section>

</main>

<script src="assets/guru.js"></script>
</body>
</html>
