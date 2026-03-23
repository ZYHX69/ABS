let chart;

function updateChart(appointments) {
    const counts = {};
    appointments.forEach(a => {
        const date = a.appointment_date;
        counts[date] = (counts[date] || 0) + 1;
    });
    const labels = Object.keys(counts).sort();
    const data = labels.map(d => counts[d]);

    if (chart) {
        chart.data.labels = labels;
        chart.data.datasets[0].data = data;
        chart.update();
    } else {
        const ctx = document.getElementById('appointmentsChart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Appointments',
                    data: data,
                    borderColor: 'blue'
                }]
            }
        });
    }
}