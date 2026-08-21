# Skill: Secure Examination Document Management

## Purpose

Control all examination-document operations.

This is a security-critical subsystem.

---

## Supported Documents

The application must explicitly define permitted formats.

For examination question papers:

    DOCX

Other categories may have their own allowed extensions according to the project specification.

Never trust the filename extension alone.

---

## Upload Validation

Validate:

1. extension
2. MIME type
3. file signature / magic bytes
4. file size
5. upload error
6. generated storage filename

Never trust:

    $_FILES['name']

as a storage filename.

---

## Storage

Examination files must be stored outside the public webroot where practical.

Never expose raw storage paths.

Do not generate predictable filenames.

---

## File Integrity

Calculate SHA-256 hashes.

On secure retrieval:

1. locate file
2. verify existence
3. verify expected size
4. verify SHA-256
5. verify authorization
6. stream securely

If integrity fails:

    DENY ACCESS

and log a security event.

---

## Versioning

Once a version leaves Draft:

    IMMUTABLE

Never mutate:

- historical metadata
- historical files
- historical hashes
- historical version numbers

---

## Revision

When a paper is returned:

    V1 → V2

The new version must receive independent physical storage.

Example:

    storage/examinations/paper-123/v1/
    storage/examinations/paper-123/v2/

Never allow:

    V1 → same storage_path → V2

if either version may later be modified or deleted.

---

## Reference Counting

Before deleting a physical file:

1. search for other database rows referencing the same path
2. only unlink if no other valid record references it

---

## Preview

DOCX preview should follow:

    authorized request
        ↓
    file lookup
        ↓
    integrity verification
        ↓
    DOCX availability
        ↓
    LibreOffice conversion
        ↓
    PDF verification
        ↓
    secure streaming

Preview must never bypass authorization.

---

## Conversion

LibreOffice path must be configuration-driven.

Do not hardcode a machine-specific path in business logic.

Conversion failures must:

- be logged
- return a controlled user-facing error
- never expose internal filesystem paths

---

## Blind Lockdown

Blind lockdown occurs only after final HOD approval.

The exact approved version must be identified.

Lockdown must:

- identify the exact version
- verify files
- encrypt according to the approved encryption design
- store required metadata
- prevent mutation
- record audit information

Do not encrypt one version while the database points to another.

---

## Download

Downloading must be separately authorized.

Do not assume:

    preview permission = download permission

unless explicitly defined by the business rules.

---

## Completion Standard

Document functionality is complete only after testing:

- valid upload
- invalid MIME
- invalid extension
- oversized file
- preview
- missing file
- corrupted file
- V1 immutability
- V2 revision
- V1/V2 physical isolation
- secure download
- lockdown
- integrity verification