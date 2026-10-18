import {Window} from 'happy-dom';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import {pathToFileURL,fileURLToPath} from 'node:url';

const win=new Window({url:'http://localhost:8099/'});
win.happyDOM.setWindowSize({width:1440,height:960});
win.document.body.innerHTML='<div id="app"><main id="view"></main></div><div id="toasts"></div>';
for(const k of ['window','document','location','navigator','HTMLElement','HTMLCanvasElement','CustomEvent','Event','KeyboardEvent','MouseEvent','PointerEvent','FormData','File','Blob'])Object.defineProperty(globalThis,k,{value:k==='window'?win:win[k],configurable:true});
globalThis.matchMedia=()=>({matches:true,addEventListener(){},removeEventListener(){}});
globalThis.requestAnimationFrame=()=>0;globalThis.cancelAnimationFrame=()=>{};
globalThis.ResizeObserver=class{observe(){}disconnect(){}};globalThis.IntersectionObserver=class{observe(){}disconnect(){}};
globalThis.devicePixelRatio=1;win.HTMLCanvasElement.prototype.getContext=()=>null;
const app=fileURLToPath(new URL('../../',import.meta.url)),out=fileURLToPath(new URL('./output/v61/',import.meta.url)),js=pathToFileURL(app+'assets/js/');
const read=n=>JSON.parse(fs.readFileSync(out+n+'.json')),state=read('state'),intent=read('intent'),shape=read('illustration'),aiResult=read('ai-result');
const core=await import(new URL('core.js',js));core.setData(state);
// Happy DOM requires whitespace after @media; browsers accept the original compact CSS.
const style=document.createElement('style');style.textContent=fs.readFileSync(app+JSON.parse(fs.readFileSync(app+'assets/manifest.json')).css,'utf8').replace(/@media\(/g,'@media (');document.head.append(style);
const main=document.querySelector('#view'),q=s=>document.querySelector(s),qa=s=>[...document.querySelectorAll(s)],tick=(ms=25)=>new Promise(r=>setTimeout(r,ms));
const response=data=>data?.apiFailure?{ok:false,status:422,headers:{get:()=>null},json:async()=>({ok:false,error:data.apiFailure})}:{ok:true,status:200,headers:{get:()=>null},json:async()=>({ok:true,data})};
let calls=[],pending={},cleanup,techJob={generation_id:'tech-v62',order_revision:1,state:'pending',stage:'operations',groups_done:0,groups_total:2,operations:0,illustration:shape,illustration_state:'ready',retry_after:0};
globalThis.fetch=async(url,options={})=>{
 const action=new URL(url,'http://x').searchParams.get('api'),input=options.body&&JSON.parse(options.body);calls.push({action,input});
 if(['simulation_intent','simulation_ai_start','simulation_ai_step','assistant_status','technology_step'].includes(action))return new Promise(resolve=>pending[action]=data=>resolve(response(data)));
 if(action==='technology_generate'||action==='technology_status')return response(techJob);
 if(action==='technology_jobs')return response({jobs:[]});return response({});
};
const click=s=>{assert(q(s),'Missing '+s);q(s).click();},submit=()=>q('#simulation-ai-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
const job=(part=0)=>({id:'ai-v62',state:'pending',kind:'simulation',progress:{part,total:6},retry_after:0});
async function start(){submit();pending.simulation_intent(intent);await tick();submit();}
let checks=[],errors=[];async function test(label,fn){try{await fn();checks.push(label);}catch(error){errors.push({label,error:error.stack});}}

await test('Home gives reserved navigation space back to the 3D model',async()=>{
 main.className='desktop-persistent';const original=console.error;console.error=()=>{};try{cleanup=await (await import(new URL('desktop.js',js))).render(main);}finally{console.error=original;}
 try{
 assert.equal(win.getComputedStyle(q('.desktop')).paddingBottom,'6px');assert.equal(win.getComputedStyle(q('.desktop')).gridTemplateRows,'auto minmax(0,1fr) auto auto');
 assert.equal(win.getComputedStyle(q('.finance-period-bar')).marginBottom,'12px');assert.equal(win.getComputedStyle(q('.finance-metric strong')).marginTop,'3px');assert.equal(qa('.finance-metric').length,3);
 const canvas=q('#loop-canvas');click('[data-finance-period=week]');assert.equal(q('#loop-canvas'),canvas);assert.equal(q('[data-finance-period=week]').getAttribute('aria-pressed'),'true');
 // Happy DOM caches media-dependent styles until a stylesheet mutation.
 win.happyDOM.setWindowSize({width:1366,height:720});style.textContent=style.textContent;assert.equal(win.getComputedStyle(q('.desktop')).paddingBottom,'4px');assert.equal(win.getComputedStyle(q('.finance-metric strong')).fontSize,'26px');
 win.happyDOM.setWindowSize({width:480,height:840});style.textContent=style.textContent;assert.equal(win.getComputedStyle(q('.desktop')).paddingBottom,'4px');assert.equal(win.getComputedStyle(q('.finance-metrics')).gridTemplateColumns,'repeat(3,minmax(0,1fr))');assert.equal(win.getComputedStyle(q('.period-picker')).width,'100%');
 }finally{win.happyDOM.setWindowSize({width:1440,height:960});style.textContent=style.textContent;cleanup();main.className='window-content';}
});
await test('AI opens one animated overlay immediately and blocks repeated submissions',async()=>{
 cleanup=await (await import(new URL('simulation.js',js))).render(main);click('[data-simulation-tab=ai]');q('[name=intent]').value='За 109 дней получить результат 3 миллиона рублей';await start();submit();submit();
 assert.equal(calls.filter(c=>c.action==='simulation_ai_start').length,1);assert.equal(qa('.simulation-ai-overlay').length,1);assert(q('.simulation-ai-overlay .assistant-orbits'));assert.equal(qa('.simulation-ai-stages>li').length,3);assert(q('#simulation-controls').disabled);assert.equal(q('.simulation-ai-overlay [role=dialog]').getAttribute('aria-modal'),'true');
});
let orbit;
await test('AI progress updates text and stage without recreating its animation',async()=>{
 orbit=q('.simulation-ai-overlay .assistant-orbits');pending.simulation_ai_start(job());await tick();pending.simulation_ai_step(job(2));await tick();assert.equal(q('.simulation-ai-overlay .assistant-orbits'),orbit);assert.equal(q('.simulation-ai-overlay progress').value,2);assert(q('.simulation-ai-stages>li:nth-child(2)').classList.contains('current'));pending.simulation_ai_step(job(5));await tick();assert.equal(q('.simulation-ai-overlay .assistant-orbits'),orbit);assert(q('.simulation-ai-stages>li:nth-child(3)').classList.contains('current'));
});
await test('Ready strategies close the overlay and keep the current section',async()=>{
 pending.simulation_ai_step({...job(6),state:'ready',result:aiResult});await tick();assert(!q('.simulation-ai-overlay'));assert(!q('#simulation-controls').disabled);assert(q('[data-simulation-tab=ai]').classList.contains('active'));assert.equal(qa('.simulation-ai-variants>article').length,3);
});
await test('Pause waits for the current step and offers saved continuation',async()=>{
 await start();pending.simulation_ai_start(job());await tick();click('#simulation-ai-pause');assert(q('#simulation-ai-pause').disabled);assert(q('.simulation-ai-overlay').classList.contains('paused'));pending.simulation_ai_step(job(2));await tick();assert(!q('.simulation-ai-overlay'));assert(q('#simulation-ai-resume'));assert(!q('#simulation-controls').disabled);
});
await test('Closing an overlay pauses the job without reopening the window',async()=>{
 click('#simulation-ai-resume');pending.assistant_status(job(2));await tick();click('.simulation-ai-overlay [data-close]');await tick();assert(!q('.simulation-ai-overlay'));assert(q('#simulation-controls').disabled);pending.simulation_ai_step(job(3));await tick();assert(!q('.simulation-ai-overlay'));assert(!q('#simulation-controls').disabled);assert(q('#simulation-ai-resume'));
});
await test('Leaving simulation removes the overlay and prevents another step',async()=>{
 click('#simulation-ai-resume');pending.assistant_status(job(3));await tick();const count=calls.filter(c=>c.action==='simulation_ai_step').length;cleanup();await tick();assert(!q('.simulation-ai-overlay'));pending.simulation_ai_step(job(4));await tick();assert.equal(calls.filter(c=>c.action==='simulation_ai_step').length,count);
});
await test('A failed AI start closes the overlay, unlocks controls and shows the error',async()=>{
 cleanup=await (await import(new URL('simulation.js',js))).render(main);await start();pending.simulation_ai_start({apiFailure:'Контрольная ошибка AI-подбора'});await tick();assert(!q('.simulation-ai-overlay'));assert(!q('#simulation-controls').disabled);assert(q('.simulation-activity.error').textContent.includes('Контрольная ошибка'));cleanup();
});
let figure,svg,observer,removals=[];
await test('Technology keeps the drawing connected across operation progress',async()=>{
 main.innerHTML='';const {generateTechnology}=await import(new URL('technology-ai.js',js));generateTechnology(state.orders[0]);await tick();q('.ai-description').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await tick();figure=q('.garment-sketch');svg=figure.querySelector('svg');assert(figure.isConnected);observer=new win.MutationObserver(records=>{for(const r of records)for(const n of r.removedNodes)if(n===figure||n===svg||n.contains?.(figure)||n.contains?.(svg))removals.push(n.nodeName);});observer.observe(q('.ai-technology-body'),{childList:true,subtree:true});
 techJob={...techJob,operations:5,groups_done:1,current_group:'Рукава',retry_after:2};pending.technology_step(techJob);await tick(1200);assert.equal(q('.garment-sketch'),figure);assert.equal(q('.garment-sketch svg'),svg);assert.deepEqual(removals,[]);assert(q('[data-working-count]').textContent.includes('5'));assert(q('[data-working-error]').textContent.includes('сек.'));
});
await test('Technology pause/resume preserves the same SVG and drawing state',async()=>{
 click('#ai-pause');assert(figure.classList.contains('paused'));assert.equal(q('.garment-sketch svg'),svg);await tick(1100);techJob={...techJob,retry_after:0};click('#ai-pause');await tick();assert(!figure.classList.contains('paused'));assert.equal(q('.garment-sketch svg'),svg);assert.deepEqual(removals,[]);observer.disconnect();
});
await test('Contour animation repeats smoothly and preserves the final preview',async()=>{
 const css=style.textContent;assert(css.includes('animation:garment-breathe 10s'));assert(css.includes('30%,50%'));assert(css.includes('80%,100%'));assert(css.includes('prefers-reduced-motion')); assert(css.includes('.garment-sketch.paused .garment-contour'));pending.technology_step({...techJob,state:'ready',stage:'ready',route:state.orders[0].route,product:'Куртка',assumptions:[],questions:[]});await tick();assert(q('.garment-sketch.complete'));assert(q('#ai-apply'));click('.modal-layer [data-close]');await tick();
});
cleanup?.();const report={passed:checks.length,checks,errors};fs.writeFileSync(out+'v62-ui-results.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));await win.happyDOM.abort();process.exit(errors.length?1:0);
