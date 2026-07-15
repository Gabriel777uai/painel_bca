import { getBaseUrl } from "./route.js?v=3";

let monthlyChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    // Current date string in footer
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Default Dates
    const currentYear = new Date().getFullYear();
    const startDateInput = document.getElementById('filtro-data-inicio');
    const endDateInput = document.getElementById('filtro-data-fim');
    
    if (startDateInput) {
        // Default start to Jan 1st of current year
        startDateInput.value = `${currentYear}-01-01`;
    }
    if (endDateInput) {
        // Default end to today
        endDateInput.value = new Date().toISOString().split('T')[0];
    }

    // Populate Area dropdown options
    loadAreasDropdown();

    // Setup filter submit
    const form = document.getElementById('form-filtro-mensal');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            loadMonthlyReport();
        });
    }
});

let tomSelectInstance = null;

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
        selectArea.innerHTML = '<option value="">Selecione um representante...</option>' + 
            data.map(area => `<option value="${area.i_cdarea}">${area.i_cdarea} - ${area.c_nomearea}</option>`).join('');

        if (typeof TomSelect !== 'undefined') {
            if (tomSelectInstance) {
                tomSelectInstance.destroy();
            }
            tomSelectInstance = new TomSelect(selectArea, {
                create: false,
                placeholder: 'Selecione um representante...',
                allowEmptyOption: true,
                maxOptions: null,
                render: {
                    option: function(data, escape) {
                        const code = escape(data.value);
                        const name = escape(data.text.replace(data.value + ' - ', ''));
                        return `<div class="py-2 px-3 border-bottom border-light-subtle d-flex justify-content-between align-items-center">
                                    <span class="text-dark">${name}</span>
                                    <span class="badge bg-light text-secondary border font-monospace">RCA ${code}</span>
                                </div>`;
                    },
                    item: function(data, escape) {
                        return `<div class="fw-semibold text-dark">${escape(data.text)}</div>`;
                    }
                }
            });
        }

    } catch (e) {
        console.error('Erro ao carregar dropdown de áreas:', e);
    }
}

/**
 * Fetch and render monthly data
 */
async function loadMonthlyReport() {
    const areaId = document.getElementById('filtro-area').value;
    const dateStart = document.getElementById('filtro-data-inicio').value;
    const dateEnd = document.getElementById('filtro-data-fim').value;

    const loader = document.getElementById('loader-mensal');
    const results = document.getElementById('resultados-mensal');
    const placeholder = document.getElementById('placeholder-mensal');

    if (!areaId || !dateStart) return;

    // Toggle loading states
    loader.classList.remove('d-none');
    results.classList.add('d-none');
    placeholder.classList.add('d-none');

    try {
        let url = `${getBaseUrl()}/sales/monthly/${areaId}?date_start=${dateStart}`;
        if (dateEnd) {
            url += `&date_end=${dateEnd}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        loader.classList.add('d-none');

        if (data.error) {
            alert('Erro: ' + data.error);
            placeholder.classList.remove('d-none');
            return;
        }

        const sales = data.monthly_sales || {};
        
        // Months mapping
        const months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        
        const getSalesVal = (monthNum) => {
            const suffix = monthNum < 10 ? `0${monthNum}` : `${monthNum}`;
            return parseFloat(sales[`soma_pedido_digitado_${suffix}`] || 0);
        };
        const getSalesCount = (monthNum) => {
            const suffix = monthNum < 10 ? `0${monthNum}` : `${monthNum}`;
            return parseInt(sales[`count_pedido_${suffix}`] || 0);
        };

        const faturamentoData = [];
        const pedidosData = [];

        for (let m = 1; m <= 12; m++) {
            faturamentoData.push(getSalesVal(m));
            pedidosData.push(getSalesCount(m));
        }

        // Calculate KPIs
        const totalFaturamento = faturamentoData.reduce((a, b) => a + b, 0);
        const totalPedidos = pedidosData.reduce((a, b) => a + b, 0);
        const ticketMedio = totalPedidos > 0 ? (totalFaturamento / totalPedidos) : 0;

        // Render KPI values
        document.getElementById('total-faturamento-men').textContent = totalFaturamento.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('total-pedidos-men').textContent = totalPedidos.toLocaleString('pt-BR');
        document.getElementById('ticket-medio-men').textContent = ticketMedio.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Update card header text
        document.getElementById('grafico-titulo-men').textContent = `Faturamento Mensal - RCA ${data.area_nome || areaId}`;

        // Show results
        results.classList.remove('d-none');

        // Render ApexCharts (Dual Axis: Bar for faturamento, Line for orders count)
        renderMonthlyChart(months, faturamentoData, pedidosData);

    } catch (e) {
        console.error('Erro ao buscar faturamento mensal:', e);
        loader.classList.add('d-none');
        placeholder.classList.remove('d-none');
        alert('Ocorreu uma falha ao buscar os dados mensais no servidor.');
    }
}

/**
 * Render the dual axis Monthly Chart using ApexCharts
 */
function renderMonthlyChart(categories, faturamento, pedidos) {
    const container = document.getElementById('grafico_mensal_detalhado');
    if (!container) return;
    container.innerHTML = '';

    const options = {
        series: [
            {
                name: 'Faturamento (BRL)',
                type: 'column',
                data: faturamento
            },
            {
                name: 'Quantidade de Pedidos',
                type: 'line',
                data: pedidos
            }
        ],
        chart: {
            height: 450,
            type: 'line',
            fontFamily: 'Outfit, sans-serif',
            toolbar: { show: true }
        },
        stroke: {
            width: [0, 4],
            curve: 'smooth'
        },
        colors: ['#0fa40b', '#000000'], // corporate primary green and black contrast
        dataLabels: {
            enabled: true,
            enabledOnSeries: [0], // Show values on column bars
            formatter: function (val) {
                if (val === 0) return '';
                return 'R$ ' + Math.round(val / 1000).toLocaleString('pt-BR') + 'k';
            },
            style: { fontSize: '10px' }
        },
        labels: categories,
        xaxis: {
            type: 'category'
        },
        yaxis: [
            {
                title: {
                    text: 'Faturamento (BRL)',
                    style: { color: '#068238', fontWeight: 600 }
                },
                labels: {
                    formatter: function (value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            },
            {
                opposite: true,
                title: {
                    text: 'Quantidade de Pedidos',
                    style: { color: '#000000', fontWeight: 600 }
                },
                labels: {
                    formatter: function (value) {
                        return Math.round(value);
                    }
                }
            }
        ],
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: function (val, opts) {
                    if (opts.seriesIndex === 0) {
                        return val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                    }
                    return val + ' pedido(s)';
                }
            }
        },
        grid: {
            borderColor: '#f1f1f1',
            strokeDashArray: 4
        }
    };

    if (monthlyChartInstance) {
        monthlyChartInstance.destroy();
    }
    monthlyChartInstance = new ApexCharts(container, options);
    monthlyChartInstance.render();
}
