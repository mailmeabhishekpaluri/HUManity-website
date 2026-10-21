"""Build the foundation homepage. Run campaign/build.py afterwards for app routes."""
from pathlib import Path
from html import escape
import argparse, hashlib, json
from PIL import Image, ImageOps

HERE=Path(__file__).resolve().parent
parser=argparse.ArgumentParser()
parser.add_argument('--site-root',type=Path,default=HERE.parent.parent)
parser.add_argument('--output',type=Path,default=HERE/'deploy')
args=parser.parse_args(); SITE=args.site_root; OUT=args.output
OUT.mkdir(parents=True,exist_ok=True); (OUT/'assets').mkdir(exist_ok=True)
LOGO='/manus-storage/Logos-2_1766126722043_d1980132.png'
ORIGIN='https://humanityorg.foundation'
h=lambda text:escape(str(text),quote=True)
def asset(name):
    raw=(HERE/name).read_bytes(); filename='homepage-'+hashlib.sha256(raw).hexdigest()[:10]+Path(name).suffix
    (OUT/'assets'/filename).write_bytes(raw)
    return '/assets/'+filename
css=asset('homepage.css');js=asset('homepage.js')
photos={
 'together':('IMG_20251129_191259115_1766587851974_33f43f1e.jpg','Children and HUManity volunteers sharing their activity sheets.'),
 'mentoring':('IMG_6166_1766966728588_29ab304d.jpg','A HUManity volunteer working with children on a learning activity.'),
 'education':('Aug1_PSH7_1767167802826_8b823fb3.jpg','A volunteer helping children with their learning.'),
 'emotions':('IMG_20251129_191701331_HDR_1766587851974_b29c53d3.jpg','Children and volunteers displaying drawings about emotions.'),
 'digital':('IMG_20251101_184806303_HDR_1766588357290_924d4627.jpg','Children exploring a laptop with support from HUManity.'),
 'health':('IMG_1956_(1)_1766588561747_8e2dcb67.jpg','An outdoor health and nutrition awareness activity with children.'),
 'circle':('IMG_20251129_180815490_HDR_1766587851974_95887945.jpg','Children and volunteers taking part in a group activity.'),
}
photo_dir=OUT/'assets/homepage';photo_dir.mkdir(exist_ok=True)
for key,(source,_) in photos.items():
    with Image.open(SITE/'manus-storage'/source) as image:
        image=ImageOps.exif_transpose(image).convert('RGB')
        for width in (640,1200):
            size=(min(width,image.width),round(image.height*min(width,image.width)/image.width))
            image.resize(size,Image.Resampling.LANCZOS).save(photo_dir/f'{key}-{width}.webp',quality=83,method=6)
with Image.open(SITE/'manus-storage/Ind_1_1766966728588_4a515fe0.jpg') as image:
    image=ImageOps.exif_transpose(image).convert('RGB')
    ImageOps.fit(image,(1200,630),centering=(.5,.4)).save(photo_dir/'social.jpg',quality=85,optimize=True)
def photo(key,css='',eager=False):
    return f'<img class="{css}" src="/assets/homepage/{key}-1200.webp" srcset="/assets/homepage/{key}-640.webp 640w, /assets/homepage/{key}-1200.webp 1200w" sizes="(max-width: 700px) 100vw, 50vw" alt="{h(photos[key][1])}" width="1200" height="900" {"fetchpriority=high" if eager else "loading=lazy"} decoding="async">'
def icon(kind):
    paths={
      'heart':'<path d="M20 5c-3-3-7 0-8 2-1-2-5-5-8-2s-1 7 8 14c9-7 11-11 8-14Z"/>',
      'book':'<path d="M12 5v16M12 6C9 3 5 3 2 4v15c4-1 7 0 10 2 3-2 6-3 10-2V4c-3-1-7-1-10 2Z"/>',
      'screen':'<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 22h8m-4-5v5m-5-12 3 3 6-7"/>',
      'health':'<path d="M21 12h-5l-3-7-3 14-3-7H2"/>',
      'sun':'<circle cx="12" cy="12" r="4"/><path d="M12 1v2m0 18v2M1 12h2m18 0h2M4 4l2 2m12 12 2 2M4 20l2-2M18 6l2-2"/>',
      'people':'<circle cx="9" cy="7" r="3"/><path d="M3 21v-4a6 6 0 0 1 12 0v4M17 5a3 3 0 0 1 0 6m2 10v-4a5 5 0 0 0-2-4"/>',
      'arrow':'<path d="M4 12h16m-6-6 6 6-6 6"/>',
      'home':'<path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/>',
    }
    return f'<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">{paths[kind]}</svg>'
programs=[
 ('education','book','Education development','Confidence begins with understanding.','Learning support that meets children where they are and helps them build stronger foundations.'),
 ('emotions','heart','Social emotional learning','A little more self-belief. A little more belonging.','Activities that make space for children to understand feelings, express themselves and build relationships.'),
 ('digital','screen','Digital literacy & AI','Opening doors to a connected world.','Hands-on opportunities to explore technology, practise digital skills and discover new ways to learn.'),
 ('health','health','Health & nutrition','Healthy habits for growing lives.','Health awareness, nutrition education and activities that put children’s everyday well-being first.'),
 ('together','book','Library project','A world waiting between the pages.','Creating opportunities for reading, curiosity and discovery through access to books and shared learning spaces.'),
]
tabs=''.join(f'<button class="program-tab" id="tab-{key}" type="button" role="tab" aria-selected="{str(i==0).lower()}" aria-controls="panel-{key}" tabindex="{0 if i==0 else -1}"><span class="program-number">0{i+1}</span>{icon(symbol)}<span>{h(title)}</span><span class="tab-arrow" aria-hidden="true">↗</span></button>' for i,(key,symbol,title,_,__) in enumerate(programs))
panels=''.join(f'<div class="program-panel" id="panel-{key}" role="tabpanel" aria-labelledby="tab-{key}" tabindex="0" {"hidden" if i else ""}><figure>{photo(key)}<span class="photo-label">HUManity in action</span></figure><div class="program-panel-copy"><span class="eyebrow">0{i+1} / FIVE WAYS TO HELP CHILDREN THRIVE</span><h3>{h(headline)}</h3><p>{h(copy)}</p><a class="text-link" href="/CCI-programs">Explore our programs {icon("arrow")}</a></div></div>' for i,(key,_,title,headline,copy) in enumerate(programs))
stories=[('digital','DIGITAL LEARNING','A screen becomes a doorway.','A shared laptop, a curious question and the chance to try something new.'),('circle','SOCIAL EMOTIONAL LEARNING','Finding a voice. Finding connection.','Shared activities create space to listen, participate and learn together.'),('health','HEALTH & NUTRITION','Everyday habits. Lifelong possibilities.','Helping children explore what it means to care for their bodies and minds.')]
stories_html=''.join(f'<article class="story"><a href="/impact"><div class="story-image">{photo(key)}<span class="round-arrow" aria-hidden="true">↗</span></div><p class="eyebrow">{label}</p><h3>{h(title)}</h3><p>{h(copy)}</p></a></article>' for key,label,title,copy in stories)
home_count=len(json.loads((SITE/'.hccf/campaign/homes.json').read_text()))
tracking_head=''.join((SITE/'.hccf'/p).read_text() for p in ('google-tag.html','meta-pixel.html'))
tracking_body=''.join((SITE/'.hccf'/p).read_text() for p in ('google-tag-body.html','meta-pixel-body.html'))
html=f'''<!doctype html>
<html lang="en"><head><meta charset="utf-8">
{tracking_head}<meta name="viewport" content="width=device-width, initial-scale=1"><title>HUManity Foundation | Every Child. Every Possibility.</title>
<meta name="description" content="HUManity Foundation supports children in Child Care Institutions through education, emotional well-being, digital literacy, health and libraries. Give, volunteer or partner with us."><link rel="canonical" href="{ORIGIN}/"><meta name="theme-color" content="#087da3"><link rel="icon" href="{LOGO}">
<meta property="og:type" content="website"><meta property="og:title" content="HUManity Foundation | Every Child. Every Possibility."><meta property="og:description" content="Together, we can give children the care, confidence and opportunities to thrive."><meta property="og:url" content="{ORIGIN}/"><meta property="og:image" content="{ORIGIN}/assets/homepage/social.jpg"><meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{css}"></head>
<body>{tracking_body}<a class="skip" href="#main">Skip to content</a>
<header class="site-header"><div class="wrap header-inner"><a class="brand" href="/" aria-label="HUManity Foundation home"><img src="{LOGO}" alt="HUManity Foundation — Humanity Uplifting Mankind" width="155" height="73"></a><nav id="main-navigation" class="main-nav" aria-label="Main navigation"><a href="#approach">Who we are</a><a href="#programs">Our programs</a><a href="#stories">Our impact</a><a href="/volunteer">Volunteer</a></nav><div class="header-actions"><a class="button button-small" href="/donate/">Donate now {icon('heart')}</a><button id="menu-toggle" class="menu-toggle" type="button" aria-controls="main-navigation" aria-expanded="false" aria-label="Open navigation"><span></span><span></span></button></div></div></header>
<main id="main"><section class="hero"><div class="wrap hero-grid"><div class="hero-copy"><p class="eyebrow"><span></span> HUMANITY UPLIFTING MANKIND</p><h1>Every child.<br>Every <em>possibility.</em></h1><p class="hero-intro">A childhood filled with care. A future filled with choices.</p><p class="hero-description">We work alongside Child Care Institutions to help children learn, feel supported and discover what they can become.</p><div class="hero-actions"><a class="button" href="/donate/">Give a child your support {icon('arrow')}</a><a class="text-link" href="#programs">See our work <span aria-hidden="true">↓</span></a></div><div class="hero-signoff">{icon('heart')} Because showing up changes what comes next.</div></div>
<div class="hero-visual"><div class="hero-orbit" aria-hidden="true"></div><figure class="hero-photo"><img src="/assets/main-donate/childhood-1200.webp" srcset="/assets/main-donate/childhood-640.webp 640w, /assets/main-donate/childhood-1200.webp 1200w" sizes="(max-width: 700px) 100vw, 52vw" alt="A smiling child sharing a learning moment with the HUManity team." width="1200" height="900" fetchpriority="high"><figcaption>Small moments.<br><strong>Brighter possibilities.</strong></figcaption></figure><div class="photo-note"><span class="note-star" aria-hidden="true">✳</span><span>With children.<br><strong>For their future.</strong></span></div><figure class="hero-inset">{photo('together')}<figcaption>Growing, together.</figcaption></figure></div></div>
<div class="wrap impact-strip" aria-label="HUManity’s impact"><div class="impact-heading">Every act of care<br><strong>adds up.</strong></div><div><strong>30<span>+</span></strong><span>Child Care Institutions</span></div><div><strong>3,000<span>+</span></strong><span>Children impacted</span></div><div><strong>300<span>+</span></strong><span>Volunteers</span></div><div><strong>5</strong><span>Programs. One whole child.</span></div></div></section>
<section class="section approach" id="approach"><div class="wrap approach-grid"><div class="approach-visual"><figure>{photo('mentoring')}<figcaption><span>THE HUManity APPROACH</span>Care is a relationship.<br>We keep showing up.</figcaption></figure><div class="approach-mark" aria-hidden="true">{icon('sun')}</div></div><div class="approach-copy"><p class="eyebrow">A CHILD IS MORE THAN THEIR CIRCUMSTANCES</p><h2>Not just a place to grow.<br><em>A chance to thrive.</em></h2><p>Children in institutional care deserve the same opportunities to learn, belong and imagine a future as any other child.</p><p>HUManity brings volunteers, partners and childcare institutions together around the whole child — their learning, emotional well-being, health and hopes for tomorrow.</p><div class="approach-values"><span>{icon('book')} Learn with confidence</span><span>{icon('heart')} Grow with care</span><span>{icon('sun')} Look ahead with hope</span></div><a class="text-link" href="/CCI-programs">Get to know our work {icon('arrow')}</a></div></div></section>
<section class="section programs" id="programs"><div class="wrap"><div class="section-heading"><div><p class="eyebrow">FIVE PROGRAMS. ONE CONNECTED APPROACH.</p><h2>Supporting a childhood.<br><em>Opening up a future.</em></h2></div><p>Learning and well-being belong together. Our five programs bring consistent, practical support into everyday life at Child Care Institutions.</p></div><div class="program-layout"><div class="program-tabs" role="tablist" aria-label="Our five programs" aria-orientation="vertical">{tabs}</div><div class="program-panels">{panels}</div></div><noscript><p class="program-noscript">Our programs include education development, social emotional learning, digital literacy & AI, health & nutrition, and the library project. <a href="/CCI-programs">Explore all five programs.</a></p></noscript></div></section>
<section class="fund-section"><div class="wrap fund-panel"><div class="fund-copy"><p class="eyebrow">HUManity CHILDCARE INSTITUTION FUND</p><h2>Keep care going.<br>Where it is <em>needed most.</em></h2><p>For under-supported homes in rural Telangana, dependable essentials can make an everyday difference. Meet {home_count} selected institutions, understand their needs and support a home’s next chapter.</p><a class="button" href="/wheelsofhope/fund/">Find a home to support {icon('arrow')}</a><a class="fund-secondary" href="/wheelsofhope">Discover Wheels of Hope ↗</a></div><figure class="fund-photo"><img src="/assets/hccf-homes/indian-gypsy-children-home-suryapet/1-1200.webp" alt="The Indian Gypsy Children Home in Suryapet, from the HCCF field assessment." width="1200" height="900" loading="lazy"><figcaption><span>THE HOME BEHIND THE NEED</span>The Indian Gypsy Children Home<br><strong>Suryapet, Telangana</strong></figcaption><div class="fund-photo-mark" aria-hidden="true">{icon('home')}</div></figure></div></section>
<section class="section stories" id="stories"><div class="wrap"><div class="section-heading"><div><p class="eyebrow">MOMENTS FROM OUR WORK</p><h2>Real people.<br><em>Everyday possibilities.</em></h2></div><a class="text-link" href="/impact">Explore our impact {icon('arrow')}</a></div><div class="story-grid">{stories_html}</div></div></section>
<section class="section take-part" id="get-involved"><div class="wrap"><div class="take-part-heading"><p class="eyebrow">THERE IS A PLACE FOR YOU HERE</p><h2>A little of your time.<br>A little of your kindness.<br><em>A world of difference.</em></h2></div><div class="involvement-grid"><article class="give-card"><span class="action-icon">{icon('heart')}</span><p class="eyebrow">GIVE</p><h3>Be there, every month.</h3><p>₹2,000 a month supports one child through HUManity’s work. Give once, or make care a monthly commitment.</p><a class="button" href="/donate/">Support a child {icon('arrow')}</a></article><article><span class="action-icon">{icon('people')}</span><p class="eyebrow">VOLUNTEER</p><h3>Your presence matters.</h3><p>Teach, mentor, share a skill or help make our programs happen. Bring what you know. Start where you can.</p><a class="text-link" href="/volunteer">Find your way to contribute {icon('arrow')}</a></article><article><span class="action-icon">{icon('home')}</span><p class="eyebrow">PARTNER</p><h3>Make care a team effort.</h3><p>Bring your organisation’s people, resources and ideas into a meaningful partnership for children.</p><a class="text-link" href="mailto:contact@humanityorg.foundation?subject=Partnership%20with%20HUManity">Talk to us about partnering {icon('arrow')}</a></article></div></div></section>
<div class="closing-line"><div class="wrap"><span>Humanity begins with us.</span>{icon('heart')}<strong>Let’s help more children thrive.</strong></div></div></main>
<footer class="site-footer"><div class="wrap footer-grid"><div class="footer-about"><a href="/" aria-label="HUManity Foundation home"><span class="footer-logo"><b>HUM</b>anity</span></a><span class="footer-tagline">HUMANITY UPLIFTING MANKIND</span><p>Creating possibilities for children through care, learning and a community that shows up.</p><div class="social-links"><a href="https://www.instagram.com/humanity_uplifting_mankind/" target="_blank" rel="noopener">Instagram ↗</a><a href="https://in.linkedin.com/company/humanity-uplifting-mankind" target="_blank" rel="noopener">LinkedIn ↗</a></div></div><div><h3>Our work</h3><a href="/CCI-programs">Our five programs</a><a href="/impact">Impact stories</a><a href="/wheelsofhope/fund/">ChildCare Institution Fund</a><a href="/wheelsofhope">Wheels of Hope</a></div><div><h3>Get involved</h3><a href="/donate/">Make a donation</a><a href="/volunteer">Volunteer with us</a><a href="mailto:contact@humanityorg.foundation">Partner with HUManity</a></div><div><h3>Say hello</h3><a href="mailto:contact@humanityorg.foundation">contact@humanityorg.foundation</a><a href="tel:+919912962885">+91 99129 62885</a><p>GF1, Kranti Nivas 2, PM Palem,<br>Visakhapatnam 530041</p></div></div><div class="wrap footer-bottom"><span>© 2026 HUManity Foundation. All rights reserved.</span><a href="/privacy">Privacy policy</a><a href="#main">Back to top ↑</a></div></footer>
<script type="module" src="{js}"></script></body></html>'''
# Preserve the existing application entry for programs, impact and volunteering.
# Homepage and campaign traffic use their own lightweight HTML documents.
app_source=SITE/('app.html' if (SITE/'app.html').exists() else 'index.html')
(OUT/'app.html').write_bytes(app_source.read_bytes())
(OUT/'index.html').write_text(html)
print('Built the foundation homepage, responsive photographs and shared app entry.')
