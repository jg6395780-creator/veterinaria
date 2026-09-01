        </div> <!-- Fin container-fluid -->
    </div> <!-- Fin page-content-wrapper -->
</div> <!-- Fin wrapper -->

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>

<script>
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });

    (() => {
        const button = document.getElementById('theme-toggle');
        const apply = (dark) => {
            document.body.classList.toggle('dark-theme', dark);
            if (button) button.innerHTML = dark ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
        };
        apply(localStorage.getItem('vetclinic-theme') === 'dark');
        if (button) button.addEventListener('click', () => {
            const dark = !document.body.classList.contains('dark-theme');
            localStorage.setItem('vetclinic-theme', dark ? 'dark' : 'light');
            apply(dark);
        });
    })();
</script>
<?php if (in_array($_SESSION['user_rol'] ?? '', ['admin', 'recepcion', 'veterinario'], true)): ?>
<script>
(() => {
    const sidebarBadge = document.getElementById('urgenciaSidebarBadge');
    const topBadge = document.getElementById('urgenciaTopBadge');
    const liveAlert = document.getElementById('urgenciaLiveAlert');
    const liveText = document.getElementById('urgenciaLiveText');
    let ultimoAviso = Number(sessionStorage.getItem('ultimaUrgenciaAvisada') || 0);
    const rolUsuario = <?= json_encode($_SESSION['user_rol'] ?? '') ?>;

    function actualizarBadge(cantidad) {
        [sidebarBadge, topBadge].forEach((badge) => {
            if (!badge) return;
            badge.textContent = cantidad > 99 ? '99+' : String(cantidad);
            badge.classList.toggle('d-none', cantidad === 0);
        });
    }

    function emitirAviso(urgencia) {
        if (!liveAlert || !liveText) return;
        liveText.textContent = `${urgencia.mascota}: ${urgencia.motivo} · llega en ${urgencia.minutos_llegada} min.`;
        liveAlert.classList.remove('d-none');
        if (navigator.vibrate) navigator.vibrate([250, 120, 250]);
    }

    async function consultarUrgencias() {
        try {
            const response = await fetch('api/urgencias_pendientes.php', {
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (!response.ok) return;
            const data = await response.json();
            if (!data.success) return;
            actualizarBadge(Number(data.cantidad || 0));
            const notificables = (data.urgencias || []).filter((item) => rolUsuario === 'veterinario'
                ? item.estado === 'confirmada'
                : ['pendiente', 'recibida'].includes(item.estado));
            const nueva = notificables.reduce((actual, item) => !actual || item.id > actual.id ? item : actual, null);
            if (nueva && nueva.id > ultimoAviso) {
                ultimoAviso = nueva.id;
                sessionStorage.setItem('ultimaUrgenciaAvisada', String(ultimoAviso));
                emitirAviso(nueva);
            }
        } catch (_) {
            // La siguiente consulta automática vuelve a intentarlo.
        }
    }

    consultarUrgencias();
    setInterval(consultarUrgencias, 5000);
})();
</script>
<?php endif; ?>
</body>
</html>
