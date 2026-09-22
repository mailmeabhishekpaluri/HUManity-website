// Isolated DOM test: all requests and payment callbacks are synthetic.
import assert from 'node:assert/strict';
import {readFileSync,existsSync} from 'node:fs';
import {createRequire} from 'node:module';
import {dirname,resolve} from 'node:path';
import {fileURLToPath} from 'node:url';
const here=dirname(fileURLToPath(import.meta.url));
const require=createRequire(resolve(process.env.HCCF_TEST_DEPS || '/tmp/hccf-brand-tests','package.json'));
const {JSDOM}=require('jsdom');
const site=process.env.DONATION_TEST_SITE || (existsSync(resolve(here,'../campaign-redesign/deploy/donate'))?resolve(here,'../campaign-redesign/deploy'):resolve(here,'../..'));
const html=readFileSync(resolve(site,'donate/index.html'),'utf8');
const code=readFileSync(resolve(here,'main-donate.js'),'utf8');
const settle=async predicate=>{for(let i=0;i<50&&!predicate();i++)await new Promise(resolve=>setTimeout(resolve,1));assert.ok(predicate(),'Expected checkout state reached');};
async function scenario(label,{pending=false,mismatch=false,local=false,restore=false}={}){
  const dom=new JSDOM(html,{url:local?'http://127.0.0.1:8081/donate/':'https://donation.test/donate/',pretendToBeVisual:true});
  const {window}=dom;
  Object.assign(globalThis,{window,document:window.document,location:window.location,sessionStorage:window.sessionStorage,CustomEvent:window.CustomEvent});
  window.HTMLElement.prototype.scrollIntoView=function(){};
  const $=selector=>document.querySelector(selector);
  let creates=[],verifications=[],optionsList=[],fail=pending,checkoutCount=0,events=[];
  window.addEventListener('humanity:donation-verified',event=>events.push(event.detail));
  // An unrelated HCCF confirmation must neither lock nor be cleared by main donations.
  sessionStorage.setItem('hccf-unrelated-confirmation','synthetic');
  if(restore)sessionStorage.setItem('humanity-main-confirmation',JSON.stringify({created:Date.now(),payload:{donation_id:'fixture_restore',donation_type:'once',razorpay_payment_id:'pay_restore',razorpay_signature:'fixture_signature'}}));
  globalThis.fetch=async(url,options)=>{
    const body=JSON.parse(options.body);
    assert.ok(url.startsWith('/backend/api/donations/'));
    assert.ok(!('campaign' in body),'Main donations must not be attributed to an HCCF campaign');
    if(url.endsWith('/create-order.php')){
      creates.push(body);
      assert.deepEqual(Object.keys(body).sort(),['amount','donationType','email','idNumber','idType','name','phone']);
      assert.equal(body.phone,'+919000000000');
      return {ok:true,json:async()=>({success:true,donationId:'fixture_'+creates.length,razorpayKeyId:'fixture_key',amount:body.amount*100,currency:mismatch?'USD':'INR',isSubscription:body.donationType==='monthly',orderId:'order_fixture',subscriptionId:'sub_fixture'})};
    }
    verifications.push({url,body});
    assert.equal(url.endsWith('/verify-subscription.php'),body.donation_type==='monthly');
    return {ok:!fail,json:async()=>fail?{success:false}:{success:true,amount:2000,paymentId:body.razorpay_payment_id,donationType:body.donation_type}};
  };
  window.Razorpay=class{
    constructor(options){this.options=options;optionsList.push(options);}
    on(){}
    open(){checkoutCount++;queueMicrotask(()=>this.options.handler({razorpay_payment_id:'pay_fixture_'+checkoutCount,razorpay_signature:'fixture_signature'}));}
  };
  await import('data:text/javascript;base64,'+Buffer.from(code+'\n// '+label).toString('base64'));
  assert.equal($('[data-frequency=monthly]').getAttribute('aria-pressed'),'true');
  assert.equal($('#donation-amount').value,'2000');
  assert.match($('#gift-explanation').textContent,/₹24,000/);
  assert.equal($('#donation-form').hidden,false);
  assert.equal($('[name=name]').closest('[hidden]'),null,'Personal details always visible');
  function submit(){
    for(const [key,value] of Object.entries({name:'Synthetic Donor',email:'fixture@example.invalid',phone:'9000000000',idType:'ration',idNumber:'FIXTURE-ONLY'})){
      const field=$(`[name=${key}]`);field.value=value;field.dispatchEvent(new window.Event('input',{bubbles:true}));
    }
    $('#donation-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));
  }
  if(restore){
    assert.equal($('#donation-fields').disabled,true);
    assert.equal($('#verify-retry').hidden,false);
    $('#verify-retry').click();await settle(()=>!$('#confirmation').hidden);
    assert.equal(creates.length,0);assert.equal(checkoutCount,0);
    assert.match($('#confirmation').textContent,/pay_restore/);
  }else{
    submit();
    if(local){
      assert.equal(creates.length,0);assert.equal(checkoutCount,0);assert.match($('#form-status').textContent,/Local preview only/);
    }else if(mismatch){
      await settle(()=>$('#form-status').textContent.includes('could not be confirmed'));
      assert.equal(checkoutCount,0);assert.equal($('#donation-fields').disabled,false);
    }else{
      await settle(()=>verifications.length===1&&!$('#donate-again').disabled);
      assert.equal(creates[0].amount,2000);assert.equal(creates[0].donationType,'monthly');
      assert.ok(optionsList[0].subscription_id);assert.ok(!optionsList[0].order_id);
      assert.deepEqual(Object.keys(optionsList[0].prefill).sort(),['contact','email','name'],'Government ID never sent to Checkout');
      if(pending){
        assert.equal($('#donation-fields').disabled,true);
        assert.equal($('#verify-retry').hidden,false);
        assert.equal($('#donate-again').hidden,true);
        assert.equal(sessionStorage.getItem('humanity-main-confirmation').includes('FIXTURE-ONLY'),false);
        submit();assert.equal(creates.length,1,'Pending state prevents a second checkout');
        fail=false;$('#verify-retry').click();await settle(()=>!$('#confirmation').hidden);
        assert.equal(creates.length,1,'Verification retry never charges again');
      }else{
        assert.equal($('#donation-form').hidden,true);
        assert.match($('#confirmation').textContent,/pay_fixture_1/);
        $('#donate-again').click();
        assert.equal($('#donation-form').hidden,false);
        assert.equal($('[data-frequency=once]').getAttribute('aria-pressed'),'true');
        assert.equal($('#monthly-terms').hidden,true);
        assert.equal($('[name=idNumber]').value,'');assert.equal($('[name=email]').value,'');
        assert.match($('#previous-donation').textContent,/existing monthly subscription continues/);
        submit();await settle(()=>verifications.length===2&&!$('#donate-again').disabled);
        assert.equal(creates[1].donationType,'once');
        assert.ok(optionsList[1].order_id);assert.ok(!optionsList[1].subscription_id);
        assert.match($('#confirmation').textContent,/pay_fixture_2/);
        assert.equal(events.length,2);
        assert.deepEqual(Object.keys(events[0]).sort(),['amount','currency','donationType','source']);
      }
      assert.equal(sessionStorage.getItem('humanity-main-confirmation'),null);
    }
  }
  assert.equal(sessionStorage.getItem('hccf-unrelated-confirmation'),'synthetic');
  $('.giving-tax').click();assert.equal($('#tax-details').open,true);
  dom.window.close();
}
await scenario('monthly gift, main endpoints, repeat one-time gift');
await scenario('pending payment locks new checkout then retries safely',{pending:true});
await scenario('reload recovers confirmation without creating a payment',{restore:true});
await scenario('incorrect server currency cannot launch checkout',{mismatch:true});
await scenario('local preview cannot create payments',{local:true});
console.log('Passed main monthly/one-time checkout routing, default ₹2,000, field privacy, repeat gift, pending/reload confirmation, mismatched currency protection, HCCF separation and local preview guard.');
