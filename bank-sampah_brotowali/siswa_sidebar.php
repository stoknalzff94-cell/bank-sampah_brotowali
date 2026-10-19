<?php
/* Sidebar siswa bersama. Menu aktif ditentukan otomatis dari nama file. */
$hal_aktif = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nama_sb   = $nama_siswa ?? ($_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Siswa');

$menu_siswa = [
    ['dashboard_siswa.php',    'house',   'Dashboard',    ['dashboard_siswa.php']],
    ['setor_sampah_siswa.php', 'sw-ic-setor.png',   'Setor Sampah', ['setor_sampah_siswa.php']],
    ['tarik_saldo_siswa.php',  'sw-ic-tarik.png',   'Tarik Saldo',  ['tarik_saldo_siswa.php']],
    ['profil_siswa.php',       'sw-ic-laporan.png', 'Profil Saya',  ['profil_siswa.php']],
];
?>
<aside class="sidebar sw-side" id="swSide">

    <div class="sw-brand">
        <img src="assets/sw-logo.png" alt="" class="sw-logo">
        <div>
            <h2>BANK SAMPAH</h2>
            <h2 class="sw-hijau">ATAYA</h2>
            <p>Jaga Lingkungan, Raih Manfaat</p>
        </div>
    </div>

    <nav class="sw-menu">
        <?php foreach ($menu_siswa as $m): ?>
            <a href="<?= $m[0] ?>" class="<?= in_array($hal_aktif, $m[3], true) ? 'active' : '' ?>">
                <span class="sw-mi">
                    <?php if ($m[1] === 'house'): ?>
                        <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                    <?php else: ?>
                        <img src="assets/<?= $m[1] ?>" alt="">
                    <?php endif; ?>
                </span>
                <span><?= $m[2] ?></span>
            </a>
        <?php endforeach; ?>
        <a href="ganti_password.php" class="<?= $hal_aktif === 'ganti_password.php' ? 'active' : '' ?>">
            <span class="sw-mi sw-mi-emoji">🔐</span>
            <span>Keamanan Akun</span>
        </a>
    </nav>

    <div class="sw-side-bottom">
        <div class="sw-boy" style="background-image:url('assets/sw-boy.png')" aria-hidden="true"></div>
        <div class="sw-signed">
            <img src="assets/sw-ava2.png" alt="" class="sw-ava">
            <div>
                <strong><?= htmlspecialchars($nama_sb, ENT_QUOTES, 'UTF-8') ?></strong>
                <small>Role Siswa</small>
            </div>
            <a href="logout.php" class="sw-out" title="Keluar" onclick="return confirm('Yakin ingin keluar dari akun Siswa?');">⎋</a>
        </div>
    </div>

</aside>
<div class="sw-overlay" onclick="document.body.classList.remove('sw-open')"></div>
