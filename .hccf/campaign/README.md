# HCCF home campaigns

The hub at `/wheelsofhope/fund/` lists seven homes with separate ₹6 lakh yearly goals. Each home has a dedicated static landing page, real audit photographs, one-time/monthly giving and no ordinary site navigation. The combined current goal is ₹42 lakh. The catalogue supports up to 30 homes with filters, search and nine-at-a-time pagination.

## Editing and building

`homes.json` is the content registry. Keep existing IDs stable because payments and shared links use them. Add a new unique ID, verified story, goal in rupees and properly matched photographs. All seven current goals were explicitly selected by the campaign owner; they are fundraising targets, not audited itemised budgets.

Run `python3 .hccf/campaign/build.py --site-root . --output /tmp/hccf-build` from the repository root. The build generates the hub, individual pages, public campaign registry, content-hashed CSS/JS, and the existing SPA's HCCF entry-point bridge. Publish those outputs together with the matching photo assets and the updated donation PHP files. The original `assets/index-D3rKJrP3.js` is the baseline for the bridge. The rest of the original SPA is preserved.

`prepare-photos.py` uses Pillow and pypdf to prepare 640px/1200px WebP images and JPEG social previews from the supplied audit. See its arguments before using it with another report. Audit pages are private source material: publish only approved, home-matched photographs, never the full audit or health/protection details.

The local preview is deliberately unable to create payments. It labels totals as live-site information rather than showing invented receipts.

The header uses the full name **HUManity ChildCare Institution Fund**. The blue/amber visual design and Montserrat/Open Sans typography follow the existing HUManity website. The main hero uses the website's existing `manus-storage/Aug1_PSH5_1767167802826_44dce97d.jpg`, optimised into `assets/hccf-brand/`; its caption identifies it as general HUManity work. Individual home galleries remain matched to their own audit photos.

## Payments and records

The backend allowlist in `backend/api/donations/cci-campaigns.php` is generated from `homes.json`. Creation and verification are bound to the selected home. The original `indian-gypsy-children-home-suryapet` ID and existing records are preserved. Main donation-page verification is unchanged.

After a verified first payment and active/authenticated monthly subscription, ₹500/month contributes ₹6,000 and ₹1,000/month contributes ₹12,000 to the yearly committed total. Actual net receipts are separate. Renewals add receipts without counting the yearly commitment twice. Refunds and inactive subscriptions adjust the totals. Payments already in progress and recurring instalments may complete after the target is reached.

The authenticated donation dashboard contains home name, donor government-ID fields, actual receipts, yearly commitment, provider references and optional ad labels. The payment history includes each verified instalment. New `attribution_json` storage is added automatically without removing existing records. Keep the existing binary joins: they handle differing legacy database collations.

After a verified payment, **Make another donation** opens a fresh form, clears personal/ID fields and defaults to one-time giving. The previous reference remains visible; monthly donors are told that their existing subscription continues. Pending confirmation cannot be bypassed, and a completed campaign goal disables another gift. `test-donate-again.mjs` exercises these flows with jsdom and entirely mocked network/provider calls; use `HCCF_TEST_DEPS` to point at a folder containing jsdom.

The FAQ and form explain that donor details support donation receipts and 80G documentation. The campaign owner's 50% benefit is described as a deduction from qualifying taxable income, subject to eligibility and limits. It is not a 50% tax refund; individuals choosing the new tax regime cannot claim Section 80G. The organisation's receipt/documentation process remains separate from the payment UI. Wording reference: [Income Tax Department 80G FAQ](https://www.incometax.gov.in/iec/foportal/sites/default/files/2025-12/FAQs_80G_Section_mentioned_%28final%29_to_upload.pdf).

For ongoing subscription/refund reconciliation, configure the Razorpay webhook and `CCI_RAZORPAY_WEBHOOK_SECRET` as described in the parent `.hccf/SETUP.md`. Deployment does not configure Razorpay or prove that live recurring charges work. Admin **Sync payments** remains the manual recovery path. No live payments were created during this redesign.

## Meta campaign links

Send each ad directly to its home's URL in `CAMPAIGN-LINKS.md`. Set `utm_source=instagram`, `utm_medium=paid_social`, a recognisable `utm_campaign`, and the supported ad/campaign IDs. The hub preserves these labels if used as an intermediate destination. They are saved with donation records; donor identity fields are never placed in URLs or Razorpay notes.

Supported parameters: `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `utm_id`, `campaign_id`, `adset_id`, `ad_id`.

The page emits the first-party `hccf:donation-verified` browser event after successful backend confirmation, with actual payment amount and home ID. No Meta Pixel ID, advertising SDK, Conversion API credential or ad budget has been configured. Ad labels in the admin are attribution aids, not proof of Meta conversion tracking. Connect and test a chosen Meta dataset before optimising ads for donations.

## Verification

Synthetic PHP tests cover signatures bound to stored orders/subscriptions, captured amount/currency, replay, renewals, refunds, cancellation, seven-home separation, test/live separation and dashboard attribution. They use SQLite with minimal MySQL-syntax translation and do not verify MySQL concurrency or make Razorpay transactions.

Page checks cover eight routes, complete assets, dedicated-home links, metadata, all seven goals and the existing SPA bridge. Browser checks cover desktop and 390px phone layouts, monthly calculations, required donor fields, safe local submission and catalogue filters.
