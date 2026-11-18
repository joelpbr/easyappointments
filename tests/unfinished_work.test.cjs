const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('assets/js/components/clinical_record_modal.js','utf8');
function extract(source,name){const start=source.indexOf('    function '+name+'(');return source.slice(start,source.indexOf('\n    }',start)+6);}
function visit(){
 const state={data:{record:{observations:'sent'},surgeryPrice:'',markers:null},notifications:0,errors:0,calls:0};
 const callbacks={};const request={done(fn){callbacks.done=fn;return this;},fail(fn){callbacks.fail=fn;return this;},always(fn){callbacks.always=fn;return this;}};
 const context={getVisitData:()=>state.data,$:()=>({prop(){}}),saveError:()=>state.errors++,updateSaveButtons(){},renderMarkers(){},updateVisitSubtitle(){},$visitDetail:{data:()=>''},lang:x=>x,onChange(){},renderVisits(){},
 App:{Http:{ClinicalRecords:{saveAppointment(){state.calls++;return request;},findPatient:()=>({done(){}})}},Layouts:{Backend:{displayNotification:()=>state.notifications++}}}};
 vm.runInNewContext(`let visitSaveRequest=null,visitAppointmentId=1,customerId=2,feet=null,markerKey=0,visitPrice=0,savedVisit='original';${extract(source,'saveVisit')}${extract(source,'isVisitDirty')}globalThis.save=saveVisit;globalThis.dirty=isVisitDirty;globalThis.saved=()=>savedVisit;`,context);
 return {state,context,callbacks};
}
test('notes typed while saving remain unsaved after the earlier request succeeds',()=>{
 const {state,context,callbacks}=visit();context.save();state.data={...state.data,record:{observations:'newer text'}};
 callbacks.done({price:null});callbacks.always();assert.equal(context.dirty(),true);assert.equal(JSON.parse(context.saved()).record.observations,'sent');assert.equal(state.data.record.observations,'newer text');
});
test('failed clinical save retains the draft and never reports success',()=>{
 const {state,context,callbacks}=visit();context.save();context.save();assert.equal(state.calls,1);
 callbacks.fail();callbacks.always();assert.equal(state.errors,1);assert.equal(state.notifications,0);assert.equal(context.dirty(),true);assert.equal(state.data.record.observations,'sent');context.save();assert.equal(state.calls,2);
});
test('an unchanged visit becomes clean only after the server accepts it',()=>{
 const {context,callbacks}=visit();context.save();assert.equal(context.dirty(),true);callbacks.done({price:null});callbacks.always();assert.equal(context.dirty(),false);
});
test('signature close requires explicit discard and cannot interrupt a save',()=>{
 const source=fs.readFileSync('assets/js/components/patient_consent.js','utf8');let accept=false,closed=0,prompts=0;
 const context={window:{confirm:()=>{prompts++;return accept;}},lang:x=>x,$id:()=>({}),App:{Components:{DocumentDialog:{close:()=>closed++}}}};
 vm.runInNewContext(`let dirty=true,saving=false;${extract(source,'requestClose')}globalThis.close=requestClose;globalThis.saving=()=>{saving=true;};`,context);
 context.close();assert.equal(closed,0);accept=true;context.close();assert.equal(closed,1);context.saving();context.close();assert.equal(closed,1);assert.equal(prompts,2);
});
for (const result of ['network-error', 'invalid-response', 'saved']) test(`signature save preserves the drawing unless confirmed (${result})`, async () => {
    const source=fs.readFileSync('assets/js/components/patient_consent.js','utf8');
    const start=source.indexOf('    async function save()');
    const save=source.slice(start,source.indexOf('\n    }',start)+6);
    const controls=[{disabled:false},{disabled:true}];
    const nodes=new Map();
    const node=id=>{if(!nodes.has(id))nodes.set(id,{textContent:'',disabled:false,value:'Test',querySelectorAll:()=>controls});return nodes.get(id);};
    let closed=0;
    const context={$id:node,document:{querySelectorAll:()=>[]},lang:x=>x,url:x=>x,vars:()=>'',customerId:1,language:()=> 'ca',role:()=> 'patient',URLSearchParams,AbortController,setTimeout:()=>1,clearTimeout(){},showStatus(){},pad:{toDataURL:()=> 'preserved-drawing'},fetch:async()=>{
        if(result==='network-error')throw Error('offline');
        return {ok:true,json:async()=>result==='saved'?{current:{id:1}}:{}};
    },App:{Utils:{WorkRecovery:{isReady:()=>true,check(){}}},Components:{DocumentDialog:{close(){closed++;}}},Layouts:{Backend:{displayNotification(){}}}}};
    vm.runInNewContext(`let saving=false,dirty=true,strokes=8,form={};${save};globalThis.save=save;globalThis.state=()=>({dirty,strokes,saving});`,context);
    await context.save();
    assert.equal(context.state().strokes,8);
    assert.equal(context.state().saving,false);
    assert.equal(context.state().dirty,result!=='saved');
    assert.equal(closed,result==='saved'?1:0);
    assert.equal(controls[0].disabled,false);assert.equal(controls[1].disabled,true);
    if(result!=='saved')assert.equal(node('patient-consent-error').textContent,'work_save_failed');
});

