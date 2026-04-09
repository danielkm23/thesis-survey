CREATE TABLE participants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_code VARCHAR(50) NOT NULL UNIQUE,
    condition_name VARCHAR(50) NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL
);

CREATE TABLE document_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    task_number INT UNSIGNED NOT NULL,
    document_key VARCHAR(100) NOT NULL,
    event_type VARCHAR(20) NOT NULL,
    event_time DATETIME NOT NULL,
    view_ms INT UNSIGNED NULL,
    event_order INT UNSIGNED NULL,
    display_order INT UNSIGNED NULL,
    INDEX idx_document_events_participant (participant_id),
    INDEX idx_document_events_task (task_number),
    CONSTRAINT fk_document_events_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id)
);

CREATE TABLE task_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    task_number INT UNSIGNED NOT NULL,
    ai_correct TINYINT(1) NOT NULL,
    reliance_choice VARCHAR(50) NOT NULL,
    final_response TEXT NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL,
    active_reflection TEXT NULL,
    verification_intention VARCHAR(60) NULL,
    task_started_at DATETIME NULL,
    task_submitted_at DATETIME NULL,
    duration_seconds INT UNSIGNED NULL,
    short_time_flag TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_task_responses_participant (participant_id),
    INDEX idx_task_responses_task (task_number),
    CONSTRAINT fk_task_responses_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id)
);

CREATE TABLE postsurvey_responses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id INT UNSIGNED NOT NULL,
    ai_lit_1 TINYINT UNSIGNED NOT NULL,
    ai_lit_2 TINYINT UNSIGNED NOT NULL,
    ai_lit_3 TINYINT UNSIGNED NOT NULL,
    ai_lit_4 TINYINT UNSIGNED NOT NULL,
    ai_lit_5 TINYINT UNSIGNED NOT NULL,
    ai_lit_6 TINYINT UNSIGNED NOT NULL,
    instruction_notice TINYINT UNSIGNED NOT NULL,
    task_realism TINYINT UNSIGNED NOT NULL,
    crt_1 DECIMAL(10,2) NOT NULL,
    crt_2 DECIMAL(10,2) NOT NULL,
    crt_3 DECIMAL(10,2) NOT NULL,
    ai_experience VARCHAR(50) NOT NULL,
    age SMALLINT UNSIGNED NOT NULL,
    gender VARCHAR(50) NOT NULL,
    education VARCHAR(50) NOT NULL,
    submitted_at DATETIME NOT NULL,
    duration_seconds INT UNSIGNED NULL,
    short_time_flag TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_postsurvey_participant (participant_id),
    CONSTRAINT fk_postsurvey_participant
        FOREIGN KEY (participant_id) REFERENCES participants(id)
);
