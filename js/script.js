(function () {
    const chartPalette = {
        cyan: '#ffffff',
        accent: '#ff0033',
        magenta: '#ff0033',
        green: '#22c55e',
        red: '#ef4444',
        white: 'rgba(255,255,255,0.85)',
        grid: 'rgba(255,255,255,0.06)',
        ticks: '#8b95a8',
    };

    const typeElement = document.getElementById('typewriterText');
    const cursor = document.getElementById('typewriterCursor');
    if (typeElement) {
        const text = 'EchoShield AI';
        let index = 0;
        const baseDelay = 80;

        function type() {
            if (index <= text.length) {
                typeElement.textContent = text.slice(0, index);
                index += 1;
                let delay = baseDelay;
                if (text.charAt(index - 1) === ' ') delay = 170;
                setTimeout(type, delay);
            } else if (cursor) {
                cursor.classList.add('done');
            }
        }

        type();
    }

    const screenshotInput = document.getElementById('screenshot');
    const screenshotPreview = document.getElementById('screenshotPreview');
    const screenshotStatus = document.getElementById('screenshotStatus');

    if (screenshotInput && screenshotPreview) {
        screenshotInput.addEventListener('change', function () {
            const file = this.files[0];
            const img = screenshotPreview.querySelector('img');
            const label = screenshotPreview.querySelector('.preview-label');
            if (file) {
                const url = URL.createObjectURL(file);
                img.src = url;
                img.hidden = false;
                label.textContent = 'Screenshot loaded. OCR ready.';
                if (screenshotStatus) screenshotStatus.textContent = 'AI OCR Ready';
            } else {
                img.hidden = true;
                label.textContent = 'Screenshot preview will appear here after selection.';
            }
        });
    }

    const analysisForm = document.getElementById('analysisForm');
    const processingOverlay = document.getElementById('processingOverlay');

    if (analysisForm && processingOverlay) {
        analysisForm.addEventListener('submit', function () {
            processingOverlay.classList.add('active');
        });
    }

    function chartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: chartPalette.ticks, font: { family: 'Share Tech Mono' } },
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.92)',
                    titleColor: '#fff',
                    bodyColor: '#ccc',
                    borderColor: '#ff005d',
                    borderWidth: 1,
                },
            },
            scales: {
                x: {
                    grid: { color: chartPalette.grid },
                    ticks: { color: chartPalette.ticks, font: { family: 'Share Tech Mono', size: 10 } },
                },
                y: {
                    grid: { color: chartPalette.grid },
                    ticks: { color: chartPalette.ticks, font: { family: 'Share Tech Mono', size: 10 } },
                },
            },
        };
    }

    function initChart(id, type, data, extraOptions) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') return;
        const opts = { ...chartOptions(), ...(extraOptions || {}) };
        if (type === 'pie' || type === 'doughnut') {
            opts.plugins.legend.display = true;
            delete opts.scales;
        }
        new Chart(canvas, { type, data, options: opts });
    }

    function buildChartsFromData() {
        const d = window.ECHO_CHART_DATA;
        if (!d) return;

        const sev = d.severity || {};
        initChart('chartHarassmentTypes', 'pie', {
            labels: ['Safe', 'Medium', 'High'],
            datasets: [{
                data: [sev.SAFE || 0, sev.MEDIUM || 0, sev.HIGH || 0],
                backgroundColor: [chartPalette.green, chartPalette.cyan, chartPalette.red],
                borderColor: '#000',
                borderWidth: 1,
            }],
        });

        initChart('chartDailyReports', 'bar', {
            labels: d.daily?.labels || [],
            datasets: [{
                label: 'Daily Reports',
                data: d.daily?.values || [],
                backgroundColor: 'rgba(255,0,93,0.35)',
                borderColor: chartPalette.magenta,
                borderWidth: 2,
            }],
        });

        initChart('chartWeeklyThreats', 'line', {
            labels: d.weekly?.labels || [],
            datasets: [{
                label: 'Weekly Activity',
                data: d.weekly?.values || [],
                fill: true,
                backgroundColor: 'rgba(255,0,93,0.12)',
                borderColor: chartPalette.magenta,
                tension: 0.35,
                pointBackgroundColor: '#fff',
                pointBorderColor: chartPalette.red,
                pointRadius: 4,
            }],
        });

        const safe = d.safe_toxic?.safe ?? 0;
        const toxic = d.safe_toxic?.toxic ?? 0;
        initChart('chartSafeToxic', 'doughnut', {
            labels: ['Safe', 'Toxic'],
            datasets: [{
                data: [safe, toxic],
                backgroundColor: [chartPalette.green, chartPalette.red],
                borderColor: '#000',
                borderWidth: 1,
            }],
        });

        initChart('adminChartSeverity', 'doughnut', {
            labels: ['Safe', 'Medium', 'High'],
            datasets: [{
                data: [sev.SAFE || 0, sev.MEDIUM || 0, sev.HIGH || 0],
                backgroundColor: [chartPalette.green, chartPalette.cyan, chartPalette.red],
            }],
        });

        initChart('adminChartDaily', 'bar', {
            labels: d.daily?.labels || [],
            datasets: [{
                label: 'Reports',
                data: d.daily?.values || [],
                backgroundColor: 'rgba(255,0,51,0.25)',
                borderColor: chartPalette.accent,
                borderWidth: 2,
            }],
        });

        initChart('adminChartWeekly', 'line', {
            labels: d.weekly?.labels || [],
            datasets: [{
                label: 'Weekly Activity',
                data: d.weekly?.values || [],
                fill: true,
                backgroundColor: 'rgba(255,255,255,0.06)',
                borderColor: chartPalette.white,
                tension: 0.35,
                pointBackgroundColor: chartPalette.accent,
                pointBorderColor: '#000',
                pointRadius: 4,
            }],
        });

        initChart('adminChartSafeToxic', 'doughnut', {
            labels: ['Safe', 'Toxic'],
            datasets: [{
                data: [safe, toxic],
                backgroundColor: [chartPalette.green, chartPalette.red],
                borderColor: '#000',
                borderWidth: 1,
            }],
        });

        const labels = d.labels || {};
        initChart('adminChartLabels', 'bar', {
            labels: ['normal', 'offensive', 'hatespeech'],
            datasets: [{
                label: 'hateXplain labels',
                data: [labels.normal || 0, labels.offensive || 0, labels.hatespeech || 0],
                backgroundColor: [
                    'rgba(34,197,94,0.55)',
                    'rgba(250,204,21,0.55)',
                    'rgba(255,0,51,0.55)',
                ],
                borderColor: [chartPalette.green, '#facc15', chartPalette.red],
                borderWidth: 1,
            }],
        });
    }

    const adminExportBtn = document.getElementById('adminExportReports');
    const adminReportSearch = document.getElementById('adminReportSearch');

    if (adminExportBtn) {
        adminExportBtn.addEventListener('click', function () {
            const table = document.getElementById('adminReportsTable');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(row => {
                return Array.from(row.querySelectorAll('th, td')).map(cell => '"' + cell.textContent.trim().replace(/"/g, '""') + '"').join(',');
            }).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'echoshield_admin_reports.csv';
            link.click();
        });
    }

    if (adminReportSearch) {
        adminReportSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('#adminReportsTable tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    window.addEventListener('load', buildChartsFromData);

    const threatMeter = document.querySelector('.threat-meter[data-threat]');
    if (threatMeter) {
        const pct = Math.min(100, Math.max(0, parseInt(threatMeter.getAttribute('data-threat') || '0', 10)));
        const progress = threatMeter.querySelector('.meter-progress');
        if (progress) {
            const circumference = 603;
            const offset = circumference - (pct / 100) * circumference;
            progress.style.strokeDashoffset = String(offset);
            if (pct >= 40) {
                progress.style.stroke = chartPalette.red;
            }
        }
    }

    const exportReportsBtn = document.getElementById('exportReports');
    const reportSearch = document.getElementById('reportSearch');

    if (exportReportsBtn) {
        exportReportsBtn.addEventListener('click', function () {
            const table = document.getElementById('reportsTable');
            if (!table) return;
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(row => {
                return Array.from(row.querySelectorAll('th, td')).map(cell => '"' + cell.textContent.trim().replace(/"/g, '""') + '"').join(',');
            }).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'echoShield_reports.csv';
            link.click();
        });
    }

    if (reportSearch) {
        reportSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('#reportsTable tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    const chatbotButton = document.getElementById('chatbotButton');
    const sosButton = document.getElementById('sosButton');
    const chatbotModal = document.getElementById('chatbotModal');
    const sosModal = document.getElementById('sosModal');
    const modalClosers = document.querySelectorAll('[data-action="close-modal"]');
    const openChatLinks = document.querySelectorAll('[data-action="open-chat"]');
    const openSosLinks = document.querySelectorAll('[data-action="open-sos"]');

    function toggleModal(modal, show) {
        if (!modal) return;
        modal.classList.toggle('active', show);
        if (show && modal === chatbotModal) {
            const input = document.getElementById('chatInput');
            if (input) setTimeout(() => input.focus(), 200);
        }
    }

    if (chatbotButton) {
        chatbotButton.addEventListener('click', () => toggleModal(chatbotModal, true));
    }
    if (sosButton) {
        sosButton.addEventListener('click', () => toggleModal(sosModal, true));
    }
    openChatLinks.forEach(link => link.addEventListener('click', function (event) {
        event.preventDefault();
        toggleModal(chatbotModal, true);
    }));
    openSosLinks.forEach(link => link.addEventListener('click', function (event) {
        event.preventDefault();
        toggleModal(sosModal, true);
    }));
    modalClosers.forEach(button => button.addEventListener('click', () => {
        toggleModal(chatbotModal, false);
        toggleModal(sosModal, false);
    }));

    document.addEventListener('click', function (event) {
        if (chatbotModal && chatbotModal.classList.contains('active') && event.target === chatbotModal) {
            toggleModal(chatbotModal, false);
        }
        if (sosModal && sosModal.classList.contains('active') && event.target === sosModal) {
            toggleModal(sosModal, false);
        }
    });

    document.querySelectorAll('[data-scroll]').forEach(el => {
        el.addEventListener('click', function (e) {
            const id = this.getAttribute('data-scroll');
            const target = document.getElementById(id);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const chatLog = document.getElementById('chatLog');
    const chatForm = document.getElementById('chatForm');
    const chatInput = document.getElementById('chatInput');
    const chatApi = window.ECHO_CHAT_API || 'api/chatbot.php';

    function appendChatLine(text, type) {
        if (!chatLog) return;
        const line = document.createElement('div');
        line.className = 'chat-line chat-line--' + (type || 'bot');
        line.textContent = text;
        chatLog.appendChild(line);
        chatLog.scrollTop = chatLog.scrollHeight;
    }

    async function sendChatMessage(message) {
        const trimmed = (message || '').trim();
        if (!trimmed) return;
        appendChatLine('> ' + trimmed, 'user');
        if (chatInput) chatInput.value = '';

        try {
            const res = await fetch(chatApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: trimmed }),
            });
            const data = await res.json();
            const reply = data.reply || data.error || '> Connection error.';
            appendChatLine(reply, 'bot');
        } catch (err) {
            appendChatLine('> OFFLINE — could not reach chat API. Check server path.', 'system');
        }
    }

    if (chatForm && chatInput) {
        chatForm.addEventListener('submit', function (e) {
            e.preventDefault();
            sendChatMessage(chatInput.value);
        });
    }

    document.querySelectorAll('[data-chat-prompt]').forEach(btn => {
        btn.addEventListener('click', function () {
            const prompt = this.getAttribute('data-chat-prompt');
            if (prompt) sendChatMessage(prompt);
        });
    });

    const activateSosBtn = document.getElementById('activateSosBtn');
    const sosStatus = document.getElementById('sosStatus');
    if (activateSosBtn) {
        activateSosBtn.addEventListener('click', function () {
            if (sosStatus) {
                sosStatus.hidden = false;
                sosStatus.textContent = '> SOS ALERT LOGGED — contact local emergency services if in danger.';
            }
            toggleModal(sosModal, false);
        });
    }

    const reportToast = document.getElementById('reportToast');
    if (reportToast) {
        if (processingOverlay) {
            processingOverlay.classList.remove('active');
        }
        reportToast.classList.add('es-toast--visible');
        setTimeout(() => reportToast.classList.remove('es-toast--visible'), 7000);
    }

    if (document.body.getAttribute('data-report-saved') === '1' && window.location.hash === '') {
        const records = document.getElementById('reports-records');
        if (records) {
            setTimeout(() => records.scrollIntoView({ behavior: 'smooth' }), 800);
        }
    }
})();
