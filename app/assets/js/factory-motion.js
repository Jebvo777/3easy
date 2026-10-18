// Small illustrative work cycles. These never generate production events or quantities.
const smooth=t=>{t=Math.max(0,Math.min(1,t));return t*t*(3-2*t);};
const pulse=(t,a,b)=>smooth((t-a)/.08)*(1-smooth((t-b)/.08));
export function workCycle(type,seconds,index=0){
 const duration={sew:7,overlock:7,cut:9,iron:8,hand:8,qc:10,pack:9}[type]||8,t=((seconds+index*.83)%duration)/duration;
 const pass=t<.62?smooth(t/.62):1-smooth((t-.62)/.38),working=pulse(t,.04,.55),travel=(pass-.5),motion={left:[0,0,0],right:[0,0,0],head:[0,0],needle:0,wheel:0,cloth:0,toolX:0,toolZ:0,press:0,phase:t};
 if(type==='sew'||type==='overlock'){
  const guide=.045*travel,reset=.012*pulse(t,.66,.85);motion.left=[-.004,reset,guide];motion.right=[.004,reset,guide];motion.head=[-.015*working,.009*Math.sin(pass*Math.PI)];motion.needle=.013*Math.sin(seconds*Math.PI*16)*working;motion.wheel=(Math.floor((seconds+index*.83)/duration)+smooth(Math.min(t/.62,1)))*25;motion.cloth=guide;
 }else if(type==='iron'){
  motion.right=[.12*travel,.005,-.025*pass];motion.left=[-.007,0,.005];motion.toolX=.12*travel;motion.toolZ=-.025*pass;motion.head=[-.018*pass,.025*travel];
 }else if(type==='cut'){
  motion.right=[.13*travel,.009*(1-working),-.025*pass];motion.left=[-.01,0,0];motion.toolX=.13*travel;motion.toolZ=-.025*pass;motion.head=[-.018*pass,.03*travel];
 }else if(type==='pack'){
  const fold=pulse(t,.1,.32),press=pulse(t,.48,.7);motion.left=[.025*fold,.012*fold,-.018*fold];motion.right=[-.025*fold,.012*fold,-.018*fold];motion.press=press;motion.head=[-.02*fold,0];
 }else if(type==='qc'){
  motion.left=[-.015*pass,.01*pass,0];motion.right=[.015*pass,.01*pass,0];motion.head=[-.025*pass,.055*travel];
 }else{
  motion.left=[.012*travel,.003,-.012*pass];motion.right=[-.012*travel,.003,-.012*pass];motion.head=[-.015*pass,0];
 }
 return motion;
}
