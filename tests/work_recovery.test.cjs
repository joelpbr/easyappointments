const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup(fetch) {
 const listeners={}, events=[], values={csrf_token:'old',base_url:'https://clinic.test'};
 const box={dataset:{userId:'7'},children:[],replaceChildren(){this.children=[];},append(el){this.children.push(el);}};
 const window={App:{Utils:{}},addEventListener:(name,fn)=>{listeners[name]=fn;},dispatchEvent:e=>events.push(e.type),confirm:()=>false};
 const document={hidden:false,querySelectorAll:()=>[],querySelector:()=>null,getElementById:()=>box,createElement:()=>({addEventListener(){}}),addEventListener:(name,fn)=>{listeners[name]=fn;}};
 const context={App:window.App,window,document,fetch,vars:key=>key?values[key]:values,lang:key=>key,AbortController,Event,URL,location:new URL('https://clinic.test/calendar'),setTimeout:()=>1,clearTimeout(){},setInterval(){}};
 vm.runInNewContext(fs.readFileSync('assets/js/utils/work_recovery.js','utf8'),context);
 return {api:window.App.Utils.WorkRecovery,box,listeners,events,values,document};
}
test('wake coalesces requests, renews CSRF and notifies the calendar without navigating',async()=>{
 let resolve,calls=0;
 const s=setup((_url,options)=>{calls++;assert.equal(options.headers['X-Requested-With'],'XMLHttpRequest');return new Promise(r=>resolve=r);});
 s.document.hidden=true;s.listeners.visibilitychange();assert.equal(s.api.isReady(),false);
 s.document.hidden=false;const first=s.api.check();const second=s.api.check();assert.equal(first,second);
 resolve({ok:true,status:200,json:async()=>({authenticated:true,user_id:7,csrf_token:'new'})});
 assert.equal(await first,true);assert.equal(calls,1);assert.equal(s.values.csrf_token,'new');assert.deepEqual(s.events,['app:reconnected']);assert.equal(s.box.hidden,true);
});
for(const status of [401,403])test(`expired session (${status}) retains the page and offers separate sign-in`,async()=>{
 const s=setup(async()=>({ok:false,status}));assert.equal(await s.api.check(),false);assert.equal(s.api.isReady(),false);
 assert.equal(s.box.children[0].textContent,'work_session_expired');assert.equal(s.box.children[1].target,'_blank');assert.deepEqual(s.events,[]);
});
test('another signed-in account cannot refresh the previous account data',async()=>{
 const s=setup(async()=>({ok:true,status:200,json:async()=>({authenticated:true,user_id:8,csrf_token:'wrong'})}));
 assert.equal(await s.api.check(),false);assert.equal(s.values.csrf_token,'old');assert.deepEqual(s.events,[]);
});
test('offline failure can recover on retry',async()=>{
 let online=false;const s=setup(async()=>{if(!online)throw Error();return {ok:true,json:async()=>({authenticated:true,user_id:7,csrf_token:'new'})};});
 assert.equal(await s.api.check(),false);assert.equal(s.box.children[0].textContent,'work_offline');
 online=true;assert.equal(await s.api.check(),true);
});
test('unsaved notes block navigation and warn before reload, while clean forms do not',()=>{
 const s=setup();let dirty=true;s.api.register(()=>dirty);
 let prevented=0;const event={preventDefault(){prevented++;},stopImmediatePropagation(){},target:{closest:()=>({href:'https://clinic.test/customers',hasAttribute:()=>false})}};
 s.listeners.click(event);s.listeners.beforeunload(event);assert.equal(prevented,2);
 dirty=false;s.listeners.click(event);s.listeners.beforeunload(event);assert.equal(prevented,2);
});
