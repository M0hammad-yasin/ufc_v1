<?php

/**
 * components/report-body.php
 * ─────────────────────────────────────────────────────────────────────────
 * Reusable final-assessment report body renderer for UFC v1.
 *
 * Public functions:
 *   getAssessmentReportData(int $assessmentId): array
 *     – Fetches phase results, weighted score, verdict, and flags from the DB.
 *
 *   renderReportBody(int $assessmentId, bool $showActions = true): string
 *     – Returns a complete HTML string for the report. Does NOT echo.
 *
 * Renders:
 *   ① Summary card   — weighted score, verdict badge, risk label, quick stats
 *   ② Phase breakdown — scored bar rows with weight / threshold pills
 *   ③ Financial risk  — four key margin-health indicators
 *   ④ Flags           — RED/AMBER items grouped: Critical · Resolvable · Internal
 *   ⑤ Actions         — links to admin details view + print report page
 *
 * Designed for:
 *   - assessment/phase-result.php  (Phase 4 PASS)
 *   - admin/assessment.php          (summary panel / modal)
 *   - assessment/report.php         (standalone printable page — new file)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/evaluation.php';
require_once __DIR__ . '/../includes/questions.php';

// ── Brand colour palette (matches ufc_v1 dark theme) ─────────────────────
if (!defined('RPT_GREEN'))  define('RPT_GREEN',  '#34d399');
if (!defined('RPT_GOLD'))   define('RPT_GOLD',   '#c9a84c');
if (!defined('RPT_RED'))    define('RPT_RED',    '#f87171');
if (!defined('RPT_BLUE'))   define('RPT_BLUE',   '#60a5fa');

// ── Flag severity keys ────────────────────────────────────────────────────
if (!defined('RPT_CRITICAL'))   define('RPT_CRITICAL',   'CRITICAL');
if (!defined('RPT_RESOLVABLE')) define('RPT_RESOLVABLE', 'RESOLVABLE');
if (!defined('RPT_INTERNAL'))   define('RPT_INTERNAL',   'INTERNAL');

/**
 * Fetch & normalise all data required for the report from the live database.
 *
 * @return array{
 *   assessment:   array,
 *   phaseResults: array[],
 *   flags:        array[],
 *   overallScore: float,
 *   verdict:      string
 * }|array{}   Empty on not-found.
 */
function getAssessmentReportData(int $assessmentId): array
{
    $pdo        = getDbConnection();
    $assessment = getAssessmentDetails($assessmentId);

    if (!$assessment) {
        return [];
    }

    // ── Phase results (with weight/threshold from phases table) ───────────
    $prStmt = $pdo->prepare("
        SELECT pr.*,
               p.phase_number, p.title, p.weight, p.threshold
        FROM   `phase_results` pr
        JOIN   `phases`        p  ON pr.phase_id = p.id
        WHERE  pr.assessment_id = ?
        ORDER  BY p.phase_number ASC
    ");
    $prStmt->execute([$assessmentId]);
    $rawResults = $prStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── All answers for this assessment ───────────────────────────────────
    $ansStmt = $pdo->prepare("
        SELECT aa.*,
               q.question_number, q.question_text, q.phase_id, q.trigger_type, q.owner
        FROM   `assessment_answers` aa
        JOIN   `questions`           q  ON aa.question_id = q.id
        WHERE  aa.assessment_id = ?
        ORDER  BY q.phase_id ASC, q.order_index ASC
    ");
    $ansStmt->execute([$assessmentId]);
    $allAnswers = $ansStmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Explain blocks keyed by question_number ───────────────────────────
    $expStmt = $pdo->prepare("
        SELECT eb.*,
               q.question_number, q.question_text, q.phase_id, q.trigger_type
        FROM   `explain_blocks` eb
        JOIN   `questions`       q  ON eb.question_id = q.id
        WHERE  eb.assessment_id = ?
    ");
    $expStmt->execute([$assessmentId]);
    $explains = [];
    while ($row = $expStmt->fetch(PDO::FETCH_ASSOC)) {
        $explains[$row['question_number']] = $row;
    }

    // ── Build normalised phaseResults + weighted score ────────────────────
    $phaseResults  = [];
    $weightedSum   = 0.0;
    $totalWeight   = 0.0;
    $phaseRejected = [];

    foreach ($rawResults as $r) {
        $threshold = (float)($r['threshold'] ?? 65.00);
        if ($threshold <= 10.0 && $threshold > 0) {
            $threshold = $threshold * 10.0;
        }
        $weight    = (float)($r['weight'] ?? 0.20);
        $passed    = ($r['status'] === 'PASS');
        $scorePct  = (float)$r['score_percent'];

        $phaseResults[] = [
            'id'             => (int)$r['phase_id'],
            'number'         => (int)$r['phase_number'],
            'title'          => $r['title'],
            'weight'         => $weight,
            'threshold'      => $threshold,
            'score_earned'   => (float)$r['score_earned'],
            'score_possible' => (float)$r['score_possible'],
            'score_percent'  => $scorePct,
            'avg_score_on_10' => round($scorePct / 10, 2),
            'status'         => $r['status'],
            'passed'         => $passed,
            'red_count'      => (int)$r['red_count'],
            'amber_count'    => (int)$r['amber_count'],
            'stop_count'     => (int)$r['stop_count'],
            'escalate_count' => (int)$r['escalate_count'],
        ];

        if ($weight > 0) {
            $weightedSum += $scorePct * $weight;
            $totalWeight += $weight;
        }

        if (!$passed && !in_array($r['status'], ['IN_PROGRESS', 'ESCALATED'])) {
            $phaseRejected[] = (int)$r['phase_id'];
        }
    }

    $overallScore = ($totalWeight > 0) ? round($weightedSum / $totalWeight, 1) : 0.0;

    // Verdict from assessment status or phase results
    $assessmentStatus = $assessment['status'] ?? 'IN_PROGRESS';
    $verdict = match ($assessmentStatus) {
        'PROCEED_TO_PROPOSAL' => 'GO',
        'NOT_A_FIT'           => 'NO-GO',
        'HOLD'                => 'HOLD',
        'ESCALATED'           => 'ESCALATED',
        default               => (!empty($phaseRejected) ? 'NO-GO' : ($overallScore >= 70.0 ? 'GO' : ($overallScore >= 55.0 ? 'HOLD' : 'IN_PROGRESS'))),
    };

    // ── Build flags from RED / AMBER answers ──────────────────────────────
    $flags = [];
    foreach ($allAnswers as $ans) {
        $light   = $ans['status_light']  ?? 'GREEN';
        $trigger = $ans['trigger_fired'] ?? 'NONE';
        if ($light !== 'RED' && $light !== 'AMBER') {
            continue;
        }

        // Options marked not applicable must NOT increase or be counted as amber
        $ansVal = strtoupper(trim((string)($ans['answer_value'] ?? '')));
        $isNotApplicable = ($ansVal === 'NOT_APPLICABLE' || $ansVal === 'NA' || (isset($ans['is_applicable']) && (int)$ans['is_applicable'] === 0));
        if ($light === 'AMBER' && $isNotApplicable) {
            continue;
        }

        $qNum    = $ans['question_number'];
        $explain = $explains[$qNum] ?? null;

        $severity = match (true) {
            in_array($trigger, ['STOP', 'ESCALATE']) => RPT_CRITICAL,
            $trigger === 'HOLD' || $light === 'RED'  => RPT_RESOLVABLE,
            default                                   => RPT_INTERNAL,
        };

        $flags[] = [
            'phaseId'          => (int)$ans['phase_id'],
            'questionNumber'   => $qNum,
            'questionText'     => $ans['question_text'],
            'trigger'          => $trigger,
            'statusLight'      => $light,
            'severity'         => $severity,
            'owner'            => $ans['owner']                 ?? null,
            'reason'           => $explain['reason']            ?? null,
            'responsibleParty' => $explain['responsible_party'] ?? null,
            'targetCureDate'   => $explain['target_cure_date']  ?? null,
        ];
    }

    return [
        'assessment'   => $assessment,
        'phaseResults' => $phaseResults,
        'flags'        => $flags,
        'overallScore' => $overallScore,
        'verdict'      => $verdict,
    ];
}

/**
 * Render the full report body HTML and return it as a string.
 *
 * @param int  $assessmentId   Which assessment to render
 * @param bool $showActions    Whether to show "Details / Print" buttons at bottom
 */
function renderReportBody(int $assessmentId, bool $showActions = true): string
{
    $data = getAssessmentReportData($assessmentId);

    if (empty($data)) {
        return '<div class="p-6 text-red-400 text-sm">Assessment not found.</div>';
    }

    $assessment   = $data['assessment'];
    $phaseResults = $data['phaseResults'];
    $flags        = $data['flags'];
    $overallScore = $data['overallScore'];
    $verdict      = $data['verdict'];

    if (empty($phaseResults)) {
        return '<div class="p-6 text-slate-400 text-sm italic">No phase evaluations recorded yet. Complete at least one phase gate to see the report.</div>';
    }

    // ── Verdict styling ───────────────────────────────────────────────────
    $vColor = match ($verdict) {
        'GO'        => RPT_GREEN,
        'NO-GO'     => RPT_RED,
        'ESCALATED' => '#c084fc',
        default     => RPT_GOLD,
    };
    $verdictLabel = match ($verdict) {
        'GO'          => 'GO — PROCEED TO PROPOSAL',
        'NO-GO'       => 'NO-GO — HARD REJECT',
        'ESCALATED'   => 'HOLD — ESCALATED TO CEO',
        'IN_PROGRESS' => 'IN PROGRESS',
        default       => 'HOLD — REQUIREMENTS OUTSTANDING',
    };
    $verdictBgClass = match ($verdict) {
        'GO'          => 'bg-emerald-950/60 border-emerald-500 text-emerald-200',
        'NO-GO'       => 'bg-red-950/60 border-red-500 text-red-200',
        'ESCALATED'   => 'bg-purple-950/60 border-purple-500 text-purple-200',
        'IN_PROGRESS' => 'bg-blue-950/60 border-blue-500 text-blue-200',
        default       => 'bg-amber-950/60 border-[#c9a84c] text-[#c9a84c]',
    };
    $riskLabel = match (true) {
        $overallScore >= 80.0 => 'Low Risk',
        $overallScore >= 65.0 => 'Moderate Risk',
        $overallScore >= 50.0 => 'High Risk',
        default               => 'Critical Risk',
    };
    $riskColor = match (true) {
        $overallScore >= 80.0 => RPT_GREEN,
        $overallScore >= 65.0 => RPT_GOLD,
        default               => RPT_RED,
    };

    // ── ② Phase bar rows ─────────────────────────────────────────────────
    ob_start();
    foreach ($phaseResults as $r) {
        $barWidth    = min(100, round($r['score_percent']));
        $barColor    = $r['passed']
            ? ($r['score_percent'] >= 80.0 ? RPT_GREEN : RPT_GOLD)
            : RPT_RED;
        $statusText  = $r['passed']
            ? 'PASS'
            : strtoupper(str_replace('_', ' ', $r['status']));
        $statusClass = $r['passed']
            ? 'bg-emerald-950 text-emerald-300 border-emerald-500'
            : ($r['status'] === 'FAIL_STOP'
                ? 'bg-red-950 text-red-300 border-red-500'
                : 'bg-amber-950 text-amber-300 border-amber-500');
        $phaseFlags  = count(array_filter($flags, fn($f) => $f['phaseId'] === $r['id']));
?>
        <div class="space-y-2 p-4 rounded-lg border border-[#1e3e68] bg-[#060f1e]/50 hover:bg-[#060f1e] transition-colors">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-[11px] font-bold text-[#c9a84c] bg-[#1a3a5c] px-2 py-0.5 rounded border border-[#234d7a]">
                        P<?= $r['number'] ?>
                    </span>
                    <span class="text-sm font-semibold text-slate-200"><?= htmlspecialchars($r['title']) ?></span>
                    <span class="text-[10px] font-mono text-slate-400 border border-slate-700 rounded px-1.5 py-0.5">
                        Pass ≥ <?= number_format($r['threshold'], 1) ?>% &middot; Wt <?= number_format($r['weight'] * 100, 1) ?>%
                    </span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if ($phaseFlags > 0): ?>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-amber-950/80 text-amber-300 border border-amber-700">
                            <?= $phaseFlags ?> flag<?= $phaseFlags > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                    <span class="text-sm font-bold" style="color:<?= $barColor ?>">
                        <?= number_format($r['score_percent'], 1) ?>%
                    </span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?= $statusClass ?>">
                        <?= $statusText ?>
                    </span>
                </div>
            </div>
            <div class="w-full bg-[#1a3a5c] rounded-full h-1.5 overflow-hidden border border-[#1e3e68]">
                <div class="h-1.5 rounded-full transition-all duration-700"
                    style="width:<?= $barWidth ?>%;background:<?= $barColor ?>"></div>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-400">
                <span>Score: <strong class="text-slate-300"><?= number_format($r['score_earned'], 1) ?> / <?= number_format($r['score_possible'], 1) ?></strong></span>
                <span>Pct: <strong class="text-slate-300"><?= number_format($r['score_percent'], 1) ?>%</strong></span>
                <?php if ($r['red_count'] > 0): ?>
                    <span class="text-red-400">&#x1F534; <?= $r['red_count'] ?> RED</span>
                <?php endif; ?>
                <?php if ($r['amber_count'] > 0): ?>
                    <span class="text-amber-400">&#x1F7E1; <?= $r['amber_count'] ?> AMBER</span>
                <?php endif; ?>
                <?php if ($r['stop_count'] > 0): ?>
                    <span class="text-red-300 font-semibold">&#x26D4; <?= $r['stop_count'] ?> STOP</span>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }
    $phaseRowsHtml = ob_get_clean();

    // ── ③ Financial risk cards ────────────────────────────────────────────
    $finItems = [
        [
            'label' => 'Profit margin viability',
            'value' => $overallScore >= 70.0 ? 'Protected' : ($overallScore >= 55.0 ? 'Marginal'    : 'At risk'),
            'color' => $overallScore >= 70.0 ? RPT_GREEN   : ($overallScore >= 55.0 ? RPT_GOLD       : RPT_RED)
        ],
        [
            'label' => 'Overhead coverage',
            'value' => $overallScore >= 65.0 ? 'Adequate'   : 'Insufficient',
            'color' => $overallScore >= 65.0 ? RPT_GREEN    : RPT_RED
        ],
        [
            'label' => 'CEO compensation %',
            'value' => $overallScore >= 65.0 ? 'Preserved'  : 'Compressed',
            'color' => $overallScore >= 65.0 ? RPT_GREEN    : RPT_RED
        ],
        [
            'label' => 'Savings retention',
            'value' => $overallScore >= 75.0 ? 'Strong'     : ($overallScore >= 60.0 ? 'Moderate'   : 'Weak'),
            'color' => $overallScore >= 75.0 ? RPT_GREEN    : ($overallScore >= 60.0 ? RPT_GOLD       : RPT_RED)
        ],
    ];

    ob_start();
    foreach ($finItems as $fi): ?>
        <div class="flex items-center justify-between p-3 rounded-lg border border-[#1e3e68] bg-[#060f1e]/50"
            style="border-left:3px solid <?= $fi['color'] ?>">
            <span class="text-xs text-slate-300 font-medium"><?= htmlspecialchars($fi['label']) ?></span>
            <span class="text-xs font-bold" style="color:<?= $fi['color'] ?>"><?= $fi['value'] ?></span>
        </div>
    <?php endforeach;
    $finCardsHtml = ob_get_clean();

    // ── ④ Flags helper closure ────────────────────────────────────────────
    $buildFlagGroup = static function (array $list, string $label, string $color, string $bgClass): string {
        if (empty($list)) return '';
        ob_start(); ?>
        <div class="space-y-2">
            <div class="flex items-center gap-2 pb-1 border-b border-[#1e3e68]">
                <span class="w-2 h-2 rounded-full" style="background:<?= $color ?>"></span>
                <span class="text-xs font-bold uppercase tracking-wider" style="color:<?= $color ?>">
                    <?= htmlspecialchars($label) ?> (<?= count($list) ?>)
                </span>
            </div>
            <?php foreach ($list as $f): ?>
                <div class="p-3 rounded-lg border border-[#1e3e68] <?= $bgClass ?> space-y-1.5">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div class="space-y-0.5 max-w-xl">
                            <span class="text-xs font-semibold text-slate-200 leading-snug">
                                <?= htmlspecialchars($f['questionText']) ?>
                            </span>
                            <?php if (!empty($f['owner'])): ?>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    Owner: <span class="text-slate-300"><?= htmlspecialchars($f['owner']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <span class="text-[10px] font-mono font-bold text-[#c9a84c] bg-[#1a3a5c] px-1.5 py-0.5 rounded border border-[#234d7a]">
                                Q<?= htmlspecialchars($f['questionNumber']) ?>
                            </span>
                            <?php if (!empty($f['trigger']) && $f['trigger'] !== 'NONE'): ?>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border uppercase"
                                    style="background:<?= $color ?>22;color:<?= $color ?>;border-color:<?= $color ?>44">
                                    <?= htmlspecialchars($f['trigger']) ?>
                                </span>
                            <?php endif; ?>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border uppercase"
                                style="background:<?= $color ?>33;color:<?= $color ?>;border-color:<?= $color ?>66">
                                <?= htmlspecialchars($f['statusLight']) ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($f['reason'])): ?>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            <span class="font-semibold text-slate-300">Reason:</span>
                            <?= htmlspecialchars($f['reason']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($f['responsibleParty']) || !empty($f['targetCureDate'])): ?>
                        <div class="flex flex-wrap gap-3 text-[11px] text-slate-500">
                            <?php if (!empty($f['responsibleParty'])): ?>
                                <span>Party: <strong class="text-slate-400"><?= htmlspecialchars($f['responsibleParty']) ?></strong></span>
                            <?php endif; ?>
                            <?php if (!empty($f['targetCureDate'])): ?>
                                <span>Cure by: <strong class="text-slate-400"><?= htmlspecialchars(date('M j, Y', strtotime($f['targetCureDate']))) ?></strong></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php return ob_get_clean();
    };

    $redFlags   = array_values(array_filter($flags, fn($f) => ($f['statusLight'] ?? '') === 'RED'));
    $amberFlags = array_values(array_filter($flags, fn($f) => ($f['statusLight'] ?? '') === 'AMBER'));

    $flagsHtml = $buildFlagGroup($redFlags,   'RED Flags (Critical Deficiencies)', RPT_RED,  'bg-red-950/30')
        . $buildFlagGroup($amberFlags, 'AMBER Flags (Warnings & Cautions)', RPT_GOLD, 'bg-amber-950/30');

    // ── Assemble ──────────────────────────────────────────────────────────
    $projectName  = htmlspecialchars($assessment['project_name'] ?? $assessment['client_name'] ?? 'Unnamed Project');
    $assessorName = htmlspecialchars($assessment['assessor_name'] ?? '—');
    $evalDate     = !empty($assessment['updated_at'])
        ? date('M j, Y', strtotime($assessment['updated_at']))
        : date('M j, Y');
    $overallFmt = number_format($overallScore, 2);
    $totalFlags = count($flags);
    $phasePassed = count(array_filter($phaseResults, fn($r) => $r['passed']));

    ob_start(); ?>

    <!-- ══════════════════════════════════════════════════════════════════
         UFC FINAL ASSESSMENT REPORT
         ══════════════════════════════════════════════════════════════════ -->
    <div class="space-y-6">

        <!-- ① SUMMARY CARD ─────────────────────────────────────────────── -->
        <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-6 sm:p-8 shadow-xl text-center relative overflow-hidden">
            <!-- Ambient glow -->
            <div class="absolute inset-0 pointer-events-none"
                style="background:radial-gradient(ellipse at 50% 0%,<?= $vColor ?>14 0%,transparent 68%)"></div>

            <p class="relative text-[11px] uppercase tracking-[0.2em] text-slate-400 font-semibold mb-3">
                Final Assessment &middot; UFC Master Framework
            </p>
            <h2 class="relative font-serif text-2xl sm:text-3xl font-bold text-white mb-1">
                <?= $projectName ?>
            </h2>
            <p class="relative text-slate-400 text-xs mb-6">
                Assessed by <?= $assessorName ?> &middot; <?= $evalDate ?>
            </p>

            <!-- Overall score -->
            <div class="relative inline-flex flex-col items-center mb-5">
                <span class="font-serif text-6xl sm:text-7xl font-bold leading-none"
                    style="color:<?= $vColor ?>"><?= $overallFmt ?>%</span>
                <span class="text-sm text-slate-400 mt-1">weighted overall score (100% scale)</span>
                <span class="text-sm font-semibold mt-0.5"
                    style="color:<?= $riskColor ?>"><?= $riskLabel ?></span>
            </div>

            <!-- Verdict badge -->
            <div class="relative mb-4">
                <span class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg border text-sm font-bold <?= $verdictBgClass ?>">
                    <?= htmlspecialchars($verdictLabel) ?>
                </span>
            </div>

            <!-- Quick stats strip -->
            <div class="relative grid grid-cols-3 gap-3 mt-6 pt-5 border-t border-[#1e3e68]">
                <div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">Phases Scored</div>
                    <div class="text-lg font-bold text-slate-200"><?= count($phaseResults) ?> / 4</div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">Phases Passed</div>
                    <div class="text-lg font-bold text-emerald-400"><?= $phasePassed ?></div>
                </div>
                <div>
                    <div class="text-[10px] uppercase tracking-wider text-slate-400">Total Flags</div>
                    <div class="text-lg font-bold"
                        style="color:<?= $totalFlags > 0 ? RPT_GOLD : RPT_GREEN ?>"><?= $totalFlags ?></div>
                </div>
            </div>
        </div>

        <!-- ② PHASE BREAKDOWN ──────────────────────────────────────────── -->
        <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-5 sm:p-6 shadow-xl">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-[#1e3e68]">
                <div class="w-1 h-5 rounded-full bg-[#c9a84c]"></div>
                <h3 class="font-serif font-bold text-base text-slate-100 tracking-wide">Phase Breakdown</h3>
            </div>
            <div class="space-y-3">
                <?= $phaseRowsHtml ?>
            </div>
        </div>

        <!-- ③ FINANCIAL RISK PROFILE ────────────────────────────────────── -->
        <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-5 sm:p-6 shadow-xl">
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-[#1e3e68]">
                <div class="w-1 h-5 rounded-full bg-[#c9a84c]"></div>
                <h3 class="font-serif font-bold text-base text-slate-100 tracking-wide">Financial Risk Profile</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?= $finCardsHtml ?>
            </div>
        </div>

        <!-- ④ FLAGS & CONDITIONS ────────────────────────────────────────── -->
        <?php if ($flagsHtml): ?>
            <div class="bg-[#0d1f3c] border border-[#1e3e68] rounded-xl p-5 sm:p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-[#1e3e68]">
                    <div class="flex items-center gap-2">
                        <div class="w-1 h-5 rounded-full bg-[#c9a84c]"></div>
                        <h3 class="font-serif font-bold text-base text-slate-100 tracking-wide">
                            Documented Flags &amp; Conditions
                        </h3>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400"><?= $totalFlags ?> item<?= $totalFlags !== 1 ? 's' : '' ?></span>
                </div>
                <div class="space-y-5">
                    <?= $flagsHtml ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-[#0d1f3c] border border-emerald-500/40 rounded-xl p-5 shadow-xl flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-semibold text-emerald-300">
                    No flags or conditions documented — all items cleared.
                </span>
            </div>
        <?php endif; ?>

        <!-- ⑤ ACTION BUTTONS ────────────────────────────────────────────── -->
        <?php if ($showActions): ?>
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <a href="<?= BASE_URL ?>/admin/assessment.php?id=<?= $assessmentId ?>"
                    class="px-6 py-2.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-semibold rounded-md border border-[#1e3e68] transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Assessment Details
                </a>
                <a href="<?= BASE_URL ?>/api/export_pdf.php?id=<?= $assessmentId ?>"
                    class="px-6 py-2.5 bg-[#c9a84c] hover:bg-[#d6b85e] text-[#060f1e] text-sm font-bold rounded-md shadow-lg transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export as PDF
                </a>
                <a href="<?= BASE_URL ?>/assessment/report.php?id=<?= $assessmentId ?>"
                    target="_blank"
                    class="px-5 py-2.5 bg-[#1a3a5c] hover:bg-[#234d7a] text-slate-200 text-sm font-semibold rounded-md border border-[#1e3e68] transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Printable View
                </a>
            </div>
        <?php endif; ?>

    </div>

<?php return ob_get_clean();
}
