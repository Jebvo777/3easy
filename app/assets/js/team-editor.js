import {S,e,key,modal,button,formData,toast} from './core.js';
import {icon} from './icons.js';
import {organizationPassport,structureRoles,personWorkshopIds} from './organization-model.js';
import {mountOrganization} from './organization.js';
export const dutyNames={sew:'Швея',qc:'ОТК',cut:'Раскрой',pack:'Упаковка',iron:'ВТО',hand:'Ручная работа'};
const groupNames={owner:'Владелец',management:'Руководство',executors:'Исполнители'};
export function mountTeam(host,{company,passport,editable=false,onChange=()=>{}}){
 let disposeMap=null,disposed=false;
 const changed=()=>{onChange();draw();};
 const departmentNames=person=>passport.structure.workshops.filter(w=>personWorkshopIds(person).includes(w.id)).map(w=>w.name).join(' · ');
 function draw(){
  disposeMap?.();if(disposed)return;Object.assign(passport,organizationPassport(passport));
  host.innerHTML=`<div class="team-toolbar"><div><strong>${passport.people.length} участников</strong><span>${passport.structure.workshops.length} подразделений · ${passport.structure.brigades.length} бригад</span></div><div class="row wrap">${Object.entries(groupNames).map(([id,name])=>button(name,'plus','small',`data-team-group="${id}" ${editable?'':'disabled'}`)).join('')}</div></div><div class="team-space"><aside class="team-roster">${Object.entries(groupNames).map(([group,title])=>`<p class="eyebrow">${title}</p>${passport.people.filter(p=>p.group===group).map(p=>`<button data-team-person="${e(p.id)}" class="team-roster-person"><span class="person-avatar">${e(p.name.split(' ').map(x=>x[0]).join('').slice(0,2))}</span><span><strong>${e(p.name)}</strong><small>${e(group==='executors'?(p.duties||[]).map(d=>dutyNames[d]).join(' · ')||'Универсальный исполнитель':p.roles.map(r=>structureRoles[r]).join(' · '))}</small>${group==='management'?`<small class="team-person-departments">${e(departmentNames(p))}</small>`:''}</span></button>`).join('')}`).join('')}</aside><div class="team-map-host"></div></div>`;
  disposeMap=mountOrganization(host.querySelector('.team-map-host'),{company,passport,onPerson:p=>personForm(p.group,p.id),onWorkshop:()=>window.dispatchEvent(new CustomEvent('easy3:navigate',{detail:{route:'passport',section:'resources'}})),onBrigade:b=>{const p=passport.people.find(x=>x.id===b.lead);if(p)personForm('management',p.id);}});
  host.querySelectorAll('[data-team-group]').forEach(b=>b.onclick=()=>personForm(b.dataset.teamGroup));
  host.querySelectorAll('[data-team-person]').forEach(b=>b.onclick=()=>{const p=passport.people.find(x=>x.id===b.dataset.teamPerson);personForm(p.group,p.id);});
 }
 function personForm(group,id=null){
  if(!passport.structure.workshops.length&&group!=='owner'){toast('Сначала создайте подразделение в ресурсах',true);return;}
  const first=passport.structure.workshops[0]?.id||'';
  const old=passport.people.find(p=>p.id===id),p=old||{id:'p-'+key(),name:'',group,active:true,roles:group==='owner'?['owner']:group==='management'?['foreman']:['worker'],duties:[],universal:true,workshop:first,workshops:group==='owner'?[]:[first],brigade:''};
  const access=p.access||S.data.personAccounts?.find(a=>a.person_id===p.id)||{},disabled=editable?'':'disabled';
  const choices=group==='management'?{chief:'Начальник производства',technologist:'Технолог',foreman:'Бригадир'}:dutyNames;
  const departments=group==='management'?`<fieldset class="team-department-field"><legend>Подразделения</legend><div class="row spread"><p class="hint">Можно выбрать несколько одновременно.</p>${button('Выбрать все','','small',`type="button" data-select-all-departments ${disabled}`)}</div><div class="team-department-picker">${passport.structure.workshops.map(w=>`<label><input type="checkbox" name="department" value="${e(w.id)}" ${personWorkshopIds(p).includes(w.id)?'checked':''} ${disabled}><span>${e(w.name)}</span></label>`).join('')}</div></fieldset>`:`<label>Подразделение<select name="workshop" ${disabled}>${passport.structure.workshops.map(w=>`<option value="${e(w.id)}" ${p.workshop===w.id?'selected':''}>${e(w.name)}</option>`).join('')}</select></label>`;
  const m=modal(groupNames[group],`<form class="team-person-form"><div class="field-grid"><label class="full">Имя или рабочее имя<input name="name" required maxlength="100" value="${e(p.name)}" ${disabled}></label><label>Доступность<select name="active" ${disabled}><option value="1" ${p.active?'selected':''}>Доступен</option><option value="0" ${!p.active?'selected':''}>Не в смене</option></select></label></div>${group==='owner'?'':`<label class="team-field-title">${group==='management'?'Роль':'Должности'}</label><div class="team-role-picker">${Object.entries(choices).map(([v,name])=>`<label><input name="choice" type="checkbox" value="${v}" ${(group==='management'?p.roles:p.duties||[]).includes(v)?'checked':''} ${disabled}><span>${name}</span></label>`).join('')}</div><div class="field-grid" style="margin-top:18px">${departments}<label data-brigade-label>Бригада<select name="brigade" ${disabled}></select></label><label class="full" data-new-brigade hidden>Название новой бригады<input name="brigade_name" maxlength="100" placeholder="Например, Бригада № 2" ${disabled}></label><label data-new-brigade-department hidden>Подразделение новой бригады<select name="brigade_workshop" ${disabled}></select></label></div>${group==='executors'?'<p class="hint" style="margin-top:16px">Без выбранных должностей — универсальный исполнитель. Перенос в другое подразделение изменит будущие назначения.</p>':''}`}
   <details class="team-access" ${group==='owner'?'open':''}><summary>Доступ в систему</summary><div class="field-grid"><label>Почта / логин<input name="email" type="email" maxlength="190" autocomplete="off" value="${e(access.email||'')}" ${disabled}></label><label>${access.email?'Новый пароль (необязательно)':'Пароль'}<input name="password" type="password" minlength="10" maxlength="128" autocomplete="new-password" value="${e(access.password||'')}" ${disabled}></label></div><p class="hint">${group==='owner'?'У каждого владельца свой пароль и полный доступ к компании.':'Отдельный аккаунт с правами выбранной роли; почту можно заполнить позже.'}</p></details><div class="error-box" role="alert"></div>${editable?`<div class="modal-actions">${old?button('Удалить','trash','danger','type="button" data-remove-person'):''}<button class="btn primary" type="submit">${old?'Применить':'Добавить'}${icon('check')}</button></div>`:''}</form>`);
  const form=m.el.querySelector('form'),workshop=form.elements.workshop,brigade=form.elements.brigade;
  const selectedDepartments=()=>group==='management'?[...form.querySelectorAll('[name="department"]:checked')].map(x=>x.value):workshop?[workshop.value]:[];
  function newBrigadeFields(){
   if(!brigade)return;const creating=brigade.value==='__new';form.querySelector('[data-new-brigade]').hidden=!creating;form.querySelector('[data-new-brigade-department]').hidden=!creating||selectedDepartments().length<=1;
  }
  function groups(){
   if(!brigade)return;
   const ids=selectedDepartments(),foreman=group==='management'&&form.querySelector('[name="choice"][value="foreman"]').checked,selected=brigade.value||p.brigade;
   const name=wid=>passport.structure.workshops.find(w=>w.id===wid)?.name||'';
   brigade.innerHTML=`<option value="">Без бригады</option>${passport.structure.brigades.filter(b=>ids.includes(b.workshop)&&(group!=='management'||b.lead===p.id)).map(b=>`<option value="${e(b.id)}" ${selected===b.id?'selected':''}>${e(b.name)}${group==='management'?' · '+e(name(b.workshop)):''}</option>`).join('')}${foreman&&ids.length?`<option value="__new" ${selected==='__new'?'selected':''}>+ Создать бригаду</option>`:''}`;
   if(group==='management'&&!foreman)brigade.value='';
   form.querySelector('[data-brigade-label]').hidden=group==='management'&&!foreman;
   const dep=form.elements.brigade_workshop,preferred=dep.value||p.workshop;dep.innerHTML=passport.structure.workshops.filter(w=>ids.includes(w.id)).map(w=>`<option value="${e(w.id)}" ${w.id===preferred?'selected':''}>${e(w.name)}</option>`).join('');
   newBrigadeFields();
  }
  if(brigade){groups();brigade.onchange=newBrigadeFields;form.querySelectorAll('[name="choice"],[name="department"]').forEach(x=>x.onchange=groups);if(workshop)workshop.onchange=()=>{brigade.value='';groups();};}
  form.querySelector('[data-select-all-departments]')?.addEventListener('click',()=>{form.querySelectorAll('[name="department"]').forEach(x=>x.checked=true);groups();});
  form.onsubmit=ev=>{ev.preventDefault();if(!editable)return;try{
   const data=formData(form),selected=[...form.querySelectorAll('[name="choice"]:checked')].map(x=>x.value),ids=selectedDepartments();
   if(group==='management'&&!selected.length)throw Error('Выберите роль руководителя');if(group!=='owner'&&!ids.length)throw Error('Выберите хотя бы одно подразделение');
   const roles=group==='owner'?['owner']:group==='management'?selected:['worker',...(selected.includes('qc')?['qc']:[])];
   const primary=ids.includes(p.workshop)?p.workshop:ids[0]||'';
   const value={...p,group,name:data.name.trim(),roles,role:group==='executors'?selected.map(x=>dutyNames[x]).join(', '):roles.map(x=>structureRoles[x]).join(', '),duties:group==='executors'?selected:[],universal:group==='executors'&&selected.length===0,skills:[],specializations:[],active:data.active==='1',workshop:group==='owner'?'':primary,workshops:group==='owner'?[]:ids,brigade:group==='owner'||(group==='management'&&!roles.includes('foreman'))?'':data.brigade||''};
   if(!value.name)throw Error('Укажите имя');if(data.email)value.access={email:data.email.trim(),password:data.password};else if(group==='owner'&&!old)throw Error('Для нового владельца укажите почту и пароль');
   const led=passport.structure.brigades.filter(b=>b.lead===p.id);if(led.some(b=>!roles.includes('foreman')||!ids.includes(b.workshop)||value.brigade!==b.id))throw Error('Сначала переназначьте существующую бригаду этого бригадира. Её подразделение должно оставаться выбранным.');
   if(value.brigade==='__new'){
    const name=data.brigade_name?.trim(),wid=data.brigade_workshop||primary;if(!name)throw Error('Назовите новую бригаду');if(!ids.includes(wid))throw Error('Выберите подразделение новой бригады');if(passport.structure.brigades.some(b=>b.workshop===wid&&b.name.toLowerCase()===name.toLowerCase()))throw Error('Такая бригада уже есть');const b={id:'b-'+key(),name,workshop:wid,lead:p.id};passport.structure.brigades.push(b);value.brigade=b.id;
   }
   if(old&&group==='executors'&&old.workshop!==value.workshop)for(const r of passport.resources)if(r.worker===old.id&&r.workshop!==value.workshop)r.worker='';
   if(old)Object.assign(old,value);else passport.people.push(value);m.close();changed();
  }catch(err){form.querySelector('.error-box').textContent=err.message;}};
  form.querySelector('[data-remove-person]')?.addEventListener('click',()=>{if(passport.resources.some(r=>r.worker===p.id)||passport.structure.brigades.some(b=>b.lead===p.id)||access.email){form.querySelector('.error-box').textContent='Участник используется. Переназначьте его центры/бригаду или отметьте как недоступного.';return;}passport.people=passport.people.filter(x=>x.id!==p.id);m.close();changed();});
 }
 draw();return()=>{disposed=true;disposeMap?.();};
}
