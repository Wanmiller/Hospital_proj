"""UI acceptance checks. Requires a test PHP server with matching HOSPITAL_DB_*.

The guarded fixture script resets only a hospital_regression_* database.
"""
from pathlib import Path
from datetime import date, timedelta
import json
import os
import subprocess
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
BASE = os.environ.get('HOSPITAL_TEST_URL', 'http://127.0.0.1:18000').rstrip('/')
PHP = os.environ.get('HOSPITAL_PHP', r'D:\xampp\php\php.exe')
if not os.environ.get('HOSPITAL_DB_NAME', '').startswith('hospital_regression_'):
    raise SystemExit('Set HOSPITAL_DB_NAME to an isolated hospital_regression_* database.')
fixture = subprocess.run([PHP, str(ROOT/'tests/tabler_fixtures.php')], capture_output=True, encoding='utf-8', check=True)
F = json.loads(fixture.stdout)
KK = json.loads((ROOT/'includes/lang/kk.json').read_text(encoding='utf-8'))
SHOTS = ROOT/'docs/tabler-preview'
SHOTS.mkdir(parents=True, exist_ok=True)
checks = []

def check(condition, label):
    if not condition:
        raise AssertionError(label)
    checks.append(label)
    print('PASS:', label, flush=True)

with sync_playwright() as p:
    browser = p.chromium.launch()
    page = browser.new_page(viewport={'width':1366, 'height':960}, locale='kk-KZ')
    errors, requests, failed = [], [], []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.on('request', lambda r: requests.append(r.url))
    page.on('response', lambda r: failed.append(r.url) if r.status >= 400 else None)

    def visit(path):
        response = page.goto(BASE+path, wait_until='networkidle')
        assert response.status == 200, (path, response.status)
        assert page.locator('html').get_attribute('lang') == 'kk'
        assert page.locator('script[src$="tabler.min.js"]').count() == 1
        assert page.locator('script[src*="bootstrap"]').count() == 0

    def login(user, role):
        visit('/login.php')
        page.fill('#username', user)
        page.fill('#password', 'password123')
        page.click('button[type=submit]')
        page.wait_for_url('**/'+role+'/dashboard.php')

    def open_modal(opener):
        page.evaluate("window._shown=false; document.addEventListener('shown.bs.modal',()=>window._shown=true,{once:true})")
        opener.click()
        page.wait_for_function('window._shown')
        assert page.locator('.modal-backdrop').count() == 1
        assert page.locator('.modal.show').count() == 1

    def close_modal(opener):
        page.keyboard.press('Tab')
        assert page.evaluate("document.querySelector('.modal.show').contains(document.activeElement)")
        page.evaluate("window._hidden=false; document.addEventListener('hidden.bs.modal',()=>window._hidden=true,{once:true})")
        page.keyboard.press('Escape')
        page.wait_for_function('window._hidden')
        assert page.locator('.modal-backdrop').count() == 0
        assert opener.evaluate('e => document.activeElement === e')

    def edit_button(handler, object_id):
        matches = page.locator('[data-edit-handler="'+handler+'"]')
        for i in range(matches.count()):
            button = matches.nth(i)
            if str(json.loads(button.get_attribute('data-edit-values'))[0]) == str(object_id):
                return button
        raise AssertionError((handler, object_id))

    visit('/login.php')
    page.click('button[type=submit]')
    check(page.locator('#username').evaluate('e=>e.validationMessage') == KK['This field is required.'], 'Native required-field feedback is Kazakh')
    page.fill('#username', 'admin')
    check(page.locator('#username').evaluate('e=>e.validity.customError') is False, 'Typing clears stale native validation errors')
    page.fill('#username', '')
    visit('/login.php')
    page.screenshot(path=str(SHOTS/'login.png'))
    check(page.locator('.auth-story').count() == 0, 'Compact Kazakh sign-in without promotional content')
    login('admin', 'admin')
    check(page.locator('.day-stats .card').count() == 3, 'Admin has three indicators')
    page.screenshot(path=str(SHOTS/'admin.png'))
    for path in ['departments.php','users.php','doctors.php']:
        visit('/admin/'+path)
        opener = page.locator('[data-bs-toggle=modal]').first
        open_modal(opener)
        if path == 'departments.php':
            page.get_by_role('button', name=KK['Save Department'], exact=True).click()
            check(page.locator('#dept_name').evaluate('e=>e.validity.customError'), 'Empty modal form shows Kazakh native validation')
        close_modal(opener)
        check(True, path+' add dialog: one backdrop, keyboard and focus return')
    visit('/admin/departments.php')
    opener = edit_button('editDept', F['department'])
    open_modal(opener)
    check(page.locator('#dept_name').evaluate('e=>e.validity.valid'), 'Opening edit clears validation left by the add form')
    check(page.input_value('#dept_name') == F['name'] and page.input_value('#dept_desc') == F['address'], 'Department quotes and markup round-trip safely')
    close_modal(opener)
    visit('/admin/users.php')
    opener = edit_button('editUser', 3)
    open_modal(opener)
    check(page.input_value('#u_role') == 'Doctor', 'User role retains its original POST value')
    close_modal(opener)
    visit('/admin/doctors.php')
    opener = edit_button('editDoctor', 3)
    open_modal(opener)
    page.select_option('#doc_spec', '2')
    page.get_by_role('button', name=KK['Save Doctor'], exact=True).click()
    page.wait_for_load_state('networkidle')
    opener = edit_button('editDoctor', 3)
    check(str(json.loads(opener.get_attribute('data-edit-values'))[5]) == '2', 'Doctor specialization saved through Tabler form')
    visit('/admin/reports.php')
    check(page.locator('main table').count() >= 6, 'Existing report tables retained')
    page.screenshot(path=str(SHOTS/'reports.png'), full_page=True)
    page.goto(BASE+'/logout.php')

    login('reception', 'receptionist')
    check(page.locator('tbody tr[data-appointment-id]').count() == 5, 'Reception day list defaults to clinic today')
    check(page.locator('[data-count=Total]').inner_text() == '5' and page.locator('[data-count=Scheduled]').inner_text() == '3', 'Counters match the selected rows')
    times = page.locator('tbody tr[data-appointment-id] td:first-child').all_inner_texts()
    check(times == sorted(times), 'Day appointments sorted ascending by time')
    page.screenshot(path=str(SHOTS/'reception-day.png'))
    visit('/receptionist/dashboard.php?date='+F['day']+'&doctor_id=2')
    check(page.locator('tbody tr[data-appointment-id]').count() == 1 and page.locator('[data-count=Total]').inner_text() == '1', 'Reception doctor filter and counters agree')
    visit('/receptionist/dashboard.php?date='+F['day']+'&status=Completed')
    check(page.locator('tbody tr[data-appointment-id]').count() == 1 and page.locator('[data-count=Completed]').inner_text() == '1', 'Status filter applied to rows and counters')
    visit('/receptionist/dashboard.php?date='+F['day']+'&patient_name='+__import__('urllib.parse',fromlist=['quote']).quote('Әли'))
    check(page.locator('tbody tr[data-appointment-id]').count() == 1, 'Kazakh patient search works')
    page.get_by_role('link', name=KK['Next day'], exact=True).click()
    check(page.input_value('#day-date') == (date.fromisoformat(F['day'])+timedelta(days=1)).isoformat(), 'Next day navigation preserves the selected search')
    check(page.locator('.empty-state').inner_text() == KK['No appointments on this day.'], 'Empty day has a useful Kazakh state')
    visit('/receptionist/dashboard.php?date=invalid')
    check(KK['Invalid date.'] in page.locator('[role=alert]').inner_text(), 'Invalid calendar date rejected')
    visit('/receptionist/dashboard.php?date=2031-02-03')
    page.click('#new-appointment')
    page.wait_for_url('**/appointments.php?*')
    page.wait_for_function("document.querySelector('.modal.show') && document.querySelector('.modal.show').contains(document.activeElement)")
    check(page.input_value('#a_date') == '2031-02-03' and page.locator('.modal-backdrop').count() == 1, 'Quick booking opens once with selected date')
    page.select_option('#a_patient', str(F['patient1']))
    page.select_option('#a_doctor', '1')
    page.fill('#a_time', '10:45')
    page.fill('#a_reason', F['reason'])
    page.get_by_role('button', name=KK['Save Appointment'], exact=True).click()
    page.wait_for_load_state('networkidle')
    check(KK['Appointment added successfully.'] in page.locator('[role=alert]').inner_text(), 'Quick booking saved through existing POST handler')
    visit('/receptionist/appointments.php?date=2031-02-03&create=1')
    page.wait_for_function("document.querySelector('.modal.show') && document.querySelector('.modal.show').contains(document.activeElement)")
    page.select_option('#a_patient', str(F['patient1']))
    page.select_option('#a_doctor', '1')
    page.fill('#a_time', '10:45')
    page.get_by_role('button', name=KK['Save Appointment'], exact=True).click()
    page.wait_for_load_state('networkidle')
    check(KK['This doctor already has an appointment at this date and time.'] in page.locator('[role=alert]').inner_text(), 'Duplicate booking displays Kazakh error')
    visit('/receptionist/appointments.php?date='+F['day'])
    opener = edit_button('editApp', F['ids'][0])
    open_modal(opener)
    check(page.input_value('#a_reason') == F['reason'], 'Appointment reason with quotes and script text is safe')
    close_modal(opener)
    confirm_messages = []
    page.once('dialog', lambda dialog: (confirm_messages.append(dialog.message), dialog.accept()))
    cancel_cell = edit_button('editApp', F['ids'][1]).locator('..')
    cancel_cell.locator('form[data-confirm] button').click()
    page.wait_for_load_state('networkidle')
    check(confirm_messages == [KK['Cancel this appointment?']] and KK['Appointment cancelled successfully.'] in page.locator('[role=alert]').inner_text(), 'Appointment cancellation asks for Kazakh confirmation and saves')
    edit_values = page.locator('[data-edit-handler="editApp"]').evaluate_all('els=>els.map(e=>e.dataset.editValues)')
    check(all(str(json.loads(raw)[0]) != str(F['ids'][3]) for raw in edit_values), 'Completed appointment has no edit action')
    visit('/receptionist/patients.php')
    opener = edit_button('editPatient', F['patient1'])
    open_modal(opener)
    check(page.input_value('#p_fname') == F['first'] and page.input_value('#p_lname') == F['last'] and page.input_value('#p_address') == F['address'], 'Patient names, slashes and markup round-trip safely')
    close_modal(opener)
    opener = page.locator('[data-bs-toggle=modal]:not([data-edit-handler])').first
    open_modal(opener)
    check(page.input_value('#p_fname') == '' and page.input_value('#modal_action') == 'add', 'Patient add form clears previous edit values')
    close_modal(opener)
    visit('/receptionist/patient_details.php?id='+str(F['patient1']))
    page.screenshot(path=str(SHOTS/'patient.png'), full_page=True)
    page.goto(BASE+'/logout.php')

    login('doctor_smith','doctor')
    check(page.locator('tbody tr[data-appointment-id]').count() == 4, 'Doctor day list is restricted to own appointments')
    page.screenshot(path=str(SHOTS/'doctor-day.png'))
    visit('/doctor/appointment_details.php?id='+str(F['ids'][3]))
    opener = edit_button('editRx', F['rx'])
    open_modal(opener)
    check(page.input_value('#rx_med') == F['medicine'], 'Prescription JSON values round-trip safely')
    close_modal(opener)
    opener = page.locator('[data-bs-target="#rxModal"]:not([data-edit-handler])')
    open_modal(opener)
    check(page.input_value('#rx_med') == '' and page.input_value('#rx_action') == 'add_prescription', 'Prescription add form clears previous edit values')
    close_modal(opener)
    page.screenshot(path=str(SHOTS/'consultation.png'), full_page=True)
    visit('/doctor/appointment_details.php?id='+str(F['ids'][0]))
    page.fill('[name=diagnosis]', 'Оқу диагнозы')
    page.fill('[name=treatment]', 'Оқу жоспары')
    page.get_by_role('button', name=KK['Complete Visit & Save Record'], exact=True).click()
    page.wait_for_load_state('networkidle')
    check(KK['Medical record saved and appointment completed.'] in page.locator('[role=alert]').inner_text(), 'Doctor completes consultation using existing transaction')
    visit('/doctor/appointment_details.php?id='+str(F['ids'][4]))
    check(page.locator('textarea[name=diagnosis]').count() == 0, 'Cancelled visit does not show clinical editing form')
    check(page.evaluate('window.testInjection !== true'), 'Stored markup is not executed')
    page.goto(BASE+'/logout.php')

    # Every user screen must fit a narrow viewport; only table regions may scroll.
    page.set_viewport_size({'width':390,'height':844})
    visit('/login.php')
    check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Mobile login fits viewport')
    page.screenshot(path=str(SHOTS/'login-mobile.png'))
    for user,role,paths in [
        ('admin','admin',['dashboard.php','departments.php','users.php','doctors.php','reports.php']),
        ('reception','receptionist',['dashboard.php','patients.php','patient_details.php?id=1','appointments.php']),
        ('doctor_smith','doctor',['dashboard.php','my_appointments.php','patient_history.php','patient_history.php?patient_id=1','appointment_details.php?id=1'])]:
        login(user,role)
        toggle = page.locator('.navbar-toggler')
        toggle.click()
        page.wait_for_function("document.querySelector('#navbarNav').classList.contains('show')")
        check(page.locator('[aria-current=page]').is_visible(), role+' mobile menu opens')
        toggle.click()
        page.wait_for_function("!document.querySelector('#navbarNav').classList.contains('show') && !document.querySelector('#navbarNav').classList.contains('collapsing')")
        for path in paths:
            visit('/'+role+'/'+path)
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), role+'/'+path+' fits 390px')
            labels = page.locator('h2,label,th,button,.nav-link-title').all_text_contents()
            assert not [label for label in labels if __import__('re').search('[A-Za-z]{3}', label)], labels
            if path=='dashboard.php': page.screenshot(path=str(SHOTS/(role+'-mobile.png')))
        page.goto(BASE+'/logout.php')
    check(not errors, 'No JavaScript errors')
    check(not failed, 'All visited resources return successful HTTP responses')
    check(not [r for r in requests if not r.startswith(BASE+'/')], 'No external asset requests')
    browser.close()

(SHOTS/'verification.json').write_text(json.dumps({'checks':checks,'passed':len(checks),'test_database':os.environ['HOSPITAL_DB_NAME'],'fixture_date':F['day']},ensure_ascii=False,indent=2),encoding='utf-8')
print(str(len(checks))+' browser checks passed.')
