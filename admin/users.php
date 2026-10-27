<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/doctor_integrity.php';

requireRole('Admin');

$msg = '';
$msgType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = t('Invalid CSRF token.');
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($first_name) || empty($last_name) || !in_array($role, ['Admin', 'Receptionist', 'Doctor', 'Patient'])) {
                $msg = t('All required fields must be filled correctly.');
                $msgType = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    $linked = $pdo->prepare('SELECT patient_id FROM patient_accounts WHERE user_id=? FOR UPDATE');
                    $linked->execute([$id]);
                    $patientId = $linked->fetchColumn();
                    if ($patientId && $role !== 'Patient') {
                        throw new DomainException(t('Patient role management rule'));
                    }
                    $selectedPatient = $_POST['patient_id'] ?? '';
                    if ($role === 'Patient' && !$patientId) {
                        if (!is_string($selectedPatient) || !ctype_digit($selectedPatient)) throw new DomainException(t('Select patient card.'));
                        $card = $pdo->prepare('SELECT id FROM patients WHERE id=? FOR UPDATE');
                        $card->execute([$selectedPatient]);
                        if (!$card->fetchColumn()) throw new DomainException(t('Select patient card.'));
                    }
                    
                    if ($action === 'edit' && $id) {
                        prepareUserRoleChange($pdo, $id, $role);
                        $sql = "UPDATE users SET username=?, first_name=?, last_name=?, role=? ";
                        $params = [$username, $first_name, $last_name, $role];
                        
                        if (!empty($password)) {
                            $sql .= ", password_hash=? ";
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                        }
                        $sql .= "WHERE id=?";
                        $params[] = $id;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        // If role changed to Doctor but not in doctors table, create placeholder record
                        if ($role === 'Doctor') {
                            $check = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
                            $check->execute([$id]);
                            if (!$check->fetch()) {
                                $stmt = $pdo->prepare("INSERT INTO doctors (user_id) VALUES (?)");
                                $stmt->execute([$id]);
                            }
                        }
                        if ($role === 'Patient' && !$patientId) {
                            $pdo->prepare('INSERT INTO patient_accounts (user_id,patient_id) VALUES (?,?)')->execute([$id,$selectedPatient]);
                        }
                        $msg = t('User updated successfully.');
                    } else {
                        if (empty($password)) {
                            throw new Exception(t('Password is required for new users.'));
                        }
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $first_name, $last_name]);
                        
                        $new_user_id = $pdo->lastInsertId();
                        
                        if ($role === 'Doctor') {
                            $stmt = $pdo->prepare("INSERT INTO doctors (user_id) VALUES (?)");
                            $stmt->execute([$new_user_id]);
                        }
                        if ($role === 'Patient') {
                            $pdo->prepare('INSERT INTO patient_accounts (user_id,patient_id) VALUES (?,?)')->execute([$new_user_id,$selectedPatient]);
                        }
                        $msg = t('User added successfully.');
                    }
                    
                    $pdo->commit();
                } catch(Exception $e) {
                    $pdo->rollBack();
                    if ($e->getCode() == 23000) {
                        $msg = t(strpos($e->getMessage(), 'patient_id') !== false ? 'Patient card already linked.' : 'Username already exists.');
                    } else {
                        $msg = $e->getMessage();
                    }
                    $msgType = 'danger';
                }
            }
        } elseif ($action === 'toggle_active') {
            $id = $_POST['id'] ?? null;
            if ($id == $_SESSION['user_id']) {
                $msg = t('You cannot deactivate your own account.');
                $msgType = 'danger';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    $msg = t('User status updated.');
                } catch(PDOException $e) {
                    $msg = t('Database error.');
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Handle Filters & Search
$search = trim($_GET['search'] ?? '');
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';

$query = "SELECT id, username, first_name, last_name, role, is_active, created_at FROM users WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (in_array($filter_role, ['Admin', 'Doctor', 'Receptionist', 'Patient'])) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
}
if ($filter_status === '1' || $filter_status === '0') {
    $query .= " AND is_active = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
$patientCards = $pdo->query('SELECT p.id,p.first_name,p.last_name,p.dob FROM patients p LEFT JOIN patient_accounts pa ON pa.patient_id=p.id WHERE pa.user_id IS NULL ORDER BY p.last_name,p.first_name')->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('Manage Users')) ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()"><?= h(t('Add User')) ?></button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= h(t($msg)) ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="users.php" class="row g-3 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="<?= h(t('Search name/username')) ?>" value="<?= h($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value=""><?= h(t('All Roles')) ?></option>
                    <option value="Admin" <?= $filter_role === 'Admin' ? 'selected' : '' ?>><?= h(t('Admin')) ?></option>
                    <option value="Doctor" <?= $filter_role === 'Doctor' ? 'selected' : '' ?>><?= h(t('Doctor')) ?></option>
                    <option value="Receptionist" <?= $filter_role === 'Receptionist' ? 'selected' : '' ?>><?= h(t('Receptionist')) ?></option>
                    <option value="Patient" <?= $filter_role === 'Patient' ? 'selected' : '' ?>><?= h(t('Patient')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value=""><?= h(t('All Statuses')) ?></option>
                    <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>><?= h(t('Active')) ?></option>
                    <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>><?= h(t('Inactive')) ?></option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100"><?= h(t('Filter')) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th><?= h(t('Name')) ?></th>
                    <th><?= h(t('Username')) ?></th>
                    <th><?= h(t('Role')) ?></th>
                    <th><?= h(t('Status')) ?></th>
                    <th><?= h(t('Registered')) ?></th>
                    <th><?= h(t('Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <tr>
                    <td><strong><?= h($user['first_name'] . ' ' . $user['last_name']) ?></strong></td>
                    <td><?= h($user['username']) ?></td>
                    <td>
                        <span class="badge bg-blue-lt"><?= h(t($user['role'])) ?></span>
                    </td>
                    <td>
                        <?= activityBadge($user['is_active']) ?>
                    </td>
                    <td><?= h(displayDate($user['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-edit-handler="editUser" data-edit-values="<?= json_attr([$user['id'], $user['username'], $user['first_name'], $user['last_name'], $user['role']]) ?>" data-bs-toggle="modal" data-bs-target="#userModal"><?= h(t('Edit')) ?></button>
                        
                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" action="users.php" class="d-inline" data-confirm="<?= h(t('Toggle status for this user?')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?= h($user['id']) ?>">
                            <?php if($user['is_active']): ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(t('Deactivate')) ?></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-outline-success"><?= h(t('Activate')) ?></button>
                            <?php endif; ?>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($users) == 0): ?>
                <tr><td colspan="6" class="text-center text-muted"><?= h(t('No users found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="users.php">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="modal_action" value="add">
          <input type="hidden" name="id" id="user_id">
          
          <div class="modal-header">
            <h5 class="modal-title" id="userModalLabel"><?= h(t('Add User')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('First Name')) ?> *</label>
                      <input type="text" name="first_name" id="u_fname" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Last Name')) ?> *</label>
                      <input type="text" name="last_name" id="u_lname" class="form-control" required>
                  </div>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Username')) ?> *</label>
                  <input type="text" name="username" id="u_username" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Password')) ?> <small class="text-muted" id="pw_hint"><?= h(t('(Required)')) ?></small></label>
                  <input type="password" name="password" id="u_pass" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Role')) ?> *</label>
                  <select name="role" id="u_role" class="form-select" required onchange="patientRoleFields()">
                      <option value="Admin"><?= h(t('Admin')) ?></option>
                      <option value="Receptionist"><?= h(t('Receptionist')) ?></option>
                      <option value="Doctor"><?= h(t('Doctor')) ?></option>
                      <option value="Patient"><?= h(t('Patient')) ?></option>
                  </select>
              </div>
              <div class="mb-3" id="patient-card-field" hidden>
                  <label class="form-label" for="u_patient_id"><?= h(t('Patient card')) ?></label>
                  <select class="form-select" id="u_patient_id" name="patient_id"><option value=""><?= h(t('Select patient card.')) ?></option><?php foreach ($patientCards as $card): ?><option value="<?= h($card['id']) ?>"><?= h($card['first_name'].' '.$card['last_name'].' · '.displayDate($card['dob']).' · #'.$card['id']) ?></option><?php endforeach; ?></select>
                  <small class="form-hint"><?= h(t('Patient card link explanation')) ?></small>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save User')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('u_role').dataset.linkedPatient = '0';
    document.getElementById('u_patient_id').value = '';
    document.getElementById('modal_action').value = 'add';
    document.getElementById('user_id').value = '';
    document.getElementById('u_fname').value = '';
    document.getElementById('u_lname').value = '';
    document.getElementById('u_username').value = '';
    document.getElementById('u_role').value = 'Admin';
    patientRoleFields();
    document.getElementById('u_pass').required = true;
    document.getElementById('pw_hint').innerText = <?= json_encode(t('(Required)')) ?>;
    document.getElementById('userModalLabel').innerText = <?= json_encode(t('Add User')) ?>;
}
function editUser(id, username, fname, lname, role) {
    document.getElementById('u_role').dataset.linkedPatient = role === 'Patient' ? '1' : '0';
    document.getElementById('u_patient_id').value = '';
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('user_id').value = id;
    document.getElementById('u_fname').value = fname;
    document.getElementById('u_lname').value = lname;
    document.getElementById('u_username').value = username;
    document.getElementById('u_role').value = role;
    patientRoleFields();
    document.getElementById('u_pass').required = false;
    document.getElementById('pw_hint').innerText = <?= json_encode(t('(Leave blank to keep current)')) ?>;
    document.getElementById('userModalLabel').innerText = <?= json_encode(t('Edit User')) ?>;
}
function patientRoleFields() {
    const role = document.getElementById('u_role');
    const show = role.value === 'Patient' && role.dataset.linkedPatient !== '1';
    document.getElementById('patient-card-field').hidden = !show;
    document.getElementById('u_patient_id').required = show;
    document.getElementById('u_patient_id').disabled = !show;
}
</script>

<?php require_once '../includes/footer.php'; ?>
