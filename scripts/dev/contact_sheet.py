"""Tile screenshots into labelled contact sheets for quick visual review.

usage: python contact_sheet.py OUT_PREFIX CROP_HEIGHT COLS SCALE file1.png file2.png ...
"""
import sys

from PIL import Image, ImageDraw

out, crop_h, cols, scale = sys.argv[1], int(sys.argv[2]), int(sys.argv[3]), float(sys.argv[4])
files = sys.argv[5:]
per = cols * 2
for sheet in range(0, len(files), per):
    group = files[sheet:sheet + per]
    tiles = []
    for f in group:
        im = Image.open(f).convert("RGB")
        im = im.crop((0, 0, im.width, min(crop_h, im.height)))
        im = im.resize((int(im.width * scale), int(im.height * scale)), Image.LANCZOS)
        tiles.append((f.replace("\\", "/").split("/")[-1], im))
    w = max(t.width for _, t in tiles)
    h = max(t.height for _, t in tiles) + 22
    rows = (len(tiles) + cols - 1) // cols
    canvas = Image.new("RGB", (w * cols + 10 * (cols - 1), h * rows + 10 * (rows - 1)), (120, 120, 120))
    for i, (name, t) in enumerate(tiles):
        x, y = (i % cols) * (w + 10), (i // cols) * (h + 10)
        canvas.paste(Image.new("RGB", (w, h), (255, 255, 255)), (x, y))
        ImageDraw.Draw(canvas).text((x + 6, y + 5), name, fill=(200, 0, 0))
        canvas.paste(t, (x, y + 22))
    path = f"{out}-{sheet // per + 1}.png"
    canvas.save(path)
    print(path)
