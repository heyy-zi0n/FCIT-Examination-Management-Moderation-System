# Skill: Regression & System Integrity Engineering

## Purpose

Prevent changes to one subsystem from silently breaking another subsystem.

---

# Change Discipline

Before modifying a core component:

1. identify all callers
2. identify all dependencies
3. identify database dependencies
4. identify filesystem dependencies
5. identify security implications
6. identify tests
7. make the smallest coherent change

---

# Critical Files

Treat these as high-risk:

    bootstrap.php
    includes/auth.php
    helpers/security_helper.php
    helpers/workflow_helper.php
    helpers/document_helper.php
    config/database.php
    config/constants.php

Do not casually rewrite them.

---

# Regression Tests

After modifying a core helper, test all features depending on it.

Example:

If workflow_helper.php changes:

    lecturer submission
    moderator approval
    HOD approval
    lockdown
    notifications
    audit logging
    printing

must be considered for regression.

---

# No Silent Failure

Do not use:

    @function()
    @include()
    @unlink()

to hide meaningful failures unless the failure is intentionally handled.

Errors should be logged and handled deliberately.

---

# Data Integrity

Never allow a UI fix to corrupt database state.

Never allow a database fix to silently invalidate files.

For document changes, verify both:

    database state
    filesystem state

---

# Version Integrity

After versioning changes verify:

    V1 exists
    V2 exists
    V1 remains unchanged
    V2 has independent files
    hashes remain correct

---

# Security Regression

After authentication/security changes retest:

- login
- logout
- sessions
- RBAC
- CSRF
- object authorization

---

# Browser Regression

After changes to shared UI/includes:

test representative pages from:

    Lecturer
    Moderator
    HOD
    Exam Officer

---

# Database Regression

After migrations:

- verify existing records remain valid
- verify foreign keys
- verify application queries
- verify seed compatibility

---

# Release Gate

A release cannot be declared stable if:

- PHP syntax errors exist
- fatal runtime errors exist
- required pages are placeholders
- database queries reference nonexistent columns
- critical workflow transitions fail
- document integrity fails
- printing rules can be bypassed
- authorization can be bypassed

---

# Final Verification

Before release:

    PHP lint
    database schema verification
    automated tests
    security tests
    document tests
    versioning tests
    browser E2E
    regression tests

Only after all applicable tests pass may the release be marked stable.