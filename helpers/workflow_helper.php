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
  

