export const stamp=v=>new Date(v.replace(' ','T')).getTime();
export function calendarBounds(schedule){
 const starts=schedule.map(t=>stamp(t.start)).filter(Number.isFinite),ends=schedule.map(t=>stamp(t.end)).filter(Number.isFinite);
 const first=new Date(starts.length?Math.min(...starts):Date.now());first.setHours(0,0,0,0);
 const last=new Date(ends.length?Math.max(...ends):first);last.setHours(0,0,0,0);last.setDate(last.getDate()+1);
 return {start:first.getTime(),end:last.getTime(),days:Math.max(1,Math.ceil((last-first)/86400000))};
}
export function calendarSegments(task,start,days){
 const end=start+days*86400000,intervals=task.slots?.length?task.slots:[[task.start,task.end]];
 return intervals.map(([a,b])=>[Math.max(start,stamp(a)),Math.min(end,stamp(b))]).filter(([a,b])=>b>a).map(([a,b])=>({left:(a-start)/(end-start)*100,width:(b-a)/(end-start)*100,start:a,end:b}));
}

export function calendarDate(value){const d=new Date(value);return [d.getFullYear(),String(d.getMonth()+1).padStart(2,'0'),String(d.getDate()).padStart(2,'0')].join('-');}

// One operation remains one object. Inner intervals show actual work; the envelope includes pauses.
export function calendarOperation(task,start,days){
 const segments=calendarSegments(task,start,days);if(!segments.length)return null;
 const left=Math.min(...segments.map(s=>s.left)),right=Math.max(...segments.map(s=>s.left+s.width)),width=right-left;
 return {left,width,segments:segments.map(s=>({left:(s.left-left)/width*100,width:s.width/width*100}))};
}
export function calendarLanes(tasks,start,days){
 const ends=[],items=[];
 for(const task of [...tasks].sort((a,b)=>stamp(a.start)-stamp(b.start))){const bar=calendarOperation(task,start,days);if(!bar)continue;let lane=ends.findIndex(end=>end<=bar.left+.000001);if(lane<0)lane=ends.length;ends[lane]=bar.left+bar.width;items.push({task,bar,lane});}
 return {items,lanes:Math.max(1,ends.length)};
}
