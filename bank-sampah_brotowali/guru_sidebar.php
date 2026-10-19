<?php
/* Sidebar guru bersama. Menu aktif ditentukan otomatis dari nama file. */
$hal_aktif = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nama_sb   = $nama_guru ?? ($_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Guru');

$ikon = [
    'home'   => '<svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
    'setor'  => '<svg viewBox="0 0 24 24"><path d="M9 3h6l1 2h4v2H4V5h4zM6 9h12l-1 11a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1zm4 3v6h1.6v-6zm2.8 0v6h1.6v-6z"/></svg>',
    'tarik'  => '<svg viewBox="0 0 24 24"><path d="M4 6a2 2 0 0 1 2-2h11v3H6v1h13a1 1 0 0 1 1 1v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm12 7a1.4 1.4 0 1 0 0 2.8 1.4 1.4 0 0 0 0-2.8z"/></svg>',
    'laporan'=> '<svg viewBox="0 0 24 24"><path d="M6 2h8l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm7 1.5V8h4.5zM8 12v1.6h8V12zm0 3.4V17h8v-1.6z"/></svg>',
];
$menu_guru = [
    ['dashboard_guru.php',     'home',    'Dashboard'],
    ['setor_sampah_guru.php',  'setor',   'Setor Sampah'],
    ['tarik_saldo_guru.php',   'tarik',   'Tarik Saldo'],
    ['laporan_guru.php',       'laporan', 'Laporan'],
];
?>
<aside class="sidebar gs-side" id="gsSide">

    <div class="gs-brand">
        <svg class="gs-recycle" viewBox="0 0 64 64" aria-hidden="true">
            <defs><linearGradient id="gsRc" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6ab0ff"/><stop offset="1" stop-color="#1f64f0"/></linearGradient></defs>
            <g fill="url(#gsRc)">
                <path d="M32 6 22 22h7l-6 10-9-5L24 8z" opacity=".95"/>
                <path d="M52 40 42 24l-3.5 6-9-5.4 5-8.4 17.6 10.8 6 10z" opacity=".85"/>
                <path d="M12 46 26 38v6h14l-4 8H18z" opacity=".9"/>
            </g>
        </svg>
        <div>
            <h2>BANK SAMPAH</h2>
            <h2 class="gs-blue">ATAYA</h2>
            <p>Lingkungan Bersih, Masa Depan Cerah</p>
        </div>
    </div>

    <div class="gs-label">WORKSPACE</div>

    <nav class="gs-menu">
        <?php foreach ($menu_guru as $m): ?>
            <a href="<?= $m[0] ?>" class="<?= $hal_aktif === $m[0] ? 'active' : '' ?>">
                <span class="gs-mi"><?= $ikon[$m[1]] ?></span>
                <span><?= $m[2] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="gs-side-bottom">
        <div class="gs-label">SIGNED IN AS</div>
        <div class="gs-signed">
            <div class="gs-avatar"><svg viewBox="0 0 24 24"><circle cx="12" cy="8.5" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7z"/></svg></div>
            <div>
                <strong><?= htmlspecialchars($nama_sb, ENT_QUOTES, 'UTF-8') ?></strong>
                <small>Role Guru</small>
            </div>
            <a href="logout.php" class="gs-out" title="Keluar" onclick="return confirm('Yakin ingin keluar dari akun Guru?');">⎋</a>
        </div>

        <div class="gs-rpl" style="background-image:url('assets/rpl-card.jpg')" aria-label="RPL - Rekayasa Perangkat Lunak"></div>
    </div>

</aside>
<div class="gs-overlay" onclick="document.body.classList.remove('gs-open')"></div>
