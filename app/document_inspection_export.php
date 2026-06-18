<?php
declare(strict_types=1);

function document_inspection_task_documents(): array
{
    $tasksPath = __DIR__ . '/../data/tasks.php';
    if (!is_file($tasksPath)) {
        return [];
    }

    $tasks = require $tasksPath;
    if (!is_array($tasks)) {
        return [];
    }

    $documentsByTask = [];
    foreach ($tasks as $taskNumber => $taskConfig) {
        if (!is_array($taskConfig) || !isset($taskConfig['documents']) || !is_array($taskConfig['documents'])) {
            continue;
        }

        $documentsByTask[(int) $taskNumber] = [];
        foreach ($taskConfig['documents'] as $document) {
            if (!is_array($document) || !isset($document['key'])) {
                continue;
            }

            $documentKey = (string) $document['key'];
            $documentsByTask[(int) $taskNumber][$documentKey] = [
                'relevant' => !empty($document['relevant']),
            ];
        }
    }

    return $documentsByTask;
}

function document_inspection_relevant_document(array $documentsByTask, int $taskNumber): string
{
    foreach ($documentsByTask[$taskNumber] ?? [] as $documentKey => $document) {
        if (!empty($document['relevant'])) {
            return (string) $documentKey;
        }
    }

    return '';
}

function document_inspection_default_task_stats(array $documentsByTask, int $taskNumber): array
{
    return [
        'first_doc_opened' => '',
        'first_doc_position' => '',
        'relevant_doc' => document_inspection_relevant_document($documentsByTask, $taskNumber),
        'relevant_doc_position' => '',
        'relevant_doc_opened' => 0,
        'relevant_doc_opened_first' => 0,
        'num_docs_opened' => 0,
        'total_doc_time' => '0.000',
        'relevant_doc_time' => '0.000',
        'doc_open_sequence' => '',
    ];
}

function document_inspection_is_relevant(array $documentsByTask, int $taskNumber, string $documentKey): bool
{
    return !empty($documentsByTask[$taskNumber][$documentKey]['relevant']);
}

function document_inspection_format_seconds(int $milliseconds): string
{
    return number_format(max(0, $milliseconds) / 1000.0, 3, '.', '');
}

function document_inspection_infer_relevant_position(array $positionsByDocument, string $relevantDocument): string
{
    if ($relevantDocument !== '' && isset($positionsByDocument[$relevantDocument])) {
        return (string) $positionsByDocument[$relevantDocument];
    }

    $observedPositions = [];
    foreach ($positionsByDocument as $position) {
        $position = (int) $position;
        if ($position >= 1 && $position <= 3) {
            $observedPositions[$position] = true;
        }
    }

    if (count($observedPositions) !== 2) {
        return '';
    }

    foreach ([1, 2, 3] as $position) {
        if (!isset($observedPositions[$position])) {
            return (string) $position;
        }
    }

    return '';
}

function document_inspection_export_columns(): array
{
    $columns = [
        'participant_id',
        'response_id',
        'condition',
    ];

    foreach ([1, 2] as $taskNumber) {
        foreach ([
            'first_doc_opened',
            'first_doc_position',
            'relevant_doc',
            'relevant_doc_position',
            'relevant_doc_opened',
            'relevant_doc_opened_first',
            'num_docs_opened',
            'total_doc_time',
            'relevant_doc_time',
            'doc_open_sequence',
        ] as $metric) {
            $columns[] = 'task' . $taskNumber . '_' . $metric;
        }
    }

    return $columns;
}

function document_inspection_export_rows(PDO $pdo, bool $includeTestParticipants = false): array
{
    $documentsByTask = document_inspection_task_documents();
    $testFilter = $includeTestParticipants ? '' : ' WHERE participant_code NOT LIKE :test_prefix';
    $participantStmt = $pdo->prepare(
        'SELECT id, participant_code, condition_name
         FROM participants'
        . $testFilter . '
         ORDER BY id ASC'
    );
    if (!$includeTestParticipants) {
        $testPrefix = defined('TEST_PARTICIPANT_PREFIX') ? (string) TEST_PARTICIPANT_PREFIX : 'TEST-';
        $participantStmt->bindValue(':test_prefix', $testPrefix . '%', PDO::PARAM_STR);
    }
    $participantStmt->execute();

    $rowsByParticipant = [];
    foreach ($participantStmt->fetchAll(PDO::FETCH_ASSOC) as $participant) {
        $participantId = (int) ($participant['id'] ?? 0);
        if ($participantId <= 0) {
            continue;
        }

        $row = [
            'participant_id' => (string) $participantId,
            'response_id' => (string) ($participant['participant_code'] ?? ''),
            'condition' => (string) ($participant['condition_name'] ?? ''),
        ];
        foreach ([1, 2] as $taskNumber) {
            foreach (document_inspection_default_task_stats($documentsByTask, $taskNumber) as $metric => $value) {
                $row['task' . $taskNumber . '_' . $metric] = $value;
            }
        }
        $rowsByParticipant[$participantId] = $row;
    }

    if ($rowsByParticipant === []) {
        return [];
    }

    $eventsStmt = $pdo->query(
        'SELECT participant_id, task_number, document_key, event_type, view_ms, event_order, display_order, event_time, id
         FROM document_events
         WHERE task_number IN (1, 2)
         ORDER BY participant_id ASC, task_number ASC, COALESCE(event_order, id) ASC, event_time ASC, id ASC'
    );

    $statsByParticipantTask = [];
    foreach ($eventsStmt->fetchAll(PDO::FETCH_ASSOC) as $event) {
        $participantId = (int) ($event['participant_id'] ?? 0);
        $taskNumber = (int) ($event['task_number'] ?? 0);
        $documentKey = (string) ($event['document_key'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');
        if (!isset($rowsByParticipant[$participantId]) || !in_array($taskNumber, [1, 2], true) || $documentKey === '') {
            continue;
        }

        $compoundKey = $participantId . '|' . $taskNumber;
        if (!isset($statsByParticipantTask[$compoundKey])) {
            $statsByParticipantTask[$compoundKey] = [
                'opened_documents' => [],
                'open_sequence' => [],
                'positions_by_document' => [],
                'total_view_ms' => 0,
                'relevant_view_ms' => 0,
            ];
        }

        $displayOrder = isset($event['display_order']) ? (int) $event['display_order'] : 0;
        if ($displayOrder > 0 && !isset($statsByParticipantTask[$compoundKey]['positions_by_document'][$documentKey])) {
            $statsByParticipantTask[$compoundKey]['positions_by_document'][$documentKey] = $displayOrder;
        }

        if ($eventType === 'open') {
            $statsByParticipantTask[$compoundKey]['opened_documents'][$documentKey] = true;
            $statsByParticipantTask[$compoundKey]['open_sequence'][] = $documentKey;
            continue;
        }

        if ($eventType === 'close') {
            $viewMs = max(0, (int) ($event['view_ms'] ?? 0));
            $statsByParticipantTask[$compoundKey]['total_view_ms'] += $viewMs;
            if (document_inspection_is_relevant($documentsByTask, $taskNumber, $documentKey)) {
                $statsByParticipantTask[$compoundKey]['relevant_view_ms'] += $viewMs;
            }
        }
    }

    foreach ($statsByParticipantTask as $compoundKey => $stats) {
        [$participantIdRaw, $taskNumberRaw] = explode('|', $compoundKey, 2);
        $participantId = (int) $participantIdRaw;
        $taskNumber = (int) $taskNumberRaw;
        if (!isset($rowsByParticipant[$participantId])) {
            continue;
        }

        $prefix = 'task' . $taskNumber . '_';
        $relevantDocument = document_inspection_relevant_document($documentsByTask, $taskNumber);
        $openSequence = array_values($stats['open_sequence']);
        $firstDocument = $openSequence[0] ?? '';
        $positionsByDocument = $stats['positions_by_document'];
        $relevantOpened = $relevantDocument !== '' && isset($stats['opened_documents'][$relevantDocument]) ? 1 : 0;

        $rowsByParticipant[$participantId][$prefix . 'first_doc_opened'] = $firstDocument;
        $rowsByParticipant[$participantId][$prefix . 'first_doc_position'] = $firstDocument !== '' && isset($positionsByDocument[$firstDocument])
            ? (string) $positionsByDocument[$firstDocument]
            : '';
        $rowsByParticipant[$participantId][$prefix . 'relevant_doc'] = $relevantDocument;
        $rowsByParticipant[$participantId][$prefix . 'relevant_doc_position'] = document_inspection_infer_relevant_position($positionsByDocument, $relevantDocument);
        $rowsByParticipant[$participantId][$prefix . 'relevant_doc_opened'] = $relevantOpened;
        $rowsByParticipant[$participantId][$prefix . 'relevant_doc_opened_first'] = ($firstDocument !== '' && $firstDocument === $relevantDocument) ? 1 : 0;
        $rowsByParticipant[$participantId][$prefix . 'num_docs_opened'] = count($stats['opened_documents']);
        $rowsByParticipant[$participantId][$prefix . 'total_doc_time'] = document_inspection_format_seconds((int) $stats['total_view_ms']);
        $rowsByParticipant[$participantId][$prefix . 'relevant_doc_time'] = document_inspection_format_seconds((int) $stats['relevant_view_ms']);
        $rowsByParticipant[$participantId][$prefix . 'doc_open_sequence'] = implode('>', $openSequence);
    }

    return array_values($rowsByParticipant);
}
