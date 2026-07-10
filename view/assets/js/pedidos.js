import { getBaseUrl } from "./route.js?v=3";

let currentPage = 1;
const limit = 15;
let isSearchMode = false;

document.addEventListener('DOMContentLoaded', () => {
    // Current date string in footer
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Default Dates (start to 1st of month, end to today)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    const startDateInput = document.getElementById('filtro-data-inicio');
    const endDateInput = document.getElementById('filtro-data-fim');
    
    if (startDateInput) {
        startDateInput.value = firstDay.toISOString().split('T')[0];
    }
    if (endDateInput) {
        endDateInput.value = today.toISOString().split('T')[0];
    }

    // Load Area Dropdown options
    loadAreasDropdown();

    // Load initial paginated pending orders
    loadPendingOrders(1);

    // Setup submit filter
    const form = document.getElementById('form-filtro-pedidos');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            isSearchMode = true;
            currentPage = 1;
            loadPendingOrders(1);
        });
    }

    // Setup clear filter
    const btnClear = document.getElementById('btn-clear-filtro');
    if (btnClear) {
        btnClear.addEventListener('click', () => {
            document.getElementById('filtro-area').value = "";
            startDateInput.value = firstDay.toISOString().split('T')[0];
            endDateInput.value = today.toISOString().split('T')[0];
            
            isSearchMode = false;
            currentPage = 1;
            loadPendingOrders(1);
        });
    }
});

/**
 * Load all areas to dropdown list
 */
async function loadAreasDropdown() {
    const selectArea = document.getElementById('filtro-area');
    if (!selectArea) return;

    try {
        const response = await fetch(`${getBaseUrl()}/area/list`);
        const data = await response.json();

        // Sort areas alphabetically
        data.sort((a, b) => (a.c_nomearea || '').localeCompare(b.c_nomearea || ''));

        // Populate dropdown
        selectArea.innerHTML = '<option value="">Todos os representantes</option>' + 
            data.map(area => `<option value="${area.i_cdarea}">${area.i_cdarea} - ${area.c_nomearea}</option>`).join('');

    } catch (e) {
        console.error('Erro ao carregar dropdown de áreas:', e);
    }
}

/**
 * Fetch and render pending orders (searched or paginated)
 */
async function loadPendingOrders(page = 1) {
    const loader = document.getElementById('loader-pedidos');
    const results = document.getElementById('resultados-pedidos');
    const tbody = document.querySelector('#table-pedidos-pendentes tbody');

    if (!tbody || !loader || !results) return;

    loader.classList.remove('d-none');
    results.classList.add('d-none');
    tbody.innerHTML = '';

    try {
        let url = '';
        if (isSearchMode) {
            const areaId = document.getElementById('filtro-area').value;
            const dateStart = document.getElementById('filtro-data-inicio').value;
            const dateEnd = document.getElementById('filtro-data-fim').value;
            
            // Search filters mode
            url = `${getBaseUrl()}/sales/pending-orders?date_start=${dateStart}&date_end=${dateEnd}&area=${areaId}`;
        } else {
            // Paginated mode
            url = `${getBaseUrl()}/sales/pending-orders?page=${page}&limit=${limit}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        loader.classList.add('d-none');
        results.classList.remove('d-none');

        if (data.error) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${data.error}</td></tr>`;
            return;
        }

        const orders = data.pedidos || [];
        
        if (orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-info-circle me-1"></i> Nenhum pedido pendente encontrado para os critérios selecionados.</td></tr>';
            document.getElementById('contador-pedidos').textContent = 'Total: 0 pedido(s)';
            document.getElementById('pagination-pedidos-nav').style.display = 'none';
            return;
        }

        // Render Table Rows
        tbody.innerHTML = orders.map(order => {
            const vlrDigitado = parseFloat(order.n_vlrdigitado || 0);
            const vlrSeparado = parseFloat(order.n_vlrseparado || 0);
            const dateCad = order.d_cadastro ? new Date(order.d_cadastro).toLocaleDateString('pt-BR') : 'N/A';

            return `
                <tr>
                    <td class="fw-bold">#${order.i_nrpedido}</td>
                    <td><span class="badge bg-light text-dark-emphasis border">${order.i_cdarea}</span></td>
                    <td class="fw-semibold">${order.c_nomearea || 'Sem Nome'}</td>
                    <td>${dateCad}</td>
                    <td class="text-end fw-semibold">R$ ${vlrDigitado.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-end fw-semibold text-secondary">R$ ${vlrSeparado.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    <td class="text-center">
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">Pendente</span>
                    </td>
                </tr>
            `;
        }).join('');

        // Update pagination controls
        if (data.mode === 'paginated') {
            const total = parseInt(data.total_registros || 0);
            const totalPages = parseInt(data.total_paginas || 1);
            currentPage = page;

            document.getElementById('contador-pedidos').textContent = `Total: ${total} pedido(s)`;
            document.getElementById('pagination-pedidos-nav').removeAttribute('style');
            
            const startIdx = (page - 1) * limit + 1;
            const endIdx = Math.min(startIdx + limit - 1, total);
            document.getElementById('info-pedidos-pag').textContent = `Exibindo ${startIdx}-${endIdx} de ${total}`;

            renderPaginationNav(totalPages);
        } else {
            // Search mode doesn't paginate at model layer, showing total list count
            document.getElementById('contador-pedidos').textContent = `Total: ${orders.length} pedido(s)`;
            document.getElementById('pagination-pedidos-nav').setAttribute('style', 'display: none !important;');
        }

    } catch (e) {
        console.error('Erro ao carregar pedidos pendentes:', e);
        loader.classList.add('d-none');
        results.classList.remove('d-none');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4"><i class="bi bi-x-circle me-1"></i> Erro ao carregar pedidos pendentes.</td></tr>';
    }
}

/**
 * Render pagination controls
 */
function renderPaginationNav(totalPages) {
    const list = document.getElementById('pagination-pedidos-list');
    if (!list) return;

    let navHtml = `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="window.changePendingPage(event, ${currentPage - 1})">Anterior</a>
        </li>
    `;

    const maxVisible = 5;
    let start = Math.max(1, currentPage - 2);
    let end = Math.min(totalPages, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        navHtml += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="window.changePendingPage(event, ${i})">${i}</a>
            </li>
        `;
    }

    navHtml += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="window.changePendingPage(event, ${currentPage + 1})">Próximo</a>
        </li>
    `;

    list.innerHTML = navHtml;
}

// Global hooks for pagination
window.changePendingPage = function (event, targetPage) {
    event.preventDefault();
    loadPendingOrders(targetPage);
};
