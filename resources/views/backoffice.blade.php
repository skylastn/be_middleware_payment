<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Backoffice - Middleware Payment</title>
        <script>
            (function() {
                try {
                    var theme = localStorage.getItem('backoffice_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-theme', theme);
                } catch(e) {}
            })();
        </script>
        <style>
            html, body { margin: 0; padding: 0; min-height: 100vh; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            [data-theme="dark"] body { background: #0b0f17; color: #f3f4f6; }
            [data-theme="light"] body { background: #f8fafc; color: #0f172a; }
            .initial-preloader {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 16px;
            }
            .initial-mark {
                width: 52px;
                height: 52px;
                border-radius: 14px;
                background: linear-gradient(135deg, #0284c7 0%, #6366f1 100%);
                color: #ffffff;
                display: grid;
                place-items: center;
                font-size: 19px;
                font-weight: 800;
                box-shadow: 0 8px 24px rgba(2, 132, 199, 0.35);
            }
            .initial-spinner {
                width: 22px;
                height: 22px;
                border: 2.5px solid rgba(150, 150, 150, 0.25);
                border-top-color: #0284c7;
                border-radius: 50%;
                animation: init-spin 0.8s linear infinite;
            }
            @keyframes init-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    </head>
    <body>
        <div id="backoffice-root">
            <div class="initial-preloader">
                <div class="initial-mark">MP</div>
                <div class="initial-spinner"></div>
            </div>
        </div>
    </body>
</html>
