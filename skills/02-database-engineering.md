# Skill: MySQL Database Engineering

## Purpose

You are responsible for designing and maintaining the authoritative database for the LASU FCIT Secure Examination Content Management System.

The database is the source of truth.

Application code must conform to the database schema.

Never invent database columns based on assumptions.

---

## Core Rule

Before writing SQL against an existing table:

1. Inspect the actual schema.
2. Confirm table name.
3. Confirm column names.
4. Confirm data types.
5. Confirm foreign keys.
6. Confirm ENUM values where applicable.
7. Confirm indexes.
8. Confirm nullable behavior.

Never assume a column exists because it existed in an older version.

---

## Schema Authority

Maintain one authoritative schema.

Avoid multiple competing schemas such as:

    legacy schema
    enterprise schema
    hypothetical v0.8 schema

If the schema changes:

1. create a migration
2. apply it
3. verify it
4. update documentation
5. update tests
6. update dependent application logic

---

## Migrations

Migrations must be:

- explicit
- deterministic
- documented
- idempotent where practical
- safe to run on the intended environment

Never silently modify the schema from random PHP pages.

Schema changes belong in migrations.

---

## Foreign Keys

Use foreign keys for relationships where appropriate.

Verify:

- referenced table exists
- referenced column exists
- types match
- indexes exist
- deletion behavior is intentional

Use appropriate actions:

    CASCADE
    SET NULL
    RESTRICT

Do not choose them arbitrarily.

---

## Transactions

Use transactions for multi-table business operations.

Example:

    BEGIN
    update paper
    insert approval
    insert audit
    insert notification
    COMMIT

If any required operation fails:

    ROLLBACK

---

## State Integrity

The database must prevent invalid states where practical.

For examination papers, valid states must match the defined state machine.

Do not allow arbitrary status values.

---

## Version Integrity

Document versions must be represented independently.

A version should have a stable identity.

Files belonging to versions must not accidentally share physical storage paths when mutation could affect historical versions.

---

## Seed Data

Seed data must:

- match the actual schema
- use the correct role codes
- respect foreign keys
- be deterministic
- not create fake production documents
- not silently overwrite user data

Development seed data must be clearly separated from production data.

---

## Testing

Every migration must be followed by:

1. migration execution
2. SHOW TABLES
3. DESCRIBE affected tables
4. foreign key verification
5. application query verification
6. relevant automated tests

---

## Database Safety

Never execute destructive SQL against a production-like database without explicit authorization.

Be especially careful with:

    DROP
    TRUNCATE
    DELETE without WHERE
    ALTER
    destructive migrations

Development cleanup scripts must require explicit confirmation.

---

## Schema Drift Detection

Regularly compare:

- schema
- application SQL
- migrations
- seed scripts
- tests

Any mismatch must be reported and resolved.

---

## Completion Standard

Database work is complete only when:

- migration passes
- schema is verified
- foreign keys work
- indexes are correct
- application queries work
- tests pass
- rollback/failure behavior is considered