import * as T from '../vendor/three-kit.js';
const TAU=Math.PI*2;
function frame(t){
 const a=t*TAU,p=new T.Vector3(3.9*Math.sin(a),1.52*Math.sin(2*a),.52*Math.cos(a));
 const tangent=new T.Vector3(3.9*Math.cos(a),3.04*Math.cos(2*a),-.52*Math.sin(a)).normalize();
 const n=new T.Vector3(-tangent.y,tangent.x,0).normalize();const b=new T.Vector3().crossVectors(tangent,n).normalize();return {p,tangent,n,b};
}
function roundedProfile(w=.25,h=.16,r=.075,steps=8){let points=[];for(let k=0;k<4;k++){const x=k===0||k===3?w-r:-w+r,y=k<2?h-r:-h+r;for(let i=0;i<=steps;i++){const a=k*Math.PI/2+i/steps*Math.PI/2;points.push([x+r*Math.cos(a),y+r*Math.sin(a)]);}}return points;}
export function ribbon(start,end,segments=384){
 const section=roundedProfile();const radial=section.length,n=(segments+1)*radial;
 const pos=new Float32Array(n*3),uv=new Float32Array(n*2),indices=[];
 for(let i=0;i<segments;i++)for(let k=0;k<radial;k++){const next=(k+1)%radial,a=i*radial+k,b=i*radial+next,c=(i+1)*radial+k,d=(i+1)*radial+next;indices.push(a,b,c,b,d,c);}
 // Solid end caps keep the separated model a real closed object.
 const front=n,back=n+1;const full=new Float32Array((n+2)*3);for(let k=0;k<radial;k++){indices.push(front,k,(k+1)%radial);indices.push(back,segments*radial+(k+1)%radial,segments*radial+k);}
 const fullUV=new Float32Array((n+2)*2);fullUV.set(uv);
 const g=new T.BufferGeometry();g.setAttribute('position',new T.BufferAttribute(full,3));g.setAttribute('uv',new T.BufferAttribute(fullUV,2));g.setIndex(indices);
 const update=(s,e)=>{
  const p=g.attributes.position.array,u=g.attributes.uv.array;
  for(let i=0;i<=segments;i++){const t=s+(e-s)*i/segments,f=frame(t);for(let k=0;k<radial;k++){const index=i*radial+k,q=f.p.clone().addScaledVector(f.n,section[k][0]).addScaledVector(f.b,section[k][1]);p[index*3]=q.x;p[index*3+1]=q.y;p[index*3+2]=q.z;u[index*2]=t*8;u[index*2+1]=k/radial;}}
  const f1=frame(s).p,f2=frame(e).p;f1.toArray(p,front*3);f2.toArray(p,back*3);g.attributes.position.needsUpdate=true;g.attributes.uv.needsUpdate=true;g.computeVertexNormals();g.computeBoundingSphere();
 };update(start,end);return {geometry:g,update};
}

// Real PBR maps: neutral albedo, microscopic directional grain and tangent normals.
export function metalMaps(){
 const w=2048,h=512,albedo=new Uint8Array(w*h*4),rough=new Uint8Array(w*h*4),normal=new Uint8Array(w*h*4);let seed=193;
 const noise=()=>{seed=(seed*16807)%2147483647;return seed/2147483647;};
 for(let y=0;y<h;y++)for(let x=0;x<w;x++){
  const i=(y*w+x)*4,n=noise(),grain=Math.sin(y*2.17)*.33+Math.sin(y*5.61+x*.008)*.2+Math.sin(y*.13+x*.003)*.18;
  const pit=n>.998?-.7:0,v=204+grain*5+(n-.5)*2+pit*7;
  albedo[i]=v;albedo[i+1]=v+3;albedo[i+2]=v+4;albedo[i+3]=255;
  const r=195+grain*25+(n-.5)*14;rough[i]=rough[i+1]=rough[i+2]=r;rough[i+3]=255;
  const nx=(n-.5)*.03,ny=Math.cos(y*2.17)*.15+Math.cos(y*5.61+x*.008)*.06+(n-.5)*.035,nz=1/Math.sqrt(nx*nx+ny*ny+1);
  normal[i]=(nx*nz*.5+.5)*255;normal[i+1]=(ny*nz*.5+.5)*255;normal[i+2]=(nz*.5+.5)*255;normal[i+3]=255;
 }
 const make=data=>{const t=new T.DataTexture(data,w,h,T.RGBAFormat);t.wrapS=t.wrapT=T.RepeatWrapping;t.magFilter=T.LinearFilter;t.minFilter=T.LinearMipmapLinearFilter;t.generateMipmaps=true;t.needsUpdate=true;return t;};
 const maps={map:make(albedo),roughnessMap:make(rough),normalMap:make(normal)};maps.map.colorSpace=T.SRGBColorSpace;return maps;
}
class FlowCurve extends T.Curve{constructor(s,e,lane){super();this.s=s;this.e=e;this.lane=lane;}getPoint(v,target=new T.Vector3()){const t=this.s+(this.e-this.s)*v,f=frame(t);return target.copy(f.p).addScaledVector(f.n,(this.lane-2)*.062).addScaledVector(f.b,.166);}}
export const loopArcFor=section=>section==null?-1:[2,0,1][section];
export function fractureIntervals(active,gap){return Array.from({length:3},(_,i)=>[i/3+.00006+(active===i?gap:0),(i+1)/3-.00006-(active===(i+1)%3?gap:0)]);}
export function createLoop(container,{hero=false,anomaly=null,onSelect=()=>{},onHover=()=>{},onProject=()=>{}}={}){
 let renderer;try{renderer=new T.WebGLRenderer({alpha:true,antialias:true,powerPreference:'high-performance'});}catch{container.innerHTML='<div class="desktop-fallback"><p>Для объёмной модели нужен WebGL.<br>Все действия доступны ниже.</p></div>';return {dispose(){},setAnomaly(){},supported:false};}
 renderer.setPixelRatio(Math.min(devicePixelRatio,hero?1.5:1.8));renderer.outputColorSpace=T.SRGBColorSpace;renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=.94;container.append(renderer.domElement);
 const scene=new T.Scene(),camera=new T.PerspectiveCamera(31,1,.1,70);camera.position.set(0,.25,14.3);
 const hemi=new T.HemisphereLight(0xc9e6ff,0x153c47,.35);scene.add(hemi);
 const pmrem=new T.PMREMGenerator(renderer),env=new T.RoomEnvironment();let envTarget=pmrem.fromScene(env,.08);scene.environment=envTarget.texture;scene.environmentIntensity=.65;env.dispose();
 const keyLight=new T.DirectionalLight(0xffdfb2,2.7);keyLight.position.set(7,5,4);scene.add(keyLight);const fill=new T.DirectionalLight(0xb8dfff,1.1);fill.position.set(-5,3,4);scene.add(fill);const rim=new T.DirectionalLight(0xd5f9e8,1.35);rim.position.set(-3,-1,-4);scene.add(rim);
 const group=new T.Group();group.rotation.set(-.08,-.14,-.025);group.position.y=.05;scene.add(group);
 const maps=metalMaps();Object.values(maps).forEach(t=>t.anisotropy=Math.min(8,renderer.capabilities.getMaxAnisotropy()));
 const metal=new T.MeshPhysicalMaterial({...maps,color:0xe4e8e5,metalness:1,roughness:.31,normalScale:new T.Vector2(.24,.38),anisotropy:.72,anisotropyRotation:0,clearcoat:.17,clearcoatRoughness:.16,envMapIntensity:1.05});
 const cutMetal=new T.MeshPhysicalMaterial({color:0xe75652,metalness:.65,roughness:.27,emissive:0xff1d30,emissiveIntensity:.6});
 const arcs=[],flows=[],caps=new T.Group();group.add(caps);for(let k=0;k<2;k++)caps.add(new T.Mesh(new T.BoxGeometry(.46,.285,.014,5,3,1),cutMetal));caps.visible=false;
 for(let i=0;i<3;i++){
  const a=ribbon(i/3+.00006,(i+1)/3-.00006),mesh=new T.Mesh(a.geometry,metal.clone());mesh.userData.section=[1,2,0][i];group.add(mesh);arcs.push({...a,mesh});
  for(let lane=0;lane<5;lane++){
   const g=new T.TubeGeometry(new FlowCurve(i/3+.0001,(i+1)/3-.0001,lane),230,.0045,5,false);
   const material=new T.ShaderMaterial({transparent:true,depthWrite:false,blending:T.AdditiveBlending,uniforms:{time:{value:0},color:{value:new T.Color([0xa8e8d4,0xbcddec,0xe7d9bc][i])},alert:{value:0},phase:{value:lane*.13+i*.333},left:{value:0},right:{value:1}},vertexShader:'varying vec2 vUv;void main(){vUv=uv;gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);}',fragmentShader:'varying vec2 vUv;uniform float time;uniform vec3 color;uniform float phase;uniform float alert;uniform float left;uniform float right;void main(){if(vUv.x<left||vUv.x>right)discard;float pulse=pow(.5+.5*sin((vUv.x-time*.12+phase)*32.0),16.0);vec3 c=mix(color,vec3(1.0,.045,.065),alert);gl_FragColor=vec4(c*(.28+pulse*.7),.12+pulse*.58);}'});
   const m=new T.Mesh(g,material);group.add(m);flows.push({material,mesh:m,arc:i});
  }
 }
 const heatMaterial=new T.ShaderMaterial({transparent:true,depthWrite:false,blending:T.NormalBlending,uniforms:{time:{value:0}},vertexShader:'varying vec3 vN;varying vec3 vV;void main(){vec4 p=modelViewMatrix*vec4(position,1.0);vN=normalize(normalMatrix*normal);vV=normalize(-p.xyz);gl_Position=projectionMatrix*p;}',fragmentShader:'varying vec3 vN;varying vec3 vV;uniform float time;void main(){float edge=pow(1.0-abs(dot(normalize(vN),normalize(vV))),1.6);float pulse=.92+.08*sin(time*2.0);gl_FragColor=vec4(1.0,.018,.055,(.13+edge*.3)*pulse);}'});
 const redArea=new T.Mesh(new T.SphereGeometry(.68,48,32),heatMaterial);redArea.visible=false;group.add(redArea);
 const ring=new T.Mesh(new T.TorusGeometry(.73,.008,8,80),new T.MeshBasicMaterial({color:0xff3545,transparent:true,opacity:.85,depthWrite:false}));ring.visible=false;group.add(ring);
 const glowLight=new T.PointLight(0xff1731,0,3,2);group.add(glowLight);
 const sparkGeo=new T.BufferGeometry(),sparkPositions=new Float32Array(45*3);sparkGeo.setAttribute('position',new T.BufferAttribute(sparkPositions,3));const sparks=new T.Points(sparkGeo,new T.PointsMaterial({color:0xff4655,size:.018,transparent:true,opacity:.65,depthWrite:false,blending:T.AdditiveBlending}));sparks.visible=false;group.add(sparks);
 const controls=new T.OrbitControls(camera,renderer.domElement);controls.enablePan=false;controls.enableZoom=false;controls.enableDamping=true;controls.dampingFactor=.065;controls.minAzimuthAngle=-.52;controls.maxAzimuthAngle=.52;controls.minPolarAngle=Math.PI*.36;controls.maxPolarAngle=Math.PI*.61;controls.target.set(0,0,0);controls.autoRotate=hero;controls.autoRotateSpeed=.22;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');if(reduced.matches)controls.autoRotate=false;
 let active=loopArcFor(anomaly?.section),gap=0,targetGap=active<0?0:.027,geometryDirty=true,disposed=false,raf=0,visible=true,pressed=null,hovered=false;
 // A real photographic HDR panorama supplies the same sunset / lake colour family as the desktop.
 import('../vendor/hdr-loader.js').then(({HDRLoader})=>new HDRLoader().load('assets/media/lakes_1k.hdr',hdr=>{if(disposed){hdr.dispose();return;}const next=pmrem.fromEquirectangular(hdr);hdr.dispose();envTarget.dispose();envTarget=next;scene.environment=next.texture;scene.environmentIntensity=.88;scene.environmentRotation.set(0,1.9,0);},undefined,()=>{})).catch(()=>{});
 function resize(){const w=container.clientWidth,h=container.clientHeight;if(!w||!h)return;renderer.setSize(w,h,false);camera.aspect=w/h;camera.position.z=w/h<1.4?19:14.3;camera.updateProjectionMatrix();}
 const resizeObs=new ResizeObserver(resize);resizeObs.observe(container);resize();const observer=new IntersectionObserver(es=>{visible=es[0].isIntersecting;});observer.observe(container);
 const ray=new T.Raycaster(),pointer=new T.Vector2();
 function hit(ev){const rect=renderer.domElement.getBoundingClientRect();pointer.set((ev.clientX-rect.left)/rect.width*2-1,-(ev.clientY-rect.top)/rect.height*2+1);ray.setFromCamera(pointer,camera);return ray.intersectObjects([...arcs.map(a=>a.mesh),...(redArea.visible?[redArea,caps]:[])],true);}
 function hover(ev){const hits=hit(ev),alert=active>=0&&hits.some(h=>h.object===redArea||h.object.parent===caps);renderer.domElement.style.cursor=hits.length?'pointer':'grab';hovered=alert;onHover(alert?{anomaly,x:ev.clientX,y:ev.clientY}:null);}
 renderer.domElement.addEventListener('pointermove',hover);renderer.domElement.addEventListener('pointerleave',()=>{hovered=false;onHover(null);});renderer.domElement.addEventListener('pointerdown',ev=>{pressed=[ev.clientX,ev.clientY];});
 renderer.domElement.addEventListener('pointerup',ev=>{if(!pressed||Math.hypot(pressed[0]-ev.clientX,pressed[1]-ev.clientY)>8)return;pressed=null;const h=hit(ev)[0];if(!h)return;if(h.object===redArea||h.object.parent===caps){onSelect(anomaly.section);return;}let o=h.object;while(o&&o.userData.section==null)o=o.parent;if(o)onSelect(o.userData.section);});
 function render(now){if(disposed)return;raf=requestAnimationFrame(render);if(!visible||document.hidden)return;const time=reduced.matches?0:now/1000,previous=gap;gap+=(targetGap-gap)*(reduced.matches?1:.09);
  if(geometryDirty||Math.abs(previous-gap)>.00001){const intervals=fractureIntervals(active,gap);arcs.forEach((a,i)=>a.update(...intervals[i]));geometryDirty=false;}
  for(const f of flows){f.material.uniforms.time.value=time;f.material.uniforms.alert.value=active===f.arc?.7:0;f.material.uniforms.left.value=active===f.arc?gap*3:0;f.material.uniforms.right.value=active===(f.arc+1)%3?1-gap*3:1;}
  caps.visible=redArea.visible=ring.visible=active>=0;
  if(active>=0){const centre=frame(active/3).p;redArea.position.copy(centre);redArea.userData.section=anomaly.section;ring.position.copy(centre);ring.position.z+=.16;glowLight.position.copy(centre);glowLight.intensity=1.2+Math.sin(time*2)*.16;heatMaterial.uniforms.time.value=time;
   for(let k=0;k<2;k++){const f=frame((active/3+(k===0?gap+.00006:-gap-.00006)+1)%1),m=caps.children[k];m.position.copy(f.p);m.quaternion.setFromRotationMatrix(new T.Matrix4().makeBasis(f.n,f.b,f.tangent));}
   sparks.visible=!reduced.matches;for(let i=0;i<45;i++){const a=i*2.399,p=(time*.19+i*.037)%1;sparkPositions[i*3]=centre.x+Math.cos(a)*p*.47;sparkPositions[i*3+1]=centre.y+Math.sin(a)*p*.44;sparkPositions[i*3+2]=centre.z+.16+p*.2;}sparkGeo.attributes.position.needsUpdate=true;
  }else{sparks.visible=false;glowLight.intensity=0;}
  if(!reduced.matches)group.position.y=.05+Math.sin(time*.45)*.025;controls.update();renderer.render(scene,camera);
  if(active>=0){const point=frame(active/3).p.applyMatrix4(group.matrixWorld).project(camera);onProject({x:(point.x+1)/2*container.clientWidth,y:(1-point.y)/2*container.clientHeight,anomaly,hovered});}else onProject(null);
 }
 raf=requestAnimationFrame(render);
 return {supported:true,setAnomaly(a){anomaly=a;active=loopArcFor(a?.section);targetGap=active<0?0:.027;geometryDirty=true;onHover(null);},dispose(){disposed=true;cancelAnimationFrame(raf);resizeObs.disconnect();observer.disconnect();controls.dispose();const materials=new Set();scene.traverse(o=>{o.geometry?.dispose();if(o.material)(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>materials.add(m));});materials.forEach(m=>m.dispose());metal.dispose();Object.values(maps).forEach(t=>t.dispose());envTarget.dispose();pmrem.dispose();renderer.dispose();renderer.domElement.remove();}};
}
