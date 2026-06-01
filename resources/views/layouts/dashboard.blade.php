<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Dashboard' }} - Middleware Payment</title>
        <style>
            :root {
                --bg: #eef2f6;
                --surface: #ffffff;
                --surface-soft: #f8fafc;
                --surface-tint: #f1f7f6;
                --border: #d9e2ec;
                --border-strong: #c8d4e0;
                --text: #111827;
                --text-soft: #344054;
                --muted: #667085;
                --accent: #0f766e;
                --accent-strong: #115e59;
                --blue: #2563eb;
                --purple: #7c3aed;
                --danger: #b42318;
                --warning: #b54708;
                --success: #067647;
                --shadow: 0 16px 40px rgba(15, 23, 42, .08);
            }

            * { box-sizing: border-box; }
            html { min-width: 320px; }
            body {
                margin: 0;
                background:
                    linear-gradient(180deg, #f7fafc 0, var(--bg) 360px);
                color: var(--text);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                font-size: 14px;
                line-height: 1.45;
            }
            a { color: inherit; text-decoration: none; }
            button, input { font: inherit; }
            .shell { min-height: 100vh; }
            .topbar {
                position: sticky;
                top: 0;
                z-index: 10;
                border-bottom: 1px solid rgba(217, 226, 236, .82);
                background: rgba(255, 255, 255, .9);
                backdrop-filter: blur(16px);
            }
            .topbar-inner {
                max-width: 1240px;
                margin: 0 auto;
                padding: 12px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 18px;
            }
            .brand {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                min-width: 0;
                font-weight: 750;
                color: var(--text);
            }
            .brand-mark {
                width: 34px;
                height: 34px;
                display: grid;
                place-items: center;
                border-radius: 8px;
                background: #0f766e;
                color: #ffffff;
                font-size: 13px;
                letter-spacing: 0;
                box-shadow: inset 0 -10px 18px rgba(0, 0, 0, .14);
            }
            .brand-copy { min-width: 0; }
            .brand-title { display: block; line-height: 1.1; }
            .brand-subtitle { display: block; color: var(--muted); font-size: 12px; font-weight: 600; margin-top: 2px; }
            .userbar { display: flex; align-items: center; gap: 12px; color: var(--muted); }
            .user-chip {
                display: inline-flex;
                align-items: center;
                gap: 9px;
                min-width: 0;
                padding: 5px 6px 5px 5px;
                border: 1px solid var(--border);
                border-radius: 999px;
                background: #ffffff;
            }
            .avatar {
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: grid;
                place-items: center;
                background: #e8f3f1;
                color: var(--accent-strong);
                font-size: 12px;
                font-weight: 800;
            }
            .button {
                min-height: 36px;
                border: 1px solid var(--border-strong);
                background: var(--surface);
                border-radius: 8px;
                padding: 8px 12px;
                color: var(--text);
                cursor: pointer;
                font-weight: 700;
                transition: background-color .16s ease, border-color .16s ease, color .16s ease, transform .16s ease;
            }
            .button:hover { border-color: #b4c2d0; background: var(--surface-soft); }
            .button:active { transform: translateY(1px); }
            .button.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
            .button.primary:hover { background: var(--accent-strong); border-color: var(--accent-strong); }
            .content { max-width: 1240px; margin: 0 auto; padding: 26px 20px 46px; }
            .page-title {
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
                gap: 16px;
                margin-bottom: 18px;
            }
            h1 { margin: 0; font-size: 28px; line-height: 1.16; letter-spacing: 0; }
            .subtitle { margin-top: 7px; color: var(--muted); max-width: 680px; }
            .eyebrow {
                color: var(--accent-strong);
                font-size: 12px;
                font-weight: 800;
                letter-spacing: .08em;
                text-transform: uppercase;
            }
            .toolbar {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }
            .timestamp {
                display: inline-flex;
                align-items: center;
                min-height: 34px;
                border: 1px solid var(--border);
                background: rgba(255, 255, 255, .72);
                border-radius: 999px;
                padding: 7px 11px;
                color: var(--text-soft);
                font-size: 12px;
                font-weight: 700;
            }
            .grid { display: grid; gap: 14px; }
            .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 14px; }
            .columns { grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr); align-items: start; margin-bottom: 14px; }
            .panel {
                background: rgba(255, 255, 255, .94);
                border: 1px solid var(--border);
                border-radius: 8px;
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            .panel-header {
                min-height: 58px;
                padding: 14px 16px;
                border-bottom: 1px solid var(--border);
                background: linear-gradient(180deg, #ffffff, #fbfdff);
                display: flex;
                justify-content: space-between;
                gap: 12px;
                align-items: center;
            }
            .panel-title { margin: 0; font-size: 15px; font-weight: 800; letter-spacing: 0; }
            .panel-kicker { color: var(--muted); font-size: 12px; margin-top: 2px; }
            .stat {
                position: relative;
                padding: 16px;
                min-height: 132px;
            }
            .stat::before {
                content: "";
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--accent);
            }
            .stat.blue::before { background: var(--blue); }
            .stat.purple::before { background: var(--purple); }
            .stat.warning::before { background: var(--warning); }
            .stat.danger::before { background: var(--danger); }
            .stat-label { color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
            .stat-value { margin-top: 10px; font-size: 32px; font-weight: 820; line-height: 1; letter-spacing: 0; }
            .stat-note { margin-top: 10px; color: var(--muted); font-size: 12px; }
            .stat-mini {
                min-height: 104px;
                background: linear-gradient(180deg, #ffffff, var(--surface-soft));
            }
            .table-wrap { width: 100%; overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 12px 14px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: middle; }
            th {
                color: var(--muted);
                font-size: 11px;
                font-weight: 800;
                letter-spacing: .06em;
                text-transform: uppercase;
                background: var(--surface-soft);
                white-space: nowrap;
            }
            td { color: var(--text-soft); }
            tbody tr:hover td { background: #fbfdff; }
            tr:last-child td { border-bottom: 0; }
            .mono {
                font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                font-size: 12px;
                color: #334155;
                word-break: break-word;
            }
            .badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 24px;
                border-radius: 999px;
                padding: 4px 9px;
                font-size: 12px;
                font-weight: 800;
                background: #eef4ff;
                color: #3538cd;
                white-space: nowrap;
            }
            .badge.success { background: #ecfdf3; color: var(--success); }
            .badge.warning { background: #fffaeb; color: var(--warning); }
            .badge.danger { background: #fef3f2; color: var(--danger); }
            .list { padding: 8px 16px 16px; display: grid; gap: 4px; }
            .list-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                min-height: 48px;
                padding: 9px 0;
                border-bottom: 1px solid var(--border);
            }
            .list-row:last-child { border-bottom: 0; }
            .list-main { min-width: 0; }
            .list-title { display: block; font-weight: 760; color: var(--text); }
            .muted { color: var(--muted); }
            .empty { padding: 22px; color: var(--muted); text-align: center; }
            .login-wrap {
                width: min(440px, 100%);
                margin: 62px auto;
            }
            .form-body { padding: 18px; display: grid; gap: 15px; }
            .field { display: grid; gap: 7px; }
            .label { color: var(--text-soft); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
            .input {
                width: 100%;
                min-height: 42px;
                padding: 10px 12px;
                border: 1px solid var(--border-strong);
                border-radius: 8px;
                background: #ffffff;
                color: var(--text);
                outline: none;
                transition: border-color .16s ease, box-shadow .16s ease;
            }
            .input:focus {
                border-color: var(--accent);
                box-shadow: 0 0 0 4px rgba(15, 118, 110, .12);
            }
            .check-row { display: flex; align-items: center; gap: 9px; color: var(--muted); }
            .check-row input { width: 16px; height: 16px; accent-color: var(--accent); }
            .alert {
                border: 1px solid #fecdca;
                border-radius: 8px;
                background: #fffbfa;
                color: var(--danger);
                padding: 10px 12px;
                font-weight: 650;
            }
            @media (max-width: 980px) {
                .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .columns { grid-template-columns: 1fr; }
            }
            @media (max-width: 640px) {
                .topbar-inner, .page-title { align-items: flex-start; flex-direction: column; }
                .topbar-inner { padding: 12px 14px; }
                .content { padding: 20px 14px 34px; }
                h1 { font-size: 24px; }
                .stats { grid-template-columns: 1fr; }
                .userbar { width: 100%; justify-content: space-between; }
                .user-chip { max-width: calc(100vw - 150px); }
                .user-chip span:last-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
                table { min-width: 760px; }
                .login-wrap { margin: 28px auto; }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <header class="topbar">
                <div class="topbar-inner">
                    <a class="brand" href="{{ route('dashboard.index') }}">
                        <span class="brand-mark">MP</span>
                        <span class="brand-copy">
                            <span class="brand-title">Middleware Payment</span>
                            <span class="brand-subtitle">Operations dashboard</span>
                        </span>
                    </a>
                    @auth
                        <div class="userbar">
                            <span class="user-chip">
                                <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                                <span>{{ auth()->user()->name }}</span>
                            </span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="button" type="submit">Logout</button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>
            <main class="content">
                @yield('content')
            </main>
        </div>
    </body>
</html>
