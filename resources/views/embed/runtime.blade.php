<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $embed->name }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            font-family: Manrope, ui-sans-serif, system-ui, sans-serif;
            background: #f4f7fb;
            color: #0f172a;
            padding: 16px;
        }

        .widget-shell {
            border: 1px solid #d9e2ec;
            border-radius: 14px;
            background: #ffffff;
            overflow: hidden;
        }

        .widget-header {
            padding: 12px 14px;
            border-bottom: 1px solid #e5edf5;
            background: #f8fbff;
        }

        .widget-header h1 {
            margin: 0;
            font-size: 15px;
            line-height: 1.3;
        }

        .widget-header p {
            margin: 4px 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .widget-content {
            padding: 14px;
        }

        .meta-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .meta-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            background: #f8fafc;
        }

        .meta-item small {
            display: block;
            font-size: 11px;
            color: #64748b;
        }

        .meta-item strong {
            font-size: 13px;
            word-break: break-word;
        }

        @media (max-width: 640px) {
            .meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="widget-shell">
    <div class="widget-header">
        <h1>{{ $embed->name }}</h1>
        <p>Renderizado pelo backend para o site autorizado {{ $origin }}</p>
    </div>
    <div class="widget-content">
        <div class="meta-grid">
            <div class="meta-item">
                <small>Embed UID</small>
                <strong>{{ $embed->embed_uid }}</strong>
            </div>
            <div class="meta-item">
                <small>Widget Type</small>
                <strong>{{ $embed->widget_type }}</strong>
            </div>
            <div class="meta-item">
                <small>Status</small>
                <strong>{{ $embed->status }}</strong>
            </div>
            <div class="meta-item">
                <small>Tenant</small>
                <strong>{{ $embed->tenant_id }}</strong>
            </div>
        </div>
    </div>
</div>
</body>
</html>
