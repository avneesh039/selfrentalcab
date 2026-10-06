import urllib.request
import re

url = 'https://www.savaari.com/main.253119a7521ed4e3.js'
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
try:
    with urllib.request.urlopen(req) as resp:
        content = resp.read().decode('utf-8', errors='ignore')
        
        # Search for CDN URLs
        cdns = re.findall(r'https?://[^\s"\'<>]+\.(?:png|webp|svg|jpg|jpeg)', content)
        print("CDN images found:", len(cdns))
        for c in sorted(list(set(cdns)))[:50]:
            print("CDN_IMG:", c)
            
        # Search for vehicle models / cars
        cars = re.findall(r'assets/[^\s"\'<>]*(?:car|sedan|suv|crysta|ertiga|etios|dzire|vehicle|tempo|taxi)[^\s"\'<>]*\.(?:png|webp|svg|jpg)', content, re.IGNORECASE)
        print("Car assets:", set(cars))
        
        # Search for colors
        hex_colors = re.findall(r'#[0-9a-fA-F]{6}', content)
        from collections import Counter
        print("Top colors:", Counter(hex_colors).most_common(20))
except Exception as e:
    print("Error:", e)
