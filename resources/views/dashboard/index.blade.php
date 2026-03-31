<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel do Cliente - MySocialBoard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="client-body" data-panel="client">
<main class="client-layout" data-tenant-id="{{ $user->tenant_id }}" data-user-id="{{ $user->id }}">
    <aside class="client-sidebar">
        <h1>MySocialBoard</h1>
        <p>{{ $user->name }} | {{ $tenant?->name ?? 'Tenant' }}</p>
        <a href="#providers" class="nav-link active">Providers</a>
        <a href="#settings" class="nav-link">Settings</a>
        <button id="themeToggle" class="client-toggle" type="button"><span id="themeText">Claro</span></button>
    </aside>

    <section class="client-main">
        <header class="client-hero">
            <div>
                <h1>Painel do Cliente</h1>
                <p>Conecte provider e gere o iframe com token na aba de settings.</p>
            </div>
        </header>

        <article id="providers" class="client-card">
            <h2>Conectar provider</h2>
            <form id="providerForm" class="stack" onsubmit="return false;">
                <label for="providerSelect">Provider</label>
                <select id="providerSelect">
                    @foreach($providers as $provider)
                        <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                    @endforeach
                </select>
                <a id="providerConnectLink" class="button-primary" href="/instagram/authorize/start">
                    Conectar conta
                </a>
            </form>

            <div class="mini-list">
                <h3>Conexoes atuais</h3>
                @forelse($connectedProviders as $connection)
                    <div class="mini-row">
                        <span>{{ $connection->provider }} - {{ $connection->display_name ?? $connection->external_account_id }}</span>
                        <strong>{{ $connection->status }}</strong>
                    </div>
                @empty
                    <p class="hint">Nenhuma conta conectada ainda.</p>
                @endforelse
            </div>
        </article>

        <article id="settings" class="client-card">
            <h2>Settings do Embed</h2>
            <p class="hint">Gere token do cliente e copie o iframe pronto para o site.</p>

            <div class="stack">
                <label for="embedSelect">Embed</label>
                <select id="embedSelect">
                    @forelse($embeds as $embed)
                        <option value="{{ $embed->embed_uid }}">{{ $embed->name }} ({{ $embed->widget_type }})</option>
                    @empty
                        <option value="">Sem embeds cadastrados</option>
                    @endforelse
                </select>

                <label for="originInput">Origin do site cliente</label>
                <input id="originInput" type="url" placeholder="https://site-do-cliente.com" class="client-input" />

                <label for="ttlInput">TTL do token (segundos)</label>
                <input id="ttlInput" type="number" min="10" max="900" value="300" class="client-input" />

                <button id="generateIframeCode" type="button" class="button-primary">Gerar iframe</button>
            </div>

            <p id="tokenStatus" class="hint" aria-live="polite"></p>

            <label for="embedCode">Codigo iframe</label>
            <textarea id="embedCode" rows="7" readonly></textarea>
            <button id="copyEmbedCode" type="button" class="button-ghost">Copiar codigo</button>
        </article>
    </section>
</main>
</body>
</html>
