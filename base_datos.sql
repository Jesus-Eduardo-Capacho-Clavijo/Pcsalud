CREATE DATABASE IF NOT EXISTS pcsalud CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE pcsalud;

DROP TABLE IF EXISTS mantenimientos;
DROP TABLE IF EXISTS administradores;
DROP TABLE IF EXISTS detalles_presupuesto;
DROP TABLE IF EXISTS emails_salida;

CREATE TABLE administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

INSERT INTO administradores (usuario, password)
VALUES ('admin', '123456');

CREATE TABLE mantenimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(10) NOT NULL UNIQUE,
    marca VARCHAR(100) NOT NULL,
    tipo_equipo ENUM('Portatil', 'PC de Escritorio') NOT NULL,
    razon_servicio TEXT NOT NULL,
    fecha_ingreso DATE NOT NULL,
    fecha_entrega DATE NOT NULL,
    nombre_cliente VARCHAR(120) NOT NULL,
    email_cliente VARCHAR(120) NULL,
    whatsapp_cliente VARCHAR(60) NOT NULL,
    diagnostico_actual TEXT NULL,
    estado ENUM('Recibido', 'En Revisión', 'Esperando Repuestos', 'Reparando', 'Listo para entregar', 'Entregado') NOT NULL DEFAULT 'Recibido',
    costo_estimado DECIMAL(10,2) NULL,
    estado_presupuesto ENUM('Pendiente', 'Aprobado', 'Rechazado') NOT NULL DEFAULT 'Pendiente',
    ruta_imagen VARCHAR(255) NULL DEFAULT NULL,
    estrellas INT NULL,
    comentario_cliente TEXT NULL
);

CREATE TABLE detalles_presupuesto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mantenimiento_id INT NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    costo DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalles_mantenimiento
        FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id)
        ON DELETE CASCADE
);

CREATE TABLE emails_salida (
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
