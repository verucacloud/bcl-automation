CREATE TABLE IF NOT EXISTS social_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    platform VARCHAR(30) NOT NULL,
    platform_account_id VARCHAR(191) NOT NULL,
    username VARCHAR(191) NULL,
    display_name VARCHAR(191) NOT NULL,
    avatar_url TEXT NULL,
    access_token TEXT NULL,
    refresh_token TEXT NULL,
    token_expires_at DATETIME NULL,
    scopes TEXT NULL,
    status ENUM('connected','expired','revoked','error') NOT NULL DEFAULT 'connected',
    last_sync_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_account (platform, platform_account_id),
    KEY idx_social_user (user_id),
    KEY idx_social_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS managed_devices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(30) NOT NULL,
    adb_serial VARCHAR(191) NOT NULL,
    manufacturer VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    android_version VARCHAR(30) NULL,
    battery_level TINYINT UNSIGNED NULL,
    connection_type ENUM('usb','wifi','agent') NOT NULL DEFAULT 'usb',
    controller_name VARCHAR(100) NULL,
    status ENUM('online','offline','unauthorized','maintenance') NOT NULL DEFAULT 'offline',
    last_seen_at DATETIME NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_code (device_code),
    UNIQUE KEY uq_adb_serial (adb_serial),
    KEY idx_device_status (status),
    KEY idx_device_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_account_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    social_account_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(191) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_social (device_id, social_account_id),
    CONSTRAINT fk_link_device FOREIGN KEY (device_id) REFERENCES managed_devices(id) ON DELETE CASCADE,
    CONSTRAINT fk_link_account FOREIGN KEY (social_account_id) REFERENCES social_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS controller_tasks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id BIGINT UNSIGNED NOT NULL,
    action ENUM('collect_status','open_app','close_app','screenshot') NOT NULL,
    payload_json JSON NULL,
    scheduled_at DATETIME NOT NULL,
    status ENUM('scheduled','claimed','running','completed','failed','cancelled') NOT NULL DEFAULT 'scheduled',
    claimed_by VARCHAR(100) NULL,
    claimed_at DATETIME NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    result_json JSON NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_task_due (status, scheduled_at),
    KEY idx_task_device (device_id),
    CONSTRAINT fk_task_device FOREIGN KEY (device_id) REFERENCES managed_devices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS controller_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    controller_name VARCHAR(100) NULL,
    device_id BIGINT UNSIGNED NULL,
    task_id BIGINT UNSIGNED NULL,
    level ENUM('info','warning','error') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logs_created (created_at),
    CONSTRAINT fk_log_device FOREIGN KEY (device_id) REFERENCES managed_devices(id) ON DELETE SET NULL,
    CONSTRAINT fk_log_task FOREIGN KEY (task_id) REFERENCES controller_tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
