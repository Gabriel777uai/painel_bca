import { getBaseUrl } from "./route.js?v=3";

document.addEventListener('DOMContentLoaded', () => {
    // Current date string in footer
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Populate dropdowns
    loadAreasDropdowns();
    loadCitiesDropdown();

    // Toggle Period/Date range fields visibility
    const selectTipoData = document.getElementById('clientes-tipo-data');
    const divDataFim = document.getElementById('div-data-fim');
    if (selectTipoData && divDataFim) {
        selectTipoData.addEventListener('change', () => {
            if (selectTipoData.value === "1") { // Por período
                divDataFim.classList.remove('d-none');
            } else { // Por data
                divDataFim.classList.add('d-none');
                document.getElementById('clientes-data-fim').value = '';
            }
        });
    }

    // Setup active clients submit
    const formClientes = document.getElementById('form-relatorio-clientes');
    if (formClientes) {
        formClientes.addEventListener('submit', (e) => {
            e.preventDefault();
            generateClientsReport();
        });
    }

    // Setup inactive clients submit
    const formInativos = document.getElementById('form-relatorio-inativos');
    if (formInativos) {
        formInativos.addEventListener('submit', (e) => {
            e.preventDefault();
            generateInactiveReport();
        });
    }
});

/**
 * Load areas for both active and inactive reports dropdowns
 */
async function loadAreasDropdowns() {
    const selectClientesArea = document.getElementById('clientes-area');
    const selectInativosArea = document.getElementById('inativos-area');

    if (!selectClientesArea || !selectInativosArea) return;

    try {
        const response = await fetch(`${getBaseUrl()}/area/list`);
        const data = await response.json();

        // Sort alphabetically
        data.sort((a, b) => (a.c_nomearea || '').localeCompare(b.c_nomearea || ''));

        const optionsHtml = '<option value="">Selecione um representante...</option>' +
            data.map(area => `<option value="${area.i_cdarea}">${area.i_cdarea} - ${area.c_nomearea}</option>`).join('');

        selectClientesArea.innerHTML = optionsHtml;
        selectInativosArea.innerHTML = optionsHtml;

    } catch (e) {
        console.error('Erro ao carregar dropdown de áreas:', e);
    }
}

/**
 * Load cities list for active clients dropdown
 */
async function loadCitiesDropdown() {
    const selectCidade = document.getElementById('clientes-cidade');
    if (!selectCidade) return;

    try {
        const response = await fetch(`${getBaseUrl()}/relatorios/cidades`);
        const data = await response.json();

        selectCidade.innerHTML = '<option value="">Todas as cidades (None)</option>' +
            data.map(city => `<option value="${city.i_cdcidade}">${city.c_nomecidade}</option>`).join('');

    } catch (e) {
        console.error('Erro ao carregar cidades:', e);
    }
}

/**
 * Generate active clients report PDF
 */
async function generateClientsReport() {
    const form = document.getElementById('form-relatorio-clientes');
    const btn = document.getElementById('btn-submit-clientes');
    const spinner = document.getElementById('spinner-clientes');
    const areaId = document.getElementById('clientes-area').value;

    if (!form || !btn || !spinner) return;

    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);

        const response = await fetch(`${getBaseUrl()}/relatorios/clientes`, {
            method: 'POST',
            body: params,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        });

        if (!response.ok) {
            const errData = await response.json();
            throw new Error(errData.error || 'Erro na resposta do servidor.');
        }

        const blob = await response.blob();
        
        // Trigger browser download
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `relatorio_clientes_area_${areaId}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

    } catch (e) {
        console.error(e);
        alert('Erro ao gerar relatório: ' + e.message);
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
}

/**
 * Generate inactive clients report PDF
 */
async function generateInactiveReport() {
    const form = document.getElementById('form-relatorio-inativos');
    const btn = document.getElementById('btn-submit-inativos');
    const spinner = document.getElementById('spinner-inativos');
    const areaId = document.getElementById('inativos-area').value;

    if (!form || !btn || !spinner) return;

    btn.disabled = true;
    spinner.classList.remove('d-none');

    try {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData);

        const response = await fetch(`${getBaseUrl()}/relatorios/inativos`, {
            method: 'POST',
            body: params,
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        });

        if (!response.ok) {
            const errData = await response.json();
            throw new Error(errData.error || 'Erro na resposta do servidor.');
        }

        const blob = await response.blob();
        
        // Trigger browser download
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `relatorio_inativos_area_${areaId}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

    } catch (e) {
        console.error(e);
        alert('Erro ao gerar relatório: ' + e.message);
    } finally {
        btn.disabled = false;
        spinner.classList.add('d-none');
    }
}
