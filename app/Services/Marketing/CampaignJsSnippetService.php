<?php

namespace App\Services\Marketing;

use App\Models\MarketingSource;

/**
 * Sinh đoạn JS theo dõi phiên RIÊNG cho từng chiến dịch Landing.
 *
 * Marketing dán đoạn này vào ô "Mã tuỳ chỉnh nâng cao" của FORM trên Ladipage
 * (cả form đặt hàng lẫn form upsale trang cảm ơn). JS sẽ:
 *  - Tạo/gắn 1 session_id (localStorage) → mọi gói tin cùng khách gom về 1 đơn.
 *  - Báo hệ thống khi khách mở trang, xem trang cảm ơn, và khi đóng/rời trang
 *    (để chốt & chia số ngay, không phải chờ cứng).
 *
 * Lưu ý: Ladipage yêu cầu dán JS THUẦN, KHÔNG kèm thẻ <script>.
 */
class CampaignJsSnippetService
{
    /** Trả về JS thuần (không có thẻ script) cho chiến dịch. */
    public function render(MarketingSource $campaign): string
    {
        $base = rtrim((string) config('app.url'), '/').'/api/v1/landing/'.$campaign->webhook_token;
        $baseJson = json_encode($base, JSON_UNESCAPED_SLASHES);
        $tokenJson = json_encode($campaign->webhook_token);
        $idleMs = (int) config('saleops.landing.hold_seconds', 90) * 1000;

        return <<<JS
/* ERM SaleOps — theo dõi phiên Landing (chiến dịch: {$campaign->name}) */
(function(){
  var BASE={$baseJson}, TOKEN={$tokenJson};
  var SKEY="saleops_sid_"+TOKEN, TKEY="saleops_submit_"+TOKEN, IDLE={$idleMs};
  function uid(){return Date.now().toString(36)+Math.random().toString(36).slice(2,10);}
  var sid;
  try{sid=localStorage.getItem(SKEY);}catch(e){}
  if(!sid){sid=uid();try{localStorage.setItem(SKEY,sid);}catch(e){}}
  function post(path,body,beacon){
    try{
      var data=JSON.stringify(Object.assign({session_id:sid},body||{}));
      if(beacon&&navigator.sendBeacon){navigator.sendBeacon(BASE+path,new Blob([data],{type:"application/json"}));return;}
      fetch(BASE+path,{method:"POST",headers:{"Content-Type":"application/json"},body:data,keepalive:true}).catch(function(){});
    }catch(e){}
  }
  function attach(){
    var forms=document.querySelectorAll("form");
    for(var i=0;i<forms.length;i++){(function(f){
      if(f.__saleops)return; f.__saleops=1;
      f.addEventListener("submit",function(){
        var el=f.querySelector('input[name="session_id"]');
        if(!el){el=document.createElement("input");el.type="hidden";el.name="session_id";f.appendChild(el);}
        el.value=sid;
        try{localStorage.setItem(TKEY,String(Date.now()));}catch(e){}
      },true);
    })(forms[i]);}
  }
  attach();
  if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",attach);}
  try{new MutationObserver(attach).observe(document.documentElement,{childList:true,subtree:true});}catch(e){}
  var isThankYou=/cam[-_ ]?on|thank|thankyou|cam_on/i.test(location.href);
  post("/session/start",{});
  post("/session/ping",{stage:isThankYou?"thankyou":"open"});
  var t;
  function resetIdle(){clearTimeout(t);t=setTimeout(function(){post("/session/close",{},true);},IDLE);}
  var evs=["mousemove","keydown","scroll","touchstart","click"];
  for(var j=0;j<evs.length;j++){document.addEventListener(evs[j],resetIdle,{passive:true});}
  resetIdle();
  function onLeave(){
    var ts=0; try{ts=parseInt(localStorage.getItem(TKEY)||"0",10);}catch(e){}
    if(ts&&(Date.now()-ts)<8000){return;} /* vừa submit → đang chuyển sang trang cảm ơn, chưa đóng phiên */
    post("/session/close",{},true);
  }
  window.addEventListener("pagehide",onLeave);
  window.addEventListener("beforeunload",onLeave);
})();
JS;
    }
}
