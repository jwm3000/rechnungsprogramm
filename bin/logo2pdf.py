"""Wandelt das potrace-SVG-Logo in einen PDF-Pfad (absolute Rohkoordinaten, y nach oben) um."""
import re, sys
svg = open(sys.argv[1]).read()
d = re.search(r' d="([^"]+)"', svg).group(1)
tok = re.findall(r'[MmLlCcZz]|-?\d+(?:\.\d+)?', d)
out, i, cmd, x, y, sx, sy = [], 0, None, 0.0, 0.0, 0.0, 0.0
def num():
    global i
    v = float(tok[i]); i += 1; return v
f = lambda v: ('%.1f' % v).rstrip('0').rstrip('.')
while i < len(tok):
    t = tok[i]
    if re.match(r'[A-Za-z]', t):
        cmd = t; i += 1
        if cmd in 'Zz':
            out.append('h'); x, y = sx, sy; continue
    if cmd in 'Mm':
        a, b = num(), num()
        if cmd == 'm': a += x; b += y
        x, y, sx, sy = a, b, a, b
        out.append(f'{f(x)} {f(y)} m'); cmd = 'l' if cmd == 'm' else 'L'
    elif cmd in 'Ll':
        a, b = num(), num()
        if cmd == 'l': a += x; b += y
        x, y = a, b; out.append(f'{f(x)} {f(y)} l')
    elif cmd in 'Cc':
        p = [num() for _ in range(6)]
        if cmd == 'c': p = [p[0]+x, p[1]+y, p[2]+x, p[3]+y, p[4]+x, p[5]+y]
        x, y = p[4], p[5]; out.append(' '.join(f(v) for v in p) + ' c')
print("<?php\n// Erzeugt von bin/logo2pdf.py aus assets/logo.svg – Logo als PDF-Pfad (Rohkoordinaten 0..40480 × 0..8680, y nach oben).\nreturn '" + ' '.join(out) + "';")
