<?php
/**
 * United Five Construction - Seed Demonstration Assessments
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';

echo "Seeding Realistic Demonstration Leads...\n";

try {
    $pdo = getDbConnection();

    // 1. Fully Passed Lead: "Metropolitan Commercial Plaza" (All 4 Phases PASS -> PROCEED TO PROPOSAL)
    $stmt1 = $pdo->prepare("
        INSERT INTO assessments 
        (assessment_number, client_name, client_email, client_phone, project_address, project_type, estimated_budget, assessor_id, current_phase, status, completed_at)
        VALUES (?, 'Metropolitan Commercial Plaza LLC', 'development@metrocommercial.com', '(212) 555-8900', '350 5th Avenue, Suite 4200, New York, NY 10118', 'Commercial Renovation', 2750000.00, 2, 4, 'PROCEED_TO_PROPOSAL', NOW())
    ");
    $assNum1 = 'UFC-' . date('Ymd') . '-A101';
    $stmt1->execute([$assNum1]);
    $ass1Id = (int)$pdo->lastInsertId();

    // Answer all 37 questions affirmatively
    $allQuestions = $pdo->query("SELECT * FROM questions ORDER BY order_index ASC")->fetchAll();
    foreach ($allQuestions as $q) {
        $val = !empty($q['is_reversed']) ? 'NO' : 'YES';
        if ($q['question_number'] === '1.1') $val = 'APPROVED_PERMIT_ISSUED';
        if ($q['question_number'] === '2.2') $val = 'SELF_FUNDED';
        if ($q['response_type'] === 'SCALE_1_10') $val = 9;
        if ($q['response_type'] === 'MULTI_SELECT') $val = ['ARCHITECTURAL', 'STRUCTURAL', 'MECHANICAL_HVAC', 'ELECTRICAL', 'PLUMBING', 'FIRE_PROTECTION', 'WRITTEN_SPECS'];
        saveAnswerAndEvaluate($ass1Id, (int)$q['id'], ['answer_value' => $val], 2);
    }
    evaluatePhaseGate($ass1Id, 1, 'Project Assessor');
    evaluatePhaseGate($ass1Id, 2, 'Project Assessor');
    evaluatePhaseGate($ass1Id, 3, 'Project Assessor');
    evaluatePhaseGate($ass1Id, 4, 'Project Assessor');
    echo "-> Seeded Lead 1: Metropolitan Commercial Plaza (PROCEED TO PROPOSAL)\n";

    // 2. Lead on HOLD: "Hudson Yards Loft Group" (Phase 1 Fail Hold with open items)
    $stmt2 = $pdo->prepare("
        INSERT INTO assessments 
        (assessment_number, client_name, client_email, client_phone, project_address, project_type, estimated_budget, assessor_id, current_phase, status, hold_deadline_date)
        VALUES (?, 'Hudson Yards Loft Group LLC', 'info@hudsonloft.com', '(646) 555-3211', '520 West 30th Street, New York, NY 10001', 'Residential Gut Rehab', 1200000.00, 2, 1, 'HOLD', ?)
    ");
    $assNum2 = 'UFC-' . date('Ymd') . '-B202';
    $deadline = date('Y-m-d', strtotime('+30 days'));
    $stmt2->execute([$assNum2, $deadline]);
    $ass2Id = (int)$pdo->lastInsertId();

    $q1_1 = getQuestionByNumber('1.1');
    $q1_2 = getQuestionByNumber('1.2');
    $q1_3 = getQuestionByNumber('1.3');
    $q1_4 = getQuestionByNumber('1.4');
    $q1_6 = getQuestionByNumber('1.6');
    $q1_7 = getQuestionByNumber('1.7');
    $q1_8 = getQuestionByNumber('1.8');
    $q1_9 = getQuestionByNumber('1.9');
    $q1_10 = getQuestionByNumber('1.10');

    saveAnswerAndEvaluate($ass2Id, (int)$q1_1['id'], ['answer_value' => 'FILED_PLAN_EXAM']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_2['id'], [
        'answer_value' => 'NO',
        'explain_reason' => 'Design professional seal is missing on mechanical and electrical sheets. Re-seal scheduled for next week.',
        'explain_responsible_party' => 'ARCHITECT',
        'explain_target_cure_date' => date('Y-m-d', strtotime('+14 days'))
    ], 2);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_3['id'], [
        'answer_value' => ['ARCHITECTURAL', 'STRUCTURAL'],
        'explain_reason' => 'MEP drawings still being finalized by consulting engineer.',
        'explain_responsible_party' => 'ENGINEER',
        'explain_target_cure_date' => date('Y-m-d', strtotime('+20 days'))
    ], 2);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_4['id'], ['answer_value' => 'YES']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_6['id'], ['answer_value' => 'YES']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_7['id'], ['answer_value' => 'YES']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_8['id'], [
        'answer_value' => 'NO',
        'explain_reason' => 'Client currently reviewing proposals from licensed expeditors.',
        'explain_responsible_party' => 'CLIENT',
        'explain_target_cure_date' => date('Y-m-d', strtotime('+7 days'))
    ], 2);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_9['id'], ['answer_value' => 'YES']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_10['id'], ['answer_value' => 6]);
    evaluatePhaseGate($ass2Id, 1, 'Project Assessor');
    echo "-> Seeded Lead 2: Hudson Yards Loft Group (HOLD — PHASE 1 REQUIREMENTS OUTSTANDING)\n";

    // 3. Declined Lead: "Tribeca Heritage Hospitality" (Phase 3 Active Stop-Work Order -> NOT A FIT)
    $stmt3 = $pdo->prepare("
        INSERT INTO assessments 
        (assessment_number, client_name, client_email, client_phone, project_address, project_type, estimated_budget, assessor_id, current_phase, status, decline_reason, decline_notes, completed_at)
        VALUES (?, 'Tribeca Heritage Hospitality LLC', 'legal@tribecahospitality.com', '(212) 555-7489', '180 Franklin Street, New York, NY 10013', 'Retail / Restaurant Fit-out', 950000.00, 2, 3, 'NOT_A_FIT', 'STOP_TRIGGER', 'Active DOB Stop-Work Order on property due to prior unpermitted excavation.', NOW())
    ");
    $assNum3 = 'UFC-' . date('Ymd') . '-C303';
    $stmt3->execute([$assNum3]);
    $ass3Id = (int)$pdo->lastInsertId();

    $q3_3 = getQuestionByNumber('3.3');
    saveAnswerAndEvaluate($ass3Id, (int)$q3_3['id'], [
        'answer_value' => 'NO',
        'explain_reason' => 'Active DOB stop-work order issued against prior tenant unpermitted structural demolition.',
        'explain_responsible_party' => 'CLIENT',
        'explain_target_cure_date' => date('Y-m-d', strtotime('+30 days'))
    ], 2);
    evaluatePhaseGate($ass3Id, 3, 'Project Assessor');
    echo "-> Seeded Lead 3: Tribeca Heritage Hospitality (NOT A FIT — STOP TRIGGER)\n";

    echo "Demo Leads successfully seeded!\n";

} catch (Exception $e) {
    echo "Error seeding demo data: " . $e->getMessage() . "\n";
}
