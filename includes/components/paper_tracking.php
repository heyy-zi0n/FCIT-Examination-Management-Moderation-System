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
?>

<!-- Next Required Action Banner -->
<div class="mb-6 p-4 rounded-xl border <?= $nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None' ? 'bg-slate-50 border-slate-200 text-slate-800 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300' : 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-200' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div class="flex items-start sm:items-center gap-3">
        <div class="p-2 bg-white dark:bg-slate-900 rounded-lg shadow-sm">
            <svg class="w-5 h-5 <?= $nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None' ? 'text-slate-500' : 'text-amber-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider <?= $nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None' ? 'text-slate-500' : 'text-amber-700 dark:text-amber-500' ?>">
                Next Action Required By: <?= htmlspecialchars($nextActionActor) ?>
            </div>
            <div class="text-sm font-medium mt-0.5">
                <?= htmlspecialchars($nextActionText) ?>
            </div>
        </div>
    </div>
    
    <?php if (!in_array($paper['status'], [STATUS_DRAFT, STATUS_PRINTED])): ?>
    <div class="flex items-center gap-2 bg-white/60 dark:bg-slate-900/40 px-3 py-2 rounded-lg border <?= $nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None' ? 'border-slate-200/50 dark:border-slate-700/50' : 'border-amber-200/50 dark:border-amber-800/50' ?>">
        <svg class="w-4 h-4 <?= $nextActionActor === 'System / Exam Officer' || $nextActionActor === 'None' ? 'text-slate-400' : 'text-amber-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Time in Current Stage</div>
            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                <?= getPaperTimeInStage($paper['updated_at']) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Canonical Lifecycle Timeline (Horizontal Progress) -->
<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 mb-8 overflow-hidden">
    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-6">Examination Lifecycle Progress</h2>
    
    <div class="relative flex flex-col md:flex-row justify-between w-full">
        <!-- Line background -->
        <div class="absolute top-4 left-4 md:left-0 md:right-0 bottom-4 md:bottom-auto md:top-4 w-1 md:w-full md:h-1 bg-slate-100 dark:bg-slate-800 rounded"></div>
        
        <?php
        $stages = [
            ['label' => 'Draft', 'status' => STATUS_DRAFT, 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
            ['label' => 'Under Moderation', 'status' => STATUS_SUBMITTED, 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Moderator Approved', 'status' => STATUS_MODERATED, 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'HOD Appr.', 'status' => 'hod_approved', 'icon' => 'M5 13l4 4L19 7'],
            ['label' => 'EO Appr.', 'status' => 'eo_approved', 'icon' => 'M5 13l4 4L19 7'],
            ['label' => 'Blind Locked', 'status' => STATUS_APPROVED, 'icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            ['label' => 'Ready to Print', 'status' => STATUS_QUEUED, 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
            ['label' => 'Printed', 'status' => STATUS_PRINTED, 'icon' => 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z']
        ];

        $currentStatus = $paper['status'];
        $hodStatus = $paper['hod_status'];
        $eoStatus = $paper['eo_status'];
        
        $statusScores = [
            STATUS_DRAFT => 0,
            STATUS_SUBMITTED => 1,
            STATUS_RETURNED => 1, // Logical return to moderation cycle
            STATUS_MODERATED => 2,
            'hod_approved' => 3,
            'eo_approved' => 4,
            STATUS_APPROVED => 5,
            STATUS_LOCKED => 5,
            STATUS_QUEUED => 6,
            STATUS_PRINTED => 7
        ];

        $currentScore = $statusScores[$currentStatus] ?? 0;
        if ($currentStatus === STATUS_MODERATED) {
            // Base score for moderated is 2. Approvals add 1 each.
            $currentScore = 2;
            if ($hodStatus === 'approved' || $eoStatus === 'approved') {
                $currentScore = 3;
            }
        }

        foreach ($stages as $idx => $stage):
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
            // If the paper is completely approved, HOD and EO steps must be marked completed
            if (in_array($currentStatus, [STATUS_APPROVED, STATUS_QUEUED, STATUS_PRINTED])) {
                if ($stage['status'] === 'hod_approved' || $stage['status'] === 'eo_approved') {
                    $isCompleted = true;
                    $isActive = false;
                }
            }

            $bgColor = $isCompleted ? 'bg-emerald-500' : ($isActive ? 'bg-blue-500 ring-4 ring-blue-500/20' : 'bg-slate-200 dark:bg-slate-700');
            $textColor = $isCompleted ? 'text-white' : ($isActive ? 'text-white' : 'text-slate-400');
            $labelColor = $isCompleted ? 'text-emerald-600 dark:text-emerald-400 font-bold' : ($isActive ? 'text-blue-600 dark:text-blue-400 font-bold' : 'text-slate-400 font-medium');
        ?>
            <div class="relative z-10 flex md:flex-col items-center gap-4 md:gap-2 mb-6 md:mb-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center transition-colors <?= $bgColor ?> <?= $textColor ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $stage['icon'] ?>"/></svg>
                </div>
                <div class="text-[10px] uppercase tracking-wider text-left md:text-center <?= $labelColor ?> w-24">
                    <?= $stage['label'] ?>
                    <?php if ($isActive && $currentStatus === STATUS_RETURNED && $stage['status'] === STATUS_SUBMITTED): ?>
                        <br><span class="text-rose-500 font-bold">(Revision Required)</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chronological Event Timeline -->
<div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 mb-8">
    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-100 dark:border-slate-800 pb-2 mb-6 flex items-center justify-between">
        <span>Chronological Event Timeline</span>
        <span class="text-[10px] font-normal text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full"><?= count($timeline) ?> Events</span>
    </h3>

    <div class="space-y-6">
        <?php if (empty($timeline)): ?>
            <div class="text-xs text-slate-400 text-center py-4">No events recorded.</div>
        <?php else: ?>
            <div class="relative">
                <!-- Vertical line -->
                <div class="absolute left-[15px] top-2 bottom-2 w-0.5 bg-slate-200 dark:bg-slate-700"></div>
                
                <?php foreach ($timeline as $idx => $event): 
                    $isLast = $idx === count($timeline) - 1;
                    
                    // Determine event visual type
                    $iconColor = 'bg-slate-200 text-slate-500';
                    $icon = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z';
                    $titleText = 'System Event';
                    
                    $action = $event['action'] ?? '';
                    $detailsArr = [];
                    if (!empty($event['details'])) {
                        $detailsArr = json_decode($event['details'], true) ?: [];
                    }
                    
                    // Format action map
                    switch($action) {
                        case 'create_draft_paper':
                            $iconColor = 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
                            $icon = 'M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
                            $titleText = 'Initial Draft Created';
                            break;
                        case 'submit_paper':
                            $iconColor = 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400';
                            $icon = 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                            $titleText = 'Paper Submitted for Moderation';
                            if (isset($detailsArr['version_id']) && $detailsArr['version_id'] > 1) {
                                $titleText = 'Revised Paper Resubmitted (V' . $detailsArr['version_id'] . ')';
                            }
                            break;
                        case 'moderator_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400';
                            $titleText = 'Moderator Approved Paper';
                            break;
                        case 'moderator_return':
                            $iconColor = 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by Moderator for Revision';
                            break;
                        case 'hod_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400';
                            $titleText = 'HOD Final Approval Given';
                            break;
                        case 'hod_return':
                            $iconColor = 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by HOD for Revision';
                            break;
                        case 'eo_approve':
                            $iconColor = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400';
                            $titleText = 'Exam Officer Approval Given';
                            break;
                        case 'eo_return':
                            $iconColor = 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400';
                            $icon = 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z';
                            $titleText = 'Paper Returned by Exam Officer for Revision';
                            break;
                        case 'dual_approval_satisfied':
                            $iconColor = 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400';
                            $icon = 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z';
                            $titleText = 'Paper Fully Approved and Enters Blind Lockdown';
                            break;
                        case 'print_success':
                            $iconColor = 'bg-teal-100 text-teal-600 dark:bg-teal-900/40 dark:text-teal-400';
                            $icon = 'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z';
                            $titleText = 'Paper Printed Securely';
                            break;
                    }
                ?>
                    <div class="relative pl-10">
                        <!-- Marker -->
                        <div class="absolute left-0 top-0.5 w-8 h-8 rounded-full border-4 border-white dark:border-slate-900 <?= $iconColor ?> flex items-center justify-center z-10 shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/></svg>
                        </div>
                        
                        <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 sm:gap-4 mb-1">
                                <div class="font-bold text-xs text-slate-800 dark:text-slate-200"><?= htmlspecialchars($titleText) ?></div>
                                <div class="text-[10px] text-slate-400 font-mono whitespace-nowrap"><?= formatDate($event['created_at']) ?></div>
                            </div>
                            
                            <div class="text-[11px] text-slate-500 mb-2 flex items-center gap-1.5">
                                <span class="font-medium text-slate-700 dark:text-slate-300">
                                    By <?= htmlspecialchars($event['title'] . ' ' . $event['first_name'] . ' ' . $event['last_name']) ?>
                                </span>
                                <span class="text-[9px] px-1.5 bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400 rounded uppercase font-bold tracking-wide">
                                    <?= htmlspecialchars($event['role']) ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($detailsArr['feedback'])): ?>
                                <div class="mt-2 text-[11px] bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-300 p-2 rounded border border-rose-100 dark:border-rose-900/30">
                                    <span class="font-bold uppercase tracking-wider block mb-0.5">Feedback Given:</span>
                                    <?= nl2br(htmlspecialchars($detailsArr['feedback'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
