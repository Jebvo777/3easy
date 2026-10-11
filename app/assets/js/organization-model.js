export const structureRoles={owner:'Собственник',chief:'Начальник производства',technologist:'Технолог',foreman:'Бригадир',worker:'Исполнитель',qc:'Контроль качества'};
export function organizationPassport(input){
 const p=structuredClone(input||{}),raw=p.structure||{};
 const workshops=raw.workshops?.length?raw.workshops.map(w=>({...w})):[{id:'w-main',name:'Основной цех'}];
 const defaultWorkshop=workshops[0].id,validWorkshop=id=>workshops.some(w=>w.id===id)?id:defaultWorkshop;
 const brigades=(raw.brigades||[]).map(b=>({...b,workshop:validWorkshop(b.workshop)}));
 p.structure={workshops,brigades};p.people=(p.people||[]).map(person=>({...person,roles:person.roles?.length?[...person.roles]:['worker'],workshop:validWorkshop(person.workshop),brigade:brigades.some(b=>b.id===person.brigade)?person.brigade:''}));
 p.resources=(p.resources||[]).map(r=>({...r,workshop:validWorkshop(r.workshop)}));return p;
}
export function distributeBrigades(input){
 const p=organizationPassport(input),changes={};
 for(const w of p.structure.workshops){const groups=p.structure.brigades.filter(b=>b.workshop===w.id);if(!groups.length)continue;const counts=Object.fromEntries(groups.map(b=>[b.id,p.people.filter(person=>person.brigade===b.id).length]));
  for(const person of p.people.filter(person=>person.active&&person.workshop===w.id&&person.roles.includes('worker')&&!person.brigade&&!groups.some(b=>b.lead===person.id))){const group=[...groups].sort((a,b)=>counts[a.id]-counts[b.id]||a.id.localeCompare(b.id))[0];changes[person.id]=group.id;counts[group.id]++;}
 }
 return changes;
}
export function organizationGraph(company,input){
 const p=organizationPassport(input),nodes=[],links=[],seen=new Set(),workshops=p.structure.workshops,leaders=p.people.filter(person=>person.roles.some(r=>['owner','chief','technologist'].includes(r))),W=210,H=82,G=24;
 const columns=workshops.map(w=>{const brigades=p.structure.brigades.filter(b=>b.workshop===w.id),workers=p.people.filter(person=>person.workshop===w.id&&!leaders.includes(person));return {w,brigades,workers,width:Math.max(300,(Math.max(1,brigades.length)+(workers.some(person=>!person.brigade&&!brigades.some(b=>b.lead===person.id))?1:0))*250)};});
 const width=Math.max(840,columns.reduce((sum,c)=>sum+c.width,0)+G*(columns.length+1),leaders.length*(W+G)+G);
 const add=(node)=>{nodes.push({...node,width:node.width||W,height:node.height||H});return node.id;};
 add({id:'company',kind:'company',name:company,subtitle:'Предприятие',x:(width-260)/2,y:24,width:260});
 leaders.forEach((person,i)=>{add({id:'person:'+person.id,kind:'person',person,name:person.name,subtitle:person.roles.map(r=>structureRoles[r]).join(' · '),x:(width-leaders.length*(W+G)+G)/2+i*(W+G),y:142});seen.add(person.id);links.push(['company','person:'+person.id]);});
 const workY=leaders.length?278:156;let left=G,maxY=workY+H;
 for(const c of columns){const wid='workshop:'+c.w.id,x=left+(c.width-240)/2;add({id:wid,kind:'workshop',workshop:c.w,name:c.w.name,subtitle:`${c.workers.length} участников · ${p.resources.filter(r=>r.workshop===c.w.id).length} центров`,x,y:workY,width:240});
  const chiefs=leaders.filter(person=>person.roles.includes('chief'));if(chiefs.length)chiefs.forEach(person=>links.push(['person:'+person.id,wid]));else links.push(['company',wid]);leaders.filter(person=>person.roles.includes('technologist')).forEach(person=>{if(!chiefs.includes(person))links.push(['person:'+person.id,wid,'technical']);});
  const columnsHere=[...c.brigades.map(b=>({b,people:c.workers.filter(person=>person.brigade===b.id||person.id===b.lead)})),...(c.workers.some(person=>!person.brigade&&!c.brigades.some(b=>b.lead===person.id))?[{b:null,people:c.workers.filter(person=>!person.brigade&&!c.brigades.some(b=>b.lead===person.id))}]:[])];
  columnsHere.forEach((group,i)=>{const gx=left+(c.width-columnsHere.length*250)/2+i*250+20;let parent=wid,y=workY+126;
   if(group.b){parent=add({id:'brigade:'+group.b.id,kind:'brigade',brigade:group.b,name:group.b.name,subtitle:'Бригада',x:gx,y,height:64});links.push([wid,parent]);y+=96;}
   const people=[...group.people].sort((a,b)=>Number(b.id===group.b?.lead)-Number(a.id===group.b?.lead));
   for(const person of people){if(seen.has(person.id))continue;const id=add({id:'person:'+person.id,kind:'person',person,name:person.name,subtitle:person.roles.map(r=>structureRoles[r]).join(' · '),x:gx,y});links.push([parent,id]);if(person.id===group.b?.lead)parent=id;seen.add(person.id);y+=H+24;maxY=Math.max(maxY,y);}
  });left+=c.width+G;
 }
 return {nodes,links,width,height:maxY+30,passport:p};
}
