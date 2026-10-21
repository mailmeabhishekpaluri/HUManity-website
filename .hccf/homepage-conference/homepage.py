"""Reusable, narrowly scoped homepage patch used by preview and release builds."""
from pathlib import Path
import hashlib
import json
import re

HERE = Path(__file__).resolve().parent


def patch_bundle(bundle):
    start = bundle.index('function vae()')
    end = bundle.index('}const Nae=', start) + 1
    original = bundle[start:end]
    if 'N.createElement(HC27ConferenceHero)' in original:
        assert 'N.createElement(HC27Mission)' in original
        home = original
        prefix = bundle[:bundle.index('const HC27_HERO=')]
    else:
        hs = original.index('u.jsxDEV("section",{"data-loc":"client/src/pages/home.tsx:144"')
        he = original.index(',u.jsxDEV("section",{"data-loc":"client/src/pages/home.tsx:169"', hs)
        ms = original.index('u.jsxDEV(xae,{"data-loc":"client/src/pages/home.tsx:211"')
        me = original.index(',u.jsxDEV(xK,{"data-loc":"client/src/pages/home.tsx:213"', ms)
        home = (original[:hs] + 'N.createElement(HC27ConferenceHero)' + original[he:ms]
                + 'N.createElement(HC27Mission)' + original[me:])
        assert home[home.index('home.tsx:169'):home.index('N.createElement(HC27Mission)')] == original[original.index('home.tsx:169'):ms]
        assert home[home.index('home.tsx:213'):] == original[original.index('home.tsx:213'):]
        prefix = bundle[:start]
    assert 'Su.mainHero' not in home and 'Su.mission2030' not in home
    sources = {'HC27_HERO': 'hero.html', 'HC27_MISSION': 'mission.html',
               'HC27_CONFERENCE_DETAILS': 'conference-details.html', 'HC27_MISSION_DETAILS': 'mission-details.html'}
    constants = '\n'.join('const ' + name + '=' + json.dumps((HERE / file).read_text(), ensure_ascii=False) + ';'
                          for name, file in sources.items())
    helpers = constants + '\n' + (HERE / 'components.js').read_text() + '\n'
    patched = prefix + helpers + home + bundle[end:]
    # Name the existing icon-only menu and drawer without changing their items.
    for line, label in [(128, 'Open menu'), (132, 'Main navigation')]:
        marker = '"data-loc":"client/src/components/layout.tsx:' + str(line) + '",'
        assert patched.count(marker) == 1
        if marker + '"aria-label":' not in patched:
            patched = patched.replace(marker, marker + '"aria-label":' + json.dumps(label) + ',', 1)
    return patched


def build_assets(output):
    from PIL import Image, ImageOps
    output = Path(output)
    target = output / 'assets/homepage-conference'
    target.mkdir(parents=True, exist_ok=True)
    files = []
    for name, source in [('mission-path', 'mission-path.png'),
                         ('conference-silhouette', 'conference-dialogue-silhouette.png')]:
        with Image.open(HERE / source) as image:
            image = ImageOps.exif_transpose(image).convert('RGBA')
            for width in (640, 1200):
                path = target / f'{name}-{width}.webp'
                image.resize((width, round(image.height * width / image.width)), Image.Resampling.LANCZOS).save(path, quality=87, method=6)
                files.append(str(path.relative_to(output)))
    css = (HERE / 'sections.css').read_bytes()
    css_name = 'home-conference-' + hashlib.sha256(css).hexdigest()[:10] + '.css'
    (output / 'assets' / css_name).write_bytes(css)
    return css_name, ['assets/' + css_name] + files


def update_index(index, bundle_name, css_name):
    index = re.sub(r'/assets/index-(?:hccf-[a-f0-9]+|D3rKJrP3)\.js', '/assets/' + bundle_name, index)
    index = re.sub(r'<link rel="stylesheet" href="/assets/home-conference-[a-f0-9]+\.css">\s*', '', index)
    return index.replace('</head>', '<link rel="stylesheet" href="/assets/' + css_name + '">\n</head>')
