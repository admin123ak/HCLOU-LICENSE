# Apple Serial Checker - ban da sua selector (id serial-number-input moi)
import os, time, json, re, openpyxl
from collections import namedtuple
from openpyxl.styles import PatternFill, Font
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from webdriver_manager.chrome import ChromeDriverManager

PROGRESS_FILE="progress.json"; RESULT_FILE="results.xlsx"; INPUT_FILE="serials.xlsx"
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
    o=webdriver.ChromeOptions()
    o.add_argument("--start-maximized"); o.add_argument("--disable-blink-features=AutomationControlled")
    o.add_experimental_option("excludeSwitches",["enable-automation"])
    o.add_experimental_option("useAutomationExtension",False)
    d=webdriver.Chrome(service=Service(ChromeDriverManager().install()),options=o)
    try: d.execute_cdp_cmd("Page.addScriptToEvaluateOnNewDocument",{"source":"Object.defineProperty(navigator,'webdriver',{get:()=>undefined});"})
    except: pass
    return d

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

def handle(driver,serial,i,total):
    try:
        driver.get("https://checkcoverage.apple.com/?locale=vi_VN")
        wait=WebDriverWait(driver,60)
        print(f"\n({i}/{total}) Serial: {serial}")
        el=find_serial_input(driver,wait)
        if not el:
            print("Khong tim duoc o serial"); return Result(serial,"ERROR","","","")
        js_set(driver,el,serial)
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
    i,results=load_progress(); d=create_browser()
    while i<len(serials):
        try:
            results.append(handle(d,serials[i],i+1,len(serials))); i+=1; save_progress(i,results)
            if len(results)%5==0: save_results(results)
        except Exception as e:
            print("Loi vong lap:",e)
            try: d.quit()
            except: pass
            d=create_browser()
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
