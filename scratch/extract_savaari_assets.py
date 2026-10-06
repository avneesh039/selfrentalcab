import urllib.request
import re
import json

url = 'https://www.savaari.com/'
headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
req = urllib.request.Request(url, headers=headers)
try:
    with urllib.request.urlopen(req) as resp:
        html = resp.read().decode('utf-8', errors='ignore')
        print("HTML length:", len(html))
        
        # Find all image sources
        imgs = re.findall(r'(?:src|href|url)\s*=\s*["\']([^"\']+\.(?:png|jpg|jpeg|webp|svg))["\']', html, re.IGNORECASE)
        print("Images found in HTML:", len(imgs))
        for img in set(imgs):
            print("IMG:", img)
            
        # Find script files
        scripts = re.findall(r'src=["\']([^"\']+\.js)["\']', html)
        print("Scripts:", scripts)
        
        # Fetch main script to find assets
        for s in scripts:
            if 'main.' in s:
                s_url = 'https://www.savaari.com/' + s.lstrip('/')
                print("Fetching main bundle:", s_url)
                s_req = urllib.request.Request(s_url, headers=headers)
                with urllib.request.urlopen(s_req) as s_resp:
                    bundle = s_resp.read().decode('utf-8', errors='ignore')
                    print("Bundle length:", len(bundle))
                    # Find assets in bundle
                    assets = re.findall(r'assets/[^\'"\s)]+\.(?:png|webp|svg|jpg)', bundle)
                    print("Assets found in main bundle:", len(assets))
                    unique_assets = sorted(list(set(assets)))
                    for a in unique_assets[:50]:
                        print("ASSET:", a)
except Exception as e:
    print("Error:", e)
