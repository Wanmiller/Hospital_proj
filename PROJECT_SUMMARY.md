# Hospital Management System

## Project Overview
This project is a comprehensive Hospital Management System designed for a university Database and Web Development course. It demonstrates advanced relational database concepts, role-based access control, secure authentication, and data integrity across a multi-user environment.

## Technology Stack
- **Backend:** PHP 8+ (Procedural)
- **Database:** MySQL via PDO
- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript (Vanilla)
- **Environment:** XAMPP

## User Roles & Capabilities
1. **Admin**
   - System overview via Dashboard metrics and analytics.
   - Manage Departments (CRUD).
   - Manage Users (CRUD).
   - Manage Doctors (Linking Users to Departments).
   - View advanced academic Reports (Appointments per Doctor, Doctors per Department, Monthly Statistics, etc.).

2. **Receptionist**
   - Manage Patients (CRUD).
   - Schedule, Edit, and Cancel Appointments.
   - Ensure double-bookings are blocked gracefully.
   - Maintain schedule history by utilizing 'Cancelled' workflows.

3. **Doctor**
   - View assigned Scheduled/Completed appointments.
   - Create and edit Patient Medical Records for their own appointments.
   - Prescribe medications linking natively to a specific Medical Record.
   - View chronological Patient Medical History exclusively for treated patients.

4. **Patient**
   - Register a personal account and sign in through the shared login page.
   - Select an active doctor and an available half-hour slot within the next 90 days.
   - View only their own appointments and cancel scheduled visits without medical records.
   - Existing patient cards can receive an account through Admin user management without creating duplicate cards.
   - Account ownership is enforced by the `patient_accounts` relation and server-side authorization. See [patient-role.md](docs/patient-role.md) for migration, booking rules and test instructions.

## Database Concepts Demonstrated
The project utilizes a highly normalized schema (`hospital_db`) and features the following academic SQL concepts:
- **Primary Keys & Foreign Keys:** Strict referential integrity (e.g., deleting a department safely nullifies the link for doctors).
- **1:N Relationships:** One Doctor to Many Appointments.
- **1:1 Relationships:** One Appointment to One Medical Record.
- **SQL JOIN / LEFT JOIN:** Extracting relational data (e.g., linking a prescription back to the patient demographics via medical records and appointments).
- **GROUP BY & COUNT:** Calculating most active doctors or patients.
- **COUNT DISTINCT:** Tracking unique patients seen by a specific doctor across multiple appointments.
- **Conditional Aggregation (`SUM(CASE WHEN...)`):** Used in reports to pivot appointment statuses into total columns dynamically.
- **Transactions:** Wrapping Medical Record creation and Appointment Status updates into atomic PDO transactions (`beginTransaction()`, `commit()`, `rollBack()`).
- **Prepared Statements:** Securing all user inputs against SQL Injection.

## Full Hospital Workflow (End-to-End)
1. **System Initialization:** An Admin creates a clinical `Department` and registers a new `Doctor` user.
2. **Registration:** A `Receptionist` registers a new walk-in `Patient`.
3. **Scheduling:** The Receptionist schedules an `Appointment` for the Patient with the new Doctor. Double-booking checks prevent overlapping timeslots.
4. **Consultation:** The Doctor logs in, views their dashboard, and opens the assigned Appointment.
5. **Medical Record & Prescriptions:** The Doctor completes the visit by entering a `Diagnosis` and `Treatment` (which automatically updates the appointment to 'Completed' via a transaction). They then attach multiple `Prescriptions` to the record.
6. **Reporting:** The Admin can view updated Analytics and Reports reflecting the new completed appointments, active doctor metrics, and departmental load.

## Installing and upgrading
- Configure MySQL in `includes/config.php`, or use `HOSPITAL_DB_HOST`, `HOSPITAL_DB_USER`, `HOSPITAL_DB_PASS`, and `HOSPITAL_DB_NAME` environment variables. A non-default port can be supplied as `127.0.0.1;port=3307` in `HOSPITAL_DB_HOST`.
- For a fresh installation, run `setup_db.php`. It reads table definitions from `database/hospital.sql` without importing sample data or dropping the database. Its initial accounts are `admin/admin123` and `reception/rec123`.
- To upgrade an existing installation, run `php database/upgrade.php`. This adds missing fields and constraints and migrates specializations into a shared reference table. If it reports invalid doctor accounts, run `php database/repair_doctors.php`: this preserves profiles and appointment history and attaches invalid profiles to new inactive doctor accounts, without changing existing user identities or roles. An administrator must set correct names/passwords before activating recovered accounts. Resolve existing duplicate occupied slots or duplicate department names before retrying an unsuccessful upgrade. Do not import `hospital.sql` into an existing installation: that sample import recreates the database.
- Only scheduled appointments without medical records can be edited or cancelled. Cancelled slots become available again. Completed appointments continue occupying their original slots.
- Account deactivation or role changes invalidate existing sessions on their next protected request. The doctor's Patient History menu lists patients with non-cancelled appointments assigned to that doctor.
- After setup or import, run `php database/seed_demo.php` to add the fictional topic 4 dataset, including Kazakh names: 56 records in the original seven tables and up to two new specialization entries. The import is transactional, preserves existing records and can be repeated without duplicates. Demo doctor usernames are `demo_topic4_doctor_1`, `demo_topic4_doctor_2`, and `demo_topic4_doctor_3`; their initial password is `password123`. Names, contact details, diagnoses and prescriptions are educational examples.
- `database/hospital.sql` creates 17 initial sample records including two specialization entries; the additional demo import brings a fresh sample installation to 75 records. The installer alone creates only the initial administrator and receptionist accounts before the demo import.
- Every doctor profile must have a user with the Doctor role. Database foreign keys enforce this and prevent deleting doctors with appointments or deleting users with doctor profiles. Changing a doctor's role through the UI is allowed only without appointment history; the unused profile is removed within the same transaction. Specializations are selected from the `specializations` reference table, with normalized, unique names when adding a new entry.

## Regression checks
`tests/run_tests_regressions.php` requires an isolated database whose name begins with `hospital_regression_`; it recreates that test database. Start a separate PHP server with matching `HOSPITAL_DB_*` variables, set `HOSPITAL_TEST_URL` to its URL, then run `php tests/run_tests_regressions.php`. The checks cover fresh installation, repeatable upgrades, revoked sessions, appointment history protection, navigation, cancelled-slot reuse, and concurrent booking.

## Interface
The Kazakh interface uses locally stored Tabler Core 1.7.1 and a subset of Tabler Icons 3.35.0. It has a responsive light sidebar, compact sign-in page, shared table/form styles, and working day schedules for receptionists and doctors. Administrator overview shows three indicators and recent appointments. Theme sources and MIT licenses are documented in `assets/vendor/README.md`; there is no second Bootstrap bundle. Shared translations are in `includes/lang/kk.json`, and custom CSS is limited to project-specific adjustments.

Current desktop/mobile previews and browser verification results are in `docs/tabler-preview/`; earlier previews remain in `docs/ui-preview/` as historical artifacts. No external fonts or CDN files are required at runtime. Day screens use `Asia/Qyzylorda` for today, show dates as `dd.mm.yyyy` and times as `HH:mm`, and preserve database/POST enum values. Access-denied pages return HTTP 403 with Kazakh messages. The old admin/receptionist index URLs redirect to their dashboard pages.

Browser acceptance: start a separate PHP server with matching `HOSPITAL_DB_*` variables, choose a database named `hospital_regression_*`, set `HOSPITAL_TEST_URL`, then run `python tests/test_tabler_browser.py`. It resets only the guarded test database using `tests/tabler_fixtures.php`, exercises all role screens, modal focus, Kazakh messages, filters, quick booking, safe edit data, cancellation and mobile layout, and writes new previews. It does not migrate the application database.
