CREATE TABLE IF NOT EXISTS outbox_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aggregate_id INT NOT NULL,
    type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_aggregate_id (aggregate_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

