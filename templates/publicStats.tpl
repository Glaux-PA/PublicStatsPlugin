{**
 * templates/publicStats.tpl
 *
 * 
 * 
 *}
{include file="frontend/components/header.tpl"}

{* Chart.js *}
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

{* Leaflet CSS y JS *}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="page page_statistics">
    <div class="container">
        {include file="frontend/components/breadcrumbs.tpl" currentTitleKey="plugins.publicStats.title.key"}
        <header class="page-header">
            <h1>{translate key="plugins.generic.publicStats.displayName"}</h1>
        </header>

        <div class="stats-grid">

            {* --- GRÁFICO DE TENDENCIA --- *}
            <div class="stats-card">
                <div class="card-header">
                    <h2>{translate key="plugins.generic.publicStats.downloadsLast12Months"}</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyDownloadsChart"></canvas>
                    </div>
                </div>
            </div>

            {* --- MAPA MUNDIAL --- *}
            <div class="stats-card">
                <div class="card-header">
                    <h2>{translate key="plugins.generic.publicStats.worldwideDistribution"}</h2>
                    <div class="map-legend">
                        <span class="legend-item">
                            <div class="legend-color" style="background: #ff6b6b;"></div>
                            <span>{translate key="plugins.generic.publicStats.legend.moreThan1000"}</span>
                        </span>
                        <span class="legend-item">
                            <div class="legend-color" style="background: #4ecdc4;"></div>
                            <span>{translate key="plugins.generic.publicStats.legend.between500and1000"}</span>
                        </span>
                        <span class="legend-item">
                            <div class="legend-color" style="background: #45b7d1;"></div>
                            <span>{translate key="plugins.generic.publicStats.legend.between100and500"}</span>
                        </span>
                        <span class="legend-item">
                            <div class="legend-color" style="background: #96ceb4;"></div>
                            <span>{translate key="plugins.generic.publicStats.legend.lessThan100"}</span>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="worldMap" style="height: 450px; width: 100%;"></div>
                </div>
            </div>

            {* --- TABLA DE TOP 10 ARTÍCULOS --- *}
            <div class="stats-card">
                <div class="card-header">
                    <h2>{translate key="plugins.generic.publicStats.mostDownloaded"}</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                    <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                </tr>
                            </thead>
                            <tbody id="topArticlesTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    {literal}
        document.addEventListener('DOMContentLoaded', function() {

            const topArticlesData = {/literal}{$topArticlesData}{literal};
            const monthlyDownloadsData = {/literal}{$monthlyDownloadsData}{literal};
            const countryDownloadsData = {/literal}{$countryDownloadsData}{literal};

            // --- POBLAR LA TABLA ---
            if (topArticlesData && topArticlesData.length > 0) {
                const tableBody = document.getElementById('topArticlesTableBody');
                topArticlesData.forEach(item => {
                    let row = tableBody.insertRow();
                    row.insertCell(0).innerHTML = item.title;
                    row.insertCell(1).innerHTML = item.total_downloads;
                });
            }

            // --- CREAR EL GRÁFICO DE LÍNEAS ---
            if (monthlyDownloadsData && monthlyDownloadsData.length > 0) {
                const monthlyDownloadsCtx = document.getElementById('monthlyDownloadsChart').getContext('2d');
                new Chart(monthlyDownloadsCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyDownloadsData.map(item => item.month_name),
                        datasets: [{
                            label: 'Total Descargas',
                            data: monthlyDownloadsData.map(item => item.total_downloads),
                            fill: true,
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            // --- CREAR EL MAPA MUNDIAL CON LEAFLET ---
            if (countryDownloadsData && countryDownloadsData.length > 0) {
                const map = L.map('worldMap', {
                    center: [20, 0],
                    zoom: 2,
                    zoomControl: true,
                    scrollWheelZoom: true
                });

                L.tileLayer('https://{/literal}{ldelim}s{rdelim}{literal}.tile.openstreetmap.org/{/literal}{ldelim}z{rdelim}{literal}/{/literal}{ldelim}x{rdelim}{literal}/{/literal}{ldelim}y{rdelim}{literal}.png', {
                attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
            }).addTo(map);

        const countryCoordinates = {
            'US': [39.8283, -98.5795],
            'ES': [40.4637, -3.7492],
            'MX': [23.6345, -102.5528],
            'AR': [-38.4161, -63.6167],
            'CO': [4.5709, -74.2973],
            'CL': [-35.6751, -71.5430],
            'PE': [-9.1900, -75.0152],
            'BR': [-14.2350, -51.9253],
            'CA': [56.1304, -106.3468],
            'GB': [55.3781, -3.4360],
            'DE': [51.1657, 10.4515],
            'FR': [46.6034, 1.8883],
            'IT': [41.8719, 12.5674],
            'PT': [39.3999, -8.2245],
            'NL': [52.1326, 5.2913],
            'BE': [50.5039, 4.4699],
            'AU': [-25.2744, 133.7751],
            'JP': [36.2048, 138.2529],
            'CN': [35.8617, 104.1954],
            'IN': [20.5937, 78.9629],
            'RU': [61.5240, 105.3188],
            'ZA': [-30.5595, 22.9375],
            'TR': [38.9637, 35.2433],
            'GR': [39.0742, 21.8243],
            'PL': [51.9194, 19.1451],
            'SE': [60.1282, 18.6435],
            'NO': [60.4720, 8.4689],
            'DK': [56.2639, 9.5018],
            'FI': [61.9241, 25.7482],
            'CH': [46.8182, 8.2275],
            'AT': [47.5162, 14.5501],
            'CZ': [49.8175, 15.4730],
            'HU': [47.1625, 19.5033],
            'RO': [45.9432, 24.9668],
            'BG': [42.7339, 25.4858],
            'HR': [45.1000, 15.2000],
            'RS': [44.0165, 21.0059],
            'UA': [48.3794, 31.1656],
            'BY': [53.7098, 27.9534],
            'LT': [55.1694, 23.8813],
            'LV': [56.8796, 24.6032],
            'EE': [58.5953, 25.0136],
            'SI': [46.1512, 14.9955],
            'SK': [48.6690, 19.6990],
            'IE': [53.4129, -8.2439],
            'IS': [64.9631, -19.0208],
            'MT': [35.9375, 14.3754],
            'CY': [35.1264, 33.4299],
            'IL': [31.0461, 34.8516],
            'EG': [26.0975, 30.0444],
            'MA': [31.7917, -7.0926],
            'DZ': [28.0339, 1.6596],
            'TN': [33.8869, 9.5375],
            'LY': [26.3351, 17.2283],
            'NG': [9.0820, 8.6753],
            'KE': [-0.0236, 37.9062],
            'GH': [7.9465, -1.0232],
            'KR': [35.9078, 127.7669],
            'TH': [15.8700, 100.9925],
            'VN': [14.0583, 108.2772],
            'PH': [12.8797, 121.7740],
            'ID': [-0.7893, 113.9213],
            'MY': [4.2105, 101.9758],
            'SG': [1.3521, 103.8198],
            'NZ': [-40.9006, 174.8860],
            'EC': [-1.8312, -78.1834],
            'VE': [6.4238, -66.5897],
            'UY': [-32.5228, -55.7658],
            'PY': [-23.4425, -58.4438],
            'BO': [-16.2902, -63.5887],
            'CR': [9.7489, -83.7534],
            'PA': [8.5380, -80.7821],
            'GT': [15.7835, -90.2308],
            'HN': [15.2000, -86.2419],
            'NI': [12.2658, -85.2072],
            'SV': [13.7942, -88.8965],
            'DO': [18.7357, -70.1627],
            'CU': [21.5218, -77.7812],
            'JM': [18.1096, -77.2975],
            'HT': [18.9712, -72.2852],
            'PR': [18.2208, -66.5901]
        };
        // Función para determinar color según las descargas
        function getCircleColor(downloads) {
            if (downloads >= 1000) return '#ff6b6b';
            if (downloads >= 500) return '#4ecdc4';
            if (downloads >= 100) return '#45b7d1';
            return '#96ceb4';
        }

        // Función para determinar tamaño del círculo según las descargas
        function getCircleSize(downloads) {
            const minSize = 8;
            const maxSize = 30;
            const maxDownloads = Math.max(...countryDownloadsData.map(c => c.total_downloads));
            const normalizedSize = (downloads / maxDownloads) * (maxSize - minSize) + minSize;
            return Math.max(minSize, Math.min(maxSize, normalizedSize));
        }

        // Agregar círculos para cada país con datos
        countryDownloadsData.forEach(country => {
            const coordinates = countryCoordinates[country.country_code];

            if (coordinates) {
                const circleSize = getCircleSize(country.total_downloads);
                const circleColor = getCircleColor(country.total_downloads);

                L.circleMarker(coordinates, {
                        radius: circleSize,
                        fillColor: circleColor,
                        color: '#fff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.7
                    })
                    .bindPopup(`
                        <div class="country-popup">
<h3>${country.country_name}</h3>
<div class="downloads">${country.total_downloads.toLocaleString()}</div>
                            <div>descargas</div>
                        </div>
                    `)
                    .addTo(map);
            }
        });

        // Ajustar vista del mapa para mostrar todos los marcadores
        setTimeout(() => {
            const group = new L.featureGroup();
            countryDownloadsData.forEach(country => {
                const coordinates = countryCoordinates[country.country_name];
                if (coordinates) {
                    group.addLayer(L.marker(coordinates));
                }
            });
            if (group.getLayers().length > 0) {
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }, 100);
        }

        });
    {/literal}
</script>

{include file="frontend/components/footer.tpl"}