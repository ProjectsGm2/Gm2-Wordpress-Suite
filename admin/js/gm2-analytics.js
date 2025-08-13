jQuery(function($){
    if(typeof gm2Analytics !== 'undefined'){
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
    }
    if(typeof gm2SiteAnalytics !== 'undefined'){
        var sc = document.getElementById('gm2-sessions-chart');
        if(sc && window.Chart){
            new Chart(sc, {
                type: 'line',
                data: {
                    labels: gm2SiteAnalytics.dates || [],
                    datasets: [{
                        label: 'Sessions',
                        data: gm2SiteAnalytics.sessions || [],
                        borderColor: 'rgba(75,192,192,1)',
                        backgroundColor: 'rgba(75,192,192,0.2)',
                        tension: 0.1
                    }]
                },
                options: { responsive: true }
            });
        }
        var dc = document.getElementById('gm2-device-chart');
        if(dc && window.Chart){
            new Chart(dc, {
                type: 'doughnut',
                data: {
                    labels: gm2SiteAnalytics.device_labels || [],
                    datasets: [{
                        data: gm2SiteAnalytics.device_counts || [],
                        backgroundColor: ['rgba(54,162,235,0.7)', 'rgba(255,206,86,0.7)', 'rgba(153,102,255,0.7)']
                    }]
                },
                options: { responsive: true }
            });
        }
    }

    $(document).on('click', '.gm2-toggle-user-events', function(){
        var target = $(this).data('target');
        $('#gm2-events-' + target).toggle();
    });
});

