"""Build the approved homepage into a separate preview/release directory."""
from pathlib import Path
import argparse
import hashlib
import json
import re
from homepage import build_assets, patch_bundle, update_index

HERE = Path(__file__).resolve().parent
parser = argparse.ArgumentParser()
parser.add_argument('--site-root', type=Path, default=HERE.parent.parent)
parser.add_argument('--output', type=Path, default=Path('/tmp/humanity-conference-preview'))
args = parser.parse_args()
index = (args.site_root / 'index.html').read_text()
base_asset = re.search(r'/assets/(index-(?:hccf-[a-f0-9]+|D3rKJrP3)\.js)', index).group(1)
patched = patch_bundle((args.site_root / 'assets' / base_asset).read_text())
css_name, files = build_assets(args.output)
bundle_name = 'index-hccf-' + hashlib.sha256(patched.encode()).hexdigest()[:10] + '.js'
(args.output / 'assets' / bundle_name).write_text(patched)
(args.output / 'index.html').write_text(update_index(index, bundle_name, css_name))
report = {'baselineBundle': base_asset, 'bundle': bundle_name, 'css': css_name,
          'replacedSlots': ['home.tsx:144 (conference hero)', 'home.tsx:211 (Mission 2030 video)'],
          'preserved': 'Other homepage sections and route components are unchanged. Navigation gains accessible labels and responsive styling.',
          'publicFiles': ['index.html', 'assets/' + bundle_name] + files}
(args.output / 'preview-manifest.json').write_text(json.dumps(report, indent=2))
print(json.dumps(report, indent=2))
