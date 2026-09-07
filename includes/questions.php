<?php
/**
 * United Five Construction - Question Bank & Branching Engine
 */

require_once __DIR__ . '/../config/database.php';

function getAllPhases(): array {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM phases ORDER BY phase_number ASC");
    return $stmt->fetchAll();
}

function getPhaseByNumber(int $phaseNumber): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM phases WHERE phase_number = ? LIMIT 1");
    $stmt->execute([$phaseNumber]);
    $phase = $stmt->fetch();
    return $phase ?: null;
}

function getQuestionsForPhase(int $phaseNumber, bool $clientFacingOnly = false): array {
    $pdo = getDbConnection();
    $sql = "SELECT q.* FROM questions q 
            JOIN phases p ON q.phase_id = p.id 
            WHERE p.phase_number = ?";
    if ($clientFacingOnly) {
        $sql .= " AND q.visibility = 'CLIENT_FACING'";
    }
    $sql .= " ORDER BY q.order_index ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$phaseNumber]);
    return $stmt->fetchAll();
}

function getQuestionByNumber(string $questionNumber): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT q.*, p.phase_number, p.title AS phase_title 
                          FROM questions q 
                          JOIN phases p ON q.phase_id = p.id 
                          WHERE q.question_number = ? LIMIT 1");
    $stmt->execute([$questionNumber]);
    $q = $stmt->fetch();
    return $q ?: null;
}

function getQuestionById(int $questionId): ?array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT q.*, p.phase_number, p.title AS phase_title 
                          FROM questions q 
                          JOIN phases p ON q.phase_id = p.id 
                          WHERE q.id = ? LIMIT 1");
    $stmt->execute([$questionId]);
    $q = $stmt->fetch();
    return $q ?: null;
}

function getQuestionOptions(int $questionId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY order_index ASC");
    $stmt->execute([$questionId]);
    return $stmt->fetchAll();
}

function getAssessmentAnswersMap(int $assessmentId): array {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT q.question_number, a.* 
                          FROM assessment_answers a
                          JOIN questions q ON a.question_id = q.id
                          WHERE a.assessment_id = ?");
    $stmt->execute([$assessmentId]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[$row['question_number']] = $row;
    }
    return $map;
}

function isQuestionApplicable(int $assessmentId, array $question, ?array $answersMap = null): bool {
    if ($answersMap === null) {
        $answersMap = getAssessmentAnswersMap($assessmentId);
    }

    if (empty($question['display_condition'])) {
        return true;
    }

    $condition = json_decode($question['display_condition'], true);
    if (!$condition) {
        return true;
    }

    return evaluateConditionNode($condition, $answersMap);
}

function evaluateConditionNode(array $condition, array $answersMap): bool {
    // 1. If condition is an indexed array of conditions (all must match)
    $isList = function_exists('array_is_list') ? array_is_list($condition) : (array_keys($condition) === range(0, count($condition) - 1));
    if ($isList) {
        foreach ($condition as $subCond) {
            if (is_array($subCond) && !evaluateConditionNode($subCond, $answersMap)) {
                return false;
            }
        }
        return true;
    }

    // 2. Handle nested or top-level 'and' condition group
    if (isset($condition['and'])) {
        $andList = is_array($condition['and']) ? $condition['and'] : [];
        $isAndList = function_exists('array_is_list') ? array_is_list($andList) : (array_keys($andList) === range(0, count($andList) - 1));
        $andItems = $isAndList ? $andList : [$andList];
        foreach ($andItems as $subCond) {
            if (is_array($subCond) && !evaluateConditionNode($subCond, $answersMap)) {
                return false;
            }
        }
    }

    // 3. Handle nested or top-level 'or' condition group (at least one must match)
    if (isset($condition['or'])) {
        $orList = is_array($condition['or']) ? $condition['or'] : [];
        $isOrList = function_exists('array_is_list') ? array_is_list($orList) : (array_keys($orList) === range(0, count($orList) - 1));
        $orItems = $isOrList ? $orList : [$orList];
        $orMatched = false;
        foreach ($orItems as $subCond) {
            if (is_array($subCond) && evaluateConditionNode($subCond, $answersMap)) {
                $orMatched = true;
                break;
            }
        }
        if (!$orMatched) {
            return false;
        }
    }

    // 4. Handle direct single question condition
    if (isset($condition['question_number'])) {
        $targetQNum = $condition['question_number'];
        $targetAnswer = $answersMap[$targetQNum]['answer_value'] ?? null;
        $op = strtoupper($condition['operator'] ?? '==');

        $isMatch = false;
        switch ($op) {
            case '==':
            case '=':
                $isMatch = ($targetAnswer === $condition['value']);
                break;
            case '!=':
            case '<>':
                $isMatch = ($targetAnswer !== null && $targetAnswer !== $condition['value']);
                break;
            case 'IN':
                $values = $condition['values'] ?? [];
                $isMatch = in_array($targetAnswer, $values, true);
                break;
            case 'NOT_IN':
                $values = $condition['values'] ?? [];
                $isMatch = ($targetAnswer !== null && !in_array($targetAnswer, $values, true));
                break;
            case '>':
                $isMatch = ($targetAnswer !== null && (float)$targetAnswer > (float)$condition['value']);
                break;
            case '>=':
                $isMatch = ($targetAnswer !== null && (float)$targetAnswer >= (float)$condition['value']);
                break;
            case '<':
                $isMatch = ($targetAnswer !== null && (float)$targetAnswer < (float)$condition['value']);
                break;
            case '<=':
                $isMatch = ($targetAnswer !== null && (float)$targetAnswer <= (float)$condition['value']);
                break;
            default:
                $isMatch = true;
        }

        if (!$isMatch) {
            return false;
        }
    }

    return true;
}

function getApplicableQuestionsForPhase(int $assessmentId, int $phaseNumber, bool $clientFacingOnly = false): array {
    $questions = getQuestionsForPhase($phaseNumber, $clientFacingOnly);
    $answersMap = getAssessmentAnswersMap($assessmentId);

    $applicable = [];
    foreach ($questions as $q) {
        if (isQuestionApplicable($assessmentId, $q, $answersMap)) {
            $applicable[] = $q;
        }
    }
    return $applicable;
}

function getNextApplicableQuestion(int $assessmentId, int $phaseNumber, string $currentQuestionNumber): ?array {
    $applicable = getApplicableQuestionsForPhase($assessmentId, $phaseNumber);
    $foundCurrent = false;

    foreach ($applicable as $q) {
        if ($foundCurrent) {
            return $q;
        }
        if ($q['question_number'] === $currentQuestionNumber) {
            $foundCurrent = true;
        }
    }

    return null; // Reached end of phase
}

function getPreviousApplicableQuestion(int $assessmentId, int $phaseNumber, string $currentQuestionNumber): ?array {
    $applicable = getApplicableQuestionsForPhase($assessmentId, $phaseNumber);
    $prev = null;

    foreach ($applicable as $q) {
        if ($q['question_number'] === $currentQuestionNumber) {
            return $prev;
        }
        $prev = $q;
    }

    return null;
}

function getPhaseQuestionsProgress(int $assessmentId, int $phaseNumber, string $currentQuestionNumber): array {
    $applicable = getApplicableQuestionsForPhase($assessmentId, $phaseNumber);
    $total = count($applicable);
    $currentIdx = 1;

    foreach ($applicable as $idx => $q) {
        if ($q['question_number'] === $currentQuestionNumber) {
            $currentIdx = $idx + 1;
            break;
        }
    }

    return [
        'current_index' => $currentIdx,
        'total_applicable' => $total,
        'percent' => $total > 0 ? round(($currentIdx / $total) * 100) : 0
    ];
}
