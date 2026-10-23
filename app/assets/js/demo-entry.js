import {api,setData} from './core.js';
let pending=null;
export async function loadDemo(trigger){
 if(pending)return pending;
 const layer=document.createElement('div');layer.className='modal-layer demo-opening';layer.innerHTML='<section class="modal" role="dialog" aria-modal="true" aria-busy="true" aria-labelledby="demo-opening-title"><div class="modal-body"><div class="loading-ring" aria-hidden="true"></div><h2 id="demo-opening-title">Открываем демофабрику</h2><p class="muted">Подготавливаем отдельное пространство с целями, сотрудниками, планами и фактами производства.</p><p class="hint">Это может занять несколько секунд.</p></div></section>';document.body.append(layer);
 if(trigger)trigger.disabled=true;
 pending=(async()=>{const data=await api('demo_enter',{});setData(data);return data;})();
 try{return await pending;}finally{pending=null;layer.remove();if(trigger?.isConnected)trigger.disabled=false;}
}
