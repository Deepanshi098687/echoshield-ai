(function () {
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
                screenshotStatus.textContent = 'AI OCR Ready';
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

    function initChart(id, type, data) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        new Chart(canvas, {
            type,
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.95)',
                        titleColor: '#e2e8f0',
                        bodyColor: '#cbd5f1',
                        borderColor: '#0f172a',
                        borderWidth: 1,
                    },
                },
                scales: {
                    x: { grid: { color: 'rgba(0,229,255,0.08)' }, ticks: { color: '#94a3b8' } },
                    y: { grid: { color: 'rgba(0,229,255,0.06)' }, ticks: { color: '#94a3b8' } },
                },
            },
        });
    }

    function initCharts() {
        initChart('chartHarassmentTypes', 'pie', {
            labels: ['Harassment', 'Threats', 'Hate', 'Spam'],
            datasets: [{
                data: [42, 28, 18, 12],
                backgroundColor: ['#00e5ff', '#7c3aed', '#ff00d4', '#22c55e'],
                hoverOffset: 8,
            }],
        });

        initChart('chartDailyReports', 'bar', {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Daily Reports',
                data: [32, 28, 46, 31, 55, 49, 61],
                backgroundColor: 'rgba(0,229,255,0.35)',
                borderColor: '#00e5ff',
                borderWidth: 2,
            }],
        });

        initChart('chartWeeklyThreats', 'line', {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Threat Activity',
                data: [58, 47, 65, 53],
                fill: true,
                backgroundColor: 'rgba(124,58,237,0.15)',
                borderColor: '#7c3aed',
                tension: 0.35,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#7c3aed',
                pointRadius: 4,
            }],
        });

        initChart('chartSafeToxic', 'doughnut', {
            labels: ['Safe', 'Toxic'],
            datasets: [{
                data: [78, 22],
                backgroundColor: ['#22c55e', '#ef4444'],
                hoverOffset: 6,
            }],
        });
    }

    window.addEventListener('load', initCharts);

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
            const rows = document.querySelectorAll('#reportsTable tbody tr');
            rows.forEach(row => {
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
})();
