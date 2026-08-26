<?php
/**
 * United Five Construction - Automated Verification & Test Suite
 * Tests all 37 questions, branch logic, and 4 phase gates
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/questions.php';
require_once __DIR__ . '/../includes/evaluation.php';
require_once __DIR__ . '/../includes/letters.php';

echo "=======================================================\n";
echo "UNITED FIVE CONSTRUCTION - TEST & VERIFICATION ENGINE\n";
echo "=======================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$name} - {$detail}\n";
        $failCount++;
    }
}

try {
    $pdo = getDbConnection();

    // 1. Verify Phase & Question Counts
    echo "1. Verifying Phase and Question Bank Database Structure...\n";
    $phases = getAllPhases();
    assertTest("4 Phases exist in database", count($phases) === 4);

    $allQuestionsStmt = $pdo->query("SELECT * FROM questions ORDER BY order_index ASC");
    $allQuestions = $allQuestionsStmt->fetchAll();
    assertTest("All 37 questions exist in database", count($allQuestions) === 37);

    // Verify per phase
    $p1 = getQuestionsForPhase(1);
    $p2 = getQuestionsForPhase(2);
    $p3 = getQuestionsForPhase(3);
    $p4 = getQuestionsForPhase(4);
    assertTest("Phase 1 has 10 questions", count($p1) === 10);
    assertTest("Phase 2 has 9 questions", count($p2) === 9);
    assertTest("Phase 3 has 8 questions", count($p3) === 8);
    assertTest("Phase 4 has 10 questions", count($p4) === 10);

    // 2. Create Test Assessment
    echo "\n2. Creating Test Assessment...\n";
    $stmtAss = $pdo->prepare("
        INSERT INTO assessments 
        (assessment_number, client_name, client_email, project_address, project_type, estimated_budget, assessor_id, current_phase, status)
        VALUES (?, 'Lexington Tower Dev LLC', 'info@lexingtontower.com', '450 Lexington Ave, New York, NY', 'Commercial Renovation', 1500000.00, 1, 1, 'IN_PROGRESS')
    ");
    $testAssNum = 'TEST-' . date('Ymd-His');
    $stmtAss->execute([$testAssNum]);
    $assessmentId = (int)$pdo->lastInsertId();
    assertTest("Test Assessment Created with ID {$assessmentId}", $assessmentId > 0);

    // 3. Test Phase 1 Question Evaluation & Branching
    echo "\n3. Testing Phase 1 Branching & Question Evaluation...\n";

    // Question 1.1: NOT_STARTED branch test
    $q1_1 = getQuestionByNumber('1.1');
    $eval1_1 = evaluateQuestionAnswer($q1_1, 'NOT_STARTED');
    assertTest("1.1 NOT_STARTED yields RED status and HOLD trigger", $eval1_1['status_light'] === 'RED' && $eval1_1['trigger_fired'] === 'HOLD');

    // Save answer 1.1 as NOT_STARTED and verify next applicable question is 1.8 (skipping 1.2 to 1.7)
    saveAnswerAndEvaluate($assessmentId, (int)$q1_1['id'], ['answer_value' => 'NOT_STARTED', 'explain_reason' => 'Architect is currently being selected by owner.']);
    $nextQ = getNextApplicableQuestion($assessmentId, 1, '1.1');
    assertTest("1.1 NOT_STARTED skips 1.2-1.7 and next question is 1.8", $nextQ !== null && $nextQ['question_number'] === '1.8', "Got: " . ($nextQ['question_number'] ?? 'null'));

    // Now change 1.1 to APPROVED_PERMIT_ISSUED
    saveAnswerAndEvaluate($assessmentId, (int)$q1_1['id'], ['answer_value' => 'APPROVED_PERMIT_ISSUED']);
    $nextQ = getNextApplicableQuestion($assessmentId, 1, '1.1');
    assertTest("1.1 APPROVED_PERMIT_ISSUED makes 1.2 next question", $nextQ !== null && $nextQ['question_number'] === '1.2');

    // 1.2: YES
    $q1_2 = getQuestionByNumber('1.2');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_2['id'], ['answer_value' => 'YES']);

    // 1.3: Multi-select checklist
    $q1_3 = getQuestionByNumber('1.3');
    $checkedAll = ['ARCHITECTURAL', 'STRUCTURAL', 'MECHANICAL_HVAC', 'ELECTRICAL', 'PLUMBING', 'FIRE_PROTECTION', 'WRITTEN_SPECS'];
    $eval1_3 = evaluateQuestionAnswer($q1_3, $checkedAll);
    assertTest("1.3 All checked yields GREEN and full 2.00 score", $eval1_3['status_light'] === 'GREEN' && $eval1_3['score'] == 2.0);
    saveAnswerAndEvaluate($assessmentId, (int)$q1_3['id'], ['answer_value' => $checkedAll]);

    // 1.4: YES -> Should skip 1.5 and go to 1.6
    $q1_4 = getQuestionByNumber('1.4');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_4['id'], ['answer_value' => 'YES']);
    $nextQ = getNextApplicableQuestion($assessmentId, 1, '1.4');
    assertTest("1.4 YES skips 1.5 and next question is 1.6", $nextQ !== null && $nextQ['question_number'] === '1.6', "Got: " . ($nextQ['question_number'] ?? 'null'));

    // 1.6, 1.7, 1.8, 1.9, 1.10
    $q1_6 = getQuestionByNumber('1.6');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_6['id'], ['answer_value' => 'YES']);
    $q1_7 = getQuestionByNumber('1.7');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_7['id'], ['answer_value' => 'YES']);
    $q1_8 = getQuestionByNumber('1.8');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_8['id'], ['answer_value' => 'YES']);
    $q1_9 = getQuestionByNumber('1.9');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_9['id'], ['answer_value' => 'YES']);
    $q1_10 = getQuestionByNumber('1.10');
    saveAnswerAndEvaluate($assessmentId, (int)$q1_10['id'], ['answer_value' => 9]);

    // Phase 1 Gate Evaluation
    $gate1 = evaluatePhaseGate($assessmentId, 1, 'Assessor Ali');
    assertTest("Phase 1 Gate PASS with 0 STOPs and high score", $gate1['status'] === 'PASS');
    assertTest("Phase 2 is now unlocked", isPhaseUnlocked($assessmentId, 2));

    // 4. Test Phase 2
    echo "\n4. Testing Phase 2 Funding Branching & Gate...\n";
    $q2_1 = getQuestionByNumber('2.1');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_1['id'], ['answer_value' => 'YES']);

    // 2.2 SELF_FUNDED -> 2.3 applicable, 2.4 & 2.5 skipped
    $q2_2 = getQuestionByNumber('2.2');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_2['id'], ['answer_value' => 'SELF_FUNDED']);
    $nextQ = getNextApplicableQuestion($assessmentId, 2, '2.2');
    assertTest("2.2 SELF_FUNDED next question is 2.3", $nextQ !== null && $nextQ['question_number'] === '2.3');

    $q2_3 = getQuestionByNumber('2.3');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_3['id'], ['answer_value' => 'YES']);
    $nextQ = getNextApplicableQuestion($assessmentId, 2, '2.3');
    assertTest("2.3 next question after SELF_FUNDED skips 2.4 & 2.5 and is 2.6", $nextQ !== null && $nextQ['question_number'] === '2.6', "Got: " . ($nextQ['question_number'] ?? 'null'));

    $q2_6 = getQuestionByNumber('2.6');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_6['id'], ['answer_value' => 'YES']);
    $q2_7 = getQuestionByNumber('2.7');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_7['id'], ['answer_value' => 9]);
    $q2_8 = getQuestionByNumber('2.8');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_8['id'], ['answer_value' => 'YES']);
    $q2_9 = getQuestionByNumber('2.9');
    saveAnswerAndEvaluate($assessmentId, (int)$q2_9['id'], ['answer_value' => 'YES']);

    $gate2 = evaluatePhaseGate($assessmentId, 2, 'Assessor Ali');
    assertTest("Phase 2 Gate PASS with Q2.9 YES", $gate2['status'] === 'PASS');
    assertTest("Phase 3 is now unlocked", isPhaseUnlocked($assessmentId, 3));

    // 5. Test Phase 3 STOP Trigger & Gate
    echo "\n5. Testing Phase 3 STOP Trigger & Legal Questions...\n";
    $q3_1 = getQuestionByNumber('3.1');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_1['id'], ['answer_value' => 'YES']);
    $q3_2 = getQuestionByNumber('3.2');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_2['id'], ['answer_value' => 'YES']);
    $q3_3 = getQuestionByNumber('3.3'); // Active stop work order
    $eval3_3 = evaluateQuestionAnswer($q3_3, 'NO');
    assertTest("3.3 NO triggers STOP", $eval3_3['status_light'] === 'RED' && $eval3_3['trigger_fired'] === 'STOP');

    // Answer 3.3 YES, 3.4 YES, 3.5 YES, 3.6 YES, 3.7 YES, 3.8 YES
    saveAnswerAndEvaluate($assessmentId, (int)$q3_3['id'], ['answer_value' => 'YES']);
    $q3_4 = getQuestionByNumber('3.4');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_4['id'], ['answer_value' => 'YES']);
    $q3_5 = getQuestionByNumber('3.5');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_5['id'], ['answer_value' => 'YES']);
    $q3_6 = getQuestionByNumber('3.6');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_6['id'], ['answer_value' => 'YES']);
    $q3_7 = getQuestionByNumber('3.7');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_7['id'], ['answer_value' => 'NOT_APPLICABLE', 'na_justification' => 'New core building; no prior construction in 10 years.']);
    $q3_8 = getQuestionByNumber('3.8');
    saveAnswerAndEvaluate($assessmentId, (int)$q3_8['id'], ['answer_value' => 'YES']);

    $gate3 = evaluatePhaseGate($assessmentId, 3, 'Assessor Ali');
    assertTest("Phase 3 Gate PASS", $gate3['status'] === 'PASS');
    assertTest("Phase 4 is now unlocked", isPhaseUnlocked($assessmentId, 4));

    // 6. Test Phase 4 Due Diligence & PROCEED TO PROPOSAL
    echo "\n6. Testing Phase 4 Due Diligence & Overall Verdict...\n";
    $p4Questions = getQuestionsForPhase(4);
    foreach ($p4Questions as $q) {
        $qId = (int)$q['id'];
        $val = ($q['response_type'] === 'SCALE_1_10') ? 9 : 'YES';
        saveAnswerAndEvaluate($assessmentId, $qId, ['answer_value' => $val]);
    }

    $gate4 = evaluatePhaseGate($assessmentId, 4, 'Assessor Ali');
    assertTest("Phase 4 Gate PASS with high score and >= 75%", $gate4['status'] === 'PASS' && $gate4['score_percent'] >= 75.0);

    $finalAss = getAssessmentDetails($assessmentId);
    assertTest("Assessment overall status is PROCEED_TO_PROPOSAL", $finalAss['status'] === 'PROCEED_TO_PROPOSAL');

    // 7. Test Requirements Letter Generation
    echo "\n7. Testing Client Requirements Letter & Decline Letter Generator...\n";
    // Create a deficient assessment to test Requirements Letter
    $stmtAss2 = $pdo->prepare("
        INSERT INTO assessments 
        (assessment_number, client_name, client_email, project_address, assessor_id, current_phase, status, hold_deadline_date)
        VALUES (?, 'Broadway Commercial Realty', 'dev@broadway.com', '780 Broadway, New York, NY', 1, 1, 'HOLD', ?)
    ");
    $holdDeadline = date('Y-m-d', strtotime('+30 days'));
    $testAssNum2 = 'TEST-REQ-' . date('Ymd-His');
    $stmtAss2->execute([$testAssNum2, $holdDeadline]);
    $ass2Id = (int)$pdo->lastInsertId();

    // 1.2 NO (RED), 1.8 NO (AMBER)
    saveAnswerAndEvaluate($ass2Id, (int)$q1_1['id'], ['answer_value' => 'APPROVED_PERMIT_ISSUED']);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_2['id'], [
        'answer_value' => 'NO',
        'explain_reason' => 'Design professional seal pending final structural verification.',
        'explain_responsible_party' => 'ARCHITECT'
    ]);
    saveAnswerAndEvaluate($ass2Id, (int)$q1_8['id'], [
        'answer_value' => 'NO',
        'explain_reason' => 'Client currently reviewing expeditor proposals.',
        'explain_responsible_party' => 'CLIENT'
    ]);

    $reqData = generateRequirementsLetterData($ass2Id, 1);
    assertTest("Requirements Letter contains deficient items", count($reqData['items']) >= 2);
    assertTest("Requirements Letter groups by responsible party", isset($reqData['grouped_items']['ARCHITECT']) && isset($reqData['grouped_items']['CLIENT']));
    assertTest("Requirements Letter includes formatted 30-day calendar date", !empty($reqData['deadline_formatted']));

    echo "\n=======================================================\n";
    echo "TEST SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
    echo "=======================================================\n";

    if ($failCount > 0) {
        exit(1);
    }

} catch (Exception $e) {
    echo "FATAL TEST ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
