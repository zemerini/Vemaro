from PIL import Image
from collections import Counter

def get_dominant_colors(image_path, num_colors=3):
    image = Image.open(image_path).convert('RGBA')
    pixels = image.getdata()
    
    valid_pixels = []
    for p in pixels:
        if p[3] > 0 and not (p[0] > 240 and p[1] > 240 and p[2] > 240) and not (p[0] < 15 and p[1] < 15 and p[2] < 15):
            valid_pixels.append((p[0], p[1], p[2]))
            
    if not valid_pixels:
        print("No valid dominant color found (only transparent or white/black).")
        return
        
    counts = Counter(valid_pixels)
    colors = counts.most_common(num_colors)
    for c, count in colors:
        print(f"#{c[0]:02x}{c[1]:02x}{c[2]:02x}")

get_dominant_colors('Bilder/Logo.png')
