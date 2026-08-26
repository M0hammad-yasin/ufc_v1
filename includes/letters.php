<?php
/**
 * United Five Construction - Client Letters Engine
 * Implements Requirements Letter and Decline Letter generation per v5.0 spec
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';


function generateRequirementsLetterData(int $assessmentId, int $phaseNumber): array {
    $pdo = getDbConnection();
    $assessment = getAssessmentDetails($assessmentId);
    if (!$assessment) {
        throw new InvalidArgumentException("Assessment not found");
    }

    // Query RED and AMBER items from the failed phase, ONLY client-facing
    $sql = "
        SELECT 
            q.id AS question_id,
            q.question_number,
            q.question_text,
            q.client_message,
            a.status_light,
            a.answer_value,
            eb.responsible_party,
            eb.reason,
            eb.target_cure_date
        FROM assessment_answers a
        JOIN questions q ON a.question_id = q.id
        JOIN phases p ON q.phase_id = p.id
        LEFT JOIN explain_blocks eb ON (eb.assessment_id = a.assessment_id AND eb.question_id = q.id)
        WHERE a.assessment_id = ? 
          AND p.phase_number = ?
          AND q.visibility = 'CLIENT_FACING'
          AND a.status_light IN ('RED', 'AMBER')
        ORDER BY 
            FIELD(eb.responsible_party, 'CLIENT', 'ARCHITECT', 'LENDER', 'ENGINEER', 'EXPEDITOR', 'UFC', 'OTHER'),
            q.order_index ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$assessmentId, $phaseNumber]);
    $items = $stmt->fetchAll();

    // Group items by responsible party
    $groupedItems = [];
    foreach ($items as $item) {
        $party = $item['responsible_party'] ?: 'CLIENT';
        $groupedItems[$party][] = $item;
    }

    // Response deadline (30 days)
    $deadlineDate = $assessment['hold_deadline_date'] ?: date('Y-m-d', strtotime('+30 days'));
    $formattedDeadline = date('F j, Y', strtotime($deadlineDate));

    return [
        'assessment' => $assessment,
        'phase_number' => $phaseNumber,
        'deadline_formatted' => $formattedDeadline,
        'items' => $items,
        'grouped_items' => $groupedItems
    ];
}

function generateDeclineLetterData(int $assessmentId): array {
    $assessment = getAssessmentDetails($assessmentId);
    if (!$assessment) {
        throw new InvalidArgumentException("Assessment not found");
    }

    $reason = $assessment['decline_reason'] ?: 'STOP_TRIGGER';
    $holdDate = $assessment['hold_deadline_date'] ? date('F j, Y', strtotime($assessment['hold_deadline_date'] . ' -30 days')) : date('F j, Y');

    return [
        'assessment' => $assessment,
        'decline_reason' => $reason,
        'initial_letter_date' => $holdDate,
        'date_issued' => date('F j, Y')
    ];
}
