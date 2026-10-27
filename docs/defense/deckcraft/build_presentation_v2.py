"""Build the revised editable 13-slide project defense in DeckCraft 0.10.

Only generates new *_v2 files. Existing presentations and databases are untouched.
"""
from pathlib import Path
import json
import subprocess
import zipfile
import xml.etree.ElementTree as ET

ROOT=Path(__file__).resolve().parents[3]
OUT=Path(__file__).resolve().parent
TEMP=ROOT/'tests/.runtime/deckcraft-v2'
TEMP.mkdir(parents=True,exist_ok=True)
CLI=Path(r'D:\CapCut_Draft\dist\CraftApps\deckcraft\0.10.0\deckcraft-0.10.0-windows-x64-portable\deckcraft-cli.exe')
if not CLI.exists(): raise SystemExit('DeckCraft CLI path does not exist')

NAVY='#142A46'; DEEP='#0E213A'; BLUE='#245BA8'; TEAL='#087C7D'
INK='#1D324B'; MUTED='#526880'; PALE='#F2F6FB'; WHITE='#FFFFFF'
LINE='#D9E3EE'; LIGHTBLUE='#DCE9F7'; LIGHTTEAL='#DDF2EE'; GOLD='#F4BC61'
LIGHTGOLD='#FFF1D7'; LIGHTRED='#FCE6E3'; RED='#A83D37'; DULL='#AFC2D8'

class Slide:
    def __init__(self,index,title,kicker,dark=False):
        self.index=index; self.title=title; self.cmd=[]; self.dark=dark
        if not index: self.add('slide.layout',layout='blank')
        else:
            self.add('slide.go',index=index-1)
            self.add('slide.new',layout='blank')
        self.rect(0,0,960,540,DEEP if dark else WHITE)
        self.rect(0,0,10,540,TEAL if dark else BLUE)
        self.text(kicker.upper(),52,27,850,19,10,GOLD if dark else TEAL,True)
        self.text(title,52,57,855,56,29,WHITE if dark else NAVY,True)
        self.rect(52,499,855,1,'#36506C' if dark else LINE)
        self.text('ЕМХАНА  /  ДЕРЕКТЕР БАЗАСЫ',52,510,700,15,9,DULL if dark else MUTED)
        self.text(f'{index+1:02}',869,508,36,18,10,DULL if dark else MUTED,False,'right')
    def add(self,name,**params):
        self.cmd.append(name+'='+json.dumps(params,ensure_ascii=False,separators=(',',':')))
    def rect(self,x,y,w,h,color):
        self.add('shape.insert',preset='rect',rect=[x,y,w,h]);self.add('shape.fill',color=color);self.add('shape.line',none=True)
    def line(self,x,y,w,h,color=LINE): self.rect(x,y,w,h,color)
    def text(self,value,x,y,w,h,size=16,color=INK,bold=False,align='left',font='Arial'):
        self.add('insert.textBox',rect=[x,y,w,h],text=value)
        self.add('format.fontDialog',family=font,size=size,bold=bold,color=color)
        self.add('format.margins',left=0,top=0,right=0,bottom=0)
        if align!='left': self.add('format.align',align=align)
        self.add('format.autofit',mode='shrink')
    def image(self,file,x,y,w,h,crop=False):
        self.add('insert.picture',path=str(ROOT/file),rect=[x,y,w,h])
        if crop: self.add('picture.crop',left=0.19,bottom=0.19)
    def callout(self,x,y,w,h,label,value,color=BLUE):
        self.rect(x,y,w,h,PALE);self.rect(x,y,4,h,color)
        self.text(label,x+15,y+13,w-30,23,13,color,True)
        self.text(value,x+15,y+44,w-30,h-52,16,INK)

slides=[]

# 01 — expressive cover with an editable process illustration
s=Slide(0,'Емханадағы қабылдауға\nжазылу жүйесі','Деректер базасы жобасы',True)
s.text('Пациентке арналған онлайн жазылудан\nдәрігер қабылдауы мен есепке дейін',52,176,585,82,23,WHITE)
s.text('PHP  /  MySQL–MariaDB  /  Tabler',52,302,550,27,15,DULL,True)
s.rect(52,368,492,2,'#36506C')
s.text('Ақпараттық жүйе · 4-тақырып',52,388,520,22,14,DULL)
s.rect(682,140,168,168,'#183859');s.rect(745,173,42,100,TEAL);s.rect(716,202,100,42,TEAL)
s.text('ПАЦИЕНТ',676,336,180,24,13,DULL,True,'center')
s.text('↓',676,362,180,24,20,GOLD,True,'center')
s.text('ҚАБЫЛДАУ',676,395,180,24,13,WHITE,True,'center')
slides.append(s)

# 02 — editorial problem/solution split, not cards
s=Slide(1,'Неліктен осы жүйе қажет?','Мәселе және мақсат')
s.rect(52,137,408,274,PALE);s.rect(490,137,417,274,LIGHTTEAL)
s.text('МӘСЕЛЕ',76,160,320,24,13,BLUE,True)
s.text('Уақыт қақтығысы',76,197,340,28,21,NAVY,True)
s.text('Бір дәрігерге бір уақытта бірнеше пациентті жазу қаупі.',76,234,335,55,17,MUTED)
s.text('Деректердің шашырауы',76,309,340,26,20,NAVY,True)
s.text('Карточка, қабылдау және нәтиже байланыссыз қалады.',76,343,335,48,16,MUTED)
s.text('ЖҮЙЕНІҢ ЖАУАБЫ',516,160,330,24,13,TEAL,True)
s.text('Бір дерекқор',516,197,335,28,21,NAVY,True)
s.text('Пациент, дәрігер және қабылдау ортақ байланыс арқылы сақталады.',516,234,340,55,17,INK)
s.text('Тексерілетін ережелер',516,309,340,26,20,NAVY,True)
s.text('Рөл, дерек иесі және қабылдау уақыты серверде тексеріледі.',516,343,340,50,16,INK)
s.text('Мақсат: жазылудан бастап қабылдау нәтижесіне дейінгі процесті біріктіру.',52,448,850,32,18,BLUE,True)
slides.append(s)

# 03 — table, user capabilities and boundaries
s=Slide(2,'Төрт рөл және олардың құқықтары','Пайдаланушылар')
s.rect(52,143,855,40,NAVY)
s.text('РӨЛ',70,153,150,22,12,WHITE,True);s.text('НЕ ІСТЕЙДІ?',240,153,400,22,12,WHITE,True)
s.text('ҚОЛЖЕТІМДІЛІК',664,153,225,22,12,WHITE,True)
rows=[('Пациент','Тіркеледі, бос уақытқа жазылады, бас тартады','Тек өз қабылдаулары'),
      ('Тіркеуші','Карточка жасайды, жазылуды басқарады','Пациенттер мен қабылдаулар'),
      ('Дәрігер','Қабылдайды, медициналық жазба, рецепт енгізеді','Өз қабылдауы және тиісті тарих'),
      ('Әкімші','Пайдаланушы, дәрігер, бөлімше және есеп','Жүйені басқару')]
for i,(role,action,access) in enumerate(rows):
 y=183+i*65;s.rect(52,y,855,64,PALE if i%2==0 else WHITE);s.line(52,y+63,855,1)
 s.text(role,70,y+19,145,26,17,BLUE,True);s.text(action,240,y+15,397,42,15,INK);s.text(access,664,y+15,222,40,15,MUTED)
s.text('Пациент карточкасы аккаунтсыз да болуы мүмкін; бұрынғы карточканы әкімші байланыстырады.',52,462,850,25,14,MUTED)
slides.append(s)

# 04 — dark process flow with concrete backend responsibilities
s=Slide(3,'Бір сұрау жүйеден қалай өтеді?','Жүйе архитектурасы',True)
for i,(x,title,caption) in enumerate([(55,'Браузер','Форма және интерфейс'),(354,'PHP сервері','Рөл, CSRF, валидация'),(652,'MySQL','Сұрау және шектеулер')]):
 s.rect(x,153,252,108,'#193B5C');s.text(title,x+18,172,215,29,21,WHITE,True)
 s.text(caption,x+18,217,216,23,14,DULL)
for x in [310,608]: s.text('→',x,184,40,40,28,GOLD,True,'center')
s.text('01',58,306,65,35,26,GOLD,True);s.text('Сұрау',117,306,210,27,18,WHITE,True)
s.text('Пациент күн мен дәрігерді таңдайды.',117,341,235,58,15,DULL)
s.text('02',365,306,65,35,26,GOLD,True);s.text('Тексеру',424,306,210,27,18,WHITE,True)
s.text('Аккаунт пен слот ережелері тексеріледі.',424,341,215,58,15,DULL)
s.text('03',660,306,65,35,26,GOLD,True);s.text('Жауап',719,306,180,27,18,WHITE,True)
s.text('Қабылдау сақталады немесе қате көрсетіледі.',719,341,190,58,15,DULL)
s.text('PDO параметрлері SQL мәндерін сұрау мәтінінен бөледі.',55,442,850,27,15,WHITE)
slides.append(s)

# 05 — data dictionary matrix grouped by purpose
s=Slide(4,'Деректер қай кестелерде сақталады?','Негізгі тоғыз кесте')
groups=[
 ('АККАУНТТАР',BLUE,('users','Логин, рөл, пароль хэші'),('patients','Аты, туған күні, телефон'),('patient_accounts','Аккаунт ↔ карточка')),
 ('ДӘРІГЕРЛЕР',TEAL,('departments','Бөлімшенің атауы'),('doctors','Дәрігер профилі'),('specializations','Мамандық анықтамалығы')),
 ('ҚАБЫЛДАУ',BLUE,('appointments','Күн, уақыт, мәртебе'),('medical_records','Диагноз, ем, ескертпе'),('prescriptions','Дәрі, мөлшер, ұзақтық'))]
for row,(label,color,*items) in enumerate(groups):
 y=147+row*103;s.rect(52,y,170,93,color);s.text(label,67,y+35,141,24,13,WHITE,True)
 for col,(name,desc) in enumerate(items):
  x=230+col*226;s.rect(x,y,217,93,PALE);s.text(name,x+11,y+18,196,25,15,NAVY,True);s.text(desc,x+11,y+50,194,32,12,MUTED)
s.text('Әр кесте нақты объектіні сақтайды; байланыстар сыртқы кілттер арқылы құрылады.',52,468,855,23,14,MUTED)
slides.append(s)

# 06 — database diagram and optionality
s=Slide(5,'Негізгі байланыстар','Бірден көпке және бірден бірге')
nodes=[(54,151,'patients','Пациент'),(368,151,'appointments','Қабылдау'),(680,151,'doctors','Дәрігер'),
       (368,294,'medical_records','Медициналық жазба'),(680,294,'prescriptions','Рецепттер')]
for x,y,name,label in nodes:
 s.rect(x,y,225,78,PALE);s.text(name,x+10,y+14,205,25,16,BLUE,True,'center');s.text(label,x+10,y+46,205,21,13,MUTED,False,'center')
s.text('1  →  N',280,177,86,23,14,TEAL,True,'center');s.text('N  ←  1',594,177,84,23,14,TEAL,True,'center')
s.text('1  ↓  0..1',400,250,162,24,14,TEAL,True,'center');s.text('1  →  0..N',595,320,83,25,13,TEAL,True,'center')
s.rect(54,406,850,49,LIGHTTEAL);s.text('users  ↔  patient_accounts  ↔  patients',70,419,565,22,16,NAVY,True)
s.text('Карточка аккаунтсыз бола алады.',623,420,264,21,13,TEAL,True,'right')
slides.append(s)

# 07 — vivid slot example and explicit index
s=Slide(6,'Бір уақытты екі рет алуға бола ма?','Уақыт қақтығысы',True)
s.text('Бірегей индекс (UNIQUE)',55,136,420,28,20,WHITE,True)
s.rect(55,176,850,66,'#193B5C');s.text('doctor_id  +  appointment_date  +  appointment_time  +  occupied_slot',75,197,810,30,18,WHITE,True)
s.text('ОҚУ МЫСАЛЫ',55,270,250,20,11,GOLD,True)
examples=[('09:00','Алғашқы жазба','Scheduled','Уақыт бос емес'),('09:00','Екінші жазба','Scheduled','Қақтығыс: рұқсат жоқ'),('09:00','Алғашқы жазба','Cancelled','Уақыт қайта босайды')]
for i,(time,case,status,result) in enumerate(examples):
 y=301+i*49;s.rect(55,y,850,45,'#193B5C' if i%2==0 else '#122F4B')
 s.text(time,70,y+10,95,25,16,GOLD,True);s.text(case,169,y+10,220,25,15,WHITE)
 s.text(status,404,y+10,155,25,14,DULL);s.text(result,578,y+10,300,26,15,WHITE,True)
s.text('Scheduled / Completed → 1; Cancelled → NULL. Дәл бірдей басталу уақыты қорғалады.',55,464,850,23,13,DULL)
slides.append(s)

# 08 — app screenshot plus operating constraints
s=Slide(7,'Пациент қалай жазылады?','Онлайн жазылу')
s.image('docs/patient-preview/booking.png',417,144,488,343,crop=True)
for y,num,title,body in [(149,'01','Аккаунт','Пациент тіркеледі: жаңа аккаунт және жаңа карточка.'),
                          (236,'02','Уақыт','Дәрігер мен күн таңдалады; бос уақыттар ғана көрсетіледі.'),
                          (323,'03','Растау','Қабылдау өз тізіміне қосылады; бас тарту қолжетімді.')]:
 s.text(num,52,y,48,30,22,TEAL,True);s.text(title,105,y,275,26,19,NAVY,True);s.text(body,105,y+34,285,47,15,MUTED)
s.rect(52,417,327,52,LIGHTTEAL);s.text('90 күн · 09:00–17:00 · 30 минут',65,433,300,24,14,TEAL,True)
slides.append(s)

# 09 — transaction and clinician screenshot
s=Slide(8,'Қабылдау нәтижесі қалай сақталады?','Дәрігер жұмысы')
s.image('docs/tabler-preview/consultation.png',459,145,446,314,crop=True)
s.text('Қабылдау ашылады',52,151,370,27,20,NAVY,True)
s.text('Дәрігер тек өзіне тағайындалған жазбаны өңдейді.',52,186,370,50,16,MUTED)
s.text('↓',58,237,45,28,22,TEAL,True)
s.text('Диагноз және ем енгізіледі',52,268,376,27,19,NAVY,True)
s.text('Медициналық жазба + мәртебе бір транзакцияда сақталады.',52,302,370,57,16,MUTED)
s.text('↓',58,359,45,28,22,TEAL,True)
s.text('Рецепт жазбаға тіркеледі',52,390,376,29,19,NAVY,True)
s.text('Аяқталған қабылдаудан бас тартуға болмайды.',52,429,380,34,14,BLUE,True)
slides.append(s)

# 10 — security layers and example authorization query
s=Slide(9,'Деректерге кім қол жеткізе алады?','Қауіпсіздік')
s.text('ҚОРҒАУ ҚАБАТТАРЫ',52,140,400,22,12,TEAL,True)
layers=[('1','Сессия және рөл','Әр қорғалған бетте белсенді аккаунт тексеріледі.'),
        ('2','Дерек иесі','Пациент тек өз қабылдауын өзгерте алады.'),
        ('3','Сұрауды тексеру','CSRF токені, PDO параметрлері, HTML экрандау.'),
        ('4','Құпиясөз','Хэш сақталып, password_verify арқылы тексеріледі.')]
for i,(num,title,desc) in enumerate(layers):
 y=172+i*67;s.rect(52,y,35,35,LIGHTTEAL);s.text(num,52,y+7,35,22,14,TEAL,True,'center')
 s.text(title,101,y,329,24,17,NAVY,True);s.text(desc,101,y+27,329,32,14,MUTED)
s.rect(466,140,440,273,NAVY);s.text('ПАЦИЕНТ ИЕЛІГІ · МЫСАЛ',488,161,397,21,11,GOLD,True)
s.text('SELECT patient_id\nFROM patient_accounts\nWHERE user_id = :session_user',488,205,395,125,19,WHITE,False,'left','Consolas')
s.text('patient_id формаға сеніп алынбайды;\nол ағымдағы сессиямен анықталады.',488,346,395,50,14,DULL)
slides.append(s)

# 11 — example SQL plus result, with explicitly fictional data
s=Slide(10,'Есеп: дәрігердің қабылдау саны','SQL және есеп беру')
s.rect(52,139,554,300,NAVY);s.text('JOIN + GROUP BY + COUNT',72,158,510,23,12,GOLD,True)
s.text('SELECT u.first_name, u.last_name,\n       COUNT(a.id) AS total\nFROM doctors d\nJOIN users u ON u.id = d.user_id\nLEFT JOIN appointments a\n       ON a.doctor_id = d.id\nGROUP BY d.id, u.first_name,\n         u.last_name;',72,194,510,218,16,WHITE,False,'left','Consolas')
s.text('НӘТИЖЕ · ОҚУ МЫСАЛЫ',630,150,270,22,12,TEAL,True)
s.rect(630,185,274,38,BLUE);s.text('Дәрігер',644,195,162,22,13,WHITE,True);s.text('Саны',813,195,75,22,13,WHITE,True,'right')
for i,(name,total) in enumerate([('Әлия Үлгіқызы','8'),('Ержан Үлгіұлы','5'),('Мәдина Тестова','0')]):
 y=223+i*48;s.rect(630,y,274,47,PALE if i%2==0 else WHITE);s.text(name,644,y+14,173,22,14,INK);s.text(total,820,y+14,67,22,14,BLUE,True,'right')
s.text('LEFT JOIN қабылдауы жоқ дәрігерді де қалдырады.',630,387,270,55,14,MUTED)
s.text('Көрсетілген сандар — түсіндіру үшін алынған оқу мысалы.',52,464,850,20,13,MUTED)
slides.append(s)

# 12 — testing evidence and limitations, distinct editorial layout
s=Slide(11,'Не тексерілді және не әлі қосылмаған?','Тексеру мен шектеулер')
s.text('ТЕКСЕРІЛГЕН',52,142,400,22,12,TEAL,True)
checks=['Рөлдер және бөтен дерекке шектеу','Бір уақытқа екі жазылу қақтығысы','Бас тарту және аяқталған қабылдауды қорғау','Медициналық жазба және рецепт','Телефонда көрсету және пернетақта']
for i,label in enumerate(checks):
 y=177+i*47;s.text('✓',54,y,30,25,18,TEAL,True);s.text(label,93,y+1,390,28,15,INK)
s.rect(517,142,389,256,PALE);s.text('ДАМЫТУ БАҒЫТТАРЫ',540,164,340,22,12,BLUE,True)
s.text('01  Дәрігердің жеке кестесі\n\n02  Демалыс пен үзілістер\n\n03  Еске салу хабарламалары\n\n04  Пациенттің медициналық тарихы',540,203,338,165,16,INK)
s.text('Сақталған браузерлік нәтижелер: 57 + 38 тексеріс, 27.10.2026; бөлек тест базасы.',52,454,855,30,13,MUTED)
slides.append(s)

# 13 — visual end-to-end conclusion
s=Slide(12,'Жүйенің нәтижесі','Қорытынды',True)
s.text('Біртұтас жұмыс барысы',54,151,800,38,25,WHITE,True)
for x,label,sub in [(54,'01','Пациент'),(270,'02','Тіркеуші'),(486,'03','Дәрігер'),(702,'04','Әкімші')]:
 s.rect(x,217,204,114,'#193B5C');s.text(label,x+16,234,170,24,15,GOLD,True);s.text(sub,x+16,275,174,30,20,WHITE,True)
s.text('Жазылу',54,348,190,27,15,DULL);s.text('Жоспарлау',270,348,190,27,15,DULL)
s.text('Медициналық нәтиже',486,348,195,27,15,DULL);s.text('Есеп',702,348,190,27,15,DULL)
s.text('Деректер байланысы  ·  рөлдік қолжетімділік  ·  бос уақытты қорғау',54,419,850,30,16,WHITE)
s.text('Назарларыңызға рақмет!',54,458,850,29,19,GOLD,True)
slides.append(s)

previous=None
for i,slide in enumerate(slides):
    target=TEMP/f'step-{i+1:02}.deckcraft';args=[str(CLI),'run']
    if previous: args+=['--in',str(previous)]
    for command in slide.cmd: args+=['--cmd',command]
    args+=['--save',str(target)]
    result=subprocess.run(args,capture_output=True,text=True,encoding='utf-8',errors='replace')
    if result.returncode: raise RuntimeError(f'Slide {i+1}: {result.stdout}\n{result.stderr}')
    previous=target;print(f'Built {i+1}/{len(slides)}',flush=True)

native=OUT/'Emhana_prezentatsiya_v2.deckcraft';pptx=OUT/'Emhana_prezentatsiya_v2.pptx';pdf=OUT/'Emhana_prezentatsiya_v2.pdf'
native.write_bytes(previous.read_bytes())
for target in [pptx,pdf]:
    subprocess.run([str(CLI),'convert',str(native),str(target)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace')
rendered=TEMP/'rendered'
subprocess.run([str(CLI),'render',str(pptx),'--all','--scale','1',str(rendered)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace')
info=json.loads(subprocess.run([str(CLI),'info',str(pptx)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace').stdout)
assert len(info['slides'])==len(slides)
with zipfile.ZipFile(pptx) as z:
    assert z.testzip() is None
    presentation=ET.fromstring(z.read('ppt/presentation.xml'))
    rels=ET.fromstring(z.read('ppt/_rels/presentation.xml.rels'))
    targets={rel.attrib['Id']:rel.attrib['Target'] for rel in rels}
    rid='{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id'
    names=['ppt/'+targets[item.attrib[rid]].lstrip('/') for item in presentation.findall('.//{http://schemas.openxmlformats.org/presentationml/2006/main}sldId')]
    assert len(names)==len(slides)
    all_text=[]
    for slide,name in zip(slides,names):
        body=' '.join(' '.join(ET.fromstring(z.read(name)).itertext()).split())
        assert ' '.join(slide.title.split()) in body,f'Slide {slide.index+1} order mismatch'
        all_text.append(body)
    all_text=' '.join(all_text)
    for word in ['1-адам','2-адам','1 адам','2 адам','00:00','06:00','12:00','15 минут']:
        assert word not in all_text,word
    for word in ['Пациент','дәрігер','appointments','medical_records','UNIQUE','LEFT JOIN']:
        assert word in all_text,word
    assert len([name for name in z.namelist() if name.startswith('ppt/media/') and not name.endswith('/')])>=2
print('PPTX and DeckCraft sources verified:',len(slides),'ordered slides; two embedded screenshots.',flush=True)
