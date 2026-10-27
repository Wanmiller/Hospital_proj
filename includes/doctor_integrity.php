<?php
function specializationId(PDO $pdo, $name) {
    $name = preg_replace('/\s+/u', ' ', trim($name));
    if ($name === '') return null;
    if (mb_strlen($name, 'UTF-8') > 100) throw new DomainException('Specialization must be at most 100 characters.');
    $stmt = $pdo->prepare('INSERT INTO specializations (name) VALUES (?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    $stmt->execute([$name]);
    return $pdo->lastInsertId();
}

function upgradeDoctorIntegrity(PDO $pdo) {
    $invalid = $pdo->query("SELECT COUNT(*) FROM doctors d LEFT JOIN users u ON u.id=d.user_id WHERE u.id IS NULL OR u.role<>'Doctor'")->fetchColumn();
    if ($invalid) throw new RuntimeException('Invalid doctor accounts exist. Run php database/repair_doctors.php first.');
    $pdo->exec('CREATE TABLE IF NOT EXISTS specializations (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $columns = $pdo->query('SHOW COLUMNS FROM doctors')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('specialization_id', $columns, true)) $pdo->exec('ALTER TABLE doctors ADD COLUMN specialization_id INT NULL');
    if (in_array('specialization', $columns, true)) {
        $rows = $pdo->query('SELECT id, specialization FROM doctors')->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('UPDATE doctors SET specialization_id=? WHERE id=?');
        foreach ($rows as $row) $stmt->execute([specializationId($pdo, $row['specialization'] ?? ''), $row['id']]);
        $pdo->exec('ALTER TABLE doctors DROP COLUMN specialization');
    }
    $has = function ($table, $constraint) use ($pdo) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
        $stmt->execute([$table, $constraint]);
        return (bool)$stmt->fetchColumn();
    };
    if (!$has('users', 'unique_user_role')) $pdo->exec('ALTER TABLE users ADD UNIQUE KEY unique_user_role (id, role)');
    if (!in_array('user_role', $columns, true)) {
        $pdo->exec("ALTER TABLE doctors ADD COLUMN user_role ENUM('Admin','Doctor','Receptionist','Patient') GENERATED ALWAYS AS ('Doctor') STORED");
    }
    $nullable = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='doctors' AND COLUMN_NAME='user_id'")->fetchColumn();
    if ($nullable === 'YES' || !$has('doctors', 'fk_doctor_user_role')) {
        $old = $pdo->query("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='doctors' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
        $clauses = [];
        foreach ($old as $fk) $clauses[] = 'DROP FOREIGN KEY `' . str_replace('`', '``', $fk) . '`';
        $clauses[] = 'MODIFY COLUMN user_id INT NOT NULL';
        $clauses[] = 'ADD CONSTRAINT fk_doctor_user_role FOREIGN KEY (user_id,user_role) REFERENCES users(id,role) ON DELETE RESTRICT ON UPDATE RESTRICT';
        $pdo->exec('ALTER TABLE doctors ' . implode(', ', $clauses));
    }
    if (!$has('doctors', 'fk_doctor_specialization')) {
        $pdo->exec('ALTER TABLE doctors ADD CONSTRAINT fk_doctor_specialization FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT');
    }
    if (!$has('appointments', 'fk_appointment_doctor')) {
        $pdo->exec('ALTER TABLE appointments ADD CONSTRAINT fk_appointment_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT');
    }
    $old = $pdo->query("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='appointments' AND COLUMN_NAME='doctor_id' AND REFERENCED_TABLE_NAME='doctors' AND CONSTRAINT_NAME<>'fk_appointment_doctor'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($old as $fk) $pdo->exec('ALTER TABLE appointments DROP FOREIGN KEY `' . str_replace('`', '``', $fk) . '`');
}

function repairDoctorAccounts(PDO $pdo) {
    $pdo->beginTransaction();
    try {
        $rows = $pdo->query("SELECT d.id FROM doctors d LEFT JOIN users u ON u.id=d.user_id WHERE u.id IS NULL OR u.role<>'Doctor' FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        $fixed = [];
        foreach ($rows as $row) {
            $base = 'recovered_doctor_' . $row['id'];
            $username = $base;
            $check = $pdo->prepare('SELECT id FROM users WHERE username=?');
            for ($suffix = 1; ; $suffix++) {
                $check->execute([$username]);
                if (!$check->fetchColumn()) break;
                $username = $base . '_' . $suffix;
            }
            // No guessed identity or reusable password; an admin must finish setup.
            $stmt = $pdo->prepare("INSERT INTO users (username,password_hash,role,first_name,last_name,is_active) VALUES (?,?,'Doctor','Recovered',?,0)");
            $stmt->execute([$username, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), 'Doctor ' . $row['id']]);
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare('UPDATE doctors SET user_id=? WHERE id=?');
            $stmt->execute([$userId, $row['id']]);
            $fixed[$row['id']] = $username;
        }
        $pdo->commit();
        return $fixed;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Caller holds the transaction; lock the user to serialize role changes/booking.
function prepareUserRoleChange(PDO $pdo, $userId, $newRole) {
    $user = $pdo->prepare('SELECT role FROM users WHERE id=? FOR UPDATE');
    $user->execute([$userId]);
    if ($user->fetchColumn() === false) throw new DomainException('User not found.');
    if ($newRole === 'Doctor') return;
    $doctor = $pdo->prepare('SELECT id FROM doctors WHERE user_id=? FOR UPDATE');
    $doctor->execute([$userId]);
    $doctorId = $doctor->fetchColumn();
    if (!$doctorId) return;
    $history = $pdo->prepare('SELECT id FROM appointments WHERE doctor_id=? LIMIT 1');
    $history->execute([$doctorId]);
    if ($history->fetchColumn()) throw new DomainException('Cannot change the role of a doctor with appointment history. Deactivate the account instead.');
    $delete = $pdo->prepare('DELETE FROM doctors WHERE id=?');
    $delete->execute([$doctorId]);
}
