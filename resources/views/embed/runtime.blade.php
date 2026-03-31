<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $embed->name }}</title>
    <style>
        body {
            margin: 0;
            font-family: Manrope, ui-sans-serif, system-ui, sans-serif;
            background: #070d14;
            color: #ebf4ff;
        }

        .runtime-shell {
            min-height: 100vh;
            padding: 16px;
            background:
                radial-gradient(circle at 15% 20%, #1a2b3f 0%, transparent 45%),
                radial-gradient(circle at 85% 5%, #123a3a 0%, transparent 42%),
                #070d14;
        }

        .runtime-frame {
            max-width: 1120px;
            margin: 0 auto;
            border: 1px solid #26384f;
            border-radius: 16px;
            background: rgba(10, 20, 32, 0.88);
            overflow: hidden;
        }

        .runtime-header {
            padding: 14px 16px;
            border-bottom: 1px solid #223447;
            background: rgba(12, 23, 37, 0.94);
        }

        .runtime-header h1 {
            margin: 0;
            font-size: 16px;
        }

        .runtime-header p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #93a6bc;
        }

        .runtime-content {
            padding: 14px;
        }

        .runtime-alert {
            border: 1px solid #51421f;
            border-radius: 10px;
            background: #2f260f;
            color: #f6dfa1;
            padding: 10px 12px;
            font-size: 13px;
            margin-bottom: 12px;
        }

        .runtime-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
            font-size: 12px;
            color: #9db2c9;
        }

        .runtime-meta span {
            border: 1px solid #2a3d53;
            border-radius: 999px;
            padding: 4px 10px;
            background: rgba(17, 30, 44, 0.8);
        }

        .media-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        }

        .media-card {
            border: 1px solid #2a3c52;
            border-radius: 10px;
            background: #0f1d2b;
            overflow: hidden;
            display: grid;
            grid-template-rows: 150px auto;
        }

        .media-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: #08121d;
        }

        .media-card .media-body {
            padding: 10px;
            display: grid;
            gap: 6px;
        }

        .media-card .media-type {
            font-size: 11px;
            color: #88a3bf;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .media-card .media-caption {
            font-size: 13px;
            color: #d5e4f4;
            line-height: 1.4;
            min-height: 36px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .media-card a {
            font-size: 12px;
            color: #77b8ff;
            text-decoration: none;
        }

        .media-card a:hover {
            text-decoration: underline;
        }

        .runtime-empty {
            border: 1px dashed #3a4f66;
            border-radius: 12px;
            padding: 28px 16px;
            text-align: center;
            color: #9bb2c8;
            background: rgba(17, 29, 43, 0.64);
        }

        @media (max-width: 640px) {
            .runtime-shell {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
<div class="runtime-shell">
    <div class="runtime-frame">
        <div class="runtime-header">
            <h1>{{ $embed->name }}</h1>
            <p>Conteudo servido pelo backend para {{ $origin }}</p>
        </div>

        <div class="runtime-content">
            @if(!empty($runtime['message']))
                <div class="runtime-alert">{{ $runtime['message'] }}</div>
            @endif

            <div class="runtime-meta">
                <span>Source: {{ $runtime['source'] }}</span>
                <span>Stale: {{ $runtime['stale'] ? 'yes' : 'no' }}</span>
                <span>Widget: {{ $embed->widget_type }}</span>
                <span>Updated: {{ $runtime['updated_at'] ?? 'n/a' }}</span>
            </div>

            @if(count($runtime['items']) > 0)
                <div class="media-grid">
                    @foreach($runtime['items'] as $item)
                        <article class="media-card">
                            <img src="{{ $item['media_url'] ?? '' }}" alt="{{ $item['caption'] ?? 'media item' }}" loading="lazy">
                            <div class="media-body">
                                <span class="media-type">{{ $item['media_type'] ?? 'UNKNOWN' }}</span>
                                <p class="media-caption">{{ $item['caption'] ?? 'Sem legenda' }}</p>
                                @if(!empty($item['permalink']))
                                    <a href="{{ $item['permalink'] }}" target="_blank" rel="noopener noreferrer">Abrir no Instagram</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="runtime-empty">
                    Nenhuma midia disponivel para este embed ainda.
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
