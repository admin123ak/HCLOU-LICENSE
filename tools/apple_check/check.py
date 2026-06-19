# Apple Serial Checker - Bản sửa lỗi khóa cứng Serial, chống lag, gõ mượt 100%
import os, time, json, re, random, openpyxl
from collections import namedtuple
from openpyxl.styles import PatternFill, Font
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

try:
    import undetected_chromedriver as uc
    HAS_UC=True
except Exception:
    HAS_UC=False
    from selenium.webdriver.chrome.service import Service
    from webdriver_manager.chrome import ChromeDriverManager
    print("!! Chua cai undetected-chromedriver. Cai: pip install undetected-chromedriver")

PROGRESS_FILE="progress.json"; RESULT_FILE="results.xlsx"; INPUT_FILE="serials.xlsx"

# ===== CHONG BAN & PROXY =====
PROXY_KEY=""            
PROXY_NHAMANG="Random"  
PROXY_TINH="0"          
PROXY_API_URL=""        
PROXY_MIN_SEC=62        
PROXY=""                

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
        if wl: u+=f"&whitelist={wl}"
        return u
    return ""

BATCH_PER_IP=6          
DELAY_MIN, DELAY_MAX=3, 6   

UNATTENDED=True
CAPTCHA_TRIES=8 # Tăng số lần thử giải lên 8 lần cho chắc chắn

_last_change=[0.0]
def fetch_rotating_proxy():
    url=_build_proxy_url()
    if not url: return None
    wait=PROXY_MIN_SEC-(time.time()-_last_change[0])
    if wait>0:
        print(f"   (cho {int(wait)}s cho du gioi han doi IP)...")
        time.sleep(wait+0.5)
    try:
        import requests
        j=requests.get(url,timeout=45).json()
        if str(j.get("status"))!="100":
            print("   !! API proxy bao loi:", j.get("message") or j); return None
        raw=(j.get("proxyhttp") or "").strip()
        m=re.match(r"([\w.\-]+):(\d+)", raw)
        ph=f"{m.group(1)}:{m.group(2)}" if m else raw.replace(":","",0)
        _last_change[0]=time.time()
        print(f"   >> proxy moi: {ph}  ({j.get('Nha Mang','')}/{j.get('Vi Tri','')})")
        time.sleep(4)
        return ph
    except Exception as e:
        print("   !! loi goi API proxy:", e); return None

UAS=[
 "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
]
Result=namedtuple("Result",["serial","status","model","activation","expiry"])
print("Apple Serial Checker - Fixed No-Lag")

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
    except PermissionError: print("!! Dong file Excel dang mo roi chay lai")

def save_progress(i,results):
    json.dump({"index":i,"results":[r._asdict() for r in results]},open(PROGRESS_FILE,"w",encoding="utf-8"),ensure_ascii=False)
def load_progress():
    if os.path.exists(PROGRESS_FILE):
        d=json.load(open(PROGRESS_FILE,encoding="utf-8"))
        rs=[Result(r.get("serial",""),r.get("status",""),r.get("model",""),r.get("activation",""),r.get("expiry","")) for r in d.get("results",[])]
        return d.get("index",0),rs
    return 0,[]

def _parse_proxy(p):
    p=p.strip().replace("http://","").replace("https://","")
    user=pwd=""
    if "@" in p:
        cred,hp=p.rsplit("@",1)
        if ":" in cred: user,pwd=cred.split(":",1)
    else: hp=p
    host,port=hp.split(":",1) if ":" in hp else (hp,"80")
    return host,port,user,pwd

def _proxy_auth_ext(host,port,user,pwd,fn="proxy_auth_ext.zip"):
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
    if proxy and not user:                        
        Opt.add_argument("--proxy-server=http://%s:%s"%(host,port))
    if proxy and user:                            
        try: Opt.add_extension(_proxy_auth_ext(host,port,user,pwd))
        except: pass
    if HAS_UC:
        d=uc.Chrome(options=Opt, version_main=149)
    else:
        Opt.add_argument("--disable-blink-features=AutomationControlled")
        Opt.add_experimental_option("excludeSwitches",["enable-automation"])
        Opt.add_experimental_option("useAutomationExtension",False)
        d=webdriver.Chrome(service=Service(ChromeDriverManager().install()),options=Opt)
    try: d.set_page_load_timeout(45)
    except: pass
    return d

def human_pause(driver):
    time.sleep(random.uniform(DELAY_MIN, DELAY_MAX))

def find_serial_input(driver, timeout=15):
    strategies=[
        (By.ID,"serial-number-input"),
        (By.CSS_SELECTOR,"input.form-textbox-input"),
        (By.XPATH,"//input[@maxlength='18']"),
    ]
    end=time.time()+timeout
    while time.time()<end:
        for by,val in strategies:
            try:
                el=driver.find_element(by,val)
                if el and el.is_displayed(): return el
            except: pass
        time.sleep(0.5)
    return None

def smooth_type(el, text):
    """Gõ phím mượt mà gồm xóa sạch ô cũ và điền chữ giãn cách nhẹ giúp React cập nhật 100%"""
    try:
        el.click()
        el.send_keys(Keys.CONTROL + "a")
        el.send_keys(Keys.BACKSPACE)
        time.sleep(0.2)
        for char in text:
            el.send_keys(char)
            time.sleep(0.05) # Giãn cách nhỏ chống lag chữ
        time.sleep(0.3)
    except Exception as e:
        print("Lỗi gõ phím:", e)

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

# ===== AUTO CAPTCHA (ddddocr) =====
try:
    import ddddocr
    _ocr=ddddocr.DdddOcr(show_ad=False)
    AUTO_CAPTCHA=True
except Exception:
    _ocr=None; AUTO_CAPTCHA=False

def _find(driver, pairs):
    for by,val in pairs:
        try:
            el=driver.find_element(by,val)
            if el and el.is_displayed(): return el
        except: pass
    return None

def find_captcha_img(driver):
    return _find(driver,[
        (By.ID,"captcha-image"),
        (By.CSS_SELECTOR,"img.captcha-image, #captcha-image img"),
    ])

def find_captcha_input(driver):
    return _find(driver,[
        (By.ID,"captcha-textbox"),
        (By.CSS_SELECTOR,"input.captcha-textbox, #captcha-textbox"),
    ])

def find_continue(driver):
    return _find(driver,[
        (By.ID,"captcha-action"),
        (By.CSS_SELECTOR,"#captcha-action, .captcha-btn"),
    ])

def refresh_captcha(driver):
    el=_find(driver,[
        (By.CSS_SELECTOR,"[aria-label*='refresh' i],[aria-label*='lam moi' i]"),
        (By.CSS_SELECTOR,".captcha-icon,.captcha-refresh"),
    ])
    if el:
        try: el.click(); time.sleep(1.5); return True
        except: pass
    return False

def submit_and_check(driver, timeout=6):
    """Bấm tiếp tục và kiểm tra thật kỹ xem trang đã thực sự chuyển hay chưa"""
    btn = find_continue(driver)
    if btn:
        try: btn.click()
        except:
            try: driver.execute_script("arguments[0].click()", btn)
            except: pass
    time.sleep(2.0) # Chờ 2 giây cố định cho trang xử lý lệnh gửi
    
    # Kiểm tra xem ảnh Captcha còn tồn tại trên giao diện không
    if find_captcha_img(driver) is None:
        return "success" # Đã chuyển trang thành công!
        
    try: 
        b = driver.find_element(By.TAG_NAME, "body").text.lower()
    except: 
        b = ""
    if any(k in b for k in ["không chính xác", "khong chinh xac", "thử lại", "sai mã", "incorrect", "try again"]):
        return "wrong_captcha"
        
    return "still_here" # Chưa chuyển trang và cũng chưa báo lỗi (có thể bị lag)

def solve_captcha_loop(driver, serial):
    """Vòng lặp khóa cứng: Bắt buộc giải bao giờ ĐÚNG và CHUYỂN TRANG mới cho đi tiếp"""
    for attempt in range(CAPTCHA_TRIES):
        img = find_captcha_img(driver)
        cin = find_captcha_input(driver)
        
        if not img or not cin:
            time.sleep(1)
            continue
            
        try: png = img.screenshot_as_png
        except: return False
        
        # Đọc captcha và ép lên IN HOA toàn bộ
        text = re.sub(r'[^A-Za-z0-9]', '', _ocr.classification(png) or '').upper()
        if not text or len(text) < 3:
            refresh_captcha(driver); time.sleep(1.2); continue
            
        print(f"   captcha OCR (lan {attempt+1}/{CAPTCHA_TRIES}): {text}")
        
        # Gõ mượt mà vào ô nhập mã
        smooth_type(cin, text)
        
        # Gửi và kiểm tra kết quả phản hồi của trang
        status = submit_and_check(driver)
        if status == "success":
            return True # Thành công vượt qua!
            
        # Nếu thất bại (Sai mã hoặc trang bị đơ lag cũ), tiến hành refresh lấy mã mới để nhập lại
        refresh_captcha(driver)
        time.sleep(1.2)
        
        # Điền lại số Serial vào ô nhập nhỡ đâu bị trang xóa mất chữ
        s_box = find_serial_input(driver, timeout=3)
        if s_box:
            current_val = s_box.get_attribute("value") or ""
            if current_val != serial:
                smooth_type(s_box, serial)
    return False

def looks_banned(driver):
    try: b=driver.find_element(By.TAG_NAME,"body").text.lower()
    except: return False
    return any(k in b for k in ["access denied","forbidden","reference #","unusual activity","too many requests"])

def handle(driver,serial,i,total):
    try:
        print(f"\n({i}/{total}) Serial: {serial}")
        try:
            driver.get("https://checkcoverage.apple.com/?locale=vi_VN")
        except:
            try: driver.execute_script("window.stop();")
            except: pass
            
        el=find_serial_input(driver,timeout=20)
        if not el:
            if looks_banned(driver):
                print("   !! Co dau hieu BI BAN/CHAN IP"); return Result(serial,"BANNED","","","")
            print("Khong tim duoc o serial"); return Result(serial,"ERROR","","","")
            
        # Điền mượt số Serial máy
        smooth_type(el, serial)
        
        # Chạy vòng lặp khóa cứng bắt buộc nhập đúng captcha mới thoát ra
        passed = solve_captcha_loop(driver, serial) if AUTO_CAPTCHA else None
        if passed is not True:
            if UNATTENDED:
                print("   (OCR {0} lan khong qua -> doi IP / RETRY)".format(CAPTCHA_TRIES))
                return Result(serial,"RETRY","","","")
            input(">> Vui long tu nhap tay o Chrome roi an Enter o day...")
            
        # --- ĐỌC KẾT QUẢ CUỐI CÙNG ---
        time.sleep(2.0) 
        body=driver.find_element(By.TAG_NAME,"body").text.lower()
        
        if any(k in body for k in ["ngày mua không hợp lệ", "thiết bị chưa được kích hoạt", "ngày mua chưa được xác nhận"]):
            print("Trang thai: Unactivated"); return Result(serial,"Unactivated",find_model(driver),"","")
            
        pur=exp=""
        for line in driver.find_element(By.TAG_NAME,"body").text.split('\n'):
            if any(k in line.lower() for k in ["đã mua", "ngày mua"]):
                pur=find_date(line)
            if any(k in line.lower() for k in ["hết hạn", "dự kiến hết hạn"]):
                exp=find_date(line)
                
        mdl=find_model(driver)
        if pur or exp or mdl:
            print(f"Trang thai: Activated | Model: {mdl} | Mua: {pur} | Het han: {exp}")
            return Result(serial,"Activated",mdl,pur,exp)
            
        print("Khong xac dinh (Unknown)"); return Result(serial,"Unknown",mdl,"","")
    except Exception as e:
        print("Loi xu ly:",e); return Result(serial,"ERROR","","","")

def main():
    serials=read_serials(INPUT_FILE)
    if not serials: print("Khong co serial nao trong serials.xlsx"); return
    print("Da nap",len(serials),"serial")
    i,results=load_progress()
    
    retry_count={}; MAX_RETRY=3
    cur_proxy = fetch_rotating_proxy() if (PROXY_API_URL or PROXY_KEY) else (PROXY or None)
    d=create_browser(cur_proxy)
    done_on_ip=0
    
    while i<len(serials):
        try:
            if done_on_ip>=BATCH_PER_IP:
                try: d.quit()
                except: pass
                print(f"\n>>> Chuyen doi IP sau khi check xong {BATCH_PER_IP} serial.")
                if (PROXY_API_URL or PROXY_KEY):
                    new=fetch_rotating_proxy()      
                    if new: cur_proxy=new
                elif PROXY:
                    time.sleep(3)
                else:
                    print(">>> DOI IP VPN -> roi an Enter...")
                    input()
                d=create_browser(cur_proxy); done_on_ip=0
                time.sleep(3)

            r=handle(d,serials[i],i+1,len(serials))

            if r.status in ("BANNED","RETRY"):
                retry_count[serials[i]]=retry_count.get(serials[i],0)+1
                if retry_count[serials[i]]>MAX_RETRY:
                    print(f"   !! Bo qua serial {serials[i]} do loi chuoi")
                    r=Result(serials[i],"Unknown","","",""); results.append(r); i+=1; done_on_ip+=1
                    save_progress(i,results); save_results(results)
                    continue
                try: d.quit()
                except: pass
                if (PROXY_API_URL or PROXY_KEY):
                    new=fetch_rotating_proxy()
                    if new: cur_proxy=new
                else:
                    print(">>> DOI IP VPN -> roi an Enter...")
                    input()
                d=create_browser(cur_proxy); done_on_ip=0
                time.sleep(3)
                continue   

            results.append(r); i+=1; done_on_ip+=1
            save_progress(i,results)
            save_results(results)              
            if i<len(serials): human_pause(d)   
        except Exception as e:
            print("Loi cap nhat:",e)
            try: d.quit()
            except: pass
            time.sleep(4)
            d=create_browser(cur_proxy); done_on_ip=0
            
    try: d.quit()
    except: pass
    save_results(results)
    if os.path.exists(PROGRESS_FILE): os.remove(PROGRESS_FILE)
    print("\n===== HOAN THANH TASK =====")

if __name__=="__main__": main()
