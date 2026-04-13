"""
Gera public/images/appdoce-logo.png — texto estilo landing (Pacifico + gradiente) em fundo azul escuro.
Execute: py -3 tools/generate_appdoce_logo_png.py
"""
from __future__ import annotations

import os
import urllib.request

from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FONT_URL = "https://fonts.gstatic.com/s/pacifico/v23/FwZY7-Qmy14u9lezJ96A.ttf"
FONT_PATH = os.path.join(ROOT, "public", "fonts", "pacifico.ttf")
OUT_PATH = os.path.join(ROOT, "public", "images", "appdoce-logo.png")

# Gradiente da .brand-logo na landing (90deg): #c4b5fd -> #67e8f9
C1 = (196, 181, 253)
C2 = (103, 232, 249)
# Fundo azul escuro (sólido)
BG = (11, 31, 58)  # ~#0b1f3a

TEXT = "appdoce"


def ensure_font() -> None:
    os.makedirs(os.path.dirname(FONT_PATH), exist_ok=True)
    if os.path.isfile(FONT_PATH) and os.path.getsize(FONT_PATH) > 1000:
        return
    urllib.request.urlretrieve(FONT_URL, FONT_PATH)


def main() -> None:
    ensure_font()
    os.makedirs(os.path.dirname(OUT_PATH), exist_ok=True)

    w, h = 920, 280
    base = Image.new("RGB", (w, h), BG)

    font = ImageFont.truetype(FONT_PATH, 128)

    mask = Image.new("L", (w, h), 0)
    mdraw = ImageDraw.Draw(mask)
    left, top, right, bottom = mdraw.textbbox((0, 0), TEXT, font=font)
    tw, th = right - left, bottom - top
    x = (w - tw) // 2 - left
    y = (h - th) // 2 - top
    mdraw.text((x, y), TEXT, font=font, fill=255)

    grad = Image.new("RGB", (w, h))
    px = grad.load()
    for i in range(w):
        t = i / max(w - 1, 1)
        r = int(C1[0] + (C2[0] - C1[0]) * t)
        g = int(C1[1] + (C2[1] - C1[1]) * t)
        b = int(C1[2] + (C2[2] - C1[2]) * t)
        for j in range(h):
            px[i, j] = (r, g, b)

    out = Image.composite(grad, base, mask)
    out.save(OUT_PATH, "PNG", optimize=True)
    print(f"OK: {OUT_PATH}")


if __name__ == "__main__":
    main()
