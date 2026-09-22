# Main Donate Now page

`/donate/` is a standalone page with a real HUManity photograph on the left and the complete donor form on the right. Monthly giving defaults to ₹2,000, with one-time giving and custom amounts available. Mobile uses a single column and an anchor to the form.

## Build

Use Python 3 with Pillow installed. From the repository root:

```sh
python3 .hccf/main-donate/build.py --output /tmp/humanity-preview
python3 .hccf/campaign/build.py --output /tmp/humanity-preview
```

The main builder generates `donate/index.html`, hashed JS/CSS, the shared HUManity stylesheet and three optimized versions of the existing `manus-storage/Ind_1_1766966728588_4a515fe0.jpg`. It removes photo metadata from the resized assets. It does not change the original photograph.

The campaign builder also produces the root SPA bridge for both the HCCF fund route and the main Donate Now route. Keep those bridge changes when rebuilding; publish both builders' output together. All unrelated SPA components remain intact. Source in `.hccf/` is excluded from deployment.

## Payment behavior

- Uses the existing main `create-order.php`, `verify-payment.php` and `verify-subscription.php` endpoints; donor information stays in the existing donations table and combined dashboard.
- Government ID fields stay visible and are sent only to HUManity's main donation endpoint, not Razorpay Checkout or the optional first-party confirmation event.
- The existing main subscription runs monthly for up to **120 instalments**, unless cancelled. The annual figure is illustrative. HCCF's separate 12-month campaign logic is unchanged.
- Confirmation requires the server's verified result. A failed confirmation locks further checkout and offers a verification retry. Session storage retains only the verification payload (no contact or government ID fields), independently of HCCF.
- After verification, Make another donation clears donor details and defaults to one-time. It explicitly explains that an existing monthly subscription continues.
- Receipt/80G documentation is issued by HUManity; the UI does not fabricate a receipt download link or promise automatic email delivery.
- The localhost guard prevents payment creation. Never use real donor data or live payments for visual testing.

## Verification

The DOM test uses `jsdom` and mocks every request and Razorpay callback. Set `HCCF_TEST_DEPS` to a directory containing a `node_modules/jsdom` installation; optionally set `DONATION_TEST_SITE` to the build output folder.

```sh
HCCF_TEST_DEPS=/tmp/hccf-brand-tests DONATION_TEST_SITE=/tmp/humanity-preview node .hccf/main-donate/test-donation.mjs
```

Check desktop and mobile layouts and the root site's Donate Now link before publishing. No live payment is necessary for these checks.
