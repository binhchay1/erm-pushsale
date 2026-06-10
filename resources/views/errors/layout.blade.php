<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('saleops.brand.name', 'ERM SaleOps') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 50%, #f8fafc 100%);
            color: #0f172a;
        }
        .card {
            width: 100%;
            max-width: 28rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 10px 40px -10px rgba(37, 99, 235, 0.12);
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        .code {
            position: absolute;
            top: -0.5rem;
            right: 0.5rem;
            font-size: 5rem;
            font-weight: 800;
            line-height: 1;
            color: rgba(15, 23, 42, 0.06);
            user-select: none;
        }
        .brand {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .brand strong { font-size: 0.875rem; }
        .brand p { font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; }
        h1 { font-size: 1.25rem; margin-bottom: 0.5rem; }
        .status { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.25rem; }
        p.desc { font-size: 0.875rem; line-height: 1.6; color: #64748b; margin-bottom: 1.5rem; }
        .actions { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        a, button {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-outline { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="card">
        <span class="code">@yield('code')</span>
        <div class="brand">
            <strong>{{ config('saleops.brand.name', 'ERM SaleOps') }}</strong>
            <p>{{ config('saleops.brand.tagline', '') }}</p>
        </div>
        @yield('content')
    </div>
</body>
</html>
