# HCCF fundraiser and shared donation records

The original HCCF page, ₹1.8 crore campaign and 30-CCI plan remain intact. The reusable institution section includes the Indian Gypsy Children Home, five rotating supplied photos and a ₹6 lakh yearly goal. Up to 30 real stories can be added in `stories.json`; each additional story requires its own backend campaign association before collecting donations.

## Donation popup and progress

“Support this home” opens the amber donation popup with one-time/monthly choices, preset/custom amounts, name as government ID, email, Indian mobile number, ID type and ID number. ID types match the main page: PAN, Aadhaar and ration card. No document upload is requested.

HCCF monthly subscriptions have 12 monthly charges. After the first full captured instalment is verified and the subscription is active/authenticated, ₹500/month counts as ₹6,000 and ₹1,000/month as ₹12,000 toward the yearly goal. “Funded & committed” is separate from “received so far.” Renewals add receipts without adding the annual commitment again. Refunds reduce totals; inactive/cancelled commitments revert to actual net receipts. New orders stop at the committed yearly goal; already-open orders and recurring instalments can still complete.

## Existing admin dashboard

`/backend/admin.php?tab=donations` combines main-page and HCCF donors, including government ID fields, per-payment amount, actual receipts, annual commitment, subscription state and provider references. `?tab=donation_payments` lists each instalment. Both can be exported through the authenticated dashboard. Donation payment records have no delete controls. “Sync payments” reconciles a donor's order/subscription with Razorpay and provides a recovery path when browser confirmation or webhooks are missed.

Government IDs stay in the existing authenticated database/dashboard flow. Public totals and Razorpay notes do not include them. The payment ledger has a unique provider payment ID; order/subscription, amount, currency and captured status are checked against provider data before recording new money.

## Razorpay configuration still required outside this repository

The existing `backend/api/config.php` supplies the live Razorpay account and database connection. HCCF optionally supports `CCI_RAZORPAY_KEY_ID` and `CCI_RAZORPAY_KEY_SECRET` overrides; keep both pages on the same Razorpay account for the shared webhook. Do not commit credentials. Subscription products/payment methods must be enabled for the account. Keep automatic capture enabled.

For unattended renewals, missed-checkout recovery, refunds and subscription status changes:

1. Set `CCI_RAZORPAY_WEBHOOK_SECRET` in the hosting environment to a private random value.
2. Add this Razorpay webhook using the same secret: `https://humanityorg.foundation/backend/api/donations/cci-fund.php?action=webhook`.
3. Enable `payment.captured`, `order.paid`, `refund.processed` and all available subscription lifecycle events: `subscription.authenticated`, `subscription.activated`, `subscription.charged`, `subscription.completed`, `subscription.updated`, `subscription.pending`, `subscription.halted`, `subscription.cancelled`, `subscription.paused`, `subscription.resumed`.
4. Verify successful webhook delivery in Razorpay and run a controlled test-mode payment, renewal, cancellation and refund. No real payment has been made by these automated checks.

The webhook rejects requests when its secret is absent. The admin shows a setup notice in that case. Deploying this code does not prove that the hosting environment or Razorpay webhook has been configured. Checkout confirmation records verified first payments immediately; automatic later updates require the webhook or an admin Sync payments action. Historic renewals not already recorded can be imported using Sync payments on each existing subscription. Existing main-page subscription durations are preserved.

Schema additions are automatic and additive through `donationEnsureSchema()`: donor ID/subscription columns on the CCI table, subscription status on main donations, and the shared `donation_payments` ledger. Existing completed first-payment records are preserved; they are not retroactively re-verified by the migration. No separate SQL import is required.

## Maintenance and verification

The supplied site contains compiled deployment files, not the original editable React project. The readable component is `fundraiser.js` (workspace `src/fundraiser.js`). The workspace `build.py` preserves the original HCCF function and inserts its wrapper/section, then generates a content-hashed bundle and versioned stylesheet reference. Keep maintenance files under `.hccf/`, which the deployment excludes.

Local preview: `http://127.0.0.1:8080/wheelsofhope/fund`. Its server blocks backend calls and real payments. Browser checks covered the popup, monthly switching, required ID fields, Escape/focus return and a 390px layout without horizontal overflow. Automated checks cover React rendering, 30-home pagination, PHP syntax, stored-order/subscription signature binding, amount/currency/capture checks, yearly arithmetic, renewal replay, refunds, cancellation and combined admin records. Database integration tests use synthetic SQLite fixtures with translated MySQL locking/upsert syntax; they do not verify production MySQL concurrency or execute real Razorpay payments.

References: [Razorpay subscriptions integration](https://razorpay.com/docs/payments/subscriptions/integration-guide/), [subscription invoices](https://razorpay.com/docs/api/payments/subscriptions/fetch-invoices/) and [subscription webhooks](https://razorpay.com/docs/webhooks/subscriptions/).
