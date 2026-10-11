# "RS" monogram: sand Montserrat 800 letters on a navy square. Glyph outlines come from the theme's font file,
# so the icon matches the headings/logo. Usage (needs fonttools + brotli):
#   python3 tools/brand-favicon.py assets/fonts/montserrat-800-latin.woff2 assets/brand
# Writes favicon.svg; the PNGs (favicon-32, apple-touch-icon 180, site-icon-512) are that SVG without the
# rounded corners, rendered in Chromium.
import sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.boundsPen import BoundsPen
font = TTFont(sys.argv[1]); gs = font.getGlyphSet(); cmap = font.getBestCmap()
NAVY, SAND, SIZE = '#1c253b', '#c1a584', 512
names = [cmap[ord(c)] for c in 'RS']
track = -10  # font units: letters close, like the wordmark
# Lay out the glyphs, measure the ink box.
x = 0; parts = []; xmin = ymin = 10**9; xmax = ymax = -10**9
for n in names:
    bp = BoundsPen(gs); gs[n].draw(bp); b = bp.bounds
    xmin, ymin, xmax, ymax = min(xmin, x + b[0]), min(ymin, b[1]), max(xmax, x + b[2]), max(ymax, b[3])
    pen = SVGPathPen(gs); gs[n].draw(pen); parts.append((x, pen.getCommands()))
    x += gs[n].width + track
w, h = xmax - xmin, ymax - ymin
scale = (SIZE * 0.64) / w          # ink width 64% of the icon (legible at 16–32px)
tx = (SIZE - w * scale) / 2 - xmin * scale
ty = (SIZE + h * scale) / 2 + ymin * scale   # font y-up → svg y-down
paths = ''.join(f'<path transform="translate({ox} 0)" d="{d}"/>' for ox, d in parts)
svg = (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {SIZE} {SIZE}">'
       f'<rect width="{SIZE}" height="{SIZE}" rx="96" fill="{NAVY}"/>'
       f'<g fill="{SAND}" transform="translate({tx:.2f} {ty:.2f}) scale({scale:.5f} {-scale:.5f})">{paths}</g></svg>')
open(f'{sys.argv[2]}/favicon.svg', 'w').write(svg)
print('svg bytes', len(svg))
