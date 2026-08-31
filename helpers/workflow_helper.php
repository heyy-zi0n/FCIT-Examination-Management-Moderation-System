<?php
/**
 * Workflow Helper
 * Canonical functional wrappers calling WorkflowService
 */

function submitPaper(int $paperId, int $lecturerId): bool {
    return WorkflowService::submitPaper($paperId, $lecturerId);
}

function moderatorApprovePaper(int $paperId, int $moderatorId, ?string $comments = null): bool {
    return WorkflowService::moderatorApprovePaper($paperId, $moderatorId, $comments);
}

function moderatorReturnPaper(int $paperId, int $moderatorId, string $feedback): bool {
    return WorkflowService::moderatorReturnPaper($paperId, $moderatorId, $feedback);
}

function hodApprovePaper(int $paperId, int $hodId, ?string $comments = null): bool {
    return WorkflowService::hodApprovePaper($paperId, $hodId, $comments);
}

function hodReturnPaper(int $paperId, int $hodId, string $feedback): bool {
    return WorkflowService::hodReturnPaper($paperId, $hodId, $feedback);
}

function eoApprovePaper(int $paperId, int $eoId, ?string $comments = null): bool {
    return WorkflowService::eoApprovePaper($paperId, $eoId, $comments);
}

function eoReturnPaper(int $paperId, int $eoId, string $feedback): bool {
    return WorkflowService::eoReturnPaper($paperId, $eoId, $feedback);
}

function evaluateFinalApproval(int $paperId, int $actorId): bool {
    return WorkflowService::evaluateDualApproval($paperId, $actorId);
}

function emergencyUnlockPaper(int $paperId, int $hodId, string $reason): bool {
    return WorkflowService::emergencyUnlock($paperId, $hodId, $reason);
}
  

/**
 * Fetch timeline of events for an examination paper
 */
function getPaperTimeline(int $paperId, PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT al.action, al.details, al.created_at, u.first_name, u.last_name, u.role, u.title
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.entity_type = 'examination_paper' AND al.entity_id = :paper_id
        ORDER BY al.created_at DESC
    ");
    $stmt->execute(['paper_id' => $paperId]);
    return $stmt->fetchAll();
}

/**
 * Get elapsed time since the paper entered its current stage
 */
function getPaperTimeInStage(string $updatedAtStr): string {
    if (empty($updatedAtStr)) return 'Unknown';
    
    try {
        $updatedAt = new DateTime($updatedAtStr, new DateTimeZone(APP_TIMEZONE));
        $now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
        $interval = $now->diff($updatedAt);
        
        $timeStr = '';
        if ($interval->d > 0) {
            $timeStr .= $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
        } elseif ($interval->h > 0) {
            $timeStr .= $interval->h . ' hr' . ($interval->h > 1 ? 's' : '');
        } elseif ($interval->i > 0) {
            $timeStr .= $interval->i . ' min' . ($interval->i > 1 ? 's' : '');
        }
        
        return trim($timeStr);
    } catch (Exception $e) {
        return 'Unknown';
    }
}

/**
 * Determine the next required action and actor for a paper
 */
function getPaperNextAction(array $paper): array {
    $actor = '';
    $action = '';
    
    switch ($paper['status']) {
        case STATUS_DRAFT:
            $action = "Upload question document and submit for moderation.";
            $actor = "Lecturer";
            break;
        case STATUS_SUBMITTED:
            $action = "Review and moderate the submitted paper.";
            $actor = "Moderator";
            break;
        case STATUS_RETURNED:
            $action = "Address feedback and upload a new revision.";
            $actor = "Lecturer";
            break;
        case STATUS_MODERATED:
            if ($paper['hod_status'] === 'pending' && $paper['eo_status'] === 'pending') {
                $action = "Dual Approval pending. Both HOD and Exam Officer must review.";
                $actor = "HOD & Exam Officer";
            } else if ($paper['hod_status'] === 'pending') {
                $action = "Review and sign-off on the moderated paper.";
                $actor = "Head of Department (HOD)";
            } else if ($paper['eo_status'] === 'pending') {
                $action = "Review and sign-off on the moderated paper.";
                $actor = "Exam Officer";
            }
            break;
        case STATUS_APPROVED:
        case STATUS_LOCKED:
            $action = "Paper is securely locked. Awaiting print eligibility window.";
            $actor = "System / Exam Officer";
            break;
        case STATUS_QUEUED:
            $action = "Execute controlled printing of the paper.";
            $actor = "Exam Officer";
            break;
        case STATUS_PRINTED:
            $action = "Lifecycle complete. Paper has been printed successfully.";
            $actor = "None";
            break;
    }
    
    return ['actor' => $actor, 'action' => $action];
}
