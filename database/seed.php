<?php

/**
 * United Five Construction, Inc.
 * Database Seeder — All 4 Phases, 37 Questions, and Tiers (authoritative specification)
 */

require_once __DIR__ . '/../config/database.php';

echo "Initializing database schema and seed data...\n";

try {
    $pdo = getDbConnection();

    // 1. Run Schema
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "-> Schema initialized successfully.\n";

    // 2. Seed Tiers
    $stmtTier = $pdo->prepare(
        "INSERT INTO `tiers` (`name`, `description`, `color`, `sort_order`) VALUES (?, ?, ?, ?)"
    );
    $tiers = [
        ['Tier 1 ',   'Large, complex, multi-phase projects requiring full UFC engagement.',    '#c9a84c', 1],
        ['Tier 2 ',    'Standard commercial or residential projects with typical complexity.',   '#3b82f6', 2],
        ['Tier 3 ',      'Smaller, well-defined scopes with limited assessment overhead.',         '#4ade80', 3],
        ['Tier 4 ',    'Projects outside normal criteria — requires CEO review.',                '#f87171', 4],
    ];
    foreach ($tiers as $t) {
        $stmtTier->execute($t);
    }
    echo "-> Seeded 4 Deal Tiers.\n";

    // 3. Seed Users
    $stmtUser = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password_hash`, `role`) VALUES (?, ?, ?, ?)");
    $users = [
        ['Project Assessor', 'apm1@unitedfiveconstruct.com', 'APM1@ufc+-', 'assessor'],
        ['Project Manager', 'apm2@unitedfiveconstruct.com', 'APM2@ufc+-', 'pm'],
        ['Ali Farhan Bhatti', 'alib@unitedfiveconstruct.com', 'AliBhatti77+-', 'ceo']
    ];
    foreach ($users as $u) {
        $stmtUser->execute($u);
    }
    echo "-> Seeded default users.\n";

    // 4. Seed Phases
    $stmtPhase = $pdo->prepare("INSERT INTO `phases` (`phase_number`, `title`, `the_question`, `unlocks_when`, `question_count`, `weight`, `threshold`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $phases = [
        [
            1,
            'DOCUMENT READINESS',
            'Can this project be priced at all?',
            'Immediately. Phase 1 runs on every lead.',
            10,
            0.200,
            65.00,
            'Runs on every lead, at every contract value, before any other work is done. This is the cheapest test available: a DOB job number can be checked in two minutes. If Phase 1 fails, nothing else is asked and no estimating time is spent. Every question is client-facing.'
        ],
        [
            2,
            'FINANCIAL CAPACITY AND CLIENT COMMITMENT',
            'Can this client pay, and are they serious?',
            'Phase 1 returns PASS. Until then this phase is locked and no question in it is displayed.',
            9,
            0.150,
            65.00,
            'Unlocks only when Phase 1 passes. This is the phase that separates a buyer from a shopper. A client who will not document funding, and will not sign a preconstruction services agreement, is not going to award a contract. Every question is client-facing except where marked.'
        ],
        [
            3,
            'PROPERTY AND LEGAL STANDING',
            'Can this property lawfully be worked on, and will UFC get paid?',
            'Phase 2 returns PASS. Until then this phase is locked and no question in it is displayed.',
            8,
            0.220,
            65.00,
            'Unlocks only when Phase 2 passes. Most of this phase United Five Construction can research directly without asking the client. Run the searches first, then ask the client only about what the searches surface.'
        ],
        [
            4,
            'UFC DUE DILIGENCE AND FIT',
            'Do we want this project, and can we deliver it?',
            'Phase 3 returns PASS. Until then this phase is locked and no question in it is displayed.',
            10,
            0.180,
            65.00,
            'Unlocks only when Phase 3 passes. The first three phases test the client. This one tests United Five Construction. A client can pass every gate and still be the wrong project for this company at this moment. Marked questions are internal only and never print.'
        ]
    ];

    $phaseIdMap = [];
    foreach ($phases as $p) {
        $stmtPhase->execute($p);
        $phaseIdMap[$p[0]] = $pdo->lastInsertId();
    }
    echo "-> Seeded 4 Phases.\n";

    // 5. Seed Questions & Options
    $stmtQ = $pdo->prepare("INSERT INTO `questions` (`phase_id`, `question_number`, `question_text`, `response_type`, `owner`, `visibility`, `trigger_type`, `is_reversed`, `client_message`, `evidence_required`, `display_condition`, `order_index`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtOpt = $pdo->prepare("INSERT INTO `question_options` (`question_id`, `option_key`, `option_label`, `branch_action`, `target_question_number`, `score_weight`, `order_index`) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $questions = [
        // PHASE 1
        [
            'phase' => 1,
            'num' => '1.1',
            'text' => 'What is the current status of the construction plans?',
            'type' => 'SINGLE_SELECT',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The plans for this project are not yet approved. United Five Construction issues a budget range from an unapproved set, never a firm price. Plan approval routinely changes scope, and a price given before approval is a price that will move.',
            'evidence' => 'DOB NOW job number and the current filing status printout, dated within the last seven days.',
            'condition' => null,
            'order' => 1,
            'options' => [
                ['NOT_STARTED', 'NOT YET STARTED — NO DESIGN PROFESSIONAL ENGAGED', 'HOLD', '1.8', 0],
                ['IN_DESIGN', 'IN DESIGN — NOT YET FILED WITH DOB', 'HOLD', '1.2', 0],
                ['FILED_PLAN_EXAM', 'FILED — UNDER PLAN EXAMINATION', 'HOLD', '1.2', 0],
                ['OBJECTIONS_OPEN', 'OBJECTIONS ISSUED — CURRENTLY OPEN', 'HOLD', '1.2', 0],
                ['OBJECTIONS_AWAITING', 'OBJECTIONS ANSWERED — AWAITING RE-REVIEW', 'HOLD', '1.2', 0],
                ['APPROVED_NO_PERMIT', 'APPROVED — PERMIT NOT YET PULLED', 'CONTINUE', '1.2', 0],
                ['APPROVED_PERMIT_ISSUED', 'APPROVED — PERMIT ISSUED', 'CONTINUE', '1.2', 0]
            ]
        ],
        [
            'phase' => 1,
            'num' => '1.2',
            'text' => 'Are the drawings signed and sealed by the design professional of record?',
            'type' => 'YES_NO',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The drawing set provided is not signed and sealed. An unsealed set cannot be filed, permitted or built from, and United Five Construction will not price against it.',
            'evidence' => 'Drawing set bearing the professional seal, signature and date.',
            'condition' => '{"question_number":"1.1","operator":"!=","value":"NOT_STARTED"}',
            'order' => 2,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.3',
            'text' => 'Which drawing disciplines are complete and have been issued to United Five Construction?',
            'type' => 'MULTI_SELECT',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The drawing set is incomplete. The disciplines listed have not been issued. United Five Construction cannot price a trade it has no drawings for, and any number produced without them carries allowances that will move.',
            'evidence' => 'The issued drawing index, compared item by item against what United Five Construction actually received.',
            'condition' => '{"question_number":"1.1","operator":"!=","value":"NOT_STARTED"}',
            'order' => 3,
            'options' => [
                ['ARCHITECTURAL', 'Architectural', 'CHECKLIST', null, 0.285],
                ['STRUCTURAL', 'Structural', 'CHECKLIST', null, 0.285],
                ['MECHANICAL_HVAC', 'Mechanical / HVAC', 'CHECKLIST', null, 0.285],
                ['ELECTRICAL', 'Electrical', 'CHECKLIST', null, 0.285],
                ['PLUMBING', 'Plumbing', 'CHECKLIST', null, 0.285],
                ['FIRE_PROTECTION', 'Fire protection / sprinkler', 'CHECKLIST', null, 0.285],
                ['WRITTEN_SPECS', 'Written specifications', 'CHECKLIST', null, 0.285]
            ]
        ],
        [
            'phase' => 1,
            'num' => '1.4',
            'text' => 'Is the filing free of open plan examiner objections?',
            'type' => 'YES_NO',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'There are open objections on the Department of Buildings filing. Please provide the objection sheet. Objections routinely carry scope and cost consequences and must be read before a price can be built.',
            'evidence' => 'DOB NOW objection sheet showing every open item.',
            'condition' => '{"question_number":"1.1","operator":"IN","values":["FILED_PLAN_EXAM","OBJECTIONS_OPEN","OBJECTIONS_AWAITING","APPROVED_NO_PERMIT","APPROVED_PERMIT_ISSUED"]}',
            'order' => 4,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.5',
            'text' => 'Have all open objections been answered by the design professional and accepted by the plan examiner?',
            'type' => 'YES_NO',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The open objections have not been resolved. Until your design professional responds and the examiner accepts, the permitted scope is not fixed and cannot be priced firmly.',
            'evidence' => 'The architect\'s written objection responses as submitted, and the DOB NOW status showing acceptance or a scheduled re-review.',
            'condition' => '{"question_number":"1.4","operator":"==","value":"NO"}',
            'order' => 5,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.6',
            'text' => 'Is the drawing set issued to United Five Construction for pricing the same revision as the set on file with DOB?',
            'type' => 'YES_NO',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The set provided for pricing does not match the set on file. Please issue the current filed revision so the estimate reflects the permitted scope rather than a superseded one.',
            'evidence' => 'Revision number and date on the pricing set, matched against the filed set.',
            'condition' => '{"question_number":"1.1","operator":"IN","values":["FILED_PLAN_EXAM","OBJECTIONS_OPEN","OBJECTIONS_AWAITING","APPROVED_NO_PERMIT","APPROVED_PERMIT_ISSUED"]}',
            'order' => 6,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.7',
            'text' => 'Has the architect of record confirmed they will remain the architect of record through project completion?',
            'type' => 'YES_NO',
            'owner' => 'DESIGN',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Your architect of record has not confirmed engagement through completion. If the architect of record is replaced, the filing must be amended and the approval timeline restarts. This must be settled before pricing.',
            'evidence' => 'Written confirmation from the design professional of continued engagement through construction administration and sign-off.',
            'condition' => '{"question_number":"1.1","operator":"!=","value":"NOT_STARTED"}',
            'order' => 7,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.8',
            'text' => 'Has the client retained a licensed expeditor for this project?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE', // Amber if NO, requires explain block
            'msg' => 'No expeditor is retained on this project. Permit obtainability should be confirmed by a licensed expeditor before either party commits to a schedule or a price.',
            'evidence' => 'Expeditor engagement confirmation and a written filing pathway and permit obtainability assessment.',
            'condition' => null,
            'order' => 8,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.9',
            'text' => 'Is the scope defined by drawings and written specifications rather than by verbal description?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The scope has been described verbally only. United Five Construction does not issue pricing against an undocumented scope. It cannot be estimated accurately, contracted cleanly, or defended if disputed.',
            'evidence' => 'Complete drawing set plus a written specification or scope narrative.',
            'condition' => null,
            'order' => 9,
            'options' => []
        ],
        [
            'phase' => 1,
            'num' => '1.10',
            'text' => 'Rate the degree to which these plans meet United Five Construction\'s criteria to start construction.',
            'type' => 'SCALE_1_10',
            'owner' => 'UFC',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Based on the current state of the documents, this project does not yet meet the criteria United Five Construction requires to begin construction. The specific items are listed in this letter. Resolve them and we will complete the review and move to pricing.',
            'evidence' => 'Anchors: 1–2 concept or schematic only. 3–4 design development, not filed. 5–6 filed and under examination, coordination incomplete. 7 objections answered, awaiting acceptance. 8 approved, permit not pulled, disciplines complete and coordinated. 9–10 approved, permit issued, all disciplines coordinated, zero open items.',
            'condition' => null,
            'order' => 10,
            'options' => []
        ],

        // PHASE 2
        [
            'phase' => 2,
            'num' => '2.1',
            'text' => 'Is the person or entity requesting this estimate the owner of record for the property, or holding written notarized authorization from the owner?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'United Five Construction contracts only with the owner of record or a party the owner has authorized in writing. Please provide the corporate documents establishing ownership, or a notarized authorization from the owner.',
            'evidence' => 'Recorded deed from ACRIS, corporate formation documents matching the deed holder, or a notarized owner authorization letter.',
            'condition' => null,
            'order' => 11,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.2',
            'text' => 'How is this project being funded?',
            'type' => 'SINGLE_SELECT',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The funding source for this project has not been confirmed. Please advise in writing whether the work is self-funded or financed. The payment structure, draw schedule and documentation requirements differ materially between the two.',
            'evidence' => 'Written statement of funding source signed by the client.',
            'condition' => null,
            'order' => 12,
            'options' => [
                ['SELF_FUNDED', 'SELF-FUNDED (CASH)', 'CONTINUE', '2.3', 0],
                ['LENDER_FINANCED', 'BANK OR PRIVATE LENDER FINANCED', 'SKIP', '2.4', 0],
                ['COMBINATION', 'COMBINATION OF CASH AND FINANCING', 'CONTINUE', '2.3', 0],
                ['NOT_DETERMINED', 'NOT YET DETERMINED', 'HOLD', '2.6', 0]
            ]
        ],
        [
            'phase' => 2,
            'num' => '2.3',
            'text' => 'Has the client provided documented proof of funds covering the anticipated construction budget plus a fifteen percent contingency?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Proof of funds has not been provided. United Five Construction does not commit crew, material or a firm price to a project without verified funding for the full construction budget plus contingency.',
            'evidence' => 'Bank or brokerage statement, or a letter from a financial institution, dated within the last thirty days.',
            'condition' => '{"question_number":"2.2","operator":"IN","values":["SELF_FUNDED","COMBINATION"]}',
            'order' => 13,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.4',
            'text' => 'Has the lender issued a written loan commitment letter or a fully executed construction loan agreement?',
            'type' => 'YES_NO',
            'owner' => 'LENDER',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'No lender commitment has been provided. Pricing and scheduling remain on hold until the construction financing is committed in writing.',
            'evidence' => 'Lender commitment letter, or the executed construction loan agreement.',
            'condition' => '{"question_number":"2.2","operator":"IN","values":["LENDER_FINANCED","COMBINATION"]}',
            'order' => 14,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.5',
            'text' => 'Have the lender\'s conditions precedent and draw requirements been provided in writing?',
            'type' => 'YES_NO',
            'owner' => 'LENDER',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE', // Amber if NO
            'msg' => 'The lender\'s conditions and draw requirements have not been provided. These govern how and when United Five Construction is paid and must be reviewed before a payment schedule is agreed.',
            'evidence' => 'Lender closing checklist, conditions precedent schedule, and the draw package requirements including requisition form, lien waiver and inspection requirements.',
            'condition' => '{"question_number":"2.2","operator":"IN","values":["LENDER_FINANCED","COMBINATION"],"and":{"question_number":"2.4","operator":"!=","value":"NO"}}',
            'order' => 15,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.6',
            'text' => 'Did the client discuss a budget range with the architect before the drawings were developed?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE', // Amber if NO
            'msg' => 'The drawings appear to have been developed without a budget conversation between you and your architect. When that conversation does not happen first, the set frequently describes a project well above the budget the owner has in mind. We raise it now so it is discovered before an estimate rather than after.',
            'evidence' => 'Client confirmation, and any written program or budget parameter issued to the design professional.',
            'condition' => null,
            'order' => 16,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.7',
            'text' => 'Rate how closely the client\'s stated budget matches the scope shown on the drawings.',
            'type' => 'SCALE_1_10',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The budget as stated does not align with the scope shown on the drawings. Either the scope or the budget will need to move before a meaningful estimate can be produced.',
            'evidence' => 'Client\'s stated budget compared against UFC\'s conceptual order-of-magnitude estimate.',
            'condition' => null,
            'order' => 17,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.8',
            'text' => 'Is the client\'s decision-maker identified in writing and authorized to sign a contract and approve change orders without third-party consent?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE', // Amber if NO
            'msg' => 'Please designate in writing a single representative authorized to sign and to approve change orders. Undefined decision authority is the leading cause of field delay and cost growth.',
            'evidence' => 'Written designation of the client\'s authorized representative, with contact information and stated authority limits.',
            'condition' => null,
            'order' => 18,
            'options' => []
        ],
        [
            'phase' => 2,
            'num' => '2.9',
            'text' => 'Has the client agreed in writing to execute a Preconstruction Services Agreement covering the estimate, the site walkthrough and the Pre-Construction Assessment Report?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'United Five Construction performs preconstruction as paid professional work. The estimate, the site walkthrough and the Pre-Construction Assessment Report each consume licensed time and produce documents you own and can use with any contractor. We ask every client to execute a Preconstruction Services Agreement before that work begins. The fee is credited in full against the contract sum if you award the project to us.',
            'evidence' => 'Executed Preconstruction Services Agreement, or written client acceptance of the terms.',
            'condition' => null,
            'order' => 19,
            'options' => []
        ],

        // PHASE 3
        [
            'phase' => 3,
            'num' => '3.1',
            'text' => 'Is the property free of open Department of Buildings violations?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Open Department of Buildings violations exist at this property. These must be cured or resolved before a permit will issue for new work.',
            'evidence' => 'DOB BIS or DOB NOW violation search for the block and lot, dated within the last thirty days.',
            'condition' => null,
            'order' => 20,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.2',
            'text' => 'Is the property free of open Environmental Control Board violations?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Open Environmental Control Board violations exist at this property. Outstanding penalties and unresolved violations obstruct permit issuance.',
            'evidence' => 'ECB violation search for the block and lot, dated within the last thirty days.',
            'condition' => null,
            'order' => 21,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.3',
            'text' => 'Is the property free of any active stop-work order?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'STOP',
            'msg' => 'There is an active stop-work order on this property. No work may lawfully proceed and no permit will issue until it is rescinded. We are glad to revisit this project once it is lifted.',
            'evidence' => 'DOB stop-work order search for the block and lot.',
            'condition' => null,
            'order' => 22,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.4',
            'text' => 'Is the property free of recorded mechanic\'s liens?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'One or more mechanic\'s liens are recorded against this property. Please advise the status of each. Existing liens affect both title and the security of payment for new work.',
            'evidence' => 'ACRIS lien search for the block and lot.',
            'condition' => null,
            'order' => 23,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.5',
            'text' => 'Is the property free of recorded judgments, tax liens and pending foreclosure?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Recorded judgments, tax liens or foreclosure activity are associated with this property. Please advise the status of each before pricing proceeds.',
            'evidence' => 'ACRIS search and a county clerk judgment search against the owner and any affiliated entity.',
            'condition' => null,
            'order' => 24,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.6',
            'text' => 'Is the client, and any affiliated entity, free of contractor litigation within the past 2 years?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'INTERNAL_ONLY',
            'trigger' => 'ESCALATE',
            'msg' => null,
            'evidence' => 'New York State eCourts and federal PACER search on the client and any affiliated entity.',
            'condition' => null,
            'order' => 25,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.7',
            'text' => 'Where prior construction work has been performed at this property, was it permitted, inspected and signed off with no open filings?',
            'type' => 'YES_NO_NA',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Prior work at this property was performed without permits or remains open on the Department of Buildings record. Unresolved prior filings frequently block a new permit and must be cleared first. If a previous contractor left this project, please disclose the circumstances, any outstanding balance, and any open dispute.',
            'evidence' => 'DOB permit history for the block and lot, showing sign-off or a letter of completion. Where a prior contractor left mid-project, the prior contract and any termination correspondence.',
            'condition' => null,
            'order' => 26,
            'options' => []
        ],
        [
            'phase' => 3,
            'num' => '3.8',
            'text' => 'Where required by building age or by the scope of work, are asbestos, lead and mold surveys complete?',
            'type' => 'YES_NO_NA',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'STOP',
            'msg' => 'The required hazardous material surveys have not been completed. Disturbing suspect material without a survey exposes both parties to regulatory penalty and health liability. United Five Construction will not proceed without them, and we are glad to resume once they are in hand.',
            'evidence' => 'ACP-5 or ACP-7 form, EPA lead assessment, and mold assessment report as applicable.',
            'condition' => null,
            'order' => 27,
            'options' => []
        ],

        // PHASE 4
        [
            'phase' => 4,
            'num' => '4.1',
            'text' => 'Has a United Five Construction site walkthrough been completed and documented?',
            'type' => 'YES_NO',
            'owner' => 'UFC',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'A site walkthrough has not yet been completed. United Five Construction does not issue pricing on a property it has not physically inspected.',
            'evidence' => 'Dated walkthrough report with photographs, filed to the project record.',
            'condition' => null,
            'order' => 28,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.2',
            'text' => 'Has a project conference been held between United Five Construction and the client to review scope, schedule and budget expectations?',
            'type' => 'YES_NO',
            'owner' => 'UFC',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'United Five Construction has not yet held a project conference with you. We do not issue pricing on a project we have not discussed directly with the owner.',
            'evidence' => 'Calendar record of the conference and the written recap issued to the client afterward.',
            'condition' => null,
            'order' => 29,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.3',
            'text' => 'Has a Pre-Construction Assessment Report been completed, or authorized in writing by the client?',
            'type' => 'YES_NO',
            'owner' => 'UFC',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'The Pre-Construction Assessment Report has not been completed or authorized. The PCAR documents the existing condition of the structure before any work begins. It protects you from being charged for pre-existing damage and protects United Five Construction from being held responsible for it.',
            'evidence' => 'Executed PCAR, or the client\'s written authorization to proceed with it under the Preconstruction Services Agreement.',
            'condition' => null,
            'order' => 30,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.4',
            'text' => 'Is the site accessible for material staging, equipment and crew, with access hours agreed in writing where the property is occupied?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE', // Amber if NO
            'msg' => 'Site logistics and access hours have not been agreed. Restricted staging or access materially affects both labor cost and duration and must be resolved before pricing is finalized.',
            'evidence' => 'Site logistics plan showing staging area and access route, plus a written access schedule signed by the owner where the premises are occupied.',
            'condition' => null,
            'order' => 31,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.5',
            'text' => 'Is Builder\'s Risk insurance confirmed, with the carrying party identified and United Five Construction named as an additional insured?',
            'type' => 'YES_NO',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'Builder\'s Risk coverage has not been confirmed. On a renovation of this type the policy is normally carried by the owner. Please confirm who carries it, at what limit, and have your carrier name United Five Construction as an additional insured before mobilization.',
            'evidence' => 'Builder\'s Risk policy or binder showing the named insured, the limit, the deductible, and UFC named as additional insured.',
            'condition' => null,
            'order' => 32,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.6',
            'text' => 'Does this project require a performance or payment bond?',
            'type' => 'YES_NO',
            'owner' => 'UFC',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'NONE',
            'is_reversed' => 1,
            'msg' => 'This project carries a bonding requirement. United Five Construction will confirm surety capacity in writing before issuing a price, and the bond premium will be carried as a separate line item.',
            'evidence' => 'Lender requirement, owner requirement, or contract provision requiring a bond, stated in writing.',
            'condition' => null,
            'order' => 33,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.7',
            'text' => 'Rate the client\'s demonstrated understanding of what is actually being built from this set of drawings.',
            'type' => 'SCALE_1_10',
            'owner' => 'CLIENT',
            'vis' => 'CLIENT_FACING',
            'trigger' => 'HOLD',
            'msg' => 'From our discussions it appears the drawings and your expectations for the project are not fully aligned. We recommend a joint review with your architect before we price the work, so that what you are paying for is what you expect to receive.',
            'evidence' => 'Assessment from the project conference and the walkthrough: whether the client can describe the scope, the sequence and the finishes from the drawings without prompting.',
            'condition' => null,
            'order' => 34,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.8',
            'text' => 'Rate whether the anticipated contract value supports United Five Construction\'s target gross margin after all direct costs.',
            'type' => 'SCALE_1_10',
            'owner' => 'UFC',
            'vis' => 'INTERNAL_ONLY',
            'trigger' => 'STOP', // Stop at <= 3, Amber at 5-7, Pass gate requires >= 4
            'msg' => null,
            'evidence' => 'Conceptual estimate showing direct cost, burden and the resulting margin percentage.',
            'condition' => null,
            'order' => 35,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.9',
            'text' => 'Rate how realistic this project\'s schedule and manpower demand are against United Five Construction\'s current active commitments.',
            'type' => 'SCALE_1_10',
            'owner' => 'UFC',
            'vis' => 'INTERNAL_ONLY',
            'trigger' => 'HOLD', // Red at 1-4
            'msg' => null,
            'evidence' => 'Current manpower loading chart against the proposed schedule and start date.',
            'condition' => null,
            'order' => 36,
            'options' => []
        ],
        [
            'phase' => 4,
            'num' => '4.10',
            'text' => 'Rate the likelihood that this client intends to award a contract in the near term, rather than collecting comparison estimates.',
            'type' => 'SCALE_1_10',
            'owner' => 'UFC',
            'vis' => 'INTERNAL_ONLY',
            'trigger' => 'HOLD', // Red at 1-4
            'msg' => null,
            'evidence' => 'Number of contractors approached, timeline stated by the client, whether design and financing are complete, responsiveness to document requests, willingness to execute the Preconstruction Services Agreement, and whether UFC has been given a competitor figure to beat rather than drawings to price.',
            'condition' => null,
            'order' => 37,
            'options' => []
        ]
    ];

    foreach ($questions as $q) {
        $phaseId = $phaseIdMap[$q['phase']];
        $stmtQ->execute([
            $phaseId,
            $q['num'],
            $q['text'],
            $q['type'],
            $q['owner'],
            $q['vis'],
            $q['trigger'],
            $q['is_reversed'] ?? 0,
            $q['msg'],
            $q['evidence'],
            $q['condition'],
            $q['order']
        ]);
        $questionId = $pdo->lastInsertId();

        if (!empty($q['options'])) {
            $optOrder = 1;
            foreach ($q['options'] as $opt) {
                $stmtOpt->execute([
                    $questionId,
                    $opt[0],
                    $opt[1],
                    $opt[2],
                    $opt[3],
                    $opt[4],
                    $optOrder++
                ]);
            }
        }
    }

    echo "-> Successfully seeded all 37 authoritative questions and their options.\n";
    echo "\n=== ALL STATIC FRAMEWORK DATA SEEDED SUCCESSFULLY! ===\n";
} catch (Exception $e) {
    die("Database Seeding Failed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
}
