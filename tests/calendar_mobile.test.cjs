const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function setup() {
 const queued=[];
 const context={App:{Utils:{}},setTimeout:fn=>queued.push(fn),window:{innerHeight:800},document:{getElementById:()=>({})},getComputedStyle:()=>({paddingBottom:'20px'})};
 vm.runInNewContext(fs.readFileSync('assets/js/utils/calendar_mobile.js','utf8'),context);
 return {api:context.App.Utils.CalendarMobile,context,flush:()=>queued.splice(0).forEach(fn=>fn())};
}
test('quick tap opens one appointment at the tapped time',()=>{
 const {api,flush}=setup(); const calls=[]; const handlers=api.selectionHandlers(info=>calls.push(info));
 const date=new Date(); handlers.dateClick({date,allDay:false,jsEvent:{}}); flush();
 assert.equal(calls.length,1); assert.equal(calls[0].start,date); assert.equal(calls[0].end,date);
});
for(const order of ['click-first','select-first']) test(`selection wins over click (${order})`,()=>{
 const {api,flush}=setup(); const calls=[]; const handlers=api.selectionHandlers(info=>calls.push(info));
 const jsEvent={}; const range={start:new Date(0),end:new Date(3600000),jsEvent};
 const click=()=>handlers.dateClick({date:range.start,allDay:false,jsEvent});
 if(order==='click-first')click(); handlers.select(range); if(order==='select-first')click(); flush();
 assert.equal(calls.length,1); assert.equal(calls[0],range);
});
test('drag keeps its complete interval and all-day click keeps navigation',()=>{
 const {api,flush}=setup(); const calls=[]; const days=[]; const handlers=api.selectionHandlers(i=>calls.push(i),i=>days.push(i));
 const range={start:new Date(0),end:new Date(7200000),jsEvent:{}}; handlers.select(range);
 handlers.dateClick({allDay:true,date:new Date(),jsEvent:{}}); flush();
 assert.deepEqual(calls,[range]); assert.equal(days.length,1);
});
test('height fits small visual viewport, safe area and expanded controls',()=>{
 const {api,context}=setup(); let top=100; const element={getBoundingClientRect:()=>({top})};
 assert.equal(api.height(element),680);
 context.window.visualViewport={height:500,offsetTop:0}; assert.equal(api.height(element),380);
 top=240; assert.equal(api.height(element),240);
 context.window.visualViewport.height=200; assert.equal(api.height(element),0);
});
test('tap respects unavailable selection areas',()=>{
 const {api,flush}=setup(); const calls=[];
 api.selectionHandlers(i=>calls.push(i),undefined,()=>false).dateClick({date:new Date(),allDay:false,jsEvent:{}});
 flush(); assert.equal(calls.length,0);
});
