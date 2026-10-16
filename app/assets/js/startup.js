(()=>{
 const failed=event=>{
  const loading=document.getElementById('loading');
  if(!loading||loading.classList.contains('hide'))return;
  const script=event.target instanceof HTMLScriptElement;
  if(!script&&!String(event.filename||'').includes('/assets/runtime/'))return;
  loading.remove();
  const app=document.getElementById('app');if(!app)return;
  app.innerHTML='<main class="auth-layout"><section class="auth-card"><h1>Не удалось открыть пространство</h1><p>Обновите страницу после завершения загрузки файлов приложения.</p><button class="btn primary" type="button">Обновить страницу</button></section></main>';
  app.querySelector('button').addEventListener('click',()=>location.reload());
 };
 window.addEventListener('error',failed,true);
})();
