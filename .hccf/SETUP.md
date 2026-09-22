# HCCF campaigns and shared donation records

The current hub is `/wheelsofhope/fund/`. It contains seven selected homes, each with a ₹6 lakh yearly fundraising goal and its own donation landing page. The combined current goal is ₹42 lakh, with the longer campaign planned for 30 homes and ₹1.8 crore.

See [campaign/README.md](campaign/README.md) for the current build, content registry, photo workflow and verification details, and [campaign/CAMPAIGN-LINKS.md](campaign/CAMPAIGN-LINKS.md) for the seven public links. The older `fundraiser.js` and `stories.json` describe the superseded single-section design and are retained only as historical source.

## Giving and progress

Each home page has one-time and monthly choices, preset/custom amounts, and a second step collecting name as per government ID, email, Indian mobile number, ID type and ID number. Supported ID types match the main page: PAN, Aadhaar and ration card. No document upload is requested.

HCCF subscriptions have 12 monthly charges. After the first captured instalment is verified and the subscription is active/authenticated, ₹500/month counts as ₹6,000 and ₹1,000/month as ₹12,000 toward that home's yearly goal. Funded and committed amounts remain separate from actual receipts. Renewals add receipts without multiplying the commitment again; refunds and inactive subscriptions adjust the totals. New orders stop at the committed yearly goal; already-open orders and recurring instalments can complete.

## Existing admin dashboard

`/backend/admin.php?tab=donations` combines main-page and HCCF donors, including government ID fields, home name, per-payment amount, actual receipts, annual commitment, subscription state, provider references and ad attribution labels. `?tab=donation_payments` lists each instalment. Exports require administrator authentication. Donation payment records have no delete controls. **Sync payments** reconciles an order/subscription with Razorpay when browser confirmation or webhooks are missed.

Government IDs stay in the existing authenticated database/dashboard flow. Public totals and Razorpay notes exclude them. The payment ledger uses a unique provider payment ID; stored order/subscription, amount, currency and captured status are verified against provider data before recording money. The original Suryapet campaign ID and historic donations are preserved.

## Razorpay configuration outside this repository

The existing `backend/api/config.php` supplies the live Razorpay account and database connection. HCCF optionally supports `CCI_RAZORPAY_KEY_ID` and `CCI_RAZORPAY_KEY_SECRET` overrides. Keep both pages on the same account for the shared webhook. Do not commit new credentials. Subscription products/payment methods must be enabled for the account; keep automatic capture enabled.

For unattended renewals, missed-checkout recovery, refunds and subscription status changes:

1. Set `CCI_RAZORPAY_WEBHOOK_SECRET` in the hosting environment to a private random value.
2. Add this Razorpay webhook with the same secret: `https://humanityorg.foundation/backend/api/donations/cci-fund.php?action=webhook`.
3. Enable `payment.captured`, `order.paid`, `refund.processed` and supported subscription lifecycle events: `subscription.authenticated`, `subscription.activated`, `subscription.charged`, `subscription.completed`, `subscription.updated`, `subscription.pending`, `subscription.halted`, `subscription.cancelled`, `subscription.paused`, `subscription.resumed`.
4. Verify delivery in Razorpay and perform controlled test-mode payment, renewal, cancellation and refund checks. No real payment was made by the redesign checks.

The webhook rejects requests when its secret is absent. The admin shows a setup notice in that case. Code deployment does not configure the hosting environment or Razorpay webhook. Checkout confirmation records verified first payments; automatic later updates require the webhook or an admin Sync payments action. Historic renewals can be imported with Sync payments for each subscription. Main-page subscription durations are preserved.

Schema additions are automatic and additive through `donationEnsureSchema()`. Existing completed first-payment records are preserved and are not retroactively re-verified by migration. No separate SQL import is required. Binary joins between legacy tables and the ledger are intentional because older tables have different database collations.

References: [Razorpay subscriptions integration](https://razorpay.com/docs/payments/subscriptions/integration-guide/), [subscription invoices](https://razorpay.com/docs/api/payments/subscriptions/fetch-invoices/) and [subscription webhooks](https://razorpay.com/docs/webhooks/subscriptions/).
