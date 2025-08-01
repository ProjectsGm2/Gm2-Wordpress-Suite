jQuery(function($){
    if(typeof gm2Analytics === 'undefined'){ return; }
    var ctx = document.getElementById('gm2-analytics-trend');
    if(ctx && window.Chart && Array.isArray(gm2Analytics.dates)){
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: gm2Analytics.dates,
                datasets: [
                    {
                        label: 'Sessions',
                        data: gm2Analytics.sessions || [],
                        borderColor: 'rgba(54,162,235,1)',
                        backgroundColor: 'rgba(54,162,235,0.2)',
                        tension: 0.1,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Bounce Rate',
                        data: gm2Analytics.bounce_rate || [],
                        borderColor: 'rgba(255,99,132,1)',
                        backgroundColor: 'rgba(255,99,132,0.2)',
                        tension: 0.1,
                        yAxisID: 'y1',
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { position: 'left' },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { callback: function(v){ return v + '%'; } }
                    }
                }
            }
        });
    }
    var q = document.getElementById('gm2-query-chart');
    if(q && window.Chart && Array.isArray(gm2Analytics.queries)){
        var labels = gm2Analytics.queries.map(function(row){ return row.query; });
        var clicks = gm2Analytics.queries.map(function(row){ return row.clicks; });
        var imps = gm2Analytics.queries.map(function(row){ return row.impressions; });
        new Chart(q, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Clicks', data: clicks, backgroundColor: 'rgba(54,162,235,0.7)' },
                    { label: 'Impressions', data: imps, backgroundColor: 'rgba(255,159,64,0.7)' }
                ]
            },
            options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
        });
    }
});

