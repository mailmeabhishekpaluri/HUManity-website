# Indian Gypsy Children Home fundraiser

Local preview: http://127.0.0.1:8080/wheelsofhope/fund

The original HCCF fund page is preserved in full, including its original design, ₹1.8 crore campaign goal, 30-CCI plan, crowdfunding equation and closing campaign message. One reusable stories section is inserted immediately before the closing message. These files are included in the website repository. A push to main triggers the existing Hostinger deployment workflow. This .hccf directory is excluded from deployment.

## Included

- One featured home: the Indian Gypsy Children Home, with its ₹6 lakh goal, concise story, expandable needs and donation form.
- Five supplied photos in a rotating carousel with previous/next and play/pause controls. Rotation pauses on hover/focus or outside the viewport, and respects reduced-motion preferences.
- Blue/amber styling consistent with the existing fund page; side-by-side gallery and story on desktop, stacked on mobile.
- `stories.json` holds independent home records. Add up to 30 real stories; the list displays six at a time with a View more homes button. No placeholder CCIs or fabricated totals are included.
- Each home has a unique share anchor, goal, photo set and payment API configuration. A newly added home must have its own matching backend campaign configuration before collecting donations; the current PHP endpoint remains specific to the Suryapet home. Do not reuse it for another CCI.
- Verified progress, goal-reached state and the existing tested campaign-specific Razorpay integration are retained.

## What is still needed before live collection

1. Confirm whether this home already has money raised. The new table cannot infer or import previous donations. No prior amount was supplied or invented.
2. Confirm the item-wise budget/allocation and add updates as documented. ₹6 lakh is a requested campaign goal, not a verified bill of quantities. No claim of tax exemption eligibility is made.
3. Apply `.hccf/cci-fund-schema.sql` to the existing database. Keep this SQL file outside the published web root.
4. Configure the server environment: `CCI_RAZORPAY_KEY_ID`, `CCI_RAZORPAY_KEY_SECRET`, `CCI_RAZORPAY_WEBHOOK_SECRET`, and `CCI_FUND_ENABLED=1`. Use test keys first. Secrets have not been embedded in the new code. Existing `backend/api/config.php` supplies getDB(); no credentials are copied into this overlay.
5. Set Razorpay automatic capture and register the webhook URL `https://humanityorg.foundation/backend/api/donations/cci-fund.php?action=webhook` for `payment.captured`, `order.paid` and `refund.processed`. Use the matching webhook secret. The endpoint fetches authoritative payment details and updates the matching campaign order idempotently.
6. Validate order creation, capture, cancellation, verification retries, webhook replay, refunds and totals against a test database/Razorpay test account. PHP helper tests are not an end-to-end payment test. Public totals count live-mode donations only.
7. The existing admin dashboard does not yet list the new table. Campaign records can be reconciled by an authorised operator through the database and Razorpay. Add an admin view before handing over routine campaign operations. Configure host-level request throttling; the endpoint only has per-email order throttling.
8. Approve and deploy the frontend overlay and the two PHP files after testing. The overlay is not a full website copy: it relies on unchanged files from the original website. Do not upload this README, tests, build script, or migration under a public path.

The goal stops new orders once the confirmed total reaches ₹6 lakh. Orders already open can complete after the goal is reached, so the final total may exceed the target. Decide how excess funds should be allocated and publish that policy before launch.

## Working files

`fundraiser.js` is the readable new component. `build.py` retains the original HCCF page function byte-for-byte (only renaming its function), adds a wrapper that inserts the stories section before its last section, and writes a content-hashed JavaScript bundle plus updated index.html. The original editable React source is not present in the supplied website. Port this component back to the original application source when available.

`../serve.py` serves `deploy/` files first and falls back to the original website files. It blocks backend access and external submission requests. The local form deliberately does not open a real payment checkout.

## Verification

- JavaScript syntax check of source and generated bundle.
- Component tests: original layout and section order preserved, reusable card and goal states, five-photo carousel controls, six-at-a-time rendering for a 30-home dataset. Exact original component and unrelated bundled code preservation verified.
- PHP 8.x WebAssembly runtime: endpoint syntax check passed; executed payment helper tests passed for stored-order signature binding, invalid signatures, amount/currency/order/capture mismatches and refund bounds. These tests use synthetic data, with no real provider or database calls.
- Browser rendering remains unverified because the browser tool could not verify its required security policy. No live donation, database migration, credential change or publication was performed.

Payment implementation references: [Razorpay Standard Checkout](https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/) and [webhook validation](https://razorpay.com/docs/webhooks/validate-test/).


## Source handoff
The readable component and story records are stored beside this file. They are integrated into the content-hashed JavaScript bundle referenced by index.html; they are not loaded directly at runtime. The original editable app source was not available. Rebuild the integration when editing these records; changing JSON alone does not change the published page.
