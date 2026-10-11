import {icon} from './icons.js';
export const app=document.querySelector('#app');
export const S={data:null,csrf:'',configured:false,route:'desktop',selectedOrder:null,cleanup:null,authMode:'login'};
export const e=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const money=(c,short=false)=>c===null||c===undefined?'Пока неизвестно':new Intl.NumberFormat('ru-RU',{style:'currency',currency:'RUB',maximumFractionDigits:short?0:2,minimumFractionDigits:0}).format(c/100);
export const num=v=>new Intl.NumberFormat('ru-RU').format(v??0);
export const date=v=>v?new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short'}).format(new Date(v.length===10?v+'T12:00:00':v.replace(' ','T'))):'—';
export const key=()=>crypto.randomUUID?crypto.randomUUID():Date.now().toString(36)+Math.random().toString(36).slice(2);
export async function api(action,input=null){
 const options=input===null?{credentials:'same-origin'}:{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':S.csrf},credentials:'same-origin',body:JSON.stringify(input)};
 let response;try{response=await fetch(`index.php?api=${encodeURIComponent(action)}`,options);}catch{throw Error('Нет соединения. Ввод сохранён в этом окне; повторите отправку.');}
 let result;try{result=await response.json();}catch{throw Error('Сервер не ответил. Повторите позже.');}
 if(!result.ok)throw Error(result.error||'Не удалось выполнить действие');
 if(result.data.csrf)S.csrf=result.data.csrf;return result.data;
}
export function toast(message,error=false){const el=document.createElement('div');el.className='toast'+(error?' error':'');el.textContent=message;document.querySelector('#toasts').append(el);setTimeout(()=>el.remove(),error?6500:3800);}
export function setData(data){S.data=data;if(data.csrf)S.csrf=data.csrf;if(!S.selectedOrder||!data.orders.some(o=>o.id===S.selectedOrder))S.selectedOrder=data.orders[0]?.id??null;}
export async function refresh(){setData(await api('state'));return S.data;}
export function selected(){return S.data?.orders.find(o=>o.id===S.selectedOrder)||S.data?.orders[0];}
export const badge=(label,type='',ico='')=>`<span class="badge ${type}">${ico?icon(ico):'<i class="dot"></i>'}${e(label)}</span>`;
export const button=(label,ico='',cls='',attrs='')=>`<button class="btn ${cls}" ${attrs}>${ico?icon(ico):''}${e(label)}</button>`;
export function aiNotice(text='Нейросети и импорт Excel — в разработке. Сейчас можно заполнить данные вручную.'){return `<div class="notice ai">${icon('sparkle')}<div><strong>В разработке</strong><br>${e(text)}</div></div>`;}
export function empty(text,action=''){return `<div class="empty">${icon('grid')}<p>${e(text)}</p>${action?`<div style="margin-top:20px">${action}</div>`:''}</div>`;}
export function modal(title,content,{large=false,onClose}={}){
 const previous=document.activeElement;const layer=document.createElement('div');layer.className='modal-layer';layer.innerHTML=`<section class="modal ${large?'large':''}" role="dialog" aria-modal="true" aria-label="${e(title)}"><div class="modal-head"><div class="window-controls"><button data-close aria-label="Закрыть окно"></button><button data-close aria-label="Закрыть окно"></button><button data-close aria-label="Закрыть окно"></button></div><h2>${e(title)}</h2></div>${content}</section>`;document.body.append(layer);
 let closed=false;function close(){if(closed)return;closed=true;layer.remove();document.removeEventListener('keydown',keys);previous?.focus();onClose?.();}
 function keys(ev){if(document.querySelector('.modal-layer:last-of-type')!==layer)return;if(ev.key==='Escape')close();if(ev.key==='Tab'){const f=[...layer.querySelectorAll('button,input,select,textarea,a[href]')].filter(el=>!el.disabled&&el.offsetParent!==null);if(!f.length)return;const first=f[0],last=f.at(-1);if(ev.shiftKey&&document.activeElement===first){last.focus();ev.preventDefault();}else if(!ev.shiftKey&&document.activeElement===last){first.focus();ev.preventDefault();}}}
 layer.querySelectorAll('[data-close]').forEach(b=>b.onclick=close);layer.addEventListener('click',ev=>{if(ev.target===layer)close();});document.addEventListener('keydown',keys);setTimeout(()=>layer.querySelector('input,select,textarea,button')?.focus(),80);return {el:layer,close};
}
export function formData(form){return Object.fromEntries(new FormData(form));}
export async function submit(form,fn){const error=form.querySelector('.error-box');const btn=form.querySelector('[type="submit"]');if(error)error.textContent='';if(btn)btn.disabled=true;try{await fn();}catch(err){if(error)error.textContent=err.message;toast(err.message,true);}finally{if(btn)btn.disabled=false;}}
export function orderPicker(){return `<select id="order-picker" aria-label="Выбрать заказ" style="width:270px;max-width:100%">${S.data.orders.filter(o=>o.status!=='archived').map(o=>`<option value="${e(o.id)}" ${o.id===S.selectedOrder?'selected':''}>${e(o.title)}</option>`).join('')}</select>`;}
export function economic(o){
 if(!S.data?.permissions.economy||!o)return null;
 const x=S.data.economy?.[o.id];if(!x)return null;
 return {labor:x.labor_cents,common:x.common_cents,direct:x.direct_cents,cost:x.cost_cents,unit:x.unit_cents,revenue:x.value_cents,result:x.result_cents,unknown:x.unknown,excluded:x.excluded,seconds:x.norm_seconds};
}
