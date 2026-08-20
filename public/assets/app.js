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

  document.querySelectorAll('[data-ios-instructions]').forEach(button=>button.addEventListener('click',()=>{
    const steps=document.getElementById('ios-instructions');
    if(steps)steps.hidden=!steps.hidden;
  }));

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

  const brandInput=document.querySelector('#brand-form input[name=logo]');
  const brandApply=document.getElementById('brand-apply');
  const brandPreview=document.getElementById('brand-preview-img');
  brandInput?.addEventListener('change',()=>{
    const file=brandInput.files?.[0];
    if(brandApply) brandApply.disabled=!file;
    if(!file||!brandPreview) return;
    if(file.size>2097152){brandApply.disabled=true;return;}
    const url=URL.createObjectURL(file);
    brandPreview.src=url;
  });
  document.querySelectorAll('[data-reset-brand]').forEach(button=>button.addEventListener('click',()=>{
    if(!confirm('Khôi phục logo TMS mặc định?')) return;
    const img=document.getElementById('brand-preview-img');
    if(img) img.src='/assets/icons/icon-192.png?v=1';
    if(brandApply) brandApply.disabled=true;
    if(brandInput) brandInput.value='';
  }));
  document.querySelectorAll('[data-clear-cache]').forEach(button=>button.addEventListener('click',()=>{
    if(!confirm('Xóa cache ngay? Các session cũ và tệp tạm sẽ bị xóa, giao diện sẽ tải lại dữ liệu mới. Phiên đăng nhập hiện tại vẫn được giữ.')) return;
    button.closest('form')?.submit();
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

// ===== TMS OS V15 · Cloudflare Hosting (tên miền riêng chính chủ) =====
(()=>{
  const endpoint=window.TMS_CF_DOMAIN_STATUS_URL;
  if(!endpoint) return;
  const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const $=id=>document.getElementById(id);
  const alertBox=$('cfd-alert');
  const showAlert=msg=>{if(!alertBox)return;alertBox.textContent=msg||'';alertBox.hidden=!msg;};
  const form=(id)=>document.getElementById(id);
  const csrf=()=>document.querySelector('#cfd-token-form input[name=csrf],#cfd-attach-form input[name=csrf],form[data-action-form] input[name=csrf]')?.value||'';

  const post=async(path,body)=>{
    const fd=new URLSearchParams();Object.entries(body).forEach(([k,v])=>fd.append(k,String(v)));
    const r=await fetch(path,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:fd.toString(),cache:'no-store'});
    return await r.json();
  };

  // ===== Trạng thái tab =====
  const pill=$('cfd-status-pill');
  const tunnelName=$('cfd-tunnel-name');
  const tunnelStatus=$('cfd-tunnel-status');
  const tunnelConns=$('cfd-tunnel-conns');
  const tunnelRunning=$('cfd-tunnel-running');
  const urlCard=$('cfd-url-card');
  const publicUrl=$('cfd-public-url');
  const openUrl=$('cfd-open-url');
  const copyUrl=$('cfd-copy-url');
  const logEl=$('cfd-log');
  const accountIdEl=$('cfd-account-id');
  const zonesCountEl=$('cfd-zones-count');
  const accountBox=$('cfd-account-box');
  const zoneSelect=$('cfd-zone');
  const targetSelect=$('cfd-target');
  const runningDot=document.querySelector('#cfd-running-dot');
  const zoneInputs=document.querySelectorAll('[data-cf-zone-id]');

  const labels={unconfigured:'Chưa cấu hình',healthy:'Đang hoạt động',degraded:'Suy giảm',inactive:'Chưa kích hoạt',unknown:'Không xác định'};

  // ===== Nạp danh sách website nội bộ vào dropdown Bước 3 =====
  let internalSites=[];
  const loadInternalSites=async()=>{
    try{
      const r=await fetch('/api/cloudflare-domain/internal-sites',{cache:'no-store',headers:{Accept:'application/json'}});
      const d=await r.json();
      internalSites=Array.isArray(d?.sites)?d.sites:[];
    }catch(e){internalSites=[];}
    const render=()=>{
      if(!targetSelect) return;
      targetSelect.innerHTML='<option value="">— chọn website nội bộ —</option>'+internalSites.map(s=>`<option value="http://127.0.0.1:${Number(s.port)}">${esc(s.name)} · cổng ${Number(s.port)}${s.status==='running'?'':` (${s.status})`}</option>`).join('');
    };
    render();
    // Refresh lại danh sách mỗi khi mở lại tab Cloudflare Hosting
    const observer=new MutationObserver(()=>setTimeout(render,50));
    document.querySelectorAll('.panel-card[data-panel=hosting]').forEach(p=>observer.observe(p,{attributes:true,attributeFilter:['hidden']}));
  };
  loadInternalSites();

  // Auto-restart: Android hay kill cloudflared ngầm (tiết kiệm pin) khiến tunnel mất kết nối
  // (Cloudflare Error 1033). Khi đã cấu hình đủ hostname + tunnel mà cloudflared bị tắt,
  // tự khởi động lại đúng 1 lần mỗi 60 giây.
  let lastAutoRestartAt = 0;
  const autoRestart = async d => {
    if (!(d.configured && d.tunnel_id && d.hostname && !d.running)) return;
    const now = Date.now();
    if (now - lastAutoRestartAt < 60000) return;
    lastAutoRestartAt = now;
    try {
      const res = await post('/api/cloudflare-domain/start', {csrf: typeof csrf === 'function' ? csrf() : ''});
      if (res && res.running) {
        showAlert('Tunnel bị tắt ngầm bởi hệ thống — đã tự khởi động lại.');
        setTimeout(renderStatus, 1500);
      }
    } catch (e) { console.warn('autoRestart failed', e); }
  };

  const renderStatus=async()=>{
    try{
      const r=await fetch(endpoint,{cache:'no-store',headers:{Accept:'application/json'}});
      if(!r.ok) return;
      const d=await r.json();
      const healthy=!!d.running&&d.health?.status==='healthy';
      await autoRestart(d);
      const anyConfig=!!d.configured||!!d.tunnel_id;
      if(pill){pill.textContent=healthy?'Đang hoạt động':(d.configured?'Đã cấu hình':'Chưa cấu hình');pill.classList.toggle('running',healthy);pill.classList.toggle('stopped',!healthy);}
      if(tunnelName) tunnelName.textContent=d.tunnel_name||'Chưa tạo';
      if(tunnelStatus) tunnelStatus.textContent=labels[d.health?.status]||d.health?.status||'—';
      if(tunnelConns) tunnelConns.textContent=String(d.health?.connections??'—');
      if(tunnelRunning) tunnelRunning.textContent=d.running?'Đang chạy':'Đã dừng';
      if(runningDot) runningDot.classList.toggle('active',!!d.running);
      if(accountIdEl){accountIdEl.textContent=d.account_id||'—';if(accountBox)accountBox.hidden=!d.account_id;}
      const zones=d.zones||[];
      if(zones.length){
        if(zonesCountEl) zonesCountEl.textContent=`${zones.length} domain`;
        if(zoneSelect&&zoneSelect.options.length<=1){
          zoneSelect.innerHTML='<option value="">— chọn domain —</option>'+zones.map(z=>`<option value="${esc(z.id)}">${esc(z.name)}</option>`).join('');
        }
      }
      const url=d.url||'';
      if(urlCard) urlCard.hidden=!url;
      if(publicUrl){publicUrl.textContent=url;publicUrl.href=url||'#';}
      if(openUrl) openUrl.href=url||'#';
      if(copyUrl){copyUrl.dataset.copy=url||'';copyUrl.onclick=async()=>{try{await navigator.clipboard.writeText(url);}catch(e){}const old=copyUrl.textContent;copyUrl.textContent='Đã sao chép';setTimeout(()=>copyUrl.textContent=old,1200);};}
      // Remote Access (V15.2.0): hiển thị panel từ xa nếu đã bật
      const panelUrl=d.panel_url||'';
      const remoteCard=document.getElementById('cfd-remote-url-card');
      const remoteUrlEl=document.getElementById('cfd-remote-url');
      const remoteOpen=document.getElementById('cfd-remote-open');
      const remoteCopy=document.getElementById('cfd-remote-copy');
      const remoteDot=document.getElementById('cfd-remote-dot');
      const panelInput=document.getElementById('cfd-panel-hostname');
      if(remoteCard) remoteCard.hidden=!panelUrl;
      if(remoteUrlEl){remoteUrlEl.textContent=panelUrl;remoteUrlEl.href=panelUrl||'#';}
      if(remoteOpen) remoteOpen.href=panelUrl||'#';
      if(remoteCopy){remoteCopy.dataset.copy=panelUrl||'';remoteCopy.onclick=async()=>{try{await navigator.clipboard.writeText(panelUrl);}catch(e){}const old=remoteCopy.textContent;remoteCopy.textContent='Đã sao chép';setTimeout(()=>remoteCopy.textContent=old,1200);};}
      if(remoteDot) remoteDot.classList.toggle('active',!!panelUrl);
      // Tự điền hostname panel mặc định = panel. + domain gốc đã chọn
      if(panelInput&&d.hostname&&!panelInput.dataset.defaulted){
        panelInput.dataset.defaulted='1';
        panelInput.addEventListener('focus',()=>{
          if(!panelInput.value.trim()){panelInput.value=d.hostname?'panel.'+d.hostname:'';}
        });
        zoneSelect?.addEventListener('change',()=>{
          const opt=zoneSelect.selectedOptions?.[0];
          if(opt?.value&&panelInput.dataset.defaulted&&!panelInput.value.trim()) panelInput.value=opt.textContent.trim()?'panel.'+opt.textContent.trim():'';
        });
      }
      if(logEl){logEl.textContent=d.log||'Chưa có nhật ký.';logEl.scrollTop=logEl.scrollHeight;}
    }catch(e){}
  };

  // ===== Bước 1: Token =====
  const tokenForm=form('cfd-token-form');
  tokenForm?.addEventListener('submit',async ev=>{
    ev.preventDefault();showAlert('');
    const token=tokenForm.querySelector('#cfd-api-token').value.trim();
    if(token.length<20){showAlert('API Token không hợp lệ.');return;}
    const btn=tokenForm.querySelector('button');btn.disabled=true;btn.textContent='Đang kiểm tra...';
    try{
      const save=await post('/api/cloudflare-domain/token',{csrf:csrf(),api_token:token});
      if(!save.success) throw new Error(save.error||'Không lưu được token.');
      const acc=await fetch('/api/cloudflare-domain/account-info',{cache:'no-store',headers:{Accept:'application/json'}});
      const accData=await acc.json();
      if(!accData.success) throw new Error(accData.error||'Token không có quyền đọc tài khoản.');
      if(accountIdEl) accountIdEl.textContent=accData.account_id||'—';
      if(accountBox) accountBox.hidden=!accData.account_id;
      if(zonesCountEl) zonesCountEl.textContent=`${(accData.zones||[]).length} domain`;
      const zones=accData.zones||[];
      if(zoneSelect){
        zoneSelect.innerHTML='<option value="">— chọn domain —</option>'+zones.map(z=>`<option value="${esc(z.id)}">${esc(z.name)}</option>`).join('');
      }
      // Tên host công khai mặc định = chính domain gốc đã chọn
      const hostnameInput=attachForm?.querySelector('#cfd-hostname');
      if(hostnameInput&&zones.length){
        const picked=zones[0].name;
        if(!hostnameInput.value.trim()) hostnameInput.value=picked;
        if(!hostnameInput.dataset.bound){
          hostnameInput.dataset.bound='1';
          zoneSelect?.addEventListener('change',()=>{
            const opt=zoneSelect.selectedOptions?.[0];
            if(opt&&opt.value&&hostnameInput.value.trim()==='') hostnameInput.value=opt.textContent.trim();
          });
          hostnameInput.addEventListener('focus',()=>{
            // Khi người dùng bấm vào trường hostname, gợi ý domain gốc nếu đang trống
            if(!hostnameInput.value.trim()){const opt=zoneSelect?.selectedOptions?.[0];if(opt?.value) hostnameInput.value=opt.textContent.trim();}
          });
        }
      }
      showAlert('');
      renderStatus();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Kiểm tra & lưu token';
  });

  // ===== Bước 2: Tạo tunnel =====
  const tunnelForm=form('cfd-tunnel-form');
  tunnelForm?.addEventListener('submit',async ev=>{
    ev.preventDefault();showAlert('');
    const btn=tunnelForm.querySelector('button');btn.disabled=true;btn.textContent='Đang tạo tunnel...';
    try{
      const d=await post('/api/cloudflare-domain/create-tunnel',{csrf:csrf()});
      if(!d.success) throw new Error(d.error||'Tạo tunnel thất bại.');
      showAlert(`Đã tạo tunnel "${d.tunnel_name||''}". Tiếp theo: gắn tên miền ở Bước 3.`);
      renderStatus();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Tạo Cloudflare Tunnel mới';
  });

  // ===== Bước 3: Gắn tên miền =====
  const attachForm=form('cfd-attach-form');
  attachForm?.addEventListener('submit',async ev=>{
    ev.preventDefault();showAlert('');
    const hostname=attachForm.querySelector('#cfd-hostname').value.trim().toLowerCase();
      const zoneId=attachForm.querySelector('#cfd-zone').value;
    const target=attachForm.querySelector('#cfd-target').value;
    if(!hostname||!/^[a-z0-9._-]+\.[a-z]{2,}$/.test(hostname)||hostname.length>253){showAlert('Tên host không hợp lệ. Ví dụ: example.com hoặc shop.example.com');return;}
    if(!zoneId){showAlert('Hãy chọn domain.');return;}
    if(!target){showAlert('Hãy chọn website nội bộ.');return;}
    const btn=attachForm.querySelector('button');btn.disabled=true;btn.textContent='Đang gắn tên miền...';
    try{
      const d=await post('/api/cloudflare-domain/attach',{csrf:csrf(),hostname,zone_id:zoneId,target});
      if(!d.success) throw new Error(d.error||'Gắn tên miền thất bại.');
      showAlert('Đã tạo record DNS CNAME. Website sẽ online ngay khi tunnel chạy ở Bước Điều khiển.');
      renderStatus();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Gắn tên miền & tạo record DNS';
  });

  // ===== Điều khiển =====
  $('cfd-start')?.addEventListener('click',async()=>{
    showAlert('');
    const btn=$('cfd-start');btn.disabled=true;btn.textContent='Đang khởi động...';
    try{
      const d=await post('/api/cloudflare-domain/start',{csrf:csrf()});
      if(!d.success) throw new Error(d.error);
      renderStatus();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='▶ Khởi động Tunnel';
  });
  $('cfd-stop')?.addEventListener('click',async()=>{
    showAlert('');
    const btn=$('cfd-stop');btn.disabled=true;btn.textContent='Đang dừng...';
    try{await post('/api/cloudflare-domain/stop',{csrf:csrf()});renderStatus();}catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='■ Dừng Tunnel';
  });
  $('cfd-refresh')?.addEventListener('click',renderStatus);
  $('cfd-detach')?.addEventListener('click',async()=>{
    if(!confirm('Tách tên miền khỏi tunnel? Record DNS sẽ bị xóa nhưng tunnel vẫn giữ nguyên.')) return;
    showAlert('');
    const btn=$('cfd-detach');btn.disabled=true;btn.textContent='Đang tách...';
    try{const d=await post('/api/cloudflare-domain/detach',{csrf:csrf()});showAlert(d.message||'Đã tách tên miền.');renderStatus();}catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Tách tên miền (giữ tunnel)';
  });
  $('cfd-delete-tunnel')?.addEventListener('click',async()=>{
    if(!confirm('Xóa tunnel khỏi tài khoản Cloudflare? Tunnel hiện tại sẽ ngừng ngay.')) return;
    showAlert('');
    const btn=$('cfd-delete-tunnel');btn.disabled=true;btn.textContent='Đang xóa...';
    try{const d=await post('/api/cloudflare-domain/delete-tunnel',{csrf:csrf()});showAlert(d.message||'Đã xóa tunnel.');renderStatus();}catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Xóa Tunnel khỏi Cloudflare';
  });
  $('cfd-uninstall')?.addEventListener('click',async()=>{
    if(!confirm('Xóa TOÀN BỘ cấu hình Cloudflare Hosting? Tunnel sẽ bị xóa khỏi tài khoản và mọi thiết lập sẽ mất.')) return;
    showAlert('');
    const btn=$('cfd-uninstall');btn.disabled=true;btn.textContent='Đang xóa...';
    try{const d=await post('/api/cloudflare-domain/uninstall',{csrf:csrf()});showAlert(d.message||'Đã xóa toàn bộ cấu hình.');renderStatus();}catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Xóa toàn bộ cấu hình Cloudflare';
  });

  // ===== Remote Access (V15.2.0): truy cập panel từ xa =====
  const remoteForm=form('cfd-remote-form');
  remoteForm?.addEventListener('submit',async ev=>{
    ev.preventDefault();showAlert('');
    const panelHostname=(document.getElementById('cfd-panel-hostname')?.value||'').trim().toLowerCase();
    if(!panelHostname){showAlert('Hãy nhập hostname cho panel, ví dụ: panel.thc.io.vn');return;}
    if(!/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?\.[a-z]{2,}$/.test(panelHostname)){showAlert('Hostname không hợp lệ. Ví dụ: panel.thc.io.vn');return;}
    const btn=remoteForm.querySelector('button');btn.disabled=true;btn.textContent='Đang bật...';
    try{
      const d=await post('/api/cloudflare-domain/attach-panel',{csrf:csrf(),panel_hostname:panelHostname});
      if(!d.success) throw new Error(d.error||'Bật truy cập từ xa thất bại.');
      showAlert('Đã bật truy cập từ xa! Bạn có thể vào panel bằng '+d.url+' từ bất kỳ máy nào, không phụ thuộc WiFi/LAN.');
      renderStatus();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='🔗 Bật truy cập từ xa';
  });
  $('cfd-remote-detach')?.addEventListener('click',async()=>{
    if(!confirm('Tắt truy cập panel từ xa? Subdomain panel sẽ ngừng hoạt động (tunnel và website không bị ảnh hưởng).')) return;
    showAlert('');
    const btn=$('cfd-remote-detach');btn.disabled=true;btn.textContent='Đang tắt...';
    try{const d=await post('/api/cloudflare-domain/detach-panel',{csrf:csrf()});showAlert(d.message||'Đã tắt truy cập từ xa.');renderStatus();}catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='Tắt truy cập từ xa';
  });

  // ===== Tối ưu hiệu năng =====
  const perfText=$('cfd-perf-text');
  const renderPerf=async()=>{
    if(!perfText) return;
    try{
      const d=await post('/api/cloudflare-domain/perf-status',{csrf:csrf()});
      perfText.textContent=d.enabled?'Đã bật — gzip + cache tĩnh + OPcache':'Chưa bật';
    }catch(e){perfText.textContent='Chưa kiểm tra';}
  };
  $('cfd-perf-check')?.addEventListener('click',renderPerf);
  $('cfd-perf-apply')?.addEventListener('click',async()=>{
    showAlert('');
    const btn=$('cfd-perf-apply');btn.disabled=true;btn.textContent='Đang tối ưu...';
    try{
      const d=await post('/api/cloudflare-domain/perf-optimize',{csrf:csrf()});
      if(!d.success) throw new Error(d.error||'Tối ưu thất bại.');
      showAlert((d.message||'Đã bật tối ưu hóa hiệu năng.')+' Hãy đợi 2-3 giây rồi tải lại trang.');
      renderPerf();
    }catch(e){showAlert(String(e.message));}
    btn.disabled=false;btn.textContent='⚡ Bật tối ưu hóa hiệu năng';
  });

  renderStatus();
  renderPerf();
  setInterval(renderStatus,15000);
})();

// ===== V15.3.1 SQL Editor (tích hợp vào trang Database) =====
(()=>{
  const path=(window.location.pathname||'').replace(/\/$/,'');
  if(path!=='/databases')return;
  const esc=(v)=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const $=id=>document.getElementById(id);
  const csrfInput=document.querySelector('form[action="/logout"] input[name=csrf]')||document.querySelector('[data-pwa-install]')||null;
  const csrf=()=>csrfInput?.value||'';
  // CSRF qua header X-CSRF-Token (meta tag luôn có trên mọi trang đã đăng nhập, không phụ thuộc cache form)
  const csrfToken=()=>document.querySelector('meta[name=csrf-token]')?.content||'';
  const hdr=({'Content-Type':'application/x-www-form-urlencoded'});
  const post=async(url,body)=>{
    const fd=new URLSearchParams();Object.entries(body).forEach(([k,v])=>{
      if(Array.isArray(v)){v.forEach(x=>fd.append(k,String(x)));}
      else{fd.append(k,String(v));}
    });
    const r=await fetch(url,{method:'POST',headers:{...hdr,'X-CSRF-Token':csrfToken()},body:fd.toString(),cache:'no-store'});
    const d=await r.json();
    if(!r.ok)throw new Error(d.error||'Lỗi máy chủ.');
    return d;
  };
  const get=async url=>{const r=await fetch(url,{cache:'no-store',headers:{Accept:'application/json','X-CSRF-Token':csrfToken()}});return await r.json();};

  // Trạng thái
  let dbKey='';let tables=[];let currentTable='';let dataColumns=[];let dataRows=[];let primaryKey=[];

  const noDb=$('sql-no-db');const panel=$('sql-panel');
  const tabs=document.querySelectorAll('.sql-tab');
  const tabData=$('tab-data');const tabSql=$('tab-sql');const tabStruct=$('tab-structure');
  const tableSelect=$('sql-tables');const structSelect=$('sql-struct-tables');
  const dataWrap=$('sql-data-wrap');const countEl=$('sql-count');const tableEmpty=$('sql-table-empty');
  const input=$('sql-input');const resultWrap=$('sql-result-wrap');const resultMeta=$('sql-result-meta');
  const structWrap=$('sql-struct-wrap');const structEmpty=$('sql-struct-empty');
  const insertModal=$('sql-insert-modal');const insertForm=$('sql-insert-form');

  // Chọn database
  document.querySelectorAll('.sql-db-item').forEach(btn=>btn.addEventListener('click',async()=>{
    document.querySelectorAll('.sql-db-item').forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    dbKey=btn.dataset.db;noDb.hidden=true;panel.hidden=false;
    tableSelect.innerHTML='<option value="">— chọn bảng —</option>';
    structSelect.innerHTML='<option value="">— chọn bảng —</option>';
    currentTable='';dataRows=[];dataColumns=[];primaryKey=[];
    dataWrap.innerHTML='';countEl.textContent='';tableEmpty.hidden=false;
    structWrap.innerHTML='';structEmpty.hidden=true;
    try{
      const d=await get('/api/sql/tables?db_key='+encodeURIComponent(dbKey));
      tables=Array.isArray(d?.tables)?d.tables:[];
      tableSelect.innerHTML='<option value="">— chọn bảng —</option>'+(tables.map(t=>`<option value="${esc(t)}">${esc(t)}</option>`).join(''));
      structSelect.innerHTML=tableSelect.innerHTML;
    }catch(e){resultMeta.innerHTML=`<div class="sql-result-error">${esc(String(e.message))}</div>`;}
  }));

  // Tab switch
  tabs.forEach(t=>t.addEventListener('click',()=>{
    tabs.forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    tabData.hidden=t.dataset.tab!=='data';
    tabSql.hidden=t.dataset.tab!=='sql';
    tabStruct.hidden=t.dataset.tab!=='structure';
  }));

  // Chọn bảng → tải dữ liệu
  const readonlyOn=()=>$('sql-readonly')?.checked||false;
  const loadTableData=async()=>{
    if(!currentTable)return;
    tableEmpty.hidden=true;
    try{
      const d=await post('/api/sql/query',{csrf:csrf(),db_key:dbKey,readonly:readonlyOn()?1:0,sql:`SELECT * FROM "${currentTable}" LIMIT 500`});
      dataColumns=Array.isArray(d?.columns)?d.columns:[];
      dataRows=Array.isArray(d?.rows)?d.rows:[];
      const s=await get('/api/sql/structure?db_key='+encodeURIComponent(dbKey)+'&table='+encodeURIComponent(currentTable));
      primaryKey=Array.isArray(s?.primary_key)?s.primary_key:[];
      renderDataGrid();
      countEl.textContent=`${dataRows.length} dòng hiển thị (tối đa 500) · ${d.time??0}s`;
    }catch(e){dataWrap.innerHTML=`<div class="sql-result-error">${esc(String(e.message))}</div>`;tableEmpty.hidden=false;}
  };
  tableSelect.addEventListener('change',()=>{currentTable=tableSelect.value;loadTableData();});
  $('sql-refresh-btn').addEventListener('click',loadTableData);

  // Render grid dữ liệu
  const renderDataGrid=()=>{
    if(dataColumns.length===0){dataWrap.innerHTML='<div class="sql-empty">Bảng trống hoặc không đọc được.</div>';return;}
    const canEdit=!readonlyOn()&&primaryKey.length>0;
    const ths=dataColumns.map(c=>`<th>${esc(c)}</th>`).join('')+(canEdit?'<th style="width:70px"></th>':'');
    const rows=dataRows.map((row,ri)=>{
      const cells=dataColumns.map(col=>{
        const val=row[col];const display=val===null?'<span class="sql-null">NULL</span>':esc(String(val));
        if(!canEdit)return `<td title="${esc(String(val??''))}">${display}</td>`;
        return `<td class="cell-edit" data-ri="${ri}" data-col="${esc(col)}" title="Bấm để sửa" tabindex="0">${display}</td>`;
      }).join('');
      const pkVals=primaryKey.map(k=>row[k]===null?'NULL':String(row[k]));
      const act=canEdit?`<td class="sql-action"><button type="button" class="del" data-del="${ri}" title="Xóa dòng">✕</button></td>`:'';
      return `<tr>${cells}${act}</tr>`;
    }).join('');
    dataWrap.innerHTML=`<table class="sql-grid"><thead><tr>${ths}</tr></thead><tbody>${rows}</tbody></table>`;
    // Inline edit: click cell → input; blur/Enter → lưu
    dataWrap.querySelectorAll('.cell-edit').forEach(td=>{
      const startEdit=()=>{
        const col=td.dataset.col;const ri=Number(td.dataset.ri);const row=dataRows[ri];
        if(td.querySelector('.sql-cell-input'))return;
        const val=row[col]===null?'':String(row[col]??'');
        td.innerHTML=`<input class="sql-cell-input" value="${esc(val)}" spellcheck="false" autocomplete="off">`;
        const inp=td.querySelector('.sql-cell-input');inp.focus();
        const finish=async(newVal)=>{
          if(newVal===String(val)){td.innerHTML=esc(val);return;}
          td.innerHTML='<span class="sql-null" style="opacity:.6">⏳</span>';
          try{
            await post('/api/sql/save-cell',{csrf:csrf(),db_key:dbKey,table:currentTable,pk_columns:primaryKey,pk_values:primaryKey.map(k=>row[k]===null?'':String(row[k]??'')),column:col,value:newVal});
            dataRows[ri][col]=newVal;renderDataGrid();
          }catch(e){td.innerHTML=esc(val);alert(String(e.message));}
        };
        inp.addEventListener('blur',()=>finish(inp.value));
        inp.addEventListener('keydown',ev=>{if(ev.key==='Enter'){ev.preventDefault();inp.blur();}if(ev.key==='Escape'){inp.value=val;inp.blur();}});
      };
      td.addEventListener('click',startEdit);
      td.addEventListener('keydown',ev=>{if(ev.key==='Enter')startEdit();});
    });
    dataWrap.querySelectorAll('[data-del]').forEach(btn=>btn.addEventListener('click',async()=>{
      const ri=Number(btn.dataset.del);const row=dataRows[ri];
      if(!confirm(`Xóa dòng này của bảng ${currentTable}?`))return;
      try{
        await post('/api/sql/delete-row',{csrf:csrf(),db_key:dbKey,table:currentTable,pk_columns:primaryKey,pk_values:primaryKey.map(k=>row[k]===null?'':String(row[k]??''))});
        dataRows.splice(ri,1);renderDataGrid();countEl.textContent=`${dataRows.length} dòng · đã xóa 1 dòng`;
      }catch(e){alert(String(e.message));}
    }));
  };

  // Thêm dòng
  $('sql-insert-btn').addEventListener('click',async()=>{
    if(!currentTable)return;
    $('sql-insert-table').textContent=currentTable;
    try{
      const s=await get('/api/sql/structure?db_key='+encodeURIComponent(dbKey)+'&table='+encodeURIComponent(currentTable));
      const cols=Array.isArray(s?.columns)?s.columns:[];
      insertForm.innerHTML=cols.map(c=>`<label><span>${esc(c.name)}</span><input name="col__${esc(c.name)}" placeholder="${esc(c.dflt_value??'')}"${c.notnull?' required':''}></label>`).join('')
        +'<div style="display:flex;gap:8px"><button class="btn btn-primary" type="submit">Chèn dòng</button><button class="btn btn-ghost" type="button" data-modal-close>Hủy</button></div>';
      insertForm.onsubmit=async ev=>{
        ev.preventDefault();
        const values={};
        insertForm.querySelectorAll('input[name^="col__"]').forEach(i=>{values[i.name.replace('col__','')]=i.value;});
        try{
          await post('/api/sql/insert-row',{csrf:csrf(),db_key:dbKey,table:currentTable,values});
          insertModal.classList.remove('show');
          await loadTableData();
        }catch(e){alert(String(e.message));}
      };
      insertModal.classList.add('show');
    }catch(e){alert(String(e.message));}
  });

  // Chạy SQL
  const runSql=async()=>{
    const sql=(input?.value||'').trim();if(!sql)return;
    const readOnly=$('sql-readonly2')?.checked||readonlyOn();
    resultWrap.innerHTML='';resultMeta.textContent='Đang thực thi...';
    try{
      const d=await post('/api/sql/query',{csrf:csrf(),db_key:dbKey,sql,readonly:readOnly?1:0});
      if(d.error){resultMeta.innerHTML=`<div class="sql-result-error">${esc(d.error)}</div>`;return;}
      if(d.message){resultMeta.innerHTML=`<div class="sql-result-ok">${esc(d.message)} (${d.time??0}s)</div>`;return;}
      if(Array.isArray(d.columns)&&d.columns.length>0){
        const ths=d.columns.map(c=>`<th>${esc(c)}</th>`).join('');
        const rows=(d.rows||[]).map(r=>`<tr>${d.columns.map(c=>{const v=r[c];return `<td title="${esc(String(v??''))}">${v===null?'<span class="sql-null">NULL</span>':esc(String(v))}</td>`;}).join('')}</tr>`).join('');
        resultWrap.innerHTML=`<table class="sql-grid"><thead><tr>${ths}</tr></thead><tbody>${rows}</tbody></table>`;
        resultMeta.textContent=`${d.rowCount??0} dòng · ${d.time??0}s`;
      }else{
        resultMeta.innerHTML=`<div class="sql-result-ok">Thành công · ${d.time??0}s</div>`;
      }
    }catch(e){resultMeta.innerHTML=`<div class="sql-result-error">${esc(String(e.message))}</div>`;}
  };
  $('sql-run-btn').addEventListener('click',runSql);
  input?.addEventListener('keydown',ev=>{if(ev.key==='Enter'&&(ev.ctrlKey||ev.metaKey)){ev.preventDefault();runSql();}});

  // Cấu trúc
  structSelect.addEventListener('change',async()=>{
    const t=structSelect.value;structEmpty.hidden=!!t;structWrap.innerHTML='';
    if(!t)return;
    try{
      const s=await get('/api/sql/structure?db_key='+encodeURIComponent(dbKey)+'&table='+encodeURIComponent(t));
      const cols=Array.isArray(s?.columns)?s.columns:[];
      const pk=Array.isArray(s?.primary_key)?s.primary_key:[];
      if(cols.length===0){structWrap.innerHTML='<div class="sql-empty">Không đọc được cấu trúc bảng.</div>';return;}
      const hasType=cols[0].type!==undefined||cols[0].Type!==undefined;
      const ths=hasType?'<th>Tên cột</th><th>Kiểu</th><th>Mặc định</th><th>Khóa</th>':'<th>Cột</th><th>Thông tin</th>';
      const rows=cols.map(c=>{
        if(hasType){const isPk=pk.includes(String(c.name??c.Field));return `<tr><td>${esc(String(c.name??c.Field))}</td><td>${esc(String(c.type??c.Type))}</td><td class="sql-null">${esc(String(c.dflt_value??c.Default??''))}</td><td>${isPk?'🔑 PRIMARY':'—'}</td></tr>`;}
        return `<tr><td>${esc(String(c[Object.keys(c)[0]]))}</td><td>${esc(JSON.stringify(c))}</td></tr>`;
      }).join('');
      structWrap.innerHTML=`<table class="sql-grid"><thead><tr>${ths}</tr></thead><tbody>${rows}</tbody></table>`;
    }catch(e){structWrap.innerHTML=`<div class="sql-result-error">${esc(String(e.message))}</div>`;}
  });
})();
