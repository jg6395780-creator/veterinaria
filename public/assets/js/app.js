$(document).ready(function() {

    var dtLang = {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "No se encontraron resultados",
        info: "Mostrando _START_–_END_ de _TOTAL_",
        infoEmpty: "No hay registros disponibles",
        infoFiltered: "(filtrado de _MAX_ totales)",
        paginate: { first:"Primero", last:"Último", next:"Siguiente", previous:"Anterior" }
    };

    // ===== DataTable: Mascotas =====
    if ($('#tablaMascotas').length) {
        // Filtro custom por especie y raza (columna 2)
        $.fn.dataTable.ext.search.push(function(settings, data) {
            if (settings.nTable.id !== 'tablaMascotas') return true;
            var especie = $('#filtroEspecie').val().toLowerCase();
            var raza    = $('#filtroRaza').val().toLowerCase();
            var celda   = (data[2] || '').toLowerCase();
            if (especie && celda.indexOf(especie) === -1) return false;
            if (raza    && celda.indexOf(raza)    === -1) return false;
            return true;
        });

        var tablaMascotas = $('#tablaMascotas').DataTable({ language: dtLang, responsive: true });

        $('#filtroEspecie, #filtroRaza').on('change', function() { tablaMascotas.draw(); });
        $('#limpiarFiltros').on('click', function() {
            $('#filtroEspecie, #filtroRaza').val('');
            tablaMascotas.draw();
        });
    }

    // ===== DataTable: Caja =====
    if ($('#tablaCaja').length) {
      var colCount = $('#tablaCaja thead tr th').length;
        $('#tablaCaja').DataTable({
            language: dtLang,
            responsive: true,
            order: [[0, 'desc']],
            columnDefs: colCount > 5 ? [{ orderable: false, targets: colCount - 1 }] : []
        });
    }

    // ===== Validación Bootstrap: todos los formularios con novalidate =====
    document.querySelectorAll('form[novalidate]').forEach(function(form) {

        // Bloquear envío si hay campos requeridos vacíos
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });

        // Feedback en tiempo real al escribir
        form.querySelectorAll('[required]').forEach(function(field) {
            field.addEventListener('input', function() {
                if (field.value.trim() !== '') {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                } else {
                    field.classList.remove('is-valid');
                    field.classList.add('is-invalid');
                }
            });
        });
    });

    // ===== Limpiar estado de validación al abrir modales =====
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('show.bs.modal', function() {
            modal.querySelectorAll('form[novalidate]').forEach(function(form) {
                form.classList.remove('was-validated');
                form.querySelectorAll('.is-valid, .is-invalid').forEach(function(el) {
                    el.classList.remove('is-valid', 'is-invalid');
                });
            });
        });
    });

});
