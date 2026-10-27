<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Receptionist');

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = t('Invalid CSRF token.');
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $dob = $_POST['dob'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            // Validation
            $errors = [];
            if (empty($first_name)) $errors[] = t('First name is required.');
            if (empty($last_name)) $errors[] = t('Last name is required.');
            if (empty($dob)) $errors[] = t('Date of birth is required.');
            if (empty($phone)) $errors[] = t('Phone is required.');
            if (!in_array($gender, ['Male', 'Female', 'Other'])) $errors[] = t('Invalid gender selection.');
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Invalid email format.');
            
            if (count($errors) > 0) {
                $msg = implode("<br>", $errors);
                $msgType = 'danger';
            } else {
                try {
                    if ($action === 'edit' && $id) {
                        $stmt = $pdo->prepare("UPDATE patients SET first_name=?, last_name=?, dob=?, gender=?, phone=?, email=?, address=? WHERE id=?");
                        $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $email, $address, $id]);
                        $msg = t('Patient updated successfully.');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO patients (first_name, last_name, dob, gender, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$first_name, $last_name, $dob, $gender, $phone, $email, $address]);
                        $msg = t('Patient added successfully.');
                    }
                } catch(PDOException $e) {
                    $msg = t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    // Check for appointment history
                    $check = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
                    $check->execute([$id]);
                    if ($check->fetchColumn() > 0) {
                        $msg = t('This patient cannot be deleted because appointment history exists.');
                        $msgType = 'danger';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
                        $stmt->execute([$id]);
                        $msg = t('Patient deleted successfully.');
                    }
                } catch(PDOException $e) {
                    $msg = t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Search functionality
$search = trim($_GET['search'] ?? '');
$query = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('Manage Patients')) ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#patientModal" onclick="resetForm()"><?= h(t('Add Patient')) ?></button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= $msg ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="patients.php" class="row g-3 align-items-center">
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="<?= h(t('Search by name, phone, or email')) ?>" value="<?= h($search) ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-secondary"><?= h(t('Search')) ?></button>
                <a href="patients.php" class="btn btn-outline-secondary ms-2"><?= h(t('Clear')) ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('ID')) ?></th>
                    <th><?= h(t('Name')) ?></th>
                    <th><?= h(t('DOB')) ?></th>
                    <th><?= h(t('Gender')) ?></th>
                    <th><?= h(t('Phone')) ?></th>
                    <th><?= h(t('Email')) ?></th>
                    <th><?= h(t('Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($patients as $p): ?>
                <tr>
                    <td><?= h($p['id']) ?></td>
                    <td><strong><?= h($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                    <td><?= h($p['dob']) ?></td>
                    <td><?= h(t($p['gender'])) ?></td>
                    <td><?= h($p['phone']) ?></td>
                    <td><?= h($p['email']) ?></td>
                    <td>
                        <a href="patient_details.php?id=<?= h($p['id']) ?>" class="btn btn-sm btn-info text-white"><?= h(t('View')) ?></a>
                        <button class="btn btn-sm btn-outline-primary" data-edit-handler="editPatient" data-edit-values="<?= json_attr([$p['id'], $p['first_name'], $p['last_name'], $p['dob'], $p['gender'], $p['phone'], $p['email'], $p['address']]) ?>" data-bs-toggle="modal" data-bs-target="#patientModal"><?= h(t('Edit')) ?></button>
                        
                        <form method="POST" action="patients.php" class="d-inline" data-confirm="<?= h(t('Delete this patient permanently?')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(t('Delete')) ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($patients) == 0): ?>
                <tr><td colspan="7" class="text-center text-muted"><?= h(t('No patients found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="patientModal" tabindex="-1" aria-labelledby="patientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="patients.php">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="modal_action" value="add">
          <input type="hidden" name="id" id="patient_id">
          
          <div class="modal-header">
            <h5 class="modal-title" id="patientModalLabel"><?= h(t('Add Patient')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('First Name')) ?> *</label>
                      <input type="text" name="first_name" id="p_fname" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Last Name')) ?> *</label>
                      <input type="text" name="last_name" id="p_lname" class="form-control" required>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Date of Birth')) ?> *</label>
                      <input type="date" name="dob" id="p_dob" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Gender')) ?> *</label>
                      <select name="gender" id="p_gender" class="form-select" required>
                          <option value="Male"><?= h(t('Male')) ?></option>
                          <option value="Female"><?= h(t('Female')) ?></option>
                          <option value="Other"><?= h(t('Other')) ?></option>
                      </select>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Phone')) ?> *</label>
                      <input type="text" name="phone" id="p_phone" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Email')) ?></label>
                      <input type="email" name="email" id="p_email" class="form-control">
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Address')) ?></label>
                  <textarea name="address" id="p_address" class="form-control" rows="2"></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save Patient')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('patient_id').value = '';
    document.getElementById('p_fname').value = '';
    document.getElementById('p_lname').value = '';
    document.getElementById('p_dob').value = '';
    document.getElementById('p_gender').value = 'Male';
    document.getElementById('p_phone').value = '';
    document.getElementById('p_email').value = '';
    document.getElementById('p_address').value = '';
    document.getElementById('patientModalLabel').innerText = <?= json_encode(t('Add Patient')) ?>;
}
function editPatient(id, fname, lname, dob, gender, phone, email, address) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('patient_id').value = id;
    document.getElementById('p_fname').value = fname;
    document.getElementById('p_lname').value = lname;
    document.getElementById('p_dob').value = dob;
    document.getElementById('p_gender').value = gender;
    document.getElementById('p_phone').value = phone;
    document.getElementById('p_email').value = email;
    document.getElementById('p_address').value = address;
    document.getElementById('patientModalLabel').innerText = <?= json_encode(t('Edit Patient')) ?>;
}
</script>

<?php require_once '../includes/footer.php'; ?>
