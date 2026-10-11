# Clean ResellStreet logo variants from assets/brand/Logo.png (opaque white background, white halos).
# Usage (needs pillow + numpy): python3 tools/brand-logo.py assets/brand/Logo.png assets/brand
# Writes logo-{240,480,720}.webp and logo-light-*.webp (+ 1200px PNGs: delete them, they aren't used).
# Each pixel = alpha * brand colour + (1 - alpha) * white; solve alpha for navy and sand, keep the better fit,
# re-emit pure brand colour + alpha. Footer variant: navy → white.
import sys
from PIL import Image
import numpy as np
SRC, OUT = sys.argv[1], sys.argv[2]
NAVY = np.array([0x1c, 0x25, 0x3b], float); SAND = np.array([0xc1, 0xa5, 0x84], float); WHITE = 255.0
im = np.asarray(Image.open(SRC).convert('RGB'), float)
def solve(C):
    d = WHITE - C                      # per channel
    a = ((WHITE - im) * d).sum(-1) / (d * d).sum()   # least squares alpha
    a = np.clip(a, 0, 1)
    err = (((WHITE - a[..., None] * d) - im) ** 2).sum(-1)
    return a, err
an, en = solve(NAVY); as_, es = solve(SAND)
is_navy = en <= es
alpha = np.where(is_navy, an, as_)
alpha[alpha < 0.06] = 0                # paper noise / halo
alpha = np.clip((alpha - 0.06) / 0.9, 0, 1)
ys, xs = np.nonzero(alpha > 0.02)
pad = 4; y0, y1, x0, x1 = max(ys.min() - pad, 0), ys.max() + pad + 1, max(xs.min() - pad, 0), xs.max() + pad + 1
alpha, is_navy = alpha[y0:y1, x0:x1], is_navy[y0:y1, x0:x1]
def render(navy_rgb, sand_rgb):
    rgb = np.where(is_navy[..., None], np.array(navy_rgb, float), np.array(sand_rgb, float))
    return Image.fromarray(np.dstack([rgb, alpha * 255]).round().astype('uint8'), 'RGBA')
variants = {'logo': (NAVY, SAND), 'logo-light': ([255, 255, 255], SAND)}
H, W = alpha.shape; print('trimmed', W, 'x', H, 'ratio', round(W / H, 2))
for name, (n, s) in variants.items():
    full = render(n, s)
    for w in (240, 480, 720):
        h = round(H * w / W)
        full.resize((w, h), Image.LANCZOS).save(f'{OUT}/{name}-{w}.webp', 'WEBP', lossless=True, method=6)
    full.resize((1200, round(H * 1200 / W)), Image.LANCZOS).save(f'{OUT}/{name}-1200.png', optimize=True)
