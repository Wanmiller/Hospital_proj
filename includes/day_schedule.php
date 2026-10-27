<?php
// Shared working screen, included only after the caller's role check.
$isDoctor = $scheduleRole === 'Doctor';
$selectedDate = $_GET['date'] ?? clinicToday();
$status = $_GET['status'] ?? '';
$patientName = $_GET['patient_name'] ?? '';
$filterDoctor = $_GET['doctor_id'] ?? '';
$filterError = '';
if (!validClinicDate($selectedDate)) $filterError = t('Invalid date.');
foreach ([$status, $patientName, $filterDoctor] as $filter) if (!is_string($filter)) $filterError = t('Invalid filter.');
if (!is_string($status) || ($status !== '' && !in_array($status, ['Scheduled','Completed','Cancelled'], true))) { $filterError = t('Invalid filter.'); $status = ''; }
$patientName = is_string($patientName) ? trim($patientName) : '';
$filterDoctor = is_string($filterDoctor) ? $filterDoctor : '';
if ($filterDoctor !== '' && !ctype_digit($filterDoctor)) $filterError = t('Invalid filter.');
$selectedDate = is_string($selectedDate) ? $selectedDate : '';
$doctorId = null;
if ($isDoctor) {
    $stmt = $pdo->prepare('SELECT id FROM doctors WHERE user_id=?');
    $stmt->execute([$_SESSION['user_id']]);
    $doctorId = $stmt->fetchColumn();
    if (!$doctorId) die(h(t('Doctor profile not found.')));
}
$doctors = $isDoctor ? [] : $pdo->query("SELECT d.id,u.first_name,u.last_name,u.is_active FROM doctors d JOIN users u ON u.id=d.user_id WHERE u.role='Doctor' ORDER BY u.last_name,u.first_name")->fetchAll();
$appointments = [];
if (!$filterError) {
    $query = "SELECT a.*,p.first_name AS pat_first,p.last_name AS pat_last,u.first_name AS doc_first,u.last_name AS doc_last,dept.name AS dept_name,m.id AS medical_record_id
              FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id
              JOIN users u ON u.id=d.user_id LEFT JOIN departments dept ON dept.id=d.department_id
              LEFT JOIN medical_records m ON m.appointment_id=a.id WHERE a.appointment_date=?";
    $params = [$selectedDate];
    if ($isDoctor || $filterDoctor !== '') { $query .= ' AND a.doctor_id=?'; $params[] = $isDoctor ? $doctorId : $filterDoctor; }
    if ($status !== '') { $query .= ' AND a.status=?'; $params[] = $status; }
    if ($patientName !== '') { $query .= " AND CONCAT(p.first_name,' ',p.last_name) LIKE ?"; $params[] = '%' . $patientName . '%'; }
    $stmt = $pdo->prepare($query . ' ORDER BY a.appointment_time ASC,a.id ASC');
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
}
$counts = ['Total'=>count($appointments),'Scheduled'=>0,'Completed'=>0];
foreach ($appointments as $row) if (isset($counts[$row['status']])) $counts[$row['status']]++;
$path_prefix = '../';
require_once __DIR__ . '/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3">
 <div><h2><?= h(t($isDoctor ? 'My day appointments' : 'Day appointments')) ?></h2><p class="text-secondary mb-0"><?= h(t('Choose a date to view appointments.')) ?></p></div>
 <?php if (!$isDoctor): ?><a class="btn btn-primary" id="new-appointment" href="appointments.php?<?= h(http_build_query(['date'=>validClinicDate($selectedDate) ? $selectedDate : clinicToday(),'create'=>1])) ?>"><?= uiIcon('calendar') ?><span class="ms-1"><?= h(t('Add Appointment')) ?></span></a><?php endif; ?>
</div>
<?php if ($filterError): ?><div class="alert alert-danger" role="alert"><?= h($filterError) ?></div><?php endif; ?>
<div class="card mb-3"><div class="card-body">
 <form method="GET" class="row g-3 filter-bar">
  <div class="col-sm-6 col-xl-2"><label for="day-date" class="form-label"><?= h(t('Date')) ?></label><input id="day-date" name="date" type="date" class="form-control" value="<?= h($selectedDate) ?>" required></div>
  <?php if (!$isDoctor): ?><div class="col-sm-6 col-xl-3"><label for="day-doctor" class="form-label"><?= h(t('Doctor')) ?></label><select id="day-doctor" name="doctor_id" class="form-select"><option value=""><?= h(t('All Doctors')) ?></option><?php foreach ($doctors as $doc): ?><option value="<?= h($doc['id']) ?>" <?= $filterDoctor == $doc['id'] ? 'selected' : '' ?>><?= h($doc['first_name'].' '.$doc['last_name']) ?><?= !$doc['is_active'] ? ' (' . h(t('Inactive')) . ')' : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div class="col-sm-6 col-xl-2"><label for="day-status" class="form-label"><?= h(t('Status')) ?></label><select name="status" id="day-status" class="form-select"><option value=""><?= h(t('Total')) ?></option><?php foreach (['Scheduled','Completed','Cancelled'] as $value): ?><option value="<?= h($value) ?>" <?= $value === $status ? 'selected' : '' ?>><?= h(t($value)) ?></option><?php endforeach; ?></select></div>
  <div class="col-sm-6 col-xl"><label for="day-patient" class="form-label"><?= h(t('Search Patient Name')) ?></label><input type="search" name="patient_name" id="day-patient" class="form-control" value="<?= h($patientName) ?>"></div>
  <div class="col-auto"><button type="submit" class="btn btn-primary"><?= h(t('Filter')) ?></button><a href="dashboard.php" class="btn ms-1"><?= h(t('Clear')) ?></a></div>
 </form>
</div></div>
<div class="d-flex justify-content-between align-items-center mb-3">
 <strong><?= h(validClinicDate($selectedDate) ? displayDate($selectedDate) : t('Date')) ?></strong>
 <?php if (validClinicDate($selectedDate)): $day = new DateTimeImmutable($selectedDate); $filters = ['status'=>$status,'patient_name'=>$patientName]; if (!$isDoctor) $filters['doctor_id']=$filterDoctor; ?>
 <div class="btn-group" aria-label="<?= h(t('Date')) ?>"><a class="btn" aria-label="<?= h(t('Previous day')) ?>" href="?<?= h(http_build_query(array_merge($filters,['date'=>$day->modify('-1 day')->format('Y-m-d')]))) ?>">&larr;</a><a class="btn" href="?<?= h(http_build_query(array_merge($filters,['date'=>clinicToday()]))) ?>"><?= h(t('Today')) ?></a><a class="btn" aria-label="<?= h(t('Next day')) ?>" href="?<?= h(http_build_query(array_merge($filters,['date'=>$day->modify('+1 day')->format('Y-m-d')]))) ?>">&rarr;</a></div>
 <?php endif; ?>
</div>
<div class="day-stats" aria-label="<?= h(t('Current selection')) ?>"><?php foreach ($counts as $label=>$count): ?><div class="card"><div class="card-body"><span><?= h(t($label)) ?></span><strong data-count="<?= h($label) ?>"><?= h($count) ?></strong></div></div><?php endforeach; ?></div>
<div class="card"><div class="table-responsive" tabindex="0" aria-label="<?= h(t('Appointments')) ?>"><table class="table table-vcenter table-hover">
 <thead><tr><th><?= h(t('Time')) ?></th><th><?= h(t('Patient')) ?></th><th><?= h(t('Doctor')) ?></th><th><?= h(t('Department')) ?></th><th><?= h(t('Status')) ?></th><th><?= h(t('Actions')) ?></th></tr></thead>
 <tbody><?php foreach ($appointments as $app): ?><tr data-appointment-id="<?= h($app['id']) ?>">
  <td class="text-nowrap"><?= h(displayTime($app['appointment_time'])) ?></td>
  <td><a href="<?= $isDoctor ? 'patient_history.php?patient_id=' : 'patient_details.php?id=' ?><?= h($app['patient_id']) ?>"><?= h($app['pat_first'].' '.$app['pat_last']) ?></a></td>
  <td><?= h($app['doc_first'].' '.$app['doc_last']) ?></td><td><?= h($app['dept_name'] ?? t('Unassigned')) ?></td><td><?= statusBadge($app['status']) ?></td>
  <td class="actions-cell"><?php if ($isDoctor): ?><a class="btn btn-sm" href="appointment_details.php?id=<?= h($app['id']) ?>"><?= h(t('View')) ?></a><?php elseif ($app['status']==='Scheduled' && !$app['medical_record_id']): ?><a class="btn btn-sm" href="appointments.php?<?= h(http_build_query(['date'=>$selectedDate,'edit'=>$app['id']])) ?>"><?= h(t('Edit')) ?></a><?php endif; ?></td>
 </tr><?php endforeach; ?>
 <?php if (!$appointments): ?><tr><td colspan="6" class="empty-state"><?= h(t('No appointments on this day.')) ?></td></tr><?php endif; ?></tbody>
</table></div></div>
<?php require_once __DIR__ . '/footer.php'; ?>
