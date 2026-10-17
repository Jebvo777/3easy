import {api,e,button} from './core.js';
import {icon} from './icons.js';
export function workingScene(title,subtitle,detail=''){
 return `<div class="assistant-working" role="status" aria-live="polite"><div class="assistant-orbits" aria-hidden="true"><i></i><i></i><i></i><span>${icon('sparkle')}</span></div><p class="eyebrow">${e(subtitle)}</p><h3>${e(title)}</h3><p>${e(detail)}</p></div>`;
}
export async function runAssistantJob(initial,{step,alive,onProgress,paused=()=>false}){
 let job=initial,failures=0;
 const wait=async(seconds,message)=>{const until=Date.now()+Math.min(3600,Math.max(1,seconds))*1000;while(alive()&&!paused()&&Date.now()<until){onProgress(job,message+' · '+Math.ceil((until-Date.now())/1000)+' сек.');await new Promise(r=>setTimeout(r,1000));}};
 while(alive()&&!paused()&&!['ready','applied'].includes(job.state)){
  if(job.retry_after>0){await wait(job.retry_after,'Продолжение после паузы');if(!alive()||paused())break;job.retry_after=0;}
  onProgress(job);try{job=await api(step,{id:job.id});failures=0;}catch(err){if(['rate_limit','busy'].includes(err.code)){await wait(err.retryAfter||15,err.message);continue;}if(err.retryable&&failures++<3){await wait(err.retryAfter||6,err.message);continue;}throw err;}
 }
 return job;
}
