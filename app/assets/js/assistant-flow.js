import {api,button,e,modal} from './core.js';
import {workingScene,runAssistantJob} from './assistant-job.js';

/** One connected animation for the whole request, including rate-limit waits. */
export function jobOverlay({title,start,initial=null,step,onJob=()=>{},onReady=()=>{},onPause=()=>{}}){
 let job=initial,busy=false,paused=false,opened=true,done=false;
 const m=modal(title,workingScene(title,'Помощник EASY3','Запрос принят. Готовые части сохраняются.')+'<div class="assistant-flow-progress"><p role="status" aria-live="polite"></p><progress max="1" value="0" aria-label="Прогресс задания"></progress><p class="error-box" role="alert"></p></div><div class="modal-actions">'+button('Приостановить','pause','','data-job-pause')+button('Продолжить','play','primary','data-job-resume hidden')+'</div><p class="hint">Закрытие окна приостанавливает работу после текущего шага.</p>',{onClose:()=>{opened=false;paused=true;if(!done)onPause(job);}});
 m.el.classList.add('assistant-flow-modal');const q=s=>m.el.querySelector(s),alive=()=>opened&&m.el.isConnected&&!m.el.classList.contains('closing');
 function progress(value,message=''){
  if(!alive())return;job=value;onJob(job);const p=job?.progress||{},total=Math.max(1,Number(p.total||1)),part=Math.min(total,Number(p.part||0));
  q('progress').max=total;q('progress').value=part;q('[role=status]:not(.assistant-working)').textContent=message||'Шаг '+Math.min(total,part+1)+' из '+total+' · готовые части сохранены';
 }
 async function run(){
  if(busy||!alive())return;busy=true;paused=false;q('[data-job-resume]').hidden=true;q('[data-job-pause]').hidden=false;q('[data-job-pause]').disabled=false;q('.error-box').textContent='';m.el.classList.remove('paused');
  try{
   job=job?.id?await api('assistant_status',{id:job.id}):await start();progress(job);
   job=await runAssistantJob(job,{step,alive,paused:()=>paused,onProgress:progress});onJob(job);
   if(!alive())return;
   if(['ready','applied'].includes(job.state)){done=true;m.close();onReady(job.result,job);}
   else{q('[data-job-resume]').hidden=false;q('[data-job-pause]').hidden=true;q('[role=status]:not(.assistant-working)').textContent='Прогресс сохранён. Можно продолжить.';onPause(job);}
  }catch(err){if(alive()){q('.error-box').textContent=err.message;q('[data-job-resume]').hidden=false;q('[data-job-pause]').hidden=true;onPause(job);}}
  finally{busy=false;}
 }
 q('[data-job-pause]').onclick=()=>{paused=true;q('[data-job-pause]').disabled=true;m.el.classList.add('paused');q('[role=status]:not(.assistant-working)').textContent='Сохраняем текущий шаг и приостанавливаем…';};
 q('[data-job-resume]').onclick=run;run();return {...m,pause:()=>paused=true};
}
