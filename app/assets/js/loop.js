import * as T from '../vendor/three-kit.js';
const TAU=Math.PI*2;
function frame(t){
 const a=t*TAU,p=new T.Vector3(3.9*Math.sin(a),1.52*Math.sin(2*a),.52*Math.cos(a));
 const tangent=new T.Vector3(3.9*Math.cos(a),3.04*Math.cos(2*a),-.52*Math.sin(a)).normalize();
 const n=new T.Vector3(-tangent.y,tangent.x,0).normalize();const b=new T.Vector3().crossVectors(tangent,n).normalize();return {p,tangent,n,b};
}
function roundedProfile(w=.25,h=.16,r=.075,steps=8){let points=[];for(let k=0;k<4;k++){const x=k===0||k===3?w-r:-w+r,y=k<2?h-r:-h+r;for(let i=0;i<=steps;i++){const a=k*Math.PI/2+i/steps*Math.PI/2;points.push([x+r*Math.cos(a),y+r*Math.sin(a)]);}}return points;}
function ribbon(start,end,segments=384){
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
function texture(){
 const w=2048,h=256,data=new Uint8Array(w*h*4);let seed=19;
 for(let y=0;y<h;y++)for(let x=0;x<w;x++){seed=(seed*16807)%2147483647;const grain=(seed%100)/100;const scratch=Math.sin(y*3.7)*.12+Math.sin(y*.8)*.08+grain*.08;const v=Math.floor(145+scratch*160);const i=(y*w+x)*4;data[i]=data[i+1]=data[i+2]=v;data[i+3]=255;}
 const tex=new T.DataTexture(data,w,h,T.RGBAFormat);tex.wrapS=tex.wrapT=T.RepeatWrapping;tex.needsUpdate=true;return tex;
}
class FlowCurve extends T.Curve{constructor(s,e,lane){super();this.s=s;this.e=e;this.lane=lane;}getPoint(v,target=new T.Vector3()){const t=this.s+(this.e-this.s)*v,f=frame(t);return target.copy(f.p).addScaledVector(f.n,(this.lane-2)*.075).addScaledVector(f.b,.171);}}
export function createLoop(container,{hero=false,anomaly=null,onSelect=()=>{}}={}){
 let renderer;try{renderer=new T.WebGLRenderer({alpha:true,antialias:true,powerPreference:'high-performance'});}catch{container.innerHTML='<div class="desktop-fallback"><p>3D недоступно на этом устройстве.<br>Цель, план и производство доступны через кнопки.</p></div>';return {dispose(){},setAnomaly(){}};}
 renderer.setPixelRatio(Math.min(devicePixelRatio,hero?1.5:2));renderer.outputColorSpace=T.SRGBColorSpace;renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=1.35;container.append(renderer.domElement);
 const scene=new T.Scene(),camera=new T.PerspectiveCamera(32,1,.1,60);camera.position.set(0,.35,14.9);scene.add(new T.HemisphereLight(0xf4ffef,0x1e515b,2.1));
 const env=new T.RoomEnvironment();const pmrem=new T.PMREMGenerator(renderer);const envTarget=pmrem.fromScene(env,.04);scene.environment=envTarget.texture;env.dispose();
 const keyLight=new T.DirectionalLight(0xffe1b2,6);keyLight.position.set(5,6,8);scene.add(keyLight);const fill=new T.DirectionalLight(0x7cddd8,4);fill.position.set(-6,-2,5);scene.add(fill);
 const group=new T.Group();group.rotation.set(-.1,-.22,.01);group.position.y=.05;scene.add(group);
 const tex=texture();const metal=new T.MeshPhysicalMaterial({color:0x42575a,metalness:.93,roughness:.27,roughnessMap:tex,bumpMap:tex,bumpScale:.016,anisotropy:.48,anisotropyRotation:Math.PI/2,clearcoat:.45,clearcoatRoughness:.22,envMapIntensity:1.5});
 const gold=new T.MeshPhysicalMaterial({color:0xf4d6a3,metalness:.85,roughness:.28,emissive:0xc48a38,emissiveIntensity:.06});
 const arcs=[],flowMaterials=[],edges=[];const colors=[0xbbeace,0xcfe3e3,0xf3d8b4];
 for(let i=0;i<3;i++){
  const s=i/3,e=(i+1)/3;const meshData=ribbon(s+.0012,e-.0012);const mesh=new T.Mesh(meshData.geometry,metal);mesh.userData.section=i;group.add(mesh);arcs.push({...meshData,mesh});
  for(let lane=0;lane<5;lane++){
   const g=new T.TubeGeometry(new FlowCurve(s+.002,e-.002,lane),230,.006,4,false);
   const material=new T.ShaderMaterial({transparent:true,depthWrite:false,blending:T.AdditiveBlending,uniforms:{time:{value:0},color:{value:new T.Color(colors[i])},alert:{value:0},phase:{value:lane*.14+i*.333}},vertexShader:'varying vec2 vUv; void main(){vUv=uv;gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);}',fragmentShader:'varying vec2 vUv; uniform float time;uniform vec3 color;uniform float phase;uniform float alert; void main(){float pulse=pow(.5+.5*sin((vUv.x-time*.28+phase)*48.0),10.0);float a=.2+pulse*.8;vec3 c=mix(color,vec3(1.0,.31,.13),alert);gl_FragColor=vec4(c*(1.0+pulse*1.2),a);}' });
   const m=new T.Mesh(g,material);group.add(m);flowMaterials.push({material,mesh:m,arc:i});
  }
  const at=frame(s);const cap=new T.Mesh(new T.BoxGeometry(.44,.28,.027,5,3,1),gold);cap.position.copy(at.p);cap.quaternion.setFromUnitVectors(new T.Vector3(0,0,1),at.tangent);cap.visible=false;group.add(cap);edges.push(cap);
 }
 const sparkGeo=new T.BufferGeometry(),sparkPositions=new Float32Array(100*3);sparkGeo.setAttribute('position',new T.BufferAttribute(sparkPositions,3));const sparkMat=new T.PointsMaterial({color:0xffb577,size:.024,transparent:true,opacity:.8,depthWrite:false,blending:T.AdditiveBlending});const sparks=new T.Points(sparkGeo,sparkMat);sparks.visible=false;group.add(sparks);
 const glowLight=new T.PointLight(0xffa05b,0,3);group.add(glowLight);
 const controls=new T.OrbitControls(camera,renderer.domElement);controls.enablePan=false;controls.enableZoom=false;controls.enableDamping=true;controls.dampingFactor=.07;controls.minAzimuthAngle=-.55;controls.maxAzimuthAngle=.55;controls.minPolarAngle=Math.PI*.35;controls.maxPolarAngle=Math.PI*.62;controls.target.set(0,0,0);controls.autoRotate=hero;controls.autoRotateSpeed=.3;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)');if(reduced.matches)controls.autoRotate=false;
 let active=anomaly?.section??-1,gap=0,targetGap=active<0?0:.018,disposed=false,raf=0,frameNo=0;let visible=true;
 function resize(){const w=container.clientWidth,h=container.clientHeight;if(!w||!h)return;renderer.setSize(w,h,false);camera.aspect=w/h;camera.position.z=w/h<1.3?19:14.9;camera.updateProjectionMatrix();}
 const resizeObs=new ResizeObserver(resize);resizeObs.observe(container);resize();
 const observer=new IntersectionObserver(es=>{visible=es[0].isIntersecting;});observer.observe(container);
 const ray=new T.Raycaster(),pointer=new T.Vector2();let press=null;
 renderer.domElement.addEventListener('pointerdown',ev=>{press=[ev.clientX,ev.clientY];});
 renderer.domElement.addEventListener('pointerup',ev=>{if(!press||Math.hypot(press[0]-ev.clientX,press[1]-ev.clientY)>8)return;const rect=renderer.domElement.getBoundingClientRect();pointer.set((ev.clientX-rect.left)/rect.width*2-1,-(ev.clientY-rect.top)/rect.height*2+1);ray.setFromCamera(pointer,camera);const hits=ray.intersectObjects(arcs.map(a=>a.mesh));if(hits.length)onSelect(hits[0].object.userData.section);});
 function render(now){if(disposed)return;raf=requestAnimationFrame(render);if(!visible||document.hidden)return;const time=now/1000;frameNo++;
  const previous=gap;gap+=(targetGap-gap)*(reduced.matches?1:.08);
  if(Math.abs(previous-gap)>.00002&&frameNo%2===0){for(let i=0;i<3;i++){const start=i/3,end=(i+1)/3;const left=active===i?gap:0,right=active===(i+1)%3?gap:0;arcs[i].update(start+.0012+left,end-.0012-right);}}
  for(const f of flowMaterials){f.material.uniforms.time.value=reduced.matches?0:time;f.material.uniforms.alert.value=active===f.arc?.9:0;f.mesh.visible=active!==f.arc||gap<.01;}
  for(let i=0;i<edges.length;i++)edges[i].visible=i===active;
  if(active>=0){const f=frame(active/3);glowLight.position.copy(f.p);glowLight.intensity=2.8+(reduced.matches?0:Math.sin(time*3.3)*.7);sparks.visible=!reduced.matches;for(let i=0;i<100;i++){const a=i*2.399,timePart=(time*.35+i*.023)%1;sparkPositions[i*3]=f.p.x+Math.cos(a)*timePart*.5;sparkPositions[i*3+1]=f.p.y+Math.sin(a)*timePart*.45;sparkPositions[i*3+2]=f.p.z+.18+timePart*.3;}sparkGeo.attributes.position.needsUpdate=true;}else{sparks.visible=false;glowLight.intensity=0;}
  if(!reduced.matches)group.position.y=.05+Math.sin(time*.65)*.04;controls.update();renderer.render(scene,camera);
 }
 raf=requestAnimationFrame(render);
 return {setAnomaly(a){active=a?.section??-1;targetGap=active<0?0:.018;},dispose(){disposed=true;cancelAnimationFrame(raf);resizeObs.disconnect();observer.disconnect();controls.dispose();scene.traverse(obj=>{obj.geometry?.dispose();if(obj.material&&obj.material!==metal&&obj.material!==gold){const materials=Array.isArray(obj.material)?obj.material:[obj.material];materials.forEach(m=>m.dispose());}});metal.dispose();gold.dispose();tex.dispose();envTarget.dispose();pmrem.dispose();renderer.dispose();renderer.domElement.remove();}};
}
