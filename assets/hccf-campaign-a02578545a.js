const pageNode = document.getElementById('hccf-page');
const config = pageNode ? JSON.parse(pageNode.textContent) : null;
const API = '/backend/api/donations/cci-fund.php';
const isLocal = ['localhost', '127.0.0.1', '[::1]'].includes(location.hostname);
const attributionKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id', 'campaign_id', 'adset_id', 'ad_id'];
export const money = value => '₹' + Number(value).toLocaleString('en-IN', {maximumFractionDigits: 2});
export function annualCommitment(amount, type) { return Number(amount) * (type === 'monthly' ? 12 : 1); }
export function attributionFrom(search) {
  const params = new URLSearchParams(search), result = {};
  for (const key of attributionKeys) {
    const value = params.get(key);
    if (value) result[key] = value.replace(/[\x00-\x1f\x7f]/g, '').slice(0, 160);
  }
  return result;
}
const attribution = attributionFrom(location.search);
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
let activeCheckout = false;
function updateProgress(totals) {
  for (const block of $$('[data-progress]')) {
    const stats = totals[block.dataset.progress];
    if (!stats || !Number.isFinite(stats.received) || !Number.isFinite(stats.committed) || stats.received < 0 || stats.committed < 0) continue;
    const goal = Number(block.dataset.goal), committed = stats.committed;
    $('[data-raised]', block).textContent = money(committed);
    $('[data-raised]', block).style.removeProperty('font-size');
    const track = $('.progress-track', block);
    track.firstElementChild.style.width = Math.min(100, committed / goal * 100) + '%';
    track.setAttribute('aria-valuenow', String(Math.min(goal, committed)));
    track.setAttribute('aria-valuetext', money(committed) + ' funded and committed toward ' + money(goal));
    $('[data-received]', block).textContent = money(stats.received) + ' received · ' + (committed >= goal ? 'Yearly goal funded & committed' : 'Yearly goal: ' + Math.floor(committed / goal * 100) + '%');
  }
  if (config?.page === 'hub') {
    const records = config.homes.map(home => totals[home.id]);
    if (records.every(record => record && Number.isFinite(record.received))) {
      $('[data-overall-received]').textContent = money(records.reduce((sum, item) => sum + item.received, 0));
    }
  }
  if (config?.page === 'landing') {
    const stats = totals[config.home.id];
    if (stats && stats.committed >= config.home.goal && !activeCheckout && !pendingPayload()) {
      $('#donation-form').hidden = true;
      if ($('#confirmation').hidden) $('#form-status').textContent = 'This home’s yearly goal is funded and committed. Thank you for helping make its care more dependable.';
    }
  }
}
let refreshing = false;
async function refreshTotals() {
  if (!config || document.hidden || refreshing) return;
  if (isLocal) {
    $$('[data-raised]').forEach(node => { node.textContent = 'Live total on website'; node.style.fontSize = '13px'; });
    return;
  }
  refreshing = true;
  try {
    const url = config.page === 'hub' ? API + '?action=campaigns' : API + '?action=totals&campaign=' + encodeURIComponent(config.home.id);
    const response = await fetch(url, {cache: 'no-store', signal: AbortSignal.timeout(15000)});
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error('Totals not available');
    updateProgress(config.page === 'hub' ? data.campaigns : {[config.home.id]: data});
  } catch (_) {
    for (const node of $$('[data-raised]')) if (node.textContent === 'Checking…') { node.textContent = 'Awaiting update'; node.style.fontSize = '13px'; }
  } finally { refreshing = false; }
}

function setupHub() {
  let category = 'all', limit = 9;
  const cards = $$('.home-card');
  function filterHomes() {
    const search = $('#home-search').value.trim().toLowerCase();
    const matching = cards.filter(card => (category === 'all' || card.dataset.category === category) && card.dataset.search.includes(search));
    cards.forEach(card => { card.hidden = !matching.includes(card) || matching.indexOf(card) >= limit; });
    $('#result-count').textContent = 'Showing ' + Math.min(limit, matching.length) + ' of ' + matching.length + ' selected ' + (matching.length === 1 ? 'home' : 'homes');
    $('#empty-results').hidden = matching.length > 0;
    $('#more-homes').hidden = matching.length <= limit;
  }
  $$('[data-filter]').forEach(button => button.addEventListener('click', () => {
    category = button.dataset.filter; limit = 9;
    $$('[data-filter]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    filterHomes();
  }));
  $('#home-search').addEventListener('input', () => { limit = 9; filterHomes(); });
  $('#more-homes').addEventListener('click', () => { limit += 9; filterHomes(); });
  for (const link of $$('[data-home-link]')) {
    const url = new URL(link.href);
    Object.entries(attribution).forEach(([key, value]) => url.searchParams.set(key, value));
    link.href = url.pathname + url.search;
  }
  filterHomes();
  // Old shared links used a home ID hash; keep them useful after the redesign.
  const existingCard = cards.find(card => '#' + card.dataset.home === location.hash);
  if (existingCard) existingCard.scrollIntoView({block: 'center', behavior: 'instant'});
}
const retryKey = () => 'hccf-confirmation-' + (config?.home?.id || '');
function pendingPayload() {
  try {
    const record = JSON.parse(sessionStorage.getItem(retryKey()) || 'null');
    if (record && record.created > Date.now() - 48 * 60 * 60 * 1000 && record.payload?.campaign === config?.home?.id) return record.payload;
  } catch (_) { /* Private browsing may disable storage. */ }
  return null;
}
function saveRetry(payload) { try { sessionStorage.setItem(retryKey(), JSON.stringify({created: Date.now(), payload})); } catch (_) {} }
function clearRetry() { try { sessionStorage.removeItem(retryKey()); } catch (_) {} }
let checkoutLoader;
function loadCheckout() {
  if (window.Razorpay) return Promise.resolve();
  if (checkoutLoader) return checkoutLoader;
  checkoutLoader = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'; script.async = true;
    const timeout = setTimeout(() => { script.remove(); checkoutLoader = null; reject(new Error('Razorpay did not load. Please check your connection and try again.')); }, 20000);
    script.onload = () => { clearTimeout(timeout); if (window.Razorpay) resolve(); else { checkoutLoader = null; reject(new Error('Razorpay is unavailable. Please try again.')); } };
    script.onerror = () => { clearTimeout(timeout); script.remove(); checkoutLoader = null; reject(new Error('Razorpay could not load. Please check your connection.')); };
    document.head.appendChild(script);
  });
  return checkoutLoader;
}
async function paymentRequest(action, body) {
  const response = await fetch(API + '?action=' + action + '&campaign=' + encodeURIComponent(config.home.id), {
    method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({...body, campaign: config.home.id}), signal: AbortSignal.timeout(45000)
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || !result.success) throw new Error(result.error || 'The donation service is temporarily unavailable. Please try again.');
  return result;
}

function setupLanding() {
  const home = config.home, form = $('#donation-form'), fields = $('#donation-fields'), amountInput = $('#donation-amount');
  const amountStep = $('#amount-step'), donorStep = $('#donor-step'), status = $('#form-status'), retryButton = $('#verify-retry');
  let type = 'once', busy = false, memoryRetry = pendingPayload();
  const amount = () => Number(amountInput.value);
  function setStep(details) {
    amountStep.hidden = details; donorStep.hidden = !details;
    $$('input,select', donorStep).forEach(input => { input.disabled = !details; });
    if (details) $('[name=name]', form).focus({preventScroll: true});
  }
  function setBusy(value) {
    busy = value; fields.disabled = value || Boolean(memoryRetry); retryButton.disabled = value;
    $('#pay-donation').textContent = value ? 'Please wait…' : 'Donate ' + money(amount()) + (type === 'monthly' ? ' / month' : '') + ' securely →';
  }
  function updateAmount() {
    const value = amount();
    $$('[data-amount]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.amount) === value)));
    amountInput.max = String(type === 'monthly' ? Math.floor(home.goal / 12) : home.goal);
    $('#amount-label').textContent = type === 'monthly' ? 'Choose your monthly contribution' : 'Choose your contribution';
    $('#continue-donation').textContent = 'Continue with ' + money(value || 0) + (type === 'monthly' ? ' / month' : '') + ' →';
    $('#selected-gift').textContent = (type === 'monthly' ? 'Monthly support of ' : 'One-time gift of ') + money(value || 0);
    const note = $('#annual-note'); note.hidden = type !== 'monthly';
    note.replaceChildren();
    const strong = document.createElement('strong'); strong.textContent = money(value || 0) + ' × 12 months = ' + money(annualCommitment(value || 0, type)) + ' toward the yearly goal.';
    note.append(strong, document.createTextNode('Counted after the first confirmed payment. Collected monthly; future instalments are commitments, not money received.'));
    setBusy(busy);
  }
  function continueDonation() {
    if (busy || memoryRetry || !amountInput.reportValidity()) return;
    setStep(true); status.textContent = ''; updateAmount();
  }
  $$('[data-frequency]').forEach(button => button.addEventListener('click', () => {
    type = button.dataset.frequency;
    $$('[data-frequency]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    updateAmount();
  }));
  $$('[data-amount]').forEach(button => button.addEventListener('click', () => { amountInput.value = button.dataset.amount; updateAmount(); }));
  amountInput.addEventListener('input', updateAmount);
  $('#continue-donation').addEventListener('click', continueDonation);
  $('#edit-amount').addEventListener('click', () => { setStep(false); amountInput.focus(); });
  const idInput = $('[name=idNumber]', form), idType = $('[name=idType]', form);
  function idValidity() {
    const value = idInput.value.replace(/\s/g, '');
    const valid = idType.value === 'pan' ? /^[a-z]{5}\d{4}[a-z]$/i.test(value) : idType.value === 'aadhaar' ? /^\d{12}$/.test(value) : value.length >= 4;
    idInput.setCustomValidity(idInput.value && !valid ? 'Please enter a valid number for the selected government ID type.' : '');
  }
  idInput.addEventListener('input', idValidity); idType.addEventListener('change', idValidity);
  async function verify(payload) {
    memoryRetry = payload; saveRetry(payload); activeCheckout = true; setBusy(true);
    status.textContent = 'Confirming your payment. Please keep this page open.'; retryButton.hidden = true;
    try {
      const result = await paymentRequest('verify', payload);
      if (!Number.isFinite(result.amount) || !result.paymentId) throw new Error('Confirmation is incomplete');
      memoryRetry = null; clearRetry(); form.hidden = true; status.textContent = '';
      const confirmation = $('#confirmation'); confirmation.hidden = false; confirmation.className = 'confirmation';
      confirmation.innerHTML = '<div class="confirmed-icon" aria-hidden="true">✓</div><h3>Thank you for showing up.</h3><p class="confirmation-amount"></p><p class="reference"></p>';
      $('.confirmation-amount', confirmation).textContent = money(result.amount) + ' is confirmed for ' + home.name + '.';
      $('.reference', confirmation).textContent = 'Payment reference: ' + result.paymentId;
      if (result.donationType === 'monthly') {
        const note = document.createElement('p'); note.className = 'privacy-small';
        note.textContent = 'Your monthly support is part of a 12-month commitment. The progress total updates once the subscription and payment are confirmed.';
        confirmation.appendChild(note);
      }
      // Integration hook only. No advertising SDK or donor/ID data is sent here.
      window.dispatchEvent(new CustomEvent('hccf:donation-verified', {detail: {campaign: home.id, amount: result.amount, currency: 'INR', donationType: result.donationType}}));
      await refreshTotals();
    } catch (_) {
      status.textContent = 'Payment confirmation is pending. If money was debited, please do not pay again. Check confirmation below, or contact HUManity with payment reference ' + payload.razorpay_payment_id + '.';
      retryButton.hidden = false;
    } finally { activeCheckout = false; setBusy(false); }
  }
  retryButton.addEventListener('click', () => { if (memoryRetry) verify(memoryRetry); });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || memoryRetry) return;
    if (donorStep.hidden) { continueDonation(); return; }
    idValidity(); if (!form.reportValidity()) return;
    if (isLocal) { status.textContent = 'Local preview only. No donation or payment will be created here.'; return; }
    setBusy(true); status.textContent = 'Preparing secure checkout…';
    // Read values directly because the fieldset is disabled while checkout starts.
    const donor = {};
    for (const key of ['name','email','phone','idType','idNumber']) donor[key] = form.elements.namedItem(key).value.trim();
    try {
      await loadCheckout();
      const order = await paymentRequest('create', {amount: amount(), donationType: type, ...donor, phone: '+91' + donor.phone, attribution});
      let handled = false;
      const checkout = new window.Razorpay({
        key: order.keyId,
        ...(order.isSubscription ? {subscription_id: order.subscriptionId} : {order_id: order.orderId, amount: order.amount, currency: 'INR'}),
        name: 'HUManity Foundation', description: home.name,
        prefill: {name: donor.name, email: donor.email, contact: '+91' + donor.phone},
        theme: {color: '#14708b'},
        handler: payload => { handled = true; verify({...payload, donation_id: order.donationId, campaign: home.id}); },
        modal: {ondismiss: () => { if (!handled) { activeCheckout = false; setBusy(false); status.textContent = 'Checkout closed. Your details are still here if you would like to continue.'; } }}
      });
      checkout.on('payment.failed', () => { status.textContent = 'The payment was not completed. If your bank shows a debit, check your payment status before retrying.'; });
      activeCheckout = true; status.textContent = ''; checkout.open();
    } catch (error) { activeCheckout = false; setBusy(false); status.textContent = error.message; }
  });
  $$('[data-photo]').forEach(button => button.addEventListener('click', () => {
    const index = Number(button.dataset.photo), path = '/assets/hccf-homes/' + home.id + '/' + (index + 1);
    const image = $('.gallery-main'); image.src = path + '-1200.webp'; image.srcset = path + '-640.webp 640w, ' + path + '-1200.webp 1200w'; image.alt = home.photos[index][1];
    $('[data-photo-caption]').textContent = home.photos[index][1]; $('[data-photo-count]').textContent = index + 1 + ' / ' + home.photos.length;
    $$('[data-photo]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  }));
  $('#share-home').addEventListener('click', async () => {
    const url = 'https://humanityorg.foundation/wheelsofhope/fund/' + home.id + '/';
    try {
      if (navigator.share) await navigator.share({title: home.name + ' | HCCF', text: home.summary, url});
      else { await navigator.clipboard.writeText(url); $('#share-status').textContent = 'Fundraiser link copied.'; }
    } catch (error) { if (error.name !== 'AbortError') $('#share-status').textContent = 'Share this link: ' + url; }
  });
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(([entry]) => { $('#mobile-donate').hidden = entry.isIntersecting; }, {threshold: .12});
    observer.observe($('#donate'));
  }
  setStep(false); updateAmount();
  if (memoryRetry) { status.textContent = 'A previous payment is awaiting confirmation. Please check it before making another donation.'; retryButton.hidden = false; setBusy(false); }
}
if (config?.page === 'hub') setupHub();
if (config?.page === 'landing') setupLanding();
refreshTotals();
setInterval(refreshTotals, 45000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshTotals(); });
