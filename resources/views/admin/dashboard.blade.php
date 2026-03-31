<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - MySocialBoard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body" data-panel="admin">
<div class="admin-layout">
    <aside class="admin-sidebar">
        <h1>MySocialBoard</h1>
        <p>Admin Console</p>
        <a href="#overview" class="active">Overview</a>
        <a href="#providers">Providers</a>
        <a href="#ingestions">Ingestoes</a>
        <a href="#embeds">Embeds</a>
        <a href="#clients">Clients</a>
        <a href="#audit">Auditoria</a>
        <button id="themeToggle" type="button"><span id="themeText">Escuro</span></button>
    </aside>

    <main class="admin-main">
        <header>
            <h2>Painel Administrativo</h2>
            <p>Visao central de operacao da plataforma.</p>
        </header>

        <section id="overview" class="admin-grid">
            <article><small>Tenants</small><strong>{{ $stats['tenants'] }}</strong></article>
            <article><small>Conectadas</small><strong>{{ $stats['provider_accounts_connected'] }}</strong></article>
            <article><small>Ingestoes OK</small><strong>{{ $stats['ingestions_success'] }}</strong></article>
            <article><small>Ingestoes erro</small><strong>{{ $stats['ingestions_failed'] }}</strong></article>
            <article><small>Embeds</small><strong>{{ $stats['embeds_active'] }}</strong></article>
            <article><small>Clients</small><strong>{{ $stats['developer_clients_active'] }}</strong></article>
        </section>

        <section id="providers" class="admin-panel">
            <h3>Providers por status</h3>
            <table>
                <thead><tr><th>Provider</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                @forelse($providerBreakdown as $line)
                    <tr><td>{{ $line->provider }}</td><td>{{ $line->status }}</td><td>{{ $line->total }}</td></tr>
                @empty
                    <tr><td colspan="3">Sem dados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section id="ingestions" class="admin-panel">
            <h3>Jobs recentes</h3>
            <table>
                <thead><tr><th>ID</th><th>Provider</th><th>Estado</th><th>Tentativas</th><th>Fim</th></tr></thead>
                <tbody>
                @forelse($recentJobs as $job)
                    <tr>
                        <td>#{{ $job->id }}</td>
                        <td>{{ $job->provider }}</td>
                        <td>{{ $job->state }}</td>
                        <td>{{ $job->attempts }}</td>
                        <td>{{ optional($job->completed_at)->format('d/m H:i') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Sem jobs.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <section id="embeds" class="admin-panel two">
            <div>
                <h3>Embeds recentes</h3>
                <ul>
                    @forelse($recentEmbeds as $embed)
                        <li>{{ $embed->name }} <span>{{ $embed->status }}</span></li>
                    @empty
                        <li>Sem embeds.</li>
                    @endforelse
                </ul>
            </div>
            <div id="clients">
                <h3>Clients recentes</h3>
                <ul>
                    @forelse($recentClients as $client)
                        <li>{{ $client->name }} <span>{{ $client->status }}</span></li>
                    @empty
                        <li>Sem clients.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section id="audit" class="admin-panel">
            <h3>Audit trail</h3>
            <table>
                <thead><tr><th>Evento</th><th>Recurso</th><th>Trace</th><th>Quando</th></tr></thead>
                <tbody>
                @forelse($recentAudit as $event)
                    <tr>
                        <td>{{ $event->event_type }}</td>
                        <td>{{ $event->resource_type }} {{ $event->resource_id }}</td>
                        <td>{{ $event->trace_id ?? '-' }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Sem eventos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>
