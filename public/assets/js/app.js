 $(document).ready(function() {
    // Inicializar DataTables en la tabla correcta
    if ($('#tablaMascotas').length) {
        $('#tablaMascotas').DataTable({
            language: {
                search: "Buscar mascota o dueño:",
                lengthMenu: "Mostrar _MENU_ registros por página",
                zeroRecords: "No se encontraron coincidencias - ¿Lo buscaste en Excel? Aquí está más fácil 😎",
                info: "Mostrando página _PAGE_ de _PAGES_",
                infoEmpty: "No hay registros disponibles",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                paginate: {
                    first: "Primero",
                    last: "Último",
                    next: "Siguiente",
                    previous: "Anterior"
                }
            },
            responsive: true
        });
    }
});