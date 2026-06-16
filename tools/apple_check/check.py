# Apple Serial Checker - ban da sua selector (id serial-number-input moi)
import os, time, json, re, openpyxl
from collections import namedtuple
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from webdriver_manager.chrome import ChromeDriverManager

PROGRESS_FILE="progress.json"; RESULT_FILE="results.xlsx"; INPUT_FILE="serials.xlsx"
Result=namedtuple("Result",["serial","status","activation","expiry"])
print("Apple Serial Checker")

def read_serials(fp):
    wb=openpyxl.load_workbook(fp); ws=wb.active; out=[]
    for row in ws.iter_rows(min_row=2,max_col=1):
        v=row[0].value
        if isinstance(v,str) and v.strip(): out.append(v.strip())
    return out

def save_results(results,fp=RESULT_FILE):
    wb=openpyxl.Workbook(); ws=wb.active
    ws.append(['Serial','Status','Ngay kich hoat','Ngay het BH'])
    for r in results: ws.append([r.serial,r.status,r.activation,r.expiry])
    wb.save(fp); print("Da luu",fp)

def save_progress(i,results):
    json.dump({"index":i,"results":[r._asdict() for r in results]},open(PROGRESS_FILE,"w",encoding="utf-8"),ensure_ascii=False)
def load_progress():
    if os.path.exists(PROGRESS_FILE):
        d=json.load(open(PROGRESS_FILE,encoding="utf-8"))
        rs=[Result(r.get("serial",""),r.get("status",""),r.get("activation",""),r.get("expiry","")) for r in d.get("results",[])]
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

# ===== HAM TIM O SERIAL (manh, cho trang load + JS quet sau) =====
def find_serial_input(driver, wait):
    js_deep=r"""
    function deep(){
      function vis(e){var r=e.getBoundingClientRect();return r.width>0&&r.height>0;}
      function s(root){
        var ins=root.querySelectorAll('input');
        for(var i=0;i<ins.length;i++){var e=ins[i];var t=(e.type||'text').toLowerCase();
          var c=((e.className||'')+' '+(e.getAttribute('placeholder')||'')+' '+(e.getAttribute('aria-label')||'')).toLowerCase();
          if(e.id==='serial-number-input') return e;
          if((t==='text'||t==='search'||t==='')&&vis(e)&&c.indexOf('globalnav')<0&&c.indexOf('searchfield')<0) return e;}
        var all=root.querySelectorAll('*');
        for(var j=0;j<all.length;j++){if(all[j].shadowRoot){var f=s(all[j].shadowRoot);if(f)return f;}}
        return null;}
      return s(document);
    } return deep();
    """
    end=time.time()+35
    while time.time()<end:
        # cho trang render xong
        try:
            if driver.execute_script("return document.readyState")!="complete":
                time.sleep(0.6); continue
        except: pass
        # 1) selenium selector thuong
        for by,val in [(By.ID,"serial-number-input"),
                       (By.CSS_SELECTOR,"input.form-textbox-input"),
                       (By.XPATH,"//input[@maxlength='18']"),
                       (By.XPATH,"//input[contains(@aria-label,'sê-ri')]"),
                       (By.XPATH,"//input[@type='text' and not(@type='hidden')]")]:
            try:
                el=driver.find_element(by,val)
                if el: return el
            except: pass
        # 2) JS quet sau (shadow DOM / an)
        try:
            el=driver.execute_script(js_deep)
            if el: return el
        except: pass
        time.sleep(1)
    # khong thay -> in trang hien tai de chan doan
    try:
        print("   [debug] URL:",driver.current_url,"| Title:",driver.title)
    except: pass
    return None

def js_set(driver,el,val):
    # Trang Apple la React -> dat .value thuong bi React xoa. Go phim that truoc.
    try: el.click(); el.clear()
    except: pass
    try: el.send_keys(val)
    except: pass
    time.sleep(0.3)
    try: cur=el.get_attribute("value") or ""
    except: cur=""
    if cur.strip().upper()!=str(val).strip().upper():
        driver.execute_script("""
        var el=arguments[0],v=arguments[1];
        var s=Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set;
        s.call(el,v);
        el.dispatchEvent(new Event('input',{bubbles:true}));
        el.dispatchEvent(new Event('change',{bubbles:true}));
        """,el,val)
    time.sleep(0.4)

def find_date(line):
    m=re.search(r"(\d{1,2})\s+tháng\s+(\d{1,2}),\s*(\d{4})",line,re.I)
    if m: d,mt,y=m.groups(); return f"{int(d):02d}/{int(mt):02d}/{y}"
    return line.strip()

URL="https://checkcoverage.apple.com/?locale=vi_VN"

def result_ready(driver):
    # CHI coi la co ket qua khi: co NGAY that, hoac trang thai ro rang.
    # (KHONG dung 'bao hanh'/'ho tro' vi co san tren trang form -> nham!)
    try: body=driver.find_element(By.TAG_NAME,"body").text
    except: return False
    low=body.lower()
    if ("không hợp lệ" in low or "chưa được kích hoạt" in low or "không thể hoàn thành" in low):
        return True
    if re.search(r"\d{1,2}\s+tháng\s+\d{1,2},?\s*\d{4}", body, re.I):
        return True
    return False

def click_submit(driver):
    # nut Gui/Tiep tuc cua Apple (id serial-button) hoac theo text
    for by,val in [(By.ID,"serial-button"),
                   (By.CSS_SELECTOR,"button[type='submit']"),
                   (By.XPATH,"//button[contains(.,'Gửi') or contains(.,'Tiếp tục') or contains(.,'Kiểm tra') or contains(.,'Continue')]")]:
        try:
            b=driver.find_element(by,val)
            if b and b.is_enabled():
                driver.execute_script("arguments[0].click();",b); return True
        except: pass
    return False

def go_to_form(driver):
    # tim nut 'Kiem tra san pham khac' de GIU PHIEN (khoi nhap captcha lai)
    for xp in ["//a[contains(.,'khác') or contains(.,'sản phẩm khác')]",
               "//button[contains(.,'khác') or contains(.,'sản phẩm khác')]",
               "//a[contains(.,'another') or contains(.,'lại')]",
               "//button[contains(.,'another') or contains(.,'lại')]"]:
        try:
            b=driver.find_element(By.XPATH,xp)
            if b and b.is_displayed():
                driver.execute_script("arguments[0].click();",b); time.sleep(1.5); return True
        except: pass
    return False

def handle(driver,serial,i,total):
    try:
        wait=WebDriverWait(driver,30)
        print(f"\n({i}/{total}) Serial: {serial}")
        # FORM SACH moi serial (tranh doc nham ket qua serial truoc)
        driver.get(URL); time.sleep(1.5)
        el=find_serial_input(driver,wait)
        if not el:
            print("Khong tim duoc o serial")
            try: print("   [debug]",driver.current_url,"|",driver.title)
            except: pass
            return Result(serial,"ERROR","","")

        # Dien serial + bam Gui, roi DUNG cho user nhap captcha
        js_set(driver,el,serial)
        click_submit(driver)
        input(">> Nhap captcha + bam Gui, roi an Enter o day...")
        click_submit(driver)
        time.sleep(1)

        # CHO RA KHOI FORM: doi den khi URL doi sang /coverage hoac /error
        body=""; cur=""
        for _ in range(30):
            try:
                cur=driver.current_url or ""
                body=driver.find_element(By.TAG_NAME,"body").text
            except: cur=""; body=""
            # da ra khoi form khi vao /coverage hoac /error
            if "/coverage" in cur or "/error" in cur:
                time.sleep(1.2)   # cho noi dung render xong
                try: body=driver.find_element(By.TAG_NAME,"body").text
                except: pass
                break
            time.sleep(1)
        low=body.lower()

        # LUU trang ket qua ra file (de gui minh xem khi sai)
        try:
            open("debug_last.txt","w",encoding="utf-8").write(
                f"SERIAL: {serial}\nURL: {cur}\n----BODY----\n{body}")
        except: pass

        # === APPLE CHAN IP -> doi VPN, thu lai ===
        if "/error" in cur or "generic_error" in low:
            print("!! Apple CHAN IP. DOI Proton VPN sang nuoc khac.")
            ans=input(">> Doi VPN xong an Enter de THU LAI | go 's' bo qua: ").strip().lower()
            if ans=="s": return Result(serial,"Error","","")
            return handle(driver,serial,i,total)

        # Con o FORM (chua submit) -> Unknown, bao ro
        if "/coverage" not in cur:
            print("Chua ra ket qua (van o form). -> Unknown. Lan sau: bam Gui THAY ket qua roi moi Enter.")
            return Result(serial,"Unknown","","")

        # === O trang /coverage: doc ket qua ===
        dates=re.findall(r"(\d{1,2})\s+tháng\s+(\d{1,2}),?\s*(\d{4})", body, re.I)
        if dates:
            norm=[f"{int(d):02d}/{int(m):02d}/{y}" for d,m,y in dates]
            pur=norm[0]; exp=norm[1] if len(norm)>1 else ""
            print("Trang thai: Activated | mua:",pur,"| het BH:",exp or "(het han)")
            return Result(serial,"Activated",pur,exp)
        if "không hợp lệ" in low or "chưa được kích hoạt" in low:
            print("Trang thai: Unactivated"); return Result(serial,"Unactivated","","")
        if "không thể hoàn thành" in low:
            print("Trang thai: Unknown"); return Result(serial,"Unknown","","")

        # O /coverage nhung khong parse duoc -> dump (xem debug_last.txt)
        print("Khong doc duoc ngay (xem file debug_last.txt, GUI minh file do):")
        print(body[:400])
        return Result(serial,"Unknown","","")
    except Exception as e:
        print("Loi:",e); return Result(serial,"ERROR","","")

def main():
    serials=read_serials(INPUT_FILE)
    if not serials: print("Khong co serial nao trong serials.xlsx (bat dau o A2)"); return
    print("Da nap",len(serials),"serial")
    i,results=load_progress(); d=create_browser()
    d.get(URL); time.sleep(2)   # mo trang Apple ngay tu dau (khoi dung o data:,)
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
    print("HOAN TAT")
    input("An Enter de dong...")

if __name__=="__main__": main()
