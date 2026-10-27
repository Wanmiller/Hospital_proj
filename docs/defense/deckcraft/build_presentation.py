"""Create the presentation with DeckCraft's own command-line editor.

Run on Windows with the installed DeckCraft CLI. All text and diagrams remain
editable; the two interface screenshots are embedded as pictures.
"""
from pathlib import Path
import json
import subprocess
import zipfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(__file__).resolve().parent
TEMP = ROOT/'tests/.runtime/deckcraft-build'
TEMP.mkdir(parents=True, exist_ok=True)
CLI = Path(r'D:\CapCut_Draft\dist\CraftApps\deckcraft\0.10.0\deckcraft-0.10.0-windows-x64-portable\deckcraft-cli.exe')
if not CLI.exists():
    raise SystemExit('DeckCraft CLI not found at the configured path')

NAVY='#173052'; BLUE='#245BA8'; BLUE_DARK='#1B437C'; PALE='#EFF4FA'
INK='#263C55'; MUTED='#5D7188'; WHITE='#FFFFFF'; LINE='#DCE5F0'
TEAL='#0D7E7C'; TEAL_LIGHT='#DFF3F0'; DARK='#102641'; GOLD='#E6B96C'

class Slide:
    def __init__(self,index,title,kicker):
        self.index=index; self.title=title; self.cmd=[]
        if index==0: self.add('slide.layout',layout='blank')
        else:
            self.add('slide.go',index=index-1)
            self.add('slide.new',layout='blank')
        self.rect(0,0,960,540,PALE)
        self.rect(0,0,9,540,BLUE)
        self.text(kicker.upper(),54,30,820,22,11,BLUE,True)
        self.text(title,54,60,850,57,30,NAVY,True)
        self.rect(54,496,852,1,LINE)
        self.text('ЕМХАНА · АҚПАРАТТЫҚ ЖҮЙЕ',54,508,740,18,9,MUTED)
        self.text(f'{index+1:02}',875,506,30,18,10,MUTED,False,'right')
    def add(self,name,**params):
        self.cmd.append(name+'='+json.dumps(params,ensure_ascii=False,separators=(',',':')))
    def rect(self,x,y,w,h,color,rounded=False):
        self.add('shape.insert',preset='roundRect' if rounded else 'rect',rect=[x,y,w,h])
        self.add('shape.fill',color=color)
        self.add('shape.line',none=True)
    def text(self,value,x,y,w,h,size=18,color=INK,bold=False,align='left'):
        self.add('insert.textBox',rect=[x,y,w,h],text=value)
        self.add('format.fontDialog',family='Arial',size=size,bold=bold,color=color)
        self.add('format.margins',left=0,top=0,right=0,bottom=0)
        if align!='left': self.add('format.align',align=align)
        self.add('format.autofit',mode='shrink')
    def card(self,x,y,w,h,label,description,fill=WHITE):
        self.rect(x,y,w,h,fill,True)
        self.text(label,x+19,y+18,w-38,31,19,NAVY,True)
        self.text(description,x+19,y+54,w-38,h-64,16,MUTED)
    def tag(self,value,x,y,w=80):
        self.rect(x,y,w,25,TEAL_LIGHT,True)
        self.text(value,x+8,y+5,w-16,15,10,TEAL,True,'center')
    def image(self,filename,x,y,w,h):
        self.add('insert.picture',path=str(ROOT/filename),rect=[x,y,w,h])

slides=[]

# 01: title
s=Slide(0,'Емханадағы қабылдауға\nжазылу жүйесі','Деректер базасы жобасы')
s.rect(54,182,850,230,WHITE,True)
s.text('Пациенттен қабылдау нәтижесіне дейін',79,213,790,48,27,BLUE,True)
s.text('Тіркелу  →  жазылу  →  қабылдау  →  есеп',79,278,790,35,21,INK)
s.text('PHP  ·  MySQL / MariaDB  ·  Tabler',79,345,790,28,16,MUTED)
slides.append(s)

# 02: purpose
s=Slide(1,'Жобаның мақсаты','Мәселе және шешім')
s.card(54,157,260,228,'Қиындық','Қабылдау уақытын, пациенттерді және дәрігерлерді бөлек жүргізгенде шатасу туындайды.')
s.card(330,157,260,228,'Мақсат','Жазылу мен қабылдауды бір дерекқорда басқару және қызметкерлердің жұмысын жеңілдету.')
s.card(606,157,298,228,'Нәтиже','Пациент өзі жазылады, дәрігер нәтижені енгізеді, әкімші есепті көреді.')
s.text('Негізгі қағида: әр пайдаланушы өз рөліне сай әрекет етеді.',54,421,840,34,19,BLUE,True)
slides.append(s)

# 03: roles
s=Slide(2,'Жүйедегі төрт рөл','Пайдаланушылар')
for x,y,label,desc in [
 (54,145,'Пациент','Тіркелу, бос уақытты таңдау және өз қабылдауларын көру.'),
 (487,145,'Тіркеуші','Пациент карточкалары мен қабылдауларды жүргізу.'),
 (54,306,'Дәрігер','Өз қабылдаулары, медициналық жазба және рецепт.'),
 (487,306,'Әкімші','Пайдаланушылар, дәрігерлер, бөлімшелер және есептер.')]:
 s.card(x,y,417,145,label,desc)
slides.append(s)

# 04: architecture
s=Slide(3,'Жүйе қалай жұмыс істейді?','Бағдарламалық құрылым')
for x,label,desc in [(54,'Браузер','HTML · CSS · JavaScript'),(351,'PHP сервері','Сессия және тексеру'),(648,'Дерекқор','MySQL / MariaDB')]:
 s.rect(x,172,257,150,WHITE,True)
 s.text(label,x+16,197,225,38,22,NAVY,True,'center')
 s.text(desc,x+16,255,225,31,16,MUTED,False,'center')
for x in [317,614]: s.text('→',x,225,32,45,30,BLUE,True,'center')
s.text('Сұрау',61,360,190,26,15,BLUE,True)
s.text('Браузер → сервер → дерекқор → пайдаланушыға жауап',61,391,818,35,19,INK)
s.text('Tabler интерфейсінің файлдары жобада жергілікті сақталған.',61,440,820,23,14,MUTED)
slides.append(s)

# 05: database
s=Slide(4,'Дерекқордың негізгі кестелері','Деректер моделі')
grid=[('users','Аккаунт және рөл'),('patients','Пациент карточкасы'),('patient_accounts','Аккаунт байланысы'),
      ('departments','Бөлімшелер'),('doctors','Дәрігер профилі'),('specializations','Мамандықтар'),
      ('appointments','Қабылдаулар'),('medical_records','Медициналық жазба'),('prescriptions','Рецепттер')]
for i,(label,desc) in enumerate(grid):
 x=54+(i%3)*289;y=139+(i//3)*111
 s.rect(x,y,270,94,WHITE,True)
 s.text(label,x+16,y+14,238,25,17,BLUE,True)
 s.text(desc,x+16,y+50,238,22,14,MUTED)
slides.append(s)

# 06: relationships
s=Slide(5,'Кестелер арасындағы байланыс','Деректер моделі')
for x,y,label,sub in [(58,154,'patients','Пациент'),(345,154,'appointments','Қабылдау'),(634,154,'doctors','Дәрігер'),
                       (345,298,'medical_records','Медициналық жазба'),(634,298,'prescriptions','Рецепттер')]:
 s.rect(x,y,225,89,WHITE,True)
 s.text(label,x+12,y+15,201,25,16,BLUE_DARK,True,'center')
 s.text(sub,x+12,y+51,201,22,13,MUTED,False,'center')
s.text('1  →  N',283,181,60,26,14,BLUE,True,'center')
s.text('N  ←  1',571,181,60,26,14,BLUE,True,'center')
s.text('1  ↓  0..1',383,257,150,23,14,BLUE,True,'center')
s.text('1  →  N',571,327,60,26,14,BLUE,True,'center')
s.text('Аккаунт пен пациент карточкасын patient_accounts кестесі байланыстырады.',58,438,820,30,15,MUTED)
slides.append(s)

# 07: slot integrity
s=Slide(6,'Бір уақытқа қос жазылуды болдырмау','Дерекқор шектеулері')
s.rect(54,146,850,84,WHITE,True)
s.text('Дәрігер  +  күн  +  басталу уақыты  +  occupied_slot',77,174,800,32,21,NAVY,True)
for x,label,value,desc,color in [(54,'Жоспарланған','1','Уақыт бос емес',BLUE),(340,'Аяқталған','1','Уақыт сақталады',TEAL),(626,'Бас тартылған','NULL','Уақыт қайта босайды',MUTED)]:
 s.rect(x,256,270,148,WHITE,True)
 s.text(label,x+18,274,234,27,17,NAVY,True)
 s.text(value,x+18,313,234,35,26,color,True)
 s.text(desc,x+18,363,234,24,14,MUTED)
s.text('UNIQUE индексі бірдей уақытты екі рет алуға жол бермейді.',54,442,850,27,16,BLUE,True)
slides.append(s)

# 08: patient experience
s=Slide(7,'Пациенттің өздігінен жазылуы','Пайдаланушы сценарийі')
s.image('docs/patient-preview/booking.png',468,150,426,300)
for y,num,title,desc in [(148,'01','Тіркелу','Жеке аккаунт пен карточка жасалады.'),
                          (225,'02','Таңдау','Дәрігер, күн және бос уақыт таңдалады.'),
                          (302,'03','Растау','Жазба жеке қабылдаулар тізіміне қосылады.'),
                          (379,'04','Басқару','Жоспарланған жазбадан бас тартуға болады.')]:
 s.tag(num,54,y,45);s.text(title,110,y-1,330,25,17,NAVY,True);s.text(desc,110,y+29,330,40,14,MUTED)
s.text('Көрсетілім — оқу деректері',565,458,300,17,10,MUTED,False,'center')
slides.append(s)

# 09: clinical workflow
s=Slide(8,'Қабылдау және медициналық жазба','Дәрігер жұмысы')
s.image('docs/tabler-preview/consultation.png',467,148,429,302)
for y,title,desc in [(155,'Қабылдау','Дәрігер өзіне тағайындалған пациентті ашады.'),
                     (247,'Медициналық жазба','Диагноз бен емді енгізіп, қабылдауды аяқтайды.'),
                     (340,'Рецепт','Медициналық жазбаға дәрі мен қолдану тәртібі тіркеледі.')]:
 s.text(title,60,y,375,29,18,NAVY,True);s.text(desc,60,y+34,375,49,15,MUTED)
s.text('Көрсетілім — оқу деректері',567,458,300,17,10,MUTED,False,'center')
slides.append(s)

# 10: safety
s=Slide(9,'Қолжетімділік және деректерді қорғау','Қауіпсіздік шаралары')
for x,y,label,desc in [
 (54,145,'Рөл мен сессия','Қорғалған беттерде аккаунттың рөлі мен белсенділігі тексеріледі.'),
 (487,145,'Деректер иесі','Пациент тек өз қабылдауларын, дәрігер өз қабылдауларын көреді.'),
 (54,307,'Құпиясөз және формалар','Құпиясөз хэшпен сақталады, POST формаларында CSRF токені бар.'),
 (487,307,'SQL және HTML','PDO параметрлері қолданылып, бетке шығатын мәтін экрандалады.')]:
 s.card(x,y,417,147,label,desc)
slides.append(s)

# 11: admin
s=Slide(10,'Есептер және SQL сұраулары','Әкімші бөлімі')
s.card(54,148,270,245,'JOIN','Пациент, дәрігер, бөлімше және қабылдау мәліметтерін байланыстырады.')
s.card(345,148,270,245,'GROUP BY + COUNT','Дәрігер мен бөлімше бойынша қабылдауларды санайды.')
s.card(636,148,270,245,'Мәртебе','Жоспарланған, аяқталған және бас тартылған қабылдаулар есептеледі.')
s.text('Сүзгілер: күн аралығы, бөлімше және дәрігер.',54,426,850,34,19,BLUE,True)
slides.append(s)

# 12: testing and limits
s=Slide(11,'Тексеру және даму бағыттары','Жобаның қазіргі күйі')
s.rect(54,145,415,290,WHITE,True);s.text('Тексерілген сценарийлер',75,165,370,35,20,NAVY,True)
s.text('• Рөлдер және дерек иелігі\n• Қабылдау уақытының қақтығысы\n• Жазба мен рецепт\n• Телефондағы көрініс',75,215,370,195,17,INK)
s.rect(488,145,416,290,WHITE,True);s.text('Келесі қадамдар',509,165,370,35,20,NAVY,True)
s.text('• Дәрігердің жеке жұмыс кестесі\n• Хабарлама және еске салу\n• Пациенттің медициналық тарихы\n• Қосымша аудит мүмкіндіктері',509,215,370,195,17,INK)
slides.append(s)

# 13: conclusion
s=Slide(12,'Қорытынды','Жоба нәтижесі')
s.rect(54,150,850,247,WHITE,True)
s.text('Бір жүйедегі толық жұмыс барысы',81,181,790,45,26,NAVY,True)
s.text('Жазылу  →  қабылдау  →  нәтиже  →  есеп',81,247,790,44,23,BLUE,True)
s.text('Өзара байланысқан деректер · рөлдер · қорғалған уақыт',81,319,790,30,17,MUTED)
s.text('Назарларыңызға рақмет!',54,441,850,35,23,BLUE,True)
slides.append(s)

previous=None
for i,slide in enumerate(slides):
    target=TEMP/f'step-{i+1:02}.deckcraft'
    args=[str(CLI),'run']
    if previous: args+=['--in',str(previous)]
    for command in slide.cmd: args+=['--cmd',command]
    args+=['--save',str(target)]
    result=subprocess.run(args,capture_output=True,text=True,encoding='utf-8',errors='replace')
    if result.returncode: raise RuntimeError(f'Slide {i+1}: {result.stdout}\n{result.stderr}')
    previous=target
    print(f'Built {i+1}/{len(slides)}',flush=True)

native=OUT/'Emhana_prezentatsiya.deckcraft'
pptx=OUT/'Emhana_prezentatsiya.pptx'
pdf=OUT/'Emhana_prezentatsiya.pdf'
native.write_bytes(previous.read_bytes())
for target in [pptx,pdf]:
    subprocess.run([str(CLI),'convert',str(native),str(target)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace')
render_dir=TEMP/'rendered'
subprocess.run([str(CLI),'render',str(pptx),'--all','--scale','1',str(render_dir)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace')
info=json.loads(subprocess.run([str(CLI),'info',str(pptx)],check=True,capture_output=True,text=True,encoding='utf-8',errors='replace').stdout)
assert len(info['slides'])==len(slides),info['slides']
with zipfile.ZipFile(pptx) as z:
    assert z.testzip() is None
    presentation=ET.fromstring(z.read('ppt/presentation.xml'))
    rels=ET.fromstring(z.read('ppt/_rels/presentation.xml.rels'))
    targets={rel.attrib['Id']:rel.attrib['Target'] for rel in rels}
    rid='{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id'
    names=['ppt/'+targets[item.attrib[rid]].lstrip('/') for item in presentation.findall('.//{http://schemas.openxmlformats.org/presentationml/2006/main}sldId')]
    assert len(names)==len(slides)
    text=' '.join(' '.join(ET.fromstring(z.read(n)).itertext()) for n in names)
    for slide,name in zip(slides,names):
        body=' '.join(' '.join(ET.fromstring(z.read(name)).itertext()).split())
        assert ' '.join(slide.title.split()) in body, f'Slide {slide.index+1} order mismatch'
    for word in ['1-адам','2-адам','1 адам','2 адам','00:00','06:00','12:00','15 минут']:
        assert word not in text,word
    for word in ['Пациент','дәрігер','appointments','medical_records','UNIQUE']:
        assert word in text,word
print('DeckCraft source, PPTX and PDF created; slides:',len(slides),flush=True)
