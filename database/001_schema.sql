-- MySQL 8.0.16+ / 8.4, InnoDB. Seleccione antes una base VACIA.
-- Este archivo solo crea estructura: no contiene INSERT, usuarios ni contrasenas.
-- No borra tablas existentes; una segunda importacion falla de forma visible.

CREATE TABLE products (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT chk_products_stock CHECK (stock >= 0),
    CONSTRAINT chk_products_name CHECK (CHAR_LENGTH(TRIM(name)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reservations (
    id BIGINT NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    remaining_stock INT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT uq_reservations_request_id UNIQUE (request_id),
    KEY idx_reservations_product_id (product_id),
    CONSTRAINT fk_reservations_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_reservations_quantity CHECK (quantity > 0),
    CONSTRAINT chk_reservations_remaining CHECK (remaining_stock >= 0),
    CONSTRAINT chk_reservations_request_id CHECK (
        CHAR_LENGTH(request_id) BETWEEN 1 AND 64
        AND request_id NOT REGEXP '[^a-zA-Z0-9._:-]'
        AND LEFT(request_id, 1) REGEXP '[a-zA-Z0-9]'
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
