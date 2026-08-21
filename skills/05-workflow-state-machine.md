# Skill: Examination Workflow & State Machine

## Purpose

This skill is the authoritative definition of the examination-paper lifecycle.

Never invent alternative workflow states or approval paths unless explicitly authorized by the project specification.

---

# Official State Machine

The primary workflow is:

    Draft
      ↓
    Submitted
      ↓
    Moderated
      ↓
    HOD + EO Approval
      ↓
    Approved
      ↓
    Blind Lockdown
      ↓
    Print Queue
      ↓
    Printed

Approval at the final approval stage requires BOTH:

    HOD approval = Approved
    AND
    Exam Officer approval = Approved

Neither HOD approval alone nor Exam Officer approval alone is sufficient to transition the paper to Approved or trigger Blind Lockdown.

Return path:

    Submitted
      ↓
    Returned
      ↓
    Draft
      ↓
    Submitted

HOD or Exam Officer may return a paper requiring correction according to their respective approval stage.

For a paper returned after moderator approval, the correction workflow is:

    Moderated
      ↓
    Returned
      ↓
    Draft
      ↓
    Submitted
      ↓
    Moderated
      ↓
    HOD + EO Approval

A paper must pass through the required approval stages again after substantive revision.

---

## Lecturer

Lecturer may:

- create paper
- edit Draft
- upload documents
- preview documents
- create revisions after Return
- submit Draft
- view own submissions
- view feedback and correction comments

Lecturer cannot:

- approve
- moderate
- change official examination schedule
- unlock approved/locked papers
- print papers
- bypass moderation or approval

---

## Moderator

Moderator may:

- view assigned papers
- preview assigned papers
- review submissions
- approve moderation
- return with feedback
- add review comments/annotations
- review resubmitted versions
- verify that requested corrections have been addressed

Moderator cannot:

- approve HOD stage
- approve EO stage
- modify official schedule
- print
- access unrelated moderator assignments
- bypass department or level assignment restrictions

Moderator assignment is department-specific and may cover multiple levels within the moderator's department.

---

## HOD

HOD may:

- manage official examination schedules for authorized department
- review Moderated papers
- approve the HOD approval stage
- return papers with feedback
- emergency unlock according to documented policy
- view departmental audit information

HOD approval alone does NOT constitute final approval.

HOD approval is one of the two required final approvals.

---

## Exam Officer

Exam Officer may:

- review papers awaiting EO approval
- approve the EO approval stage
- return papers with feedback
- view Approved/locked papers
- queue eligible papers
- print eligible papers
- view printing history

Exam Officer cannot:

- alter examination papers
- alter the HOD approval
- bypass moderation
- bypass HOD approval
- bypass printing date restrictions
- modify the official examination content after final approval

EO approval is one of the two required final approvals.

---

# Official Examination Schedule

The examination schedule is authoritative.

It contains at minimum:

- course
- academic session
- semester
- examination date
- examination time

Lecturers must not arbitrarily type examination dates into papers.

A paper should reference its official examination schedule.

The printing window must be calculated from the authoritative examination date.

---

# Moderator Approval

Moderator approval:

    Submitted → Moderated

Moderator approval must NOT trigger blind lockdown.

Moderator approval only confirms that the examination has passed the moderation stage.

After moderation, the paper enters the final approval stage:

    Moderated → Awaiting HOD + EO Approval

If the implementation uses a separate internal representation for pending approvals, that representation must not introduce an unauthorized public workflow state. HOD and EO approval records should be tracked independently.

---

# HOD + Exam Officer Dual Approval

The final approval mechanism uses separation of duties.

The paper is eligible for final approval only after successful moderator approval.

HOD and EO must independently review the approved moderated version.

The final approval condition is:

    HOD approved
    AND
    EO approved

Only when BOTH conditions are satisfied may the system transition:

    Moderated → Approved

Neither actor may approve on behalf of the other.

The system must record independently:

- HOD approver
- HOD approval timestamp
- HOD decision
- HOD feedback where applicable
- EO approver
- EO approval timestamp
- EO decision
- EO feedback where applicable

If either HOD or EO returns the paper:

    Return → Draft → Resubmit → Moderate → HOD + EO Approval

The exact approved version must be preserved throughout the approval process.

---

# Final Approval

Final approval:

    Moderated
       ↓
    HOD + EO approval
       ↓
    Approved

The Approved state is reached only when both required approvals have been recorded successfully.

Final approval is the ONLY normal transition that permits final Blind Lockdown.

---

# Blind Lockdown

Blind lockdown must operate on the exact fully approved paper version.

Blind Lockdown may occur only when:

    Moderator approval = complete
    AND
    HOD approval = complete
    AND
    EO approval = complete

After lockdown:

    version = immutable

The approved document must not be replaced, edited, or silently altered.

Any authorized emergency change after lockdown must use a documented unlock/revision procedure, create a new version, and repeat the required review and approval workflow.

---

# Printing

Printing is permitted only when:

    current date == exam date - 1 calendar day

Timezone:

    Africa/Lagos

Example:

Exam:

    20 August 2026

Printing:

    19 August 2026 ONLY

The printing rule must be enforced server-side.

The UI must not be the security boundary.

Before the permitted date:

    DENY

On the permitted date:

    ALLOW

After the permitted date:

    DENY

Only an authorized Exam Officer may perform the final printing operation.

---

# State Transition Authority

Each transition must have one authoritative backend function.

Examples:

    submitPaper()
    moderatorApprovePaper()
    moderatorReturnPaper()
    hodApprovePaper()
    hodReturnPaper()
    eoApprovePaper()
    eoReturnPaper()
    evaluateFinalApproval()
    activateBlindLockdown()
    queuePaperForPrinting()
    markPaperPrinted()

Dashboards must call these functions.

They must not implement their own competing state transitions.

The backend must determine whether both HOD and EO approvals exist before allowing the final Approved transition or Blind Lockdown.

---

# Invalid Transitions

The system must reject transitions such as:

    Draft → Approved
    Submitted → Approved
    Submitted → Blind Lockdown
    Moderated → Blind Lockdown without HOD + EO approval
    Moderated → Printed
    Returned → Approved
    Approved → Draft
    Printed → Draft

unless an explicitly documented administrative procedure permits them.

---

# Notifications

Important transitions should notify affected users.

Examples:

- Lecturer receives submission, return, correction, and approval notifications.
- Moderator receives new assigned submissions and resubmissions.
- HOD receives papers awaiting HOD approval.
- Exam Officer receives papers awaiting EO approval.
- Exam Officer receives final approved/locked papers.
- Relevant users receive return/correction notifications.

Notifications must respect department and assignment boundaries.

---

# Audit

Every important transition must create an audit record.

For final approval, the audit trail must independently record:

- Moderator approval
- HOD approval or return
- EO approval or return
- Final approval condition becoming satisfied
- Blind Lockdown activation
- Print authorization
- Print attempt
- Successful print

---

# Completion Standard

Workflow is complete only when automated and browser tests verify:

- every valid transition
- every invalid transition
- moderator routing
- HOD approval
- EO approval
- dual-approval enforcement
- return and resubmission
- exact-version locking
- emergency unlock/revision behavior where implemented
- printing-date restrictions
- department authorization
- role authorization
- audit logging
