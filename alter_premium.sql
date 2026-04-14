USE pcsalud;

ALTER TABLE mantenimientos
    MODIFY COLUMN estado ENUM(
        'Recibido',
        'En Revisión',
        'Esperando Repuestos',
        'Reparando',
        'Listo para entregar',
        'Entregado'
    ) NOT NULL DEFAULT 'Recibido',
    ADD COLUMN costo_estimado DECIMAL(10,2) NULL AFTER estado,
    ADD COLUMN estado_presupuesto ENUM('Pendiente', 'Aprobado', 'Rechazado') NOT NULL DEFAULT 'Pendiente' AFTER costo_estimado,
    ADD COLUMN ruta_imagen VARCHAR(255) NULL DEFAULT NULL AFTER estado_presupuesto;
