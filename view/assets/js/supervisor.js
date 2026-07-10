import { getBaseUrl } from "./route.js?v=3";

let supervisorChartInstance = null;

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

    // Populate Supervisors dropdown
    loadSupervisorsDropdown();

    // Setup filter submit
    const form = document.getElementById('form-filtro-supervisor');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            loadSupervisorReport();
        });
    }
});

/**
 * Load supervisors list
 */
async function loadSupervisorsDropdown() {
    const optGroup = document.getElementById('select-supervisores-opt');
    if (!optGroup) return;

    try {
        const response = await fetch(`${getBaseUrl()}/supervisores/list`);
        const data = await response.json();

        optGroup.innerHTML = data.map(sup => 
            `<option value="${sup.codigo_supervisor}">${sup.nome_supervisor}</option>`
        ).join('');

    } catch (e) {
        console.error('Erro ao carregar supervisores:', e);
    }
}

/**
 * Fetch and render supervisor faturamento
 */
async function loadSupervisorReport() {
    const supervisorId = document.getElementById('filtro-supervisor').value;
    const dateStart = document.getElementById('filtro-data-inicio').value;
    const dateEnd = document.getElementById('filtro-data-fim').value;

    const loader = document.getElementById('loader-supervisor');
    const results = document.getElementById('resultados-supervisor');
    const placeholder = document.getElementById('placeholder-supervisor');

    if (!supervisorId || !dateStart || !dateEnd) return;

    // Toggle loading states
    loader.classList.remove('d-none');
    results.classList.add('d-none');
    placeholder.classList.add('d-none');

    try {
        const url = `${getBaseUrl()}/sales/team-sales/${supervisorId}?date_start=${dateStart}&date_end=${dateEnd}`;
        const response = await fetch(url);
        const data = await response.json();

        loader.classList.add('d-none');

        if (data.error) {
            alert('Erro: ' + data.error);
            placeholder.classList.remove('d-none');
            return;
        }

        if (!Array.isArray(data) || data.length === 0) {
            alert('Sem faturamento registrado para esta equipe no período selecionado.');
            placeholder.classList.remove('d-none');
            return;
        }

        // Sort data descending by faturamento
        data.sort((a, b) => parseFloat(b.valor_digitado || 0) - parseFloat(a.valor_digitado || 0));

        // Calculate KPIs
        let totalFaturamento = 0;
        let totalPedidos = 0;

        data.forEach(item => {
            totalFaturamento += parseFloat(item.valor_digitado || 0);
            totalPedidos += parseInt(item.quantidade_pedidos || 0);
        });

        const ticketMedio = totalPedidos > 0 ? (totalFaturamento / totalPedidos) : 0;

        // Render KPI values
        document.getElementById('total-faturamento-sup').textContent = totalFaturamento.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        document.getElementById('total-pedidos-sup').textContent = totalPedidos.toLocaleString('pt-BR');
        document.getElementById('ticket-medio-sup').textContent = ticketMedio.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Populate Table breakdown
        const tbody = document.querySelector('#table-equipe-desempenho tbody');
        if (tbody) {
            tbody.innerHTML = data.map(item => `
                <tr>
                    <td><span class="badge bg-light text-dark-emphasis border">${item.area_vendedor}</span></td>
                    <td class="fw-semibold">${item.c_nomearea || 'Sem Nome'}</td>
                    <td class="text-end">${parseInt(item.quantidade_pedidos).toLocaleString('pt-BR')}</td>
                    <td class="text-end fw-semibold text-primary-green">R$ ${parseFloat(item.valor_digitado || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                </tr>
            `).join('');
        }

        // Show results
        results.classList.remove('d-none');

        // Render ApexCharts Column Bar
        const categories = data.map(item => item.c_nomearea || `Área ${item.area_vendedor}`);
        const faturamentoData = data.map(item => parseFloat(item.valor_digitado || 0));
        renderSupervisorChart(categories, faturamentoData);

    } catch (e) {
        console.error('Erro ao buscar vendas da equipe:', e);
        loader.classList.add('d-none');
        placeholder.classList.remove('d-none');
        alert('Ocorreu uma falha ao buscar os dados da equipe no servidor.');
    }
}

/**
 * Render supervisor ApexCharts
 */
function renderSupervisorChart(categories, faturamento) {
    const container = document.getElementById('grafico_supervisor_detalhado');
    if (!container) return;
    container.innerHTML = '';

    const options = {
        series: [{
            name: 'Faturamento (BRL)',
            data: faturamento
        }],
        chart: {
            type: 'bar',
            height: 400,
            fontFamily: 'Outfit, sans-serif',
            toolbar: { show: true }
        },
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '55%',
                dataLabels: { position: 'top' } // Show faturamento on top of bars
            }
        },
        colors: ['#0fa40b'], // Corporate primary green
        dataLabels: {
            enabled: true,
            formatter: function (val) {
                if (val === 0) return '';
                return 'R$ ' + Math.round(val / 1000).toLocaleString('pt-BR') + 'k';
            },
            offsetY: -20,
            style: {
                fontSize: '10px',
                colors: ["#333"]
            }
        },
        xaxis: {
            categories: categories,
            position: 'bottom',
            labels: {
                rotate: -45,
                style: { fontSize: '10px' }
            }
        },
        yaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                show: true,
                formatter: function (val) {
                    return 'R$ ' + (val / 1000).toLocaleString('pt-BR') + 'k';
                }
            }
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
                }
            }
        },
        grid: {
            borderColor: '#f1f1f1',
            strokeDashArray: 4
        }
    };

    if (supervisorChartInstance) {
        supervisorChartInstance.destroy();
    }
    supervisorChartInstance = new ApexCharts(container, options);
    supervisorChartInstance.render();
}
