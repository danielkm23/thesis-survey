-- Live schema alignment for current survey version.
-- Goal: keep only fields that are actively collected by the survey flow.
--
-- Safe to run multiple times.
-- Compatibility: avoids ALTER TABLE ... IF [NOT] EXISTS so it works on older MySQL variants too.

SELECT DATABASE() AS current_database;

-- 1) Ensure active post-survey columns exist.
SET @db_name = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'serious_effort') = 0,
    'ALTER TABLE postsurvey_responses ADD COLUMN serious_effort TINYINT UNSIGNED NOT NULL',
    'SELECT ''serious_effort already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'instructions_clarity') = 0,
    'ALTER TABLE postsurvey_responses ADD COLUMN instructions_clarity TINYINT UNSIGNED NOT NULL',
    'SELECT ''instructions_clarity already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'instruction_notice') = 0,
    'ALTER TABLE postsurvey_responses ADD COLUMN instruction_notice TINYINT UNSIGNED NOT NULL',
    'SELECT ''instruction_notice already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'task_realism') = 0,
    'ALTER TABLE postsurvey_responses ADD COLUMN task_realism TINYINT UNSIGNED NOT NULL',
    'SELECT ''task_realism already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Drop deprecated post-survey columns if present.
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'ai_lit_5') > 0,
    'ALTER TABLE postsurvey_responses DROP COLUMN ai_lit_5',
    'SELECT ''ai_lit_5 already absent'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'postsurvey_responses' AND COLUMN_NAME = 'ai_lit_6') > 0,
    'ALTER TABLE postsurvey_responses DROP COLUMN ai_lit_6',
    'SELECT ''ai_lit_6 already absent'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Drop deprecated task field if present.
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'task_responses' AND COLUMN_NAME = 'decision_justification') > 0,
    'ALTER TABLE task_responses DROP COLUMN decision_justification',
    'SELECT ''decision_justification already absent'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Ensure raffle table exists.
CREATE TABLE IF NOT EXISTS raffle_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    INDEX idx_raffle_entries_email (email),
    CONSTRAINT fk_raffle_entries_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Verification output.
SELECT 'postsurvey_responses columns' AS section;
SHOW COLUMNS FROM postsurvey_responses;

SELECT 'task_responses columns' AS section;
SHOW COLUMNS FROM task_responses;

SELECT 'raffle_entries table' AS section;
SHOW TABLES LIKE 'raffle_entries';
