(()=>{
  const root=document.documentElement;
  const sidebar=document.getElementById('sidebar');
  const overlay=document.querySelector('[data-sidebar-overlay]');

  document.querySelectorAll('[data-menu-toggle]').forEach(button=>button.addEventListener('click',()=>{
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('show');
  }));
  overlay?.addEventListener('click',()=>{
    sidebar?.classList.remove('open');
    overlay.classList.remove('show');
  });

  document.querySelectorAll('[data-theme-toggle]').forEach(button=>button.addEventListener('click',()=>{
    const next=root.dataset.theme==='dark'?'light':'dark';
    root.dataset.theme=next;
    document.cookie=`tms_theme=${next}; path=/; max-age=31536000; SameSite=Lax`;
  }));

  document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',event=>{
    if(!confirm(form.dataset.confirm||'Xác nhận thao tác?')) event.preventDefault();
  }));

  document.querySelectorAll('[data-action-form]').forEach(form=>form.addEventListener('submit',event=>{
    const button=event.submitter;
    if(button instanceof HTMLButtonElement){
      button.disabled=true;
      button.textContent='Đang xử lý...';
    }
  }));

  const openModal=id=>document.getElementById(id)?.classList.add('show');
  const closeModal=modal=>modal?.classList.remove('show');
  document.querySelectorAll('[data-modal-open]').forEach(button=>button.addEventListener('click',()=>openModal(button.dataset.modalOpen)));
  document.querySelectorAll('[data-modal-close]').forEach(button=>button.addEventListener('click',()=>closeModal(button.closest('.modal'))));
  document.querySelectorAll('.modal').forEach(modal=>modal.addEventListener('click',event=>{
    if(event.target===modal) closeModal(modal);
  }));

  document.querySelectorAll('[data-rename]').forEach(button=>button.addEventListener('click',()=>{
    const relative=document.getElementById('rename-relative');
    const name=document.getElementById('rename-name');
    if(relative) relative.value=button.dataset.rename||'';
    if(name) name.value=button.dataset.name||'';
    openModal('rename-modal');
  }));

  document.querySelectorAll('[data-file-picker]').forEach(input=>input.addEventListener('change',()=>{
    const text=input.closest('label')?.querySelector('[data-file-picker-text]');
    if(text && input.files?.[0]) text.textContent=input.files[0].name;
  }));

  const sheet=document.getElementById('file-action-sheet');
  const sheetTitle=document.getElementById('action-sheet-title');
  const sheetIcon=document.getElementById('action-sheet-icon');
  const renameButton=document.getElementById('sheet-rename');
  const downloadLink=document.getElementById('sheet-download');
  const archiveForm=document.getElementById('sheet-archive-form');
  const extractForm=document.getElementById('sheet-extract-form');
  const deleteForm=document.getElementById('sheet-delete-form');
  const chmodForm=document.getElementById('sheet-chmod-form');

  const setRelative=(form,value)=>{
    const input=form?.querySelector('input[name="relative"]');
    if(input) input.value=value;
  };
  const closeSheet=()=>{
    sheet?.classList.remove('show');
    sheet?.setAttribute('aria-hidden','true');
    document.body.classList.remove('sheet-open');
  };
  const openSheet=button=>{
    if(!sheet) return;
    const name=button.dataset.name||'Tệp';
    const relative=button.dataset.relative||'';
    const isDir=button.dataset.isDir==='1';
    const isZip=button.dataset.isZip==='1';
    const download=button.dataset.download||'';

    if(sheetTitle) sheetTitle.textContent=name;
    if(sheetIcon) sheetIcon.textContent=isDir?'📁':(isZip?'🗜️':'📄');
    setRelative(archiveForm,relative);
    setRelative(extractForm,relative);
    setRelative(deleteForm,relative);
    setRelative(chmodForm,relative);
    setRelativeAll(relative);

    if(renameButton){
      renameButton.dataset.relative=relative;
      renameButton.dataset.name=name;
    }
    if(downloadLink){
      downloadLink.href=download||'#';
      downloadLink.classList.toggle('sheet-hidden',isDir||!download);
    }
    extractForm?.classList.toggle('sheet-hidden',!isZip);
    if(deleteForm) deleteForm.dataset.confirm=`Xóa ${name}?`;

    sheet.classList.add('show');
    sheet.setAttribute('aria-hidden','false');
    document.body.classList.add('sheet-open');
  };

  document.querySelectorAll('[data-file-actions]').forEach(button=>button.addEventListener('click',()=>openSheet(button)));
  document.querySelectorAll('[data-action-sheet-close]').forEach(button=>button.addEventListener('click',closeSheet));
  const chmodApplyForm=document.getElementById('chmod-apply-form');
  const chmodInput=chmodApplyForm?.querySelector('[data-chmod-input]');
  const chmodRecursiveCb=chmodApplyForm?.querySelector('[data-chmod-recursive-cb]');
  const chmodRecursiveInput=chmodApplyForm?.querySelector('[data-chmod-recursive]');
  const chmodTargetName=chmodApplyForm?.querySelector('[data-chmod-target-name]');
  const copyForm=document.getElementById('copy-apply-form');
  const moveForm=document.getElementById('move-apply-form');
  const sheetCopyForm=document.getElementById('sheet-copy-form');
  const sheetMoveForm=document.getElementById('sheet-move-form');

  const setRelative=(form,value)=>{const input=form?.querySelector('input[name="relative"]');if(input)input.value=value;};
  const setRelativeAll=(relative)=>{setRelative(sheetCopyForm,relative);setRelative(sheetMoveForm,relative);};

  document.querySelector('[data-chmod-open]')?.addEventListener('click',()=>{
    const name=document.querySelector('[data-file-actions].sheet-active')?.dataset.name||sheetTitle?.textContent||'Tệp';
    if(chmodApplyForm){
      setRelative(chmodApplyForm,chmodApplyForm.querySelector('[data-chmod-relative]').value);
      if(chmodTargetName) chmodTargetName.textContent='Mục: '+name;
    }
    closeSheet();
    openModal('chmod-modal');
  });
  chmodApplyForm?.querySelectorAll('[data-chmod-preset]').forEach(button=>button.addEventListener('click',()=>{
    if(chmodInput) chmodInput.value=button.dataset.chmodPreset;
  }));
  chmodRecursiveCb?.addEventListener('change',()=>{if(chmodRecursiveInput)chmodRecursiveInput.value=chmodRecursiveCb.checked?'1':'0';});

  const openCopyMove=(mode)=>{
    const button=document.querySelector('[data-file-actions].sheet-active');
    const relative=button?.dataset.relative||'';
    const targetInput=document.getElementById(mode==='copy'?'copy-apply-form':'move-apply-form')?.querySelector(`[data-${mode}-target-input]`);
    const targetDisplay=document.getElementById(mode==='copy'?'copy-apply-form':'move-apply-form')?.querySelector(`[data-${mode}-target-display]`);
    const applyForm=document.getElementById(mode+'-apply-form');
    const relativeInput=applyForm?.querySelector(`[data-${mode}-relative]`);
    if(relativeInput) relativeInput.value=relative;
    const currentPath=new URL(location).searchParams.get('path')||'';
    if(targetInput) targetInput.value=currentPath;
    if(targetDisplay) targetDisplay.textContent=currentPath==='⌂'?'thư mục gốc':(currentPath||'thư mục gốc');
    closeSheet();
    openModal(mode+'-modal');
  };
  document.querySelector('[data-copy-open]')?.addEventListener('click',()=>openCopyMove('copy'));
  document.querySelector('[data-move-open]')?.addEventListener('click',()=>openCopyMove('move'));

  document.querySelector('[data-use-here]')?.addEventListener('click',()=>{
    const targetPath=new URL(location).searchParams.get('path')||'';
    if(copyForm){const inp=copyForm.querySelector('[data-copy-target-input]');if(inp)inp.value=targetPath;const disp=copyForm.querySelector('[data-copy-target-display]');if(disp)disp.textContent=targetPath==='⌂'?'thư mục gốc':(targetPath||'thư mục gốc');openModal('copy-modal');}
    if(moveForm){const inp=moveForm.querySelector('[data-move-target-input]');if(inp)inp.value=targetPath;const disp=moveForm.querySelector('[data-move-target-display]');if(disp)disp.textContent=targetPath==='⌂'?'thư mục gốc':(targetPath||'thư mục gốc');openModal('move-modal');}
  });

  document.querySelectorAll('[data-copy-open],[data-move-open],[data-chmod-open]').forEach(button=>button.addEventListener('click',()=>{
    document.querySelectorAll('[data-file-actions]').forEach(b=>b.classList.remove('sheet-active'));
    button.closest('.explorer-item')?.querySelector('[data-file-actions]')?.classList.add('sheet-active');
  }));

  document.querySelectorAll('[data-file-actions]').forEach(button=>button.addEventListener('click',()=>{
    const rel=button.dataset.relative||'';
    const applyC=copyForm?.querySelector('[data-copy-relative]');if(applyC)applyC.value=rel;
    const applyM=moveForm?.querySelector('[data-move-relative]');if(applyM)applyM.value=rel;
  }));

  renameButton?.addEventListener('click',()=>{
    const relative=document.getElementById('rename-relative');
    const name=document.getElementById('rename-name');
    if(relative) relative.value=renameButton.dataset.relative||'';
    if(name) name.value=renameButton.dataset.name||'';
    closeSheet();
    openModal('rename-modal');
  });

  document.addEventListener('keydown',event=>{
    if(event.key==='Escape'){
      closeSheet();
      document.querySelectorAll('.modal.show').forEach(closeModal);
    }
  });
})();

// TMS OS 4.0 copy helpers
(()=>{let toast=document.querySelector('.copy-toast');if(!toast){toast=document.createElement('div');toast.className='copy-toast';toast.textContent='Đã sao chép';document.body.appendChild(toast);}document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{const value=btn.getAttribute('data-copy')||'';try{await navigator.clipboard.writeText(value);}catch(e){const area=document.createElement('textarea');area.value=value;document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();}toast.classList.add('show');setTimeout(()=>toast.classList.remove('show'),1400);}));})();

// ===== TMS OS 6.0 PWA =====
let tmsDeferredInstallPrompt = null;
const installButtons = document.querySelectorAll('[data-pwa-install]');
window.addEventListener('beforeinstallprompt', (event) => {
  event.preventDefault();
  tmsDeferredInstallPrompt = event;
  installButtons.forEach(button => button.classList.remove('pwa-install-hidden'));
});
installButtons.forEach(button => button.addEventListener('click', async () => {
  if (!tmsDeferredInstallPrompt) return;
  tmsDeferredInstallPrompt.prompt();
  await tmsDeferredInstallPrompt.userChoice;
  tmsDeferredInstallPrompt = null;
  installButtons.forEach(item => item.classList.add('pwa-install-hidden'));
}));
window.addEventListener('appinstalled', () => installButtons.forEach(button => button.classList.add('pwa-install-hidden')));

if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const registration = await navigator.serviceWorker.register('/service-worker.js', {scope: '/'});
      registration.addEventListener('updatefound', () => {
        const worker = registration.installing;
        if (!worker) return;
        worker.addEventListener('statechange', () => {
          if (worker.state === 'installed' && navigator.serviceWorker.controller) {
            const banner = document.querySelector('[data-pwa-update]');
            if (banner) banner.hidden = false;
          }
        });
      });
    } catch (error) {
      console.warn('Không thể đăng ký PWA:', error);
    }
  });
}
document.querySelectorAll('[data-pwa-reload]').forEach(button => button.addEventListener('click', () => location.reload()));

document.querySelectorAll('[data-dialog-open]').forEach(button => {
  button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.dialogOpen);
    if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
  });
});
document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));

// ===== TMS OS Platform =====
document.querySelectorAll('[data-app-select]').forEach(button => button.addEventListener('click', () => {
  const dialog=document.getElementById('install-app-dialog');
  const app=document.getElementById('selected-app');
  const fields=document.getElementById('database-fields');
  if(app) app.value=button.dataset.appSelect||'';
  if(fields) fields.hidden=button.dataset.appDb!=='1';
  if(dialog?.showModal) dialog.showModal();
}));

function drawMonitorChart(canvas, rows) {
  if (!canvas || !Array.isArray(rows) || rows.length < 1) return;
  const ratio=window.devicePixelRatio||1, width=canvas.clientWidth||600, height=220;
  canvas.width=width*ratio;canvas.height=height*ratio;
  const ctx=canvas.getContext('2d');ctx.scale(ratio,ratio);ctx.clearRect(0,0,width,height);
  const pad=26, w=width-pad*2,h=height-pad*2;
  ctx.strokeStyle='rgba(148,163,184,.25)';ctx.lineWidth=1;
  for(let i=0;i<=4;i++){const y=pad+h*i/4;ctx.beginPath();ctx.moveTo(pad,y);ctx.lineTo(width-pad,y);ctx.stroke();}
  ctx.strokeStyle='#315ee8';ctx.lineWidth=2;ctx.beginPath();
  rows.forEach((row,i)=>{const x=pad+(rows.length===1?0:w*i/(rows.length-1));const y=pad+h-(Math.max(0,Math.min(100,Number(row.memory)||0))/100*h);i?ctx.lineTo(x,y):ctx.moveTo(x,y);});
  ctx.stroke();
}
const monitorCanvas=document.getElementById('monitor-chart');
if(monitorCanvas){
  let rows=[];try{rows=JSON.parse(monitorCanvas.dataset.history||'[]')}catch{}
  drawMonitorChart(monitorCanvas,rows);
  setInterval(async()=>{try{
    const r=await fetch('/api/monitoring',{cache:'no-store'});if(!r.ok)return;const d=await r.json();
    ['memory','storage','load'].forEach(k=>{const el=document.querySelector(`[data-monitor-value="${k}"]`);if(el)el.textContent=k==='load'?d.current[k]:`${d.current[k]}%`;const bar=document.querySelector(`[data-monitor-bar="${k}"]`);if(bar)bar.style.width=`${d.current[k]}%`;});
    drawMonitorChart(monitorCanvas,d.history||[]);
  }catch{}},30000);
  window.addEventListener('resize',()=>drawMonitorChart(monitorCanvas,rows));
}

const notificationEnable=document.querySelector('[data-notification-enable]');
const notificationTest=document.querySelector('[data-notification-test]');
const permissionText=document.getElementById('notification-permission-text');
function updatePermissionText(){if(permissionText)permissionText.textContent=('Notification'in window)?`Trạng thái: ${Notification.permission}`:'Trình duyệt không hỗ trợ Notification API.'}
updatePermissionText();
notificationEnable?.addEventListener('click',async()=>{if('Notification'in window)await Notification.requestPermission();updatePermissionText();});
notificationTest?.addEventListener('click',()=>{if(Notification.permission==='granted')new Notification('TMS OS',{body:'Thông báo PWA đang hoạt động.',icon:'/assets/icons/icon-192.png'});});
if(document.querySelector('[data-service-alert]')){
  let previous=null;
  setInterval(async()=>{try{
    const r=await fetch('/api/notifications/status',{cache:'no-store'});if(!r.ok)return;const d=await r.json();
    if(previous&&Notification.permission==='granted'){
      Object.entries(d.services||{}).forEach(([name,running])=>{if(previous[name]===true&&running===false)new Notification('Dịch vụ đã dừng',{body:`${name} không còn chạy.`,icon:'/assets/icons/icon-192.png'});});
    }
    previous=d.services||{};
  }catch{}},60000);
}

// In-page splash removed: navigation between modules is now immediate.

// ===== TMS OS Platform Stable V9 · Cloudflare Tunnel V2 =====
(()=>{
  const endpoint=window.TMS_CLOUDFLARE_STATUS_URL;
  if(!endpoint) return;
  const pill=document.getElementById('cf-status-pill');
  const box=document.getElementById('cf-state-box');
  const icon=document.getElementById('cf-state-icon');
  const title=document.getElementById('cf-state-title');
  const message=document.getElementById('cf-state-message');
  const urlCard=document.getElementById('cf-url-card');
  const publicUrl=document.getElementById('cf-public-url');
  const openUrl=document.getElementById('cf-open-url');
  const copyUrl=document.getElementById('cf-copy-url');
  const log=document.getElementById('cf-log');
  const liveDot=document.getElementById('cf-live-dot');
  const refresh=document.getElementById('cf-refresh');
  const processState=document.getElementById('cf-process-state');
  const edgeState=document.getElementById('cf-edge-state');
  const providerState=document.getElementById('cf-provider');
  const httpCode=document.getElementById('cf-http-code');
  const labels={stopped:'Đã dừng',starting:'Đang khởi động',connecting:'Đang kết nối',verifying:'Đang xác minh',connected:'Đã kết nối',timeout:'Hết thời gian',error:'Có lỗi'};
  let timer=null;

  const updateCopy=value=>{
    if(!copyUrl) return;
    copyUrl.dataset.copy=value||'';
    copyUrl.onclick=async()=>{
      if(!value) return;
      try{await navigator.clipboard.writeText(value);}catch(e){const area=document.createElement('textarea');area.value=value;document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();}
      const old=copyUrl.textContent;copyUrl.textContent='Đã sao chép';setTimeout(()=>copyUrl.textContent=old,1200);
    };
  };

  const render=data=>{
    const state=data.status||'stopped';
    const active=['starting','connecting','verifying','connected'].includes(state)&&data.running;
    if(pill){pill.textContent=labels[state]||state;pill.classList.toggle('running',active);pill.classList.toggle('stopped',!active);}
    if(liveDot) liveDot.classList.toggle('active',active);
    if(box){box.className=`cf-state-box state-${state}`;}
    if(icon) icon.textContent=state==='connected'?'✓':(['error','timeout'].includes(state)?'!':'…');
    if(title) title.textContent=labels[state]||state;
    if(message) message.textContent=data.message||'';
    if(processState) processState.textContent=data.running?'Đang chạy':'Đã dừng';
    if(edgeState) edgeState.textContent=data.registered?'Đã đăng ký':'Chưa đăng ký';
    if(providerState) providerState.textContent=data.provider_label||'Chưa chọn';
    if(httpCode) httpCode.textContent=data.http_code||'—';
    if(log){log.textContent=data.log||'Chưa có nhật ký.';log.scrollTop=log.scrollHeight;}
    const url=data.url||'';
    if(urlCard) urlCard.hidden=!url;
    if(publicUrl){publicUrl.textContent=url;publicUrl.href=url||'#';}
    if(openUrl) openUrl.href=url||'#';
    updateCopy(url);
    if(['connected','stopped'].includes(state)&&timer){clearInterval(timer);timer=null;}
    if(['error','timeout'].includes(state)&&!data.running&&timer){clearInterval(timer);timer=null;}
  };

  const poll=async()=>{
    try{const response=await fetch(endpoint,{cache:'no-store',headers:{Accept:'application/json'}});if(!response.ok) return;render(await response.json());}catch(e){}
  };
  refresh?.addEventListener('click',poll);
  updateCopy(copyUrl?.dataset.copy||'');
  timer=setInterval(poll,2500);
  poll();
})();

// ===== TMS OS Platform Stable V10 · Appearance Center =====
(()=>{
  const form=document.getElementById('appearance-form');
  if(!form) return;
  const primary=form.querySelector('[data-accent]');
  const secondary=form.querySelector('[data-accent-secondary]');
  const background=form.querySelector('[data-pwa-background]');
  const sync=()=>{
    if(primary){document.documentElement.style.setProperty('--primary',primary.value);primary.closest('.color-input')?.querySelector('code')?.replaceChildren(primary.value);}
    if(secondary){document.documentElement.style.setProperty('--primary2',secondary.value);secondary.closest('.color-input')?.querySelector('code')?.replaceChildren(secondary.value);}
    if(background) background.closest('.color-input')?.querySelector('code')?.replaceChildren(background.value);
    const meta=document.querySelector('meta[name="theme-color"]');if(meta&&primary)meta.setAttribute('content',primary.value);
  };
  [primary,secondary,background].forEach(input=>input?.addEventListener('input',sync));
  document.querySelectorAll('[data-color-presets] [data-primary]').forEach(btn=>btn.addEventListener('click',()=>{if(primary)primary.value=btn.dataset.primary;if(secondary)secondary.value=btn.dataset.secondary;sync();}));
  document.querySelector('[data-reset-colors]')?.addEventListener('click',()=>{if(primary)primary.value='#315ee8';if(secondary)secondary.value='#6b4dea';if(background)background.value='#f2f5fb';sync();});
  sync();
})();
(()=>{const toggle=document.querySelector('[data-wp-auto]');const fields=document.querySelector('[data-wp-fields]');if(!toggle||!fields)return;const sync=()=>{fields.hidden=!toggle.checked};toggle.addEventListener('change',sync);sync();})();

// ===== TMS OS Platform V10.2.1 =====
(()=>{
  // Không hiển thị splash khi chuyển trang; điều hướng server-side diễn ra trực tiếp.
  const scope=document.querySelector('[data-backup-scope]');
  const website=document.querySelector('[data-backup-website]');
  const sync=()=>{if(website)website.hidden=scope?.value!=='website'};
  scope?.addEventListener('change',sync);sync();
})();

// TMS OS V12 Guardian live status
(() => {
  const root = document.querySelector('[data-guardian-root]');
  if (!root) return;
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const render = (data) => {
    document.querySelectorAll('[data-guardian-value]').forEach(el => { const k=el.dataset.guardianValue; el.textContent=data.status?.[k] ?? '—'; });
    document.querySelectorAll('[data-guardian-bool]').forEach(el => { const k=el.dataset.guardianBool; el.textContent=data.status?.[k] ? 'Khỏe' : 'Lỗi'; });
    const running=document.querySelector('[data-guardian-running]'); if(running){running.textContent=data.running?'Guardian đang chạy':'Guardian đã dừng';running.classList.toggle('running',!!data.running);}
    const count=document.querySelector('[data-guardian-repair-count]'); if(count)count.textContent=data.repair_count_hour ?? 0;
    const updated=document.querySelector('[data-guardian-updated]'); if(updated)updated.textContent='Cập nhật: '+(data.status?.updated_at || 'Chưa có');
    const box=document.querySelector('[data-guardian-events]'); if(box&&Array.isArray(data.events)) box.innerHTML=data.events.length?data.events.map(e=>`<div class="guardian-event guardian-${esc(e.level||'info')}"><span>${esc(new Date(e.time).toLocaleString('vi-VN',{hour:'2-digit',minute:'2-digit',second:'2-digit',day:'2-digit',month:'2-digit'}))}</span><strong>${esc(String(e.service||'').toUpperCase())}</strong><p>${esc(e.message||'')}</p></div>`).join(''):'<p class="muted">Chưa có sự kiện Guardian.</p>';
  };
  const refresh=()=>fetch('/api/guardian',{cache:'no-store'}).then(r=>r.ok?r.json():Promise.reject()).then(render).catch(()=>{});
  setInterval(refresh,15000);
})();

// ===== TMS OS V13.0.1 LAN Address Stability =====
(()=>{
  const grid=document.querySelector('[data-service-grid]');
  if(!grid)return;
  const refreshButton=document.querySelector('[data-service-refresh]');
  const dialog=document.getElementById('service-log-dialog');
  const logTitle=document.getElementById('service-log-title');
  const logOutput=document.querySelector('[data-service-log-output]');
  const logLines=document.querySelector('[data-log-lines]');
  const pauseButton=document.querySelector('[data-log-pause]');
  let currentLog='',paused=false,logTimer=null;
  const setText=(selector,value)=>{const el=document.querySelector(selector);if(el)el.textContent=String(value)};
  const update=async()=>{try{const r=await fetch('/api/services',{cache:'no-store'});if(!r.ok)return;const d=await r.json();setText('[data-service-total]',d.summary.total);setText('[data-service-installed]',d.summary.installed);setText('[data-service-running]',d.summary.running);setText('[data-service-stopped]',d.summary.stopped);(d.services||[]).forEach(s=>{const card=document.querySelector(`[data-service-card="${CSS.escape(s.id)}"]`);if(!card)return;const status=card.querySelector('[data-service-status]');if(status){status.textContent=s.health.label;status.classList.toggle('running',!!s.running);status.classList.toggle('pending',!!s.pending)};const map={pid:s.pid||'—',memory:`${s.metrics.memory_mb} MB`,threads:s.metrics.threads,uptime:s.metrics.uptime,health:s.health.message};Object.entries(map).forEach(([k,v])=>{const el=card.querySelector(`[data-service-${k}]`);if(el)el.textContent=String(v)})})}catch(e){console.warn('Service status update failed',e)}};
  refreshButton?.addEventListener('click',async()=>{refreshButton.disabled=true;await update();refreshButton.disabled=false});
  setInterval(update,15000);
  const loadLog=async()=>{if(!currentLog||paused)return;try{const r=await fetch(`/services/log?id=${encodeURIComponent(currentLog)}&lines=${encodeURIComponent(logLines?.value||150)}`,{cache:'no-store'});if(r.ok&&logOutput){logOutput.textContent=await r.text();logOutput.scrollTop=logOutput.scrollHeight}}catch{}};
  document.querySelectorAll('[data-service-log]').forEach(button=>button.addEventListener('click',()=>{currentLog=button.dataset.serviceLog||'';paused=false;if(pauseButton)pauseButton.textContent='Tạm dừng';if(logTitle)logTitle.textContent=`Live Log · ${button.dataset.serviceName||currentLog}`;if(dialog?.showModal)dialog.showModal();loadLog();clearInterval(logTimer);logTimer=setInterval(loadLog,3000)}));
  document.querySelector('[data-log-refresh]')?.addEventListener('click',loadLog);
  pauseButton?.addEventListener('click',()=>{paused=!paused;pauseButton.textContent=paused?'Tiếp tục':'Tạm dừng';if(!paused)loadLog()});
  logLines?.addEventListener('change',loadLog);
  dialog?.addEventListener('close',()=>{clearInterval(logTimer);currentLog=''});
})();
