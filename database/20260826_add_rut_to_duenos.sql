ALTER TABLE duenos
    ADD COLUMN rut VARCHAR(12) NULL AFTER id;

CREATE UNIQUE INDEX uq_duenos_rut ON duenos (rut);

-- Los registros existentes quedan temporalmente con RUT nulo. Asígneles su RUT
-- desde la edición de mascotas antes de habilitar su acceso mediante RUT.
