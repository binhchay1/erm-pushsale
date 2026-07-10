<?php

namespace App\Services\Marketing;

use App\Models\MarketingSource;

/**
 * Sinh JS theo dõi phiên Landing cho từng chiến dịch.
 *
 * Đoạn này được dán vào cả form chính và form upsell trang cảm ơn. Nó ưu tiên
 * session/client-ref do URL hoặc Auto Funnel chuyển sang, sau đó mới dùng
 * window.name/localStorage. Vì vậy vẫn hoạt động khi hai trang khác domain.
 */
class CampaignJsSnippetService
{
    /** Trả về JS thuần, không kèm thẻ <script>. */
    public function render(MarketingSource $campaign): string
    {
        $base = rtrim((string) config('app.url'), '/').'/api/v1/landing/'.$campaign->webhook_token;
        $baseJson = json_encode($base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tokenJson = json_encode($campaign->webhook_token, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $idleMs = max(1000, (int) config('saleops.landing.hold_seconds', 90) * 1000);
        $heartbeatMs = min(30_000, max(10_000, (int) floor($idleMs / 3)));

        return <<<JS
/* ERM SaleOps — Landing session + upsell merge window */
(function(){
  "use strict";
  var BASE={$baseJson}, TOKEN={$tokenJson}, IDLE={$idleMs}, HEARTBEAT={$heartbeatMs};
  var SKEY="saleops_sid_"+TOKEN, PREFKEY="saleops_pref_"+TOKEN, TKEY="saleops_submit_"+TOKEN;
  var isThankYou=/cam[-_ ]?on|thank|thankyou|cam_on/i.test(location.href);
  var sid=null, pref=null, lastPing=0, idleTimer=null;

  function clean(v){return typeof v==="string"&&/^[A-Za-z0-9_-]{8,64}$/.test(v)?v:null;}
  function uid(){
    try{if(crypto&&crypto.randomUUID)return crypto.randomUUID().replace(/-/g,"");}catch(e){}
    try{var a=new Uint8Array(16);crypto.getRandomValues(a);return Array.prototype.map.call(a,function(x){return x.toString(16).padStart(2,"0");}).join("");}catch(e){}
    return Date.now().toString(36)+Math.random().toString(36).slice(2)+Math.random().toString(36).slice(2);
  }
  function getParam(names){
    try{var q=new URLSearchParams(location.search);for(var i=0;i<names.length;i++){var v=clean(q.get(names[i]));if(v)return v;}}catch(e){}
    return null;
  }
  function getField(names,root){
    root=root||document;
    for(var i=0;i<names.length;i++){
      var el=root.querySelector('[name="'+names[i]+'"]');
      var v=clean(el&&el.value); if(v)return v;
    }
    return null;
  }
  function readWindowName(){
    try{
      if(typeof window.name!=="string"||window.name.indexOf("saleops:")!==0)return null;
      var d=JSON.parse(window.name.slice(8));
      if(d&&d.token===TOKEN&&Date.now()-(Number(d.ts)||0)<600000)return d;
    }catch(e){}
    return null;
  }
  function persist(){
    try{localStorage.setItem(SKEY,sid);localStorage.setItem(PREFKEY,pref);}catch(e){}
    try{window.name="saleops:"+JSON.stringify({token:TOKEN,sid:sid,pref:pref,ts:Date.now()});}catch(e){}
  }
  function hydrate(root){
    var w=readWindowName()||{};
    sid=getParam(["saleops_session","session_id","session_key"])
      ||getField(["session_id","session_key","saleops_session"],root)
      ||clean(w.sid)||sid;
    pref=getParam(["saleops_ref","saleops_client_ref","parent_ref","parent_submission_id"])
      ||getField(["saleops_client_ref","parent_ref","parent_submission_id"],root)
      ||clean(w.pref)||pref;
    if(!sid){try{sid=clean(localStorage.getItem(SKEY));}catch(e){}}
    if(!pref){try{pref=clean(localStorage.getItem(PREFKEY));}catch(e){}}
    if(!sid)sid=uid();
    if(!pref)pref=uid();
    persist();
  }
  function hidden(f,name,val){
    if(!val)return;
    var el=f.querySelector('input[name="'+name+'"]');
    if(!el){el=document.createElement("input");el.type="hidden";el.name=name;f.appendChild(el);}
    el.value=val;
  }
  function post(path,body,beacon){
    try{
      var data=JSON.stringify(Object.assign({session_id:sid,saleops_client_ref:pref},body||{}));
      if(beacon&&navigator.sendBeacon){
        navigator.sendBeacon(BASE+path,new Blob([data],{type:"application/json"}));
        return;
      }
      fetch(BASE+path,{method:"POST",mode:"cors",headers:{"Content-Type":"application/json","Accept":"application/json"},body:data,keepalive:true}).catch(function(){});
    }catch(e){}
  }
  function ping(force){
    var now=Date.now();
    if(force||now-lastPing>=HEARTBEAT){lastPing=now;post("/session/ping",{stage:isThankYou?"thankyou":"open"});}
  }
  function resetIdle(){
    clearTimeout(idleTimer);
    ping(false);
    idleTimer=setTimeout(function(){post("/session/close",{},true);},IDLE);
  }
  function attach(){
    var forms=document.querySelectorAll("form");
    for(var i=0;i<forms.length;i++){(function(f){
      hydrate(f);
      hidden(f,"session_id",sid);
      hidden(f,"saleops_client_ref",pref);
      if(isThankYou){hidden(f,"parent_submission_id",pref);hidden(f,"parent_ref",pref);hidden(f,"is_upsell","1");hidden(f,"item_type","upsell");}
      if(f.__saleops)return; f.__saleops=1;
      f.addEventListener("submit",function(){
        hydrate(f);
        hidden(f,"session_id",sid);
        hidden(f,"saleops_client_ref",pref);
        if(isThankYou){hidden(f,"parent_submission_id",pref);hidden(f,"parent_ref",pref);hidden(f,"is_upsell","1");hidden(f,"item_type","upsell");}
        persist();
        try{localStorage.setItem(TKEY,String(Date.now()));}catch(e){}
        ping(true);
      },true);
    })(forms[i]);}
  }

  hydrate(document);
  attach();
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",attach);
  try{new MutationObserver(attach).observe(document.documentElement,{childList:true,subtree:true});}catch(e){}

  post("/session/start",{});
  ping(true);
  var evs=["mousemove","keydown","scroll","touchstart","click","change"];
  for(var j=0;j<evs.length;j++)document.addEventListener(evs[j],resetIdle,{passive:true});
  document.addEventListener("visibilitychange",function(){if(!document.hidden)ping(true);});
  setInterval(function(){if(!document.hidden)ping(false);},HEARTBEAT);
  resetIdle();

  function onLeave(){
    var ts=0;try{ts=parseInt(localStorage.getItem(TKEY)||"0",10);}catch(e){}
    if(ts&&Date.now()-ts<12000)return; /* đang chuyển từ form chính sang trang cảm ơn */
    post("/session/close",{},true);
  }
  window.addEventListener("pagehide",onLeave);
  window.addEventListener("beforeunload",onLeave);
})();
JS;
    }
}
