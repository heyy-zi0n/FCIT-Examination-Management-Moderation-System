# Skill: Security & Middleware Engineering

## Purpose

Implement centralized security controls for the LASU FCIT Secure Examination Content Management System.

Security must be enforced server-side.

Never rely solely on UI restrictions.

---

## Authentication

Authentication must provide:

- password hashing using password_hash()
- secure password verification
- session regeneration after login
- inactivity timeout
- secure session cookies
- logout invalidation
- brute-force protection
- login history

Never store plaintext passwords.

---

## Authorization

Every protected endpoint must verify authorization.

Authentication answers:

    Who are you?

Authorization answers:

    Are you allowed to perform this action?

Never confuse the two.

---

## Middleware

Use centralized middleware for common requirements.

Recommended middleware:

    requireAuth()
    requireRole()
    requireCsrf()
    requirePaperAccess()
    requireModeratorAssignment()
    requireHODDepartmentAccess()
    requirePrintingWindow()

Do not duplicate authorization SQL throughout dashboards.

---

## RBAC

Roles include:

- admin
- lecturer
- moderator
- hod
- exam_officer

Role names must come from the authoritative database.

Do not trust:

    $_GET['role']
    $_POST['role']
    hidden form fields

for authorization.

---

## CSRF

Every state-changing POST request must use CSRF protection.

Validate:

- token existence
- token validity
- session association

GET requests must not perform destructive state changes.

---

## IDOR Protection

Never assume that because a user knows:

    paper_id
    file_id
    version_id

they are authorized to access it.

Every object request must verify ownership/assignment/role.

---

## Moderator Authorization

A moderator must only access papers assigned to them.

Do not merely check:

    role = moderator

Also verify the assignment.

---

## HOD Authorization

HOD actions must verify that the paper belongs to the HOD's authorized department.

Never rely solely on the URL.

---

## Exam Officer Authorization

Exam Officers may access only papers eligible for the Exam Officer workflow.

Printing must require:

- authenticated user
- exam_officer role
- Approved state
- correct locked version
- valid examination schedule
- valid printing date
- no duplicate print condition

---

## Printing Security

Printing is allowed ONLY:

    current calendar date == examination date - 1 calendar day

Timezone:

    Africa/Lagos

Do not use:

    48 hours
    24 hours
    diffHours()
    2880 minutes

The same rule must be enforced:

- UI
- POST handler
- backend service

The UI is not security.

---

## Audit Logging

Record security-sensitive events such as:

- login
- failed login
- account lockout
- logout
- paper submission
- moderator approval
- moderator return
- HOD approval
- HOD return
- lockdown
- emergency unlock
- print queue
- print
- unauthorized access attempts

Audit logging must not expose passwords, tokens or encryption keys.

---

## Security Failures

Fail closed.

If authorization cannot be established:

    DENY

Do not assume permission.

If the printing schedule cannot be loaded:

    DENY PRINTING

If a file cannot be verified:

    DENY ACCESS

---

## Completion Standard

Security work is complete only after testing:

- authorized access
- unauthorized access
- wrong role
- wrong department
- wrong moderator assignment
- invalid CSRF
- invalid object ID
- expired session
- locked account
- early printing
- late printing