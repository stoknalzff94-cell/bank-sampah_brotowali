/* Skrip bersama tampilan guru: menu, pencarian cepat, jam */
function gsMenu() {
    var b = document.body;
    if (window.innerWidth <= 900) { b.classList.toggle('gs-open'); }
    else { b.classList.toggle('gs-collapsed'); }
}
(function () {
    var cari = document.getElementById('gsCari');
    if (cari) {
        cari.addEventListener('input', function () {
            var q = cari.value.trim().toLowerCase();
            document.querySelectorAll('main table tbody tr, .gs-filterable').forEach(function (el) {
                if (el.querySelector('td[colspan]')) { return; }
                el.style.display = (q === '' || el.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    var jam = document.getElementById('gsJam');
    if (jam) {
        var tick = function () {
            var n = new Date();
            var tgl = n.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            var j = n.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).replace(/\./g, ':');
            jam.textContent = tgl + ' · ' + j;
        };
        tick();
        setInterval(tick, 1000);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.body.classList.remove('gs-open'); }
    });
})();
