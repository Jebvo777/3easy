import QR from '../vendor/qrcode.js';
import {S,e,modal,button,toast} from './core.js';

export function workplaceUrl(id,companyId=S.data.company.id){
 const url=new URL(location.href);url.search='';url.searchParams.set('company',companyId);url.searchParams.set('workplace',id);url.hash='workplace';return url.href;
}
export function qrSvg(id,companyId=S.data.company.id){
 const qr=QR(0,'M');qr.addData(workplaceUrl(id,companyId),'Byte');qr.make();return qr.createSvgTag({cellSize:8,margin:32,scalable:true});
}
const short=(text,limit)=>{const s=String(text||'');return s.length>limit?s.slice(0,limit-1)+'…':s;};
function labelData(workplace,company){
 const departments=company.passport?.structure?.workshops||[];
 return {id:workplace.id,name:workplace.name,company_id:company.id,company_name:company.name,department_name:departments.find(d=>d.id===workplace.workshop)?.name||workplace.department_name||''};
}
function nameLines(text){
 const words=String(text||'').split(/\s+/),lines=[''];
 for(const word of words){const last=lines.length-1;if((lines[last]+' '+word).trim().length>30&&lines[last])lines.push(word);else lines[last]=(lines[last]+' '+word).trim();}
 return lines.slice(0,2).map((s,i)=>short(s,lines.length>2&&i===1?28:32)+(lines.length>2&&i===1?'…':''));
}
export function workplaceLabelSvg(workplace,company=S.data.company){
 const d=labelData(workplace,company),qr=qrSvg(d.id,d.company_id).replace('<svg ','<svg x="64" y="46" width="472" height="472" ');
 return `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="720" viewBox="0 0 600 720" role="img" aria-label="${e(d.name)}"><title>${e(d.name)} — QR рабочего места</title><rect width="600" height="720" fill="white"/><rect x="1" y="1" width="598" height="718" rx="20" fill="none" stroke="#d1d5d0"/>${qr}<g text-anchor="middle" font-family="Arial, sans-serif" fill="#14271d"><text x="300" y="548" font-size="18">${e(short(d.company_name,42))}</text>${nameLines(d.name).map((line,i)=>`<text x="300" y="584" dy="${i*30}" font-size="25" font-weight="700">${e(line)}</text>`).join('')}<text x="300" y="650" font-size="17">${e(short(d.department_name,48))}</text><text x="300" y="680" font-size="13">${e(d.id)}</text><text x="300" y="704" font-size="12">EASY3 · Отсканируйте для начала работы</text></g></svg>`;
}
function saveBlob(blob,name){
 const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);
}
export async function downloadWorkplaceLabel(workplace,format='svg',company=S.data.company){
 const svg=workplaceLabelSvg(workplace,company),blob=new Blob([svg],{type:'image/svg+xml;charset=utf-8'}),name='EASY3_QR_'+String(workplace.name||workplace.id).replace(/[^\p{L}\p{N}._-]+/gu,'_').slice(0,65)+'_'+workplace.id.slice(-6);
 if(format==='svg'){saveBlob(blob,name+'.svg');return;}
 const url=URL.createObjectURL(blob),img=new Image();
 try{await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=()=>reject(new Error('Не удалось подготовить PNG. Скачайте SVG.'));img.src=url;});const canvas=document.createElement('canvas');canvas.width=1800;canvas.height=2160;const ctx=canvas.getContext('2d');if(!ctx)throw new Error('Браузер не поддерживает PNG. Скачайте SVG.');ctx.fillStyle='white';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);const png=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!png)throw new Error('Не удалось сохранить PNG. Скачайте SVG.');saveBlob(png,name+'.png');}finally{URL.revokeObjectURL(url);}
}
export function printWorkplaceLabels(workplaces,company=S.data.company){
 if(!workplaces.length){toast('В этом подразделении пока нет рабочих мест',true);return;}
 const frame=document.createElement('iframe');frame.title='Печать QR рабочих мест';frame.setAttribute('aria-hidden','true');frame.style.cssText='position:fixed;left:-10000px;top:0;width:1px;height:1px;border:0;';
 frame.onload=()=>{const win=frame.contentWindow;if(!win)return;win.addEventListener('afterprint',()=>frame.remove(),{once:true});try{win.focus();win.print();}catch{toast('Не удалось открыть печать. Скачайте SVG или PNG.',true);}setTimeout(()=>frame.remove(),60000);};
 frame.srcdoc=`<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>QR рабочих мест — ${e(company.name)}</title><style>@page{size:A4;margin:10mm}*{box-sizing:border-box}body{margin:0;background:white}.labels{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:5mm}.label{break-inside:avoid;page-break-inside:avoid;display:block}.label svg{display:block;width:100%;height:auto;print-color-adjust:exact;-webkit-print-color-adjust:exact}@media screen{body{max-width:190mm;margin:10mm auto}}</style></head><body><div class="labels">${workplaces.map(w=>`<article class="label">${workplaceLabelSvg(w,company)}</article>`).join('')}</div></body></html>`;
 document.body.append(frame);
}
export function showWorkplaceQr(id){
 const company=S.data.company,workplace=company.passport.workplaces?.find(w=>w.id===id);if(!workplace){toast('Сохраните рабочее место перед созданием QR',true);return null;}
 const m=modal('QR рабочего места',`<div class="qr-label-preview">${workplaceLabelSvg(workplace,company)}</div><div class="qr-label-actions">${button('Скачать SVG','download','','data-download-svg')}${button('Скачать PNG','download','','data-download-png')}${button('Распечатать','grid','primary','data-print-label')}</div><p class="hint">QR постоянный. Его можно скачать и распечатать до начала работы сотрудника.</p><a class="btn small" href="${e(workplaceUrl(id,company.id))}" target="_blank" rel="noopener">Открыть экран сотрудника</a>`,{large:true});
 for(const format of ['svg','png'])m.el.querySelector('[data-download-'+format+']').onclick=async ev=>{const b=ev.currentTarget;b.disabled=true;try{await downloadWorkplaceLabel(workplace,format,company);}catch(err){toast(err.message,true);}finally{b.disabled=false;}};
 m.el.querySelector('[data-print-label]').onclick=()=>printWorkplaceLabels([workplace],company);return m;
}
