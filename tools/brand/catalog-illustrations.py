"""
Иллюстрации карточек каталога в стиле брендбука «Периметр» (docs/50-brand-system.md, раздел 6.3).

Плоская сцена 4:3: небо brand-50, дом и дерево в глубине, газон brand-100, изделие цветом RAL, фирменные полосы
«лестницей» у левого края. Текста на картинке нет — название, цена и характеристики в карточке (portfolio).

Запуск: python3 tools/brand/catalog-illustrations.py  →  docs/brand/catalog/<slug>.svg
PNG 1600×1200 для медиатеки: node tools/brand/render-catalog-png.mjs (нужен Chromium Playwright).
Цвета RAL и бренда — значения токенов assets/shared/styles/theme.css: это картинка, а не интерфейс.
"""
import os

OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'docs', 'brand', 'catalog')
W, H = 800, 600

T = dict(brand50='#eef6ec', brand100='#d9edd5', brand200='#b6dcaf', brand300='#8cc783', brand500='#45a63d',
         brand700='#2d7f27', brand900='#1a4f17', ink='#23272d', graphite='#575c63', surface='#f3f5f2',
         surface_strong='#e9ece8', line='#dce0db', line_strong='#c9cec8', night='#2a2f35', white='#ffffff')
RAL = {'7024': '#373f43', '8017': '#45322e', '3005': '#5e2028', '5005': '#1e4a84', '6005': '#0f4336', '9005': '#16181b'}


def blend(c, other, t):
    a = [int(c[i:i + 2], 16) for i in (1, 3, 5)]
    b = [int(other[i:i + 2], 16) for i in (1, 3, 5)]
    return '#' + ''.join(f'{round(x + (y - x) * t):02x}' for x, y in zip(a, b))


def light(c, t): return blend(c, '#ffffff', t)
def dark(c, t): return blend(c, '#000000', t)


def rect(x, y, w, h, fill, rx=0, extra=''):
    return f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" rx="{rx}" fill="{fill}" {extra}/>'


def poly(pts, fill, extra=''):
    return f'<polygon points="{" ".join(f"{x:.1f},{y:.1f}" for x, y in pts)}" fill="{fill}" {extra}/>'


def stripes(x, y, h, lengths, color, side='left', opacity=1.0):
    """Фирменная «лестница»: полосы толщиной h, шаг 2h, скос 45°, прижаты к краю (side)."""
    out = []
    for i, length in enumerate(lengths):
        yy = y + i * 2 * h
        if side == 'left':
            pts = [(x, yy), (x + length, yy), (x + length - h, yy + h), (x, yy + h)]
        else:
            pts = [(x, yy), (x - length, yy), (x - length + h, yy + h), (x, yy + h)]
        out.append(poly(pts, color, f'opacity="{opacity}"'))
    return ''.join(out)


# ---------- Изделия: вид спереди, в прямоугольнике (x, y, w, h) ----------

def post(x, y, w, h, c, cap=True):
    s = rect(x, y, w, h, c) + rect(x + w * 0.62, y, w * 0.38, h, dark(c, .22)) + rect(x, y, w * 0.18, h, light(c, .12))
    if cap:
        s += rect(x - 3, y - 7, w + 6, 9, dark(c, .35), 2)
    return s


def jaluzi(x, y, w, h, c):
    """Жалюзи: наклонные ламели в П-планке, светлая верхняя грань и тень снизу."""
    s, step = [], 22
    s.append(rect(x, y, w, h, dark(c, .45)))
    yy = y + 10
    while yy < y + h - 12:
        s.append(poly([(x, yy), (x + w, yy), (x + w, yy + 15), (x, yy + 15)], c))
        s.append(rect(x, yy, w, 6, light(c, .34)))
        s.append(rect(x, yy + 13, w, 3, dark(c, .3)))
        yy += step
    s.append(rect(x, y, w, 10, dark(c, .2)) + rect(x, y + h - 10, w, 10, dark(c, .2)))
    return ''.join(s)


def rancho(x, y, w, h, c):
    """Ранчо: горизонтальные планки 40 px с равными просветами на всю высоту."""
    s, plank = [], 40
    n = max(2, int((h + 12) // (plank + 12)))
    gap = (h - n * plank) / (n - 1)
    for i in range(n):
        yy = y + i * (plank + gap)
        s.append(rect(x, yy, w, plank, c, 3))
        s.append(rect(x, yy, w, 5, light(c, .2), 3))
        s.append(rect(x, yy + plank - 5, w, 5, dark(c, .28), 3))
        s.append(rect(x, yy + plank / 2 - 1, w, 2, dark(c, .14)))
    return ''.join(s)


def shtaketnik(x, y, w, h, c):
    """Евроштакетник: планки с полукруглым верхом на двух лагах, рёбра жёсткости — светлая и тёмная полосы."""
    s, pw, gap = [], 34, 17
    rail = dark(c, .3)
    s.append(rect(x, y + h * 0.22, w, 12, rail) + rect(x, y + h * 0.72, w, 12, rail))
    xx = x + 6
    while xx + pw <= x + w:
        d = f'M{xx} {y + h} V{y + 16} Q{xx} {y} {xx + pw / 2} {y} Q{xx + pw} {y} {xx + pw} {y + 16} V{y + h} Z'
        s.append(f'<path d="{d}" fill="{c}"/>')
        s.append(rect(xx + 4, y + 14, 5, h - 14, light(c, .2)))
        s.append(rect(xx + pw * .5 - 2, y + 14, 4, h - 14, dark(c, .22)))
        s.append(rect(xx + pw - 6, y + 14, 6, h - 14, dark(c, .3)))
        xx += pw + gap
    return ''.join(s)


def profnastil(x, y, w, h, c):
    """Профнастил: трапециевидные гофры (скат, полка, скат) и П-планка сверху."""
    s, period = [], 48
    s.append(rect(x, y, w, h, c))
    xx = x
    while xx < x + w - 1:
        s.append(rect(xx + 26, y, 5, h, dark(c, .3)))
        s.append(rect(xx + 31, y, 10, h, light(c, .2)))
        s.append(rect(xx + 41, y, 5, h, dark(c, .18)))
        xx += period
    s.append(rect(x - 4, y - 10, w + 8, 14, dark(c, .25), 2))
    return ''.join(s)


def mesh(x, y, w, h, c):
    """3D сетка: вертикальные прутки с двумя V-изгибами, горизонтальные прутки, зубцы снизу."""
    s = []
    folds = [y + h * 0.3, y + h * 0.68]
    xx = x + 10
    while xx <= x + w - 4:
        d = f'M{xx} {y} '
        for f in folds:
            d += f'V{f - 8} L{xx + 7} {f} L{xx} {f + 8} '
        d += f'V{y + h + 16}'
        s.append(f'<path d="{d}" fill="none" stroke="{c}" stroke-width="3.2" stroke-linejoin="round"/>')
        xx += 22
    yy = y
    while yy <= y + h:
        s.append(f'<path d="M{x} {yy:.1f} H{x + w}" stroke="{c}" stroke-width="3.2"/>')
        yy += h / 5
    for f in folds:
        s.append(rect(x, f - 2, w, 4, light(c, .18), 0, 'opacity=".6"'))
    return ''.join(s)


def caps(x, y, w, h, c):
    """Столбы с заглушками: плоская, «домик» и круглая; одна заглушка «домик» лежит отдельно."""
    s = []
    plastic = T['ink']
    posts = [(x + w * .12, y + h * .28, 64, 'flat'), (x + w * .42, y + h * .08, 64, 'house'), (x + w * .72, y + h * .36, 56, 'round')]
    for px, py, pw, kind in posts:
        ph = y + h - py
        s.append(rect(px, py, pw, ph, c) + rect(px + pw * .62, py, pw * .38, ph, dark(c, .22)) + rect(px, py, pw * .16, ph, light(c, .14)))
        if kind == 'flat':
            s.append(rect(px - 5, py - 14, pw + 10, 16, plastic, 3, ''))
            s.append(rect(px - 5, py - 14, pw + 10, 4, light(plastic, .25), 3))
        elif kind == 'house':
            s.append(poly([(px - 5, py + 1), (px + pw / 2, py - 30), (px + pw + 5, py + 1)], plastic, ''))
            s.append(poly([(px + pw / 2, py - 30), (px + pw + 5, py + 1), (px + pw / 2, py + 1)], dark(plastic, .35)))
        else:
            s.append(f'<path d="M{px - 4} {py + 2} Q{px + pw / 2} {py - 26} {px + pw + 4} {py + 2} Z" fill="{plastic}" />')
    lx, ly = x + w * .5, y + h - 6
    s.append(poly([(lx, ly), (lx + 34, ly - 26), (lx + 68, ly)], plastic) + poly([(lx + 34, ly - 26), (lx + 68, ly), (lx + 34, ly)], dark(plastic, .35)))
    return ''.join(s)


PRODUCTS = [
    ('jaluzi', jaluzi, RAL['7024']),
    ('rancho', rancho, RAL['8017']),
    ('shtaketnik', shtaketnik, RAL['3005']),
    ('profnastil', profnastil, RAL['5005']),
    ('mesh', mesh, RAL['6005']),
    ('caps', caps, RAL['7024']),
]


def illustration(fn, c, slug):
    """Сцена с изделием: забор между двумя столбами у дома; заглушки — на трёх столбах разной высоты."""
    g = 520
    s = [rect(0, 0, W, H, T['brand50'])]
    s.append(rect(470, 200, 230, 180, T['surface_strong']) + poly([(452, 205), (585, 118), (718, 205)], T['line']))
    s.append(rect(505, 245, 46, 54, T['white']) + rect(620, 245, 46, 54, T['white']))
    s.append(f'<circle cx="150" cy="270" r="92" fill="{T["brand200"]}"/>' + rect(142, 320, 16, 200, T['line_strong']))
    s.append(rect(0, g, W, H - g, T['brand100']) + rect(0, g, W, 6, T['brand200']))
    if slug == 'caps':
        s.append(f'<g transform="translate(-130 -150) scale(1.35)">' + caps(150, 230, 600, g / 1.35 + 150 / 1.35 - 230 - 4, c) + '</g>')
    else:
        top = 112 if slug != 'mesh' else 132
        s.append(post(44, top - 10, 32, g - top + 10, dark(c, .1)) + post(724, top - 10, 32, g - top + 10, dark(c, .1)))
        s.append(fn(76, top, 648, g - top, c))
    s.append(stripes(0, 30, 14, [150, 122, 94], T['brand500'], 'left'))
    return s



def svg(body):
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">{"".join(body)}</svg>\n'


if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    for slug, fn, color in PRODUCTS:
        with open(os.path.join(OUT, slug + '.svg'), 'w') as f:
            f.write(svg(illustration(fn, color, slug)))
        print(os.path.join(OUT, slug + '.svg'))
