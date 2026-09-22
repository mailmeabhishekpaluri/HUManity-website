// Isolated DOM test. Every network call and Razorpay callback is synthetic.
import assert from 'node:assert/strict';
import {readFileSync,existsSync} from 'node:fs';
import {createRequire} from 'node:module';
import {dirname,resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
const here=dirname(fileURLToPath(import.meta.url));
const require=createRequire(resolve(process.env.HCCF_TEST_DEPS || '/tmp/hccf-brand-tests','package.json'));
const {JSDOM}=require('jsdom');
const site=existsSync(resolve(here,'deploy/wheelsofhope'))?resolve(here,'deploy'):resolve(here,'../..');
const home='indian-gypsy-children-home-suryapet';
const html=readFileSync(resolve(site,'wheelsofhope/fund',home,'index.html'),'utf8');
const code=readFileSync(resolve(here,'campaign.js'),'utf8');
const interval=globalThis.setInterval;
globalThis.setInterval=()=>0;

async function scenario(label,{failVerification=false,reachGoal=false}={}) {
  const dom=new JSDOM(html,{url:'https://campaign.test/wheelsofhope/fund/'+home+'/',pretendToBeVisual:true});
  const {window}=dom;
  Object.assign(globalThis,{window,document:window.document,location:window.location,sessionStorage:window.sessionStorage,CustomEvent:window.CustomEvent});
  window.HTMLElement.prototype.scrollIntoView=function(){};
  let sequence=0,created=[],verified=0,lastGift,received=0,committed=0;
  globalThis.fetch=async(url,options={})=>{
    const action=new URL(url,'https://campaign.test').searchParams.get('action');
    if(action==='totals')return {ok:true,json:async()=>({success:true,received,committed,goal:600000})};
    const body=JSON.parse(options.body);
    assert.equal(body.campaign,home);
    if(action==='create'){
      sequence++;created.push(body);lastGift=body;
      return {ok:true,json:async()=>({success:true,donationId:'fixture_'+sequence,keyId:'test_only',isSubscription:body.donationType==='monthly',subscriptionId:'sub_fixture_'+sequence,orderId:'order_fixture_'+sequence,amount:body.amount*100})};
    }
    if(action==='verify'){
      verified++;
      if(failVerification)return {ok:false,json:async()=>({success:false,error:'Synthetic pending verification'})};
      received+=lastGift.amount;committed=reachGoal?600000:committed+lastGift.amount*(lastGift.donationType==='monthly'?12:1);
      return {ok:true,json:async()=>({success:true,amount:lastGift.amount,paymentId:'pay_fixture_'+sequence,donationType:lastGift.donationType})};
    }
    throw new Error('Unexpected network request blocked: '+action);
  };
  window.Razorpay=class {
    constructor(options){this.options=options;}
    on(){}
    open(){queueMicrotask(()=>this.options.handler({razorpay_payment_id:'pay_fixture_'+sequence,razorpay_signature:'fixture_signature'}));}
  };
  await import('data:text/javascript;base64,'+Buffer.from(code+'\n// '+label).toString('base64'));
  const $=s=>document.querySelector(s);
  const settle=async(predicate)=>{for(let i=0;i<30&&!predicate();i++)await new Promise(resolve=>setTimeout(resolve,1));assert.ok(predicate(),label+' settled');};
  function details(){
    $('#continue-donation').click();
    for(const [key,value] of Object.entries({name:'Synthetic Donor',email:'fixture@example.invalid',phone:'9000000000',idType:'ration',idNumber:'FIXTURE-ONLY'})){
      const input=$(`[name=${key}]`);input.value=value;input.dispatchEvent(new window.Event('input',{bubbles:true}));
    }
    $('#donation-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
  }
  $('[data-frequency=monthly]').click();$('[data-amount="500"]').click();
  assert.match($('#annual-note').textContent,/₹6,000/);
  details();
  await settle(()=>verified===1 && (failVerification?!$('#verify-retry').hidden:!$('#donate-again').disabled||reachGoal));
  assert.equal(created.length,1);
  if(failVerification){
    assert.equal($('#donate-again').hidden,true,'Cannot donate again before verification');
    assert.equal($('#donation-fields').disabled,true,'Pending confirmation locks another checkout');
    assert.ok(sessionStorage.length>0,'Pending receipt is recoverable');
  }else if(reachGoal){
    await settle(()=>$('#donate-again').textContent.includes('goal reached'));
    assert.equal($('#donate-again').disabled,true,'Goal limit remains enforced');
  }else{
    assert.equal($('#donation-form').hidden,true);
    assert.match($('#confirmation').textContent,/pay_fixture_1/);
    assert.equal(sessionStorage.length,0,'Confirmed retry record cleared');
    $('#donate-again').click();
    assert.equal($('#donation-form').hidden,false);
    assert.equal($('#confirmation').hidden,true);
    assert.equal($('[data-frequency=once]').getAttribute('aria-pressed'),'true','New gift defaults to one-time');
    assert.equal($('[name=idNumber]').value,'','Government ID cleared');
    assert.equal($('[name=email]').value,'');
    assert.match($('#previous-donation').textContent,/existing monthly subscription continues/);
    assert.match($('#previous-donation').textContent,/pay_fixture_1/);
    assert.equal(created.length,1,'Restart itself cannot create a payment');
    details();await settle(()=>verified===2&&!$('#donate-again').disabled);
    assert.equal(created[1].donationType,'once');
    assert.match($('#confirmation').textContent,/pay_fixture_2/);
    $('.tax-benefit').click();assert.equal($('#tax-details').open,true);
  }
  dom.window.close();
}
await scenario('confirmed gift and second donation');
await scenario('pending verification cannot restart',{failVerification:true});
await scenario('goal reached cannot restart',{reachGoal:true});
globalThis.setInterval=interval;
console.log('Passed Donate Again after verification, fresh second checkout, monthly-to-once reset, private-field clearing, pending-payment lock and completed-goal protection.');
