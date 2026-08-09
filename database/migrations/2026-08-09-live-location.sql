-- Forward-only upgrade from the populated 90cb666 schema.
-- Safe to run again after a complete or partially completed deployment.

SET @oems_schema = DATABASE();

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'location_visibility'
    ),
    'SELECT 1',
    'ALTER TABLE events ADD COLUMN location_visibility ENUM(''public'', ''registered'') NOT NULL DEFAULT ''public'' AFTER map_url'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'events'
          AND COLUMN_NAME = 'arrival_notes'
    ),
    'SELECT 1',
    'ALTER TABLE events ADD COLUMN arrival_notes VARCHAR(500) NULL AFTER location_visibility'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'venues'
          AND INDEX_NAME = 'idx_venues_coordinates'
    ),
    'SELECT 1',
    'ALTER TABLE venues ADD INDEX idx_venues_coordinates (latitude, longitude)'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @oems_schema
          AND TABLE_NAME = 'venues'
          AND CONSTRAINT_NAME = 'chk_venues_coordinate_pair'
          AND CONSTRAINT_TYPE = 'CHECK'
    ),
    'SELECT 1',
    'ALTER TABLE venues ADD CONSTRAINT chk_venues_coordinate_pair CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL))'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

CREATE TABLE IF NOT EXISTS geocoding_cache (
    query_hash CHAR(64) PRIMARY KEY,
    normalized_query VARCHAR(255) NOT NULL,
    provider VARCHAR(80) NOT NULL,
    response_json JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_geocoding_cache_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @oems_sql = NULL;
SET @oems_schema = NULL;
