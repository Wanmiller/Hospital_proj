<?php
// Run only from the installer/CLI upgrade, never from a page request.
function upgradePatientSchema(PDO $pdo) {
    $type = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch(PDO::FETCH_ASSOC)['Type'];
    $doctorType = $pdo->query("SHOW COLUMNS FROM doctors LIKE 'user_role'")->fetch(PDO::FETCH_ASSOC)['Type'];
    if (strpos($type, "'Patient'") === false || strpos($doctorType, "'Patient'") === false) {
        $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='doctors' AND CONSTRAINT_NAME='fk_doctor_user_role'")->fetchColumn();
        if ($fk) $pdo->exec('ALTER TABLE doctors DROP FOREIGN KEY fk_doctor_user_role');
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('Admin','Doctor','Receptionist','Patient') NOT NULL");
        $pdo->exec("ALTER TABLE doctors MODIFY user_role ENUM('Admin','Doctor','Receptionist','Patient') GENERATED ALWAYS AS ('Doctor') STORED");
    }
    $fk = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='doctors' AND CONSTRAINT_NAME='fk_doctor_user_role'")->fetchColumn();
    if (!$fk) $pdo->exec('ALTER TABLE doctors ADD CONSTRAINT fk_doctor_user_role FOREIGN KEY (user_id,user_role) REFERENCES users(id,role) ON DELETE RESTRICT ON UPDATE RESTRICT');
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_accounts (
        user_id INT PRIMARY KEY,
        patient_id INT NOT NULL UNIQUE,
        user_role ENUM('Admin','Doctor','Receptionist','Patient') GENERATED ALWAYS AS ('Patient') STORED,
        CONSTRAINT fk_patient_account_user FOREIGN KEY (user_id,user_role) REFERENCES users(id,role) ON DELETE RESTRICT ON UPDATE RESTRICT,
        CONSTRAINT fk_patient_account_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT ON UPDATE RESTRICT
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
