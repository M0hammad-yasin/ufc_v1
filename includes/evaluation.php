<?php
/**
 * United Five Construction - Business Rules & Assessment Evaluation Engine
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/questions.php';
require_once __DIR__ . '/functions.php';

function evaluateQuestionAnswer(array $question, $rawAnswer, ?string $naJustification = null, array $checklistContext = []): array {
    $qType = $question['response_type'];
    $triggerType = $question['trigger_type'];
    $qNum = $question['question_number'];
    $isReversed = !empty($question['is_reversed']);

    $result = [
        'status_light' => 'GREEN',
        'trigger_fired' => 'NONE',
        'score' => 0.00,
        'points_possible' => 10.00,
        'require_explain' => false,
        'answer_value' => $rawAnswer,
        'na_justification' => $naJustification,
        'client_message' => $question['client_message'] ?? null
    ];

    switch ($qType) {
        case 'YES_NO':
            $result['points_possible'] = 10.00;
            $val = strtoupper(trim((string)$rawAnswer));
            $result['answer_value'] = $val;

            $isFavorable = $isReversed ? ($val === 'NO') : ($val === 'YES');

            if ($isFavorable) {
                $result['score'] = 10.00;
                $result['status_light'] = 'GREEN';
                $result['trigger_fired'] = 'NONE';
            } else {
                $result['score'] = 0.00;
                if ($triggerType === 'HOLD') {
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'HOLD';
                    $result['require_explain'] = true;
                } elseif ($triggerType === 'STOP') {
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'STOP';
                    $result['require_explain'] = true;
                } elseif ($triggerType === 'ESCALATE') {
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'ESCALATE';
                    $result['require_explain'] = true;
                } else {
                    // Trigger NONE (e.g. 1.8, 2.5, 2.6, 2.8, 4.4, 4.6)
                    $result['status_light'] = 'AMBER';
                    $result['trigger_fired'] = 'NONE';
                    $result['require_explain'] = true;
                }
            }
            break;

        case 'YES_NO_NA':
            $val = strtoupper(trim((string)$rawAnswer));
            $result['answer_value'] = $val;

            if ($val === 'NOT_APPLICABLE') {
                // NA removes question from both score and denominator
                $result['score'] = 0.00;
                $result['points_possible'] = 0.00;
                $result['status_light'] = 'AMBER';
                $result['trigger_fired'] = 'NONE';
                $result['require_explain'] = false; // Requires na_justification separately
            } else {
                $isFavorable = $isReversed ? ($val === 'NO') : ($val === 'YES');
                if ($isFavorable) {
                    $result['score'] = 10.00;
                    $result['points_possible'] = 10.00;
                    $result['status_light'] = 'GREEN';
                    $result['trigger_fired'] = 'NONE';
                } else {
                    // Unfavorable
                    $result['score'] = 0.00;
                    $result['points_possible'] = 10.00;
                    $result['status_light'] = 'RED';
                    $result['require_explain'] = true;
                    if ($triggerType === 'STOP') {
                        $result['trigger_fired'] = 'STOP';
                    } else {
                        $result['trigger_fired'] = 'HOLD';
                    }
                }
            }
            break;

        case 'SCALE_1_10':
            $scoreVal = (int)$rawAnswer;
            if ($scoreVal < 1) $scoreVal = 1;
            if ($scoreVal > 10) $scoreVal = 10;
            $result['answer_value'] = (string)$scoreVal;
            $result['score'] = (float)$scoreVal;
            $result['points_possible'] = 10.00;

            if ($scoreVal >= 8) {
                $result['status_light'] = 'GREEN';
                $result['trigger_fired'] = 'NONE';
            } elseif ($scoreVal >= 5) {
                $result['status_light'] = 'AMBER';
                $result['trigger_fired'] = 'NONE';
            } else {
                // 1 to 4 is RED
                $result['status_light'] = 'RED';
                $result['require_explain'] = true;

                if ($qNum === '4.8') {
                    if ($scoreVal <= 3) {
                        $result['trigger_fired'] = 'STOP';
                    } else {
                        $result['trigger_fired'] = 'HOLD';
                    }
                } else {
                    $result['trigger_fired'] = ($triggerType !== 'NONE') ? $triggerType : 'HOLD';
                }
            }
            break;

        case 'SINGLE_SELECT':
            $val = trim((string)$rawAnswer);
            $result['answer_value'] = $val;
            $result['points_possible'] = 10.00;

            // Check options for 1.1 and 2.2
            if ($qNum === '1.1') {
                if (in_array($val, ['APPROVED_NO_PERMIT', 'APPROVED_PERMIT_ISSUED'], true)) {
                    $result['score'] = 10.00;
                    $result['status_light'] = 'GREEN';
                    $result['trigger_fired'] = 'NONE';
                } else {
                    $result['score'] = 0.00;
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'HOLD';
                    if ($val === 'NOT_STARTED') {
                        $result['require_explain'] = true;
                    }
                }
            } elseif ($qNum === '2.2') {
                if ($val === 'NOT_DETERMINED') {
                    $result['score'] = 0.00;
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'HOLD';
                    $result['require_explain'] = true;
                } else {
                    $result['score'] = 10.00;
                    $result['status_light'] = 'GREEN';
                    $result['trigger_fired'] = 'NONE';
                }
            } else {
                // Generic single-select fallback
                if ($val !== '' && $val !== 'NOT_STARTED' && $val !== 'NOT_DETERMINED') {
                    $result['score'] = 10.00;
                    $result['status_light'] = 'GREEN';
                    $result['trigger_fired'] = 'NONE';
                } else {
                    $result['score'] = 0.00;
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = ($triggerType !== 'NONE') ? $triggerType : 'HOLD';
                }
            }
            break;

        case 'MULTI_SELECT':
            // Multi-select for 1.3
            // rawAnswer is array of checked keys or JSON
            $checked = is_array($rawAnswer) ? $rawAnswer : (json_decode((string)$rawAnswer, true) ?: []);
            $notRequired = $checklistContext['not_required'] ?? [];

            $options = getQuestionOptions((int)$question['id']);
            $applicableOptions = [];
            foreach ($options as $opt) {
                if (!in_array($opt['option_key'], $notRequired, true)) {
                    $applicableOptions[] = $opt['option_key'];
                }
            }

            $totalApplicable = count($applicableOptions);
            $checkedApplicable = 0;
            foreach ($applicableOptions as $optKey) {
                if (in_array($optKey, $checked, true)) {
                    $checkedApplicable++;
                }
            }

            $result['points_possible'] = 10.00;
            if ($totalApplicable === 0) {
                $result['score'] = 10.00;
                $result['status_light'] = 'GREEN';
                $result['trigger_fired'] = 'NONE';
            } else {
                $result['score'] = round(10.00 * ($checkedApplicable / $totalApplicable), 2);
                if ($checkedApplicable === $totalApplicable) {
                    $result['status_light'] = 'GREEN';
                    $result['trigger_fired'] = 'NONE';
                } else {
                    $result['status_light'] = 'RED';
                    $result['trigger_fired'] = 'HOLD';
                    $result['require_explain'] = true;
                }
            }
            $result['answer_value'] = json_encode([
                'checked' => array_values($checked),
                'not_required' => array_values($notRequired)
            ]);
            break;
    }

    return $result;
}

function saveAnswerAndEvaluate(int $assessmentId, int $questionId, array $data, ?int $userId = null): array {
    $pdo = getDbConnection();
    $question = getQuestionById($questionId);
    if (!$question) {
        throw new InvalidArgumentException("Question not found");
    }

    $rawAnswer = $data['answer_value'] ?? null;
    $naJustification = $data['na_justification'] ?? null;
    $checklistContext = [
        'not_required' => $data['not_required'] ?? []
    ];

    $eval = evaluateQuestionAnswer($question, $rawAnswer, $naJustification, $checklistContext);

    // Save answer
    $stmt = $pdo->prepare("
        INSERT INTO `assessment_answers` 
        (`assessment_id`, `question_id`, `answer_value`, `na_justification`, `score`, `points_possible`, `status_light`, `trigger_fired`, `is_applicable`, `updated_by_user_id`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE 
        `answer_value` = VALUES(`answer_value`),
        `na_justification` = VALUES(`na_justification`),
        `score` = VALUES(`score`),
        `points_possible` = VALUES(`points_possible`),
        `status_light` = VALUES(`status_light`),
        `trigger_fired` = VALUES(`trigger_fired`),
        `is_applicable` = VALUES(`is_applicable`),
        `updated_by_user_id` = VALUES(`updated_by_user_id`)
    ");
    $stmt->execute([
        $assessmentId,
        $questionId,
        $eval['answer_value'],
        $eval['na_justification'],
        $eval['score'],
        $eval['points_possible'],
        $eval['status_light'],
        $eval['trigger_fired'],
        $userId
    ]);

    // Update last_updated_by on assessment
    if ($userId) {
        $stmtUpd = $pdo->prepare("UPDATE `assessments` SET `last_updated_by_user_id` = ? WHERE `id` = ?");
        $stmtUpd->execute([$userId, $assessmentId]);
    }

    // Handle Explain Block if provided
    if (!empty($data['explain_reason'])) {
        $reason = trim((string)$data['explain_reason']);
        $respParty = trim((string)($data['explain_responsible_party'] ?? 'CLIENT'));
        $cureDate = !empty($data['explain_target_cure_date']) ? trim((string)$data['explain_target_cure_date']) : date('Y-m-d', strtotime('+30 days'));

        $stmtExp = $pdo->prepare("
            INSERT INTO `explain_blocks` (`assessment_id`, `question_id`, `reason`, `responsible_party`, `target_cure_date`)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            `reason` = VALUES(`reason`),
            `responsible_party` = VALUES(`responsible_party`),
            `target_cure_date` = VALUES(`target_cure_date`)
        ");
        $stmtExp->execute([
            $assessmentId,
            $questionId,
            $reason,
            $respParty,
            $cureDate
        ]);
        $explainBlockId = $pdo->lastInsertId() ?: getExplainBlockId($assessmentId, $questionId);

        // Create or update follow-up task
        if ($explainBlockId) {
            $stmtTask = $pdo->prepare("
                INSERT INTO `follow_up_tasks` (`assessment_id`, `explain_block_id`, `question_id`, `title`, `responsible_party`, `target_cure_date`, `status`)
                VALUES (?, ?, ?, ?, ?, ?, 'OPEN')
                ON DUPLICATE KEY UPDATE
                `title` = VALUES(`title`),
                `responsible_party` = VALUES(`responsible_party`),
                `target_cure_date` = VALUES(`target_cure_date`)
            ");
            $taskTitle = "Resolve {$question['question_number']} deficiency: " . substr($reason, 0, 60);
            $stmtTask->execute([
                $assessmentId,
                $explainBlockId,
                $questionId,
                $taskTitle,
                $respParty,
                $cureDate
            ]);
        }
    }

    logAudit($assessmentId, "ANSWER_SAVED", [
        'question_number' => $question['question_number'],
        'status_light' => $eval['status_light'],
        'trigger_fired' => $eval['trigger_fired'],
        'score' => $eval['score']
    ], $userId);

    return $eval;
}

function getExplainBlockId(int $assessmentId, int $questionId): ?int {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id FROM explain_blocks WHERE assessment_id = ? AND question_id = ? LIMIT 1");
    $stmt->execute([$assessmentId, $questionId]);
    $res = $stmt->fetch();
    return $res ? (int)$res['id'] : null;
}

function getExplainBlock(int $assessmentId, int $questionId): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM explain_blocks WHERE assessment_id = ? AND question_id = ? LIMIT 1");
    $stmt->execute([$assessmentId, $questionId]);
    $res = $stmt->fetch();
    return $res ?: null;
}

function evaluatePhaseGate(int $assessmentId, int $phaseNumber, ?string $assessorName = null): array {
    $pdo = getDbConnection();
    $phase = getPhaseByNumber($phaseNumber);
    if (!$phase) {
        throw new InvalidArgumentException("Phase not found");
    }

    $applicable = getApplicableQuestionsForPhase($assessmentId, $phaseNumber);
    $answersMap = getAssessmentAnswersMap($assessmentId);

    $totalScoreEarned = 0.00;
    $totalScorePossible = 0.00;
    $redCount = 0;
    $amberCount = 0;
    $stopCount = 0;
    $escalateCount = 0;
    $unansweredCount = 0;

    $redItems = [];
    $amberItems = [];

    foreach ($applicable as $q) {
        $qNum = $q['question_number'];
        $ans = $answersMap[$qNum] ?? null;

        if (!$ans || $ans['answer_value'] === null) {
            $unansweredCount++;
            continue;
        }

        $totalScoreEarned += (float)$ans['score'];
        $totalScorePossible += (float)$ans['points_possible'];

        if ($ans['status_light'] === 'RED') {
            $redCount++;
            $redItems[] = $q;
        } elseif ($ans['status_light'] === 'AMBER') {
            $amberCount++;
            $amberItems[] = $q;
        }

        if ($ans['trigger_fired'] === 'STOP') {
            $stopCount++;
        } elseif ($ans['trigger_fired'] === 'ESCALATE') {
            $escalateCount++;
        }
    }

    $scorePercent = ($totalScorePossible > 0) ? round(($totalScoreEarned / $totalScorePossible) * 100, 2) : 100.00;

    // Check CEO Overrides
    $hasStopOverride = checkCeoOverride($assessmentId, (int)$phase['id'], 'STOP');
    $hasEscalateOverride = checkCeoOverride($assessmentId, (int)$phase['id'], 'ESCALATE');

    if ($hasStopOverride) {
        $stopCount = 0;
    }
    if ($hasEscalateOverride) {
        $escalateCount = 0;
    }

    $gateResult = 'FAIL_HOLD';
    $verdictMessage = '';

    if ($unansweredCount > 0) {
        $gateResult = 'IN_PROGRESS';
        $verdictMessage = "Phase {$phaseNumber} is incomplete ({$unansweredCount} questions remaining).";
    } elseif ($stopCount > 0) {
        $gateResult = 'FAIL_STOP';
        $verdictMessage = "Phase {$phaseNumber} failed on a critical STOP trigger. Verdict: NOT A FIT.";
    } elseif ($escalateCount > 0) {
        $gateResult = 'ESCALATED';
        $verdictMessage = "Phase {$phaseNumber} raised an ESCALATION trigger and is routed to CEO/Counsel.";
    } else {
        // Use the DB-configured threshold for this phase (default to 65.0% if not set)
        $phaseThreshold = isset($phase['threshold']) ? (float)$phase['threshold'] : 65.00;
        // If threshold was stored in 0-10 format (e.g. 6.50), normalize it to 0-100% format (e.g. 65.00%)
        if ($phaseThreshold <= 10.0 && $phaseThreshold > 0) {
            $phaseThreshold = $phaseThreshold * 10.0;
        }

        // Evaluate specific Phase Gates using 100% based DB threshold
        switch ($phaseNumber) {
            case 1:
                $q10Score = isset($answersMap['1.10']) ? (float)$answersMap['1.10']['score'] : 0.0;
                // PASS when: zero STOP triggers, <= 2 RED items, Question 1.10 scored >= 5.0/10, and phase score% >= threshold%
                if ($redCount <= 2 && $q10Score >= 5.0 && $scorePercent >= $phaseThreshold) {
                    $gateResult = 'PASS';
                    $verdictMessage = "Phase 1 Passed. Document readiness confirmed. (Score: {$scorePercent}%, Threshold: {$phaseThreshold}%)";
                } else {
                    $gateResult = 'FAIL_HOLD';
                    $verdictMessage = "Phase 1 Failed. {$redCount} RED item(s), Score: {$scorePercent}% (requires {$phaseThreshold}%). Verdict: HOLD — PHASE 1 REQUIREMENTS OUTSTANDING.";
                }
                break;

            case 2:
                $q29Val = isset($answersMap['2.9']) ? strtoupper(trim((string)$answersMap['2.9']['answer_value'])) : '';
                // PASS when: zero STOP triggers, <= 2 RED items, Question 2.9 answered YES, and phase score% >= threshold%
                if ($redCount <= 2 && $q29Val === 'YES' && $scorePercent >= $phaseThreshold) {
                    $gateResult = 'PASS';
                    $verdictMessage = "Phase 2 Passed. Financial capacity and commitment confirmed. (Score: {$scorePercent}%, Threshold: {$phaseThreshold}%)";
                } else {
                    $gateResult = 'FAIL_HOLD';
                    $verdictMessage = "Phase 2 Failed. Score: {$scorePercent}% (requires {$phaseThreshold}%). Verdict: HOLD — PHASE 2 REQUIREMENTS OUTSTANDING.";
                }
                break;

            case 3:
                // PASS when: zero STOP triggers, zero ESCALATE triggers, <= 1 RED item, and phase score% >= threshold%
                if ($redCount <= 1 && $scorePercent >= $phaseThreshold) {
                    $gateResult = 'PASS';
                    $verdictMessage = "Phase 3 Passed. Property and legal standing confirmed. (Score: {$scorePercent}%, Threshold: {$phaseThreshold}%)";
                } else {
                    $gateResult = 'FAIL_HOLD';
                    $verdictMessage = "Phase 3 Failed. {$redCount} RED item(s), Score: {$scorePercent}% (requires {$phaseThreshold}%). Verdict: HOLD — PHASE 3 REQUIREMENTS OUTSTANDING.";
                }
                break;

            case 4:
                $q48Score = isset($answersMap['4.8']) ? (float)$answersMap['4.8']['score'] : 0.0;
                // PASS when: zero STOP triggers, Question 4.8 scored >= 4.0/10, and phase score% >= threshold%
                if ($q48Score >= 4.0 && $scorePercent >= $phaseThreshold) {
                    $gateResult = 'PASS';
                    $verdictMessage = "Phase 4 Passed. All 4 phases passed. Verdict: PROCEED TO PROPOSAL. (Score: {$scorePercent}%, Threshold: {$phaseThreshold}%)";
                } else {
                    if ($q48Score < 4.0 || $scorePercent < $phaseThreshold) {
                        $gateResult = 'FAIL_STOP'; // Capacity failure
                        $verdictMessage = "Phase 4 Failed on UFC Capacity / Target Margin criteria (Score: {$scorePercent}%, Threshold: {$phaseThreshold}%). Verdict: NOT A FIT.";
                    } else {
                        $gateResult = 'FAIL_HOLD';
                        $verdictMessage = "Phase 4 Failed on curable requirements. Verdict: HOLD — PHASE 4 REQUIREMENTS OUTSTANDING.";
                    }
                }
                break;
        }
    }

    // Save Phase Result in database
    $stmtSave = $pdo->prepare("
        INSERT INTO `phase_results` 
        (`assessment_id`, `phase_id`, `status`, `score_earned`, `score_possible`, `score_percent`, `red_count`, `amber_count`, `stop_count`, `escalate_count`, `assessor_name`, `evaluated_at`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
        `status` = VALUES(`status`),
        `score_earned` = VALUES(`score_earned`),
        `score_possible` = VALUES(`score_possible`),
        `score_percent` = VALUES(`score_percent`),
        `red_count` = VALUES(`red_count`),
        `amber_count` = VALUES(`amber_count`),
        `stop_count` = VALUES(`stop_count`),
        `escalate_count` = VALUES(`escalate_count`),
        `assessor_name` = VALUES(`assessor_name`),
        `evaluated_at` = NOW()
    ");
    $stmtSave->execute([
        $assessmentId,
        $phase['id'],
        $gateResult,
        $totalScoreEarned,
        $totalScorePossible,
        $scorePercent,
        $redCount,
        $amberCount,
        $stopCount,
        $escalateCount,
        $assessorName
    ]);

    // Update assessment status
    updateAssessmentOverallStatus($assessmentId, $phaseNumber, $gateResult);

    return [
        'phase_number'     => $phaseNumber,
        'weight'           => isset($phase['weight']) ? (float)$phase['weight'] : null,
        'threshold'        => $phaseThreshold,
        'status'           => $gateResult,
        'message'          => $verdictMessage,
        'score_earned'     => $totalScoreEarned,
        'score_possible'   => $totalScorePossible,
        'score_percent'    => $scorePercent,
        'avg_score_on_10'  => round($scorePercent / 10, 2),
        'red_count'        => $redCount,
        'amber_count'      => $amberCount,
        'stop_count'       => $stopCount,
        'escalate_count'   => $escalateCount,
        'unanswered_count' => $unansweredCount,
        'red_items'        => $redItems,
        'amber_items'      => $amberItems
    ];
}

function updateAssessmentOverallStatus(int $assessmentId, int $phaseNumber, string $phaseGateResult): void {
    $pdo = getDbConnection();
    ensureCheckReportColumns($pdo);
    $currentUserId = $_SESSION['user']['id'] ?? null;

    if ($phaseGateResult === 'PASS') {
        if ($phaseNumber === 1) {
            $stmt = $pdo->prepare("
                UPDATE `assessments` 
                SET `current_phase` = 2, 
                    `status` = 'IN_PROGRESS', 
                    `phase_1_completed_at` = COALESCE(`phase_1_completed_at`, NOW()),
                    `checkpoint_pre_assessment` = 1,
                    `checkpoint_pre_assessment_at` = COALESCE(`checkpoint_pre_assessment_at`, NOW()),
                    `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`)
                WHERE `id` = ?
            ");
            $stmt->execute([$currentUserId, $assessmentId]);
            startAssessmentTracker($assessmentId, $pdo);
        } elseif ($phaseNumber < 4) {
            $nextPhase = $phaseNumber + 1;
            $stmt = $pdo->prepare("
                UPDATE `assessments` 
                SET `current_phase` = ?, 
                    `status` = 'IN_PROGRESS',
                    `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`)
                WHERE `id` = ?
            ");
            $stmt->execute([$nextPhase, $currentUserId, $assessmentId]);
        } else {
            // Phase 4 Passed -> PROCEED TO PROPOSAL
            $stmt = $pdo->prepare("
                UPDATE `assessments` 
                SET `status` = 'PROCEED_TO_PROPOSAL', 
                    `completed_at` = NOW(),
                    `checkpoint_build_proposal` = 1,
                    `checkpoint_build_proposal_at` = COALESCE(`checkpoint_build_proposal_at`, NOW()),
                    `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`)
                WHERE `id` = ?
            ");
            $stmt->execute([$currentUserId, $assessmentId]);
        }
    } elseif ($phaseGateResult === 'FAIL_HOLD') {
        $deadline = date('Y-m-d', strtotime('+30 days'));
        $stmt = $pdo->prepare("UPDATE `assessments` SET `status` = 'HOLD', `hold_deadline_date` = ?, `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`) WHERE `id` = ?");
        $stmt->execute([$deadline, $currentUserId, $assessmentId]);
    } elseif ($phaseGateResult === 'FAIL_STOP') {
        $reason = ($phaseNumber === 4) ? 'UFC_CAPACITY' : 'STOP_TRIGGER';
        $stmt = $pdo->prepare("UPDATE `assessments` SET `status` = 'NOT_A_FIT', `decline_reason` = ?, `completed_at` = NOW(), `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`) WHERE `id` = ?");
        $stmt->execute([$reason, $currentUserId, $assessmentId]);
    } elseif ($phaseGateResult === 'ESCALATED') {
        $stmt = $pdo->prepare("UPDATE `assessments` SET `status` = 'ESCALATED', `last_updated_by_user_id` = COALESCE(?, `last_updated_by_user_id`) WHERE `id` = ?");
        $stmt->execute([$currentUserId, $assessmentId]);
    }
}

function checkCeoOverride(int $assessmentId, int $phaseId, string $triggerType): bool {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id FROM `ceo_overrides` WHERE `assessment_id` = ? AND `phase_id` = ? AND `trigger_type` = ? LIMIT 1");
    $stmt->execute([$assessmentId, $phaseId, $triggerType]);
    return (bool)$stmt->fetch();
}

function isPhaseUnlocked(int $assessmentId, int $phaseNumber): bool {
    if ($phaseNumber === 1) {
        return true;
    }
    $pdo = getDbConnection();
    $prevPhase = getPhaseByNumber($phaseNumber - 1);
    if (!$prevPhase) return false;

    $stmt = $pdo->prepare("SELECT status FROM phase_results WHERE assessment_id = ? AND phase_id = ? LIMIT 1");
    $stmt->execute([$assessmentId, $prevPhase['id']]);
    $res = $stmt->fetch();
    return $res && $res['status'] === 'PASS';
}
