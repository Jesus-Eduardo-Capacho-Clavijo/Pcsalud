USE pcsalud;

CREATE TABLE IF NOT EXISTS emails_salida (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mantenimiento_id INT NULL,
    destinatario VARCHAR(120) NOT NULL,
    asunto VARCHAR(255) NOT NULL,
    cuerpo TEXT NOT NULL,
    estado_envio ENUM('pendiente', 'enviado', 'fallido') NOT NULL DEFAULT 'pendiente',
    mensaje_error VARCHAR(255) NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enviado_en TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_email_mantenimiento
        FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id)
        ON DELETE SET NULL
);
