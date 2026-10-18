import fs from 'node:fs';import assert from 'node:assert/strict';import {createWorkerAvatar,updateWorkerPose} from '../../assets/js/worker-avatar.js';import {workCycle} from '../../assets/js/factory-motion.js';
const checks=[],ok=(name,v)=>{assert(v,name);checks.push(name);};let moved=0,frames=0,maxMove=0;
for(const type of ['sew','overlock','cut','iron','hand','qc','pack']){
 const avatar=createWorkerAvatar({index:1,seated:['sew','overlock'].includes(type),type}),base=avatar.children.map(m=>m.geometry.getAttribute('position').array.slice());
 for(let frame=0;frame<80;frame++){
  const motion=workCycle(type,frame*.125,1);updateWorkerPose(avatar,motion);frames++;
  avatar.children.forEach((mesh,i)=>{const p=mesh.geometry.getAttribute('position').array;for(let k=0;k<p.length;k+=3){assert(Number.isFinite(p[k])&&Number.isFinite(p[k+1])&&Number.isFinite(p[k+2]));const delta=Math.hypot(p[k]-base[i][k],p[k+1]-base[i][k+1],p[k+2]-base[i][k+2]);maxMove=Math.max(maxMove,delta);if(delta>.001)moved++;if(base[i][k+1]<.2)assert(delta===0,'Feet stay planted');}});
 }
 ok(type+' stays finite, bounded and planted',maxMove<.16);ok(type+' keeps merged draw calls',avatar.children.length<=12);
 for(const m of avatar.children){m.geometry.dispose();m.material.dispose();}
}
ok('Arms and gaze actually change geometry',moved>1000);
for(const type of ['sew','overlock']){const a=workCycle(type,5.2,0),b=workCycle(type,6,0);ok(type+' wheel pauses during repositioning',a.wheel===b.wheel);}
for(const type of ['cut','iron']){const m=workCycle(type,2.3,0);ok(type+' tool follows the working hand',Math.abs(m.toolX-m.right[0])<1e-12&&Math.abs(m.toolZ-m.right[2])<1e-12);}
const report={passed:checks.length,checks,frames,max_displacement_m:maxMove};fs.mkdirSync('tools/qa/output/v71',{recursive:true});fs.writeFileSync('tools/qa/output/v71/motion-results.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
