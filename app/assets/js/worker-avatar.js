import * as T from '../vendor/three-kit.js';
// Human proportions in metres. Details are merged by material to keep workshop draw calls bounded.
function mergeStaticParts(root){
 root.updateMatrixWorld(true);const groups=new Map(),old=[];
 root.traverse(o=>{if(!o.isMesh)return;old.push(o);const g=o.geometry.clone().applyMatrix4(o.matrixWorld),flat=g.index?g.toNonIndexed():g;if(flat!==g)g.dispose();const key=o.material.uuid;if(!groups.has(key))groups.set(key,{material:o.material,positions:[],normals:[]});const b=groups.get(key);b.positions.push(flat.getAttribute('position').array.slice());b.normals.push(flat.getAttribute('normal').array.slice());flat.dispose();});
 for(const o of old)o.geometry.dispose();root.clear();
 for(const b of groups.values()){const concat=parts=>{const data=new Float32Array(parts.reduce((n,p)=>n+p.length,0));let at=0;for(const p of parts){data.set(p,at);at+=p.length;}return data;};const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.BufferAttribute(concat(b.positions),3));geometry.setAttribute('normal',new T.BufferAttribute(concat(b.normals),3));geometry.computeBoundingSphere();const mesh=new T.Mesh(geometry,b.material);mesh.castShadow=true;mesh.receiveShadow=true;root.add(mesh);}
 root.userData.detailParts=old.length;root.userData.drawCalls=groups.size;return root;
}
export function createWorkerAvatar({index=0,seated=false,type='sew'}={}){
 const variant=Math.abs(index)%6,root=new T.Group();root.name='worker-avatar-v2';root.userData.avatarVersion=2;root.userData.pose=seated?'seated':'standing';
 const skinColors=[0xd6a17f,0xba8868,0xe1b496,0xa87556,0xc99270,0xe4b99b],coats=[0x496e61,0x4f6b83,0x8b7452,0x7a5b50,0x6d6481,0x617850],hairColors=[0x392d25,0x282523,0x775741,0x49392e,0x9c8156,0x584b40];
 const mat=(color,roughness=.82)=>new T.MeshStandardMaterial({color,roughness,metalness:0});
 const skin=mat(skinColors[variant],.78),coat=mat(coats[variant],.94),darkCoat=mat(new T.Color(coats[variant]).multiplyScalar(.7),.96),pants=mat([0x354544,0x343d4d,0x434941][variant%3],.95),hair=mat(hairColors[variant],.94),shoe=mat(0x2d3331,.72),sole=mat(0x707a70,.9),ivory=mat(0xe8e4d3,.86),eyeWhite=mat(0xe5e1d8,.5),eyeDark=mat(0x343c33,.35),trim=mat(0x839288,.62),lip=mat(0x9f6a57,.9);
 const add=(geometry,material,parent=root,x=0,y=0,z=0)=>{const mesh=new T.Mesh(geometry,material);mesh.position.set(x,y,z);parent.add(mesh);return mesh;};
 const oval=(radii,material,x,y,z,parent=root)=>{const mesh=add(new T.SphereGeometry(1,16,12),material,parent,x,y,z);mesh.scale.set(...radii);return mesh;};
 const box=(size,material,x,y,z,parent=root)=>add(new T.BoxGeometry(...size),material,parent,x,y,z);
 const limb=(a,b,r1,r2,material,parent=root)=>{const start=new T.Vector3(...a),end=new T.Vector3(...b),direction=end.clone().sub(start);const part=add(new T.CylinderGeometry(r2,r1,direction.length(),12,1),material,parent);part.position.copy(start).add(end).multiplyScalar(.5);part.quaternion.setFromUnitVectors(new T.Vector3(0,1,0),direction.normalize());return part;};
 const hip=seated?.57:.85,waist=hip+.17,chest=hip+.4,shoulder=hip+.51,neck=hip+.61,face=hip+.78;
 // Shaped torso: pelvis, fitted waist, chest, shoulder slope, neck opening.
 const rings=[[hip-.02,.145,.105],[hip+.08,.15,.108],[waist,.132,.09],[chest-.04,.177,.108],[shoulder-.03,.195,.092],[shoulder+.035,.115,.085],[neck-.045,.072,.07]],positions=[],indices=[],segments=24;
 for(const [y,w,d] of rings)for(let i=0;i<segments;i++){const t=i/segments*Math.PI*2;positions.push(Math.cos(t)*w,y,Math.sin(t)*d);}
 for(let ring=0;ring<rings.length-1;ring++)for(let i=0;i<segments;i++){const a=ring*segments+i,b=ring*segments+(i+1)%segments,c=a+segments,d=b+segments;indices.push(a,c,b,b,c,d);}
 const torso=new T.BufferGeometry();torso.setAttribute('position',new T.BufferAttribute(new Float32Array(positions),3));torso.setIndex(indices);torso.computeVertexNormals();add(torso,coat);
 oval([.147,.095,.105],pants,0,hip-.025,0);limb([0,neck-.045,0],[0,neck+.03,0],.054,.05,skin);
 // Collar, placket, chest pockets, hem and a small neutral badge.
 for(const side of [-1,1]){const collar=box([.08,.07,.018],ivory,side*.047,neck-.06,-.067);collar.rotation.z=side*.43;box([.066,.062,.011],darkCoat,side*.095,chest-.03,-.102);box([.072,.012,.014],coat,side*.095,chest+.003,-.109);}
 box([.009,.38,.012],darkCoat,0,hip+.25,-.109);for(let i=0;i<4;i++)oval([.008,.008,.006],trim,0,hip+.12+i*.085,-.12);
 box([.06,.032,.012],ivory,.095,chest+.03,-.112);box([.038,.008,.013],trim,.095,chest+.03,-.119);box([.245,.023,.17],darkCoat,0,hip+.025,0);
 const head=new T.Group();root.add(head);head.position.y=face;head.rotation.x=seated?-.14:-.1;head.rotation.y=variant%3===0?.07:variant%3===1?-.06:0;
 oval([.102,.137,.105],skin,0,0,0,head);oval([.077,.055,.076],skin,0,-.072,-.014,head);oval([.025,.038,.028],skin,0,-.01,-.098,head);oval([.019,.013,.018],skin,0,-.025,-.117,head);
 for(const side of [-1,1]){oval([.023,.034,.016],skin,side*.104,-.005,.002,head);oval([.023,.012,.01],eyeWhite,side*.039,.029,-.094,head);oval([.009,.01,.007],eyeDark,side*.039,.029,-.103,head);const brow=box([.042,.009,.009],hair,side*.039,.053,-.096,head);brow.rotation.z=side*.08;}
 box([.038,.005,.006],lip,0,-.051,-.09,head);
 const cap=add(new T.SphereGeometry(1,20,12,0,Math.PI*2,0,1.27),hair,head,0,.009,.005);cap.scale.set(.107,.14,.109);
 oval([.085,.068,.034],hair,0,.027,.083,head);if(variant%2===1)oval([.057,.055,.045],hair,0,.067,.114,head);else{const lock=oval([.064,.025,.035],hair,-.035,.104,-.066,head);lock.rotation.z=-.25;}
 // Two articulated legs. Seated thighs face the machine; feet rest near the pedal.
 for(const side of [-1,1]){const x=side*.083,knee=seated?[x,.43,-.29]:[x,.47,.012],ankle=seated?[x,.105,-.28]:[x,.105,-.012];limb([x,hip-.02,0],knee,.078,.064,pants);oval([.065,.064,.062],pants,...knee);limb(knee,ankle,.061,.043,pants);limb([ankle[0],.1,ankle[2]],[ankle[0],.075,ankle[2]],.043,.041,pants);oval([.063,.045,.122],shoe,ankle[0],.052,ankle[2]-.055);box([.122,.019,.205],sole,ankle[0],.022,ankle[2]-.05);box([.071,.009,.028],trim,ankle[0],.086,ankle[2]-.067);}
 // Sleeves, exposed forearms, palms and individual fingers have distinct silhouettes.
 for(const side of [-1,1]){const shoulderPoint=[side*.18,shoulder-.03,0],elbow=seated?[side*.205,hip+.26,-.1]:[side*.225,hip+.22,-.08],wrist=seated?[side*.17,.85,-.37]:[side*.21,type==='cut'?.88:.94,-.29];
  oval([.074,.085,.075],coat,...shoulderPoint);limb(shoulderPoint,elbow,.064,.048,coat);oval([.048,.048,.048],coat,...elbow);const sleeveEnd=[elbow[0]+(wrist[0]-elbow[0])*.33,elbow[1]+(wrist[1]-elbow[1])*.33,elbow[2]+(wrist[2]-elbow[2])*.33];limb(elbow,sleeveEnd,.047,.043,coat);limb(sleeveEnd,wrist,.037,.026,skin);oval([.026,.025,.026],skin,...wrist);oval([.036,.02,.05],skin,wrist[0],wrist[1]-.006,wrist[2]-.035);
  for(let f=0;f<4;f++){const fx=wrist[0]+(f-1.5)*.015,tip=[fx,wrist[1]-.014,wrist[2]-.105+Math.abs(f-1.5)*.006];limb([fx,wrist[1]-.009,wrist[2]-.058],tip,.008,.006,skin);oval([.006,.007,.009],skin,...tip);}
  oval([.014,.014,.027],skin,wrist[0]-side*.039,wrist[1]-.004,wrist[2]-.052);
 }
 const merged=mergeStaticParts(root),scale=[1,.98,1.025,.99,1.015,.985][variant];merged.scale.setScalar(scale);return merged;
}
