-- Forward-only upgrade from the populated 5857358 schema.
-- Safe to run again after a complete or partially completed deployment.

SET @oems_schema = DATABASE();

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = 'reviewed_by'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD COLUMN reviewed_by BIGINT UNSIGNED NULL AFTER refunded_at'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = 'reviewed_at'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'payments'
          AND COLUMN_NAME = 'review_note'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD COLUMN review_note VARCHAR(500) NULL AFTER reviewed_at'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = @oems_schema
          AND TABLE_NAME = 'payments'
          AND CONSTRAINT_NAME = 'fk_payments_reviewed_by'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'SELECT 1',
    'ALTER TABLE payments ADD CONSTRAINT fk_payments_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = IF(
    EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @oems_schema
          AND TABLE_NAME = 'reviews'
          AND INDEX_NAME = 'idx_reviews_status_created'
    ),
    'SELECT 1',
    'ALTER TABLE reviews ADD INDEX idx_reviews_status_created (status, created_at)'
);
PREPARE oems_migration_statement FROM @oems_sql;
EXECUTE oems_migration_statement;
DEALLOCATE PREPARE oems_migration_statement;

SET @oems_sql = NULL;
SET @oems_schema = NULL;
