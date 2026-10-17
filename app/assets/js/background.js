let film=null,enabled=false;const reduced=matchMedia('(prefers-reduced-motion: reduce)');
export function setWorkspaceFilm(on){
 enabled=on;const wall=document.querySelector('.wallpaper');if(!wall)return;
 if(on&&!reduced.matches){if(!film){film=document.createElement('video');film.className='workspace-film';film.muted=true;film.loop=true;film.playsInline=true;film.preload='metadata';film.setAttribute('aria-hidden','true');film.setAttribute('disablepictureinpicture','');film.src='assets/media/workspace-loop-20261017-hq.mp4';film.addEventListener('loadeddata',()=>film.classList.add('ready'));wall.append(film);}film.play().catch(()=>{});}else film?.pause();
 wall.classList.toggle('has-workspace-film',on&&!reduced.matches);
}
reduced.addEventListener?.('change',()=>setWorkspaceFilm(enabled));
