// Usage: renderSectionChart(canvas, chartData)
// chartData: { labels: [...], values: [...] }
function renderSectionChart(canvas, chartData) {
    // Define a palette of colors for columns (feel free to update)
    const colorPalette = [
        "#3498db", // blue
        "#f39c12", // orange
        "#2ecc71", // green
        "#e74c3c", // red
        "#9b59b6", // purple
        "#1abc9c", // turquoise
        "#e67e22", // carrot
        "#34495e", // dark blue
        "#7f8c8d", // gray
        "#95a5a6"  // light gray
    ];
    // Assign a color for each column, cycle if more columns than palette
    const barColors = chartData.labels.map((_, i) =>
        colorPalette[i % colorPalette.length]
    );

    return new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Average Score',
                data: chartData.values,
                backgroundColor: barColors
            }]
        },
        options: {
            responsive: false,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }},
            scales: {
                y: { min: 0, max: 5, ticks: { stepSize: 1 } }
            }
        }
    });
}