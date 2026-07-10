import { getBaseUrl } from "./route.js?v=3";

let weeklyChartInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    // Current date string in footer
    const currentStr = document.getElementById('current-date-str');
    if (currentStr) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        currentStr.textContent = new Date().toLocaleDateString('pt-BR', options);
    }

    // Default Date to today
    const dateInput = document.getElementById('filtro-data');
    if (dateInput) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    // Populate Area dropdown options
    loadAreasDropdown();

    // Setup filter submit
    const form = document.getElementById('form-filtro-semanal');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            loadWeeklyReport();
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
        selectArea.innerHTML = '<option value="">Selecione um representante...</option>' + 
            data.map(area => `<option value="${area.i_cdarea}">${area.i_cdarea} - ${area.c_nomearea}</option>`).join('');

    } catch (e) {
        console.error('Erro ao carregar dropdown de áreas:', e);
    }
}

/**
 * Fetch and render weekly data
 */
async function loadWeeklyReport() {
    const areaId = document.getElementById('filtro-area').value;
    const date = document.getElementById('filtro-data').value;

    const loader = document.getElementById('loader-semanal');
    const results = document.getElementById('resultados-semanal');
    const placeholder = document.getElementById('placeholder-semanal');

    if (!areaId || !date) return;

    // Toggle loading states
    loader.classList.remove('d-none');
    results.classList.add('d-none');
    placeholder.classList.add('d-none');

    try {
        const response = await fetch(`${getBaseUrl()}/sales/weekly/${areaId}/${date}`);
        const data = await response.json();

        loader.classList.add('d-none');

        if (data.error) {
            alert('Erro: ' + data.error);
            placeholder.classList.remove('d-none');
            return;
        }

        const sales = data.weekly_sales || {};
        
        // Days of week mapping (01: Sunday, 02: Monday, ..., 07: Saturday)
        const days = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
        const getSalesVal = (dayNum) => parseFloat(sales[`soma_pedido_digitado_0${dayNum}`] || 0);
        const getSalesCount = (dayNum) => parseInt(sales[`count_pedido_0${dayNum}`] || 0);

        const faturamentoData = [
            getSalesVal(1), // Sun
            getSalesVal(2), // Mon
            getSalesVal(3), // Tue
            getSalesVal(4), // Wed
            getSalesVal(5), // Thu
            getSalesVal(6), // Fri
            getSalesVal(7)  // Sat
        ];

        const pedidosData = [
            getSalesCount(1),
            getSalesCount(2),
            getSalesCount(3),
            getSalesCount(4),
            getSalesCount(5),
            getSalesCount(6),
            getSalesCount(7)
        ];

        // Calculate KPIs
        const totalFaturamento = faturamentoData.reduce((a, b) => a + b, 0);
        const totalPedidos = pedidosData.reduce((a, b) => a + b, 0);
        const ticketMedio = totalPedidos > 0 ? (totalFaturamento / totalPedidos) : 0;

        // Render KPI values
        document.getElementById('total-faturamento-sem').textContent = totalFaturamento.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('total-pedidos-sem').textContent = totalPedidos.toLocaleString('pt-BR');
        document.getElementById('ticket-medio-sem').textContent = ticketMedio.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Update card header text
        document.getElementById('grafico-titulo-sem').textContent = `Faturamento Semanal - RCA ${data.area_nome || areaId}`;

        // Show results
        results.classList.remove('d-none');

        // Render ApexCharts (Dual Axis: Bar for faturamento, Line for orders count)
        renderWeeklyChart(days, faturamentoData, pedidosData);

    } catch (e) {
        console.error('Erro ao buscar faturamento semanal:', e);
        loader.classList.add('d-none');
        placeholder.classList.remove('d-none');
        alert('Ocorreu uma falha ao buscar os dados semanais no servidor.');
    }
}

/**
 * Render the dual axis Weekly Chart using ApexCharts
 */
function renderWeeklyChart(categories, faturamento, pedidos) {
    const container = document.getElementById('grafico_semanal_detalhado');
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
            enabledOnSeries: [0], // Show data values on Column bars
            formatter: function (val) {
                if (val === 0) return '';
                return 'R$ ' + Math.round(val).toLocaleString('pt-BR');
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

    if (weeklyChartInstance) {
        weeklyChartInstance.destroy();
    }
    weeklyChartInstance = new ApexCharts(container, options);
    weeklyChartInstance.render();
}
