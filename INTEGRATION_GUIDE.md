# LASU FCIT — Integration Guide (`faculty` + `ems`)

This guide explains how to combine the **LASU FCIT Examination Management & Moderation System (`ems`)** as a subfolder inside the **LASU FCIT Faculty Website & Staff Portal (`faculty`)**, sharing the single `lasu_faculty` database and staff authentication session.

---

## 1. Subfolder Placement (`faculty/ems/`)

1. Copy or extract the `ems` project folder directly inside the root of the `faculty` project so the directory structure is:
   ```text
   faculty/
   ├── public/
   ├── src/
   ├── ems/                  <-- This project
   │   ├── assets/
   │   ├── config/
   │   ├── dashboard/
   │   ├── database/
   │   │   ├── schema.sql
   │   │   └── faculty_integration.sql
   │   ├── helpers/
   │   ├── includes/
   │   ├── middleware/
   │   ├── services/
   │   ├── storage/
   │   ├── .env.example
   │   ├── .htaccess
   │   ├── bootstrap.php
   │   ├── index.php
   │   └── login.php
   ├── .htaccess
   └── index.php
   ```
2. **URL Auto-Detection**:
   - `ems/config/app.php` and `ems/helpers/url_helper.php` automatically detect the subfolder path (`/ems` or `/faculty/ems`) when `APP_URL=` is left empty in `ems/.env`.
3. **Web Server Routing**:
   - **Apache (`.htaccess`)**: `ems/.htaccess` defines its own `RewriteEngine On` without `RewriteOptions Inherit`. Apache will serve `/ems/*` directly through `ems/` and will not rewrite `/ems/*.php` into `faculty/public/index.php`.
   - **Nginx (if deploying on Nginx per `faculty/DEPLOYMENT.md`)**: Add a `location ^~ /ems/` block above the root `location /` block so `/ems/` PHP scripts execute directly rather than routing to `/public/index.php`:
     ```nginx
     location ^~ /ems/ {
         alias /var/www/lasu-faculty/ems/;
         index index.php;

         location ~ ^/ems/(config|database|services|middleware|helpers|includes|storage|tests)/ {
             deny all;
             return 403;
         }

         location ~ \.php$ {
             include snippets/fastcgi-php.conf;
             fastcgi_param SCRIPT_FILENAME $request_filename;
             fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
         }
     }
     ```

---

## 2. Environment Configuration (`ems/.env`)

1. Copy `ems/.env.example` to `ems/.env`:
   ```env
   APP_ENV=local
   APP_DEBUG=false
   APP_URL=

   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=lasu_faculty
   DB_USERNAME=root
   DB_PASSWORD=

   ENCRYPTION_KEY=replace_with_at_least_32_byte_random_hex_secret_key
   ```
2. Ensure `ems/storage/` (`examinations/`, `previews/`, `profile_pictures/`, `backups/`, `logs/`) is writable by the web server user.

---

## 3. Database Integration (`database/faculty_integration.sql`)

Instead of running `ems/database/schema.sql` (which creates a standalone `lasu_fcit_exam_db` database), run **`ems/database/faculty_integration.sql`** against your existing **`lasu_faculty`** database:

```bash
mysql -u root -p lasu_faculty < ems/database/faculty_integration.sql
```

### What `database/faculty_integration.sql` Does (100% Non-Destructive)
- **Zero existing `lasu_faculty` tables are dropped or modified.**
- Creates the 15 non-colliding EMMS tables with InnoDB foreign keys referencing `lasu_faculty.staff(id)`, `departments(id)`, `courses(id)`, and `academic_sessions(id)`:
  - `semesters`, `academic_levels`, `ems_staff_meta`
  - `moderator_assignments`, `exam_schedules`, `delegations`
  - `examination_papers`, `paper_versions`, `paper_approvals`, `moderation_comments`
  - `blind_lockdowns`, `print_logs`, `emergency_print_requests`, `notifications`, `audit_logs`
- Creates 4 SQL `VIEW`s that map `lasu_faculty`'s existing tables into EMMS's expected structure:

| EMMS Entity | Source in `lasu_faculty` | Mapping Details |
| :--- | :--- | :--- |
| **`departments`** | `lasu_faculty.departments` | Shared directly (`id`, `code`, `name`, `description`). |
| **`users`** *(SQL View)* | `lasu_faculty.staff` + `assignments` + `positions` + `ems_staff_meta` | Maps `staff.id -> id`, `staff.password -> password_hash`, splits `staff.name` into `title`, `first_name`, `last_name`, resolves HOD department from active `assignments`/`departments.hod_id`, and maps `role` (`admin`/`dean`/`web_administrator` $\to$ `admin`, `hod` $\to$ `hod`, `Examination Officer` position $\to$ `exam_officer`, default $\to$ `lecturer`, or overridden via `ems_staff_meta.ems_role`). |
| **`lecturer_course_assignments`** *(SQL View)* | `lasu_faculty.course_allocations` + `academic_sessions` | Maps active allocations (`is_current = 1`): `staff_id -> lecturer_id`, `course_id`, `academic_sessions.id -> academic_session_id`, `allocated_by -> assigned_by`. |
| **`ems_courses`** *(SQL View)* | `lasu_faculty.courses` + `programs` | Maps `code -> course_code`, `title -> course_title`, `programs.department_id -> department_id`, `level -> level_id` (`100`..`400`, `Year 1`/`Diploma Yr 1`, `Year 2`/`Diploma Yr 2`, `Postgraduate`), `semester -> semester_id`, `credit_unit -> credit_units`, `(status = 'active') -> is_active`. *(Alternatively, add those alias columns as generated `VIRTUAL` columns directly on `lasu_faculty.courses` if you prefer not to change `FROM courses` to `FROM ems_courses` in EMMS).* |
| **`ems_academic_sessions`** *(SQL View)* | `lasu_faculty.academic_sessions` | Maps `name -> session_name` and `(status = 'current') -> is_current`. *(Alternatively, add `session_name VARCHAR(20) GENERATED ALWAYS AS (name) VIRTUAL` and `is_current TINYINT(1) GENERATED ALWAYS AS (status = 'current') VIRTUAL` on `lasu_faculty.academic_sessions` so all existing `FROM academic_sessions` queries in EMMS work with zero SQL edits!)* |

### Pro Tip: Zero-Query-Edit Option Using MySQL/MariaDB Virtual Columns
If you want **every existing query in `ems`** against `courses` and `academic_sessions` to work without changing table names to `ems_courses` / `ems_academic_sessions`, you can run this 2-table `ALTER TABLE` on `lasu_faculty`:

```sql
-- 1. Add virtual alias columns to `academic_sessions` (non-breaking for `faculty`)
ALTER TABLE `academic_sessions`
    ADD COLUMN IF NOT EXISTS `session_name` VARCHAR(20) GENERATED ALWAYS AS (`name`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `is_current` TINYINT(1) GENERATED ALWAYS AS (IF(`status` = 'current', 1, 0)) VIRTUAL;

-- 2. Add virtual/helper columns to `courses` (non-breaking for `faculty`)
ALTER TABLE `courses`
    ADD COLUMN IF NOT EXISTS `course_code` VARCHAR(20) GENERATED ALWAYS AS (`code`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `course_title` VARCHAR(255) GENERATED ALWAYS AS (`title`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `department_id` INT(11) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `level_id` INT(11) GENERATED ALWAYS AS (
        CASE
            WHEN TRIM(`level`) IN ('100', '100 Level') THEN 1
            WHEN TRIM(`level`) IN ('200', '200 Level') THEN 2
            WHEN TRIM(`level`) IN ('300', '300 Level') THEN 3
            WHEN TRIM(`level`) IN ('400', '400 Level') THEN 4
            WHEN TRIM(`level`) IN ('500', '500 Level') THEN 5
            WHEN TRIM(`level`) IN ('Year 1', 'Diploma Yr 1', 'Diploma Year 1') THEN 6
            WHEN TRIM(`level`) IN ('Year 2', 'Diploma Yr 2', 'Diploma Year 2') THEN 7
            ELSE 8
        END
    ) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `semester_id` INT(11) GENERATED ALWAYS AS (IF(`semester` IN (1, 2), `semester`, 1)) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `credit_units` INT(11) GENERATED ALWAYS AS (`credit_unit`) VIRTUAL,
    ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) GENERATED ALWAYS AS (IF(`status` = 'active', 1, 0)) VIRTUAL;

-- Populate `courses.department_id` from `programs.department_id`
UPDATE `courses` c
JOIN `programs` p ON c.program_id = p.id
SET c.department_id = p.department_id
WHERE c.department_id IS NULL;
```

With those virtual columns in place + `database/faculty_integration.sql`, **`lasu_faculty` satisfies 100% of `ems`'s `SELECT` queries out of the box!**

---

## 4. Single Sign-On (SSO) Session Sharing

Both projects already store the authenticated staff member's primary key in **`$_SESSION['user_id']`**:
- In `faculty/src/Auth/Auth.php`:
  ```php
  Session::set('user_id', $user['id']); // staff.id
  Session::set('role', $user['role']);
  Session::set('name', $user['name']);
  Session::set('email', $user['email']);
  ```
- In `ems/helpers/security_helper.php`:
  `currentUser()` reads `$_SESSION['user_id']` and queries `users WHERE id = :id AND status = 'active'`.

To enable automatic Single Sign-On between `/portal` and `/ems`:
1. In `ems/config/app.php` (line 43), change the session cookie name from `'LASU_FCIT_SESSID'` to PHP's default `'PHPSESSID'` (matching `faculty/src/Http/Session.php`):
   ```php
   'session' => [
       'name' => 'PHPSESSID',
       // ...
   ],
   ```
2. For write operations on `users` during login (`ems/helpers/security_helper.php` line 88 and `ems/services/AuthService.php` lines 57 & 68), update `ems_staff_meta` instead of `users`:
   ```sql
   INSERT INTO ems_staff_meta (staff_id, last_login_at, failed_attempts, locked_until, ems_status)
   VALUES (:id, NOW(), 0, NULL, 'active')
   ON DUPLICATE KEY UPDATE last_login_at = NOW(), failed_attempts = 0, locked_until = NULL, ems_status = 'active';
   ```
3. To assign a staff member as an **Examination Officer** or **Moderator** in EMMS, either:
   - Assign them the `"Examination Officer"` position in `faculty` (`portal/positions`), **or**
   - Insert/update a row in `ems_staff_meta`:
     ```sql
     INSERT INTO ems_staff_meta (staff_id, ems_role, ems_department_id)
     VALUES (13, 'exam_officer', 1)
     ON DUPLICATE KEY UPDATE ems_role = VALUES(ems_role), ems_department_id = VALUES(ems_department_id);
     ```

---

## 5. Adding the EMMS Link in the Faculty Portal Sidebar

In `faculty/src/views/portal/includes/portal_header.php`, add a link to `url('ems/')` inside `<nav class="portal-nav">`:

```php
<div class="portal-nav-label">Examination Governance</div>
<a href="<?php echo url('ems/'); ?>" class="portal-nav-link">
    <span>&#128221;</span> Examination System (EMMS)
</a>
```
