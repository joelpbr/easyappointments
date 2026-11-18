const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('assets/js/utils/calendar_table_view.js','utf8');
const replacement = source.slice(source.indexOf('    function replaceDisplayedEvents('), source.indexOf('    // Event Listeners'));
const sync = source.slice(source.indexOf('    function syncAllDaySlot('), source.indexOf('    // Public API'));

test('opening a table calendar aligns 08:45 or the first earlier visible appointment below the heading', () => {
    const start = source.indexOf('    function scrollToStartTime(');
    const scroll = source.slice(start, source.indexOf('\n    }', start) + 6);
    const row = (top, height) => ({getBoundingClientRect: () => ({top, height})});
    // Half-hour rows put 08:45 halfway between two grid lines.
    const lanes = Array.from({length: 48}, (_, index) => row(60 + index * 30, 30));
    const appointments = [];
    const view = {
        scrollTop: 0,
        querySelector: selector => selector === '.fc-timegrid-slots'
            ? {querySelectorAll: () => lanes}
            : {offsetHeight: 40},
        querySelectorAll: () => appointments,
        getBoundingClientRect: () => ({top: 20}),
    };
    const context = {document: {querySelector: () => view}, vars: () => 30};
    vm.runInNewContext(source.match(/const START_MINUTES = [^;]+;/)[0] + scroll, context);
    for (const [rows, expected] of [
        [[], 525],
        [[row(60 + 540, 30)], 525],
        [[row(60 + 500, 30), row(60 + 482, 15), row(0, 0)], 482],
    ]) {
        appointments.splice(0, appointments.length, ...rows);
        view.scrollTop = 0;
        context.scrollToStartTime();
        assert.equal(view.scrollTop, expected);
    }
});

test('reload keeps shared hours stable and restores both outer and column scroll positions', () => {
    const frames=[];
    const inner={scrollTop:230,scrollLeft:4};
    const root={scrollTop:890,scrollLeft:120,querySelectorAll:()=>[inner]};
    const changes=[];
    const calendars=[0,1].map(id=>({
        paused:false, events:[{allDay:true}], allDay:true,
        pauseRendering(){this.paused=true;},
        resumeRendering(){this.paused=false;root.scrollTop=0;inner.scrollTop=0;},
        getEvents(){return this.events;}, getOption(){return this.allDay;},
        setOption(key,value){changes.push([id,key,value]);this.allDay=value;},
        getEventSources(){return [{remove:()=>{assert.ok(calendars.every(c=>c.paused));this.events=[];context.sync();}}];},
    }));
    const providers=calendars.map(calendar=>({calendar}));
    const column={data:()=>new Date('2026-11-17').getTime(),find(selector){return selector==='.date-column-title'?{text(){}}:{each(fn){providers.forEach((p,i)=>fn(i,p));}};}};
    function $(value){
        if(value==='.calendar-view .date-column')return {each:fn=>fn(0,column)};
        if(value===column)return column;
        return {data:()=>({settings:{working_plan:'{}'}}),find:()=>({data:()=>value.calendar}),calendar:value.calendar};
    }
    const context={
        document:{querySelector:()=>root},requestAnimationFrame:fn=>frames.push(fn),getCalendars:()=>calendars,$,
        vars:()=>'',App:{Utils:{Date:{format:()=>''}}},
        moment:value=>({format:()=>new Date(value).toISOString().slice(0,10),locale:()=>({format:()=> 'Tuesday'}),add:()=>({toDate:()=>new Date(new Date(value).getTime()+86400000)})}),
        createNonWorkingHours(){},createUnavailabilities(){},createBlockedPeriods(){},createBreaks(){},
        createAppointments(column){column.calendar.events=[{allDay:true}];context.sync();},
    };
    vm.runInNewContext(`let replacingEvents=false,lastResponseSignature=null;${sync}${replacement};globalThis.replace=replaceDisplayedEvents;globalThis.sync=syncAllDaySlot;`,context);
    for(let i=0;i<3;i++){
        context.replace({appointments:[],unavailabilities:[],blocked_periods:[]},new Date('2026-11-17'),new Date('2026-11-17'));
        assert.equal(root.scrollTop,890);assert.equal(root.scrollLeft,120);assert.equal(inner.scrollTop,230);
        root.scrollTop=0;inner.scrollTop=0;frames.shift()();
        assert.equal(root.scrollTop,890);assert.equal(inner.scrollTop,230);
    }
    assert.deepEqual(changes,[], 'An all-day row must never disappear temporarily during reload');
    assert.ok(calendars.every(c=>!c.paused));
});
