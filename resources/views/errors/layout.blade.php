<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'en') ? 'en' : 'vi' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('saleops.brand.name', 'ERM SaleOps') }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { min-height: 100vh; margin: 0; }
        body { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: radial-gradient(circle at top, #fff, #f8fafc 44%, #edf3fa 100%); color: #0f172a; overflow: hidden; }
        .page { position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .orb { position: absolute; border-radius: 999px; filter: blur(54px); pointer-events: none; }
        .orb.one { width: 260px; height: 260px; left: -60px; top: 40px; background: rgba(14,165,233,.12); }
        .orb.two { width: 320px; height: 320px; right: -100px; top: 90px; background: rgba(251,113,133,.12); }
        .orb.three { width: 340px; height: 340px; left: 45%; bottom: -120px; background: rgba(191,219,254,.7); }
        .panel { position: relative; z-index: 1; width: min(1120px, 100%); height: min(720px, calc(100vh - 32px)); min-height: 560px; display: grid; grid-template-columns: 1fr 1fr; overflow: hidden; border: 1px solid rgba(255,255,255,.9); border-radius: 32px; background: rgba(255,255,255,.96); box-shadow: 0 28px 90px rgba(15,23,42,.14); }
        .copy { position: relative; min-width: 0; display: flex; flex-direction: column; overflow-y: auto; padding: 42px 48px; }
        .copy:before { content: ''; position: absolute; inset: 0 0 auto; height: 150px; background: linear-gradient(180deg, var(--accent-soft, rgba(251,146,60,.12)), transparent); pointer-events: none; }
        .brand { position: relative; display: flex; align-items: center; gap: 12px; }
        .brand-icon { width: 46px; height: 46px; display: grid; place-items: center; border-radius: 16px; background: #0ea5e9; color: #fff; font-weight: 900; box-shadow: 0 12px 24px rgba(14,165,233,.22); }
        .brand-name { font-size: 15px; font-weight: 800; }
        .brand-tag { margin-top: 3px; font-size: 12px; color: #64748b; }
        .badge { position: relative; margin-top: 34px; width: fit-content; border: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; border-radius: 999px; padding: 7px 12px; font-size: 11px; font-weight: 800; letter-spacing: .22em; text-transform: uppercase; }
        h1 { position: relative; margin: 28px 0 0; font-size: clamp(34px, 4.2vw, 54px); line-height: 1.06; letter-spacing: -.045em; font-weight: 950; color: #111827; }
        .desc { position: relative; margin-top: 22px; max-width: 430px; font-size: 15px; line-height: 1.8; color: #475569; }
        .hint { position: relative; margin-top: 24px; max-width: 430px; border: 1px dashed #dbe2ea; border-radius: 18px; background: rgba(248,250,252,.88); padding: 16px 18px; }
        .hint strong { display: block; font-size: 14px; margin-bottom: 8px; }
        .hint p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.7; }
        .actions { position: relative; display: flex; flex-wrap: wrap; gap: 12px; margin-top: auto; padding-top: 28px; }
        .btn { appearance: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 11px 18px; border-radius: 14px; border: 1px solid #d8e0eb; background: #fff; color: #0f172a; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .btn-primary { background: #0ea5e9; border-color: #0ea5e9; color: #fff; box-shadow: 0 12px 22px rgba(14,165,233,.20); }
        .art { position: relative; min-width: 0; border-left: 1px solid #f1f5f9; background: linear-gradient(180deg, rgba(255,255,255,.9), rgba(248,250,252,.96)); display: flex; align-items: center; justify-content: center; padding: 48px; text-align: center; }
        .art-card { width: min(430px, 100%); }
        .donut { position: relative; width: min(330px, 82vw); height: min(330px, 82vw); margin: 0 auto 22px; border-radius: 50%; background: conic-gradient(var(--accent-light, #fdba74), var(--accent-main, #fb923c) 310deg, #f8d89f 310deg 360deg); box-shadow: 0 30px 60px rgba(15,23,42,.10); }
        .donut:before { content: ''; position: absolute; inset: 19%; border-radius: 50%; background: #fff8eb; box-shadow: inset 0 4px 10px rgba(15,23,42,.05); }
        .donut:after { content: ''; position: absolute; width: 31%; height: 31%; right: 0; top: 6%; background: radial-gradient(circle at left bottom, transparent 0 51%, rgba(248,215,155,.98) 52% 100%); transform: rotate(22deg); border-radius: 12px 50% 50% 12px; }
        .code { position: absolute; left: 50%; bottom: 18%; transform: translateX(-50%); z-index: 2; font-size: 26px; font-weight: 950; color: #334155; letter-spacing: -.04em; }
        .eyes { position: absolute; top: 39%; left: 50%; width: 28%; transform: translateX(-50%); display: flex; justify-content: space-between; z-index: 2; }
        .eye { width: 10px; height: 10px; border-radius: 999px; background: #111827; }
        .smile { position: absolute; top: 51%; left: 50%; width: 17%; height: 9%; transform: translateX(-50%); border-bottom: 6px solid #111827; border-radius: 0 0 999px 999px; z-index: 2; }
        .sprinkle { position: absolute; width: 18px; height: 6px; border-radius: 999px; z-index: 2; }
        .s1 { left: 30%; top: 25%; transform: rotate(-16deg); background: #fff; }
        .s2 { left: 43%; top: 22%; transform: rotate(12deg); background: #7dd3fc; }
        .s3 { right: 30%; top: 29%; transform: rotate(24deg); background: #fff; }
        .s4 { left: 30%; top: 47%; transform: rotate(14deg); background: var(--accent-chip, #fdba74); }
        .s5 { right: 34%; top: 50%; transform: rotate(-20deg); background: #fff; }
        .art-title { font-size: 16px; font-weight: 800; color: #1e293b; }
        .art-desc { margin-top: 8px; font-size: 14px; line-height: 1.7; color: #64748b; }
        @media (max-width: 980px) { body { overflow: auto; } .page { align-items: flex-start; padding: 16px; } .panel { height: auto; min-height: 0; grid-template-columns: 1fr; } .art { order: -1; border-left: 0; border-bottom: 1px solid #f1f5f9; padding: 28px 20px; } .donut { width: 220px; height: 220px; } .copy { padding: 28px 26px 30px; } h1 { font-size: 40px; } }
        @media (max-width: 560px) { .page { padding: 10px; } .panel { border-radius: 24px; } .copy { padding: 22px 18px 24px; } .actions .btn { width: 100%; } h1 { font-size: 34px; } .desc, .hint p { font-size: 14px; } .hint { padding: 14px; } }
    </style>
</head>
<body>
@php($isEn = str_starts_with(app()->getLocale(), 'en'))
<div class="page" style="--accent-main:@yield('accent_main', '#fb923c'); --accent-light:@yield('accent_light', '#fdba74'); --accent-soft:@yield('accent_soft', 'rgba(251,146,60,.12)'); --accent-chip:@yield('accent_chip', '#fdba74');">
    <div class="orb one"></div><div class="orb two"></div><div class="orb three"></div>
    <main class="panel">
        <section class="copy">
            <div class="brand">
                <div class="brand-icon">S</div>
                <div><div class="brand-name">{{ config('saleops.brand.name', 'ERM SaleOps') }}</div><div class="brand-tag">{{ config('saleops.brand.tagline', $isEn ? 'Sales and operations management system' : 'Hệ thống điều hành bán hàng và vận hành') }}</div></div>
            </div>
            <div class="badge">{{ $isEn ? 'Error' : 'Mã lỗi' }} @yield('code')</div>
            <h1>@yield($isEn ? 'en_title' : 'vi_title')</h1>
            <p class="desc">@yield($isEn ? 'en_desc' : 'vi_desc')</p>
            <div class="hint"><strong>{{ $isEn ? 'Suggested next step' : 'Gợi ý xử lý' }}</strong><p>@yield($isEn ? 'en_hint' : 'vi_hint')</p></div>
            <div class="actions">@yield($isEn ? 'actions_en' : 'actions_vi')</div>
        </section>
        <section class="art">
            <div class="art-card">
                <div class="donut"><span class="sprinkle s1"></span><span class="sprinkle s2"></span><span class="sprinkle s3"></span><span class="sprinkle s4"></span><span class="sprinkle s5"></span><div class="eyes"><span class="eye"></span><span class="eye"></span></div><div class="smile"></div><div class="code">@yield('code')</div></div>
                <div class="art-title">{{ $isEn ? 'Oops! It looks like something is missing or not ready right now.' : 'Oops! Có vẻ có thứ gì đó đang bị thiếu hoặc chưa sẵn sàng.' }}</div>
                <div class="art-desc">{{ $isEn ? 'You can try reloading the page or return to the previous screen.' : 'Bạn có thể thử tải lại trang hoặc quay về màn hình trước đó.' }}</div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
