<?php
declare(strict_types=1);

function analysis_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
        $cache[$cacheKey] = $stmt !== false && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function analysis_reflection_extract_sql(string $key): string
{
    return "CASE
        WHEN LOCATE('{$key}=', tr.active_reflection) > 0
            THEN NULLIF(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(tr.active_reflection, '{$key}=', -1), '\n', 1)), '')
        ELSE NULL
    END";
}

function analysis_reliance_score(?string $relianceChoice): ?int
{
    if ($relianceChoice === null) {
        return null;
    }
    $value = strtolower(trim($relianceChoice));
    if ($value === '') {
        return null;
    }

    $score4 = ['use_as_is', 'high_reliance', 'high reliance'];
    $score3 = ['minor_edits', 'moderate_edits', 'minor_reliance', 'moderate_reliance'];
    $score2 = ['major_edits', 'low_reliance', 'low reliance'];
    $score1 = ['discarded', 'did_not_use', 'no_reliance', 'no reliance'];

    if (in_array($value, $score4, true)) {
        return 4;
    }
    if (in_array($value, $score3, true)) {
        return 3;
    }
    if (in_array($value, $score2, true)) {
        return 2;
    }
    if (in_array($value, $score1, true)) {
        return 1;
    }

    return null;
}

function analysis_ai_experience_score(?string $aiExperience): ?int
{
    if ($aiExperience === null) {
        return null;
    }
    $value = trim(strtolower($aiExperience));
    if ($value === '') {
        return null;
    }

    return match ($value) {
        'never' => 0,
        'less_than_monthly' => 1,
        'few_times_per_month' => 2,
        'few_times_per_week' => 3,
        'daily' => 4,
        default => null,
    };
}

function analysis_crt_correctness(?array $post): array
{
    if (!is_array($post)) {
        return [
            'crt_1_correct' => null,
            'crt_2_correct' => null,
            'crt_3_correct' => null,
            'crt_score' => null,
        ];
    }

    if (!isset($post['crt_1'], $post['crt_2'], $post['crt_3'])) {
        return [
            'crt_1_correct' => null,
            'crt_2_correct' => null,
            'crt_3_correct' => null,
            'crt_score' => null,
        ];
    }

    $crt1 = (float) $post['crt_1'];
    $crt2 = (float) $post['crt_2'];
    $crt3 = (float) $post['crt_3'];
    $crt1Correct = abs($crt1 - 0.05) <= 0.01 ? 1 : 0;
    $crt2Correct = abs($crt2 - 5.0) <= 0.01 ? 1 : 0;
    $crt3Correct = abs($crt3 - 47.0) <= 0.01 ? 1 : 0;

    return [
        'crt_1_correct' => $crt1Correct,
        'crt_2_correct' => $crt2Correct,
        'crt_3_correct' => $crt3Correct,
        'crt_score' => $crt1Correct + $crt2Correct + $crt3Correct,
    ];
}

function analysis_postsurvey_metrics(?array $post): array
{
    if (!is_array($post)) {
        return [
            'ai_literacy_score' => null,
            'ai_experience_score' => null,
            'serious_effort' => null,
            'instructions_clarity' => null,
            'task_realism' => null,
            'instruction_notice' => null,
            'age' => null,
            'gender' => null,
            'education' => null,
            'crt_1_correct' => null,
            'crt_2_correct' => null,
            'crt_3_correct' => null,
            'crt_score' => null,
        ];
    }

    $aiValues = [];
    foreach (['ai_lit_1', 'ai_lit_2', 'ai_lit_3', 'ai_lit_4'] as $field) {
        if (!isset($post[$field]) || $post[$field] === null || $post[$field] === '') {
            $aiValues = [];
            break;
        }
        $aiValues[] = (float) $post[$field];
    }
    $aiLiteracyScore = count($aiValues) === 4 ? array_sum($aiValues) / 4.0 : null;
    $crtMetrics = analysis_crt_correctness($post);

    return [
        'ai_literacy_score' => $aiLiteracyScore,
        'ai_experience_score' => analysis_ai_experience_score(isset($post['ai_experience']) ? (string) $post['ai_experience'] : null),
        'serious_effort' => isset($post['serious_effort']) && $post['serious_effort'] !== '' ? (int) $post['serious_effort'] : null,
        'instructions_clarity' => isset($post['instructions_clarity']) && $post['instructions_clarity'] !== '' ? (int) $post['instructions_clarity'] : null,
        'task_realism' => isset($post['task_realism']) && $post['task_realism'] !== '' ? (int) $post['task_realism'] : null,
        'instruction_notice' => isset($post['instruction_notice']) && $post['instruction_notice'] !== '' ? (int) $post['instruction_notice'] : null,
        'age' => isset($post['age']) && $post['age'] !== '' ? (int) $post['age'] : null,
        'gender' => $post['gender'] ?? null,
        'education' => $post['education'] ?? null,
        'crt_1_correct' => $crtMetrics['crt_1_correct'],
        'crt_2_correct' => $crtMetrics['crt_2_correct'],
        'crt_3_correct' => $crtMetrics['crt_3_correct'],
        'crt_score' => $crtMetrics['crt_score'],
    ];
}

function analysis_relevant_document_by_task(): array
{
    $map = [];
    $tasksPath = __DIR__ . '/../data/tasks.php';
    if (!is_file($tasksPath)) {
        return $map;
    }

    $tasks = require $tasksPath;
    if (!is_array($tasks)) {
        return $map;
    }

    foreach ($tasks as $taskNumber => $taskConfig) {
        if (!is_array($taskConfig) || !isset($taskConfig['documents']) || !is_array($taskConfig['documents'])) {
            continue;
        }
        foreach ($taskConfig['documents'] as $document) {
            if (!is_array($document)) {
                continue;
            }
            if (!empty($document['relevant']) && isset($document['key'])) {
                $map[(int) $taskNumber] = (string) $document['key'];
                break;
            }
        }
    }

    return $map;
}

function analysis_task_level(PDO $pdo): array
{
    $hasManualResponseCorrectness = analysis_column_exists($pdo, 'task_responses', 'manual_response_correctness');
    $hasSelectedResponseOption = analysis_column_exists($pdo, 'task_responses', 'selected_response_option');
    $hasSelectedOptionKey = analysis_column_exists($pdo, 'task_responses', 'selected_option_key');
    $hasSelectedDisplayLetter = analysis_column_exists($pdo, 'task_responses', 'selected_display_letter');
    $hasResponseCorrectness = analysis_column_exists($pdo, 'task_responses', 'response_correctness');
    $hasManualCodeRequired = analysis_column_exists($pdo, 'task_responses', 'manual_code_required');
    $hasCustomResponseText = analysis_column_exists($pdo, 'task_responses', 'custom_response_text');
    $hasVerificationIntention = analysis_column_exists($pdo, 'task_responses', 'verification_intention');
    $hasRelevantDocumentOpened = analysis_column_exists($pdo, 'task_responses', 'relevant_document_opened');
    $hasNumberDocumentsOpened = analysis_column_exists($pdo, 'task_responses', 'number_documents_opened');
    $hasTotalDocumentViewTimeMs = analysis_column_exists($pdo, 'task_responses', 'total_document_view_time_ms');
    $hasRelevantDocumentViewTimeMs = analysis_column_exists($pdo, 'task_responses', 'relevant_document_view_time_ms');
    $hasDurationSeconds = analysis_column_exists($pdo, 'task_responses', 'duration_seconds');
    $hasShortTimeFlag = analysis_column_exists($pdo, 'task_responses', 'short_time_flag');
    $hasIsRelevantInEvents = analysis_column_exists($pdo, 'document_events', 'is_relevant');

    $manualResponseCorrectnessSql = $hasManualResponseCorrectness
        ? 'tr.manual_response_correctness'
        : 'NULL';
    $selectedResponseOptionSql = $hasSelectedResponseOption
        ? 'tr.selected_response_option'
        : analysis_reflection_extract_sql('selected_response_option');
    $selectedOptionKeySql = $hasSelectedOptionKey
        ? 'tr.selected_option_key'
        : analysis_reflection_extract_sql('selected_option_key');
    $selectedDisplayLetterSql = $hasSelectedDisplayLetter
        ? 'tr.selected_display_letter'
        : analysis_reflection_extract_sql('selected_display_letter');
    $responseCorrectnessSql = $hasResponseCorrectness
        ? 'tr.response_correctness'
        : analysis_reflection_extract_sql('response_correctness');
    $manualCodeRequiredSql = $hasManualCodeRequired
        ? 'tr.manual_code_required'
        : analysis_reflection_extract_sql('manual_code_required');
    $customResponseTextSql = $hasCustomResponseText
        ? 'tr.custom_response_text'
        : analysis_reflection_extract_sql('custom_response_text');
    $verificationIntentionSql = $hasVerificationIntention
        ? 'tr.verification_intention'
        : analysis_reflection_extract_sql('verification_intention');
    $relevantDocumentOpenedSql = $hasRelevantDocumentOpened
        ? 'tr.relevant_document_opened'
        : 'NULL';
    $numberDocumentsOpenedSql = $hasNumberDocumentsOpened
        ? 'tr.number_documents_opened'
        : 'NULL';
    $totalDocumentViewTimeMsSql = $hasTotalDocumentViewTimeMs
        ? 'tr.total_document_view_time_ms'
        : 'NULL';
    $relevantDocumentViewTimeMsSql = $hasRelevantDocumentViewTimeMs
        ? 'tr.relevant_document_view_time_ms'
        : 'NULL';
    $durationSecondsSql = $hasDurationSeconds ? 'tr.duration_seconds' : 'NULL';
    $shortTimeFlagSql = $hasShortTimeFlag ? 'tr.short_time_flag' : '0';
    $relevantOpenFromEventsSql = $hasIsRelevantInEvents
        ? 'MAX(CASE WHEN de.event_type = \'open\' AND de.is_relevant = 1 THEN 1 ELSE 0 END)'
        : 'NULL';
    $relevantViewMsFromEventsSql = $hasIsRelevantInEvents
        ? 'COALESCE(SUM(CASE WHEN de.event_type = \'close\' AND de.is_relevant = 1 THEN COALESCE(de.view_ms, 0) ELSE 0 END), 0)'
        : 'NULL';

    $sql = 'SELECT
        p.id AS participant_id,
        p.participant_code,
        p.condition_name,
        tr.task_number,
        tr.ai_correct,
        ' . $selectedResponseOptionSql . ' AS selected_response_option,
        ' . $selectedOptionKeySql . ' AS selected_option_key,
        ' . $selectedDisplayLetterSql . ' AS selected_display_letter,
        ' . $responseCorrectnessSql . ' AS response_correctness,
        ' . $manualResponseCorrectnessSql . ' AS manual_response_correctness,
        tr.final_response,
        ' . $manualCodeRequiredSql . ' AS manual_code_required,
        ' . $customResponseTextSql . ' AS custom_response_text,
        tr.reliance_choice,
        tr.confidence,
        ' . $relevantDocumentOpenedSql . ' AS relevant_document_opened_raw,
        ' . $numberDocumentsOpenedSql . ' AS number_documents_opened_raw,
        ' . $totalDocumentViewTimeMsSql . ' AS total_document_view_time_ms_raw,
        ' . $relevantDocumentViewTimeMsSql . ' AS relevant_document_view_time_ms_raw,
        ' . $durationSecondsSql . ' AS duration_seconds,
        ' . $shortTimeFlagSql . ' AS short_time_flag,
        ' . $verificationIntentionSql . ' AS verification_intention,
        tr.active_reflection,
        deagg.number_documents_opened_from_events,
        deagg.total_document_view_time_ms_from_events,
        deagg.relevant_document_opened_from_events,
        deagg.relevant_document_view_time_ms_from_events
    FROM task_responses tr
    JOIN participants p ON p.id = tr.participant_id
    LEFT JOIN (
        SELECT
            de.participant_id,
            de.task_number,
            COUNT(DISTINCT CASE WHEN de.event_type = \'open\' THEN de.document_key END) AS number_documents_opened_from_events,
            COALESCE(SUM(CASE WHEN de.event_type = \'close\' THEN COALESCE(de.view_ms, 0) ELSE 0 END), 0) AS total_document_view_time_ms_from_events,
            ' . $relevantOpenFromEventsSql . ' AS relevant_document_opened_from_events,
            ' . $relevantViewMsFromEventsSql . ' AS relevant_document_view_time_ms_from_events
        FROM document_events de
        GROUP BY de.participant_id, de.task_number
    ) AS deagg
        ON deagg.participant_id = tr.participant_id
        AND deagg.task_number = tr.task_number
    ORDER BY tr.participant_id ASC, tr.task_number ASC, tr.id ASC';

    $postSurveyByParticipant = [];
    $postStmt = $pdo->query('SELECT * FROM postsurvey_responses ORDER BY participant_id ASC, id DESC');
    foreach ($postStmt->fetchAll(PDO::FETCH_ASSOC) as $postRow) {
        $participantId = (int) ($postRow['participant_id'] ?? 0);
        if ($participantId <= 0 || isset($postSurveyByParticipant[$participantId])) {
            continue;
        }
        $postSurveyByParticipant[$participantId] = $postRow;
    }

    $eventStatsByParticipantTask = [];
    $relevantDocumentByTask = analysis_relevant_document_by_task();
    $isRelevantSelect = $hasIsRelevantInEvents ? 'de.is_relevant' : 'NULL AS is_relevant';
    $eventStmt = $pdo->query(
        'SELECT
            de.participant_id,
            de.task_number,
            de.document_key,
            de.event_type,
            de.view_ms,
            ' . $isRelevantSelect . '
         FROM document_events de'
    );
    foreach ($eventStmt->fetchAll(PDO::FETCH_ASSOC) as $eventRow) {
        $participantId = (int) ($eventRow['participant_id'] ?? 0);
        $taskNumber = (int) ($eventRow['task_number'] ?? 0);
        $documentKey = (string) ($eventRow['document_key'] ?? '');
        $eventType = (string) ($eventRow['event_type'] ?? '');
        if ($participantId <= 0 || $taskNumber <= 0 || $documentKey === '') {
            continue;
        }
        $compoundKey = $participantId . '|' . $taskNumber;
        if (!isset($eventStatsByParticipantTask[$compoundKey])) {
            $eventStatsByParticipantTask[$compoundKey] = [
                'opened_keys' => [],
                'total_view_ms' => 0,
                'relevant_opened' => 0,
                'relevant_view_ms' => 0,
            ];
        }

        $isRelevantByEvent = isset($eventRow['is_relevant']) && $eventRow['is_relevant'] !== null
            ? ((int) $eventRow['is_relevant'] === 1)
            : null;
        $isRelevantByConfig = isset($relevantDocumentByTask[$taskNumber]) && $relevantDocumentByTask[$taskNumber] === $documentKey;
        $isRelevantDocument = $isRelevantByEvent === null ? $isRelevantByConfig : $isRelevantByEvent;

        if ($eventType === 'open') {
            $eventStatsByParticipantTask[$compoundKey]['opened_keys'][$documentKey] = true;
            if ($isRelevantDocument) {
                $eventStatsByParticipantTask[$compoundKey]['relevant_opened'] = 1;
            }
        }
        if ($eventType === 'close') {
            $viewMs = max(0, (int) ($eventRow['view_ms'] ?? 0));
            $eventStatsByParticipantTask[$compoundKey]['total_view_ms'] += $viewMs;
            if ($isRelevantDocument) {
                $eventStatsByParticipantTask[$compoundKey]['relevant_view_ms'] += $viewMs;
            }
        }
    }

    foreach ($eventStatsByParticipantTask as $compoundKey => $stats) {
        $eventStatsByParticipantTask[$compoundKey]['number_documents_opened'] = count($stats['opened_keys']);
        unset($eventStatsByParticipantTask[$compoundKey]['opened_keys']);
    }

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rawRows = [];
    $participantTaskFlags = [];

    foreach ($rows as $row) {
        $manualResponseCorrectness = $row['manual_response_correctness'] !== null
            ? (int) $row['manual_response_correctness']
            : null;
        $responseCorrectness = $row['response_correctness'] !== null && $row['response_correctness'] !== ''
            ? (int) $row['response_correctness']
            : null;
        $finalDecisionCorrect = $manualResponseCorrectness ?? $responseCorrectness;

        $compoundKey = ((int) $row['participant_id']) . '|' . ((int) $row['task_number']);
        $eventStats = $eventStatsByParticipantTask[$compoundKey] ?? null;

        $relevantDocumentOpened = $row['relevant_document_opened_raw'] !== null
            ? (int) $row['relevant_document_opened_raw']
            : (
                $row['relevant_document_opened_from_events'] !== null
                    ? (int) $row['relevant_document_opened_from_events']
                    : (int) ($eventStats['relevant_opened'] ?? 0)
            );
        $numberDocumentsOpened = $row['number_documents_opened_raw'] !== null
            ? (int) $row['number_documents_opened_raw']
            : (
                $row['number_documents_opened_from_events'] !== null
                    ? (int) $row['number_documents_opened_from_events']
                    : (int) ($eventStats['number_documents_opened'] ?? 0)
            );
        $totalDocumentViewTimeMs = $row['total_document_view_time_ms_raw'] !== null
            ? (int) $row['total_document_view_time_ms_raw']
            : (
                $row['total_document_view_time_ms_from_events'] !== null
                    ? (int) $row['total_document_view_time_ms_from_events']
                    : (int) ($eventStats['total_view_ms'] ?? 0)
            );
        $relevantDocumentViewTimeMs = $row['relevant_document_view_time_ms_raw'] !== null
            ? (int) $row['relevant_document_view_time_ms_raw']
            : (
                $row['relevant_document_view_time_ms_from_events'] !== null
                    ? (int) $row['relevant_document_view_time_ms_from_events']
                    : (int) ($eventStats['relevant_view_ms'] ?? 0)
            );
        $selectedOptionKey = $row['selected_option_key'] !== null ? (string) $row['selected_option_key'] : null;
        $relianceChoice = $row['reliance_choice'] !== null ? (string) $row['reliance_choice'] : null;

        $participantId = (int) $row['participant_id'];
        if (!isset($participantTaskFlags[$participantId])) {
            $participantTaskFlags[$participantId] = ['tasks' => 0, 'short_flags' => 0];
        }
        $participantTaskFlags[$participantId]['tasks']++;
        if ((int) ($row['short_time_flag'] ?? 0) === 1) {
            $participantTaskFlags[$participantId]['short_flags']++;
        }

        $rawRows[] = [
            'participant_id' => (int) $row['participant_id'],
            'participant_code' => (string) $row['participant_code'],
            'condition_name' => (string) $row['condition_name'],
            'task_number' => (int) $row['task_number'],
            'ai_correct' => (int) $row['ai_correct'],
            'selected_response_option' => $row['selected_response_option'] !== null ? (string) $row['selected_response_option'] : null,
            'selected_option_key' => $selectedOptionKey,
            'selected_display_letter' => $row['selected_display_letter'] !== null ? (string) $row['selected_display_letter'] : null,
            'final_response' => $row['final_response'] !== null ? (string) $row['final_response'] : null,
            'response_correctness' => $responseCorrectness,
            'manual_response_correctness' => $manualResponseCorrectness,
            'manual_code_required' => $row['manual_code_required'] !== null ? (int) $row['manual_code_required'] : 0,
            'custom_response_text' => $row['custom_response_text'] !== null ? (string) $row['custom_response_text'] : null,
            'final_decision_correct' => $finalDecisionCorrect,
            'reliance_choice' => $relianceChoice,
            'reliance_score' => analysis_reliance_score($relianceChoice),
            'confidence' => $row['confidence'] !== null ? (int) $row['confidence'] : null,
            'relevant_document_opened' => $relevantDocumentOpened,
            'number_documents_opened' => $numberDocumentsOpened,
            'total_document_view_time_ms' => $totalDocumentViewTimeMs,
            'relevant_document_view_time_ms' => $relevantDocumentViewTimeMs,
            'total_document_view_time_sec' => round($totalDocumentViewTimeMs / 1000.0, 3),
            'relevant_document_view_time_sec' => $relevantDocumentViewTimeMs === null ? null : round($relevantDocumentViewTimeMs / 1000.0, 3),
            'duration_seconds' => $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null,
            'short_time_flag' => (int) ($row['short_time_flag'] ?? 0),
            'verification_intention' => $row['verification_intention'] !== null ? (string) $row['verification_intention'] : null,
            'active_reflection' => $row['active_reflection'] !== null ? (string) $row['active_reflection'] : null,
            'inspection_any' => $numberDocumentsOpened > 0 ? 1 : 0,
            'inspection_relevant' => $relevantDocumentOpened > 0 ? 1 : 0,
            'opened_all_docs' => $numberDocumentsOpened >= 3 ? 1 : 0,
            'overreliance_error' => ((int) $row['ai_correct'] === 0 && $selectedOptionKey === 'ai_consistent_wrong') ? 1 : 0,
            'underreliance_or_false_alarm_error' => $selectedOptionKey === 'too_strict' ? 1 : 0,
        ];
    }

    $analysisRows = [];
    foreach ($rawRows as $row) {
        $participantId = (int) $row['participant_id'];
        $post = $postSurveyByParticipant[$participantId] ?? null;
        $postMetrics = analysis_postsurvey_metrics($post);
        $taskFlags = $participantTaskFlags[$participantId] ?? ['tasks' => 0, 'short_flags' => 0];
        $hasBothShortFlags = $taskFlags['tasks'] >= 2 && $taskFlags['short_flags'] >= 2;
        $seriousEffort = $postMetrics['serious_effort'];
        $lowQualityResponse = (($seriousEffort !== null && $seriousEffort <= 2) || $hasBothShortFlags) ? 1 : 0;

        $analysisRows[] = array_merge($row, $postMetrics, [
            'low_quality_response' => $lowQualityResponse,
        ]);
    }

    return $analysisRows;
}

function analysis_participant_summary(PDO $pdo): array
{
    $taskRows = analysis_task_level($pdo);
    $tasksByParticipant = [];
    foreach ($taskRows as $taskRow) {
        $participantId = (int) $taskRow['participant_id'];
        if (!isset($tasksByParticipant[$participantId])) {
            $tasksByParticipant[$participantId] = [];
        }
        $tasksByParticipant[$participantId][] = $taskRow;
    }

    $postSurveyByParticipant = [];
    $postStmt = $pdo->query('SELECT * FROM postsurvey_responses ORDER BY participant_id ASC, id DESC');
    foreach ($postStmt->fetchAll(PDO::FETCH_ASSOC) as $postRow) {
        $participantId = (int) ($postRow['participant_id'] ?? 0);
        if ($participantId <= 0 || isset($postSurveyByParticipant[$participantId])) {
            continue;
        }
        $postSurveyByParticipant[$participantId] = $postRow;
    }

    $participantsStmt = $pdo->query(
        'SELECT id AS participant_id, participant_code, condition_name, started_at, completed_at
         FROM participants
         ORDER BY id ASC'
    );
    $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

    $summaryRows = [];
    foreach ($participants as $participant) {
        $participantId = (int) $participant['participant_id'];
        $participantTaskRows = $tasksByParticipant[$participantId] ?? [];
        $tasksCompleted = count($participantTaskRows);

        $correctCount = 0;
        $correctAiTaskCorrect = 0;
        $incorrectAiTaskCorrect = 0;
        $relevantDocOpenCount = 0;
        $docsOpenedSum = 0.0;
        $docsOpenedCount = 0;
        $totalDocTimeSecSum = 0.0;
        $totalDocTimeSecCount = 0;
        $relevantDocTimeSecSum = 0.0;
        $relevantDocTimeSecCount = 0;
        $confidenceSum = 0.0;
        $confidenceCount = 0;
        $lowQualityResponse = 0;

        foreach ($participantTaskRows as $taskRow) {
            $finalDecisionCorrect = $taskRow['final_decision_correct'];
            $aiCorrect = (int) $taskRow['ai_correct'];
            if ($finalDecisionCorrect === 1) {
                $correctCount++;
                if ($aiCorrect === 1) {
                    $correctAiTaskCorrect++;
                } else {
                    $incorrectAiTaskCorrect++;
                }
            }
            if (($taskRow['inspection_relevant'] ?? null) === 1) {
                $relevantDocOpenCount++;
            }
            if ($taskRow['number_documents_opened'] !== null) {
                $docsOpenedSum += (float) $taskRow['number_documents_opened'];
                $docsOpenedCount++;
            }
            if ($taskRow['total_document_view_time_sec'] !== null) {
                $totalDocTimeSecSum += (float) $taskRow['total_document_view_time_sec'];
                $totalDocTimeSecCount++;
            }
            if ($taskRow['relevant_document_view_time_sec'] !== null) {
                $relevantDocTimeSecSum += (float) $taskRow['relevant_document_view_time_sec'];
                $relevantDocTimeSecCount++;
            }
            if ($taskRow['confidence'] !== null) {
                $confidenceSum += (float) $taskRow['confidence'];
                $confidenceCount++;
            }
            if ((int) ($taskRow['low_quality_response'] ?? 0) === 1) {
                $lowQualityResponse = 1;
            }
        }

        $post = $postSurveyByParticipant[$participantId] ?? null;
        $postMetrics = analysis_postsurvey_metrics($post);

        $summaryRows[] = [
            'participant_id' => $participantId,
            'participant_code' => (string) $participant['participant_code'],
            'condition_name' => (string) $participant['condition_name'],
            'tasks_completed' => $tasksCompleted,
            'correct_count' => $correctCount,
            'correct_pct' => $tasksCompleted > 0 ? round(($correctCount / $tasksCompleted) * 100.0, 2) : null,
            'correct_ai_task_correct' => $correctAiTaskCorrect,
            'incorrect_ai_task_correct' => $incorrectAiTaskCorrect,
            'relevant_doc_open_count' => $relevantDocOpenCount,
            'relevant_doc_open_rate' => $tasksCompleted > 0 ? round($relevantDocOpenCount / $tasksCompleted, 4) : null,
            'avg_confidence' => $confidenceCount > 0 ? round($confidenceSum / $confidenceCount, 4) : null,
            'avg_docs_opened' => $docsOpenedCount > 0 ? round($docsOpenedSum / $docsOpenedCount, 4) : null,
            'avg_total_doc_time_sec' => $totalDocTimeSecCount > 0 ? round($totalDocTimeSecSum / $totalDocTimeSecCount, 4) : null,
            'avg_relevant_doc_time_sec' => $relevantDocTimeSecCount > 0 ? round($relevantDocTimeSecSum / $relevantDocTimeSecCount, 4) : null,
            'ai_literacy_score' => $postMetrics['ai_literacy_score'],
            'ai_experience_score' => $postMetrics['ai_experience_score'],
            'crt_score' => $postMetrics['crt_score'],
            'serious_effort' => $postMetrics['serious_effort'],
            'instructions_clarity' => $postMetrics['instructions_clarity'],
            'task_realism' => $postMetrics['task_realism'],
            'instruction_notice' => $postMetrics['instruction_notice'],
            'low_quality_response' => $lowQualityResponse,
            'age' => $postMetrics['age'],
            'gender' => $postMetrics['gender'],
            'education' => $postMetrics['education'],
            // Backward-compatible extras used by some dashboard blocks.
            'started_at' => $participant['started_at'],
            'completed_at' => $participant['completed_at'],
            'overreliance_error_count' => array_sum(array_map(
                static fn (array $taskRow): int => (int) ($taskRow['overreliance_error'] ?? 0),
                $participantTaskRows
            )),
            'ai_experience' => is_array($post) ? ($post['ai_experience'] ?? null) : null,
        ];
    }

    return $summaryRows;
}
