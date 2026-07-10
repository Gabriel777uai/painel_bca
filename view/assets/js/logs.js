import { getBaseUrl } from "./route.js";

document.addEventListener('DOMContentLoaded', () => {
    // Set current date in footer
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Initialize elements
    const logsLoading = document.getElementById('logs-loading-state');
    const logsEmpty = document.getElementById('logs-empty-state');
    const logsList = document.getElementById('logs-list-container');
    const webhookInput = document.getElementById('webhook-url-input');
    const btnSaveWebhook = document.getElementById('btn-save-webhook');
    const webhookBadgeStatus = document.getElementById('webhook-badge-status');
    const sidebarLogsCount = document.getElementById('sidebar-logs-count');

    const btnRefresh = document.getElementById('btn-refresh-logs');
    const btnClearAll = document.getElementById('btn-clear-logs');
    const btnSimulateError = document.getElementById('btn-simulate-error');
    const btnSimulateErrorEmpty = document.getElementById('btn-simulate-error-empty');

    const API_BASE_URL = getBaseUrl();

    // Load custom webhook from localStorage
    let savedWebhook = localStorage.getItem('n8n_custom_webhook_url') || '';
    if (webhookInput) {
        webhookInput.value = savedWebhook;
        updateWebhookStatusBadge(savedWebhook);
    }

    if (btnSaveWebhook) {
        btnSaveWebhook.addEventListener('click', () => {
            const url = webhookInput.value.trim();
            if (url) {
                localStorage.setItem('n8n_custom_webhook_url', url);
                savedWebhook = url;
                alert('URL do webhook salva com sucesso neste navegador!');
            } else {
                localStorage.removeItem('n8n_custom_webhook_url');
                savedWebhook = '';
                alert('Configuração de webhook limpa. O servidor usará a URL configurada no arquivo .env.');
            }
            updateWebhookStatusBadge(savedWebhook);
        });
    }

    function updateWebhookStatusBadge(url) {
        if (!webhookBadgeStatus) return;
        if (url) {
            webhookBadgeStatus.textContent = 'Salvo no Browser';
            webhookBadgeStatus.className = 'badge bg-success text-white border ms-2';
        } else {
            webhookBadgeStatus.textContent = 'Ambiente (.env)';
            webhookBadgeStatus.className = 'badge bg-secondary text-white border ms-2';
        }
    }

    // Load logs
    async function loadLogs() {
        showState('loading');
        try {
            const response = await fetch(`${API_BASE_URL}/logs`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) {
                throw new Error(`Falha HTTP: ${response.status}`);
            }
            const logs = await response.json();
            renderLogs(logs);
        } catch (error) {
            console.error('Erro ao buscar logs:', error);
            showState('empty');
            alert('Não foi possível carregar os logs. Verifique a conexão com o servidor.');
        }
    }

    function showState(state) {
        if (logsLoading) logsLoading.style.display = state === 'loading' ? 'block' : 'none';
        if (logsEmpty) logsEmpty.style.display = state === 'empty' ? 'block' : 'none';
        if (logsList) logsList.style.display = state === 'list' ? 'flex' : 'none';
    }

    function renderLogs(logs) {
        if (!logsList || !logsEmpty) return;
        logsList.innerHTML = '';

        // Update counts
        const count = logs.length;
        if (sidebarLogsCount) {
            if (count > 0) {
                sidebarLogsCount.textContent = count;
                sidebarLogsCount.style.display = 'inline-block';
            } else {
                sidebarLogsCount.style.display = 'none';
            }
        }

        if (count === 0) {
            showState('empty');
            return;
        }

        showState('list');

        logs.forEach(log => {
            const entryId = log.id;
            const formattedTime = new Date(log.timestamp).toLocaleString('pt-BR');
            const method = log.request_method ? log.request_method.toUpperCase() : 'N/A';
            let badgeClass = 'badge-other';

            if (method === 'GET') badgeClass = 'badge-get';
            else if (method === 'POST') badgeClass = 'badge-post';
            else if (method === 'DELETE') badgeClass = 'badge-delete';

            const logCard = document.createElement('div');
            logCard.className = 'card log-card shadow-sm rounded-4 border-0 p-4 mb-1';
            logCard.id = `log-card-${entryId}`;

            // Status Badge HTML
            let statusBadgeHtml = '';
            if (log.status === 'analyzed') {
                statusBadgeHtml = `<span class="badge bg-success rounded-pill px-3 py-1"><i class="bi bi-robot me-1"></i> Analisado</span>`;
            } else if (log.status === 'analyzing') {
                statusBadgeHtml = `<span class="badge bg-primary rounded-pill px-3 py-1"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Analisando...</span>`;
            } else {
                statusBadgeHtml = `<span class="badge bg-warning text-dark rounded-pill px-3 py-1"><i class="bi bi-clock me-1"></i> Pendente</span>`;
            }

            const cleanFile = log.file || 'Arquivo Desconhecido';
            const cleanLine = log.line || '0';

            logCard.innerHTML = `
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge ${badgeClass} px-2 py-1 fs-7 fw-bold rounded-2">${method}</span>
                        <span class="text-muted small"><i class="bi bi-calendar-event me-1"></i> ${formattedTime}</span>
                        <span class="text-muted small d-none d-sm-inline">|</span>
                        <span class="text-muted small d-none d-sm-inline font-monospace text-truncate" style="max-width: 300px;" title="${log.request_uri}">${log.request_uri}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        ${statusBadgeHtml}
                        <button class="btn btn-outline-danger btn-sm border-0 rounded-circle btn-delete-single" data-id="${entryId}" title="Excluir log">
                            <i class="bi bi-x-circle-fill fs-5"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <h4 class="h5 fw-bold text-dark mb-2">${escapeHtml(log.message)}</h4>
                    <p class="text-muted small font-monospace mb-1">
                        <i class="bi bi-file-code me-1 text-primary-green"></i> <strong>Arquivo:</strong> ${cleanFile} | <strong>Linha:</strong> ${cleanLine}
                    </p>
                </div>

                <div class="d-flex gap-2 mb-3">
                    <button class="btn btn-dark btn-sm rounded-3 py-1.5 px-3 btn-toggle-trace" type="button" data-bs-toggle="collapse" data-bs-target="#trace-collapse-${entryId}">
                        <i class="bi bi-code-slash me-1"></i> Ver Stack Trace
                    </button>
                    <button class="btn btn-primary-green btn-sm rounded-3 py-1.5 px-3 btn-analyze-ia" data-id="${entryId}">
                        <i class="bi bi-robot me-1"></i> Analisar com IA (n8n)
                    </button>
                </div>

                <div class="collapse mt-2 mb-3" id="trace-collapse-${entryId}">
                    <div class="trace-container">${escapeHtml(log.trace || 'Pilha de execução indisponível.')}</div>
                </div>

                <div class="analysis-container mt-3" id="analysis-container-${entryId}" style="display: ${log.ai_analysis ? 'block' : 'none'};">
                    <div class="analysis-box">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="icon-box-small bg-light-green text-primary-green rounded-circle">
                                <i class="bi bi-chat-left-dots-fill"></i>
                            </span>
                            <h5 class="fw-bold mb-0 text-black">Explicação e Correção Sugerida:</h5>
                        </div>
                        <div class="markdown-body text-dark" id="analysis-body-${entryId}">
                            ${log.ai_analysis ? formatMarkdown(log.ai_analysis) : ''}
                        </div>
                    </div>
                </div>
            `;

            logsList.appendChild(logCard);
        });

        // Attach action events dynamically
        document.querySelectorAll('.btn-delete-single').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                deleteSingleLog(id);
            });
        });

        document.querySelectorAll('.btn-analyze-ia').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                runAiAnalysis(id, e.currentTarget);
            });
        });
    }

    // Escape HTML to prevent injection issues in logs display
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Helper to replace literal \r\n and \n sequences with real newlines, then parse as Markdown
    function formatMarkdown(text) {
        if (!text) return '';
        let formatted = text
            .replace(/\\r\\n/g, '\n')
            .replace(/\\n/g, '\n')
            .replace(/\r\n/g, '\n');
        return marked.parse(formatted);
    }

    // Call API to analyze error
    async function runAiAnalysis(id, buttonEl) {
        const originalHtml = buttonEl.innerHTML;
        buttonEl.disabled = true;
        buttonEl.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processando...`;

        // Update status badge dynamically to "Analyzing"
        const card = document.getElementById(`log-card-${id}`);
        const statusBadge = card ? card.querySelector('.badge:not(.badge-get):not(.badge-post):not(.badge-delete):not(.badge-other)') : null;
        if (statusBadge) {
            statusBadge.className = 'badge bg-primary rounded-pill px-3 py-1';
            statusBadge.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Analisando...`;
        }

        const analysisContainer = document.getElementById(`analysis-container-${id}`);
        const analysisBody = document.getElementById(`analysis-body-${id}`);

        try {
            const payload = {
                id: id,
                webhook_url: savedWebhook || null
            };

            const response = await fetch(`${API_BASE_URL}/logs/analyze`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || `Erro HTTP ${response.status}`);
            }

            if (result.success && result.ai_analysis) {
                if (statusBadge) {
                    statusBadge.className = 'badge bg-success rounded-pill px-3 py-1';
                    statusBadge.innerHTML = `<i class="bi bi-robot me-1"></i> Analisado`;
                }

                // Render markdown
                if (analysisBody && analysisContainer) {
                    analysisBody.innerHTML = formatMarkdown(result.ai_analysis);
                    analysisContainer.style.display = 'block';
                    
                    // Smooth scroll to analysis container
                    analysisContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            } else {
                throw new Error('Retorno inválido da IA do n8n.');
            }
        } catch (error) {
            console.error('Erro na análise:', error);
            if (statusBadge) {
                statusBadge.className = 'badge bg-warning text-dark rounded-pill px-3 py-1';
                statusBadge.innerHTML = `<i class="bi bi-clock me-1"></i> Pendente`;
            }
            if (analysisBody && analysisContainer) {
                analysisBody.innerHTML = `<div class="text-danger small"><i class="bi bi-exclamation-triangle-fill me-1"></i> Falha ao obter análise: ${error.message}</div>`;
                analysisContainer.style.display = 'block';
            }
        } finally {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
        }
    }

    // Call API to delete log
    async function deleteSingleLog(id) {
        if (!confirm('Deseja realmente excluir este registro de log?')) return;

        try {
            const response = await fetch(`${API_BASE_URL}/logs/${id}`, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.error || 'Falha ao deletar log.');
            }

            const card = document.getElementById(`log-card-${id}`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    card.remove();
                    // Check if list is empty now
                    if (logsList && logsList.children.length === 0) {
                        showState('empty');
                        if (sidebarLogsCount) sidebarLogsCount.style.display = 'none';
                    } else if (sidebarLogsCount) {
                        const count = parseInt(sidebarLogsCount.textContent, 10);
                        if (count > 1) {
                            sidebarLogsCount.textContent = count - 1;
                        } else {
                            sidebarLogsCount.style.display = 'none';
                        }
                    }
                }, 300);
            }
        } catch (error) {
            console.error('Erro ao excluir log:', error);
            alert('Não foi possível excluir o log: ' + error.message);
        }
    }

    // Call API to clear all logs
    async function clearAllLogs() {
        if (!confirm('ATENÇÃO: Deseja realmente excluir todos os logs de erros? Esta ação não pode ser desfeita.')) return;

        try {
            const response = await fetch(`${API_BASE_URL}/logs/clear`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                throw new Error('Falha ao limpar logs.');
            }

            if (sidebarLogsCount) sidebarLogsCount.style.display = 'none';
            showState('empty');
        } catch (error) {
            console.error('Erro ao limpar logs:', error);
            alert('Não foi possível limpar os logs: ' + error.message);
        }
    }

    // Call API to trigger a test error simulation
    async function simulateError() {
        try {
            showState('loading');
            const response = await fetch(`${API_BASE_URL}/logs/test-error`, {
                headers: { 'Accept': 'application/json' }
            });
            // We expect a 500 error here as the backend is designed to crash
            if (response.status === 500) {
                // Succeeded in crashing!
                setTimeout(() => {
                    loadLogs();
                }, 500);
            } else {
                throw new Error('O endpoint não gerou a exceção esperada.');
            }
        } catch (error) {
            // Even if it failed to request, let's load logs since the custom log was written before throw
            setTimeout(() => {
                loadLogs();
            }, 500);
        }
    }

    // Button click mappings
    if (btnRefresh) btnRefresh.addEventListener('click', loadLogs);
    if (btnClearAll) btnClearAll.addEventListener('click', clearAllLogs);
    if (btnSimulateError) btnSimulateError.addEventListener('click', simulateError);
    if (btnSimulateErrorEmpty) btnSimulateErrorEmpty.addEventListener('click', simulateError);

    // Initial Load
    loadLogs();
});
