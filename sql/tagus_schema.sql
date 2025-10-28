USE `daniel24533_tagus_db`;

CREATE TABLE IF NOT EXISTS `ind_productos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `titulo` VARCHAR(255) NOT NULL,
  `descripcion` TEXT NULL,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `categoria` VARCHAR(100) NULL,
  `marca` VARCHAR(100) NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ind_variantes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `producto_id` INT NOT NULL,
  `talle` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  `sku` VARCHAR(100) NULL,
  `stock` INT NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_producto_talle_color` (`producto_id`,`talle`,`color`),
  CONSTRAINT `fk_var_prod` FOREIGN KEY (`producto_id`) REFERENCES `ind_productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ind_imagenes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `producto_id` INT NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `ancho` INT NULL,
  `alto` INT NULL,
  CONSTRAINT `fk_img_prod` FOREIGN KEY (`producto_id`) REFERENCES `ind_productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ind_config` (
  `id` TINYINT PRIMARY KEY DEFAULT 1,
  `razon_social` VARCHAR(255),
  `cuit` VARCHAR(25),
  `domicilio_fiscal` VARCHAR(255),
  `condiciones_venta` TEXT,
  `costo_envio` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `habilitar_contraentrega` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `ind_config` (`id`) VALUES (1)
ON DUPLICATE KEY UPDATE `id`=`id`;

CREATE TABLE IF NOT EXISTS `ind_pedidos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(120) NOT NULL,
  `telefono` VARCHAR(50) NOT NULL,
  `email` VARCHAR(120) NULL,
  `direccion` TEXT NOT NULL,
  `ciudad` VARCHAR(100) NULL,
  `provincia` VARCHAR(100) NULL,
  `cp` VARCHAR(20) NULL,
  `metodo_pago` VARCHAR(50) NOT NULL,
  `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  `total` DECIMAL(10,2) NOT NULL,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ind_pedido_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT NOT NULL,
  `producto_id` INT NOT NULL,
  `variante_id` INT NULL,
  `titulo` VARCHAR(255) NOT NULL,
  `talle` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  `precio` DECIMAL(10,2) NOT NULL,
  `cantidad` INT NOT NULL,
  CONSTRAINT `fk_item_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `ind_pedidos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
