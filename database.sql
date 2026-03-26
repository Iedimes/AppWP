-- Field Data - Base de Datos
-- MySQL/MariaDB

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS field_data;
USE field_data;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telefono VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol VARCHAR(20) DEFAULT 'user',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de registros
CREATE TABLE IF NOT EXISTS registros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL,
    descripcion TEXT,
    cantidad REAL,
    unidad VARCHAR(20),
    lugar VARCHAR(100),
    monto REAL,
    cultivo VARCHAR(100),
    animal VARCHAR(100),
    item VARCHAR(100),
    usuario VARCHAR(50),
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Usuarios de ejemplo
INSERT INTO usuarios (telefono, nombre, rol) VALUES 
('0981517309', 'Juan Chavez', 'admin'),
('0972123456', 'Maria Lopez', 'user'),
('0983765432', 'Pedro Gomez', 'user');

-- Fin del SQL