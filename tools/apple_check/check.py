# Apple Serial Checker - ban da sua selector (id serial-number-input moi)
import os, time, json, re, random, openpyxl
from collections import namedtuple
from openpyxl.styles import PatternFill, Font
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# undetected-chromedriver: qua mat bot-detection cua Apple (Akamai)
try:
    import undetected_chromedriver as uc
    HAS_UC=True
except Exception:
    HAS_UC=False
    from selenium.webdriver.chrome.service import Service
    from webdriver_manager.chrome import ChromeDriverManager
    print("!! Chua cai undetected-chromedriver -> de bi ban hon. Cai: pip install undetected-chromedriver")

PROGRESS_FILE="progress.json"; RESULT_FILE="results.xlsx"; INPUT_FILE="serials.xlsx"

# ===== CHONG BAN (free) =====
BATCH_PER_IP=6          # check bao nhieu serial thi DOI IP (doi server VPN)
DELAY_MIN, DELAY_MAX=6, 14   # nghi ngau nhien giua moi serial (giay) - giong nguoi
UAS=[
 "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
 "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
 "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
 "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36",
]
Result=namedtuple("Result",["serial","status","model","activation","expiry"])
print("Apple Serial Checker")

def read_serials(fp):
    wb=openpyxl.load_workbook(fp); ws=wb.active; out=[]
    for row in ws.iter_rows(min_row=2,max_col=1):
        v=row[0].value
        if isinstance(v,str) and v.strip(): out.append(v.strip())
    return out

def save_results(results,fp=RESULT_FILE):
    wb=openpyxl.Workbook(); ws=wb.active
    hdr=['Serial','Status','Model','Ngay kich hoat','Ngay het BH']
    ws.append(hdr)
    for c in ws[1]: c.font=Font(bold=True,color="FFFFFF"); c.fill=PatternFill("solid",fgColor="374151")
    colors={"Activated":"C6EFCE","Unactivated":"FFC7CE","ERROR":"D9D9D9","Unknown":"FFEB9C"}
    for r in results:
        ws.append([r.serial,r.status,r.model,r.activation,r.expiry])
        fg=colors.get(r.status,"FFFFFF")
        for c in ws[ws.max_row]: c.fill=PatternFill("solid",fgColor=fg)
    for col,w in zip("ABCDE",[22,13,22,16,16]): ws.column_dimensions[col].width=w
    try: wb.save(fp); print("Da luu",fp)
    except PermissionError: print("!! Dong file",fp,"dang mo roi chay lai (Excel dang giu file)")

def save_progress(i,results):
    json.dump({"index":i,"results":[r._asdict() for r in results]},open(PROGRESS_FILE,"w",encoding="utf-8"),ensure_ascii=False)
def load_progress():
    if os.path.exists(PROGRESS_FILE):
        d=json.load(open(PROGRESS_FILE,encoding="utf-8"))
        rs=[Result(r.get("serial",""),r.get("status",""),r.get("model",""),r.get("activation",""),r.get("expiry","")) for r in d.get("results",[])]
        return d.get("index",0),rs
    return 0,[]

def create_browser():
    ua=random.choice(UAS)
    if HAS_UC:
        o=uc.ChromeOptions()
        o.add_argument("--start-maximized")
        o.add_argument("--user-agent="+ua)
        o.add_argument("--lang=vi-VN")
        d=uc.Chrome(options=o)
    else:
        o=webdriver.ChromeOptions()
        o.add_argument("--start-maximized"); o.add_argument("--disable-blink-features=AutomationControlled")
        o.add_argument("--user-agent="+ua)
        o.add_experimental_option("excludeSwitches",["enable-automation"])
        o.add_experimental_option("useAutomationExtension",False)
        d=webdriver.Chrome(service=Service(ChromeDriverManager().install()),options=o)
        try: d.execute_cdp_cmd("Page.addScriptToEvaluateOnNewDocument",{"source":"Object.defineProperty(navigator,'webdriver',{get:()=>undefined});"})
        except: pass
    return d

def human_pause(driver):
    """Cuon trang + di chuot ngau nhien (giong nguoi) + nghi ngau nhien."""
    try:
        driver.execute_script("window.scrollBy(0,%d);" % random.randint(80,300))
    except: pass
    time.sleep(random.uniform(DELAY_MIN, DELAY_MAX))

# ===== HAM DA SUA: selector dung voi trang Apple moi (Next.js) =====
def find_serial_input(driver, wait):
    strategies=[
        (By.ID,"serial-number-input"),
        (By.CSS_SELECTOR,"input.form-textbox-input"),
        (By.XPATH,"//input[@maxlength='18']"),
        (By.XPATH,"//input[contains(@aria-label,'sê-ri')]"),
        (By.XPATH,"//input[@type='text' and @required]"),
    ]
    end=time.time()+30
    while time.time()<end:
        for by,val in strategies:
            try:
                el=driver.find_element(by,val)
                if el and el.is_displayed(): return el
            except: pass
        time.sleep(1)
    return None

def js_set(driver,el,val):
    driver.execute_script("""var e=arguments[0],v=arguments[1];e.focus();e.value=v;
    ['input','change'].forEach(function(n){e.dispatchEvent(new Event(n,{bubbles:true}));});""",el,val)
    time.sleep(0.4)

def find_model(driver):
    for xp in ["//h2","//h1","//*[contains(@class,'device') or contains(@class,'product')]"]:
        try:
            for el in driver.find_elements(By.XPATH,xp):
                t=el.text.strip()
                if t and any(k in t for k in ["iPhone","iPad","Watch","MacBook","iMac","Mac","AirPods","iPod"]):
                    return t[:40]
        except: pass
    return ""

def find_date(line):
    m=re.search(r"(\d{1,2})\s+tháng\s+(\d{1,2}),\s*(\d{4})",line,re.I)
    if m: d,mt,y=m.groups(); return f"{int(d):02d}/{int(mt):02d}/{y}"
    return line.strip()

# ===== AUTO CAPTCHA (ddddocr - OCR free) =====
try:
    import ddddocr
    _ocr=ddddocr.DdddOcr(show_ad=False)
    AUTO_CAPTCHA=True
except Exception:
    _ocr=None; AUTO_CAPTCHA=False
    print("!! Chua cai ddddocr -> se nhap captcha tay. Cai: pip install ddddocr")

def _find(driver, pairs):
    for by,val in pairs:
        try:
            el=driver.find_element(by,val)
            if el and el.is_displayed(): return el
        except: pass
    return None

def find_captcha_img(driver):
    return _find(driver,[
        (By.CSS_SELECTOR,"img[src*='captcha']"),
        (By.CSS_SELECTOR,"img[alt*='captcha' i]"),
        (By.XPATH,"//img[contains(@id,'captcha') or contains(@class,'captcha')]"),
        (By.XPATH,"//*[contains(@class,'captcha')]//img"),
        (By.CSS_SELECTOR,"img[src^='data:image']"),
    ])
def find_captcha_input(driver):
    return _find(driver,[
        (By.ID,"captcha-input"),
        (By.CSS_SELECTOR,"input[name*='captcha' i]"),
        (By.CSS_SELECTOR,"input[aria-label*='captcha' i]"),
        (By.XPATH,"//input[contains(@id,'captcha')]"),
        (By.XPATH,"//input[@maxlength='4' or @maxlength='5' or @maxlength='6']"),
    ])
def find_continue(driver):
    return _find(driver,[
        (By.XPATH,"//button[contains(.,'Tiếp tục') or contains(.,'Continue') or contains(.,'Tiep tuc')]"),
        (By.ID,"continue-button"),
        (By.CSS_SELECTOR,"button[type='submit']"),
    ])

def solve_captcha_auto(driver,serial_el,serial):
    """Tu giai captcha bang ddddocr, thu toi 4 lan. True=qua, False=khong giai duoc -> fallback tay."""
    for attempt in range(4):
        img=find_captcha_img(driver); cin=find_captcha_input(driver)
        if not img or not cin:
            return None  # khong tim thay element -> bao goi nhap tay
        try: png=img.screenshot_as_png
        except Exception: return None
        text=re.sub(r'[^A-Za-z0-9]','',_ocr.classification(png) or '')
        if not text:
            time.sleep(1); continue
        print(f"   captcha OCR (lan {attempt+1}): {text}")
        js_set(driver,cin,text)
        btn=find_continue(driver)
        if btn:
            try: btn.click()
            except Exception:
                try: driver.execute_script("arguments[0].click()",btn)
                except Exception: pass
        time.sleep(3)
        # Qua duoc khi: khong con o captcha nua (da sang trang ket qua)
        if find_captcha_img(driver) is None:
            return True
        # Sai -> trang reload captcha moi; dien lai serial neu o serial xuat hien lai
        s2=find_serial_input(driver,None)
        if s2: js_set(driver,s2,serial)
        time.sleep(1)
    return False

def handle(driver,serial,i,total):
    try:
        driver.get("https://checkcoverage.apple.com/?locale=vi_VN")
        wait=WebDriverWait(driver,60)
        print(f"\n({i}/{total}) Serial: {serial}")
        el=find_serial_input(driver,wait)
        if not el:
            print("Khong tim duoc o serial"); return Result(serial,"ERROR","","","")
        js_set(driver,el,serial)
        # Tu giai captcha; neu khong duoc -> nhap tay (fallback)
        ok=solve_captcha_auto(driver,el,serial) if AUTO_CAPTCHA else None
        if ok is not True:
            if ok is None: print("   (Khong tu tim/giai duoc captcha — nhap tay)")
            else: print("   (OCR sai 4 lan — nhap tay)")
            input(">> Nhap captcha & bam Tiep tuc, roi an Enter o day...")
        body=driver.find_element(By.TAG_NAME,"body").text.lower()
        if "ngày mua không hợp lệ" in body or "thiết bị chưa được kích hoạt" in body:
            print("Trang thai: Unactivated"); return Result(serial,"Unactivated",find_model(driver),"","")
        pur=exp=""
        try: pur=find_date(driver.find_element(By.XPATH,"//body//*[contains(text(),'Đã mua')]").text)
        except: pass
        try: exp=find_date(driver.find_element(By.XPATH,"//body//*[contains(text(),'Hết hạn')]").text)
        except: pass
        mdl=find_model(driver)
        if pur or exp:
            print("Trang thai: Activated", mdl, pur, exp); return Result(serial,"Activated",mdl,pur,exp)
        print("Khong xac dinh"); return Result(serial,"Unknown",mdl,"","")
    except Exception as e:
        print("Loi:",e); return Result(serial,"ERROR","","","")

def main():
    serials=read_serials(INPUT_FILE)
    if not serials: print("Khong co serial nao trong serials.xlsx (bat dau o A2)"); return
    print("Da nap",len(serials),"serial")
    i,results=load_progress()
    print(f">> Chong ban: doi IP moi {BATCH_PER_IP} serial, nghi {DELAY_MIN}-{DELAY_MAX}s/serial, undetected={'BAT' if HAS_UC else 'TAT'}")
    d=create_browser()
    done_on_ip=0
    while i<len(serials):
        try:
            # Doi IP sau moi lo: dong browser -> nhac doi server VPN -> browser moi (fingerprint moi)
            if done_on_ip>=BATCH_PER_IP:
                try: d.quit()
                except: pass
                print(f"\n>>> Da check {BATCH_PER_IP} serial tren IP nay.")
                print(">>> DOI SERVER Proton VPN (de doi IP) -> roi an Enter de chay tiep...")
                input()
                d=create_browser(); done_on_ip=0
                time.sleep(random.uniform(3,6))

            results.append(handle(d,serials[i],i+1,len(serials))); i+=1; done_on_ip+=1
            save_progress(i,results)
            if len(results)%5==0: save_results(results)
            if i<len(serials): human_pause(d)   # nghi ngau nhien giong nguoi
        except Exception as e:
            print("Loi vong lap:",e)
            try: d.quit()
            except: pass
            time.sleep(random.uniform(3,6))
            d=create_browser(); done_on_ip=0
    try: d.quit()
    except: pass
    save_results(results)
    if os.path.exists(PROGRESS_FILE): os.remove(PROGRESS_FILE)
    from collections import Counter
    cnt=Counter(r.status for r in results)
    print("\n===== THONG KE =====")
    print(f"Tong: {len(results)} | Activated: {cnt.get('Activated',0)} | Unactivated: {cnt.get('Unactivated',0)} | Unknown: {cnt.get('Unknown',0)} | Loi: {cnt.get('ERROR',0)}")
    print("HOAN TAT")
    input("An Enter de dong...")

if __name__=="__main__": main()
