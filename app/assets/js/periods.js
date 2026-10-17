// Calendar periods follow the enterprise's local date, never UTC slicing.
export function localDay(value=new Date(),zone='Europe/Moscow'){
 const parts=new Intl.DateTimeFormat('en-CA',{timeZone:zone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(value),get=k=>parts.find(p=>p.type===k).value;
 return `${get('year')}-${get('month')}-${get('day')}`;
}
export function periodRange(kind,zone='Europe/Moscow',now=new Date()){
 const day=localDay(now,zone),start=new Date(day+'T12:00:00Z'),end=new Date(start);
 if(kind==='week'){start.setUTCDate(start.getUTCDate()-((start.getUTCDay()+6)%7));end.setTime(start.getTime());end.setUTCDate(end.getUTCDate()+6);}
 if(kind==='month'){start.setUTCDate(1);end.setUTCMonth(end.getUTCMonth()+1,0);}
 return {from:start.toISOString().slice(0,10),to:end.toISOString().slice(0,10)};
}
export function inPeriod(order,range){return !!order.deadline&&order.deadline>=range.from&&order.deadline<=range.to;}
