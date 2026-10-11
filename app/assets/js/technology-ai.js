import {S,e,key,api,modal,button,num,toast,formData} from './core.js';
import {icon} from './icons.js';
const drafts=new Map();
const centers={cut:'Раскрой',sew:'Швейная операция',overlock:'Обмётка',iron:'ВТО',qc:'Контроль',pack:'Упаковка'};
export function generateTechnology(order,{onApply,currentLength=0}={}){
 const cacheKey=S.data.user.id+':'+S.data.company.id+':'+order.id;
 let draft=drafts.get(cacheKey);if(!draft||draft.revision!==order.revision){draft={product:order.product,description:'',max_operations:30,revision:order.revision,result:null,pending:null,request:null};drafts.set(cacheKey,draft);}
 const m=modal('Технология с AI','',{large:true});let connected=true;
 const head=m.el.querySelector('.modal-head');const body=document.createElement('div');body.className='ai-technology-body';head.after(body);
 function isOpen(){return connected&&m.el.isConnected&&!m.el.classList.contains('closing');}
 function capture(){const form=body.querySelector('form');if(form){const x=formData(form);draft.product=x.product;draft.description=x.description;draft.max_operations=Number(x.max_operations);}}
 function inputView(error=''){
  if(!isOpen())return;
  const available=S.data.ai?.configured;
  body.innerHTML=`<p class="ai-intro">Назовите изделие и особенности конструкции. AI предложит до 50 операций; вы сможете изменить каждый шаг.</p><form id="ai-technology-form"><div class="field-grid"><label class="full">Изделие<input name="product" required maxlength="160" value="${e(draft.product)}" placeholder="Худи из футера с капюшоном"></label><label class="full">Материал и конструкция<textarea name="description" maxlength="1400" rows="4" placeholder="Футер 3-нитка, карман-кенгуру, манжеты и пояс из кашкорсе…">${e(draft.description)}</textarea><small class="hint">Только техническое описание, без имён, контактов и заказчика.</small></label><label>Детализация<select name="max_operations">${[[15,'Кратко · до 15 шагов'],[30,'Подробно · до 30'],[50,'Максимум · до 50']].map(([v,t])=>`<option value="${v}" ${draft.max_operations===v?'selected':''}>${t}</option>`).join('')}</select></label><div class="ai-boundary">${icon('shield')}<span>AI создаёт предложение.<br>Нормы проверяете вы.</span></div></div><div class="error-box" role="alert">${e(error)}</div>${available?'':'<p class="hint ai-unavailable">AI пока не подключён. Ручной редактор технологии доступен.</p>'}<div class="modal-actions">${draft.result?button('К предложению','arrow','','id="ai-show-result" type="button"'):''}<button class="btn primary" type="submit" ${available?'':'disabled'}>${icon('sparkle')}Составить последовательность</button></div></form>`;
  body.querySelector('#ai-show-result')?.addEventListener('click',preview);
  body.querySelector('form').onsubmit=async ev=>{ev.preventDefault();capture();await generate();};
 }
 function pendingView(){
  if(!isOpen())return;body.innerHTML=`<div class="ai-working" aria-live="polite"><div class="ai-orbit">${icon('sparkle')}<i></i><i></i></div><h3>Собираем последовательность</h3><p>От деталей — до готового изделия.</p><small>Обычно это занимает несколько десятков секунд.<br>Можно закрыть окно: запрос продолжится.</small></div>`;
 }
 async function generate(){
  if(!draft.pending){
   const signature=JSON.stringify([draft.product,draft.description,draft.max_operations,order.revision]);
   if(!draft.request||draft.request.signature!==signature)draft.request={signature,key:key()};
   const input={key:draft.request.key,order_id:order.id,revision:order.revision,product:draft.product,description:draft.description,max_operations:draft.max_operations};
   draft.pending=api('technology_generate',input).then(result=>{draft.result=result;return result;}).catch(error=>{if(error.status&&error.status!==409)draft.request=null;throw error;}).finally(()=>{draft.pending=null;});
  }
  pendingView();try{await draft.pending;if(isOpen())preview();}catch(error){if(isOpen())inputView(error.message);}
 }
 function preview(){
  if(!isOpen())return;const p=draft.result;if(!p){inputView();return;}
  const resources=new Set(S.data.company.passport.resources.filter(r=>r.active).map(r=>r.type));const missing=[...new Set(p.route.filter(op=>!resources.has(op.type)).map(op=>centers[op.type]))];
  body.innerHTML=`<div class="ai-result-head"><div><p class="eyebrow">Предложение AI</p><h3>${e(p.product)}</h3><p>${p.route.length} операций · нормы требуют проверки</p></div>${button('Уточнить','edit','','id="ai-refine"')}</div>${missing.length?`<p class="ai-resource-warning">Для расчёта потребуется добавить центры: ${e(missing.join(', '))}.</p>`:''}<ol class="ai-steps">${p.route.map((op,i)=>`<li><span class="ai-step-no">${String(i+1).padStart(2,'0')}</span><details><summary><span><strong>${e(op.name)}</strong><small>${e(centers[op.type])}${op.grade?' · разряд '+op.grade:''}</small></span><span class="ai-estimate">${op.estimated_seconds===null?'Уточнить время':'≈ '+num(op.estimated_seconds)+' сек.'}</span>${icon('down')}</summary><p>${e(op.equipment||'Оборудование уточняет технолог')}${op.notes?' · '+e(op.notes):''}</p><small>После: ${op.dependencies.length?op.dependencies.map(id=>e(p.route.find(x=>x.id===id)?.name||id)).join(' + '):'начало потока'}</small></details></li>`).join('')}</ol>${p.assumptions.length?`<details class="ai-clarifications"><summary>Допущения · ${p.assumptions.length}</summary><ul>${p.assumptions.map(t=>`<li>${e(t)}</li>`).join('')}</ul></details>`:''}${p.questions.length?`<details class="ai-clarifications" open><summary>Что уточнить · ${p.questions.length}</summary><ul>${p.questions.map(t=>`<li>${e(t)}</li>`).join('')}</ul></details>`:''}<p class="hint ai-review-note">Оценки AI отделены от нормативов. При вставке можно редактировать шаги, а нормы заполнить после проверки.${currentLength?' Текущий локальный маршрут будет заменён этим черновиком.':''}</p><div class="modal-actions">${button('Вставить черновик','check','primary','id="ai-apply"')}</div>`;
  body.querySelector('#ai-refine').onclick=()=>inputView();
  body.querySelector('#ai-apply').onclick=()=>{const current=S.data.orders.find(o=>o.id===order.id);if(S.selectedOrder!==order.id||!current||current.revision!==p.order_revision){toast('Цель изменилась. Откройте актуальную технологию и повторите генерацию.',true);return;}onApply?.(structuredClone(p.route));m.close();connected=false;toast('Черновик вставлен. Проверьте нормы перед расчётом.');};
 }
 if(draft.pending)generate();else if(draft.result)preview();else inputView();return m;
}
export function reviewAiNorms(route,{onApply}={}){
 const list=route.filter(op=>op.ai_origin);if(!list.length)return;
 const m=modal('Проверка норм',{toString:()=>''},{large:true});
 const body=document.createElement('div');m.el.querySelector('.modal-head').after(body);
 body.innerHTML=`<p class="ai-intro">Проверьте трудоёмкость, подготовку и оборудование для своего изделия. Оценки AI можно изменить; пустое время останется неизвестным.</p><form><div class="ai-norm-list">${list.map((op,i)=>`<div class="ai-norm-row"><div><span>${String(i+1).padStart(2,'0')}</span><strong>${e(op.name)}</strong><small>${e(op.equipment||centers[op.type])}</small></div><label>Сек. / шт.<input name="seconds-${e(op.id)}" type="number" min="1" max="36000" step="1" value="${op.seconds??op.estimated_seconds??''}" placeholder="Неизвестно"></label><label>Подготовка, мин.<input name="setup-${e(op.id)}" type="number" min="0" max="480" step="1" required value="${op.setup_minutes}"></label></div>`).join('')}</div><label class="ai-norm-source">Основание<input name="source" maxlength="300" required value="Оценка AI, проверена пользователем для данного изделия"></label><label class="ai-confirm"><input name="reviewed" type="checkbox" required><span>Проверены заполненные нормы, подготовка и оборудование.</span></label><div class="error-box" role="alert"></div><div class="modal-actions"><button class="btn primary" type="submit">${icon('check')}Применить проверенные данные</button></div></form>`;
 body.querySelector('form').onsubmit=ev=>{ev.preventDefault();const f=ev.currentTarget,x=formData(f);const result=route.map(op=>{if(!op.ai_origin)return op;const seconds=x['seconds-'+op.id];return {...op,seconds:seconds===''?null:Number(seconds),setup_minutes:Number(x['setup-'+op.id]),source:x.source,norm_reviewed:seconds!==''};});onApply?.(result);m.close();};return m;
}
