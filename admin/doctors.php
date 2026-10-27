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
            $user_id = $_POST['user_id'] ?? null;
            $username = trim($_POST['username'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $password = $_POST['password'] ?? '';
            
            $department_id = $_POST['department_id'] ?? null;
            if ($department_id === '') $department_id = null;
            
            $specialization = trim($_POST['new_specialization'] ?? ($_POST['specialization'] ?? ''));
            $specialization_id = $_POST['specialization_id'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($username) || empty($first_name) || empty($last_name)) {
                $msg = t('Username, First Name, and Last Name are required.');
                $msgType = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    if ($specialization !== '') {
                        $specialization_id = specializationId($pdo, $specialization);
                    } elseif ($specialization_id !== '') {
                        $checkSpecialty = $pdo->prepare('SELECT id FROM specializations WHERE id=?');
                        $checkSpecialty->execute([$specialization_id]);
                        if (!$checkSpecialty->fetchColumn()) throw new DomainException(t('Invalid specialization.'));
                    } else {
                        $specialization_id = null;
                    }
                    
                    if ($action === 'edit' && $user_id) {
                        $owner = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='Doctor' FOR UPDATE");
                        $owner->execute([$user_id]);
                        if (!$owner->fetchColumn()) throw new DomainException(t('Doctor account not found.'));
                        // Update users table
                        $sql = "UPDATE users SET username=?, first_name=?, last_name=? ";
                        $params = [$username, $first_name, $last_name];
                        
                        if (!empty($password)) {
                            $sql .= ", password_hash=? ";
                            $params[] = password_hash($password, PASSWORD_DEFAULT);
                        }
                        $sql .= "WHERE id=? AND role='Doctor'";
                        $params[] = $user_id;
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        
                        // Update doctors table
                        $stmt = $pdo->prepare("UPDATE doctors SET department_id=?, specialization_id=?, phone=? WHERE user_id=?");
                        $stmt->execute([$department_id, $specialization_id, $phone, $user_id]);
                        
                        $msg = t('Doctor updated successfully.');
                    } else {
                        if (empty($password)) {
                            throw new Exception(t('Password is required for new doctors.'));
                        }
                        // Insert users table
                        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, last_name) VALUES (?, ?, 'Doctor', ?, ?)");
                        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $first_name, $last_name]);
                        
                        $new_user_id = $pdo->lastInsertId();
                        
                        // Insert doctors table
                        $stmt = $pdo->prepare("INSERT INTO doctors (user_id, department_id, specialization_id, phone) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$new_user_id, $department_id, $specialization_id, $phone]);
                        
                        $msg = t('Doctor added successfully.');
                    }
                    
                    $pdo->commit();
                } catch(Exception $e) {
                    $pdo->rollBack();
                    if ($e instanceof PDOException) {
                        $msg = ($e->errorInfo[1] ?? null) == 1062 ? t('Username already exists.') : t('Database error.');
                    } else {
                        $msg = $e->getMessage();
                    }
                    $msgType = 'danger';
                }
            }
        } elseif ($action === 'toggle_active') {
            $user_id = $_POST['user_id'] ?? null;
            if ($user_id) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'Doctor'");
                    $stmt->execute([$user_id]);
                    $msg = t('Doctor status updated.');
                } catch(PDOException $e) {
                    $msg = t('Database error.');
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Fetch departments for the dropdown
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC")->fetchAll();
$specializations = $pdo->query('SELECT id, name FROM specializations ORDER BY name')->fetchAll();

// Handle Filters & Search
$search = trim($_GET['search'] ?? '');
$filter_dept = $_GET['department'] ?? '';
$filter_spec = trim($_GET['specialization'] ?? '');
$filter_status = $_GET['status'] ?? '';

$query = "SELECT u.id AS user_id, u.username, u.first_name, u.last_name, u.is_active, 
                 d.department_id, d.specialization_id, sp.name AS specialization, d.phone, dept.name AS department_name 
          FROM users u 
          JOIN doctors d ON u.id = d.user_id 
          LEFT JOIN specializations sp ON sp.id = d.specialization_id
          LEFT JOIN departments dept ON d.department_id = dept.id 
          WHERE u.role = 'Doctor'";
$params = [];

if ($search !== '') {
    $query .= " AND (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_dept !== '') {
    $query .= " AND d.department_id = ?";
    $params[] = $filter_dept;
}
if ($filter_spec !== '') {
    $query .= " AND sp.name LIKE ?";
    $params[] = "%$filter_spec%";
}
if ($filter_status === '1' || $filter_status === '0') {
    $query .= " AND u.is_active = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY u.last_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('Manage Doctors')) ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#doctorModal" onclick="resetForm()"><?= h(t('Add Doctor')) ?></button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= h(t($msg)) ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="doctors.php" class="row g-3 align-items-center">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="<?= h(t('Search name/username')) ?>" value="<?= h($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="department" class="form-select">
                    <option value=""><?= h(t('All Departments')) ?></option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filter_dept == $dept['id'] ? 'selected' : '' ?>><?= h($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="specialization" class="form-control" placeholder="<?= h(t('Specialization')) ?>" value="<?= h($filter_spec) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value=""><?= h(t('All Statuses')) ?></option>
                    <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>><?= h(t('Active')) ?></option>
                    <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>><?= h(t('Inactive')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-secondary"><?= h(t('Filter')) ?></button>
                <a href="doctors.php" class="btn btn-outline-secondary"><?= h(t('Clear')) ?></a>
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
                    <th><?= h(t('Department')) ?></th>
                    <th><?= h(t('Specialization')) ?></th>
                    <th><?= h(t('Phone')) ?></th>
                    <th><?= h(t('Status')) ?></th>
                    <th><?= h(t('Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($doctors as $doc): ?>
                <tr>
                    <td><strong><?= h(t('Dr.')) ?> <?= h($doc['first_name'] . ' ' . $doc['last_name']) ?></strong></td>
                    <td><?= h($doc['username']) ?></td>
                    <td><?= h($doc['department_name'] ?? t('Unassigned')) ?></td>
                    <td><?= h($doc['specialization'] ?? t('Unassigned')) ?></td>
                    <td><?= h($doc['phone']) ?></td>
                    <td>
                        <?= activityBadge($doc['is_active']) ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-edit-handler="editDoctor" data-edit-values="<?= json_attr([$doc['user_id'], $doc['username'], $doc['first_name'], $doc['last_name'], $doc['department_id'] ?? '', $doc['specialization_id'] ?? '', $doc['phone']]) ?>" data-bs-toggle="modal" data-bs-target="#doctorModal"><?= h(t('Edit')) ?></button>
                        
                        <form method="POST" action="doctors.php" class="d-inline" data-confirm="<?= h(t('Toggle status for this doctor?')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= h($doc['user_id']) ?>">
                            <?php if($doc['is_active']): ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><?= h(t('Deactivate')) ?></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-outline-success"><?= h(t('Activate')) ?></button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($doctors) == 0): ?>
                <tr><td colspan="7" class="text-center text-muted"><?= h(t('No doctors found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="doctorModal" tabindex="-1" aria-labelledby="doctorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="doctors.php">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="modal_action" value="add">
          <input type="hidden" name="user_id" id="doc_user_id">
          
          <div class="modal-header">
            <h5 class="modal-title" id="doctorModalLabel"><?= h(t('Add Doctor')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <h6 class="mb-3 text-primary"><?= h(t('User Information')) ?></h6>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('First Name')) ?> *</label>
                      <input type="text" name="first_name" id="doc_fname" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Last Name')) ?> *</label>
                      <input type="text" name="last_name" id="doc_lname" class="form-control" required>
                  </div>
              </div>
              <div class="row">
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Username')) ?> *</label>
                      <input type="text" name="username" id="doc_username" class="form-control" required>
                  </div>
                  <div class="col-md-6 mb-3">
                      <label class="form-label"><?= h(t('Password')) ?> <small class="text-muted" id="pw_hint"><?= h(t('(Required)')) ?></small></label>
                      <input type="password" name="password" id="doc_pass" class="form-control" required>
                  </div>
              </div>
              
              <h6 class="mb-3 mt-4 text-primary"><?= h(t('Professional Information')) ?></h6>
              <div class="row">
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Department')) ?></label>
                      <select name="department_id" id="doc_dept" class="form-select">
                          <option value=""><?= h(t('Unassigned')) ?></option>
                          <?php foreach($departments as $dept): ?>
                              <option value="<?= h($dept['id']) ?>"><?= h($dept['name']) ?></option>
                          <?php endforeach; ?>
                      </select>
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Specialization')) ?></label>
                      <select name="specialization_id" id="doc_spec" class="form-select">
                          <option value=""><?= h(t('Unassigned')) ?></option>
                          <?php foreach ($specializations as $specialty): ?>
                          <option value="<?= h($specialty['id']) ?>"><?= h($specialty['name']) ?></option>
                          <?php endforeach; ?>
                      </select>
                      <label for="doc_new_spec" class="form-label mt-2"><?= h(t('New specialization (optional)')) ?></label>
                      <input type="text" name="new_specialization" id="doc_new_spec" class="form-control" maxlength="100" placeholder="<?= h(t('Or add a new specialization')) ?>">
                  </div>
                  <div class="col-md-4 mb-3">
                      <label class="form-label"><?= h(t('Phone')) ?></label>
                      <input type="text" name="phone" id="doc_phone" class="form-control">
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save Doctor')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('doc_user_id').value = '';
    document.getElementById('doc_fname').value = '';
    document.getElementById('doc_lname').value = '';
    document.getElementById('doc_username').value = '';
    document.getElementById('doc_pass').required = true;
    document.getElementById('pw_hint').innerText = <?= json_encode(t('(Required)')) ?>;
    
    document.getElementById('doc_dept').value = '';
    document.getElementById('doc_spec').value = '';
    document.getElementById('doc_new_spec').value = '';
    document.getElementById('doc_phone').value = '';
    
    document.getElementById('doctorModalLabel').innerText = <?= json_encode(t('Add Doctor')) ?>;
}
function editDoctor(id, username, fname, lname, dept_id, spec, phone) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('doc_user_id').value = id;
    document.getElementById('doc_fname').value = fname;
    document.getElementById('doc_lname').value = lname;
    document.getElementById('doc_username').value = username;
    document.getElementById('doc_pass').required = false;
    document.getElementById('pw_hint').innerText = <?= json_encode(t('(Leave blank to keep current)')) ?>;
    
    document.getElementById('doc_dept').value = dept_id;
    document.getElementById('doc_spec').value = spec;
    document.getElementById('doc_new_spec').value = '';
    document.getElementById('doc_phone').value = phone;
    
    document.getElementById('doctorModalLabel').innerText = <?= json_encode(t('Edit Doctor')) ?>;
}
</script>

<?php require_once '../includes/footer.php'; ?>
