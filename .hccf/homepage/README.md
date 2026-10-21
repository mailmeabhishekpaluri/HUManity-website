# HUManity foundation homepage

The root page is a standalone, responsive foundation homepage. It replaces the Childhood Allies signup, ally tracker and two homepage videos with real photographs, five program tabs, impact figures, the ChildCare Institution Fund and donation/volunteer/partnership links.

## Build

From the repository root, use Python with Pillow installed:

```sh
python3 .hccf/homepage/build.py --output /tmp/humanity-site-build
python3 .hccf/campaign/build.py --output /tmp/humanity-site-build
```

Run the homepage build first. It writes `index.html`, responsive photographs and content-hashed CSS/JS, and preserves the existing application shell as `app.html`. The campaign build then updates the shell's bundle reference and generates bridges from internal SPA navigation to the standalone homepage, donation page and fund page. `.htaccess` serves `app.html` for other application routes; the root and existing standalone pages retain their own HTML. Publish these entry files, the new bundle and their referenced assets together.

The pinned original bundle is `assets/index-D3rKJrP3.js`. Only the three page components are replaced; shared application code is preserved. If updating that baseline, check the explicit component boundaries in the campaign builder.

## Content and assets

Edit `build.py` for copy and photo choices, `homepage.css` for the design and `homepage.js` for the navigation/program tabs. The 30+ institutions, 3,000+ children, 300+ volunteers and five programs are carried over from the previous homepage; update these when new verified figures are available. The ₹2,000 monthly support amount follows the existing donation page. The current home count comes from `.hccf/campaign/homes.json`.

Photos are existing HUManity website images. The hero reuses the main donation page's optimized photograph, and the fund section uses the existing Suryapet campaign photo. No beneficiary stories, receipts or financial progress are invented. The homepage has no live counter, signup form or video player.

Tracking uses the existing shared GTM and Meta Pixel snippets in `.hccf/`. Do not add another standalone Google Analytics tag. Program tabs support keyboard selection; the mobile menu supports Escape; reduced-motion preferences are respected.

After building, check desktop and narrow phone layouts, program tabs, mobile navigation, homepage links and returning Home from the existing application. Check all image references, and verify that the donation and eight fund pages are unchanged. Use a local preview that blocks payment submissions and third-party tracking during checks.
