<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Admin');

// --- Filters ---
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$dept_filter = $_GET['department'] ?? '';
$doc_filter = $_GET['doctor'] ?? '';

// Building the WHERE clause for queries that use `appointments a`
$app_where = [];
$app_params = [];
if ($date_from) {
    $app_where[] = "a.appointment_date >= ?";
    $app_params[] = $date_from;
}
if ($date_to) {
    $app_where[] = "a.appointment_date <= ?";
    $app_params[] = $date_to;
}
if ($dept_filter) {
    $app_where[] = "d.department_id = ?";
    $app_params[] = $dept_filter;
}
if ($doc_filter) {
    $app_where[] = "a.doctor_id = ?";
    $app_params[] = $doc_filter;
}

$where_clause = count($app_where) > 0 ? "WHERE " . implode(" AND ", $app_where) : "";
$and_clause = count($app_where) > 0 ? "AND " . implode(" AND ", $app_where) : "";

// --- 1. Doctors per Department ---
// (Not affected by date/doctor filters as it's just structure)
$q1 = "SELECT dept.name, COUNT(d.id) AS num_doctors 
       FROM departments dept 
       LEFT JOIN doctors d ON dept.id = d.department_id 
       GROUP BY dept.id 
       ORDER BY num_doctors DESC";
$res_dept_docs = $pdo->query($q1)->fetchAll();

// --- 2. Appointments per Doctor ---
$q2 = "SELECT u.first_name, u.last_name, dept.name AS dept_name, 
              COUNT(a.id) AS total,
              SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
              SUM(CASE WHEN a.status = 'Scheduled' THEN 1 ELSE 0 END) AS scheduled,
              SUM(CASE WHEN a.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
       FROM doctors d
       JOIN users u ON d.user_id = u.id
       LEFT JOIN departments dept ON d.department_id = dept.id
       LEFT JOIN appointments a ON d.id = a.doctor_id 
       $where_clause
       GROUP BY d.id
       ORDER BY total DESC";
$stmt2 = $pdo->prepare($q2);
$stmt2->execute($app_params);
$res_doc_apps = $stmt2->fetchAll();

// --- 3. Appointments by Status ---
// Need to join doctors 'd' so $app_where works if filtering by dept/doc
$q3 = "SELECT a.status, COUNT(a.id) AS count
       FROM appointments a
       JOIN doctors d ON a.doctor_id = d.id
       $where_clause
       GROUP BY a.status";
$stmt3 = $pdo->prepare($q3);
$stmt3->execute($app_params);
$res_status = $stmt3->fetchAll();
$total_apps_for_status = array_sum(array_column($res_status, 'count'));

// --- 4. Appointments per Department ---
// For LEFT JOIN to keep zero-count departments, filters must go into the ON clause or be applied differently.
// We'll join appointments normally to count them, but use LEFT JOIN from departments.
// Using inline view to apply filters first.
$q4_params = $app_params;
$q4 = "SELECT dept.name, COUNT(filtered_a.id) AS total
       FROM departments dept
       LEFT JOIN doctors d ON dept.id = d.department_id
       LEFT JOIN (
           SELECT a.id, a.doctor_id
           FROM appointments a
           JOIN doctors d ON a.doctor_id = d.id
           $where_clause
       ) AS filtered_a ON d.id = filtered_a.doctor_id
       GROUP BY dept.id
       ORDER BY total DESC";
$stmt4 = $pdo->prepare($q4);
$stmt4->execute($app_params);
$res_dept_apps = $stmt4->fetchAll();

// --- 5. Most Active Doctors (Top 5) ---
$q5 = "SELECT u.first_name, u.last_name, dept.name AS dept_name, COUNT(a.id) AS total
       FROM appointments a
       JOIN doctors d ON a.doctor_id = d.id
       JOIN users u ON d.user_id = u.id
       LEFT JOIN departments dept ON d.department_id = dept.id
       $where_clause
       GROUP BY d.id
       ORDER BY total DESC
       LIMIT 5";
$stmt5 = $pdo->prepare($q5);
$stmt5->execute($app_params);
$res_top_docs = $stmt5->fetchAll();

// --- 6. Patients with Most Appointments (Top 5) ---
$q6 = "SELECT p.first_name, p.last_name, p.phone, COUNT(a.id) AS total
       FROM appointments a
       JOIN patients p ON a.patient_id = p.id
       JOIN doctors d ON a.doctor_id = d.id
       $where_clause
       GROUP BY p.id
       ORDER BY total DESC
       LIMIT 5";
$stmt6 = $pdo->prepare($q6);
$stmt6->execute($app_params);
$res_top_patients = $stmt6->fetchAll();

// --- 7. Monthly Appointment Statistics ---
$q7 = "SELECT DATE_FORMAT(a.appointment_date, '%Y-%m') AS month_year,
              COUNT(a.id) AS total,
              SUM(CASE WHEN a.status = 'Scheduled' THEN 1 ELSE 0 END) AS scheduled,
              SUM(CASE WHEN a.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
              SUM(CASE WHEN a.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
       FROM appointments a
       JOIN doctors d ON a.doctor_id = d.id
       $where_clause
       GROUP BY month_year
       ORDER BY month_year DESC";
$stmt7 = $pdo->prepare($q7);
$stmt7->execute($app_params);
$res_monthly = $stmt7->fetchAll();

// Helper queries for filters
$filter_depts = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$filter_docs = $pdo->query("SELECT d.id, u.first_name, u.last_name FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY u.first_name")->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><?= h(t('Hospital Analytics & Reports')) ?></h2>
        <p class="text-muted"><?= h(t('Explore appointments, team activity and department performance.')) ?></p>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body bg-light">
        <form method="GET" action="reports.php" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label"><?= h(t('Date From')) ?></label>
                <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= h(t('Date To')) ?></label>
                <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('Department')) ?></label>
                <select name="department" class="form-select">
                    <option value=""><?= h(t('All Departments')) ?></option>
                    <?php foreach($filter_depts as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $dept_filter == $dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= h(t('Doctor')) ?></label>
                <select name="doctor" class="form-select">
                    <option value=""><?= h(t('All Doctors')) ?></option>
                    <?php foreach($filter_docs as $doc): ?>
                        <option value="<?= $doc['id'] ?>" <?= $doc_filter == $doc['id'] ? 'selected' : '' ?>><?= h(t('Dr.')) ?> <?= h($doc['first_name'] . ' ' . $doc['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><?= h(t('Apply Filters')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <!-- Appointments by Status -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= h(t('Appointments by Status')) ?></h6>
            </div>
            <div class="card-body">
                <?php if($total_apps_for_status == 0): ?>
                    <p class="text-muted"><?= h(t('No data available for this period.')) ?></p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach($res_status as $s): ?>
                        <?php $pct = round(($s['count'] / $total_apps_for_status) * 100, 1); ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= h(t($s['status'])) ?>
                            <span>
                                <strong><?= h($s['count']) ?></strong> 
                                <span class="badge bg-secondary-lt rounded-pill"><?= $pct ?>%</span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Appointments per Department -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= h(t('Appointments per Department')) ?></h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th><?= h(t('Department')) ?></th><th><?= h(t('Total')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach($res_dept_apps as $row): ?>
                        <tr>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h($row['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($res_dept_apps)==0): ?><tr><td colspan="2"><?= h(t('No data')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Doctors per Department -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= h(t('Doctors per Department (Static)')) ?></h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th><?= h(t('Department')) ?></th><th><?= h(t('Doctors')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach($res_dept_docs as $row): ?>
                        <tr>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h($row['num_doctors']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Most Active Doctors -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= h(t('Most Active Doctors (Top 5)')) ?></h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th><?= h(t('Doctor')) ?></th><th><?= h(t('Department')) ?></th><th><?= h(t('Appointments')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach($res_top_docs as $row): ?>
                        <tr>
                            <td><?= h(t('Dr.')) ?> <?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= h($row['dept_name']) ?></td>
                            <td><strong><?= h($row['total']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($res_top_docs)==0): ?><tr><td colspan="3" class="text-center"><?= h(t('No data available for this period.')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Top Patients -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0"><?= h(t('Patients with Most Appointments (Top 5)')) ?></h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th><?= h(t('Patient')) ?></th><th><?= h(t('Phone')) ?></th><th><?= h(t('Appointments')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach($res_top_patients as $row): ?>
                        <tr>
                            <td><?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= h($row['phone']) ?></td>
                            <td><strong><?= h($row['total']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($res_top_patients)==0): ?><tr><td colspan="3" class="text-center"><?= h(t('No data available for this period.')) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Appointments per Doctor (Comprehensive) -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><?= h(t('Appointments per Doctor (Detailed)')) ?></h6>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('Doctor')) ?></th>
                    <th><?= h(t('Department')) ?></th>
                    <th><?= h(t('Total Appointments')) ?></th>
                    <th class="text-success"><?= h(t('Completed')) ?></th>
                    <th class="text-info"><?= h(t('Scheduled')) ?></th>
                    <th class="text-danger"><?= h(t('Cancelled')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($res_doc_apps as $row): ?>
                <tr>
                    <td><?= h(t('Dr.')) ?> <?= h($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td><?= h($row['dept_name']) ?></td>
                    <td><strong><?= h($row['total']) ?></strong></td>
                    <td class="text-success"><?= h($row['completed']) ?></td>
                    <td class="text-info"><?= h($row['scheduled']) ?></td>
                    <td class="text-danger"><?= h($row['cancelled']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($res_doc_apps)==0): ?><tr><td colspan="6" class="text-center"><?= h(t('No data available.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Monthly Statistics -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0"><?= h(t('Monthly Appointment Statistics')) ?></h6>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('Month (YYYY-MM)')) ?></th>
                    <th><?= h(t('Total Appointments')) ?></th>
                    <th class="text-success"><?= h(t('Completed')) ?></th>
                    <th class="text-info"><?= h(t('Scheduled')) ?></th>
                    <th class="text-danger"><?= h(t('Cancelled')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($res_monthly as $row): ?>
                <tr>
                    <td><strong><?= h($row['month_year']) ?></strong></td>
                    <td><strong><?= h($row['total']) ?></strong></td>
                    <td class="text-success"><?= h($row['completed']) ?></td>
                    <td class="text-info"><?= h($row['scheduled']) ?></td>
                    <td class="text-danger"><?= h($row['cancelled']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($res_monthly)==0): ?><tr><td colspan="5" class="text-center"><?= h(t('No data available.')) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
