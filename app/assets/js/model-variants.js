import {e,key,button,num} from './core.js';
export function initialVariants(order,existing=false){
 const rows=order.variants?.length?structuredClone(order.variants):[{id:'v-'+key(),color:existing?'Основной':'',size:existing?'Единый':'',quantity:order.quantity||1}];const groups=new Map();return rows.map(v=>{if(!groups.has(v.color))groups.set(v.color,'c-'+key());return {...v,_color_group:groups.get(v.color)};});
}
export function variantEditor(variants){
 const groups=new Map();for(const v of variants){const id=v._color_group||v.color;if(!groups.has(id))groups.set(id,{id,name:v.color,rows:[]});groups.get(id).rows.push(v);}
 return `<div class="model-variants"><div class="row spread"><h3>Цвета и размеры</h3>${button('Добавить цвет','plus','small','type="button" data-add-color')}</div>${[...groups.values()].map(g=>`<section class="model-color" data-color="${e(g.id)}"><div class="row"><label>Цвет<input class="model-color-name" maxlength="100" required value="${e(g.name)}" placeholder="Например, чёрный"></label><button class="icon-btn" type="button" data-remove-color="${e(g.id)}" aria-label="Удалить цвет">×</button></div><div class="model-sizes">${g.rows.map(v=>`<div class="model-size" data-variant="${e(v.id)}"><label>Размер<input name="variant_size" maxlength="60" required value="${e(v.size)}" placeholder="S, 42 или 44–164"></label><label>Количество, шт.<input name="variant_quantity" type="number" min="1" max="100000" required value="${v.quantity||''}"></label><button class="icon-btn" type="button" data-remove-variant="${e(v.id)}" aria-label="Удалить размер">×</button></div>`).join('')}</div>${button('Добавить размер','plus','ghost small',`type="button" data-add-size="${e(g.id)}"`)}</section>`).join('')}<div class="model-total">Всего по модели: <strong id="model-total">${num(variants.reduce((n,v)=>n+(Number(v.quantity)||0),0))}</strong> шт.</div></div>`;
}
export function readVariants(root){return [...root.querySelectorAll('.model-color')].flatMap(color=>[...color.querySelectorAll('.model-size')].map(row=>({id:row.dataset.variant,_color_group:color.dataset.color,color:color.querySelector('.model-color-name').value.trim(),size:row.querySelector('[name="variant_size"]').value.trim(),quantity:Number(row.querySelector('[name="variant_quantity"]').value)})));}
export function bindVariants(root,{read,set,redraw}){
 const capture=()=>{set(readVariants(root));};
 root.querySelector('[data-add-color]')?.addEventListener('click',()=>{capture();const list=read();list.push({id:'v-'+key(),_color_group:'c-'+key(),color:'',size:'',quantity:1});set(list);redraw();});
 root.querySelectorAll('[data-add-size]').forEach(b=>b.onclick=()=>{capture();const list=read(),first=list.find(v=>v._color_group===b.dataset.addSize);list.push({id:'v-'+key(),_color_group:b.dataset.addSize,color:first?.color||'',size:'',quantity:1});set(list);redraw();});
 root.querySelectorAll('[data-remove-color]').forEach(b=>b.onclick=()=>{capture();set(read().filter(v=>v._color_group!==b.dataset.removeColor));redraw();});
 root.querySelectorAll('[data-remove-variant]').forEach(b=>b.onclick=()=>{capture();set(read().filter(v=>v.id!==b.dataset.removeVariant));redraw();});
 root.querySelectorAll('[name="variant_quantity"]').forEach(i=>i.addEventListener('input',()=>{capture();root.querySelector('#model-total').textContent=num(read().reduce((n,v)=>n+(v.quantity||0),0));}));
}
