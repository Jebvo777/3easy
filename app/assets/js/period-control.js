import {e} from './core.js';
import {periodRange} from './periods.js';
const periods=[['day','День','Сегодня'],['week','Неделя','Эта неделя'],['month','Месяц','Этот месяц']];
export function periodControl(value,{attribute='data-finance-period',label='Период сдачи целей'}={}){
 return `<div class="period-picker" role="group" aria-label="${e(label)}">${periods.map(([id,title,hint])=>`<button type="button" ${attribute}="${id}" class="period-choice ${id===value?'selected':''}" aria-pressed="${id===value}" title="${hint}"><span>${title}</span><small>${hint}</small></button>`).join('')}</div>`;
}
export function periodCaption(value,zone){if(value==='all')return 'Все действующие цели · '+new Intl.DateTimeFormat('ru-RU',{year:'numeric',timeZone:zone||'Europe/Moscow'}).format(new Date())+' г.';const r=periodRange(value,zone),format=s=>new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric'}).format(new Date(s+'T12:00:00'));return r.from===r.to?format(r.from):format(r.from)+' — '+format(r.to);}

export function portfolioControl(value,attribute='data-finance-period'){return `<div class="portfolio-period-controls"><button type="button" class="portfolio-period ${value==='all'?'selected':''}" ${attribute}="all" aria-pressed="${value==='all'}">Весь портфель</button>${periodControl(value,{attribute})}</div>`;}
