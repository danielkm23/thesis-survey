-- Rollback for live_align_survey_schema.sql
-- Note: this restores dropped columns as nullable (without historical data).

SELECT DATABASE() AS current_database;

ALTER TABLE postsurvey_responses
    ADD COLUMN IF NOT EXISTS ai_lit_5 TINYINT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS ai_lit_6 TINYINT UNSIGNED NULL;

ALTER TABLE task_responses
    ADD COLUMN IF NOT EXISTS decision_justification TEXT NULL;

ALTER TABLE participants
    DROP COLUMN IF EXISTS prolific;

DROP TABLE IF EXISTS raffle_entries;

SELECT 'postsurvey_responses columns after rollback' AS section;
SHOW COLUMNS FROM postsurvey_responses;

SELECT 'task_responses columns after rollback' AS section;
SHOW COLUMNS FROM task_responses;
