import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
const output=path.resolve(process.argv[2]||'tools/qa/output');fs.mkdirSync(output,{recursive:true});
const {ribbon}=await import('../../assets/js/loop.js');
let report={vertices:0,triangles:0,arcs:3,checks:[]};
for(let i=0;i<3;i++){
 const r=ribbon(i/3+.00006,(i+1)/3-.00006),g=r.geometry;
 report.vertices+=g.attributes.position.count;report.triangles+=g.index.count/3;
 assert.ok(g.attributes.position.count>10000);
 assert.ok(g.attributes.uv.count===g.attributes.position.count);
 for(const name of ['position','normal','uv'])assert.ok([...g.attributes[name].array].every(Number.isFinite));
 const edges=new Map(),idx=g.index.array;
 for(let n=0;n<idx.length;n+=3)for(let k=0;k<3;k++){const a=idx[n+k],b=idx[n+(k+1)%3],key=a<b?`${a}/${b}`:`${b}/${a}`;edges.set(key,(edges.get(key)||0)+1);}
 assert.ok([...edges.values()].every(v=>v===2),'Closed manifold: every indexed edge belongs to two triangles');
 const original=g.attributes.position.array.slice();r.update(i/3+.018,(i+1)/3-.018);
 assert.ok(g.attributes.position.array.some((v,index)=>Math.abs(v-original[index])>.1),'Anomaly moves actual geometry vertices');
 g.dispose();
}
report.checks=['Более 10 000 вершин на сегмент','Позиции, нормали и UV конечны','Каждое ребро относится к двум треугольникам: объём закрыт','Разрыв физически перемещает вершины'];
fs.writeFileSync(path.join(output,'model.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
