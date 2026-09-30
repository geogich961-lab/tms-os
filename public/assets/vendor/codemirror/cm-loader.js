/* TMS-OS CodeMirror loader: ghép các chunk base64 thành CodeMirror, nạp offline không cần CDN.
 * Cách dùng: window.tmsLoadCodeMirror(baseUrl, version, cmMode).then(function(ok){...}) */
(function(){
  'use strict';
  var MODE_DEPS={
    'php':['xml','javascript','css','htmlmixed','clike','php'],
    'htmlmixed':['xml','javascript','css','htmlmixed'],
    'javascript':['javascript'],
    'application/json':['javascript'],
    'css':['css'],
    'xml':['xml'],
    'text/x-sql':['sql'],
    'shell':['shell'],
    'yaml':['yaml'],
    'markdown':['markdown']
  };
  function loadScript(src){
    return new Promise(function(res,rej){
      var s=document.createElement('script');
      s.src=src;
      s.onload=function(){res();};
      s.onerror=function(){rej(new Error('Không tải được '+src));};
      document.head.appendChild(s);
    });
  }
  window.tmsLoadCodeMirror=function(base,version,mode){
    base=String(base||'/assets/vendor/codemirror/').replace(/\/?$/,'/');
    var ver=version?'?v='+encodeURIComponent(version):'';
    function boot(){
      var modes=MODE_DEPS[mode]||[],chain=Promise.resolve();
      modes.forEach(function(m){
        chain=chain.then(function(){return loadScript(base+'mode/'+m+'/'+m+'.min.js'+ver);});
      });
      return chain.then(function(){return typeof window.CodeMirror!=='undefined';});
    }
    try{
      if(typeof window.CodeMirror!=='undefined') return boot();
      var b64=window._tmsCMB64||'';
      if(!b64) return Promise.resolve(false);
      var bin=atob(b64),bytes=new Uint8Array(bin.length),i;
      for(i=0;i<bin.length;i++) bytes[i]=bin.charCodeAt(i);
      var url=URL.createObjectURL(new Blob([bytes],{type:'text/javascript'}));
      return loadScript(url).then(function(){
        try{URL.revokeObjectURL(url);}catch(e){}
        window._tmsCMB64=null;
        return boot();
      },function(){return false;});
    }catch(e){ return Promise.resolve(false); }
  };
})();
