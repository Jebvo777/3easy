// Isolated reference arithmetic; does not exercise the UI or a backend.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const html = fs.readFileSync(path.join(root, 'demos/target.html'), 'utf8');
const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(x => x[1]);
const script = scripts.find(s => s.includes('const periods=') && s.includes('root.TargetModel'));
assert.ok(script, 'TargetModel script missing');
const end = script.indexOf("})(typeof window==='undefined'?globalThis:window);");
assert.ok(end >= 0, 'TargetModel boundary missing');
const context = vm.createContext({module:{exports:{}}});
vm.runInContext(script.slice(0, end) + "})(typeof window==='undefined'?globalThis:window);", context);
const M = context.module.exports;
const sum = rows => rows.reduce((a,b)=>a+b,0);
const month = M.period('month');
assert.equal(sum(M.orders.filter(o=>o.type==='contract').map(M.revenue)), month.income);
assert.equal(sum(M.orders.map(o=>o.forecastCost)), month.cost);
assert.equal(sum(M.orders.map(o=>o.actualCost)), month.nowCost);
assert.equal(sum(M.orders.map(o=>o.forecastCost-o.baselineCost)), -month.delta);
assert.equal(month.forecast, 412000);
assert.equal(month.delta, -168000);
assert.equal(month.now, -126000);
const own=M.economics(M.orders.find(o=>o.type==='own'));
assert.equal(own.unitCost,230);
assert.equal(own.income,null);
assert.equal(own.result,null);
assert.equal(M.parseNumber(''),null);
const report={status:'passed',scope:'Isolated synthetic TargetModel arithmetic only',
  month:{income:month.income,cost:month.cost,result:month.forecast,delta:month.delta,current:month.now},
  own:{unitCost:own.unitCost,income:own.income,result:own.result},
  limitations:['No browser rendering or interaction','No production economics validation']};
console.log(JSON.stringify(report,null,2));
if(process.argv.includes('--report')) fs.writeFileSync(path.join(root,'docs/target-model-verification.json'),JSON.stringify(report,null,2)+'\n');
