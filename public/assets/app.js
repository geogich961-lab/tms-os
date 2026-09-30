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
  if(!box){
    box=document.createElement('div');
    box.id='tms-toast-container';
    // Ép vị trí Top bằng inline style để bỏ qua cache CSS
    box.style.cssText = 'position:fixed!important;top:calc(12px + env(safe-area-inset-top))!important;right:12px!important;left:12px!important;z-index:9999999!important;display:flex;flex-direction:column;gap:10px;pointer-events:none;bottom:auto!important;';
    if(window.innerWidth > 760) {
        box.style.left = 'auto';
        box.style.right = '20px';
        box.style.maxWidth = '380px';
    }
    document.body.appendChild(box);
  }
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
  if(flash){const text=flash.textContent.trim();if(text) tmsToast(text,flash.dataset.flashToast||'info');flash.remove();}
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
    if(!text||!input.files?.length) return;
    if(input.hasAttribute('webkitdirectory')){
      const rel=input.files[0].webkitRelativePath||'';
      text.textContent='Thư mục: '+(rel.split('/')[0]||input.files.length+' tệp');
    }else if(input.files.length>1){
      text.textContent=input.files.length+' tệp đã chọn';
    }else{
      text.textContent=input.files[0].name;
    }
    const form=input.closest('form'); if(form) form._tmsDropped=null;
  }));

  // Upload qua Cloudflare: nhiều tệp + kéo-thả + thư mục, mỗi request nhỏ và tuần tự để Android 7 không bị 524.
  document.querySelectorAll('[data-chunked-upload]').forEach(form=>form.addEventListener('submit',async event=>{
    event.preventDefault();
    const filesInput=form.querySelector('[data-upload-files]');
    const folderInput=form.querySelector('[data-upload-folder]');
    const button=form.querySelector('[data-upload-submit]');
    const status=form.querySelector('[data-upload-status]');
    const message=form.querySelector('[data-upload-message]');
    const percent=form.querySelector('[data-upload-percent]');
    const progress=form.querySelector('[data-upload-progress]');
    const queueEl=form.querySelector('[data-upload-queue]');
    const csrf=form.querySelector('input[name="csrf"]')?.value||'';
    const root=form.querySelector('input[name="root"]')?.value||'websites';
    const path=form.querySelector('input[name="path"]')?.value||'';
    const files=[];
    (filesInput?.files?Array.from(filesInput.files):[]).forEach(f=>{f._tmsSubdir='';files.push(f);});
    (folderInput?.files?Array.from(folderInput.files):[]).forEach(f=>{const rp=f.webkitRelativePath||'';f._tmsSubdir=rp?rp.split('/').slice(0,-1).join('/'):'';files.push(f);});
    (form._tmsDropped||[]).forEach(f=>files.push(f));
    if(!files.length){tmsToast('Hãy chọn tệp (hoặc kéo-thả) trước khi tải lên.','error');return;}
    const chunkSize=4*1024*1024;
    const randomPart=()=>{try{const bytes=new Uint8Array(18);crypto.getRandomValues(bytes);return Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');}catch(_){return Date.now().toString(36)+'_'+Math.random().toString(36).slice(2);}};
    const totalBytes=files.reduce((s,f)=>s+f.size,0);
    let doneBytes=0;
    const setOverall=(text)=>{const v=totalBytes>0?Math.max(0,Math.min(100,Math.round(doneBytes/totalBytes*100))):100;if(status)status.hidden=false;if(progress){progress.value=v;progress.setAttribute('aria-valuenow',String(v));}if(percent)percent.textContent=v+'%';if(message)message.textContent=text;};
    const requestJson=async(url,body)=>{
      const controller=typeof AbortController==='function'?new AbortController():null; const timer=setTimeout(()=>controller?.abort(),90000);
      try{
        const response=await fetch(url,{method:'POST',body,credentials:'same-origin',cache:'no-store',...(controller?{signal:controller.signal}:{})});
        let data=null; try{data=await response.json();}catch(_){throw new Error(response.status===524?'Origin xử lý quá lâu (524). Hãy thử lại với tệp nhỏ hơn.':'Máy chủ trả về phản hồi không hợp lệ.');}
        if(!response.ok||data?.ok!==true) throw new Error(data?.message||`Upload thất bại (HTTP ${response.status}).`);
        return data;
      }catch(error){if(error?.name==='AbortError')throw new Error('Một phần upload mất quá lâu. Hãy kiểm tra mạng rồi thử lại.');throw error;}finally{clearTimeout(timer);}
    };
    const uploadOne=async(file,rowEl,onBytes)=>{
      const subdir=file._tmsSubdir||'';
      const destPath=subdir?(path?path+'/'+subdir:subdir):path;
      const totalChunks=Math.max(1,Math.ceil(file.size/chunkSize));
      const uploadId='web_'+randomPart();
      const rowMsg=rowEl?.querySelector('[data-queue-msg]');
      const rowBar=rowEl?.querySelector('[data-queue-bar]');
      const rowPct=rowEl?.querySelector('[data-queue-pct]');
      const setRow=(pct,text)=>{const v=Math.max(0,Math.min(100,Math.round(pct)));if(rowBar){rowBar.value=v;rowBar.setAttribute('aria-valuenow',String(v));}if(rowPct)rowPct.textContent=v+'%';if(rowMsg)rowMsg.textContent=text;if(rowEl)rowEl.dataset.state=pct>=100?'done':(text&&text.indexOf('lỗi')>=0?'error':'active');};
      let uploaded=0;
      for(let index=0;index<totalChunks;index++){
        const start=index*chunkSize; const end=Math.min(file.size,start+chunkSize);
        const body=new FormData(); body.append('csrf',csrf);body.append('root',root);body.append('path',destPath);body.append('upload_id',uploadId);body.append('chunk_index',String(index));body.append('total_chunks',String(totalChunks));body.append('name',file.name);body.append('total_size',String(file.size));body.append('chunk',file.slice(start,end),file.name+'.part');
        const result=await requestJson('/files/upload-chunk',body);
        if(Number(result.received_bytes||0)<end) throw new Error('Máy chủ chưa nhận đủ dữ liệu của phần upload.');
        uploaded=end; if(typeof onBytes==='function')onBytes(end); else doneBytes+=(end-start);
        setRow(uploaded/file.size*96,`Đang tải phần ${index+1}/${totalChunks}...`); setOverall(`Đang tải ${file.name}...`);
      }
      setRow(97,'Đang hoàn tất và kiểm tra tệp...');
      const done=new FormData();done.append('csrf',csrf);done.append('upload_id',uploadId);
      await requestJson('/files/upload-complete',done);
      setRow(100,'Xong');
    };
    if(button)button.disabled=true;
    if(filesInput)filesInput.disabled=true;
    if(folderInput)folderInput.disabled=true;
    if(queueEl){queueEl.innerHTML='';files.forEach(f=>{const row=document.createElement('div');row.className='upload-queue-row';row.innerHTML='<span class="upload-queue-name"></span><progress data-queue-bar value="0" max="100"></progress><span class="upload-queue-pct" data-queue-pct>0%</span><span class="upload-queue-msg" data-queue-msg></span>';row.querySelector('.upload-queue-name').textContent=(f._tmsSubdir?f._tmsSubdir+'/':'')+f.name;queueEl.appendChild(row);});}
    const rows=queueEl?Array.from(queueEl.children):[];
    let failed=0; let okCount=0;
    try{
      for(let i=0;i<files.length;i++){
        setOverall(`Đang tải ${i+1}/${files.length}: ${files[i].name}...`);
        let credited=0;
        try{ await uploadOne(files[i],rows[i],n=>{doneBytes+=n-credited;credited=n;}); okCount++; }
        catch(error){
          failed++;
          const text=error instanceof Error?error.message:'Không thể tải tệp lên.';
          const rowMsg=rows[i]?.querySelector('[data-queue-msg]'); if(rowMsg)rowMsg.textContent='Lỗi: '+text;
          if(rows[i])rows[i].dataset.state='error';
          doneBytes+= Math.max(0,files[i].size-credited);
          tmsToast(files[i].name+': '+text,'error');
        }
      }
      setOverall(failed?`Hoàn tất: ${okCount} xong, ${failed} lỗi.`:`Đã tải lên ${okCount} tệp thành công.`);
      tmsToast(failed?`Đã tải ${okCount}/${files.length} tệp (${failed} lỗi).`:`Đã tải lên ${okCount} tệp.`,'success',2200);
      setTimeout(()=>window.location.reload(),600);
    }catch(error){
      const text=error instanceof Error?error.message:'Không thể tải tệp lên.';
      setOverall(text); tmsToast(text,'error');
      if(button){button.disabled=false;button.textContent='Thử lại';}
      if(filesInput)filesInput.disabled=false; if(folderInput)folderInput.disabled=false;
    }
  }));

  // Kéo-thả tệp/thư mục vào Explorer để tải lên.
  const dropZone=document.querySelector('[data-drop-zone]');
  const dropOverlay=document.querySelector('[data-drop-overlay]');
  const uploadForm=document.getElementById('explorer-upload-form');
  const collectDropEntries=async items=>{
    const files=[]; const queue=[];
    for(const item of items){
      const entry=item.webkitGetAsEntry&&item.webkitGetAsEntry();
      if(entry) queue.push({entry,path:''});
      else { const f=item.getAsFile&&item.getAsFile(); if(f){f._tmsSubdir='';files.push(f);} }
    }
    while(queue.length){
      const {entry,path}=queue.shift();
      if(entry.isFile){
        const f=await new Promise((res,rej)=>entry.file(res,rej));
        f._tmsSubdir=path; files.push(f);
      }else if(entry.isDirectory){
        const reader=entry.createReader();
        const entries=await new Promise(res=>reader.readEntries(res));
        for(const e of entries) queue.push({entry:e,path:path?path+'/'+entry.name:entry.name});
      }
    }
    return files;
  };
  if(dropZone&&dropOverlay&&uploadForm){
    let dragDepth=0;
    dropZone.addEventListener('dragenter',e=>{e.preventDefault();dragDepth++;dropOverlay.hidden=false;});
    dropZone.addEventListener('dragover',e=>{e.preventDefault();});
    dropZone.addEventListener('dragleave',e=>{e.preventDefault();if(--dragDepth<=0){dragDepth=0;dropOverlay.hidden=true;}});
    dropZone.addEventListener('drop',async e=>{
      e.preventDefault(); dragDepth=0; dropOverlay.hidden=true;
      const items=e.dataTransfer?Array.from(e.dataTransfer.items||[]):[];
      let files=await collectDropEntries(items);
      if(!files.length&&e.dataTransfer) files=Array.from(e.dataTransfer.files||[]).map(f=>{f._tmsSubdir='';return f;});
      if(!files.length){tmsToast('Không đọc được tệp kéo-thả.','error');return;}
      uploadForm._tmsDropped=files;
      const label=uploadForm.querySelector('[data-file-picker-text]');
      if(label) label.textContent=files.length+' tệp đã sẵn sàng — bấm Tải lên';
      tmsToast('Đã nhận '+files.length+' tệp. Bấm "Tải lên" để bắt đầu.','info');
    });
  }

  // Xem trước ảnh/media.
  const previewModal=document.getElementById('preview-modal');
  const previewBody=previewModal?.querySelector('[data-preview-body]');
  const previewTitle=document.getElementById('preview-title');
  const openPreview=(url,name)=>{
    if(!previewModal||!previewBody) return;
    if(previewTitle) previewTitle.textContent=name||'Xem trước';
    previewBody.innerHTML='';
    const lower=(name||url||'').toLowerCase();
    let el;
    if(/\.(mp4|webm|ogv)(\?|$)/.test(lower)){ el=document.createElement('video'); el.src=url; el.controls=true; el.preload='metadata'; }
    else if(/\.(mp3|wav|ogg|oga|m4a|flac)(\?|$)/.test(lower)){ el=document.createElement('audio'); el.src=url; el.controls=true; el.preload='metadata'; }
    else { el=document.createElement('img'); el.src=url; el.alt=name||''; }
    previewBody.appendChild(el);
    if(typeof openModal==='function') openModal('preview-modal');
    else previewModal.classList.add('show');
  };
  document.querySelectorAll('[data-preview-file]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();openPreview(a.dataset.previewUrl,a.dataset.previewName);}));
  previewModal?.querySelector('[data-modal-close]')?.addEventListener('click',()=>{if(previewBody)previewBody.innerHTML='';});

  // Remote download: dán URL để server tự tải về.
  document.querySelectorAll('[data-remote-download]').forEach(form=>form.addEventListener('submit',async event=>{
    event.preventDefault();
    const urlInput=form.querySelector('input[name="url"]');
    const button=form.querySelector('[data-remote-submit]');
    const status=form.querySelector('[data-remote-status]');
    const message=form.querySelector('[data-remote-message]');
    const url=urlInput?.value.trim()||'';
    if(!url){tmsToast('Hãy dán URL trước.','error');return;}
    if(button)button.disabled=true;
    if(status)status.hidden=false;
    if(message)message.textContent='Đang tải về server... (có thể mất vài phút với tệp lớn)';
    try{
      const body=new FormData(form);
      const response=await fetch('/files/remote-download',{method:'POST',body,credentials:'same-origin',cache:'no-store'});
      let data=null; try{data=await response.json();}catch(_){throw new Error('Máy chủ trả về phản hồi không hợp lệ.');}
      if(!response.ok||data?.ok!==true) throw new Error(data?.message||`Tải thất bại (HTTP ${response.status}).`);
      if(message)message.textContent='Đã tải xong: '+(data.name||'');
      tmsToast('Đã tải về: '+(data.name||''),'success',2200);
      setTimeout(()=>window.location.reload(),600);
    }catch(error){
      const text=error instanceof Error?error.message:'Không tải được URL.';
      if(message)message.textContent=text;
      tmsToast(text,'error');
      if(button)button.disabled=false;
    }
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
  const contextToolbar=document.getElementById('explorer-context-toolbar');
  const selectedCountEl=document.getElementById('selected-count');
  const fab=document.querySelector('.explorer-fab');

  let selectedItems=new Set();
  const updateSelectionUI=()=>{
    const count=selectedItems.size;
    if(count>0){
      contextToolbar?.removeAttribute('hidden');
      if(selectedCountEl) selectedCountEl.textContent=count;
      fab?.classList.add('has-toolbar');
    }else{
      contextToolbar?.setAttribute('hidden','');
      fab?.classList.remove('has-toolbar');
    }
    document.querySelectorAll('[data-explorer-item]').forEach(item=>{
      const rel=item.dataset.relative;
      const isSelected=selectedItems.has(rel);
      item.classList.toggle('selected',isSelected);
      const cb=item.querySelector('[data-select-item]');
      if(cb) cb.checked=isSelected;
    });
  };
  document.querySelectorAll('[data-select-item]').forEach(cb=>{
    cb.addEventListener('change',()=>{
      if(cb.checked) selectedItems.add(cb.value); else selectedItems.delete(cb.value);
      updateSelectionUI();
    });
  });
  document.querySelectorAll('[data-explorer-item]').forEach(item=>{
    let timer;
    item.addEventListener('touchstart',()=>{
      timer=setTimeout(()=>{
        const rel=item.dataset.relative;
        if(selectedItems.has(rel)) selectedItems.delete(rel); else selectedItems.add(rel);
        updateSelectionUI();
        if(window.navigator.vibrate) window.navigator.vibrate(50);
      },600);
    });
    item.addEventListener('touchend',()=>clearTimeout(timer));
    item.addEventListener('touchmove',()=>clearTimeout(timer));
  });

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
    const preview=button.dataset.preview||'';

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
    const previewButton=document.getElementById('sheet-preview');
    if(previewButton){
      previewButton.hidden=!(preview&&!isDir);
      previewButton.onclick=()=>{closeSheet();openPreview(preview,name);};
    }
    extractForm?.classList.toggle('sheet-hidden',!isZip);
    if(deleteForm) deleteForm.dataset.confirm=`Chuyển "${name}" vào thùng rác? Bạn có thể khôi phục sau.`;

    document.querySelectorAll('[data-file-actions]').forEach(b=>b.classList.remove('sheet-active'));
    button.classList.add('sheet-active');

    sheet.classList.add('show');
    sheet.setAttribute('aria-hidden','false');
    document.body.classList.add('sheet-open');
  };

  document.querySelectorAll('[data-file-actions]').forEach(button=>button.addEventListener('click',()=>openSheet(button)));
  document.querySelectorAll('[data-action-sheet-close]').forEach(button=>button.addEventListener('click',closeSheet));
  const chmodApplyForm=document.getElementById('chmod-modal')?.querySelector('form');
  const chmodInput=document.getElementById('chmod-modal')?.querySelector('input[name="mode"]');
  const chmodRecursiveCb=document.getElementById('chmod-modal')?.querySelector('input[type="checkbox"]');
  const copyForm=document.getElementById('copy-modal')?.querySelector('form');
  const moveForm=document.getElementById('move-modal')?.querySelector('form');

  const submitBatch=(action,extraParams={})=>{
    if(selectedItems.size===0) return;
    const form=document.createElement('form'); form.method='POST'; form.action=`/files/${action}`;
    const add=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
    add('csrf',document.querySelector('meta[name="csrf-token"]')?.content||'');
    add('root',new URLSearchParams(window.location.search).get('root')||'websites');
    selectedItems.forEach(rel=>{const i=document.createElement('input');i.type='hidden';i.name='relatives[]';i.value=rel;form.appendChild(i);});
    Object.keys(extraParams).forEach(k=>add(k,extraParams[k]));
    document.body.appendChild(form); form.submit();
  };
  document.getElementById('batch-clear')?.addEventListener('click',()=>{selectedItems.clear();updateSelectionUI();});
  document.getElementById('batch-delete')?.addEventListener('click',()=>{if(confirm(`Chuyển ${selectedItems.size} mục vào thùng rác? Bạn có thể khôi phục sau.`)) submitBatch('delete');});
  document.getElementById('batch-archive')?.addEventListener('click',()=>submitBatch('archive'));
  document.getElementById('batch-chmod')?.addEventListener('click',()=>{const m=prompt("Quyền (vd 0755):","0755");if(m)submitBatch('chmod',{mode:m,recursive:'1'});});
  const startBatchOp=(type)=>{
    const root=new URLSearchParams(window.location.search).get('root')||'websites';
    localStorage.setItem('tms_explorer_batch_op',JSON.stringify({type,root,relatives:Array.from(selectedItems)}));
    tmsToast(`Đã chọn ${selectedItems.size} mục để ${type==='copy'?'sao chép':'di chuyển'}.`);
    selectedItems.clear(); updateSelectionUI();
  };
  document.getElementById('batch-copy')?.addEventListener('click',()=>startBatchOp('copy'));
  document.getElementById('batch-move')?.addEventListener('click',()=>startBatchOp('move'));

  const useHereBtn=document.getElementById('explorer-use-here');
  const activeBatchOp=localStorage.getItem('tms_explorer_batch_op');
  const activeSingleOp=localStorage.getItem('tms_explorer_op');
  if((activeBatchOp||activeSingleOp)&&useHereBtn){
    const op=activeBatchOp?JSON.parse(activeBatchOp):JSON.parse(activeSingleOp);
    useHereBtn.style.display='block'; useHereBtn.textContent=`📥 ${op.type==='copy'?'Sao chép':'Di chuyển'} vào đây`;
    useHereBtn.addEventListener('click',()=>{
      const form=document.createElement('form'); form.method='POST'; form.action=`/files/${op.type}`;
      const add=(n,v)=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);};
      add('csrf',document.querySelector('meta[name="csrf-token"]')?.content||'');
      add('root',op.root); add('target_path',new URLSearchParams(window.location.search).get('path')||'');
      if(op.relatives) op.relatives.forEach(rel=>{const i=document.createElement('input');i.type='hidden';i.name='relatives[]';i.value=rel;form.appendChild(i);});
      else add('relative',op.relative);
      document.body.appendChild(form); localStorage.removeItem('tms_explorer_batch_op'); localStorage.removeItem('tms_explorer_op'); form.submit();
    });
  }

  const setRelativeAll=(relative)=>{
    const c=document.getElementById('copy-modal')?.querySelector('input[name="relative"]'); if(c)c.value=relative;
    const m=document.getElementById('move-modal')?.querySelector('input[name="relative"]'); if(m)m.value=relative;
  };

  const openCopyMove=(mode)=>{
    const button=document.querySelector('[data-file-actions].sheet-active');
    const relative=button?.dataset.relative||'';
    const modal=document.getElementById(mode+'-modal');
    const relInp=modal?.querySelector('input[name="relative"]'); if(relInp)relInp.value=relative;
    const targetInp=modal?.querySelector(`[data-${mode}-target-input]`);
    const currentPath=new URL(location).searchParams.get('path')||'';
    if(targetInp) targetInp.value=currentPath;
    closeSheet(); openModal(mode+'-modal');
  };
  document.querySelector('[data-copy-open]')?.addEventListener('click',()=>openCopyMove('copy'));
  document.querySelector('[data-move-open]')?.addEventListener('click',()=>openCopyMove('move'));

  document.querySelector('[data-chmod-open]')?.addEventListener('click',()=>{
    const rel=document.querySelector('[data-file-actions].sheet-active')?.dataset.relative||'';
    const modal=document.getElementById('chmod-modal');
    const relInp=modal?.querySelector('input[name="relative"]'); if(relInp)relInp.value=rel;
    closeSheet(); openModal('chmod-modal');
  });

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

