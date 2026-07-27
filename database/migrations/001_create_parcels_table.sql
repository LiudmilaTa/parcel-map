CREATE TABLE IF NOT EXISTS parcels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    cuzk_id VARCHAR(50) NOT NULL,
    local_id VARCHAR(100) NULL,

    label VARCHAR(100) NOT NULL,
    national_cadastral_reference VARCHAR(100) NULL,

    area_value DECIMAL(12, 2) NULL,

    zoning_name VARCHAR(255) NULL,
    administrative_unit_name VARCHAR(255) NULL,

    min_x DOUBLE NOT NULL,
    min_y DOUBLE NOT NULL,
    max_x DOUBLE NOT NULL,
    max_y DOUBLE NOT NULL,

    geometry LONGTEXT NOT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_parcels_cuzk_id (cuzk_id),

    KEY idx_parcels_bbox (
        min_x,
        max_x,
        min_y,
        max_y
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;