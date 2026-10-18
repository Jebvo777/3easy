// Development only: npm install --prefix tools, then node tools/build.mjs.
// Production hosting needs only the generated local assets and PHP.
import {readFile,mkdir,writeFile,readdir,unlink} from 'node:fs/promises';
import {fileURLToPath} from 'node:url';
import path from 'node:path';
import {createHash} from 'node:crypto';
const esbuild=await import(process.env.EASY3_BUILD_MODULE||'esbuild');
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),runtime=path.join(root,'assets/runtime');
const built=await esbuild.build({entryPoints:[path.join(root,'assets/js/app.js')],bundle:true,format:'esm',target:['es2022'],minify:true,legalComments:'inline',banner:{js:'/* EASY3 workspace 2026-10-18 v7 */'},write:false,logLevel:'silent'});
const js=built.outputFiles[0].text,css=(await readFile(path.join(root,'assets/css/app.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/refinement.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/manufacturing.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/easy3-next.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/easy3-refine.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/easy3-v7.css'),'utf8'))+'\n'+(await readFile(path.join(root,'assets/css/easy3-v71.css'),'utf8'));
const hash=s=>createHash('sha256').update(s).digest('hex').slice(0,16),jsName='app.'+hash(js)+'.js',cssName='ui.'+hash(css)+'.css';
await mkdir(runtime,{recursive:true});for(const file of await readdir(runtime))if(/^(app\.[a-f0-9]+\.js|ui\.[a-f0-9]+\.css)$/.test(file))await unlink(path.join(runtime,file));
await writeFile(path.join(runtime,jsName),js);await writeFile(path.join(runtime,cssName),css);
const manifest={build:'20261018.1-'+hash(js+css),js:'assets/runtime/'+jsName,css:'assets/runtime/'+cssName};
await writeFile(path.join(root,'assets/manifest.json'),JSON.stringify(manifest,null,2)+'\n');
process.stdout.write(JSON.stringify(manifest)+'\n');
