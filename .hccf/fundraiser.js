function HccfStoryCard({story}) {
  const h = N.createElement;
  const goal = story.goal;
  const money = value => '₹' + Number(value).toLocaleString('en-IN');
  const [stats, setStats] = N.useState(null);
  const [amount, setAmount] = N.useState('1000');
  const [busy, setBusy] = N.useState(false);
  const [message, setMessage] = N.useState('');
  const [receipt, setReceipt] = N.useState(null);
  const [shareMessage, setShareMessage] = N.useState('');
  const [retry, setRetry] = N.useState(null);
  const formRef = N.useRef(null);
  const local = ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
  const api = story.api;
  const refresh = N.useCallback(async () => {
    if (local || !api) return;
    try {
      const r = await fetch(api + '?action=totals', {cache:'no-store'});
      const d = await r.json();
      if (r.ok && d.success && Number.isFinite(d.raised) && d.raised >= 0) setStats(d);
    } catch (_) { /* Unavailable is never displayed as zero. */ }
  }, [local, api]);
  N.useEffect(() => {
    refresh();
    const timer = setInterval(refresh, 30000);
    return () => { clearInterval(timer);  };
  }, [refresh]);
  async function request(action, data) {
    const response = await fetch(api + '?action=' + action, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
    const result = await response.json().catch(() => ({}));
    if (!response.ok || !result.success) throw new Error(result.error || 'We could not connect to the donation service. Please try again.');
    return result;
  }
  async function verify(payload) {
    setBusy(true);
    setMessage('Confirming your payment. Please keep this page open.');
    try {
      const result = await request('verify', payload);
      setReceipt(result);
      setRetry(null);
      setMessage('Your donation is confirmed. Thank you for supporting this home.');
      refresh();
    } catch (_) {
      setRetry(payload);
      setMessage('Your payment confirmation is pending. If money was debited, please do not pay again. Check confirmation below or contact HUManity with payment reference ' + payload.razorpay_payment_id + '.');
    } finally { setBusy(false); }
  }
  async function donate(event) {
    event.preventDefault();
    if (busy || retry) return;
    if (!api) { setMessage('Donations for this home will open soon.'); return; }
    setMessage('');
    if (local) { setMessage('This is a local preview. No payment will be taken. Razorpay checkout will be available after the fundraiser is configured on the live website.'); return; }
    if (!Number.isInteger(Number(amount)) || Number(amount) < 1 || Number(amount) > goal) { setMessage('Please choose a whole-rupee amount from ₹1 to ' + money(goal) + '.'); return; }
    if (!window.Razorpay) { setMessage('Razorpay could not load. Please check your connection and try again.'); return; }
    const fields = new FormData(event.currentTarget);
    setBusy(true);
    try {
      const order = await request('create', {amount:Number(amount), name:fields.get('name'), email:fields.get('email'), phone:fields.get('phone')});
      const checkout = new window.Razorpay({
        key:order.keyId, order_id:order.orderId, amount:order.amount, currency:'INR',
        name:'HUManity', description:story.name + ' · ' + story.location,
        prefill:{name:fields.get('name'), email:fields.get('email'), contact:fields.get('phone')},
        theme:{color:'#3191c2'},
        handler:payload => verify({...payload, donation_id:order.donationId}),
        modal:{ondismiss:() => { setBusy(false); setMessage('Checkout closed. You can return whenever you are ready.'); }}
      });
      checkout.on('payment.failed', () => { setMessage('The payment was not completed. If an amount was debited, check with your bank before retrying.'); });
      checkout.open();
    } catch (error) { setMessage(error.message); setBusy(false); }
  }
  async function share() {
    const url = 'https://humanityorg.foundation/wheelsofhope/fund#' + story.id;
    const text = 'Support ' + story.name + ' in ' + story.location + '. Help reach the ' + money(goal) + ' goal.';
    try {
      if (navigator.share) await navigator.share({title:story.appeal, text, url});
      else { await navigator.clipboard.writeText(url); setShareMessage('Fundraiser link copied'); }
    } catch (error) { if (error.name !== 'AbortError') setShareMessage('Share this link: ' + url); }
  }
  const raised = stats ? stats.raised : null;
  const percent = raised === null ? null : Math.min(100, raised / goal * 100);
  const complete = raised !== null && raised >= goal;
  return h('article',{className:'hccf-story-card',id:story.id},
    h(HccfPhotoCarousel,{photos:story.photos,name:story.name}),
    h('div',{className:'hccf-story-body'},
      h('p',{className:'hccf-story-location'},story.location + ' · Since ' + story.established),
      h('h3',null,story.name),
      h('p',{className:'hccf-story-appeal'},story.appeal),
      h('p',null,story.story),h('p',null,story.need),
      h('details',{className:'hccf-needs-detail'},h('summary',null,'The home’s priority needs'),
        h('ul',null,story.needs.map(need=>h('li',{key:need},need))),h('p',null,story.budgetNote)),
      h('div',{className:'hccf-card-progress'},
        h('div',{className:'hccf-progress-heading'},h('strong',null,'Goal: ' + money(goal)),h('span',null,raised===null?'Raised: awaiting confirmation':money(raised)+' raised')),
        h('div',{className:'hccf-meter',role:'progressbar','aria-label':story.name+' fundraising progress','aria-valuemin':0,'aria-valuemax':goal,...(raised===null?{'aria-valuetext':'Awaiting confirmed total'}:{'aria-valuenow':Math.min(goal,raised)})},h('div',{style:{width:(percent||0)+'%'}})),
        h('p',{className:'hccf-progress-note'},complete?'Goal reached — thank you!':percent===null?'Progress will reflect verified donations to this home.':Math.floor(percent)+'% achieved · '+money(Math.max(0,goal-raised))+' to go')),
      h('details',{className:'hccf-give-detail'},h('summary',null,complete?'Goal reached · Thank you':receipt?'Your donation confirmation':'Support this home →'),
        h('div',{className:'hccf-checkout'},
            complete?h('p',{className:'cci-success'},'Thank you for helping reach this home’s goal. Follow the campaign updates as support is put to work.'):
            receipt?h('div',{className:'cci-success',role:'status'},h('h3',null,'Thank you for showing up.'),h('p',null,money(receipt.amount)+' has been confirmed for this home.'),h('small',null,'Payment reference: '+receipt.paymentId)):
            h('form',{ref:formRef,onSubmit:donate},
              h('fieldset',{disabled:busy||Boolean(retry)},h('legend',null,'Choose your contribution'),
                h('div',{className:'cci-amounts'},[500,1000,2500,5000].map(v=>h('button',{type:'button',key:v,'aria-pressed':amount===String(v),onClick:()=>setAmount(String(v))},money(v)))),
                h('label',{className:'cci-field'},h('span',null,'Your amount (₹)'),h('input',{type:'number',name:'amount',min:1,max:goal,step:1,inputMode:'numeric',required:true,value:amount,onChange:e=>setAmount(e.target.value)})),
                h('div',{className:'cci-donor-fields'},[['name','Full name','text','name'],['email','Email address','email','email'],['phone','Mobile number','tel','tel']].map(([name,label,type,auto])=>h('label',{className:'cci-field',key:name},h('span',null,label),h('input',{name,type,autoComplete:auto,required:true,maxLength:name==='name'?120: name==='email'?254:20,...(name==='phone'?{pattern:'[+0-9 ()-]{10,20}',inputMode:'tel'}:{})})))),
                h('button',{className:'cci-donate-button',type:'submit'},busy?'Please wait…':'Donate '+(Number(amount)>0?money(amount):'now')+' →'),
                h('p',{className:'cci-payment-note'},'One-time donation · Payments via Razorpay'),
                h('p',{className:'cci-privacy-note'},'Your details are used to process and acknowledge your donation. ',h('a',{href:'/privacy'},'Privacy policy')))),
            message&&h('p',{className:receipt?'cci-status cci-success':'cci-status',role:'status'},message),
            retry&&h('button',{className:'cci-donate-button',type:'button',disabled:busy,onClick:()=>verify(retry)},busy?'Checking…':'Check payment confirmation'),
          null)),
      h('button',{className:'hccf-share',type:'button',onClick:share},'Share this home’s fundraiser ↗'),
      shareMessage&&h('p',{role:'status'},shareMessage)));
}

function HccfPhotoCarousel({photos,name}) {
  const h=N.createElement;
  const [index,setIndex]=N.useState(0);
  const [playing,setPlaying]=N.useState(false);
  const [visible,setVisible]=N.useState(false);
  const [interacting,setInteracting]=N.useState(false);
  const ref=N.useRef(null);
  N.useEffect(()=>{
    const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
    setPlaying(!motion.matches);
    const onMotion=()=>setPlaying(!motion.matches);
    motion.addEventListener('change',onMotion);
    const observer=new IntersectionObserver(([entry])=>setVisible(entry.isIntersecting),{threshold:0.2});
    if(ref.current) observer.observe(ref.current);
    return()=>{observer.disconnect();motion.removeEventListener('change',onMotion);};
  },[]);
  N.useEffect(()=>{
    if(!playing||!visible||interacting||photos.length<2) return;
    const timer=setInterval(()=>setIndex(i=>(i+1)%photos.length),4500);
    return()=>clearInterval(timer);
  },[playing,visible,interacting,photos.length]);
  const move=delta=>{setPlaying(false);setIndex(i=>(i+delta+photos.length)%photos.length);};
  return h('div',{className:'hccf-carousel',ref,role:'region','aria-label':name+' photos','aria-roledescription':'carousel',onMouseEnter:()=>setInteracting(true),onMouseLeave:()=>setInteracting(false),onFocus:()=>setInteracting(true),onBlur:event=>{if(!event.currentTarget.contains(event.relatedTarget))setInteracting(false);}},
    h('div',{className:'hccf-photo-window'},h('div',{className:'hccf-photo-track',style:{transform:'translateX(-'+index*100+'%)'}},photos.map((photo,i)=>h('figure',{key:photo.src,'aria-hidden':index!==i},h('img',{src:photo.src,alt:photo.caption,loading:'lazy',decoding:'async',width:1280,height:960}))))),
    h('div',{className:'hccf-photo-caption','aria-live':'off'},photos[index].caption),
    h('div',{className:'hccf-photo-controls'},
      h('button',{type:'button',onClick:()=>move(-1),'aria-label':'Previous photo'},'←'),
      h('span',null,(index+1)+' / '+photos.length),
      h('button',{type:'button',onClick:()=>move(1),'aria-label':'Next photo'},'→'),
      h('button',{className:'hccf-play',type:'button',onClick:()=>setPlaying(p=>!p),'aria-label':playing?'Pause slideshow':'Play slideshow'},playing?'Pause':'Play')));
}

function HccfStoriesSection(){
  const h=N.createElement;
  const [limit,setLimit]=N.useState(6);
  return h('section',{className:'hccf-stories',id:'cci-stories','aria-labelledby':'hccf-stories-heading'},
    h('div',{className:'container mx-auto px-4'},
      h('div',{className:'hccf-stories-heading'},
        h('div',null,h('p',{className:'hccf-story-eyebrow'},'THE INSTITUTIONS BEHIND THE FUND'),
          h('h2',{id:'hccf-stories-heading'},'Different homes. One shared commitment.'),
          h('p',null,'Meet the institutions, understand their needs, and support a home’s fundraising goal. We will add more stories as the campaign grows toward 30 Child Care Institutions.')),
        h('span',{className:'hccf-story-count'},HCCF_STORIES.length+' '+(HCCF_STORIES.length===1?'home featured':'homes featured')+' · 30 planned')),
      h('div',{className:'hccf-story-list'},HCCF_STORIES.slice(0,limit).map(story=>h(HccfStoryCard,{story,key:story.id}))),
      limit<HCCF_STORIES.length&&h('button',{className:'hccf-load-more',type:'button',onClick:()=>setLimit(n=>n+6)},'View more homes')));
}

function nse(){
  const original=HccfOriginalPage();
  const children=N.Children.toArray(original.props.children);
  // Keep every original section in order; insert the CCI stories before the final campaign CTA.
  children.splice(Math.max(0,children.length-1),0,N.createElement(HccfStoriesSection,{key:'cci-stories'}));
  return N.cloneElement(original,{},...children);
}
