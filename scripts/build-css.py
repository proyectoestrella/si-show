#!/usr/bin/env python3
"""Une y minifica las hojas de estilo de la portada en sishow.css (1 petición en vez de 9).
Uso (desde la raíz del repo):  python3 scripts/build-css.py
Si editas theme/style/hero-tablet/reunion/block-c/wa/iteracion/movil/movil-demos .css,
vuelve a ejecutarlo y sube el número de ?v= del <link> de sishow.css en index.html.
Todas las url() son relativas a la raíz (solo theme.css las usa), por eso el paquete vive en la raíz."""
import re, pathlib
ROOT = pathlib.Path(__file__).resolve().parent.parent
ORDEN = ["theme.css", "style.css", "assets/hero-tablet.css", "reunion.css", "block-c.css",
         "wa.css", "iteracion.css", "movil.css", "movil-demos.css"]
def mini(s):
    s = re.sub(r"/\*.*?\*/", "", s, flags=re.S)
    s = re.sub(r"\s+", " ", s)
    s = re.sub(r"\s*([{};])\s*", r"\1", s)
    s = re.sub(r";}", "}", s)
    return s.strip()
out = "/* siShow · paquete generado por scripts/build-css.py. No editar a mano. */\n"
for f in ORDEN:
    out += mini((ROOT / f).read_text(encoding="utf-8"))
(ROOT / "sishow.css").write_text(out + "\n", encoding="utf-8")
print("sishow.css", len(out.encode()), "bytes")
