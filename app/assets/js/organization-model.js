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
 const p=organizationPassport(input),nodes=[],links=[],seen=new Set(),W=210,H=74,G=18;
 const leaders=p.people.filter(person=>person.roles.some(r=>['owner','chief','technologist'].includes(r)));
 const columns=p.structure.workshops.map(w=>{
  const members=p.people.filter(person=>person.workshop===w.id&&!leaders.includes(person));
  const groups=p.structure.brigades.filter(b=>b.workshop===w.id).map(b=>({brigade:b,lead:members.find(person=>person.id===b.lead),people:members.filter(person=>person.brigade===b.id&&person.id!==b.lead)}));
  const free=members.filter(person=>!groups.some(group=>group.lead?.id===person.id||group.people.includes(person)));if(free.length)groups.push({brigade:null,lead:null,people:free});
  groups.forEach(group=>group.width=Math.max(250,Math.min(3,Math.max(1,group.people.length))*(W+G)-G+30));
  return {w,members,groups,width:Math.max(280,groups.reduce((sum,group)=>sum+group.width,0)+G*Math.max(0,groups.length-1))};
 });
 const width=Math.max(840,columns.reduce((sum,c)=>sum+c.width,0)+G*(columns.length+1),leaders.length*(W+G)+G);
 const add=node=>{nodes.push({...node,width:node.width||W,height:node.height||H});return node.id;};
 const personNode=(person,x,y,extra={})=>{seen.add(person.id);return add({id:'person:'+person.id,kind:'person',person,name:person.name,subtitle:person.roles.map(r=>structureRoles[r]).join(' · '),x,y,...extra});};
 add({id:'company',kind:'company',name:company,subtitle:'Предприятие',x:(width-260)/2,y:18,width:260});
 leaders.forEach((person,i)=>{const id=personNode(person,(width-leaders.length*(W+G)+G)/2+i*(W+G),122);links.push(['company',id]);});
 const workY=leaders.length?234:122;let left=G,maxY=workY+H+24;
 for(const c of columns){const wid=add({id:'workshop:'+c.w.id,kind:'workshop',workshop:c.w,name:c.w.name,subtitle:`${c.members.length} участников · ${p.resources.filter(r=>r.workshop===c.w.id).length} центров`,x:left+(c.width-250)/2,y:workY,width:250});
  const chiefs=leaders.filter(person=>person.roles.includes('chief'));if(chiefs.length)chiefs.forEach(person=>links.push(['person:'+person.id,wid]));else links.push(['company',wid]);leaders.filter(person=>person.roles.includes('technologist')&&!chiefs.includes(person)).forEach(person=>links.push(['person:'+person.id,wid,'technical']));
  let gx=left;for(const group of c.groups){let parent=wid,y=workY+110;
   if(group.brigade){if(group.lead&&!seen.has(group.lead.id))parent=personNode(group.lead,gx+(group.width-250)/2,y,{kind:'brigade',brigade:group.brigade,name:group.brigade.name,subtitle:'Бригадир · '+group.lead.name,width:250});else parent=add({id:'brigade:'+group.brigade.id,kind:'brigade',brigade:group.brigade,name:group.brigade.name,subtitle:'Бригада',x:gx+(group.width-250)/2,y,width:250});links.push([wid,parent]);y+=110;}
   const count=Math.min(3,Math.max(1,group.people.length)),rowWidth=count*(W+G)-G;
   group.people.forEach((person,i)=>{if(seen.has(person.id))return;const id=personNode(person,gx+(group.width-rowWidth)/2+(i%count)*(W+G),y+Math.floor(i/count)*(H+G));links.push([parent,id]);maxY=Math.max(maxY,y+Math.floor(i/count)*(H+G)+H+24);});gx+=group.width+G;
  }left+=c.width+G;
 }
 return {nodes,links,width,height:maxY,passport:p};
}
