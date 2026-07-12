<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acme Ledger</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; background: #f4f7fb; color: #10233f; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        header { height: 68px; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; background: #fff; border-bottom: 1px solid #e3eaf3; }
        .brand { display: flex; align-items: center; gap: 11px; font-weight: 750; letter-spacing: -.02em; }
        .mark { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 10px; background: #0284c7; color: white; }
        nav { display: flex; gap: 8px; }
        nav a, .action { color: #51647f; text-decoration: none; border: 0; background: transparent; padding: 9px 12px; border-radius: 9px; font: inherit; font-size: 14px; cursor: pointer; }
        nav a:hover, .action:hover { background: #eef4fa; color: #10233f; }
        main { max-width: 1180px; margin: 0 auto; padding: 42px 28px 80px; }
        .eyebrow { color: #0284c7; font-size: 12px; font-weight: 750; letter-spacing: .13em; text-transform: uppercase; }
        h1 { margin: 8px 0 8px; font-size: clamp(32px, 5vw, 48px); letter-spacing: -.045em; line-height: 1.05; }
        .lead { margin: 0; color: #61738c; font-size: 17px; }
        .grid { display: grid; grid-template-columns: 1.45fr .9fr; gap: 22px; margin-top: 34px; }
        .card { background: white; border: 1px solid #e1e9f2; border-radius: 18px; padding: 24px; box-shadow: 0 8px 28px rgba(27,53,87,.05); }
        .card h2 { margin: 0 0 18px; font-size: 17px; }
        .metric { display: flex; align-items: end; justify-content: space-between; padding: 18px 0; border-top: 1px solid #edf2f7; }
        .metric strong { font-size: 28px; letter-spacing: -.035em; }
        .metric span { color: #718198; font-size: 13px; }
        .activity { display: grid; gap: 12px; }
        .activity div { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-top: 1px solid #edf2f7; font-size: 14px; }
        .activity time { color: #7d8ba0; }
        .help-card { margin-top: 22px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 20px; border: 1px dashed #bcd2e5; border-radius: 14px; background: #f5fbff; }
        .help-card p { margin: 3px 0 0; color: #61738c; font-size: 14px; }
        .help-card button { border: 0; border-radius: 10px; background: #10233f; color: white; padding: 10px 14px; font-weight: 650; cursor: pointer; white-space: nowrap; }
        @media (max-width: 760px) { header { padding: 0 18px; } nav a { display: none; } main { padding: 30px 18px 80px; } .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <header>
        <div class="brand"><span class="mark">A</span> Acme Ledger</div>
        <nav>
            <a href="/docs">Documentation</a>
            <button class="action" type="button" data-docent-open>Help</button>
        </nav>
    </header>

    <main>
        <div class="eyebrow">Account overview</div>
        <h1>Your financial operations,<br>at a glance.</h1>
        <p class="lead">Track balances, reconcile entries, and keep the team moving.</p>

        <div class="grid">
            <section class="card">
                <h2>Ledger health</h2>
                <div class="metric"><span>Current balance</span><strong>$284,920</strong></div>
                <div class="metric"><span>Unreconciled entries</span><strong>12</strong></div>
                <div class="help-card">
                    <div><strong>Need help reconciling?</strong><p>Open the guide without leaving this screen.</p></div>
                    <button type="button" data-docent-article="getting-started/quickstart">View guide</button>
                </div>
            </section>
            <aside class="card">
                <h2>Recent activity</h2>
                <div class="activity">
                    <div><span>Invoice batch posted</span><time>8m</time></div>
                    <div><span>Bank feed synced</span><time>1h</time></div>
                    <div><span>Report exported</span><time>Yesterday</time></div>
                </div>
            </aside>
        </div>
    </main>

    <x-docent::widget />
    {{-- The launcher captures the current route name itself; no Docent('page') call needed. --}}
    <script>
        window.addEventListener('docent:analytics', ({ detail }) => console.debug('Docent widget', detail));
    </script>
</body>
</html>
