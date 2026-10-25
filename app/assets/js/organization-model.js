export const structureRoles={owner:'Владелец',chief:'Начальник производства',technologist:'Технолог',foreman:'Бригадир',worker:'Исполнитель',qc:'Контроль качества'};
export function organizationPassport(input){
 const p=structuredClone(input||{}),raw=p.structure||{};
 const workshops=raw.workshops?.length?raw.workshops.map(w=>({...w,kind:w.kind||'assembly'})):[{id:'w-main',name:'Швейный цех',kind:'assembly'}];
 const defaultWorkshop=workshops[0].id,validWorkshop=id=>workshops.some(w=>w.id===id)?id:defaultWorkshop;
 const brigades=(raw.brigades||[]).map(b=>({...b,workshop:validWorkshop(b.workshop)}));
 p.structure={workshops,brigades};p.people=(p.people||[]).map(person=>({...person,roles:person.roles?.length?[...person.roles]:['worker'],workshop:validWorkshop(person.workshop),brigade:brigades.some(b=>b.id===person.brigade)?person.brigade:''}));
 p.people=p.people.map(x=>({...x,group:x.group||(x.roles.includes('owner')?'owner':x.roles.some(r=>['chief','technologist','foreman'].includes(r))?'management':'executors'),duties:x.duties||[...(x.skills||[]).filter(v=>['cut','sew','qc','pack','iron','hand'].includes(v)),...((x.skills||[]).includes('special')?['sew']:[])],universal:x.universal??true}));
 p.workplaces=p.workplaces||((p.resources||[]).map(r=>({id:'wp-'+r.id,name:r.name,workshop:r.workshop||defaultWorkshop,equipment:[r.id]})));
 p.workplaces=p.workplaces.map(w=>({active:true,...w}));
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
 const p=organizationPassport(input),nodes=[],links=[],W=210,H=74,G=22;
 const owners=p.people.filter(x=>x.group==='owner'),chiefs=p.people.filter(x=>x.group!=='owner'&&x.roles.includes('chief')),technologists=p.people.filter(x=>x.group!=='owner'&&x.roles.includes('technologist')&&!chiefs.includes(x));
 const heads=[...chiefs,...technologists],columns=p.structure.workshops.map(w=>({w,people:p.people.filter(x=>x.group!=='owner'&&!heads.includes(x)&&x.workshop===w.id)}));
 for(const col of columns){col.groups=p.structure.brigades.filter(b=>b.workshop===col.w.id).map(b=>({b,lead:col.people.find(x=>x.id===b.lead),people:col.people.filter(x=>x.brigade===b.id&&x.id!==b.lead)}));const free=col.people.filter(x=>!col.groups.some(g=>g.lead===x||g.people.includes(x)));if(free.length)col.groups.push({b:null,lead:null,people:free});col.width=Math.max(300,col.groups.reduce((sum,g)=>sum+Math.max(250,Math.min(3,Math.max(1,g.people.length))*(W+G)),0));}
 const width=Math.max(840,columns.reduce((sum,c)=>sum+c.width+G,0)+G,Math.max(chiefs.length,technologists.length)*(W+G)+G);let maxY=560;
 const add=n=>{nodes.push({width:W,height:H,...n});return n.id;},person=(x,px,y,extra={})=>add({id:'person:'+x.id,kind:'person',person:x,name:x.name,subtitle:x.group==='executors'?(x.role||'Универсальный исполнитель'):x.roles.map(r=>structureRoles[r]).join(' · '),x:px,y,...extra});
 add({id:'company',kind:'company',name:company,subtitle:owners.length?'Владельцы: '+owners.map(x=>x.name).join(', '):'Компания',x:(width-330)/2,y:18,width:330});
 chiefs.forEach((x,i)=>{person(x,(width-chiefs.length*(W+G)+G)/2+i*(W+G),130);links.push(['company','person:'+x.id]);});
 technologists.forEach((x,i)=>{person(x,(width-technologists.length*(W+G)+G)/2+i*(W+G),246);chiefs.forEach(c=>links.push(['person:'+c.id,'person:'+x.id]));});
 let left=G;for(const c of columns){add({id:'workshop:'+c.w.id,kind:'workshop',workshop:c.w,name:c.w.name,subtitle:c.people.length+' участников',x:left+(c.width-260)/2,y:362,width:260});let gx=left;
  for(const g of c.groups){const gw=Math.max(250,Math.min(3,Math.max(1,g.people.length))*(W+G));let parent=null;
   if(g.lead){parent=person(g.lead,gx+(gw-250)/2,466,{kind:'brigade',brigade:g.b,name:g.b.name,subtitle:'Бригадир · '+g.lead.name,width:250});for(const tech of technologists.length?technologists:chiefs.filter(x=>x.roles.includes('technologist')))links.push(['person:'+tech.id,parent]);}
   const count=Math.min(3,Math.max(1,g.people.length));g.people.forEach((x,i)=>{const y=580+Math.floor(i/count)*(H+G),id=person(x,gx+(gw-count*(W+G))/2+(i%count)*(W+G),y);if(parent)links.push([parent,id]);maxY=Math.max(maxY,y+H+30);});gx+=gw;
  }left+=c.width+G;
 }
 return {nodes,links,width,height:maxY,passport:p};
}
