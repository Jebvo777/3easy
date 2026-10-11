export const centreSizes={cut:[2.8,1.6],sew:[1.5,1],overlock:[1.5,1],iron:[1.8,1],qc:[1.8,1],pack:[1.8,1],storage:[1.7,.8]};
export function clampCentre(layout,object,x,z){let [w,d]=centreSizes[object.type];if(object.rotation%180)[w,d]=[d,w];return {x:Math.max(-layout.width/2+w/2+.1,Math.min(layout.width/2-w/2-.1,Math.round(x*2)/2)),z:Math.max(-layout.depth/2+d/2+.1,Math.min(layout.depth/2-d/2-.1,Math.round(z*2)/2))};}
export function distributePeople(resources,people){
 const active=people.filter(p=>p.active),loads=new Map(active.map(p=>[p.id,0])),result={};
 const compatible=r=>active.filter(p=>p.skills.includes(r.type));
 for(const r of resources.filter(r=>r.active).slice().sort((a,b)=>compatible(a).length-compatible(b).length||a.id.localeCompare(b.id))){
  const choices=compatible(r).sort((a,b)=>(loads.get(a.id)-loads.get(b.id))||(a.id===r.worker?-1:b.id===r.worker?1:0)||a.id.localeCompare(b.id));
  const p=choices[0];result[r.id]=p?.id||'';if(p)loads.set(p.id,loads.get(p.id)+1);
 }
 return result;
}
export function arrangeFactory(layout){
 const types=['cut','sew','overlock','iron','qc','pack','storage'],objects=layout.objects.slice().sort((a,b)=>types.indexOf(a.type)-types.indexOf(b.type)||a.id.localeCompare(b.id));
 const columns=Math.min(6,Math.max(2,Math.ceil(Math.sqrt(objects.length*1.5)))),rows=Math.ceil(objects.length/columns);
 layout.width=Math.max(layout.width,columns*3.4+1.2);layout.depth=Math.max(layout.depth,rows*2.5+1.8);
 objects.forEach((o,i)=>{o.rotation=0;o.x=Math.round(((i%columns-(columns-1)/2)*3.4)*2)/2;o.z=Math.round((Math.floor(i/columns)-(rows-1)/2)*2.5*2)/2;});
 return layout;
}
export function connectFromRoutes(layout,routes){
 const links=new Map();let missing=0;
 for(const route of routes){const ops=new Map(route.map(op=>[op.id,op]));for(const op of route){const targets=layout.objects.filter(o=>o.type===op.type);if(!targets.length)missing++;for(const dependency of op.dependencies||[]){const predecessor=ops.get(dependency);if(!predecessor)continue;const sources=layout.objects.filter(o=>o.type===predecessor.type);for(const a of sources)for(const b of targets)if(a.id!==b.id)links.set(a.id+'>'+b.id,[a.id,b.id]);}}}
 return {links:[...links.values()],missing};
}
