<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('Admin');

$msg = '';
$msgType = 'success';

// Handle CSRF protected POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg = t('Invalid CSRF token.');
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $msg = t('Department name is required.');
                $msgType = 'danger';
            } else {
                try {
                    if ($action === 'edit' && $id) {
                        $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $id]);
                        $msg = t('Department updated successfully.');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
                        $stmt->execute([$name, $description]);
                        $msg = t('Department added successfully.');
                    }
                } catch(PDOException $e) {
                    if ($e->getCode() == 23000) { // Integrity constraint violation (UNIQUE)
                        $msg = t('Department name already exists.');
                    } else {
                        $msg = t('Database error occurred.');
                    }
                    $msgType = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? null;
            if ($id) {
                try {
                    $check = $pdo->prepare("SELECT COUNT(*) FROM doctors WHERE department_id = ?");
                    $check->execute([$id]);
                    if ($check->fetchColumn() > 0) {
                        $msg = t('This department cannot be deleted because doctors are assigned to it.');
                        $msgType = 'danger';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                        $stmt->execute([$id]);
                        $msg = t('Department deleted successfully.');
                    }
                } catch(PDOException $e) {
                    $msg = t('Database error occurred.');
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Handle Search
$search = trim($_GET['search'] ?? '');
$query = "SELECT d.id, d.name, d.description, COUNT(doc.id) AS total_doctors 
          FROM departments d 
          LEFT JOIN doctors doc ON d.id = doc.department_id";
$params = [];

if ($search !== '') {
    $query .= " WHERE d.name LIKE ?";
    $params[] = "%$search%";
}

$query .= " GROUP BY d.id ORDER BY d.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll();

$path_prefix = '../';
require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><?= h(t('Manage Departments')) ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="resetForm()"><?= h(t('Add Department')) ?></button>
</div>

<?php if($msg): ?>
    <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
        <?= h(t($msg)) ?>
        <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="departments.php" class="row g-3 align-items-center">
            <div class="col-auto">
                <input type="text" name="search" class="form-control" placeholder="<?= h(t('Search by name')) ?>" value="<?= h($search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-secondary"><?= h(t('Search')) ?></button>
            </div>
            <?php if($search): ?>
                <div class="col-auto">
                    <a href="departments.php" class="btn btn-outline-secondary"><?= h(t('Clear')) ?></a>
                </div>
            <?php endif; ?>
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
                    <th><?= h(t('Description')) ?></th>
                    <th><?= h(t('Doctors')) ?></th>
                    <th><?= h(t('Actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($departments as $dept): ?>
                <tr>
                    <td><?= h($dept['id']) ?></td>
                    <td><strong><?= h($dept['name']) ?></strong></td>
                    <td><?= h($dept['description']) ?></td>
                    <td><span class="badge bg-blue-lt"><?= h($dept['total_doctors']) ?></span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-edit-handler="editDept" data-edit-values="<?= json_attr([$dept['id'], $dept['name'], $dept['description']]) ?>" data-bs-toggle="modal" data-bs-target="#deptModal"><?= h(t('Edit')) ?></button>
                        <form method="POST" action="departments.php" class="d-inline" data-confirm="<?= h(t('Are you sure you want to delete this department?')) ?>">
                            <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= h($dept['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= h(t('Delete')) ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($departments) == 0): ?>
                <tr><td colspan="5" class="text-center text-muted"><?= h(t('No departments found.')) ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="deptModal" tabindex="-1" aria-labelledby="deptModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="departments.php">
          <input type="hidden" name="csrf_token" value="<?= h(generate_csrf_token()) ?>">
          <input type="hidden" name="action" id="modal_action" value="add">
          <input type="hidden" name="id" id="dept_id">
          
          <div class="modal-header">
            <h5 class="modal-title" id="deptModalLabel"><?= h(t('Add Department')) ?></h5>
            <button type="button" class="btn-close" aria-label="<?= h(t('Close')) ?>" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Name')) ?> *</label>
                  <input type="text" name="name" id="dept_name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label"><?= h(t('Description')) ?></label>
                  <textarea name="description" id="dept_desc" class="form-control" rows="3"></textarea>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= h(t('Cancel')) ?></button>
            <button type="submit" class="btn btn-primary"><?= h(t('Save Department')) ?></button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function resetForm() {
    document.getElementById('modal_action').value = 'add';
    document.getElementById('dept_id').value = '';
    document.getElementById('dept_name').value = '';
    document.getElementById('dept_desc').value = '';
    document.getElementById('deptModalLabel').innerText = <?= json_encode(t('Add Department')) ?>;
}
function editDept(id, name, desc) {
    document.getElementById('modal_action').value = 'edit';
    document.getElementById('dept_id').value = id;
    document.getElementById('dept_name').value = name;
    document.getElementById('dept_desc').value = desc;
    document.getElementById('deptModalLabel').innerText = <?= json_encode(t('Edit Department')) ?>;
}
</script>

<?php require_once '../includes/footer.php'; ?>
