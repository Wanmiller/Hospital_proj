<?php
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
if (strpos(DB_NAME, 'hospital_regression_') !== 0) {
    fwrite(STDERR, "Use an isolated hospital_regression_* database.\n");
    exit(1);
}
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec(str_replace('hospital_db', DB_NAME, file_get_contents(__DIR__ . '/../database/hospital.sql')));
$stmt = $pdo->prepare('INSERT INTO patients (first_name,last_name,dob,gender,phone,email,address) VALUES (?,?,?,?,?,?,?)');
$first = "Әли 'Оқу'";
$last = 'Қанат "Сынақ" \\';
$address = '"><img src=x onerror="window.testInjection=true"> Қазақша \\ мекенжай';
$stmt->execute([$first,$last,'1995-02-03','Male','DEMO-01','tabler.one@example.test',$address]);
$patient1 = $pdo->lastInsertId();
$stmt->execute(['Аружан','Сынақ','2000-04-09','Female','DEMO-02','tabler.two@example.test','Оқу мекенжайы']);
$patient2 = $pdo->lastInsertId();
$name = "Оқу бөлімшесі 'A' \\ \"Б\"";
$stmt = $pdo->prepare('INSERT INTO departments (name,description) VALUES (?,?)');
$stmt->execute([$name,$address]);
$department = $pdo->lastInsertId();
$day = clinicToday();
$reason = "Оқу 'қабылдауы' \\ \"себеп\" <script>window.testInjection=true</script>";
$stmt = $pdo->prepare('INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (?,?,?,?,?,?)');
$ids = [];
foreach ([[$patient1,1,'09:00','Scheduled'],[$patient2,1,'10:30','Scheduled'],[$patient2,2,'11:00','Scheduled'],[$patient2,1,'13:00','Completed'],[$patient2,1,'14:00','Cancelled']] as [$pat,$doc,$time,$status]) {
    $stmt->execute([$pat,$doc,$day,$time,$reason,$status]);
    $ids[] = $pdo->lastInsertId();
}
$stmt = $pdo->prepare('INSERT INTO medical_records (appointment_id,diagnosis,treatment) VALUES (?,?,?)');
$stmt->execute([$ids[3],'Оқу диагнозы','Оқу жазбасы']);
$record = $pdo->lastInsertId();
$medicine = "Дәрі 'оқу' \\ \"А\"";
$stmt = $pdo->prepare('INSERT INTO prescriptions (medical_record_id,medicine_name,dosage,frequency,duration) VALUES (?,?,?,?,?)');
$stmt->execute([$record,$medicine,'Оқу дозасы','Оқу жиілігі','Оқу ұзақтығы']);
$rx = $pdo->lastInsertId();
echo json_encode(compact('day','patient1','patient2','ids','department','name','address','first','last','reason','medicine','rx'), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
