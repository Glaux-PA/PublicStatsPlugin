/**
 * Public Statistics - Chart.js renderers.
 *
 * Registers window.PublicStats.Charts. Depends on
 * window.PublicStatsHelpers (Utils, ChartInstances, ChartConfig) and the
 * template globals statsData and i18n.
 */
(function () {
  "use strict";

  const { Utils, ChartInstances, ChartConfig } = window.PublicStatsHelpers;
  const PS = (window.PublicStats = window.PublicStats || {});

  const Charts = {
    initializeMonthlyChart() {
      const ctx = document.getElementById("monthlyStatsChart");
      if (!ctx || !ctx.getContext) return;

      if (!statsData.monthlyStats || statsData.monthlyStats.length === 0) {
        Utils.showNoDataMessage("monthlyStatsChart", i18n.noMonthlyData);
        return;
      }

      if (ChartInstances.monthly) ChartInstances.monthly.destroy();

      ChartInstances.monthly = new Chart(ctx.getContext("2d"), {
        type: "line",
        data: {
          labels: statsData.monthlyStats.map((item) => item.downloads.label),
          datasets: [
            {
              label: i18n.downloads,
              data: statsData.monthlyStats.map((item) => item.downloads.value),
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
              label: i18n.views,
              data: statsData.monthlyStats.map((item) => item.views.value),
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
        options: ChartConfig.getLineChartOptions(),
      });
    },

    initializeAnnualChart() {
      const ctx = document.getElementById("annualStatsChart");
      if (!ctx || !ctx.getContext) return;

      if (!statsData.annualStats || statsData.annualStats.length === 0) {
        Utils.showNoDataMessage("annualStatsChart", i18n.noAnnualData);
        return;
      }

      if (ChartInstances.annual) ChartInstances.annual.destroy();

      ChartInstances.annual = new Chart(ctx.getContext("2d"), {
        type: "line",
        data: {
          labels: statsData.annualStats.map((item) => item.year),
          datasets: [
            {
              label: i18n.downloads,
              data: statsData.annualStats.map((item) => item.downloads),
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
              label: i18n.views,
              data: statsData.annualStats.map((item) => item.views),
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
        options: ChartConfig.getLineChartOptions(),
      });
    },

    initializeEditorialChart() {
      const ctx = document.getElementById("editorialStatsChart");
      if (!ctx || !ctx.getContext || !statsData.editorialStats) return;

      if (!statsData.editorialStats || statsData.editorialStats.length === 0) {
        Utils.showNoDataMessage("editorialStatsChart", i18n.noEditorialData);
        return;
      }

      if (ChartInstances.editorial) ChartInstances.editorial.destroy();

      ChartInstances.editorial = new Chart(ctx.getContext("2d"), {
        type: "line",
        data: {
          labels: statsData.editorialStats.map((item) => item.label),
          datasets: [
            {
              label: i18n.received,
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
              label: i18n.published,
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
              label: i18n.declined,
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
              label: i18n.inProcess,
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
        options: ChartConfig.getLineChartOptions(),
      });
    },

    initializeEditorialAnnualChart() {
      const ctx = document.getElementById("editorialAnnualChart");
      if (!ctx || !ctx.getContext || !statsData.editorialStatsAnnual) return;

      if (
        !statsData.editorialStatsAnnual ||
        statsData.editorialStatsAnnual.length === 0
      ) {
        Utils.showNoDataMessage(
          "editorialAnnualChart",
          i18n.noEditorialAnnualData,
        );
        return;
      }

      if (ChartInstances.editorialAnnual)
        ChartInstances.editorialAnnual.destroy();

      ChartInstances.editorialAnnual = new Chart(ctx.getContext("2d"), {
        type: "line",
        data: {
          labels: statsData.editorialStatsAnnual.map((item) => item.label),
          datasets: [
            {
              label: i18n.received,
              data: statsData.editorialStatsAnnual.map((item) => item.received),
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
              label: i18n.published,
              data: statsData.editorialStatsAnnual.map(
                (item) => item.published,
              ),
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
              label: i18n.declined,
              data: statsData.editorialStatsAnnual.map((item) => item.declined),
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
              label: i18n.inProcess,
              data: statsData.editorialStatsAnnual.map(
                (item) => item.inProcess,
              ),
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
        options: ChartConfig.getLineChartOptions(),
      });
    },

    initializeIssueChart() {
      const ctx = document.getElementById("issueStatsChart");
      if (!ctx || !ctx.getContext || !statsData.issueStats) return;

      if (!statsData.issueStats || statsData.issueStats.length === 0) {
        Utils.showNoDataMessage("issueStatsChart", i18n.noIssueData);
        return;
      }

      if (ChartInstances.issue) ChartInstances.issue.destroy();

      const limit = this.issueChartLimit || statsData.issueStats.length;
      const data =
        limit === "all"
          ? statsData.issueStats
          : statsData.issueStats.slice(0, parseInt(limit));

      ChartInstances.issue = new Chart(ctx.getContext("2d"), {
        type: "bar",
        data: {
          labels: data.map((item) => item.issueTitle),
          datasets: [
            {
              label: i18n.downloads,
              data: data.map((item) => item.downloads),
              backgroundColor: "rgba(139, 38, 53, 0.8)",
              borderColor: "rgba(139, 38, 53, 1)",
              borderWidth: 1,
            },
            {
              label: i18n.views,
              data: data.map((item) => item.views),
              backgroundColor: "rgba(54, 162, 235, 0.8)",
              borderColor: "rgba(54, 162, 235, 1)",
              borderWidth: 1,
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: "top",
            },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return `${
                    context.dataset.label
                  }: ${context.parsed.x.toLocaleString()}`;
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: { color: "rgba(0,0,0,0.05)" },
              ticks: { precision: 0 },
            },
            y: {
              grid: { display: false },
              ticks: {
                font: { size: 12 },
              },
            },
          },
        },
      });
    },

    issueChartLimit: 5,

    initializeSectionChart() {
      const ctx = document.getElementById("sectionStatsChart");
      if (!ctx || !ctx.getContext || !statsData.sectionStats) return;

      if (!statsData.sectionStats || statsData.sectionStats.length === 0) {
        Utils.showNoDataMessage("sectionStatsChart", i18n.noSectionData);
        return;
      }

      if (ChartInstances.section) ChartInstances.section.destroy();

      const colors = Utils.generateColors(statsData.sectionStats.length);

      ChartInstances.section = new Chart(ctx.getContext("2d"), {
        type: "pie",
        data: {
          labels: statsData.sectionStats.map((item) => item.sectionTitle),
          datasets: [
            {
              data: statsData.sectionStats.map((item) => item.total),
              backgroundColor: colors,
              borderColor: "#fff",
              borderWidth: 2,
            },
          ],
        },
        options: ChartConfig.getPieChartOptions(),
      });
    },

    initializeLanguageChart() {
      const ctx = document.getElementById("languageStatsChart");
      if (!ctx || !ctx.getContext) return;

      const items = Array.isArray(statsData.languageStats)
        ? statsData.languageStats
        : [];

      if (items.length === 0) {
        Utils.showNoDataMessage("languageStatsChart", i18n.noLanguageData);
        return;
      }

      if (ChartInstances.language) ChartInstances.language.destroy();

      const colors = Utils.generateColors(items.length);

      ChartInstances.language = new Chart(ctx.getContext("2d"), {
        type: "pie",
        data: {
          labels: items.map((item) => item.name),
          datasets: [
            {
              data: items.map((item) => item.count),
              backgroundColor: colors,
              borderColor: "#fff",
              borderWidth: 2,
            },
          ],
        },
        options: ChartConfig.getPieChartOptions(),
      });
    },

    initializeLanguageTrendsChart() {
      const ctx = document.getElementById("languageTrendsChart");
      if (!ctx || !ctx.getContext) return;

      const data = statsData.languageTrends;
      if (
        !data ||
        !data.labels ||
        data.labels.length === 0 ||
        !data.series ||
        data.series.length === 0
      ) {
        Utils.showNoDataMessage(
          "languageTrendsChart",
          i18n.noLanguageTrendsData,
        );
        return;
      }

      if (ChartInstances.languageTrends)
        ChartInstances.languageTrends.destroy();

      const colors = Utils.generateColors(data.series.length);

      const toBg = (color) => {
        if (color.startsWith("#") && color.length === 7) {
          const r = parseInt(color.slice(1, 3), 16);
          const g = parseInt(color.slice(3, 5), 16);
          const b = parseInt(color.slice(5, 7), 16);
          return `rgba(${r}, ${g}, ${b}, 0.1)`;
        }
        if (color.startsWith("hsl(")) {
          return color.replace("hsl(", "hsla(").replace(")", ", 0.1)");
        }
        return color;
      };

      const datasets = data.series.map((series, i) => ({
        label: series.name,
        data: series.data,
        fill: true,
        backgroundColor: toBg(colors[i]),
        borderColor: colors[i],
        borderWidth: 2,
        pointBackgroundColor: colors[i],
        pointBorderColor: "#fff",
        pointBorderWidth: 2,
        pointRadius: 4,
        tension: 0.4,
      }));

      ChartInstances.languageTrends = new Chart(ctx.getContext("2d"), {
        type: "line",
        data: { labels: data.labels, datasets },
        options: ChartConfig.getLineChartOptions(),
      });
    },

    initializeInstitutionChart() {
      const ctx = document.getElementById("institutionStatsChart");
      if (!ctx || !ctx.getContext || !statsData.authorsByInstitution) return;

      if (
        !statsData.authorsByInstitution ||
        statsData.authorsByInstitution.length === 0
      ) {
        Utils.showNoDataMessage(
          "institutionStatsChart",
          i18n.noInstitutionData,
        );
        return;
      }

      if (ChartInstances.institution) ChartInstances.institution.destroy();

      const TOP_INSTITUTIONS = 10;
      const MIN_AUTHORS = 2;

      let topInstitutions = [];
      let othersCount = 0;

      statsData.authorsByInstitution.forEach((item, index) => {
        if (index < TOP_INSTITUTIONS && item.total_count >= MIN_AUTHORS) {
          topInstitutions.push(item);
        } else {
          othersCount += item.total_count;
        }
      });

      if (othersCount > 0) {
        topInstitutions.push({
          institution: i18n.otherInstitutions || "Otras instituciones",
          total_count: othersCount,
        });
      }

      const colors = Utils.generateColors(topInstitutions.length);

      ChartInstances.institution = new Chart(ctx.getContext("2d"), {
        type: "pie",
        data: {
          labels: topInstitutions.map((item) => item.institution),
          datasets: [
            {
              data: topInstitutions.map((item) => item.total_count),
              backgroundColor: colors,
              borderColor: "#fff",
              borderWidth: 2,
            },
          ],
        },
        options: ChartConfig.getPieChartOptions(),
      });
    },

    initializeReviewerInstitutionChart() {
      const ctx = document.getElementById("reviewerInstitutionStatsChart");
      if (!ctx || !ctx.getContext || !statsData.reviewersByInstitution) return;

      if (
        !statsData.reviewersByInstitution ||
        statsData.reviewersByInstitution.length === 0
      ) {
        Utils.showNoDataMessage(
          "reviewerInstitutionStatsChart",
          i18n.noInstitutionData,
        );
        return;
      }

      if (ChartInstances.reviewerInstitution)
        ChartInstances.reviewerInstitution.destroy();

      const TOP_INSTITUTIONS = 10;
      const MIN_REVIEWERS = 2;

      let topInstitutions = [];
      let othersCount = 0;

      statsData.reviewersByInstitution.forEach((item, index) => {
        if (index < TOP_INSTITUTIONS && item.total_count >= MIN_REVIEWERS) {
          topInstitutions.push(item);
        } else {
          othersCount += item.total_count;
        }
      });

      if (othersCount > 0) {
        topInstitutions.push({
          institution: i18n.otherInstitutions || "Otras instituciones",
          total_count: othersCount,
        });
      }

      const colors = Utils.generateColors(topInstitutions.length);

      ChartInstances.reviewerInstitution = new Chart(ctx.getContext("2d"), {
        type: "pie",
        data: {
          labels: topInstitutions.map((item) => item.institution),
          datasets: [
            {
              data: topInstitutions.map((item) => item.total_count),
              backgroundColor: colors,
              borderColor: "#fff",
              borderWidth: 2,
            },
          ],
        },
        options: ChartConfig.getPieChartOptions(),
      });
    },

    resetZoom(chartId) {
      const chartMap = {
        monthlyStatsChart: ChartInstances.monthly,
        annualStatsChart: ChartInstances.annual,
        editorialStatsChart: ChartInstances.editorial,
        editorialAnnualChart: ChartInstances.editorialAnnual,
        issueStatsChart: ChartInstances.issue,
        sectionStatsChart: ChartInstances.section,
      };

      const chart = chartMap[chartId];
      if (chart && chart.resetZoom) {
        chart.resetZoom();
      }
    },
  };

  PS.Charts = Charts;
})();
