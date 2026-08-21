# Skill: PHP Architecture & Application Engineering

## Purpose

You are responsible for maintaining a clean, predictable, maintainable plain-PHP architecture for the Design and Implementation of a Secure Examination Question Management, Approval and Controlled Printing System for LASU FCIT.

The application uses:

- PHP 8+
- MySQL
- PDO
- Apache/XAMPP during development
- Tailwind CSS
- Vanilla JavaScript where necessary
- No Laravel
- No Symfony
- No unnecessary framework introduction

The architecture must remain framework-free unless the project specification explicitly changes this requirement.

---

## Core Principles

### 1. Separation of concerns

Separate:

- presentation/UI
- request handling
- business logic
- database access
- authentication/authorization
- document/file operations
- workflow operations

Do not put large amounts of business logic directly into HTML templates.

Do not put HTML rendering inside database/helper functions unless explicitly necessary.

---

## Canonical Architecture

Use a predictable structure similar to:

    /config
    /database
    /helpers
    /middleware
    /services
    /includes
    /dashboard
        /admin
        /lecturer
        /moderator
        /hod
        /exam_officer
    /storage
    /tests
    /docs

Do not introduce unnecessary directories.

---

## Bootstrap

The project must have ONE canonical application bootstrap.

Example:

    bootstrap.php

Bootstrap is responsible for loading required infrastructure in a deterministic order.

The application must not have multiple competing bootstrap chains.

Avoid situations where:

    header.php
    auth.php
    auth_helper.php
    functions.php

all independently load each other in unpredictable ways.

---

## Include Rules

Use:

    require_once

for required project dependencies.

Use absolute filesystem paths based on:

    __DIR__

Avoid fragile relative paths such as:

    require_once '../../something.php';

when a canonical bootstrap/service can be used instead.

---

## Function Uniqueness

Every globally defined function must have exactly one authoritative implementation.

Before creating a function:

1. Search the entire project.
2. Determine whether it already exists.
3. Reuse or refactor the existing implementation.
4. Never create duplicate implementations with different behavior.

This is especially important for:

- currentUser()
- requireRole()
- loginUser()
- logoutUser()
- csrf functions
- audit functions
- workflow functions
- document functions

---

## Business Logic

Business rules must live in reusable services/helpers.

Do not duplicate state transition logic across:

    lecturer pages
    moderator pages
    HOD pages
    Exam Officer pages

For example:

The dashboard should call:

    submitPaper()

rather than directly performing a custom SQL UPDATE.

---

## Error Handling

Production-facing pages must not expose:

- SQL errors
- filesystem paths
- stack traces
- credentials
- session IDs
- encryption keys

Development logs may contain detailed diagnostics.

User-facing errors must be generic and useful.

---

## Transactions

Use database transactions whenever an operation modifies multiple related records and those records must remain consistent.

Example:

Paper approval may involve:

1. status update
2. approval record
3. audit log
4. notification
5. lockdown record

Where atomicity is required, use:

    BEGIN
    COMMIT
    ROLLBACK

Do not leave half-completed workflow transitions.

---

## Configuration

Secrets and environment-specific settings must not be hardcoded into application logic.

Centralize:

- database credentials
- application URL
- storage paths
- LibreOffice path
- security constants
- session configuration

---

## Core Infrastructure Protection

Treat these files as critical infrastructure:

- bootstrap.php
- config/database.php
- config/constants.php
- includes/auth.php
- helpers/security_helper.php
- helpers/workflow_helper.php
- helpers/document_helper.php

Do not rewrite these files wholesale unless absolutely necessary.

Before modifying one:

1. inspect callers
2. inspect dependencies
3. inspect database dependencies
4. understand existing behavior
5. make the smallest coherent change
6. run regression tests

---

## No Fake Completion

A feature is not complete because code exists.

A feature is complete only when:

- PHP lint passes
- relevant tests pass
- database behavior is correct
- filesystem behavior is correct where applicable
- browser behavior is verified where applicable
- security behavior is verified

If verification is unavailable, report:

    IMPLEMENTED — UNVERIFIED

Never report:

    TEST PASSED

without actually running the test.

---

## Forbidden Practices

Do not:

- create duplicate functions
- create duplicate authentication systems
- create duplicate schemas without justification
- silently change database structure
- hardcode role IDs
- hardcode moderator assignments
- hardcode examination dates
- bypass workflow helpers
- bypass authorization
- suppress errors merely to hide failures
- mark unfinished pages as complete
- create "coming soon" pages for required functionality

---

## Completion Standard

Before declaring an architecture phase complete:

1. Search for duplicate functions.
2. Run PHP lint.
3. Verify bootstrap loading.
4. Verify database connection.
5. Verify authentication.
6. Verify role middleware.
7. Verify error handling.
8. Run relevant regression tests.
9. Verify critical pages in browser.