const settings = JSON.parse(document.getElementById('main-donation-config').textContent);
const $ = selector => document.querySelector(selector);
const $$ = selector => [...document.querySelectorAll(selector)];
const money = value => '₹' + Number(value).toLocaleString('en-IN', {maximumFractionDigits: 2});
const local = ['localhost', '127.0.0.1', '[::1]'].includes(location.hostname);
const form = $('#donation-form'), fields = $('#donation-fields'), amountInput = $('#donation-amount');
const status = $('#form-status'), retry = $('#verify-retry'), again = $('#donate-again');
const idInput = $('[name=idNumber]'), idType = $('[name=idType]');
const retryKey = 'humanity-main-confirmation';
let frequency = 'monthly', busy = false, lastGift = null, checkoutLoader;
function readPending() {
  try {
    const value = JSON.parse(sessionStorage.getItem(retryKey) || 'null');
    if (value?.created > Date.now() - 48 * 60 * 60 * 1000 && ['once', 'monthly'].includes(value.payload?.donation_type)) return value.payload;
  } catch (_) {}
  return null;
}
let pending = readPending();
function storePending(payload) {
  try { sessionStorage.setItem(retryKey, JSON.stringify({created: Date.now(), payload})); } catch (_) {}
}
function setBusy(value) {
  busy = value; fields.disabled = value || Boolean(pending); retry.disabled = value; again.disabled = value;
  $('#pay-donation').textContent = value ? 'Please wait…' : 'Donate ' + money(Number(amountInput.value) || 0) + (frequency === 'monthly' ? ' / month' : '') + ' →';
}
function updateAmount() {
  const value = Number(amountInput.value) || 0, monthly = frequency === 'monthly';
  $$('[data-amount]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.amount) === value)));
  $$('[data-frequency]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.frequency === frequency)));
  $('#amount-label').textContent = monthly ? 'Choose your monthly gift' : 'Choose your one-time gift';
  $('#gift-explanation').textContent = monthly
    ? (value === 2000 ? '₹2,000 a month supports one child. ' : '') + money(value) + ' each month adds up to ' + money(value * 12) + ' over a year of giving.'
    : 'Your one-time gift supports HUManity’s work with children.';
  $('#monthly-terms').hidden = !monthly;
  $('#mobile-gift').textContent = money(value) + (monthly ? ' / month' : ' one-time');
  setBusy(busy);
}
$$('[data-frequency]').forEach(button => button.addEventListener('click', () => { frequency = button.dataset.frequency; updateAmount(); }));
$$('[data-amount]').forEach(button => button.addEventListener('click', () => { amountInput.value = button.dataset.amount; updateAmount(); }));
amountInput.addEventListener('input', updateAmount);
function checkId() {
  const number = idInput.value.replace(/\s/g, '');
  const valid = idType.value === 'pan' ? /^[a-z]{5}\d{4}[a-z]$/i.test(number) : idType.value === 'aadhaar' ? /^\d{12}$/.test(number) : number.length >= 4;
  idInput.setCustomValidity(idInput.value && !valid ? 'Please enter a valid number for the selected ID type.' : '');
}
idInput.addEventListener('input', checkId); idType.addEventListener('change', checkId);
$$('a[href="#tax-details"]').forEach(link => link.addEventListener('click', () => { $('#tax-details').open = true; }));
function loadCheckout() {
  if (window.Razorpay) return Promise.resolve();
  if (checkoutLoader) return checkoutLoader;
  checkoutLoader = new Promise((resolve, reject) => {
    const script = document.createElement('script'); script.src = 'https://checkout.razorpay.com/v1/checkout.js'; script.async = true;
    const fail = () => { script.remove(); checkoutLoader = null; reject(new Error('Razorpay could not load. Please check your connection and try again.')); };
    const timeout = setTimeout(fail, 20000);
    script.onerror = () => { clearTimeout(timeout); fail(); };
    script.onload = () => { clearTimeout(timeout); if (window.Razorpay) resolve(); else fail(); };
    document.head.appendChild(script);
  });
  return checkoutLoader;
}
async function request(endpoint, data) {
  const response = await fetch('/backend/api/donations/' + endpoint, {
    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data), signal: AbortSignal.timeout(45000)
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || !result.success) throw new Error('We could not complete this step. Please check your details and try again.');
  return result;
}
async function verify(payload) {
  pending = payload; storePending(payload); setBusy(true); retry.hidden = true;
  status.textContent = 'Confirming your payment. Please keep this page open.';
  try {
    const endpoint = payload.donation_type === 'monthly' ? 'verify-subscription.php' : 'verify-payment.php';
    const result = await request(endpoint, payload);
    if (!Number.isFinite(result.amount) || !result.paymentId || result.donationType !== payload.donation_type) throw new Error('Incomplete payment confirmation');
    pending = null; try { sessionStorage.removeItem(retryKey); } catch (_) {}
    lastGift = result; form.hidden = true; status.textContent = ''; $('#previous-donation').hidden = true;
    const box = $('#confirmation'); box.hidden = false;
    box.innerHTML = '<div class="confirmed-icon" aria-hidden="true">✓</div><h3>Thank you for being there.</h3><p class="confirmation-amount"></p><p class="reference"></p><p class="receipt-note">HUManity will use your details to issue your donation receipt and 80G documentation.</p>';
    $('.confirmation-amount').textContent = money(result.amount) + ' is confirmed for HUManity Foundation.';
    $('.reference').textContent = 'Payment reference: ' + result.paymentId;
    if (result.donationType === 'monthly') {
      const note = document.createElement('p'); note.className = 'receipt-note';
      note.textContent = 'Thank you for choosing monthly support. Future payments follow your Razorpay subscription. Contact us whenever you need help with cancellation.'; box.appendChild(note);
    }
    again.hidden = false;
    // First-party hook only; no donor details or advertising SDK are sent.
    window.dispatchEvent(new CustomEvent('humanity:donation-verified', {detail: {source: 'main', amount: result.amount, currency: 'INR', donationType: result.donationType}}));
  } catch (_) {
    status.textContent = 'Payment confirmation is pending. If money was debited, please do not pay again. Check confirmation below, or contact HUManity with reference ' + payload.razorpay_payment_id + '.';
    retry.hidden = false;
  } finally { setBusy(false); }
}
retry.addEventListener('click', () => { if (pending && !busy) verify(pending); });
again.addEventListener('click', () => {
  if (busy || pending || !lastGift) return;
  const previous = $('#previous-donation'); previous.hidden = false;
  previous.textContent = 'Previous gift confirmed: ' + money(lastGift.amount) + ' · ' + lastGift.paymentId + '.';
  if (lastGift.donationType === 'monthly') previous.append(document.createTextNode(' Your existing monthly subscription continues. This is a separate, new donation.'));
  form.reset(); idInput.setCustomValidity(''); frequency = 'once'; updateAmount();
  $('#confirmation').hidden = true; again.hidden = true; form.hidden = false; status.textContent = ''; retry.hidden = true;
  amountInput.focus({preventScroll: true}); $('#donate').scrollIntoView({block: 'start', behavior: 'instant'});
});
form.addEventListener('submit', async event => {
  event.preventDefault(); if (busy || pending) return;
  checkId(); if (!form.reportValidity()) return;
  const amount = Number(amountInput.value);
  if (!Number.isSafeInteger(amount) || !Number.isSafeInteger(amount * 100) || amount <= 0) { status.textContent = 'Please choose a valid whole-rupee amount.'; return; }
  if (local) { status.textContent = 'Local preview only. No donation or payment will be created here.'; return; }
  const donor = {};
  for (const key of ['name', 'email', 'phone', 'idType', 'idNumber']) donor[key] = form.elements.namedItem(key).value.trim();
  setBusy(true); status.textContent = 'Preparing secure checkout…';
  try {
    await loadCheckout();
    const order = await request('create-order.php', {...donor, phone: '+91' + donor.phone, amount, donationType: frequency});
    if (!order.razorpayKeyId || !order.donationId || order.amount !== amount * 100 || order.currency !== 'INR' || order.isSubscription !== (frequency === 'monthly')) throw new Error('Checkout details could not be confirmed. Please contact donation support.');
    if (order.isSubscription ? !order.subscriptionId : !order.orderId) throw new Error('Checkout could not be prepared. Please try again.');
    const paidType = order.isSubscription ? 'monthly' : 'once'; let handled = false;
    const checkout = new window.Razorpay({
      key: order.razorpayKeyId, name: 'HUManity Foundation', description: paidType === 'monthly' ? 'Monthly support for children' : 'Support for children', image: settings.logo,
      ...(order.isSubscription ? {subscription_id: order.subscriptionId} : {order_id: order.orderId, amount: order.amount, currency: 'INR'}),
      prefill: {name: donor.name, email: donor.email, contact: '+91' + donor.phone}, theme: {color: '#087da3'},
      handler: payload => { handled = true; verify({...payload, donation_id: order.donationId, donation_type: paidType}); },
      modal: {ondismiss: () => { if (!handled) { setBusy(false); status.textContent = 'Checkout closed. Your details are still here if you would like to continue.'; } }}
    });
    checkout.on('payment.failed', () => { status.textContent = 'Payment was not completed. If your bank shows a debit, check the payment status before retrying.'; });
    status.textContent = ''; checkout.open();
  } catch (error) { setBusy(false); status.textContent = error.message; }
});
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver(([entry]) => { $('#mobile-donate').hidden = entry.isIntersecting; }, {threshold: .15});
  observer.observe($('#donate'));
}
updateAmount();
if (pending) { status.textContent = 'A previous payment is awaiting confirmation. Please check it before making another donation.'; retry.hidden = false; setBusy(false); }
