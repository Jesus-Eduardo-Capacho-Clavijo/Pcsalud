USE pcsalud;

CREATE TABLE IF NOT EXISTS detalles_presupuesto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mantenimiento_id INT NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    costo DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalles_mantenimiento
        FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id)
        ON DELETE CASCADE
);
