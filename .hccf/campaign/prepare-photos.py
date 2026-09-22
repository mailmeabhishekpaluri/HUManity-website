"""Extract only the selected homes' photographs; never publish the full audit."""
from pathlib import Path
import argparse, json
from pypdf import PdfReader
from PIL import ImageOps

HERE=Path(__file__).resolve().parent
parser=argparse.ArgumentParser()
parser.add_argument('audit',type=Path)
parser.add_argument('--output',type=Path,default=HERE/'deploy/assets/hccf-homes')
args=parser.parse_args()
reader=PdfReader(args.audit)
homes=json.loads((HERE/'homes.json').read_text())
count=0
for home in homes:
    target=args.output/home['id'];target.mkdir(parents=True,exist_ok=True)
    for index,(source,caption) in enumerate(home['photos']):
        page=int(source[1:3])-1;name='/'+source.split('-',1)[1].rsplit('.',1)[0]
        image=ImageOps.exif_transpose(reader.pages[page].images[name].image).convert('RGB')
        for width in [640,1200]:
            copy=image.copy();copy.thumbnail((width,width*2))
            copy.save(target/f'{index+1}-{width}.webp','WEBP',quality=82,method=6)
        if index==0:
            copy=image.copy();copy.thumbnail((1200,1200))
            copy.save(target/'social.jpg','JPEG',quality=86,optimize=True)
        count+=1
print(f'Prepared {count} verified field photographs for {len(homes)} homes.')
