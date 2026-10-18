import {e,money,num,modal,button} from './core.js';
import {icon} from './icons.js';
export function insightCards(data,metrics={}){
 if(!data)return '';
 const metric=id=>{const m=metrics[id];if(!m)return '';return '<span class="insight-metric"><span>'+e(m.label)+'</span><strong>'+e(m.value)+'</strong></span>';};
 return '<section class="ai-insight-report"><div class="ai-insight-heading">'+icon('sparkle')+'<div><p class="eyebrow">Пояснение AI · числа из расчёта</p><h3>'+e(data.summary)+'</h3></div></div><div class="insight-three">'+data.findings.map(f=>'<article class="'+e(f.direction)+'">'+metric(f.metric_id)+'<p>'+e(f.text)+'</p></article>').join('')+'</div><details class="insight-details" open><summary>Что можно сделать</summary><div class="insight-action-list">'+data.actions.map((a,i)=>'<article><i>'+(i+1)+'</i><div><strong>'+e(a.title)+'</strong><p>'+e(a.description)+'</p>'+metric(a.metric_id)+'</div></article>').join('')+'</div></details>'+data.questions.map(q=>'<p class="hint import-question">'+e(q)+'</p>').join('')+'</section>';
}
export function metricMoney(model,labels){return Object.fromEntries(Object.entries(labels).map(([id,label])=>[id,{label,value:money(model[id],true)}]));}
export function infoMetric(title,value,explanation){return modal(title,'<p class="insight-large-value">'+e(value)+'</p><p class="muted">'+e(explanation)+'</p>');}
