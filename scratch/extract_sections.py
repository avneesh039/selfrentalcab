import urllib.request
import re

url = 'https://www.savaari.com/main.253119a7521ed4e3.js'
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
try:
    with urllib.request.urlopen(req) as resp:
        content = resp.read().decode('utf-8', errors='ignore')
        
        # Search for section headings, texts, badges
        headings = re.findall(r'<h[1-4][^>]*>([^<]+)</h[1-4]>', content)
        print("HTML Headings found:", len(headings))
        for h in headings[:30]:
            print("H:", h.strip())
            
        # Search for svg icons or icon names
        svgs = re.findall(r'<svg[^>]*>.*?</svg>', content, re.DOTALL)
        print("Inline SVGs found:", len(svgs))
        
        # Search for text like "Why Savaari", "Ride with", "Cab Booking", "Fleet"
        sections = re.findall(r'["\']([A-Z][a-zA-Z\s]{4,40})["\']', content)
        relevant = [s for s in sections if any(k in s.lower() for k in ['savaari', 'cab', 'taxi', 'outstation', 'car', 'chauffeur', 'trust', 'fleet', 'driver', 'rating', 'clean'])]
        print("Relevant text snippets:", len(relevant))
        for r in sorted(list(set(relevant)))[:40]:
            print("TXT:", r)
except Exception as e:
    print("Error:", e)
