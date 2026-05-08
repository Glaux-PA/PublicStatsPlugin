/**
 * Public Statistics - Shared helpers.
 *
 * This file holds the pieces of the frontend that do not reference the
 * other internal modules (API, Navigation, Charts, ...), which makes them
 * safe to live in their own <script> without cross-module coordination.
 *
 * Everything here is attached to window.PublicStatsHelpers so statistics.js
 * can pull it in with a single destructuring line at the top of its IIFE.
 *
 * Depends on one global that publicStats.tpl defines before loading any JS:
 *   - i18n (translations bag)
 */
(function () {
  "use strict";

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  /**
   * Sections whose data is re-fetched when the user changes the year selector.
   * statistics.js checks this list to decide whether a section needs a refresh.
   */
  const YEAR_FILTERABLE_SECTIONS = [
    "monthly-trends",
    "general-downloads",
    "general-views",
    "general-issues",
    "general-sections",
    "editorial-submissions",
    "first-decision-stats",
    "acceptance-publication-stats",
    "author-individual-stats",
    "citation-evolution",
  ];

  /**
   * Shared registry of Chart.js instances keyed by section.
   * Used to dispose old charts before re-rendering and to support zoom reset.
   */
  const ChartInstances = {
    monthly: null,
    annual: null,
    issue: null,
    section: null,
    worldMap: null,
    editorial: null,
    editorialAnnual: null,
    authorsMap: null,
    institution: null,
    reviewersMap: null,
    reviewerInstitution: null,
    citingJournals: null,
    language: null,
    languageTrends: null,
  };

  /**
   * Small DOM helpers used across the UI.
   */
  const Utils = {
    showNoDataMessage(containerId, message) {
      const container = document.getElementById(containerId);
      if (!container) return;

      container.innerHTML = `
                <div class="ps-empty-state">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ps-empty-state-icon">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p class="ps-empty-state-title">${
                      i18n.noDataAvailable || "No data available"
                    }</p>
                    <p class="ps-empty-state-desc">${
                      message ||
                      i18n.noDataMessage ||
                      "No data available for the selected period"
                    }</p>
                </div>
            `;
    },

    generateColors(count) {
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
    },

    showLoadingIndicator() {
      const indicator = document.getElementById("loadingIndicator");
      if (indicator) indicator.style.display = "flex";
    },

    hideLoadingIndicator() {
      const indicator = document.getElementById("loadingIndicator");
      if (indicator) indicator.style.display = "none";
    },
  };

  /**
   * Base Chart.js option builders shared by most chart renderers.
   */
  const ChartConfig = {
    getLineChartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: "index",
          intersect: false,
        },
        plugins: {
          legend: {
            display: true,
            position: "top",
          },
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

            limits: {
              x: { min: "original", max: "original" },
              y: { min: 0 },
            },
          },
        },
        scales: {
          x: {
            grid: { display: false },
          },
          y: {
            beginAtZero: true,
            grid: { color: "rgba(0,0,0,0.05)" },
            ticks: { precision: 0 },
          },
        },
      };
    },

    getPieChartOptions() {
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
    },
  };

  window.PublicStatsHelpers = {
    escapeHtml,
    YEAR_FILTERABLE_SECTIONS,
    ChartInstances,
    Utils,
    ChartConfig,
  };
})();
