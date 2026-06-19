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

# ===== CHONG BAN =====
# ===== PROXY XOAY proxyxoay.shop (proxy.vn) =====
#  CACH DE NHAT: tao file "proxy_key.txt" cung thu muc, dan KEY proxy vao do.
#  Vd noi dung proxy_key.txt:  auTIIHkBKzsXULfICziAVb
#  Tool se TU whitelist IP may ban + tu doi IP. KHONG can sua gi them.
PROXY_KEY=""            # hoac dan thang key vao day (trong "")
PROXY_NHAMANG="Random"  # Random / viettel / fpt / vnpt
PROXY_TINH="0"          # 0=Random, 3=Ha Noi, 6=HCM ...
PROXY_API_URL=""        # (nang cao) dan full link get.php neu muon tu chinh
PROXY_MIN_SEC=62        # goi proxy gioi han doi IP toi thieu 60s -> de 62s cho chac
PROXY=""                # (tuy chon) proxy co dinh "host:port"

# Doc key tu file proxy_key.txt (de nguoi khong rich khoi sua .py)
try:
    if os.path.exists("proxy_key.txt"):
        _k=open("proxy_key.txt",encoding="utf-8").read().strip()
        if _k: PROXY_KEY=_k
except: pass

_my_ip=[None]
def my_public_ip():
    if _my_ip[0] is not None: return _my_ip[0]
    try:
        import requests
        _my_ip[0]=requests.get("https://api.ipify.org",timeout=10).text.strip()
    except: _my_ip[0]=""
    return _my_ip[0]

def _build_proxy_url():
    if PROXY_API_URL: return PROXY_API_URL
    if PROXY_KEY:
        u=f"https://proxyxoay.shop/api/get.php?key={PROXY_KEY}&nhamang={PROXY_NHAMANG}&tinhthanh={PROXY_TINH}"
        wl=my_public_ip()
        if wl: u+=f"&whitelist={wl}"   # tu whitelist IP may dang chay
        return u
    return ""

BATCH_PER_IP=6          # check bao nhieu serial thi DOI IP
DELAY_MIN, DELAY_MAX=6, 14   # nghi ngau nhien giua moi serial (giay) - giong nguoi

# ===== CHE DO TREO (chay khong can ngoi canh) =====
# True = KHONG BAO GIO dung cho nhap tay. OCR sai -> thu lai/doi IP -> bo qua serial (RETRY).
#        Bat buoc dung PROXY_API_URL (de tu doi IP, khong cho VPN tay).
UNATTENDED=True
CAPTCHA_TRIES=6         # so lan thu giai 1 captcha truoc khi bo qua

_last_change=[0.0]
def fetch_rotating_proxy():
    """Goi API proxyxoay.shop -> tra 'host:port' proxy moi (da doi IP). None neu loi.
       Ton trong gioi han doi IP toi thieu PROXY_MIN_SEC giay."""
    url=_build_proxy_url()
    if not url: return None
    wait=PROXY_MIN_SEC-(time.time()-_last_change[0])
    if wait>0:
        print(f"   (cho {int(wait)}s cho du gioi han doi IP {PROXY_MIN_SEC}s)...")
        time.sleep(wait+0.5)
    try:
        import requests
        j=requests.get(url,timeout=45).json()
        if str(j.get("status"))!="100":
            print("   !! API proxy bao loi:", j.get("message") or j); return None
        raw=(j.get("proxyhttp") or "").strip()          # "host:port::"
        m=re.match(r"([\w.\-]+):(\d+)", raw)
        ph=f"{m.group(1)}:{m.group(2)}" if m else raw.replace(":","",0)
        _last_change[0]=time.time()
        print(f"   >> proxy moi: {ph}  ({j.get('Nha Mang','')}/{j.get('Vi Tri','')})  {j.get('message','')}")
        time.sleep(4)   # cho proxy san sang
        return ph
    except Exception as e:
        print("   !! loi goi API proxy:", e); return None
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

def _parse_proxy(p):
    """Tach 'user:pass@host:port' hoac 'host:port' -> (host,port,user,pass)."""
    p=p.strip().replace("http://","").replace("https://","")
    user=pwd=""
    if "@" in p:
        cred,hp=p.rsplit("@",1)
        if ":" in cred: user,pwd=cred.split(":",1)
    else: hp=p
    host,port=hp.split(":",1) if ":" in hp else (hp,"80")
    return host,port,user,pwd

def _proxy_auth_ext(host,port,user,pwd,fn="proxy_auth_ext.zip"):
    """Tao extension Chrome de proxy CO user:pass (Chrome khong nhan auth qua URL)."""
    import zipfile
    manifest='{"version":"1.0","manifest_version":2,"name":"px","permissions":["proxy","tabs","unlimitedStorage","storage","<all_urls>","webRequest","webRequestBlocking"],"background":{"scripts":["bg.js"]},"minimum_chrome_version":"22.0.0"}'
    bg=('var c={mode:"fixed_servers",rules:{singleProxy:{scheme:"http",host:"%s",port:parseInt(%s)},bypassList:["localhost"]}};'
        'chrome.proxy.settings.set({value:c,scope:"regular"},function(){});'
        'chrome.webRequest.onAuthRequired.addListener(function(d){return{authCredentials:{username:"%s",password:"%s"}};},{urls:["<all_urls>"]},["blocking"]);'
       )%(host,port,user,pwd)
    z=zipfile.ZipFile(fn,"w"); z.writestr("manifest.json",manifest); z.writestr("bg.js",bg); z.close()
    return fn

def create_browser(proxy=None):
    ua=random.choice(UAS)
    host=port=user=pwd=""
    if proxy: host,port,user,pwd=_parse_proxy(proxy)
    Opt = uc.ChromeOptions() if HAS_UC else webdriver.ChromeOptions()
    Opt.add_argument("--start-maximized")
    Opt.add_argument("--user-agent="+ua)
    Opt.add_argument("--lang=vi-VN")
    if proxy and not user:                        # proxy KHONG auth (IP-whitelist)
        Opt.add_argument("--proxy-server=http://%s:%s"%(host,port))
    if proxy and user:                            # proxy CO user:pass -> extension
        try: Opt.add_extension(_proxy_auth_ext(host,port,user,pwd))
        except Exception as e: print("!! Loi extension proxy:",e)
    if HAS_UC:
        d=uc.Chrome(options=Opt)
    else:
        Opt.add_argument("--disable-blink-features=AutomationControlled")
        Opt.add_experimental_option("excludeSwitches",["enable-automation"])
        Opt.add_experimental_option("useAutomationExtension",False)
        d=webdriver.Chrome(service=Service(ChromeDriverManager().install()),options=Opt)
        try: d.execute_cdp_cmd("Page.addScriptToEvaluateOnNewDocument",{"source":"Object.defineProperty(navigator,'webdriver',{get:()=>undefined});"})
        except: pass
    # Chong treo vo han khi proxy cham/chet
    try: d.set_page_load_timeout(45)
    except: pass
    return d

def human_pause(driver):
    """Cuon trang + di chuot ngau nhien (giong nguoi) + nghi ngau nhien."""
    try:
        driver.execute_script("window.scrollBy(0,%d);" % random.randint(80,300))
    except: pass
    time.sleep(random.uniform(DELAY_MIN, DELAY_MAX))

# ===== HAM DA SUA: selector dung voi trang Apple moi (Next.js) =====
def find_serial_input(driver, wait=None, timeout=30):
    strategies=[
        (By.ID,"serial-number-input"),
        (By.CSS_SELECTOR,"input.form-textbox-input"),
        (By.XPATH,"//input[@maxlength='18']"),
        (By.XPATH,"//input[contains(@aria-label,'sê-ri')]"),
        (By.XPATH,"//input[@type='text' and @required]"),
    ]
    end=time.time()+timeout
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
    # selector THAT cua Apple: captcha-image (+ container)
    return _find(driver,[
        (By.ID,"captcha-image"),
        (By.CSS_SELECTOR,"img.captcha-image, #captcha-image img, .captcha-image-container img, .captcha-image-section img"),
        (By.CSS_SELECTOR,"[class*='captcha-image'] img"),
        (By.CSS_SELECTOR,"img[src*='captcha']"),
        (By.CSS_SELECTOR,"img[src^='data:image']"),
    ])
def find_captcha_input(driver, serial_el=None):
    """O nhap captcha THAT cua Apple: captcha-textbox (trong captcha-input-box)."""
    el=_find(driver,[
        (By.ID,"captcha-textbox"),
        (By.CSS_SELECTOR,"input.captcha-textbox, #captcha-textbox, .captcha-input-box input, .captcha-field-box input"),
        (By.CSS_SELECTOR,"[class*='captcha-textbox']"),
        (By.CSS_SELECTOR,"input[id*='captcha' i], input[name*='captcha' i]"),
    ])
    if el and el!=serial_el: return el
    # Du phong: o text cung khung voi anh captcha
    img=find_captcha_img(driver)
    if img:
        try:
            box=img.find_element(By.XPATH,"./ancestor::*[self::div or self::form][1]")
            for inp in box.find_elements(By.XPATH,".//input[@type='text' or not(@type)]"):
                if inp.is_displayed() and inp!=serial_el: return inp
        except: pass
    return None
def find_continue(driver):
    # nut submit THAT: captcha-action / captcha-btn / trong captcha-form-element
    return _find(driver,[
        (By.ID,"captcha-action"),
        (By.CSS_SELECTOR,"#captcha-action, .captcha-btn, #captcha-form-element button[type='submit'], .captcha-form-element button"),
        (By.XPATH,"//button[contains(.,'Tiếp tục') or contains(.,'Continue') or contains(.,'Gửi') or contains(.,'Submit')]"),
        (By.CSS_SELECTOR,"button[type='submit']"),
    ])

def human_type(el, text):
    """Go tung ky tu cham nhu nguoi (kich hoat su kien React) - khong spam."""
    try: el.click()
    except: pass
    try: el.clear()
    except: pass
    for ch in text:
        try: el.send_keys(ch)
        except: pass
        time.sleep(random.uniform(0.08,0.22))
    time.sleep(random.uniform(0.3,0.7))

def refresh_captcha(driver):
    """Bam nut lam moi captcha (lay anh moi de OCR de hon)."""
    el=_find(driver,[
        (By.CSS_SELECTOR,"[aria-label*='refresh' i],[aria-label*='lam moi' i],[aria-label*='làm mới' i]"),
        (By.CSS_SELECTOR,".captcha-icon,.captcha-refresh,#captcha-refresh,[class*='refresh']"),
        (By.XPATH,"//*[contains(@class,'captcha')]//button[not(@type='submit')]"),
    ])
    if el:
        try: el.click(); time.sleep(1.5); return True
        except: pass
    return False

def submit_and_wait(driver, timeout=25):
    """Bam Tiep tuc roi DOI ket qua that. Tra: 'done' (qua) / 'captcha_fail' / 'timeout'."""
    btn=find_continue(driver)
    if btn:
        try: btn.click()
        except Exception:
            try: driver.execute_script("arguments[0].click()",btn)
            except Exception: pass
    end=time.time()+timeout
    while time.time()<end:
        # qua duoc = khong con anh captcha (da sang trang ket qua)
        if find_captcha_img(driver) is None: return "done"
        try: b=driver.find_element(By.TAG_NAME,"body").text.lower()
        except: b=""
        if any(k in b for k in ["không chính xác","khong chinh xac","thử lại","captcha không","sai mã","incorrect","try again"]):
            return "captcha_fail"
        time.sleep(1)
    return "timeout"

def wait_captcha_loaded(driver, timeout=18):
    """Cho anh captcha hien (trang co 'Loading CAPTCHA' truoc khi load xong)."""
    end=time.time()+timeout
    while time.time()<end:
        img=find_captcha_img(driver)
        if img:
            try:
                # anh da load that (co kich thuoc), khong phai placeholder
                if img.size.get('width',0)>10: return img
            except: return img
        time.sleep(1)
    return find_captcha_img(driver)

def solve_captcha_auto(driver,serial_el,serial):
    """Giai captcha tu dong (ddddocr) - go cham + submit + DOI ket qua. Thu toi 4 lan.
       True=qua / False=OCR sai het / None=khong tim thay o -> nhap tay."""
    wait_captcha_loaded(driver)   # cho captcha load xong
    for attempt in range(CAPTCHA_TRIES):
        img=find_captcha_img(driver); cin=find_captcha_input(driver,serial_el)
        if not img or not cin:
            return None
        try:
            driver.execute_script("arguments[0].scrollIntoView({block:'center'});", img)
            time.sleep(random.uniform(0.5,1.0))
        except: pass
        try: png=img.screenshot_as_png
        except Exception: return None
        text=re.sub(r'[^A-Za-z0-9]','',_ocr.classification(png) or '')
        if not text:
            refresh_captcha(driver); time.sleep(1.2); continue
        print(f"   captcha OCR (lan {attempt+1}/{CAPTCHA_TRIES}): {text}")
        try: driver.execute_script("arguments[0].scrollIntoView({block:'center'});", cin)
        except: pass
        human_type(cin,text)            # go cham vao DUNG o captcha
        res=submit_and_wait(driver)     # gui + doi ket qua that
        if res=="done": return True
        # sai -> lam moi captcha lay anh moi; dien lai serial neu o serial hien lai
        refresh_captcha(driver)
        s2=find_serial_input(driver,timeout=3)
        if s2 and (s2.get_attribute("value") or "")!=serial: js_set(driver,s2,serial)
        time.sleep(random.uniform(1.0,2.0))
    return False

def looks_banned(driver):
    """Phat hien trang bi BAN/CHAN (Apple/Akamai)."""
    try: b=driver.find_element(By.TAG_NAME,"body").text.lower()
    except: return False
    return any(k in b for k in [
        "access denied","forbidden","reference #","unusual activity","too many requests",
        "đã bị chặn","quá nhiều yêu cầu","akamai","error 16","blocked","captcha không thể"])

def handle(driver,serial,i,total):
    try:
        print(f"\n({i}/{total}) Serial: {serial}")
        try:
            driver.get("https://checkcoverage.apple.com/?locale=vi_VN")
        except Exception:
            # page load timeout (proxy cham) -> coi nhu can doi IP
            try: driver.execute_script("window.stop();")
            except: pass
        el=find_serial_input(driver,timeout=30)
        if not el:
            # Khong thay o serial -> rat co the bi BAN/CHAN IP
            if looks_banned(driver):
                print("   !! Co dau hieu BI BAN/CHAN"); return Result(serial,"BANNED","","","")
            print("Khong tim duoc o serial"); return Result(serial,"ERROR","","","")
        js_set(driver,el,serial)
        # Tu giai captcha
        ok=solve_captcha_auto(driver,el,serial) if AUTO_CAPTCHA else None
        if ok is not True:
            if UNATTENDED:
                # CHE DO TREO: khong cho nhap tay -> bao RETRY de doi IP + thu lai sau
                print("   (OCR chua qua -> RETRY, doi IP)")
                return Result(serial,"RETRY","","","")
            if ok is None: print("   (Khong tu tim/giai duoc captcha — nhap tay)")
            else: print(f"   (OCR sai {CAPTCHA_TRIES} lan — nhap tay)")
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
    mode = "PROXY XOAY (API)" if (PROXY_API_URL or PROXY_KEY) else ("PROXY co dinh" if PROXY else "VPN tay")
    print(f">> Che do: TREO={'BAT' if UNATTENDED else 'TAT'} | IP={mode} | doi IP moi {BATCH_PER_IP} serial | undetected={'BAT' if HAS_UC else 'TAT'}")
    if UNATTENDED and not (PROXY_API_URL or PROXY_KEY):
        print("!! CANH BAO: Che do TREO can PROXY_API_URL (de tu doi IP). Chua co proxy -> se phai cho VPN tay, KHONG treo duoc.")
    retry_count={}; MAX_RETRY=4
    cur_proxy = fetch_rotating_proxy() if (PROXY_API_URL or PROXY_KEY) else (PROXY or None)
    d=create_browser(cur_proxy)
    done_on_ip=0
    while i<len(serials):
        try:
            # Doi IP sau moi lo
            if done_on_ip>=BATCH_PER_IP:
                try: d.quit()
                except: pass
                print(f"\n>>> Da check {BATCH_PER_IP} serial tren IP nay.")
                if (PROXY_API_URL or PROXY_KEY):
                    new=fetch_rotating_proxy()      # goi API -> doi IP + proxy moi
                    if new: cur_proxy=new
                elif PROXY:
                    time.sleep(random.uniform(2,4))
                else:
                    print(">>> DOI SERVER Proton VPN (de doi IP) -> roi an Enter de chay tiep...")
                    input()
                d=create_browser(cur_proxy); done_on_ip=0
                time.sleep(random.uniform(3,6))

            r=handle(d,serials[i],i+1,len(serials))

            # BI BAN / RETRY (OCR chua qua) -> doi IP roi thu lai serial nay
            if r.status in ("BANNED","RETRY"):
                retry_count[serials[i]]=retry_count.get(serials[i],0)+1
                if retry_count[serials[i]]>MAX_RETRY:
                    # Thu qua nhieu lan van khong duoc -> bo qua, ghi UNKNOWN, di tiep
                    print(f"   !! {serials[i]} thu {MAX_RETRY} lan khong duoc -> bo qua (UNKNOWN)")
                    r=Result(serials[i],"Unknown","","",""); results.append(r); i+=1; done_on_ip+=1
                    save_progress(i,results); save_results(results)
                    continue
                print(f"   !! {r.status} -> doi IP roi thu lai (lan {retry_count[serials[i]]}/{MAX_RETRY})")
                try: d.quit()
                except: pass
                if (PROXY_API_URL or PROXY_KEY):
                    new=fetch_rotating_proxy()
                    if new: cur_proxy=new
                elif PROXY:
                    time.sleep(random.uniform(2,4))
                else:
                    # Khong co proxy -> treo khong tu doi IP duoc; cho VPN tay (neu UNATTENDED se ket)
                    print(">>> DOI SERVER Proton VPN -> roi an Enter...")
                    input()
                d=create_browser(cur_proxy); done_on_ip=0
                time.sleep(random.uniform(3,6))
                continue   # KHONG tang i -> check lai serial nay tren IP moi

            results.append(r); i+=1; done_on_ip+=1
            save_progress(i,results)
            save_results(results)              # LUU NGAY sau moi serial hoan thanh
            print(f"   -> da luu ket qua ({r.status})")
            if i<len(serials): human_pause(d)   # nghi ngau nhien giong nguoi
        except Exception as e:
            print("Loi vong lap:",e)
            try: d.quit()
            except: pass
            time.sleep(random.uniform(3,6))
            d=create_browser(cur_proxy); done_on_ip=0
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
