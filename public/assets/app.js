/* ===== Toast notification hệ thống (V15.3.6) — hiển thị trạng thái thao tác tại vị trí người dùng ===== */
(()=>{
window.tmsToast=(msg,type,ms)=>{
  const text=String(msg==null?'':msg);if(!text)return;
  type=['success','error','warn','info'].includes(type)?type:'info';
  if(!ms){
    const d=document.currentScript?.dataset.toastDuration || document.querySelector('script[data-toast-duration]')?.dataset.toastDuration;
    ms = d ? parseInt(d)*1000 : (type==='error'?5500:3500);
  }
  let box=document.getElementById('tms-toast-container');
  if(!box){box=document.createElement('div');box.id='tms-toast-container';document.body.appendChild(box);}
  const t=document.createElement('div');t.className='tms-toast tms-toast-'+type;
  t.innerHTML='<span class="tms-toast-icon">'+({'success':'✓','error':'✕','warn':'!','info':'i'}[type])+'</span><span class="tms-toast-text"></span>';
  t.querySelector('.tms-toast-text').textContent=text;
  box.appendChild(t);
  requestAnimationFrame(()=>t.classList.add('tms-toast-in'));
  setTimeout(()=>{t.classList.add('tms-toast-out');setTimeout(()=>t.remove(),320);},ms);
};
})();

// Auto-convert: flash message từ server (POST truyền thống) → toast notification
(()=>{
  const flash=document.querySelector('[data-flash-toast]');
  if(flash){const text=flash.textContent.trim();if(text) tmsToast(text,flash.dataset.flashToast||'info',7000);flash.remove();}
})();

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
    if(file.size>2097152){brandApply.disabled=true;tmsToast('Logo quá 2 MB. Chọn tệp nhỏ hơn.','error');return;}
    tmsToast('Đã chọn logo: '+file.name,'info');
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
    tmsToast('Đang xóa cache... giao diện sẽ tải lại dữ liệu mới.','info');
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
    if(typeof setRelativeAll === 'function') setRelativeAll(relative);

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

// Copy helpers → toast hệ thống (không tạo div riêng)
(() => {window.tmsCopy=value=>{const v=String(value??'');navigator.clipboard.writeText(v).catch(()=>{const area=document.createElement('textarea');area.value=v;document.body.appendChild(area);area.select();document.execCommand('copy');area.remove();});tmsToast('Đã sao chép vào clipboard.','success',1500);};document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',()=>tmsCopy(btn.getAttribute('data-copy')||'')));})();

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

// ===== Resource Monitor logic (V15.4.7) =====
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
  let rows=[];try{rows=JSON.parse(monitorCanvas.dataset.history||'[]')}catch(e){}
  drawMonitorChart(monitorCanvas,rows);
  const $m=(s)=>document.querySelector(s);
  const update=async()=>{try{
    const r=await fetch('/api/monitoring?force=1',{cache:'no-store'});if(!r.ok)return;const d=await r.json();
    const cur=d.current||{};const det=d.details||{};const dev=d.device||{};
    ['memory','storage','load'].forEach(k=>{const el=$m(`[data-monitor-value="${k}"]`);if(el)el.textContent=k==='load'?cur[k]:`${cur[k]}%`;const bar=$m(`[data-monitor-bar="${k}"]`);if(bar)bar.style.width=`${cur[k]}%`;});
    const map={'device_model':dev.model,'android_version':dev.android_version?('Android '+dev.android_version):'','architecture':det.architecture,'uptime':det.uptime,'memory_used_mb':det.memory_used_mb,'memory_total_mb':det.memory_total_mb,'storage_used_gb':det.storage_used_gb,'storage_total_gb':det.storage_total_gb,'processes':det.processes,'cpu_temp':det.temperature?`${det.temperature}°C`:'Không khả dụng','network_rx':det.network?.rx_mb,'network_tx':det.network?.tx_mb};
    Object.entries(map).forEach(([k,v])=>{const el=$m(`[data-monitor-value="${k}"]`);if(el&&v!==undefined)el.textContent=String(v)});
    const bat=det.battery||{};const pct=bat.percentage;const batLabel=(pct!==null)?(pct+'%'):(bat.status||'Không khả dụng');
    const labelEl=$m('[data-monitor-value="battery_label"]');if(labelEl)labelEl.textContent=batLabel+(pct!==null&&bat.status?` · ${bat.status}`:'');
    const hRow=document.getElementById('battery-health-row');if(hRow){hRow.style.display=pct!==null?'flex':'none';const hVal=$m('[data-monitor-value="battery_health"]');if(hVal)hVal.textContent=bat.health||'';}
    const cRow=document.getElementById('battery-current-row');if(cRow){cRow.style.display=bat.current?'flex':'none';const cVal=$m('[data-monitor-value="battery_current"]');if(cVal)cVal.textContent=bat.current||'';}
    const bRow=document.getElementById('battery-bar-row');if(bRow){bRow.style.display=pct!==null?'flex':'none';const bBar=$m('[data-monitor-bar="battery"]');if(bBar){bBar.style.width=pct+'%';bBar.style.background=pct<20?'var(--danger)':'';}const bPct=$m('[data-monitor-value="battery_percent"]');if(bPct)bPct.textContent=pct;}
    const tRow=document.getElementById('battery-temp-row');if(tRow){tRow.style.display=bat.temperature?'flex':'none';const tVal=$m('[data-monitor-value="battery_temp"]');if(tVal)tVal.textContent=bat.temperature||'';}
    drawMonitorChart(monitorCanvas,d.history||[]);
  }catch(e){}};
  setInterval(update,15000);setTimeout(update,1000);
  window.addEventListener('resize',()=>drawMonitorChart(monitorCanvas,rows));
}

// ===== Notification logic =====
const notificationEnable=document.querySelector('[data-notification-enable]');
const notificationTest=document.querySelector('[data-notification-test]');
const permissionText=document.getElementById('notification-permission-text');
function updatePermissionText(){if(permissionText)permissionText.textContent=('Notification'in window)?`Trạng thái: ${Notification.permission}`:'Trình duyệt không hỗ trợ Notification API.'}
updatePermissionText();
notificationEnable?.addEventListener('click',async()=>{if('Notification'in window)await Notification.requestPermission();updatePermissionText();tmsToast(Notification.permission==='granted'?'Đã bật thông báo PWA.':'Quyền thông báo chưa được cấp.','success');});
notificationTest?.addEventListener('click',()=>{if(Notification.permission==='granted')new Notification('TMS OS',{body:'Thông báo PWA đang hoạt động.',icon:'/assets/icons/icon-192.png'});});
if(document.querySelector('[data-service-alert]')){
  let previous=null;
  setInterval(async()=>{try{
    const r=await fetch('/api/notifications/status',{cache:'no-store'});if(!r.ok)return;const d=await r.json();
    if(previous&&Notification.permission==='granted'){
      Object.entries(d.services||{}).forEach(([name,running])=>{if(previous[name]===true&&running===false)new Notification('Dịch vụ đã dừng',{body:`${name} không còn chạy.`,icon:'/assets/icons/icon-192.png'});});
    }
    previous=d.services||{};
  }catch(e){}},60000);
}

// ===== Appearance Center =====
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

// ===== Guardian live status =====
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
