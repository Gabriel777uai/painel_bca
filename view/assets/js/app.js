import { getBaseUrl } from "./route.js?v=3";

// Keep track of chart instances globally
let pieChartInstance = null;
let trendChartInstance = null;
let bestSellersChartInstance = null;
let supervisorsChartInstance = null;

// Keep track of Inadimplência list states
let allRepresentatives = [];
let currentPage = 1;
const itemsPerPage = 8;
let searchQuery = '';
let currentPartnerId = null;
let currentPartnerName = '';

document.addEventListener('DOMContentLoaded', () => {
    // Set current date in footer/sidebar
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Initialize Dashboard
    loadDashboardData();
    loadSalesMap();
    loadSupervisorsList();
    loadRepresentatives(); // Load initial list (all representatives)

    // Setup event listeners
    const btnRefresh = document.getElementById('btn-refresh-dash');
    if (btnRefresh) {
        btnRefresh.addEventListener('click', () => {
            loadDashboardData();
            loadSalesMap();
        });
    }

    const btnApplyFilter = document.getElementById('btn-aplicar-filtro');
    if (btnApplyFilter) {
        btnApplyFilter.addEventListener('click', () => {
            const supervisorId = document.getElementById('filtro_vendedor_gerente').value;
            loadRepresentatives(supervisorId);
        });
    }

    const searchInput = document.getElementById('search_representante');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            currentPage = 1;
            renderRepresentativesList();
        });
    }
});

/**
 * Fetch overview metrics (cards, monthly trend, best sellers, and supervisor totals)
 */
async function loadDashboardData() {
    try {
        const response = await fetch(`${getBaseUrl()}/sales/dashboard/overview`);
        const data = await response.json();

        // Populate Cards safely
        document.getElementById('card-pedidos-hoje').textContent = (data.pedidos_dia || 0).toLocaleString('pt-BR');
        document.getElementById('card-pedidos-mes').textContent = (data.pedidos_mes || 0).toLocaleString('pt-BR');
        document.getElementById('card-clientes-novos').textContent = (data.clientes_novos_hoje || 0).toLocaleString('pt-BR');

        // Render ApexCharts instead of HTML tables/lists
        renderBestSellersChart(data.melhores_semana);
        renderSupervisorsChart(data.pedidos_por_supervisor);
        renderMonthlyTrendChart(data.grafico_mensal);

    } catch (error) {
        console.error('Erro ao carregar dados do dashboard:', error);
    }
}

/**
 * Render Evolution of Monthly Sales (grafico_temp_real_linha)
 */
function renderMonthlyTrendChart(monthlyData) {
    const container = document.getElementById('grafico_temp_real_linha');
    if (!container) return;
    container.innerHTML = ''; // Clear spinner

    const months = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
    
    // Safely parse values
    const getVal = (field) => {
        if (!monthlyData) return 0;
        const val = monthlyData[field];
        if (val === null || val === undefined) return 0;
        return parseFloat(val);
    };

    const seriesData = [
        getVal('soma_pedido_digitado_01'),
        getVal('soma_pedido_digitado_02'),
        getVal('soma_pedido_digitado_03'),
        getVal('soma_pedido_digitado_04'),
        getVal('soma_pedido_digitado_05'),
        getVal('soma_pedido_digitado_06'),
        getVal('soma_pedido_digitado_07'),
        getVal('soma_pedido_digitado_08'),
        getVal('soma_pedido_digitado_09'),
        getVal('soma_pedido_digitado_10'),
        getVal('soma_pedido_digitado_11'),
        getVal('soma_pedido_digitado_12')
    ];

    const options = {
        series: [{
            name: "Valor",
            data: seriesData
        }],
        chart: {
            height: 370,
            type: 'line',
            fontFamily: 'Outfit, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        colors: ['#0fa40b'], // Company primary green
        dataLabels: { enabled: false },
        stroke: { curve: 'straight', width: 3 },
        title: {
            text: `Vendas de cada mês do ano de ${new Date().getFullYear()}`,
            align: 'left',
            style: { color: '#333', fontWeight: 'bold' }
        },
        grid: {
            borderColor: '#f1f1f1',
            strokeDashArray: 4
        },
        xaxis: {
            categories: months
        },
        yaxis: {
            labels: {
                formatter: function(value) {
                    if (value === null || value === undefined) return 'R$ 0';
                    return 'R$ ' + value.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    if (val === null || val === undefined) return 'R$ 0,00';
                    return val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
            }
        }
    };

    if (trendChartInstance) {
        trendChartInstance.destroy();
    }
    trendChartInstance = new ApexCharts(container, options);
    trendChartInstance.render();
}

/**
 * Render Melhores Vendedores da Semana Horizontal Bar Chart (grafico_temp_real_basico)
 */
function renderBestSellersChart(bestSellers) {
    const container = document.getElementById('grafico_temp_real_basico');
    if (!container) return;
    container.innerHTML = ''; // Clear spinner

    if (!bestSellers || bestSellers.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-4">Sem faturamento semanal disponível.</div>';
        return;
    }

    const seriesData = bestSellers.map(seller => parseFloat(seller.valor_digitado || 0));
    const categories = bestSellers.map(seller => `${seller.area_vendedor} ${seller.c_nomearea || ''}`);

    const options = {
        series: [{
            name: 'Faturamento',
            data: seriesData
        }],
        chart: {
            height: 370,
            type: 'bar',
            fontFamily: 'Outfit, sans-serif',
            toolbar: { show: false }
        },
        colors: ['#068238'], // Company secondary green
        plotOptions: {
            bar: {
                borderRadius: 4,
                horizontal: true,
                barHeight: '75%'
            }
        },
        dataLabels: {
            enabled: true,
            formatter: function(value) {
                if (value === null || value === undefined) return 'R$ 0';
                return 'R$ ' + Math.round(value).toLocaleString('pt-BR');
            },
            style: {
                fontSize: '10px',
                colors: ['#fff']
            }
        },
        xaxis: {
            categories: categories,
            labels: {
                formatter: function(value) {
                    if (value === null || value === undefined) return 'R$ 0';
                    return 'R$ ' + (value / 1000).toLocaleString('pt-BR') + 'k';
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    if (val === null || val === undefined) return 'R$ 0,00';
                    return val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
            }
        }
    };

    if (bestSellersChartInstance) {
        bestSellersChartInstance.destroy();
    }
    bestSellersChartInstance = new ApexCharts(container, options);
    bestSellersChartInstance.render();
}

/**
 * Render Faturamento por Supervisor Pie Chart (grafico_temp_real_pizza)
 */
function renderSupervisorsChart(supervisors) {
    const container = document.getElementById('grafico_temp_real_pizza');
    if (!container) return;
    container.innerHTML = ''; // Clear spinner

    if (!supervisors || supervisors.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-4">Sem dados faturamento supervisor.</div>';
        return;
    }

    const series = supervisors.map(sup => parseInt(sup.quantidade_pedidos || 0));
    const labels = supervisors.map(sup => sup.nome_supervisor || 'Sem Nome');

    const options = {
        series: series,
        labels: labels,
        chart: {
            type: 'pie',
            height: 300,
            fontFamily: 'Outfit, sans-serif'
        },
        colors: ['#0fa40b', '#068238', '#000000', '#20c997', '#ffc107', '#fd7e14', '#dc3545'],
        legend: {
            position: 'bottom',
            fontSize: '11px'
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    if (val === null || val === undefined) return '0';
                    return val.toLocaleString('pt-BR') + " pedido(s)";
                }
            }
        }
    };

    if (supervisorsChartInstance) {
        supervisorsChartInstance.destroy();
    }
    supervisorsChartInstance = new ApexCharts(container, options);
    supervisorsChartInstance.render();
}

/**
 * Fetch and Render Brazil Sales Heat Map (using Highcharts default colors)
 */
async function loadSalesMap() {
    const container = document.getElementById('container-mapa-calor');
    if (!container) return;

    try {
        const response = await fetch(`${getBaseUrl()}/sales/dashboard/get-vendas-map`);
        const dataApi = await response.json();

        const dataForMap = dataApi.map(el => ({
            'hc-key': 'br-' + el.estado_uf,
            value: parseInt(el.quantidade_vendas || 0),
            valor_dig: parseFloat(el.vlr_digitado || 0)
        }));

        // Load map config RELATIVE to view folder, removing leading slash
        const mapResponse = await fetch('assets/libs/config/br-all.map.json');
        const geojson = await mapResponse.json();

        // Clear loading spinner
        container.innerHTML = '';

        Highcharts.mapChart('container-mapa-calor', {
            chart: {
                map: geojson,
                backgroundColor: 'transparent'
            },
            title: { text: '' },
            credits: { enabled: false },
            tooltip: {
                backgroundColor: '#ffffff',
                borderColor: '#eaeaea',
                borderRadius: 8,
                style: { color: '#333333' },
                formatter: function () {
                    const formattedValue = new Intl.NumberFormat('pt-BR', {
                        style: 'currency',
                        currency: 'BRL'
                    }).format(this.point.valor_dig || 0);
                    return `<b>${this.point.name}</b><br/>` +
                           `Quantidade de Pedidos: <i>${this.point.value || 0}</i><br/>` +
                           `Faturamento: <b>${formattedValue}</b>`;
                }
            },
            // Highcharts default styles for heat maps (using blue-orange gradient axis as requested)
            colorAxis: {
                min: 0,
                minColor: '#003399',
                maxColor: '#d44300'
            },
            legend: {
                layout: 'horizontal',
                align: 'center',
                verticalAlign: 'bottom'
            },
            mapNavigation: {
                enabled: true,
                buttonOptions: { verticalAlign: 'bottom' }
            },
            series: [{
                data: dataForMap,
                joinBy: 'hc-key',
                name: 'Pedidos',
                states: {
                    hover: { color: '#a4edba' } // Default map hover shade
                },
                dataLabels: {
                    enabled: true,
                    format: '{point.properties.postal-code}',
                    style: { fontSize: '10px', fontWeight: 'bold', color: '#111111' }
                }
            }]
        });
    } catch (error) {
        console.error('Erro ao renderizar o mapa:', error);
        container.innerHTML = '<div class="text-center text-danger p-5"><i class="bi bi-x-circle fs-1 mb-2"></i><p>Erro ao carregar mapa de vendas.</p></div>';
    }
}

/**
 * Fetch supervisors list
 */
async function loadSupervisorsList() {
    try {
        const response = await fetch(`${getBaseUrl()}/supervisores/list`);
        const data = await response.json();

        const optGroup = document.getElementById('select-supervisores-opt');
        if (optGroup) {
            optGroup.innerHTML = data.map(sup => 
                `<option value="${sup.codigo_supervisor}">${sup.nome_supervisor}</option>`
            ).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar supervisores:', error);
    }
}

/**
 * Fetch and list representatives
 */
async function loadRepresentatives(supervisorId = '') {
    const listGroup = document.getElementById('list-representantes');
    if (!listGroup) return;

    listGroup.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary-green spinner-border-sm" role="status"></div>
            <span class="ms-2 text-muted small">Carregando...</span>
        </div>
    `;

    try {
        const url = supervisorId 
            ? `${getBaseUrl()}/supervisores/sellers?code=${supervisorId}` 
            : `${getBaseUrl()}/supervisores/sellers`;
        
        const response = await fetch(url);
        allRepresentatives = await response.json();
        
        currentPage = 1;
        renderRepresentativesList();

        // Render payment default chart if supervisor is selected
        if (supervisorId) {
            loadInadimplenciaPieChart();
        } else {
            clearPieChart();
        }
    } catch (error) {
        console.error('Erro ao buscar representantes:', error);
        listGroup.innerHTML = '<div class="alert alert-danger m-2 py-2 small">Erro ao carregar representantes.</div>';
    }
}

/**
 * Render paginated and searched list of representatives
 */
function renderRepresentativesList() {
    const listGroup = document.getElementById('list-representantes');
    const paginationContainer = document.getElementById('pagination-container');
    if (!listGroup) return;

    const query = searchQuery.toLowerCase().trim();
    const filtered = allRepresentatives.filter(rep => {
        const name = (rep.c_nome || '').toLowerCase();
        const area = String(rep.area_de_venda || '').toLowerCase();
        const code = String(rep.i_cdvendedor || '').toLowerCase();
        return name.includes(query) || area.includes(query) || code.includes(query);
    });

    if (filtered.length === 0) {
        listGroup.innerHTML = '<div class="text-center text-muted p-4 small">Nenhum representante correspondente.</div>';
        if (paginationContainer) paginationContainer.setAttribute('style', 'display: none !important;');
        return;
    }

    const total = filtered.length;
    const totalPages = Math.ceil(total / itemsPerPage);

    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * itemsPerPage;
    const endIdx = Math.min(startIdx + itemsPerPage, total);
    const pageData = filtered.slice(startIdx, endIdx);

    listGroup.innerHTML = pageData.map(rep => `
        <button type="button" class="list-group-item list-group-item-action border-0 rounded-2 mb-1 d-flex justify-content-between align-items-center ${currentPartnerId === rep.i_cdparceiro ? 'active-rep' : ''}" 
                onclick="window.selectRepresentative(${rep.i_cdparceiro}, '${rep.c_nome.replace(/'/g, "\\'")}')">
            <div>
                <h5 class="mb-0 fs-6 fw-semibold text-dark">${rep.c_nome}</h5>
                <span class="text-muted small">Cod: ${rep.i_cdvendedor} &bull; Área: ${rep.area_de_venda}</span>
            </div>
            <i class="bi bi-chevron-right text-muted small"></i>
        </button>
    `).join('');

    // Pagination
    const info = document.getElementById('pagination-info');
    if (info) {
        info.textContent = `Exibindo ${startIdx + 1}-${endIdx} de ${total}`;
    }

    const nav = document.getElementById('pagination-nav');
    if (nav) {
        let navHtml = `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.changeRepPage(event, ${currentPage - 1})">Ant.</a>
            </li>
        `;

        const maxVisible = 3;
        let start = Math.max(1, currentPage - 1);
        let end = Math.min(totalPages, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
            navHtml += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="window.changeRepPage(event, ${i})">${i}</a>
                </li>
            `;
        }

        navHtml += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="window.changeRepPage(event, ${currentPage + 1})">Próx.</a>
            </li>
        `;
        nav.innerHTML = navHtml;
    }

    if (paginationContainer) {
        paginationContainer.removeAttribute('style');
    }
}

// Global hooks for reps listing
window.changeRepPage = function(event, targetPage) {
    event.preventDefault();
    currentPage = targetPage;
    renderRepresentativesList();
};

window.selectRepresentative = function(partnerId, name) {
    currentPartnerId = partnerId;
    currentPartnerName = name;

    renderRepresentativesList();

    const noSelectCard = document.getElementById('no-representative-selected');
    const detailsContent = document.getElementById('representative-details-content');

    // FIX placeholder overlapping: remove 'd-flex' and add 'd-none'
    if (noSelectCard) {
        noSelectCard.classList.remove('d-flex');
        noSelectCard.classList.add('d-none');
    }

    if (detailsContent) {
        detailsContent.style.display = 'block';

        const todayStr = new Date().toISOString().split('T')[0];

        detailsContent.innerHTML = `
            <h4 class="fs-5 fw-bold text-black border-bottom pb-2 mb-3">
                <i class="bi bi-person-fill text-primary-green me-1"></i> ${name}
            </h4>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label for="date-limit" class="small text-muted mb-1">Data Limite</label>
                    <input type="date" id="date-limit" class="form-control form-control-sm border-secondary-subtle" value="${todayStr}">
                </div>
                <div class="col-6">
                    <label for="date-final" class="small text-muted mb-1">Data Final</label>
                    <input type="date" id="date-final" class="form-control form-control-sm border-secondary-subtle" value="${todayStr}">
                </div>
            </div>
            <button class="btn btn-outline-dark btn-sm w-100 rounded-2 mb-3" id="btn-fetch-details">
                <i class="bi bi-funnel"></i> Filtrar Títulos vencidos
            </button>

            <div id="details-sub-loader" style="display: none;">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary-green spinner-border-sm" role="status"></div>
                </div>
            </div>

            <div id="details-body-results">
                <!-- Dynamically loaded -->
            </div>
        `;

        document.getElementById('btn-fetch-details').addEventListener('click', loadInadimplenciaDetailsData);

        loadInadimplenciaDetailsData();
    }
};

/**
 * Load overdue titles and default indices
 */
async function loadInadimplenciaDetailsData() {
    const detailsLoader = document.getElementById('details-sub-loader');
    const resultsContainer = document.getElementById('details-body-results');
    const dateLimit = document.getElementById('date-limit').value;
    const dateFinal = document.getElementById('date-final').value;

    if (!currentPartnerId || !resultsContainer || !detailsLoader) return;

    detailsLoader.style.display = 'block';
    resultsContainer.innerHTML = '';

    try {
        const urlPercent = `${getBaseUrl()}/inadimplencia/get?type=percentual&i_cdparceiro=${currentPartnerId}&d_limite=${dateLimit}&d_final=${dateFinal}`;
        const urlTitles = `${getBaseUrl()}/inadimplencia/get?type=titulos&i_cdparceiro=${currentPartnerId}&d_limite=${dateLimit}&d_final=${dateFinal}`;

        const [resPercent, resTitles] = await Promise.all([
            fetch(urlPercent).then(r => r.json()),
            fetch(urlTitles).then(r => r.json())
        ]);

        detailsLoader.style.display = 'none';

        if (resPercent.error || resTitles.error) {
            resultsContainer.innerHTML = `
                <div class="alert alert-danger py-2 small">
                    ${resPercent.error || resTitles.error}
                </div>
            `;
            return;
        }

        const percentage = resPercent.n_perc_indice_inadimplencia || '0,00';
        const percentFloat = parseFloat(percentage.replace(',', '.'));

        let alertBadge = 'bg-success text-white';
        let alertText = 'Sob Controle';
        if (percentFloat > 5 && percentFloat <= 15) {
            alertBadge = 'bg-warning text-dark';
            alertText = 'Atenção';
        } else if (percentFloat > 15) {
            alertBadge = 'bg-danger text-white';
            alertText = 'Alerta Crítico';
        }

        let titlesTable = '';
        let totalVal = 0;

        if (Array.isArray(resTitles) && resTitles.length > 0) {
            const rowsHtml = resTitles.map(t => {
                const amount = parseFloat(t.n_inadimplencia || t.n_vlrsaldo || 0);
                totalVal += amount;
                
                const formattedVenc = t.d_vencimento || t.d_venc || 'N/A';
                const formattedDoc = t.c_documento || t.c_nrdoc || 'N/A';
                
                return `
                    <tr>
                        <td><span class="d-inline-block text-truncate" style="max-width: 90px;" title="${t.c_nomecliente || ''}">${t.c_nomecliente || 'N/A'}</span></td>
                        <td>${formattedDoc}</td>
                        <td>${formattedVenc}</td>
                        <td class="text-end fw-semibold text-danger">R$ ${amount.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                    </tr>
                `;
            }).join('');

            titlesTable = `
                <div class="table-responsive rounded border mt-3" style="max-height: 200px;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.75rem;">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Cliente</th>
                                <th>Doc</th>
                                <th>Vencimento</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                </div>
            `;
        } else {
            titlesTable = `
                <div class="alert alert-success border-0 small mt-3 py-2 text-center">
                    <i class="bi bi-check-circle-fill me-1"></i> Nenhum título vencido encontrado no período.
                </div>
            `;
        }

        resultsContainer.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="detail-stat-box flex-grow-1 me-2 text-center">
                    <span class="text-muted small uppercase d-block">Índice</span>
                    <h5 class="fw-bold mb-1">${percentage}%</h5>
                    <span class="detail-badge-pill ${alertBadge}">${alertText}</span>
                </div>
                <div class="detail-stat-box flex-grow-1 ms-2 text-center">
                    <span class="text-muted small uppercase d-block">Saldo devedor</span>
                    <h5 class="fw-bold mb-1 text-danger">R$ ${totalVal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</h5>
                    <span class="detail-badge-pill bg-light text-muted border">${resTitles.length || 0} título(s)</span>
                </div>
            </div>

            <h5 class="fs-6 fw-bold text-dark mt-3 mb-1">Títulos em Aberto</h5>
            ${titlesTable}
        `;

    } catch (e) {
        console.error('Erro ao carregar detalhes:', e);
        detailsLoader.style.display = 'none';
        resultsContainer.innerHTML = '<div class="alert alert-danger py-2 small">Falha na comunicação com o servidor.</div>';
    }
}

/**
 * Fetch and render ApexCharts pie chart for payment default (larger size, height: 460)
 */
async function loadInadimplenciaPieChart() {
    const container = document.getElementById('chart-inadimplencia-pizza');
    if (!container) return;

    container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary-green spinner-border-sm mb-2" role="status"></div>
            <p class="text-muted small mb-0">Carregando índices do grupo...</p>
        </div>
    `;

    if (!allRepresentatives || allRepresentatives.length === 0) {
        container.innerHTML = '<div class="text-center text-muted p-4">Nenhum dado de representantes disponível.</div>';
        return;
    }

    const todayStr = new Date().toISOString().split('T')[0];

    try {
        const fetchPromises = allRepresentatives.map(async (rep) => {
            try {
                const url = `${getBaseUrl()}/inadimplencia/get?type=percentual&i_cdparceiro=${rep.i_cdparceiro}&d_limite=${todayStr}&d_final=${todayStr}`;
                const res = await fetch(url);
                const data = await res.json();
                
                if (data.error) {
                    return { name: rep.c_nome, value: 0 };
                }
                const val = data.n_perc_indice_inadimplencia || '0,00';
                const percentage = parseFloat(val.replace(',', '.'));
                return { name: rep.c_nome, value: isNaN(percentage) ? 0 : percentage };
            } catch (e) {
                return { name: rep.c_nome, value: 0 };
            }
        });

        const results = await Promise.all(fetchPromises);
        
        const filteredResults = results.filter(r => r.value > 0);

        if (filteredResults.length === 0) {
            container.innerHTML = `
                <div class="text-center text-success p-5 border rounded-3 bg-light mt-4">
                    <i class="bi bi-shield-fill-check fs-1 text-success mb-2 d-block"></i>
                    <h5 class="fs-6 fw-bold">Inadimplência Zero</h5>
                    <p class="small text-muted mb-0">Todos os representantes desse grupo estão com inadimplência zerada.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        const series = filteredResults.map(r => r.value);
        const labels = filteredResults.map(r => r.name);

        const options = {
            series: series,
            labels: labels,
            chart: {
                type: 'donut',
                height: 440, // Larger size
                fontFamily: 'Outfit, sans-serif'
            },
            dataLabels: {
                enabled: true,
                formatter: function(val, opts) {
                    return opts.w.config.series[opts.seriesIndex].toLocaleString('pt-BR', { minimumFractionDigits: 1 }) + "%";
                }
            },
            colors: ['#0fa40b', '#068238', '#000000', '#20c997', '#ffc107', '#fd7e14', '#dc3545', '#6f42c1'],
            stroke: { colors: ['#fff'], width: 2 },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center',
                labels: { colors: '#333' }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return val.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + "%";
                    }
                }
            }
        };

        if (pieChartInstance) {
            pieChartInstance.destroy();
        }
        pieChartInstance = new ApexCharts(container, options);
        pieChartInstance.render();

    } catch (error) {
        console.error('Erro ao processar gráfico circular:', error);
        container.innerHTML = '<div class="alert alert-danger py-2 small">Erro ao processar gráfico.</div>';
    }
}

function clearPieChart() {
    const container = document.getElementById('chart-inadimplencia-pizza');
    if (!container) return;
    if (pieChartInstance) {
        pieChartInstance.destroy();
        pieChartInstance = null;
    }
    container.innerHTML = `
        <div class="text-center text-muted p-4">
            <i class="bi bi-pie-chart-fill fs-2 mb-2 d-block text-secondary"></i>
            Selecione um supervisor e aplique o filtro para visualizar o gráfico.
        </div>
    `;
}