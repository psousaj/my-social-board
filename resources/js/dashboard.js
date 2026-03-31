const root = document.documentElement;
const toggle = document.getElementById('themeToggle');
const themeText = document.getElementById('themeText');
const panel = document.body?.dataset?.panel;

function applyTheme(theme) {
    root.setAttribute('data-theme', theme);

    if (themeText) {
        themeText.textContent = theme === 'dark' ? 'Escuro' : 'Claro';
    }
}

function initialTheme() {
    const stored = localStorage.getItem('msb-theme');

    if (stored === 'dark' || stored === 'light') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

if (panel === 'client' || panel === 'admin') {
    applyTheme(initialTheme());

    toggle?.addEventListener('click', () => {
        const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';

        localStorage.setItem('msb-theme', next);
        applyTheme(next);
    });

    const links = Array.from(document.querySelectorAll('.nav-link'));

    links.forEach((link) => {
        link.addEventListener('click', () => {
            links.forEach((item) => item.classList.remove('active'));
            link.classList.add('active');
        });
    });

    if (panel === 'client') {
        const clientLayout = document.querySelector('.client-layout');
        const providerSelect = document.getElementById('providerSelect');
        const providerConnectLink = document.getElementById('providerConnectLink');
        const embedSelect = document.getElementById('embedSelect');
        const originInput = document.getElementById('originInput');
        const ttlInput = document.getElementById('ttlInput');
        const generateIframeCode = document.getElementById('generateIframeCode');
        const tokenStatus = document.getElementById('tokenStatus');
        const embedCode = document.getElementById('embedCode');
        const copyEmbedCode = document.getElementById('copyEmbedCode');
        const currentUrl = new URL(window.location.href);

        const tenantFromLayout = clientLayout?.dataset?.tenantId || '';

        const menuLinks = Array.from(document.querySelectorAll('.client-sidebar .nav-link'));

        menuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                menuLinks.forEach((item) => item.classList.remove('active'));
                link.classList.add('active');
            });
        });

        const refreshProviderLink = () => {
            if (!providerConnectLink || !providerSelect) {
                return;
            }

            const provider = providerSelect.value || 'instagram';
            providerConnectLink.setAttribute('href', `/${provider}/authorize/start`);
        };

        const defaultOrigin = () => {
            if (!originInput) {
                return;
            }

            if (originInput.value.trim() !== '') {
                return;
            }

            const fromQuery = currentUrl.searchParams.get('origin');
            originInput.value = fromQuery || window.location.origin;
        };

        const setStatus = (message, isError = false) => {
            if (!tokenStatus) {
                return;
            }

            tokenStatus.textContent = message;
            tokenStatus.style.color = isError ? '#d7263d' : '';
        };

        const buildIframeCode = (selectedEmbed, token) => {
            const iframeUrl = `${window.location.origin}/embed/${encodeURIComponent(selectedEmbed)}?token=${encodeURIComponent(token)}`;
            return `<iframe src="${iframeUrl}" loading="lazy" style="width:100%;height:560px;border:0;" referrerpolicy="strict-origin-when-cross-origin" title="MySocialBoard Embed"></iframe>`;
        };

        const generateTokenAndCode = async () => {
            const selectedEmbed = embedSelect?.value || '';
            const origin = originInput?.value?.trim() || '';
            const ttlSeconds = Number.parseInt(ttlInput?.value || '300', 10);

            if (!selectedEmbed) {
                setStatus('Selecione um embed valido.', true);
                return;
            }

            if (!origin) {
                setStatus('Informe a origin do site cliente.', true);
                return;
            }

            if (!Number.isFinite(ttlSeconds) || ttlSeconds < 10 || ttlSeconds > 900) {
                setStatus('TTL deve estar entre 10 e 900 segundos.', true);
                return;
            }

            setStatus('Gerando token...');

            try {
                const response = await fetch(`/embeds/${encodeURIComponent(selectedEmbed)}/token`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tenant_id: Number.parseInt(tenantFromLayout || '0', 10),
                        origin,
                        ttl_seconds: ttlSeconds,
                    }),
                });

                const data = await response.json();

                if (!response.ok || !data.token) {
                    setStatus(data?.message || 'Falha ao gerar token.', true);
                    return;
                }

                if (embedCode) {
                    embedCode.value = buildIframeCode(selectedEmbed, data.token);
                }

                setStatus('Token gerado e iframe pronto para copiar.');
            } catch (error) {
                setStatus('Erro de rede ao gerar token.', true);
            }
        };

        providerSelect?.addEventListener('change', refreshProviderLink);
        generateIframeCode?.addEventListener('click', generateTokenAndCode);

        copyEmbedCode?.addEventListener('click', async () => {
            if (!embedCode?.value) {
                return;
            }

            await navigator.clipboard.writeText(embedCode.value);
            copyEmbedCode.textContent = 'Codigo copiado';
            setTimeout(() => {
                copyEmbedCode.textContent = 'Copiar codigo';
            }, 1200);
        });

        refreshProviderLink();
        defaultOrigin();
    }
}
