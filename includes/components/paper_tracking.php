<?php
/**
 * Shared Paper Lifecycle Tracking UI Component
 *
 * Expected variables in scope:
 * - $paper: Array of examination paper record
 * - $timeline: Array of audit_log events from getPaperTimeline()
 */

$nextAction = getPaperNextAction($paper);
$nextActionActor = $nextAction['actor'];
$nextActionText = $nextAction['action'];
$isPassiveStage = ($nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None');
?>

<!-- Next Required Action Banner -->
<div class="mb-6 p-4 sm:p-5 rounded-xl border <?= $isPassiveStage ? 'bg-slate-50 border-slate-200 text-slate-800' : 'bg-amber-50 border-amber-200 text-amber-950' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-start sm:items-center gap-3.5">
        <div class="p-2.5 bg-white rounded-xl shadow-sm border <?= $isPassiveStage ? 'border-slate-200/80' : 'border-amber-200/80' ?> shrink-0">
            <svg class="w-5 h-5 <?= $isPassiveStage ? 'text-slate-600' : 'text-amber-700' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider <?= $isPassiveStage ? 'text-slate-600' : 'text-amber-800' ?>">
                Next Action Required By: <?= htmlspecialchars($nextActionActor, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-sm font-semibold mt-0.5 leading-snug">
                <?= htmlspecialchars($nextActionText, ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>

    <?php if (!in_array($paper['status'], [STATUS_DRAFT, STATUS_PRINTED], true)): ?>
    <div class="flex items-center gap-2.5 bg-white px-3.5 py-2 rounded-xl border <?= $isPassiveStage ? 'border-slate-200' : 'border-amber-200' ?> shrink-0 self-start sm:self-auto">
        <svg class="w-4 h-4 <?= $isPassiveStage ? 'text-slate-500' : 'text-amber-700' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Time in Stage</div>
            <div class="text-xs font-bold text-slate-800 tabular-nums">
                <?= htmlspecialchars(getPaperTimeInStage($paper['updated_at']), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Canonical Lifecycle Timeline (Responsive 8-Stage Stepper) -->
<section aria-label="Examination lifecycle progress" class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-200 mb-8">
    <div class="flex items-center justify-between gap-2 mb-5">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600">Examination Lifecycle Progress</h2>
        <span class="text-xs font-semibold text-slate-500">8 Governance Stages</span>
    </div>

    <?php
    $stages = [
        ['label' => 'Draft',              'status' => STATUS_DRAFT,     'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
        ['label' => 'Under Moderation',   'status' => STATUS_SUBMITTED, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => 'Moderator Approved', 'status' => STATUS_MODERATED, 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
        ['label' => 'HOD Approval',       'status' => 'hod_approved',   'icon' => 'M5 13l4 4L19 7'],
        ['label' => 'EO Approval',        'status' => 'eo_approved',    'icon' => 'M5 13l4 4L19 7'],
        ['label' => 'Blind Locked',       'status' => STATUS_APPROVED,  'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['label' => 'Ready to Print',     'status' => STATUS_QUEUED,    'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
        ['label' => 'Printed',            'status' => STATUS_PRINTED,   'icon' => 'M5 13l4 4L19 7'],
    ];

    $currentStatus = $paper['status'];
    $hodStatus = $paper['hod_status'];
    $eoStatus = $paper['eo_status'];

    $statusScores = [
        STATUS_DRAFT     => 0,
        STATUS_SUBMITTED => 1,
        STATUS_RETURNED  => 1,
        STATUS_MODERATED => 2,
        'hod_approved'   => 3,
        'eo_approved'    => 4,
        STATUS_APPROVED  => 5,
        STATUS_LOCKED    => 5,
        STATUS_QUEUED    => 6,
        STATUS_PRINTED   => 7,
    ];

    $currentScore = $statusScores[$currentStatus] ?? 0;
    if ($currentStatus === STATUS_MODERATED) {
        $currentScore = 2;
        if ($hodStatus === 'approved' || $eoStatus === 'approved') {
            $currentScore = 3;
        }
    }
    ?>

    <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-2.5">
        <?php foreach ($stages as $idx => $stage):
            $stageScore = $statusScores[$stage['status']];
            $isCompleted = $currentScore > $stageScore;
            $isActive = $currentScore === $stageScore;

            if ($stage['status'] === 'hod_approved' && $hodStatus === 'approved') {
                $isCompleted = true;
                $isActive = false;
            }
            if ($stage['status'] === 'eo_approved' && $eoStatus === 'approved') {
                $isCompleted = true;
                $isActive = false;
            }
            if (in_array($currentStatus, [STATUS_APPROVED, STATUS_LOCKED, STATUS_QUEUED, STATUS_PRINTED], true)) {
                if ($stage['status'] === 'hod_approved' || $stage['status'] === 'eo_approved') {
                    $isCompleted = true;
                    $isActive = false;
                }
            }

            $isRevisionState = ($isActive && $currentStatus === STATUS_RETURNED && $stage['status'] === STATUS_SUBMITTED);

            if ($isCompleted) {
                $cardClasses = 'bg-emerald-50/70 border-emerald-200';
                $badgeClasses = 'bg-emerald-600 text-white';
                $labelClasses = 'text-emerald-900 font-bold';
            } elseif ($isRevisionState) {
                $cardClasses = 'bg-rose-50 border-rose-300 ring-2 ring-rose-500/15';
                $badgeClasses = 'bg-rose-600 text-white';
                $labelClasses = 'text-rose-900 font-bold';
            } elseif ($isActive) {
                $cardClasses = 'bg-blue-50/80 border-blue-300 ring-2 ring-blue-500/15';
                $badgeClasses = 'bg-lasu-navy text-lasu-gold';
                $labelClasses = 'text-lasu-navy font-bold';
            } else {
                $cardClasses = 'bg-slate-50/70 border-slate-200/80';
                $badgeClasses = 'bg-slate-200 text-slate-500';
                $labelClasses = 'text-slate-500 font-medium';
            }
        ?>
            <li class="rounded-xl border p-3 flex xl:flex-col items-center xl:items-start gap-3 xl:gap-2 transition <?= $cardClasses ?>" <?= $isActive ? 'aria-current="step"' : '' ?>>
                <div class="flex items-center justify-between xl:w-full shrink-0">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 <?= $badgeClasses ?>">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $stage['icon'] ?>"/></svg>
                    </span>
                    <span class="hidden xl:inline text-[11px] font-bold tabular-nums text-slate-400">0<?= $idx + 1 ?></span>
                </div>
                <div class="min-w-0">
                    <div class="text-xs leading-snug <?= $labelClasses ?>"><?= htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if ($isRevisionState): ?>
                        <div class="text-[11px] font-bold text-rose-700 mt-0.5">Revision Required</div>
                    <?php elseif ($isCompleted): ?>
                        <div class="text-[11px] font-medium text-emerald-700 mt-0.5">Completed</div>
                    <?php elseif ($isActive): ?>
                        <div class="text-[11px] font-semibold text-blue-700 mt-0.5">Current Stage</div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>

<!-- Chronological Event Timeline -->
<section aria-label="Chronological event timeline" class="bg-white rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-200 mb-8">
    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600 border-b border-slate-100 pb-3 mb-6 flex items-center justify-between">
        <span>Chronological Event Timeline</span>
        <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-0.5 rounded-full tabular-nums"><?= count($timeline) ?> <?= count($timeline) === 1 ? 'Event' : 'Events' ?></span>
    </h3>

    <div class="space-y-5">
        <?php if (empty($timeline)): ?>
            <div class="text-sm text-slate-500 text-center py-6">No lifecycle events recorded yet.</div>
        <?php else: ?>
            <div class="relative space-y-5">
                <!-- Vertical line -->
                <div class="absolute left-[15px] top-2 bottom-2 w-0.5 bg-slate-200" aria-hidden="true"></div>

                <?php foreach ($timeline as $event):
                    $iconColor = 'bg-slate-200 text-slate-600';
                    $icon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
                    $titleText = 'System Event';

                    $action = $event['action'] ?? '';
                    $detailsArr = [];
                    if (!empty($event['details'])) {
                        $detailsArr = json_decode($event['details'], true) ?: [];
                    }

                    switch ($action) {
                        case 'create_draft_paper':
                            $iconColor = 'bg-slate-200 text-slate-700';
                            $icon = 'M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                            $titleText = 'Initial Draft Created';
                            break;
                        case 'submit_paper':
                            $iconColor = 'bg-blue-100 text-blue-700';
                            $icon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                            $titleText = 'Paper Submitted for Moderation';
                            if (isset($detailsArr['version_id']) && $detailsArr['version_id'] > 1) {
                                $titleText = 'Revised Paper Resubmitted (V' . $detailsArr['version_id'] . ')';
                            }
                            break;
                        case 'moderator_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-700';
                            $titleText = 'Moderator Approved Paper';
                            break;
                        case 'moderator_return':
                            $iconColor = 'bg-rose-100 text-rose-700';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by Moderator for Revision';
                            break;
                        case 'hod_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-700';
                            $titleText = 'HOD Final Approval Given';
                            break;
                        case 'hod_return':
                            $iconColor = 'bg-rose-100 text-rose-700';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by HOD for Revision';
                            break;
                        case 'eo_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-700';
                            $titleText = 'Exam Officer Approval Given';
                            break;
                        case 'eo_return':
                            $iconColor = 'bg-rose-100 text-rose-700';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by Exam Officer for Revision';
                            break;
                        case 'dual_approval_satisfied':
                            $iconColor = 'bg-purple-100 text-purple-800';
                            $icon = 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z';
                            $titleText = 'Paper Fully Approved and Enters Blind Lockdown';
                            break;
                        case 'print_success':
                            $iconColor = 'bg-teal-100 text-teal-800';
                            $icon = 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z';
                            $titleText = 'Paper Printed Securely';
                            break;
                    }
                ?>
                    <div class="relative pl-10">
                        <div class="absolute left-0 top-0.5 w-8 h-8 rounded-full border-4 border-white <?= $iconColor ?> flex items-center justify-center z-10 shadow-sm" aria-hidden="true">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
                        </div>

                        <div class="bg-slate-50 rounded-xl p-3.5 border border-slate-200/80">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 sm:gap-4 mb-1.5">
                                <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-slate-500 font-mono tabular-nums whitespace-nowrap"><?= formatDate($event['created_at']) ?></div>
                            </div>

                            <div class="text-xs text-slate-600 flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-700">
                                    By <?= htmlspecialchars(trim(($event['title'] ?? '') . ' ' . ($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <span class="text-[11px] px-2 py-0.5 bg-slate-200/80 text-slate-700 rounded-md uppercase font-bold tracking-wide">
                                    <?= htmlspecialchars(str_replace('_', ' ', (string)($event['role'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <?php if (!empty($detailsArr['feedback'])): ?>
                                <div class="mt-3 text-xs bg-rose-50 text-rose-900 p-3 rounded-lg border border-rose-200 leading-relaxed">
                                    <span class="font-bold uppercase tracking-wider block mb-1 text-rose-800">Feedback Given</span>
                                    <?= nl2br(htmlspecialchars($detailsArr['feedback'], ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
