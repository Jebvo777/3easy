import {e} from './core.js';
import {periodRange} from './periods.js';
const periods=[['day','День','Сегодня'],['week','Неделя','Эта неделя'],['month','Месяц','Этот месяц']];
export function periodControl(value,{attribute='data-finance-period',label='Период сдачи целей'}={}){
 return `<div class="period-picker" role="group" aria-label="${e(label)}">${periods.map(([id,title,hint])=>`<button type="button" ${attribute}="${id}" class="period-choice ${id===value?'selected':''}" aria-pressed="${id===value}" title="${hint}"><span>${title}</span><small>${hint}</small></button>`).join('')}</div>`;
}
export function periodCaption(value,zone){const r=periodRange(value,zone),format=s=>new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric'}).format(new Date(s+'T12:00:00'));return r.from===r.to?format(r.from):format(r.from)+' — '+format(r.to);}
