USE pcsalud;

ALTER TABLE mantenimientos
    ADD COLUMN email_cliente VARCHAR(120) NULL AFTER nombre_cliente,
    ADD COLUMN estrellas INT NULL AFTER ruta_imagen,
    ADD COLUMN comentario_cliente TEXT NULL AFTER estrellas;
