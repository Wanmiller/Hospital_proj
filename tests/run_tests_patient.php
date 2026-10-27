<?php
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/schema.php';
require_once __DIR__ . '/../includes/patients.php';
if (!preg_match('/\Ahospital_regression_[a-z0-9_]+\z/', DB_NAME)) exit('Use an isolated hospital_regression_* database.');
$pdo = new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$checks=0; $cookies=[];
function ensurePatient($ok,$name) { global $checks; if (!$ok) throw new RuntimeException($name); $checks++; echo "PASS: $name\n"; }
function rejectPatient($fn,$name) { try { $fn(); } catch (DomainException $e) { ensurePatient(true,$name); return; } throw new RuntimeException($name); }
function patientRequest($path,$cookie,$post=null) {
    $ch=curl_init((getenv('HOSPITAL_TEST_URL') ?: 'http://127.0.0.1:18000').$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>15]);
    if ($post !== null) curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($post));
    $result=curl_exec($ch); if ($result===false) throw new RuntimeException(curl_error($ch));
    $size=curl_getinfo($ch,CURLINFO_HEADER_SIZE);
    $out=['code'=>curl_getinfo($ch,CURLINFO_HTTP_CODE),'head'=>substr($result,0,$size),'body'=>substr($result,$size)]; curl_close($ch); return $out;
}
function patientToken($page) { preg_match('/name="csrf_token" value="([^"]+)"/',$page['body'],$m); return $m[1] ?? ''; }
function patientCookie() { global $cookies; $cookie=tempnam(sys_get_temp_dir(),'patient_test_'); $cookies[]=$cookie; return $cookie; }
function patientLogin($name,$password='Patient123!') {
    $cookie=patientCookie(); $page=patientRequest('/login.php',$cookie);
    $response=patientRequest('/login.php',$cookie,['csrf_token'=>patientToken($page),'username'=>$name,'password'=>$password]);
    ensurePatient($response['code']===302,'Login '.$name); return $cookie;
}
try {
    // Reproduce the pre-Patient schema and prove the upgrade preserves rows.
    $sql=file_get_contents(__DIR__.'/../database/hospital.sql');
    $old=preg_replace('/CREATE TABLE patient_accounts \(.*?\);\s*/s','',$sql);
    $old=str_replace(", 'Patient'",'',$old);
    $pdo->exec(str_replace('hospital_db',DB_NAME,$old));
    $before=$pdo->query('SELECT * FROM appointments ORDER BY id')->fetchAll();
    upgradeHospitalSchema($pdo); upgradeHospitalSchema($pdo);
    ensurePatient($before===$pdo->query('SELECT * FROM appointments ORDER BY id')->fetchAll(),'Repeatable old-schema migration preserves appointments');
    $input=['username'=>'patient_one','password'=>'Patient123!','password_confirm'=>'Patient123!','first_name'=>'Әсел','last_name'=>'Тест','dob'=>'2000-01-01','gender'=>'Female','phone'=>'+7 700 123 4567','role'=>'Admin','patient_id'=>'1'];
    $id=registerPatient($pdo,$input); $patient=patientAccount($pdo,$id);
    ensurePatient($patient['id']!=1 && $pdo->query("SELECT role FROM users WHERE id=$id")->fetchColumn()==='Patient','Registration assigns Patient and a new card despite forged identity/role');
    ensurePatient(password_verify('Patient123!',$pdo->query("SELECT password_hash FROM users WHERE id=$id")->fetchColumn()),'Password is hashed');
    $count=$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
    try { registerPatient($pdo,$input); throw new RuntimeException('Duplicate accepted'); } catch (PDOException $e) { ensurePatient($pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn()===$count,'Duplicate username rolls back entire registration'); }
    foreach (['password'=>'short','password_confirm'=>'wrong','dob'=>'2099-01-01','phone'=>'1','username'=>'<script>','first_name'=>''] as $key=>$value) {
        rejectPatient(fn()=>registerPatient($pdo,array_replace($input,[$key=>$value])),'Registration rejects invalid '.$key);
    }
    $date=(new DateTimeImmutable(clinicToday()))->modify('+2 days')->format('Y-m-d');
    $booking=['doctor_id'=>'1','date'=>$date,'time'=>'09:00','reason'=>'Тест <script>alert(1)</script>','patient_id'=>'1'];
    bookPatientAppointment($pdo,$id,$booking);
    $app=$pdo->query('SELECT * FROM appointments ORDER BY id DESC LIMIT 1')->fetch();
    ensurePatient($app['patient_id']===$patient['id'],'Booking derives owner from account, ignores posted patient_id');
    ensurePatient(!in_array('09:00',patientAvailableSlots($pdo,1,$date),true),'Occupied time excluded');
    try { bookPatientAppointment($pdo,$id,$booking); throw new RuntimeException('Conflict accepted'); } catch (PDOException $e) { ensurePatient(($e->errorInfo[1]??0)==1062,'Unique slot rejects competing booking'); }
    foreach (['date'=>'2000-01-01','time'=>'09:15','doctor_id'=>'999999','reason'=>str_repeat('x',2001)] as $key=>$value) rejectPatient(fn()=>bookPatientAppointment($pdo,$id,array_replace($booking,[$key=>$value])),'Booking rejects invalid '.$key);
    $other=registerPatient($pdo,array_replace($input,['username'=>'patient_two']));
    rejectPatient(fn()=>cancelPatientAppointment($pdo,$other,$app['id']),'Another patient cannot cancel appointment');
    cancelPatientAppointment($pdo,$id,$app['id']);
    ensurePatient(in_array('09:00',patientAvailableSlots($pdo,1,$date),true),'Cancellation releases time');
    bookPatientAppointment($pdo,$id,$booking);
    $complete=$pdo->lastInsertId(); // query explicitly: transaction commit can reset lastInsertId
    $complete=$pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    $pdo->exec("UPDATE appointments SET status='Completed' WHERE id=$complete");
    rejectPatient(fn()=>cancelPatientAppointment($pdo,$id,$complete),'Completed appointment protected');
    $recordBooking=array_replace($booking,['time'=>'10:00']); bookPatientAppointment($pdo,$id,$recordBooking);
    $recordApp=$pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    $pdo->exec("INSERT INTO medical_records (appointment_id,diagnosis) VALUES ($recordApp,'Test record')");
    rejectPatient(fn()=>cancelPatientAppointment($pdo,$id,$recordApp),'Medical record protects scheduled appointment');
    $pdo->exec("UPDATE users SET is_active=0 WHERE id=$id");
    rejectPatient(fn()=>bookPatientAppointment($pdo,$id,array_replace($booking,['time'=>'11:00'])),'Inactive patient cannot book');
    $pdo->exec("UPDATE users SET is_active=1 WHERE id=$id");
    try { $pdo->exec("UPDATE users SET role='Admin' WHERE id=$id"); throw new RuntimeException('Role changed'); } catch (PDOException $e) { ensurePatient($e->getCode()==='23000','Database prevents linked patient role change'); }
    try { $pdo->exec("INSERT INTO patient_accounts (user_id,patient_id) VALUES (1,1)"); throw new RuntimeException('Admin linked'); } catch (PDOException $e) { ensurePatient($e->getCode()==='23000','Database rejects non-Patient account link'); }
    $anonymous=patientCookie();
    ensurePatient(patientRequest('/patient/dashboard.php',$anonymous)['code']===302,'Anonymous patient page redirects to login');
    $page=patientRequest('/register.php',$anonymous);
    $bad=patientRequest('/register.php',$anonymous,array_replace($input,['username'=>'csrf_patient','csrf_token'=>'bad']));
    ensurePatient(strpos($bad['body'],t('Invalid CSRF token.'))!==false && !$pdo->query("SELECT id FROM users WHERE username='csrf_patient'")->fetchColumn(),'Registration requires CSRF');
    $registered=patientRequest('/register.php',$anonymous,array_replace($input,['username'=>'patient_web','csrf_token'=>patientToken($page)]));
    ensurePatient($registered['code']===302 && strpos($registered['head'],'login.php')!==false,'Registration HTTP redirects to sign in');
    $cookie=patientLogin('patient_one');
    $page=patientRequest('/patient/dashboard.php',$cookie);
    ensurePatient($page['code']===200 && strpos($page['body'],'&lt;script&gt;')!==false && strpos($page['body'],'<script>alert(1)</script>')===false,'Dashboard escapes patient data');
    ensurePatient(strpos($page['body'],'patient_id=1')===false,'Dashboard has no arbitrary patient link');
    foreach (['/admin/dashboard.php','/doctor/dashboard.php','/receptionist/dashboard.php'] as $path) ensurePatient(patientRequest($path,$cookie)['code']===403,'Patient denied '.$path);
    $beforeCount=$pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn();
    $res=patientRequest('/patient/book.php',$cookie,array_replace($booking,['time'=>'11:00','csrf_token'=>'bad']));
    ensurePatient($pdo->query('SELECT COUNT(*) FROM appointments')->fetchColumn()===$beforeCount && strpos($res['body'],t('Invalid CSRF token.'))!==false,'Booking requires CSRF');
    $bookPage=patientRequest('/patient/book.php?doctor_id=1&date='.$date,$cookie);
    $res=patientRequest('/patient/book.php',$cookie,array_replace($booking,['time'=>'11:00','csrf_token'=>patientToken($bookPage)]));
    ensurePatient($res['code']===302,'Valid HTTP booking succeeds');
    $secondCookie=patientLogin('patient_two'); $secondPage=patientRequest('/patient/dashboard.php',$secondCookie);
    ensurePatient(strpos($secondPage['body'],t('No personal appointments.'))!==false,'Second account cannot see first patient appointments');
    $foreign=$pdo->query('SELECT MAX(id) FROM appointments')->fetchColumn();
    $secondBookPage=patientRequest('/patient/book.php?doctor_id=1&date='.$date,$secondCookie);
    $res=patientRequest('/patient/dashboard.php',$secondCookie,['csrf_token'=>patientToken($secondBookPage),'action'=>'cancel','id'=>$foreign]);
    ensurePatient(strpos($res['body'],t('Appointment unavailable.'))!==false && $pdo->query("SELECT status FROM appointments WHERE id=$foreign")->fetchColumn()==='Scheduled','Forged HTTP cancellation rejected');
    $admin=patientLogin('admin','password123'); $adminPage=patientRequest('/admin/users.php',$admin);
    $res=patientRequest('/admin/users.php',$admin,['csrf_token'=>patientToken($adminPage),'action'=>'add','username'=>'linked_patient','password'=>'Patient123!','first_name'=>'Existing','last_name'=>'Patient','role'=>'Patient','patient_id'=>'1']);
    ensurePatient(strpos($res['body'],t('User added successfully.'))!==false && $pdo->query('SELECT user_id FROM patient_accounts WHERE patient_id=1')->fetchColumn(),'Admin links existing card without duplicating patient');
    $linked=patientLogin('linked_patient'); ensurePatient(patientRequest('/patient/dashboard.php',$linked)['code']===200,'Linked existing patient can use personal dashboard');
    $res=patientRequest('/admin/users.php',$admin,['csrf_token'=>patientToken($adminPage),'action'=>'edit','id'=>$id,'username'=>'patient_one','first_name'=>'Әсел','last_name'=>'Тест','role'=>'Doctor']);
    ensurePatient(strpos($res['body'],t('Patient role management rule'))!==false,'Admin cannot change linked patient role');
    $pdo->exec("UPDATE users SET is_active=0 WHERE id=$id");
    ensurePatient(patientRequest('/patient/dashboard.php',$cookie)['code']===302,'Deactivation invalidates existing patient session');
    $pdo->exec("UPDATE users SET is_active=1 WHERE id=$id");
    $staff=patientLogin('reception','password123');
    ensurePatient(patientRequest('/patient/book.php',$staff)['code']===403,'Staff cannot use patient endpoints');
    echo "All $checks patient checks passed.\n";
} finally { foreach ($cookies as $cookie) if (is_file($cookie)) unlink($cookie); }
