# LASU FCIT CMS — Agent Rules

## Rule 1 — Source of Truth

The project specification and authoritative database schema are the sources of truth.

Never invent requirements, database columns, workflow states, or permissions.

## Rule 2 — Inspect Before Modify

Before modifying existing code:

- inspect the file
- search for callers
- inspect dependencies
- inspect relevant database tables
- inspect related tests

## Rule 3 — No Duplicate Infrastructure

Never create duplicate:

- authentication systems
- currentUser() functions
- CSRF implementations
- database connection implementations
- workflow functions
- document storage systems

## Rule 4 — Server-Side Security

Never rely on hidden fields, disabled buttons, JavaScript, or UI visibility for authorization.

All security rules must be enforced server-side.

## Rule 5 — Database First

Never write SQL based on assumptions.

Inspect the actual schema first.

## Rule 6 — No Placeholder Completion

Required functionality must never be replaced with:

"Coming Soon"

or

"Module Under Development".

## Rule 7 — Test While Building

Do not build the entire system and test at the end.

Build one phase → test → fix → verify → continue.

## Rule 8 — Browser Verification

Whenever browser tooling is available, use it.

Interact with the actual application.

Do not claim browser verification without actually performing it.

## Rule 9 — Never Fabricate Results

Never claim:

- tests passed
- browser verified
- database verified
- screenshot captured
- feature completed

unless it actually happened.

## Rule 10 — Preserve Working Code

Do not rewrite working infrastructure unnecessarily.

Prefer small, controlled changes.

## Rule 11 — Fail Closed

If authorization, schedule, document integrity, or workflow state cannot be verified:

DENY THE OPERATION.

## Rule 12 — Completion

A feature is COMPLETE only when:

- implementation exists
- syntax passes
- backend behavior passes
- database behavior passes
- filesystem behavior passes where applicable
- browser behavior passes where applicable
- security behavior passes
- regression tests pass