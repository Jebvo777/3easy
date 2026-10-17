import {S,e,button,modal} from './core.js';
import {icon} from './icons.js';
import {organizationPassport,structureRoles} from './organization-model.js';
import {mountOrganization} from './organization.js';
import {render as renderWorkshop} from './workshop.js';
let selectedWorkshop=null;
export async function render(main){
 let cleanup=null,disposed=false,ticket=0;
 main.classList.add('enterprise-page');
 const passport=()=>organizationPassport(S.data.company.passport);
 async function openWorkshop(id){const t=++ticket;cleanup?.();cleanup=null;selectedWorkshop=id;main.classList.remove('enterprise-overview');if(disposed)return;const dispose=await renderWorkshop(main,{workshopId:id,onBack:overview});if(t!==ticket||disposed){dispose?.();return;}cleanup=dispose;}
 function overview(){++ticket;cleanup?.();cleanup=null;if(disposed)return;selectedWorkshop=null;const p=passport();main.classList.add('enterprise-overview');
  main.innerHTML=`<div class="page-heading"><div><p class="eyebrow">Сначала — люди. Затем — пространство.</p><h2>${e(S.data.company.name)}</h2><p>${p.people.length} участников · ${p.structure.workshops.length} цеха · ${p.structure.brigades.length} бригады</p></div><div class="page-actions">${button('Изменить состав','people','','id="enterprise-team"')}${button('План производства','plan','','id="enterprise-plan"')}${button('Оптимизация ?','sparkle','','id="enterprise-optimization"')}</div></div><div class="enterprise-workshops"><span class="eyebrow">Открыть цех</span>${p.structure.workshops.map(w=>button(w.name,'builder','',`data-open-workshop="${e(w.id)}"`)).join('')}</div><div class="enterprise-map"></div><p class="enterprise-hint">Выберите цех — откроется его схема оборудования.</p>`;
  cleanup=mountOrganization(main.querySelector('.enterprise-map'),{company:S.data.company.name,passport:p,onWorkshop:w=>openWorkshop(w.id),onPerson:person=>modal(person.name,`<p class="muted">${e(person.roles.map(r=>structureRoles[r]).join(' · '))}</p><div class="divider"></div><p class="hint">${e(p.structure.workshops.find(w=>w.id===person.workshop)?.name||'')} ${person.brigade?' · '+e(p.structure.brigades.find(b=>b.id===person.brigade)?.name||''):''}</p><div class="modal-actions">${button('Открыть цех','builder','primary',`data-person-workshop="${e(person.workshop)}"`)}</div>`,{onClose(){}}),onBrigade:b=>openWorkshop(b.workshop)});
  main.querySelectorAll('[data-open-workshop]').forEach(b=>b.onclick=()=>openWorkshop(b.dataset.openWorkshop));main.querySelector('#enterprise-team').onclick=()=>go('passport',{section:'people'});main.querySelector('#enterprise-plan').onclick=()=>go('plan');main.querySelector('#enterprise-optimization').onclick=()=>go('optimization');
  // The card dialog can also enter a workshop without changing structural data.
  const click=ev=>{const b=ev.target.closest('[data-person-workshop]');if(!b)return;b.closest('.modal-layer')?.querySelector('[data-close]')?.click();openWorkshop(b.dataset.personWorkshop);};document.addEventListener('click',click);const disposeMap=cleanup;cleanup=()=>{disposeMap?.();document.removeEventListener('click',click);};
 }
 if(selectedWorkshop&&passport().structure.workshops.some(w=>w.id===selectedWorkshop))await openWorkshop(selectedWorkshop);else overview();
 return()=>{disposed=true;++ticket;cleanup?.();main.classList.remove('enterprise-page','enterprise-overview');};
}
function go(route,detail={}){window.dispatchEvent(new CustomEvent('easy3:navigate',{detail:{route,...detail}}));}
