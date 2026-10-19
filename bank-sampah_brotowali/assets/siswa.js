/* Skrip bersama tampilan siswa: menu, pencarian cepat, sapaan tanggal */
function swMenu() {
    var b = document.body;
    if (window.innerWidth <= 900) { b.classList.toggle('sw-open'); }
    else { b.classList.toggle('sw-collapsed'); }
}
(function () {
    var cari = document.getElementById('swCari');
    if (cari) {
        cari.addEventListener('input', function () {
            var q = cari.value.trim().toLowerCase();
            document.querySelectorAll('main table tbody tr').forEach(function (el) {
                if (el.querySelector('td[colspan]')) { return; }
                el.style.display = (q === '' || el.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.body.classList.remove('sw-open'); }
    });
})();
