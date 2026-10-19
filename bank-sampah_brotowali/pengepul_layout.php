<?php
/* Layout & fungsi bersama untuk semua halaman role pengepul. */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/koneksi.php';

if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('rupiah')) {
    function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
}
function angka($n) { return number_format((float)$n, 2, ',', '.'); }

function wajib_pengepul()
{
    if (
        ($_SESSION['login'] ?? false) !== true ||
        ($_SESSION['role'] ?? '') !== 'pengepul' ||
        empty($_SESSION['id_user'])
    ) {
        header("Location: login.php");
        exit;
    }
    return (int) $_SESSION['id_user'];
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_ok()
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '');
}

function flash($pesan = null, $tipe = 'ok')
{
    if ($pesan !== null) {
        $_SESSION['flash'] = ['pesan' => $pesan, 'tipe' => $tipe];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function badge($status)
{
    $label = ['pending' => 'Menunggu admin', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'];
    return '<span class="badge b-' . e($status) . '">' . e($label[$status] ?? $status) . '</span>';
}

/* Stok tersedia = setoran nasabah yang sudah disetujui admin
   dikurangi pembelian pengepul yang pending / disetujui. */
function ambil_katalog($koneksi, $id_sampah = null)
{
    $sql = "SELECT s.id_sampah, s.nama_sampah, s.harga, s.satuan,
                   COALESCE(t.tb, 0) - COALESCE(p.pb, 0) AS stok
            FROM sampah s
            LEFT JOIN (SELECT id_sampah, SUM(berat) AS tb FROM transaksi
                       WHERE status = 'disetujui' GROUP BY id_sampah) t
                   ON t.id_sampah = s.id_sampah
            LEFT JOIN (SELECT id_sampah, SUM(berat) AS pb FROM pembelian_pengepul
                       WHERE status IN ('pending','disetujui') GROUP BY id_sampah) p
                   ON p.id_sampah = s.id_sampah
            WHERE s.status = 'disetujui'";
    if ($id_sampah !== null) {
        $sql .= " AND s.id_sampah = " . (int) $id_sampah;
    }
    $sql .= " ORDER BY s.nama_sampah ASC";
    $res = mysqli_query($koneksi, $sql);
    $rows = [];
    while ($res && ($r = mysqli_fetch_assoc($res))) {
        $r['stok'] = max(0, (float) $r['stok']);
        $rows[] = $r;
    }
    return $rows;
}


/* Color Timer: penunjuk stok. Biru = aman, kuning = mulai turun, merah berkedip = hampir habis. */
function color_timer($stok, $max)
{
    $stok = (float) $stok;
    $r = $max > 0 ? $stok / $max : 0;
    $kls = ($stok < 5 || $r <= 0.15) ? 'low' : (($stok < 20 || $r <= 0.4) ? 'mid' : '');
    $w = $stok <= 0 ? 0 : max(5, (int) round($r * 100));
    return '<div class="ct ' . $kls . '"><div class="ct-track"><div class="ct-fill" style="width:' . $w . '%"></div></div>'
         . ($kls === 'low' ? '<span class="ct-note">' . ($stok <= 0 ? 'Habis' : 'Menipis') . '</span>' : '') . '</div>';
}

function pengepul_head($judul, $aktif, $hero = false)
{
    $nama = $_SESSION['nama_user'] ?? $_SESSION['username'] ?? 'Pengepul';
    $menu = [
        'dashboard_pengepul.php' => ['beranda', 'Dashboard', '🏠'],
        'katalog_pengepul.php'   => ['beli', 'Beli sampah', '♻️'],
        'riwayat_pembelian.php'  => ['riwayat', 'Riwayat pembelian', '🕘'],
        'laporan_pengepul.php'   => ['laporan', 'Laporan', '📊'],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> - Pengepul | Bank Sampah Ataya</title>
<style>
:root{--red:#c71a1a;--red2:#9e1015;--deep:#8b0b10;--bg:#fbf3f2;--card:#fff;--line:#f0d9d7;--pink:#fdeeed;--ink:#2a1b1b;--muted:#7a6463;--blue:#21b6ff;--amber:#ffb000;--ok:#1d7a4f;--warn:#8f5c00;--bad:#b3242a}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 "Segoe UI",system-ui,-apple-system,sans-serif}
a{color:inherit}
.top{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:8px 32px;background:linear-gradient(90deg,#b6040d,#8b0b10 45%,#880a0f);color:#fff;position:relative;z-index:5}
.logo{display:flex;align-items:center;gap:12px;text-decoration:none;color:#fff}
.logo img{width:46px;height:auto}
.logo b{display:block;font-size:24px;font-weight:800;letter-spacing:.02em;line-height:1.1}
.logo small{display:block;font-size:11.5px;letter-spacing:.32em;opacity:.9}
.topnav{display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.topnav>a,.topnav summary{display:flex;align-items:center;gap:8px;padding:12px 16px;border-radius:10px;text-decoration:none;color:#fff;font-weight:600;cursor:pointer;list-style:none}
.topnav summary::-webkit-details-marker{display:none}
.topnav>a:hover,.topnav summary:hover{background:rgba(255,255,255,.14)}
.topnav>a.on{background:#c9282b;box-shadow:inset 0 -3px 0 rgba(255,255,255,.55)}
.akun{position:relative}
.akun .menu{position:absolute;right:0;top:100%;min-width:190px;background:#fff;color:var(--ink);border-radius:10px;box-shadow:0 8px 24px rgba(60,0,0,.25);padding:8px;z-index:10}
.akun .menu span{display:block;padding:8px 10px;font-size:13px;color:var(--muted);border-bottom:1px solid var(--line);margin-bottom:4px}
.akun .menu a{display:block;padding:9px 10px;border-radius:8px;text-decoration:none;font-weight:600;color:var(--red2)}
.akun .menu a:hover{background:var(--pink)}
.hero img{display:block;width:100%;height:auto}
.shell{display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px;max-width:1500px;margin:0 auto;padding:22px 24px 48px;align-items:start}
.shell.dua{grid-template-columns:260px minmax(0,1fr) 330px}
.side{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px;position:sticky;top:16px;box-shadow:0 2px 10px rgba(120,20,20,.06)}
.side a{display:flex;gap:12px;align-items:center;padding:12px 14px;border-radius:10px;text-decoration:none;font-weight:600;color:var(--ink);margin-bottom:4px}
.side a:hover{background:var(--pink)}
.side a.on{background:var(--red);color:#fff}
.side img{display:block;width:100%;height:auto;margin-top:14px;border-radius:10px}
main{min-width:0}
.promo{display:grid;gap:16px}
.promo img{display:block;width:100%;height:auto;border-radius:14px;box-shadow:0 2px 10px rgba(120,20,20,.15)}
h1{font-size:26px;font-weight:800;margin:0 0 4px;line-height:1.25}
.sub{color:var(--muted);margin:0 0 20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin-bottom:20px}
.stat{display:flex;gap:14px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;box-shadow:0 2px 10px rgba(120,20,20,.05)}
.ico{flex:none;width:52px;height:52px;border-radius:14px;background:var(--red);color:#fff;display:grid;place-items:center;font-size:22px;font-weight:800}
.stat span{color:var(--muted);font-size:13.5px;display:block}
.stat b{display:block;font-size:24px;line-height:1.25}
.panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px 22px;margin-bottom:20px;box-shadow:0 2px 10px rgba(120,20,20,.05)}
.panel h2{font-size:18px;font-weight:800;margin:0 0 12px}
.tbl{width:100%;border-collapse:collapse}
.tbl th,.tbl td{text-align:left;padding:10px 10px;border-bottom:1px solid var(--line);vertical-align:middle}
.tbl th{font-size:13px;background:var(--pink);font-weight:700}
.tbl th:first-child{border-radius:8px 0 0 8px}.tbl th:last-child{border-radius:0 8px 8px 0}
.tbl td.r,.tbl th.r{text-align:right}
.wrap{overflow-x:auto}
.badge{display:inline-block;padding:2px 10px;border-radius:99px;font-size:12.5px;font-weight:700}
.b-pending{background:#fff0c7;color:var(--warn)}
.b-disetujui{background:#d3efe0;color:var(--ok)}
.b-ditolak{background:#fbdcdc;color:var(--bad)}
.btn{display:inline-block;border:0;border-radius:8px;padding:10px 18px;font:inherit;font-weight:700;cursor:pointer;background:var(--red);color:#fff;text-decoration:none}
.btn:hover{background:var(--red2)}
.btn.alt{background:#fff;color:var(--red2);border:1px solid #e3b0b2}
.btn.alt:hover{background:var(--pink)}
.btn.bad{background:#fff;color:var(--bad);border:1px solid #e3b0b2}
input[type=number],input[type=date],select{font:inherit;padding:9px 11px;border:1px solid #e6c9c7;border-radius:8px;background:#fff}
input:focus-visible,select:focus-visible,.btn:focus-visible,a:focus-visible,summary:focus-visible{outline:3px solid var(--blue);outline-offset:2px}
.msg{padding:12px 16px;border-radius:10px;margin-bottom:18px;font-weight:700;border-left:5px solid}
.msg.ok{background:#d3efe0;color:var(--ok);border-color:var(--ok)}
.msg.err{background:#fbdcdc;color:var(--bad);border-color:var(--bad)}
.ct{display:flex;align-items:center;gap:8px}
.ct-track{flex:1;min-width:90px;height:14px;padding:2px;background:#2a1216;border-radius:99px;box-shadow:inset 0 1px 3px #000c}
.ct-fill{height:100%;border-radius:99px;background:var(--blue);box-shadow:0 0 8px 1px rgba(33,182,255,.7)}
.ct.mid .ct-fill{background:var(--amber);box-shadow:0 0 8px 1px rgba(255,176,0,.7)}
.ct.low .ct-fill{background:#ff3b30;box-shadow:0 0 8px 1px rgba(255,59,48,.8);animation:kedip 1s steps(2) infinite}
.ct-note{font-size:12px;font-weight:800;color:var(--red2);white-space:nowrap}
@keyframes kedip{50%{opacity:.3}}
@media (prefers-reduced-motion:reduce){.ct.low .ct-fill{animation:none}}
.legend{font-size:13px;color:var(--muted);margin:-6px 0 14px}
.kosong{color:var(--muted);padding:14px 0}
.filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px}
.filter label{display:block;font-size:13px;color:var(--muted)}
.lihat{display:flex;justify-content:flex-end;margin-top:14px}
@media(max-width:1180px){.shell.dua{grid-template-columns:260px minmax(0,1fr)}.promo{grid-column:1/-1;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}}
@media(max-width:820px){
 .top{padding:10px 14px}
 .logo b{font-size:20px}
 .shell,.shell.dua{grid-template-columns:1fr;padding:14px 12px 40px}
 .side{position:static}
 .side img{display:none}
 .side{display:flex;flex-wrap:wrap;gap:4px}
 .side a{margin:0;flex:1 1 45%}
}
@media print{.top,.hero,.side,.promo,.noprint{display:none!important}.shell{display:block;padding:0}body{background:#fff}}
</style>
</head>
<body>
<header class="top">
  <a class="logo" href="dashboard_pengepul.php">
    <img src="assets/logo.png" alt="" width="46" height="40">
    <span><b>BANK SAMPAH</b><small>BERSAMA JAGA BUMI</small></span>
  </a>
  <nav class="topnav" aria-label="Menu utama">
    <?php foreach ($menu as $file => [$kode, $label, $ikon]): ?>
      <a href="<?= e($file) ?>" class="<?= $aktif === $kode ? 'on' : '' ?>"><span aria-hidden="true"><?= $ikon ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
    <details class="akun">
      <summary><span aria-hidden="true">👤</span>Akun ▾</summary>
      <div class="menu"><span><?= e($nama) ?> · Pengepul</span><a href="logout.php">Keluar</a></div>
    </details>
  </nav>
</header>
<?php if ($hero): ?>
<div class="hero"><img src="assets/hero.jpg" alt="Bank Sampah Pengepul. Sampah hari ini, masa depan lebih baik."></div>
<?php endif; ?>
<div class="shell<?= $hero ? ' dua' : '' ?>">
<nav class="side" aria-label="Menu samping">
  <?php foreach ($menu as $file => [$kode, $label, $ikon]): ?>
    <a href="<?= e($file) ?>" class="<?= $aktif === $kode ? 'on' : '' ?>"><span aria-hidden="true"><?= $ikon ?></span><?= e($label) ?></a>
  <?php endforeach; ?>
  <img src="assets/maskot.jpg" alt="Ultraman peduli lingkungan">
</nav>
<main>
<?php
    $f = flash();
    if ($f) {
        echo '<div class="msg ' . ($f['tipe'] === 'ok' ? 'ok' : 'err') . '" role="status">' . e($f['pesan']) . '</div>';
    }
}

function pengepul_foot($promo = false)
{
    echo "</main>\n";
    if ($promo) {
        echo '<aside class="promo" aria-label="Semangat pengepul">'
           . '<img src="assets/promo1.jpg" alt="Sampah bukan akhir, tapi awal dari perubahan.">'
           . '<img src="assets/promo2.jpg" alt="Bersama pengepul, sampah menjadi nilai, lingkungan jadi lestari.">'
           . "</aside>\n";
    }
    echo "</div>\n</body>\n</html>";
}
