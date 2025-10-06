// Global chart instances
let monthlyChart = null;
let annualChart = null;
let issueChart = null;
let sectionChart = null;
let worldMapInstance = null;
let editorialChart = null;

function toggleSection(sectionId) {
  const content = document.getElementById(sectionId + "-content");
  if (content) {
    const isCollapsed = content.classList.contains("section-collapsed");
    content.classList.toggle("section-collapsed");
    const section = content.closest(".sidebar-section");
    const toggle = section.querySelector(".section-toggle");
    if (toggle) {
      toggle.style.transform = isCollapsed ? "rotate(0deg)" : "rotate(-90deg)";
    }
  }
}

function showSection(sectionId) {
  document.querySelectorAll(".content-section").forEach((section) => {
    section.style.display = "none";
  });
  document.querySelectorAll(".menu-link").forEach((link) => {
    link.classList.remove("active");
  });
  const targetSection = document.getElementById(sectionId);
  if (targetSection) targetSection.style.display = "block";
  if (
    typeof event !== "undefined" &&
    event.type !== "DOMContentLoaded" &&
    event.target
  ) {
    event.target.classList.add("active");
  }
  setTimeout(() => initializeSectionContent(sectionId), 100);
}

function initializeSectionContent(sectionId) {
  const handlers = {
    "monthly-trends": initializeMonthlyChart,
    "annual-trends": initializeAnnualChart,
    "geographic-distribution": initializeWorldMap,
    "general-downloads": renderTopDownloadedArticles,
    "general-views": renderTopViewedArticles,
    "general-issues": () => {
      initializeIssueChart();
      renderIssueTable();
    },
    "general-sections": () => {
      initializeSectionChart();
      renderSectionTable();
    },
    "recent-downloads": renderRecentTopDownloaded,
    "recent-views": renderRecentTopViewed,
    "editorial-submissions": () => {
      initializeEditorialChart();
      renderEditorialSummary();
    },
  };
  if (handlers[sectionId]) handlers[sectionId]();
}

function updateSectionTitles(year) {
  const yearText = year ? ` (${year})` : "";
  const titles = {
    "monthly-trends": `Monthly overview${yearText}`,
    "general-downloads": `Most downloaded articles${yearText}`,
    "general-views": `Most viewed articles${yearText}`,
    "general-issues": `Downloads by Issue${yearText}`,
    "general-sections": `Downloads by section${yearText}`,
    "editorial-submissions": `Submissions Overview${yearText}`,
  };
  Object.keys(titles).forEach((id) => {
    const section = document.getElementById(id);
    if (section) {
      const title = section.querySelector(".content-title");
      if (title) title.textContent = titles[id];
    }
  });
  const issueTable = document.querySelector(
    "#general-issues .stats-card:nth-child(2) .card-title"
  );
  if (issueTable) issueTable.textContent = `Downloads by issue${yearText}`;
}

function changeYear(year) {
  showLoadingIndicator();
  const url =
    window.location.origin +
    window.location.pathname.replace("/total", "/getStatsData");
  const params = new URLSearchParams();
  if (year) params.append("year", year);
  const fullUrl = url + (params.toString() ? "?" + params.toString() : "");

  fetch(fullUrl)
    .then((response) => {
      if (!response.ok) throw new Error("Network response was not ok");
      return response.json();
    })
    .then((data) => {
      updateStatsData(data);
      updateSectionTitles(year);
      const newUrl = new URL(window.location.href);
      if (year) newUrl.searchParams.set("year", year);
      else newUrl.searchParams.delete("year");
      window.history.pushState({ year: year }, "", newUrl.toString());
      const current = document.querySelector(
        '.content-section[style*="display: block"]'
      );
      if (current) initializeSectionContent(current.id);
      hideLoadingIndicator();
    })
    .catch((error) => {
      console.error("Error loading statistics:", error);
      hideLoadingIndicator();
      alert("Error loading statistics. Please try again.");
    });
}

function updateStatsData(data) {
  statsData.monthlyStats = data.monthlyStats || [];
  statsData.topArticlesByDownloads = data.topDownloadedArticles || [];
  statsData.topArticlesByViews = data.topViewedArticles || [];
  statsData.annualStats = data.annualStats || [];
  statsData.issueStats = data.issueStats || [];
  statsData.sectionStats = data.sectionStats || [];
  statsData.recentTopDownloaded = data.recentTopDownloaded || [];
  statsData.recentTopViewed = data.recentTopViewed || [];
  statsData.editorialStats = data.editorialStats || [];
}

function showLoadingIndicator() {
  const indicator = document.getElementById("loadingIndicator");
  if (indicator) indicator.style.display = "flex";
}

function hideLoadingIndicator() {
  const indicator = document.getElementById("loadingIndicator");
  if (indicator) indicator.style.display = "none";
}

window.addEventListener("popstate", function (event) {
  if (event.state && event.state.year !== undefined) {
    const yearSelector = document.getElementById("yearSelector");
    if (yearSelector) {
      yearSelector.value = event.state.year || "";
      changeYear(event.state.year);
    }
  }
});

function resetChartZoom(chartId) {
  const charts = {
    monthlyStatsChart: monthlyChart,
    annualStatsChart: annualChart,
    editorialStatsChart: editorialChart,
    issueStatsChart: issueChart,
    sectionStatsChart: sectionChart,
  };
  const chart = charts[chartId];
  if (chart && chart.resetZoom) chart.resetZoom();
}

// Chart configurations
function getChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
      legend: { display: true, position: "top" },
      zoom: {
        zoom: {
          wheel: { enabled: true, speed: 0.1 },
          pinch: { enabled: true },
          drag: {
            enabled: true,
            backgroundColor: "rgba(139, 38, 53, 0.1)",
            borderColor: "#8b2635",
            borderWidth: 1,
          },
          mode: "x",
        },
        pan: { enabled: true, mode: "x" },
        limits: { x: { min: "original", max: "original" }, y: { min: 0 } },
      },
    },
    scales: {
      x: { grid: { display: false } },
      y: {
        beginAtZero: true,
        grid: { color: "rgba(0,0,0,0.05)" },
        ticks: { precision: 0 },
      },
    },
  };
}

function getPieChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: function (context) {
            const value = context.parsed || 0;
            const total = context.dataset.data.reduce(
              (a, b) => Number(a) + Number(b),
              0
            );
            const percentage =
              total > 0 ? ((value / total) * 100).toFixed(1) : 0;
            return `${
              context.label
            }: ${value.toLocaleString()} (${percentage}%)`;
          },
        },
      },
    },
  };
}

function generateColors(count) {
  const base = [
    "#8b2635",
    "#36a2eb",
    "#ff6b6b",
    "#4ecdc4",
    "#45b7d1",
    "#96ceb4",
    "#feca57",
    "#ff6348",
    "#1dd1a1",
    "#5f27cd",
    "#48dbfb",
    "#ff9ff3",
    "#54a0ff",
    "#00d2d3",
  ];
  if (count <= base.length) return base.slice(0, count);
  const colors = [...base];
  const step = 360 / (count - base.length);
  for (let i = base.length; i < count; i++) {
    colors.push(`hsl(${(step * (i - base.length)) % 360}, 70%, 60%)`);
  }
  return colors;
}

function initializeMonthlyChart() {
  const ctx = document.getElementById("monthlyStatsChart");
  if (!ctx || !ctx.getContext) return;
  if (monthlyChart) monthlyChart.destroy();
  const filtered = statsData.monthlyStats.slice(
    statsData.monthlyStats.findLastIndex((item) => item.total === 0) + 1
  );
  monthlyChart = new Chart(ctx.getContext("2d"), {
    type: "line",
    data: {
      labels: filtered.map((item) => item.downloads.label),
      datasets: [
        {
          label: "Downloads",
          data: filtered.map((item) => item.downloads.value),
          fill: true,
          backgroundColor: "rgba(139, 38, 53, 0.1)",
          borderColor: "rgba(139, 38, 53, 1)",
          borderWidth: 2,
          pointBackgroundColor: "rgba(139, 38, 53, 1)",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
        },
        {
          label: "Views",
          data: filtered.map((item) => item.views.value),
          fill: true,
          backgroundColor: "rgba(54, 162, 235, 0.1)",
          borderColor: "rgba(54, 162, 235, 1)",
          borderWidth: 2,
          pointBackgroundColor: "rgba(54, 162, 235, 1)",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
        },
      ],
    },
    options: getChartOptions(),
  });
}

function initializeAnnualChart() {
  const ctx = document.getElementById("annualStatsChart");
  if (!ctx || !ctx.getContext) return;
  if (annualChart) annualChart.destroy();
  const filtered = statsData.annualStats.slice(
    statsData.annualStats.findLastIndex((item) => item.total === 0) + 1
  );
  annualChart = new Chart(ctx.getContext("2d"), {
    type: "line",
    data: {
      labels: filtered.map((item) => item.year),
      datasets: [
        {
          label: "Downloads",
          data: filtered.map((item) => item.downloads),
          fill: true,
          backgroundColor: "rgba(139, 38, 53, 0.1)",
          borderColor: "rgba(139, 38, 53, 1)",
          borderWidth: 2,
          pointBackgroundColor: "rgba(139, 38, 53, 1)",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
        },
        {
          label: "Views",
          data: filtered.map((item) => item.views),
          fill: true,
          backgroundColor: "rgba(54, 162, 235, 0.1)",
          borderColor: "rgba(54, 162, 235, 1)",
          borderWidth: 2,
          pointBackgroundColor: "rgba(54, 162, 235, 1)",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
        },
      ],
    },
    options: getChartOptions(),
  });
}

function initializeEditorialChart() {
  const ctx = document.getElementById("editorialStatsChart");
  if (!ctx || !ctx.getContext || !statsData.editorialStats) return;
  if (editorialChart) editorialChart.destroy();
  editorialChart = new Chart(ctx.getContext("2d"), {
    type: "line",
    data: {
      labels: statsData.editorialStats.map((item) => item.label),
      datasets: [
        {
          label: "Received",
          data: statsData.editorialStats.map((item) => item.received),
          borderColor: "#3498db",
          backgroundColor: "rgba(52, 152, 219, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#3498db",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
          fill: true,
        },
        {
          label: "Published",
          data: statsData.editorialStats.map((item) => item.published),
          borderColor: "#2ecc71",
          backgroundColor: "rgba(46, 204, 113, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#2ecc71",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
          fill: true,
        },
        {
          label: "Declined",
          data: statsData.editorialStats.map((item) => item.declined),
          borderColor: "#e74c3c",
          backgroundColor: "rgba(231, 76, 60, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#e74c3c",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
          fill: true,
        },
        {
          label: "In Process",
          data: statsData.editorialStats.map((item) => item.inProcess),
          borderColor: "#f39c12",
          backgroundColor: "rgba(243, 156, 18, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#f39c12",
          pointBorderColor: "#fff",
          pointBorderWidth: 2,
          pointRadius: 4,
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: getChartOptions(),
  });
}

function initializeIssueChart() {
  const ctx = document.getElementById("issueStatsChart");
  if (!ctx || !ctx.getContext || !statsData.issueStats) return;
  if (issueChart) issueChart.destroy();
  const colors = generateColors(statsData.issueStats.length);
  issueChart = new Chart(ctx.getContext("2d"), {
    type: "pie",
    data: {
      labels: statsData.issueStats.map((item) => item.title),
      datasets: [
        {
          data: statsData.issueStats.map((item) => item.downloads),
          backgroundColor: colors,
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: getPieChartOptions(),
  });
}

function initializeSectionChart() {
  const ctx = document.getElementById("sectionStatsChart");
  if (!ctx || !ctx.getContext || !statsData.sectionStats) return;
  if (sectionChart) sectionChart.destroy();
  const colors = generateColors(statsData.sectionStats.length);
  sectionChart = new Chart(ctx.getContext("2d"), {
    type: "pie",
    data: {
      labels: statsData.sectionStats.map((item) => item.sectionTitle),
      datasets: [
        {
          data: statsData.sectionStats.map((item) => item.downloads),
          backgroundColor: colors,
          borderColor: "#fff",
          borderWidth: 2,
        },
      ],
    },
    options: getPieChartOptions(),
  });
}

// Map functions
function getCountryCoordinates(code) {
  const coords = {
    US: [39.8283, -98.5795],
    ES: [40.4637, -3.7492],
    MX: [23.6345, -102.5528],
    AR: [-38.4161, -63.6167],
    CO: [4.5709, -74.2973],
    CL: [-35.6751, -71.543],
    PE: [-9.19, -75.0152],
    BR: [-14.235, -51.9253],
    CA: [56.1304, -106.3468],
    GB: [55.3781, -3.436],
    DE: [51.1657, 10.4515],
    FR: [46.6034, 1.8883],
    IT: [41.8719, 12.5674],
    PT: [39.3999, -8.2245],
    NL: [52.1326, 5.2913],
    BE: [50.5039, 4.4699],
    AU: [-25.2744, 133.7751],
    JP: [36.2048, 138.2529],
    CN: [35.8617, 104.1954],
    IN: [20.5937, 78.9629],
    RU: [61.524, 105.3188],
    ZA: [-30.5595, 22.9375],
    TR: [38.9637, 35.2433],
    GR: [39.0742, 21.8243],
    PL: [51.9194, 19.1451],
    SE: [60.1282, 18.6435],
    NO: [60.472, 8.4689],
    DK: [56.2639, 9.5018],
    FI: [61.9241, 25.7482],
    CH: [46.8182, 8.2275],
    AT: [47.5162, 14.5501],
    CZ: [49.8175, 15.473],
    HU: [47.1625, 19.5033],
    RO: [45.9432, 24.9668],
    BG: [42.7339, 25.4858],
    HR: [45.1, 15.2],
    RS: [44.0165, 21.0059],
    UA: [48.3794, 31.1656],
    BY: [53.7098, 27.9534],
    LT: [55.1694, 23.8813],
    LV: [56.8796, 24.6032],
    EE: [58.5953, 25.0136],
    SI: [46.1512, 14.9955],
    SK: [48.669, 19.699],
    IE: [53.4129, -8.2439],
    IS: [64.9631, -19.0208],
    MT: [35.9375, 14.3754],
    CY: [35.1264, 33.4299],
    IL: [31.0461, 34.8516],
    EG: [26.0975, 30.0444],
    MA: [31.7917, -7.0926],
    DZ: [28.0339, 1.6596],
    TN: [33.8869, 9.5375],
    LY: [26.3351, 17.2283],
    NG: [9.082, 8.6753],
    KE: [-0.0236, 37.9062],
    GH: [7.9465, -1.0232],
    KR: [35.9078, 127.7669],
    TH: [15.87, 100.9925],
    VN: [14.0583, 108.2772],
    PH: [12.8797, 121.774],
    ID: [-0.7893, 113.9213],
    MY: [4.2105, 101.9758],
    SG: [1.3521, 103.8198],
    NZ: [-40.9006, 174.886],
    EC: [-1.8312, -78.1834],
    VE: [6.4238, -66.5897],
    UY: [-32.5228, -55.7658],
    PY: [-23.4425, -58.4438],
    BO: [-16.2902, -63.5887],
    CR: [9.7489, -83.7534],
    PA: [8.538, -80.7821],
    GT: [15.7835, -90.2308],
    HN: [15.2, -86.2419],
    NI: [12.2658, -85.2072],
    SV: [13.7942, -88.8965],
    DO: [18.7357, -70.1627],
    CU: [21.5218, -77.7812],
    JM: [18.1096, -77.2975],
    HT: [18.9712, -72.2852],
    PR: [18.2208, -66.5901],
  };
  return coords[code] || null;
}

function initializeWorldMap() {
  const container = document.getElementById("worldMap");
  if (!container || typeof L === "undefined" || !statsData.countryData) return;
  if (worldMapInstance) worldMapInstance.remove();
  worldMapInstance = L.map("worldMap", { center: [20, 0], zoom: 2 });
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "© OpenStreetMap contributors",
  }).addTo(worldMapInstance);
  const maxAccess = Math.max(
    ...statsData.countryData.map((c) => c.total_access)
  );
  statsData.countryData.forEach((country) => {
    const coords = getCountryCoordinates(country.country_code);
    if (coords) {
      const size = (country.total_access / maxAccess) * 17 + 8;
      const color =
        country.total_access >= 1000
          ? "#ff6b6b"
          : country.total_access >= 500
          ? "#4ecdc4"
          : country.total_access >= 100
          ? "#45b7d1"
          : "#96ceb4";
      const marker = L.circleMarker(coords, {
        radius: size,
        fillColor: color,
        color: "#fff",
        weight: 2,
        opacity: 1,
        fillOpacity: 0.7,
      });
      marker.bindPopup(`
        <div style="text-align: center; min-width: 120px;">
          <h3 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">${
            country.country_name
          }</h3>
          <div style="font-size: 16px; font-weight: bold; color: #8b2635;">${country.total_access.toLocaleString()}</div>
          <div style="font-size: 12px; color: #666;">total accesses</div>
        </div>
      `);
      marker.addTo(worldMapInstance);
    }
  });
}

// Table rendering
function renderArticlesTable(bodyId, articles, key) {
  const body = document.getElementById(bodyId);
  if (!body) return;
  body.innerHTML = "";
  articles.forEach((article) => {
    const row = body.insertRow();
    row.insertCell(
      0
    ).innerHTML = `<a href="${article.urlPublished}">${article.title}</a>`;
    row.insertCell(1).innerHTML = article[key];
  });
}

function renderTopDownloadedArticles() {
  renderArticlesTable(
    "topArticlesByDownloadsTableBody",
    statsData.topArticlesByDownloads,
    "downloads"
  );
}

function renderTopViewedArticles() {
  renderArticlesTable(
    "topArticlesByViewsTableBody",
    statsData.topArticlesByViews,
    "views"
  );
}

function renderRecentTopDownloaded() {
  renderArticlesTable(
    "recentTopDownloadsTableBody",
    statsData.recentTopDownloaded,
    "downloads"
  );
}

function renderRecentTopViewed() {
  renderArticlesTable(
    "recentTopViewsTableBody",
    statsData.recentTopViewed,
    "views"
  );
}

function renderIssueTable() {
  const body = document.getElementById("issueStatsTableBody");
  if (!body || !statsData.issueStats) return;
  body.innerHTML = "";
  const colors = generateColors(statsData.issueStats.length);
  statsData.issueStats.forEach((issue, i) => {
    const row = body.insertRow();
    row.insertCell(
      0
    ).innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[i]}"></span>`;
    row.insertCell(1).textContent = issue.title;
    row.insertCell(2).textContent = issue.downloads.toLocaleString();
    row.insertCell(3).textContent = issue.articleCount.toLocaleString();
  });
}

function renderSectionTable() {
  const body = document.getElementById("sectionStatsTableBody");
  if (!body || !statsData.sectionStats) return;
  body.innerHTML = "";
  const colors = generateColors(statsData.sectionStats.length);
  statsData.sectionStats.forEach((section, i) => {
    const row = body.insertRow();
    row.insertCell(
      0
    ).innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[i]}"></span>`;
    row.insertCell(1).textContent = section.sectionTitle;
    row.insertCell(2).textContent = section.downloads.toLocaleString();
    row.insertCell(3).textContent = section.articleCount.toLocaleString();
  });
}

function renderEditorialSummary() {
  const div = document.getElementById("editorialSummary");
  if (!div || !statsData.editorialStats) return;
  const totals = statsData.editorialStats.reduce(
    (acc, item) => {
      acc.received += item.received;
      acc.published += item.published;
      acc.declined += item.declined;
      acc.inProcess += item.inProcess;
      return acc;
    },
    { received: 0, published: 0, declined: 0, inProcess: 0 }
  );
  div.innerHTML = `
    <div style="text-align: center;">
      <div style="font-size: 32px; font-weight: bold; color: #3498db;">${totals.received}</div>
      <div style="color: #666; margin-top: 5px;">Total Received</div>
    </div>
    <div style="text-align: center;">
      <div style="font-size: 32px; font-weight: bold; color: #2ecc71;">${totals.published}</div>
      <div style="color: #666; margin-top: 5px;">Published</div>
    </div>
    <div style="text-align: center;">
      <div style="font-size: 32px; font-weight: bold; color: #e74c3c;">${totals.declined}</div>
      <div style="color: #666; margin-top: 5px;">Declined</div>
    </div>
    <div style="text-align: center;">
      <div style="font-size: 32px; font-weight: bold; color: #f39c12;">${totals.inProcess}</div>
      <div style="color: #666; margin-top: 5px;">In Process</div>
    </div>
  `;
}

document.addEventListener("DOMContentLoaded", function () {
  const firstLink = document.querySelector(".menu-link");
  if (firstLink) firstLink.classList.add("active");
  showSection("monthly-trends");
});
