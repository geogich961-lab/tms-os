/* ==========================================================================
   TMS OS V15.3.7 — Navicat-style Database Manager
   Object Explorer + Browse (inline edit, filter, sort, paging, search)
   + SQL + Structure (CREATE TABLE copy)
   ========================================================================== */
(()=>{
  const path=(window.location.pathname||'').split('?')[0].replace(/\/$/,'');
  if(path!=='/databases')return;

  const esc=(v)=>String(v??'').replace(/[&<>'"\/]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;','/':'&#47;'}[c]));
  const $=id=>document.getElementById(id);
  const csrfToken=()=>document.querySelector('meta[name=csrf-token]')?.content||'';
  const post=async(url,body)=>{
    const fd=new URLSearchParams();Object.entries(body).forEach(([k,v])=>{
      if(Array.isArray(v)){v.forEach(x=>fd.append(k,String(x)));}
      else{fd.append(k,String(v));}
    });
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':csrfToken()},body:fd.toString(),cache:'no-store'});
    const d=await r.json();
    if(!r.ok)throw new Error(d.error||'Lỗi máy chủ.');
    return d;
  };
  const get=async url=>{const r=await fetch(url,{cache:'no-store',headers:{Accept:'application/json','X-CSRF-Token':csrfToken()}});const d=await r.json();if(d&&d.error&&Object.keys(d).length===1)throw new Error(d.error);return d;};

  // ===== State =====
  const state={
    dbs: Array.isArray(window.TMS_SQL_DBS)?window.TMS_SQL_DBS:[],
    driver: window.TMS_SQL_DRIVER||'SQLite',
    openDb:'',            // db_key đang mở
    tables:[],            // danh sách bảng của db đang mở
    table:'',             // bảng đang chọn
    columns:[],           // cấu trúc cột
    primaryKey:[],        // khóa chính
    rows:[],
    totalRows:0,          // tổng số dòng (sau filter)
    page:1,
    pageSize:50,
    sortCol:'',sortDir:'ASC',
    filter:{col:'',op:'LIKE',val:''},
    search:'',
    ddls:{},              // cache CREATE TABLE
  };
  const DB_URL=(k)=>'/api/sql';

  // ===== Object Explorer =====
  const dbListEl=$('navdb-db-list');
  const renderExplorer=()=>{
    const mng=state.dbs.filter(d=>d.source==='managed'||(!d.site&&!d.source));
    const web=state.dbs.filter(d=>d.source==='website'&&d.site);
    let html='';
    const group=(label,items)=>{
      html+=`<div class="navdb-group-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/></svg>${label}</div>`;
      items.forEach((db,i)=>{
        const i0=state.dbs.indexOf(db);
        const isActive=state.openDb===db.db_key;
        const open=isActive;
        html+=`<div class="navdb-db-wrap" data-i="${i0}">
          <button class="navdb-db-item${isActive?' active':''}" data-i="${i0}" type="button">
            <span class="navdb-db-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
            <span>${esc(db.name)}</span>
            ${db.site?`<span class="navdb-chip">${esc(db.site)}</span>`:''}
            ${db.size?`<span class="navdb-meta">${fmtSize(db.size)}</span>`:''}
            <button class="navdb-db-toggle${open?' open':''}" data-toggle="${i0}" type="button" title="Mở rộng"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></button>
          </button>
          <div class="navdb-tables${open?'':' hidden'}" data-tree="${i0}">
            <input class="navdb-search-tbl navdb-search" placeholder="Tìm bảng..." data-tbl-search="${i0}" data-tbl-db="${esc(db.db_key)}" type="text">
            ${renderTree(db,open)}
          </div>
        </div>`;
      });
    };
    if(mng.length)group('TMS OS',mng);
    if(web.length)group('Website',web);
    if(!state.dbs.length)html='<div class="navdb-empty-db" style="padding:10px 15px">Chưa có database nào.</div>';
    dbListEl.innerHTML=html;
    $('navdb-create-db-btn').style.display=(state.driver==='SQLite')?'':'none';
  };

  const renderTree=(db,open)=>{
    const input=document.querySelector(`input[data-tbl-db="${db.db_key}"]`);
    const q=input?input.value:'';
    let tbls=state.tablesByDb&&state.tablesByDb[db.db_key]?state.tablesByDb[db.db_key]:[];
    if(q)tbls=tbls.filter(t=>t.toLowerCase().includes(q.toLowerCase()));
    if(!open)return '';
    if(!state.tablesByDb||!(db.db_key in state.tablesByDb))return '<div class="navdb-empty-db">Đang tải bảng...</div>';
    if(!tbls.length)return '<div class="navdb-empty-db">Không tìm thấy bảng nào.</div>';
    return tbls.map(t=>`<button class="navdb-table-item${(state.openDb===db.db_key&&state.table===t)?' active':''}" data-db="${esc(db.db_key)}" data-tbl="${esc(t)}" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="1.5"/><path d="M3 9h18"/><path d="M3 14h18"/></svg>${esc(t)}</button>`).join('');
  };

  const fmtSize=(b)=>{b=Number(b)||0;if(b<1024)return b+' B';if(b<1048576)return (b/1024).toFixed(1)+' KB';if(b<1073741824)return (b/1048576).toFixed(1)+' MB';return (b/1073741824).toFixed(2)+' GB';};

  const loadTablesForDb=async(dbKey)=>{
    try{
      const d=await get('/api/sql/tables?db_key='+encodeURIComponent(dbKey));
      state.tablesByDb=state.tablesByDb||{};
      state.tablesByDb[dbKey]=Array.isArray(d?.tables)?d.tables:[];
      renderExplorer();
      // UX Navicat: database chỉ có 1 bảng thực tế → mở luôn bảng đó
      const tbls=(state.tablesByDb[dbKey]||[]).filter(t=>t!=='sqlite_sequence'&&t!=='sqlite_stat1');
      if(tbls.length===1&&state.openDb===dbKey){openTable(dbKey,tbls[0]);}
    }catch(e){state.tablesByDb=state.tablesByDb||{};state.tablesByDb[dbKey]=[];tmsToast(String(e.message),'error');renderExplorer();}
  };

  // Chọn database
  const pickDb=(dbKey)=>{
    state.openDb=dbKey;state.table='';state.page=1;
    $('navdb-empty').hidden=true;$('navdb-panel').hidden=true;
    renderExplorer();
    loadTablesForDb(dbKey);
    $('navdb-export-name').value='';
    const db=state.dbs.find(d=>d.db_key===dbKey);
    if(db&&db.name){
      const name=(db.source==='website'||db.site)?db.name.replace(/\s*\(.*?\)\s*$/,''):db.name;
      $('navdb-export-name').value=name;
    }
  };

  dbListEl.addEventListener('click',(ev)=>{
      const toggle=ev.target.closest('[data-toggle]');
    if(toggle){
      ev.stopPropagation();
      const tree=dbListEl.querySelector(`div[data-tree="${toggle.dataset.toggle}"]`);
      if(tree)tree.classList.toggle('hidden');
      toggle.classList.toggle('open');
      return;
    }
    const item=ev.target.closest('.navdb-db-item');
    if(item){pickDb(item.dataset.i!==undefined?state.dbs[Number(item.dataset.i)].db_key:item.dataset.db);}
    const tbl=ev.target.closest('.navdb-table-item');
    if(tbl){openTable(tbl.dataset.db,tbl.dataset.tbl);}
  });
  dbListEl.addEventListener('input',(ev)=>{
    const inp=ev.target.closest('[data-tbl-search]');
    if(inp){renderExplorer();const n=$(inp.dataset.tblSearch?'navdb-tbl-s-0':'');}
  });
  $('navdb-refresh-sb').addEventListener('click',async()=>{
    try{
      const d=await get('/api/sql/databases');
      state.dbs=Array.isArray(d?.databases)?d.databases:state.dbs;
      state.tablesByDb={};
      if(state.openDb)await loadTablesForDb(state.openDb);
      renderExplorer();
      tmsToast('Đã làm mới danh sách database.','info');
    }catch(e){tmsToast(String(e.message),'error');}
  });
  $('navdb-create-db-btn').addEventListener('click',()=>document.querySelector('[data-modal-open="db-modal"]')?.click());

  // ===== Open table =====
  const openTable=async(dbKey,table)=>{
    state.openDb=dbKey;state.table=table;state.page=1;state.search='';
    state.sortCol='';state.sortDir='ASC';state.filter={col:'',op:'LIKE',val:''};
    $('navdb-empty').hidden=true;$('navdb-panel').hidden=false;
    $('navdb-pane-browse').hidden=false;$('navdb-pane-sql').hidden=true;$('navdb-pane-structure').hidden=true;
    document.querySelectorAll('.navdb-tab').forEach(t=>t.classList.toggle('active',t.dataset.tab==='browse'));
    const db=state.dbs.find(d=>d.db_key===dbKey);
    $('navdb-breadcrumb').innerHTML=`${db?esc(db.name):''} <span class="muted">›</span> <strong>${esc(table)}</strong>`;
    renderExplorer();
    $('navdb-export-name').value='';
    if(db){
      const name=(db.source==='website'||db.site)?db.name.replace(/\s*\(.*?\)\s*$/,''):db.name;
      $('navdb-export-name').value=name;
    }
    await loadStructure();
    await loadTableData();
  };


  const loadStructure=async()=>{
    try{
      const s=await get(`/api/sql/structure?db_key=${encodeURIComponent(state.openDb)}&table=${encodeURIComponent(state.table)}`);
      state.columns=Array.isArray(s?.columns)?s.columns:[];
      state.primaryKey=Array.isArray(s?.primary_key)?s.primary_key:[];
      $('navdb-struct-title').textContent=state.table;
      $('navdb-struct-meta').innerHTML=state.primaryKey.length?`<span class="navdb-pk-badge">🔑 PK: ${state.primaryKey.map(esc).join(', ')}</span>`:'<span class="muted">Không có khóa chính (không thể chỉnh sửa)</span>';
      renderStructure();
      const colOpts='<option value="">— Tất cả —</option>'+state.columns.map(c=>`<option value="${esc(c.name||c.Field)}">${esc(c.name||c.Field)}</option>`).join('');
      $('navdb-filter-col').innerHTML=colOpts;
      $('navdb-sort-col').innerHTML='<option value="">— Mặc định —</option>'+colOpts.replace('<option value="">— Tất cả —</option>','');
    }catch(e){tmsToast(String(e.message),'error');}
  };

  const buildSql=()=>{
    const cols='*';
    const wh=[];
    if(state.filter.col&&state.filter.op){
      const c=state.driver==='SQLite'?`"${state.filter.col}"`:`\`${state.filter.col}\``;
      if(state.filter.op==='IS NULL'){wh.push(`${c} IS NULL`);}
      else{
        const v=state.filter.val===''?'':state.driver==='SQLite'?`'${state.filter.val.replace(/'/g,"''")}'`:`'${state.filter.val.replace(/'/g,"''")}'`;
        if(state.filter.op==='LIKE')wh.push(`${c} LIKE '%${state.filter.val.replace(/'/g,"''")}%'`);
        else if(state.filter.op==='=')wh.push(`${c} = ${v}`);
        else if(state.filter.op==='!=')wh.push(`${c} != ${v}`);
        else wh.push(`${c} ${state.filter.op} ${v}`);
      }
    }
    let sql=`SELECT ${cols} FROM ${state.driver==='SQLite'?`"${state.table}"`:`\`${state.table}\``}`;
    if(wh.length)sql+=' WHERE '+wh.join(' AND ');
    if(state.sortCol)sql+=` ORDER BY ${state.driver==='SQLite'?`"${state.sortCol}"`:`\`${state.sortCol}\``} ${state.sortDir}`;
    return {sql,wh};
  };

  const loadTableData=async()=>{
    $('navdb-data-empty').hidden=true;$('navdb-data-wrap').innerHTML='';
    const {sql:baseSql}=buildSql();
    try{
      // Đếm tổng (nhanh)
      const countSql=baseSql.replace(/SELECT \* FROM /i,'SELECT COUNT(*) AS c FROM ').replace(/ORDER BY [^ ]+ (ASC|DESC)$/i,'');
      let total=0;
      try{
        const cd=await post('/api/sql/query',{db_key:state.openDb,readonly:1,sql:countSql});
        total=Number((cd?.rows?.[0]||{}).c??cd?.rows?.[0]?.['COUNT(*)']??cd?.rowCount??0)||0;
      }catch(_e){/* bỏ qua nếu COUNT thất bại */}
      state.totalRows=total;
      const offset=(state.page-1)*state.pageSize;
      const sql=baseSql+` LIMIT ${state.pageSize} OFFSET ${offset}`;
      const d=await post('/api/sql/query',{db_key:state.openDb,readonly:1,sql});
      state.columns_data=Array.isArray(d?.columns)?d.columns:[];
      state.rows=Array.isArray(d?.rows)?d.rows:[];
      renderDataGrid();
      $('navdb-count').textContent=`${state.totalRows} dòng · trang ${state.page}/${Math.max(1,Math.ceil(state.totalRows/state.pageSize))} · ${d.time??0}s`;
      $('navdb-pageinfo').textContent=`${state.rows.length} dòng trên trang`;
      $('navdb-prev').disabled=state.page<=1;
      $('navdb-next').disabled=state.rows.length<state.pageSize;
      $('navdb-pager-text').textContent=`Trang ${state.page} / ${Math.max(1,Math.ceil(state.totalRows/state.pageSize))}`;
      updateToolbarState();
    }catch(e){$('navdb-data-wrap').innerHTML=`<div class="navdb-msg" style="color:var(--danger,#c0392b)">${esc(String(e.message))}</div>`;}
  };

  const updateToolbarState=()=>{
    const canEdit=!$('navdb-readonly').checked&&state.primaryKey.length>0;
    $('navdb-insert-btn').disabled=!canEdit||!state.table;
  };

  const cellVal=(row,col)=>row[col]===null?'NULL':String(row[col]??'');

  const renderDataGrid=()=>{
    const cols=state.columns_data;
    if(!cols||cols.length===0){$('navdb-data-empty').hidden=false;$('navdb-data-wrap').innerHTML='';return;}
    const canEdit=!$('navdb-readonly').checked&&state.primaryKey.length>0;
    const q=state.search.toLowerCase();
    const hl=(v)=>{
      if(!q)return esc(String(v));
      const s=esc(String(v??''));
      if(!s.toLowerCase().includes(q))return s;
      const i=s.toLowerCase().indexOf(q);
      return s.slice(0,i)+`<mark class="navdb-search-hl">${s.slice(i,i+state.search.length)}</mark>`+s.slice(i+state.search.length);
    };
    const ths=cols.map(c=>{
      let label=esc(c);
      if(state.sortCol===c)label+=state.sortDir==='ASC'?' ↑':' ↓';
      return `<th class="${state.primaryKey.includes(c)?'pk':''}" data-sortcol="${esc(c)}">${label}</th>`;
    }).join('')+(canEdit?'<th class="navdb-row-actions"></th>':'');
    const tbody=state.rows.map((row,ri)=>{
      const cells=cols.map(col=>{
        const v=row[col];
        const display=v===null?'<span class="navdb-null">NULL</span>':hl(v);
        const title=esc(String(v??''));
        const cls=(col.length>40?' navdb-td-long':'')+(canEdit?' navdb-cell':'');
        return `<td class="${state.primaryKey.includes(col)?'pk':''}${cls}" data-ri="${ri}" data-col="${esc(col)}" title="${title}">${display}</td>`;
      }).join('');
      const act=canEdit?`<td class="navdb-row-actions"><button type="button" class="navdb-del-btn" data-del="${ri}" title="Xóa dòng">✕</button></td>`:'';
      return `<tr>${cells}${act}</tr>`;
    }).join('');
    $('navdb-data-wrap').innerHTML=`<table class="navdb-grid"><thead><tr>${ths}</tr></thead><tbody>${tbody}</tbody></table>`;

    // Inline edit
    $('navdb-data-wrap').querySelectorAll('.navdb-cell').forEach(td=>{
      const startEdit=()=>{
        if(td.querySelector('.navdb-cell-input'))return;
        const col=td.dataset.col;const ri=Number(td.dataset.ri);const row=state.rows[ri];
        const val=row[col]===null?'':String(row[col]??'');
        const inp=document.createElement('input');
        inp.className='navdb-cell-input';inp.value=val;inp.autocomplete='off';
        td.textContent='';td.appendChild(inp);inp.focus();
        const finish=async(newVal)=>{
          if(newVal===val){td.textContent=val;td.insertAdjacentHTML('beforeend','');return;}
          td.innerHTML='<span class="navdb-null" style="opacity:.5">⏳</span>';
          try{
            await post('/api/sql/save-cell',{csrf:csrfToken(),db_key:state.openDb,table:state.table,pk_columns:state.primaryKey,pk_values:state.primaryKey.map(k=>row[k]===null?'':String(row[k]??'')),column:col,value:newVal});
            state.rows[ri][col]=newVal;
            renderDataGrid();
            tmsToast('Đã lưu ô dữ liệu.','success');
          }catch(e){td.textContent=val;tmsToast(String(e.message),'error');}
        };
        inp.addEventListener('blur',()=>finish(inp.value));
        inp.addEventListener('keydown',ev=>{if(ev.key==='Enter'){ev.preventDefault();inp.blur();}if(ev.key==='Escape'){inp.value=val;inp.blur();}});
      };
      td.addEventListener('click',startEdit);
    });

    // Sort by header click
    $('navdb-data-wrap').querySelectorAll('th[data-sortcol]').forEach(th=>th.addEventListener('click',()=>{
      const col=th.dataset.sortcol;
      if(state.sortCol===col){state.sortDir=state.sortDir==='ASC'?'DESC':'ASC';}
      else{state.sortCol=col;state.sortDir='ASC';}
      state.page=1;loadTableData();
    }));

    // Delete row
    $('navdb-data-wrap').querySelectorAll('.navdb-del-btn').forEach(btn=>btn.addEventListener('click',async()=>{
      const ri=Number(btn.dataset.del);const row=state.rows[ri];
      if(!confirm(`Xóa dòng này của bảng "${state.table}"?`))return;
      try{
        await post('/api/sql/delete-row',{csrf:csrfToken(),db_key:state.openDb,table:state.table,pk_columns:state.primaryKey,pk_values:state.primaryKey.map(k=>row[k]===null?'':String(row[k]??''))});
        state.rows.splice(ri,1);state.totalRows=Math.max(0,state.totalRows-1);
        renderDataGrid();
        tmsToast('Đã xóa 1 dòng.','success');
      }catch(e){tmsToast(String(e.message),'error');}
    }));
  };

  // Toolbar handlers
  $('navdb-browse-refresh').addEventListener('click',()=>loadTableData());
  $('navdb-readonly').addEventListener('change',()=>{updateToolbarState();renderDataGrid();});
  $('navdb-prev').addEventListener('click',()=>{if(state.page>1){state.page--;loadTableData();}});
  $('navdb-next').addEventListener('click',()=>{if(state.rows.length>=state.pageSize){state.page++;loadTableData();}});

  // Filter
  $('navdb-filter-apply').addEventListener('click',()=>{
    state.filter={col:$('navdb-filter-col').value,op:$('navdb-filter-op').value,val:$('navdb-filter-val').value};
    state.page=1;loadTableData();
    tmsToast(state.filter.col?`Đang lọc: ${state.filter.col} ${state.filter.op} ${state.filter.val?'"'+state.filter.val+'"':'(rỗng)'}`:'Đã bỏ lọc.','info');
  });
  $('navdb-filter-clear').addEventListener('click',()=>{
    state.filter={col:'',op:'LIKE',val:''};$('navdb-filter-col').value='';$('navdb-filter-val').value='';
    state.page=1;loadTableData();
  });
  // Nút bật/tắt thanh lọc: thêm vào thanh toolbar (element có sẵn trong HTML)
  const filterBtn=document.createElement('button');
  filterBtn.type='button';filterBtn.className='btn btn-ghost btn-small';
  filterBtn.innerHTML='⚙ Lọc';filterBtn.title='Bật/tắt thanh lọc';
  filterBtn.addEventListener('click',()=>{const fb=$('navdb-filterbar');if(fb)fb.classList.toggle('hidden');});
  const toolsRow=document.querySelector('.navdb-tools-row');
  if(toolsRow)toolsRow.appendChild(filterBtn);

  // Search trong grid
  $('navdb-search').addEventListener('input',(ev)=>{state.search=ev.target.value;renderDataGrid();});

  // Tabs
  document.querySelectorAll('.navdb-tab').forEach(t=>t.addEventListener('click',async()=>{
    document.querySelectorAll('.navdb-tab').forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    $('navdb-pane-browse').hidden=t.dataset.tab!=='browse';
    $('navdb-pane-sql').hidden=t.dataset.tab!=='sql';
    $('navdb-pane-structure').hidden=t.dataset.tab!=='structure';
    if(t.dataset.tab==='sql')$('navdb-sql-input')?.focus();
    if(t.dataset.tab==='structure')renderStructure();
  }));

  // ===== SQL =====
  const runSql=async()=>{
    const sql=($('navdb-sql-input')?.value||'').trim();if(!sql)return;
    const readOnly=$('navdb-readonly2')?.checked||true;
    $('navdb-result-meta').textContent='Đang thực thi...';$('navdb-result-wrap').innerHTML='';
    try{
      const d=await post('/api/sql/query',{csrf:csrfToken(),db_key:state.openDb,sql,readonly:readOnly?1:0});
      if(d.error){$('navdb-result-meta').innerHTML=`<span style="color:var(--danger,#c0392b)">${esc(d.error)}</span>`;tmsToast(d.error,'error');return;}
      if(d.message){$('navdb-result-meta').innerHTML=`<span style="color:var(--success,#27ae60)">${esc(d.message)}</span> (${d.time??0}s)`;tmsToast(d.message,'success');return;}
      if(Array.isArray(d.columns)&&d.columns.length>0){
        const ths=d.columns.map(c=>`<th>${esc(c)}</th>`).join('');
        const rows=(d.rows||[]).map(r=>`<tr>${d.columns.map(c=>{const v=r[c];return `<td title="${esc(String(v??''))}">${v===null?'<span class="navdb-null">NULL</span>':esc(String(v))}</td>`;}).join('')}</tr>`).join('');
        $('navdb-result-wrap').innerHTML=`<div class="navdb-table-wrap" style="max-height:50vh"><table class="navdb-grid"><thead><tr>${ths}</tr></thead><tbody>${rows}</tbody></table></div>`;
        $('navdb-result-meta').textContent=`${d.rowCount??0} dòng · ${d.time??0}s`;
      }else{
        $('navdb-result-meta').innerHTML=`<span style="color:var(--success,#27ae60)">Thành công</span> · ${d.time??0}s`;
        tmsToast('SQL thực thi thành công · '+(d.time??0)+'s','success');
      }
    }catch(e){$('navdb-result-meta').innerHTML=`<span style="color:var(--danger,#c0392b)">${esc(String(e.message))}</span>`;}
  };
  $('navdb-run-btn').addEventListener('click',runSql);
  $('navdb-sql-input')?.addEventListener('keydown',ev=>{if(ev.key==='Enter'&&(ev.ctrlKey||ev.metaKey)){ev.preventDefault();runSql();}});

  // ===== Structure =====
  const renderStructure=async()=>{
    if(!state.table){$('navdb-struct-empty').hidden=false;$('navdb-struct-wrap').innerHTML='';return;}
    $('navdb-struct-empty').hidden=true;
    const cols=state.columns;
    if(!cols||!cols.length){$('navdb-struct-wrap').innerHTML='<div class="navdb-msg">Không đọc được cấu trúc bảng.</div>';return;}
    const hasType=cols[0]&&('type' in cols[0]||'Type' in cols[0]);
    let ths='<th>Cột</th><th>Kiểu dữ liệu</th><th>Mặc định</th><th>NOT NULL</th><th>Khóa chính</th>';
    let rows=cols.map(c=>{
      const name=esc(String(c.name??c.Field??''));
      const type=esc(String(c.type??c.Type??''));
      const dflt=c.dflt_value??c.Default??'';
      const isPk=state.primaryKey.includes(String(c.name??c.Field??''));
      return `<tr><td><strong>${name}</strong></td><td class="navdb-col-type">${type}</td><td class="navdb-null">${esc(String(dflt))}</td><td>${c.notnull===1||c.Null==='NO'?'Có':'<span class="navdb-null">Không</span>'}</td><td>${isPk?'<span class="navdb-pk-badge">🔑 PRIMARY</span>':'—'}</td></tr>`;
    }).join('');
    $('navdb-struct-wrap').innerHTML=`<table class="navdb-grid"><thead><tr>${ths}</tr></thead><tbody>${rows}</tbody></table>`;
    // Cache DDL
    try{
      const ddlRes=await post('/api/sql/query',{csrf:csrfToken(),db_key:state.openDb,readonly:1,sql:state.driver==='SQLite'?`SELECT sql FROM sqlite_master WHERE type='table' AND name='${state.table.replace(/'/g,"''")}'`:`SHOW CREATE TABLE \`${state.table}\``});
      const r=(ddlRes?.rows?.[0]||{});
      state.ddls[state.table]=r.sql||r['Create Table']||'';
    }catch(_e){}
  };
  $('navdb-struct-copyddl').addEventListener('click',async()=>{
    const ddl=state.ddls[state.table];
    if(!ddl){tmsToast('Chưa tải được CREATE TABLE.','error');return;}
    try{
      if(navigator.clipboard){await navigator.clipboard.writeText(ddl);}
      else{const ta=document.createElement('textarea');ta.value=ddl;document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);}
      tmsToast('Đã sao chép CREATE TABLE vào clipboard.','success');
    }catch(e){tmsToast(String(e.message),'error');}
  });

  // ===== Insert =====
  $('navdb-insert-btn').addEventListener('click',async()=>{
    if(!state.table)return;
    $('navdb-insert-h').textContent='Thêm dòng vào bảng '+state.table;
    try{
      const s=await get(`/api/sql/structure?db_key=${encodeURIComponent(state.openDb)}&table=${encodeURIComponent(state.table)}`);
      const cols=Array.isArray(s?.columns)?s.columns:[];
      $('navdb-insert-form').innerHTML=cols.map(c=>{
        const nm=c.name||c.Field;const isPk=Array.isArray(s?.primary_key)?s.primary_key.includes(String(nm)):false;
        return `<label><span>${esc(nm)}${isPk?' <span class="navdb-chip">PK</span>':''}</span><input name="col__${esc(nm)}" placeholder="${esc(c.dflt_value??c.Default??'')}"${c.notnull===1?' required':''}></label>`;
      }).join('')+'<div style="display:flex;gap:8px"><button class="btn btn-primary" type="submit">Chèn dòng</button><button class="btn btn-ghost" type="button" data-modal-close>Hủy</button></div>';
      $('navdb-insert-form').onsubmit=async ev=>{
        ev.preventDefault();
        const values={};
        $('navdb-insert-form').querySelectorAll('input[name^="col__"]').forEach(i=>{values[i.name.replace('col__','')]=i.value;});
        try{
          await post('/api/sql/insert-row',{csrf:csrfToken(),db_key:state.openDb,table:state.table,values});
          $('navdb-insert-modal').classList.remove('show');
          await loadStructure();await loadTableData();
          tmsToast('Đã thêm dòng mới.','success');
        }catch(e){tmsToast(String(e.message),'error');}
      };
      $('navdb-insert-modal').classList.add('show');
    }catch(e){tmsToast(String(e.message),'error');}
  });

  // ===== Search shortcut Ctrl+F =====
  document.addEventListener('keydown',ev=>{
    if((ev.ctrlKey||ev.metaKey)&&ev.key==='f'&&path==='/databases'&&!ev.target.matches('input,textarea')){
      ev.preventDefault();
      if(!$('navdb-pane-browse').hidden)$('navdb-search')?.focus();
    }
  });

  // ===== Init =====
  if(state.dbs.length>0){
    pickDb(state.dbs[0].db_key);
    // Nếu có website db thì ưu tiên: mở db TMS OS đầu tiên (managed)
    const first=state.dbs.find(d=>d.source==='managed'||!d.site);
    if(first&&first.db_key!==state.dbs[0].db_key)pickDb(first.db_key);
  }else{
    $('navdb-db-list').innerHTML='<div class="navdb-empty-db" style="padding:10px 15px">Chưa có database nào. Bấm "+ Tạo database" để tạo.</div>';
  }
})();
