import {e} from './core.js';
// Render validated coordinates, never provider HTML/SVG. Defensive limits also protect old jobs.
export function garmentSketch(data,{paused=false,complete=false}={}){
 if(!data||!Array.isArray(data.contours)||data.contours.length>12)return '';
 let points=0;const contours=[];
 for(const contour of data.contours){if(!['outline','detail','seam'].includes(contour.kind)||!Array.isArray(contour.points)||contour.points.length<2||contour.points.length>72)return '';const p=[];for(const pair of contour.points){if(!Array.isArray(pair)||pair.length!==2||pair.some(n=>typeof n!=='number'||!Number.isFinite(n)||n<0||n>1000))return '';p.push(pair);if(++points>360)return '';}contours.push({...contour,points:p});}
 const path=c=>c.points.map(([x,y],i)=>(i?'L':'M')+x+' '+y).join(' ')+(c.closed?' Z':'');
 return `<figure class="garment-sketch ${paused?'paused':''} ${complete?'complete':''}"><svg viewBox="0 0 1000 1000" role="img" aria-label="Схема: ${e(data.label||'Изделие')}"><g class="sketch-grid" aria-hidden="true"><path d="M100 100H900V900H100ZM500 70V930M70 500H930"/></g>${contours.map((c,i)=>`<path class="garment-contour ${c.kind}" d="${path(c)}" pathLength="1000" style="--contour-delay:${i*.16}s"/>`).join('')}<circle class="sketch-origin" cx="${contours[0]?.points[0][0]??500}" cy="${contours[0]?.points[0][1]??500}" r="5"/></svg><figcaption><strong>${e(data.label||'Изделие')}</strong><span>Схематичный вид · не лекала</span></figcaption></figure>`;
}
