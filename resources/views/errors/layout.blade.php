<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'en') ? 'en' : 'vi' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('saleops.brand.name', 'ERM SaleOps') }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { min-height: 100vh; margin: 0; }
        body {
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: radial-gradient(circle at top, rgba(255,255,255,.96), rgba(248,250,252,.93) 40%, rgba(235,243,250,.96) 100%);
            color: #0f172a;
            overflow-x: hidden;
        }
        .page {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .bg-orb,
        .bg-orb-two,
        .bg-orb-three {
            position: absolute;
            border-radius: 999px;
            filter: blur(48px);
            pointer-events: none;
        }
        .bg-orb { width: 220px; height: 220px; left: -40px; top: 36px; background: rgba(59,130,246,.11); }
        .bg-orb-two { width: 260px; height: 260px; right: -50px; top: 60px; background: rgba(251,113,133,.12); }
        .bg-orb-three { width: 260px; height: 260px; left: 50%; bottom: -70px; transform: translateX(-50%); background: rgba(125,211,252,.15); }
        .panel {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1120px;
            border-radius: 32px;
            background: rgba(255,255,255,.93);
            border: 1px solid rgba(255,255,255,.85);
            box-shadow: 0 26px 80px rgba(15,23,42,.12);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .panel-top {
            position: absolute;
            inset: 0 0 auto 0;
            height: 140px;
            background: linear-gradient(180deg, var(--accent-soft, rgba(251,146,60,.12)), transparent);
            pointer-events: none;
        }
        .panel-grid {
            display: grid;
            grid-template-columns: 1.06fr .94fr;
            gap: 24px;
            position: relative;
            padding: 24px;
        }
        .copy { padding: 12px 12px 12px 8px; }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .brand-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: #0ea5e9;
            color: white;
            font-weight: 800;
            box-shadow: 0 12px 24px rgba(14,165,233,.22);
        }
        .brand-name { font-weight: 700; font-size: 15px; }
        .brand-tag { font-size: 12px; color: #64748b; margin-top: 3px; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #f8fafc;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .24em;
            text-transform: uppercase;
            color: #64748b;
        }
        h1 {
            margin: 18px 0 0;
            font-size: 40px;
            line-height: 1.1;
            letter-spacing: -.03em;
        }
        .subtitle {
            margin-top: 10px;
            color: #0ea5e9;
            font-size: 20px;
            font-weight: 700;
        }
        .desc {
            margin-top: 18px;
            font-size: 15px;
            line-height: 1.85;
            color: #475569;
        }
        .desc p { margin: 0 0 6px; }
        .hint {
            margin-top: 18px;
            border: 1px dashed #dbe2ea;
            background: rgba(248,250,252,.9);
            border-radius: 18px;
            padding: 16px 18px;
        }
        .hint strong {
            display: block;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .hint p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            line-height: 1.75;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }
        .btn {
            appearance: none;
            border: 1px solid #d8e0eb;
            background: white;
            color: #0f172a;
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #0ea5e9;
            border-color: #0ea5e9;
            color: #fff;
            box-shadow: 0 12px 22px rgba(14,165,233,.20);
        }
        .art {
            min-height: 520px;
            border: 1px solid #f1f5f9;
            border-radius: 28px;
            background: linear-gradient(180deg, rgba(255,255,255,.85), rgba(248,250,252,.92));
            box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            text-align: center;
        }
        .art-wrap { width: 100%; max-width: 380px; }
        .donut {
            position: relative;
            width: 248px;
            height: 248px;
            margin: 6px auto 16px;
            border-radius: 50%;
            background: conic-gradient(var(--accent-light, #fdba74) 0deg, var(--accent-main, #fb923c) 310deg, #f8d89f 310deg 360deg);
            box-shadow: 0 30px 60px rgba(15,23,42,.10);
        }
        .donut::before {
            content: '';
            position: absolute;
            inset: 44px;
            border-radius: 50%;
            background: #fff8eb;
            box-shadow: inset 0 4px 10px rgba(15,23,42,.05);
        }
        .donut::after {
            content: '';
            position: absolute;
            width: 82px;
            height: 82px;
            right: -2px;
            top: 14px;
            background: radial-gradient(circle at left bottom, transparent 0 42px, rgba(248,215,155,.98) 43px 100%);
            transform: rotate(22deg);
            border-radius: 12px 50% 50% 12px;
        }
        .face {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            z-index: 2;
            pointer-events: none;
        }
        .code {
            font-weight: 900;
            font-size: 34px;
            color: #334155;
            margin-top: 48px;
            letter-spacing: -.04em;
        }
        .eyes {
            position: absolute;
            top: 95px;
            left: 50%;
            width: 72px;
            transform: translateX(-50%);
            display: flex;
            justify-content: space-between;
        }
        .eye {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #1f2937;
        }
        .smile {
            position: absolute;
            top: 122px;
            left: 50%;
            width: 42px;
            height: 22px;
            transform: translateX(-50%);
            border-bottom: 5px solid #1f2937;
            border-radius: 0 0 42px 42px;
        }
        .crumb,
        .crumb-two,
        .crumb-three {
            position: absolute;
            border-radius: 999px;
            background: var(--accent-main, #fb923c);
            opacity: .9;
        }
        .crumb { width: 18px; height: 18px; right: 50px; top: 66px; }
        .crumb-two { width: 10px; height: 10px; right: 28px; top: 106px; }
        .crumb-three { width: 8px; height: 8px; right: 48px; top: 30px; background: #fff; }
        .sprinkle,
        .sprinkle-two,
        .sprinkle-three,
        .sprinkle-four,
        .sprinkle-five,
        .sprinkle-six {
            position: absolute;
            width: 14px;
            height: 5px;
            border-radius: 999px;
        }
        .sprinkle { left: 78px; top: 72px; transform: rotate(-16deg); background: #fff; }
        .sprinkle-two { left: 103px; top: 61px; transform: rotate(12deg); background: #7dd3fc; }
        .sprinkle-three { left: 138px; top: 74px; transform: rotate(24deg); background: rgba(255,255,255,.94); }
        .sprinkle-four { left: 80px; top: 114px; transform: rotate(14deg); background: var(--accent-soft-chip, #fda4af); }
        .sprinkle-five { left: 118px; top: 122px; transform: rotate(-22deg); background: #fff; }
        .sprinkle-six { left: 148px; top: 110px; transform: rotate(18deg); background: #7dd3fc; }
        .art-title { font-size: 15px; font-weight: 700; color: #1e293b; }
        .art-subtitle { margin-top: 6px; font-size: 14px; color: #64748b; line-height: 1.7; }
        @media (max-width: 960px) {
            .panel-grid { grid-template-columns: 1fr; }
            .art { min-height: auto; }
            .copy { order: 2; }
            .art { order: 1; }
            h1 { font-size: 34px; }
        }
        @media (max-width: 640px) {
            .page { padding: 18px 12px; }
            .panel { border-radius: 26px; }
            .panel-grid { padding: 16px; gap: 14px; }
            .copy { padding: 8px 2px 2px; }
            .art { padding: 18px 12px; border-radius: 22px; }
            .donut { width: 214px; height: 214px; }
            .donut::before { inset: 38px; }
            .eyes { top: 82px; width: 62px; }
            .smile { top: 107px; }
            .code { margin-top: 42px; font-size: 30px; }
            h1 { font-size: 30px; }
            .subtitle { font-size: 18px; }
            .desc, .hint p { font-size: 14px; }
            .actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="page"
     style="--accent-main:@yield('accent_main', '#fb923c'); --accent-light:@yield('accent_light', '#fdba74'); --accent-soft:@yield('accent_soft', 'rgba(251,146,60,.12)'); --accent-soft-chip:@yield('accent_chip', '#fda4af');">
    <div class="bg-orb"></div>
    <div class="bg-orb-two"></div>
    <div class="bg-orb-three"></div>

    <div class="panel">
        <div class="panel-top"></div>
        <div class="panel-grid">
            <div class="copy">
                <div class="brand">
                    <div class="brand-icon">S</div>
                    <div>
                        <div class="brand-name">{{ config('saleops.brand.name', 'ERM SaleOps') }}</div>
                        <div class="brand-tag">{{ config('saleops.brand.tagline', 'CRM / ERP / Sales Operations Platform') }}</div>
                    </div>
                </div>

                <div class="badge">@yield('code')</div>
                <h1>@yield('vi_title')</h1>
                <div class="subtitle">@yield('en_title')</div>

                <div class="desc">
                    <p>@yield('vi_desc')</p>
                    <p>@yield('en_desc')</p>
                </div>

                <div class="hint">
                    <strong>Gợi ý xử lý / Suggested next step</strong>
                    <p>@yield('vi_hint')</p>
                    <p>@yield('en_hint')</p>
                </div>

                <div class="actions">@yield('actions')</div>
            </div>

            <div class="art">
                <div class="art-wrap">
                    <div class="donut">
                        <div class="sprinkle"></div>
                        <div class="sprinkle-two"></div>
                        <div class="sprinkle-three"></div>
                        <div class="sprinkle-four"></div>
                        <div class="sprinkle-five"></div>
                        <div class="sprinkle-six"></div>
                        <div class="crumb"></div>
                        <div class="crumb-two"></div>
                        <div class="crumb-three"></div>
                        <div class="face">
                            <div class="eyes"><span class="eye"></span><span class="eye"></span></div>
                            <div class="smile"></div>
                            <div class="code">@yield('code')</div>
                        </div>
                    </div>
                    <div class="art-title">Oops! Có vẻ có thứ gì đó đang bị thiếu hoặc chưa sẵn sàng.</div>
                    <div class="art-subtitle">Oops! It looks like something is missing or not ready right now.</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
