const VERSION='tms-os-v15-3-7';
const STATIC_CACHE=VERSION+'-static';
const STATIC_ASSETS=[
  '/offline.html','/manifest.php?v=15.3.4',
  '/assets/app.css?v=15.3.4',
  '/assets/icons/icon-192.png','/assets/icons/icon-512.png'
];
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(STATIC_CACHE).then(cache=>cache.addAll(STATIC_ASSETS)).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==STATIC_CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch',event=>{
  const req=event.request;
  if(req.method!=='GET') return;
  const url=new URL(req.url);
  if(url.origin!==location.origin) return;
  if(req.mode==='navigate'){
    event.respondWith(fetch(req,{cache:'no-store'}).catch(()=>caches.match('/offline.html')));
    return;
  }
  if(url.pathname.startsWith('/assets/') || url.pathname==='/manifest.webmanifest' || url.pathname==='/manifest.php'){
    if(url.search.includes('v=')){
      event.respondWith(fetch(req).then(res=>{
        const copy=res.clone();
        caches.open(STATIC_CACHE).then(c=>c.put(req,copy)).catch(()=>{});
        return res;
      }).catch(()=>caches.match(req)));
    }else{
      event.respondWith(caches.match(req).then(hit=>hit||fetch(req).then(res=>{
        const copy=res.clone(); caches.open(STATIC_CACHE).then(c=>c.put(req,copy)); return res;
      })));
    }
  }
});
