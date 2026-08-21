# Skill: Browser QA & End-to-End Verification

## Purpose

You are responsible for proving that the application works in an actual browser.

Source-code inspection alone is NOT sufficient.

---

# Mandatory Verification Rule

Never claim:

    implemented and tested

unless the application has actually been executed and tested.

If browser automation is unavailable:

    IMPLEMENTED — BROWSER UNVERIFIED

must be reported.

---

# Page Verification

For every page:

1. open page
2. verify HTTP response
3. verify rendered UI
4. inspect console errors
5. interact with controls
6. submit forms
7. verify success/failure behavior
8. inspect database where applicable
9. inspect filesystem where applicable
10. inspect PHP logs when errors occur

---

# Authentication Test

Test:

    valid login
    invalid password
    unknown account
    locked account
    logout
    expired session

---

# Lecturer E2E

Test:

    login
    dashboard
    assigned courses
    create paper
    upload question paper
    upload marking guide
    preview
    save draft
    submit
    verify Submitted state
    verify moderator routing

---

# Moderator E2E

Test:

    login
    dashboard
    assigned paper visible
    unrelated paper invisible
    open paper
    preview
    approve
    verify Moderated state

Then test Return:

    return paper
    feedback
    lecturer notification
    Returned state

---

# Revision E2E

Test:

    lecturer opens Returned paper
    creates V2
    uploads replacement
    saves
    verifies V1 unchanged
    submits V2

Check both:

    database
    filesystem

---

# HOD E2E

Test:

    login
    pending Moderated paper visible
    review
    official exam schedule visible
    approve
    verify Approved state
    verify Blind Lockdown
    verify exact version locked

Also test:

    return to lecturer

---

# Exam Officer E2E

Test:

    login
    approved locked paper visible
    printing before allowed date → blocked
    printing on allowed date → allowed
    printing after allowed date → blocked

---

# Printing Rule Test

For exam date:

    20 August 2026

Expected:

    18 Aug → BLOCK
    19 Aug → ALLOW
    20 Aug → BLOCK
    21 Aug → BLOCK

Timezone:

    Africa/Lagos

---

# Security Tests

Attempt:

- unauthorized role access
- direct URL access
- altered paper ID
- altered version ID
- invalid CSRF
- unauthorized moderator access
- wrong department HOD access
- early printing
- late printing

Expected:

    DENIED

---

# Failure Investigation

When a browser test fails:

1. capture exact error
2. inspect browser console
3. inspect Apache/PHP logs
4. inspect relevant PHP file
5. inspect database
6. inspect filesystem
7. reproduce
8. fix root cause
9. rerun test

Do not patch symptoms blindly.

---

# Completion Criteria

A phase may be marked COMPLETE only when its browser tests pass.

If testing infrastructure fails:

    UNVERIFIED

Do not fabricate screenshots, test results, or successful interactions.