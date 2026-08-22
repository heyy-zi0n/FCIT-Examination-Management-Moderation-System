# LASU FCIT Examination Management & Moderation System (EMMS)

The **LASU FCIT Examination Management & Moderation System** is a secure, role-based platform designed to manage the complete lifecycle of examination question papers for the Faculty of Communication and Information Technology (FCIT), Lagos State University.

The system ensures academic integrity through a rigorous multi-tier moderation workflow, dual-approval sign-offs, and cryptographic blind-lockdown (AES-256-GCM encryption) prior to controlled physical printing.

## Core Features

- **Lifecycle Management:** Tracks examination papers through canonical stages:
  `Draft → Submitted → Moderated → HOD Appr. → EO Appr. → Locked → Ready to Print → Printed`
- **Role-Based Workflows:**
  - **Lecturers:** Submit drafts, track lifecycle status, and address moderator corrections via revisions.
  - **Moderators:** Review submissions, provide line-by-line annotations, and return or approve papers.
  - **Heads of Department (HODs):** Oversee departmental readiness and perform the first sign-off for moderated papers.
  - **Examination Officers (EOs):** Manage the overall examination pipeline, perform final sign-off, and execute secure, time-gated printing.
- **Cryptographic Blind Lockdown:** Once dual-approved, papers are automatically encrypted and sealed until the scheduled print window opens.
- **Audit Logging:** Every state transition, login, and administrative action is persistently logged.
- **Watermarked Previews:** Integrated secure document previewing to prevent unauthorized downloading.

## Prerequisites

- **PHP** 8.1 or higher
- **MySQL** 
- **Web Server** (Apache/Nginx) configured for PHP (e.g., via XAMPP/MAMP)
- **LibreOffice** (Required for secure PDF/Docx previews; ensure `soffice.exe` path is correctly set in `.env` if not in the default Windows location)

## Installation & Setup

1. **Clone the Repository**
   Clone the repository into your local server's web root (e.g., `htdocs/FOCITECMS`).

2. **Configure Environment Variables**
   Copy the example environment configuration file and update the variables for your local setup:
   ```bash
   cp .env.example .env
   ```
   Open `.env` and configure your database credentials (`DB_USERNAME`, `DB_PASSWORD`), `APP_URL`, and ensure your `ENCRYPTION_KEY` is securely set.

3. **Database Migration and Seeding**
   A comprehensive migration and seeder script is included to automatically provision the database schema and prepopulate it with test data, including departments, courses, schedules, and test accounts.
   
   Execute the migration script from your terminal:
   ```bash
   cd database/
   php migrate.php
   ```
   *(Note: This script will drop and recreate the `lasu_fcit_exam_db` database. Do not run this on a production environment where data must be preserved.)*

## Test Accounts

Following a successful database migration, the following accounts will be available for end-to-end testing (Password for all accounts is `Password123!`):

- **Admin:** `admin@lasu.edu.ng`
- **Exam Officer:** `exam.officer@lasu.edu.ng`
- **HOD (Computer Science):** `hod.csc@lasu.edu.ng`
- **HOD (Cyber Security):** `hod.cyb@lasu.edu.ng`
- **Moderator (Computer Science):** `moderator.csc@lasu.edu.ng`
- **Lecturer (Computer Science):** `lecturer.csc@lasu.edu.ng`
- **Lecturer (Cyber Security):** `lecturer.cyb@lasu.edu.ng`

## Security Considerations

- The `storage/` directory contains sensitive uploaded examinations, previews, and backups. Direct web access to this directory is blocked via `.htaccess`. Ensure your web server is configured to respect `.htaccess` overrides.
- The `.env` file contains sensitive credentials and encryption keys; it is explicitly ignored in `.gitignore` and must never be committed to source control.
