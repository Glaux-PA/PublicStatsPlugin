/**
 * Public Statistics - Author and impact modules.
 *
 * Registers window.PublicStats.AuthorStats (per-author dashboard) and
 * window.PublicStats.Impact (citations, OA, thematic profile, citing journals).
 *
 * Depends on window.PublicStatsHelpers (escapeHtml, Utils) and
 * window.PublicStats.API. Template globals: statsData, selectedYear, i18n.
 */
(function () {
  "use strict";

  const { escapeHtml, Utils } = window.PublicStatsHelpers;
  const PS = (window.PublicStats = window.PublicStats || {});

  const AuthorStats = {
    chartInstances: {
      temporal: null,
      sections: null,
    },

    async renderAll() {
      const data = statsData.authorStats;

      if (!data || !data.author) {
        this.showNoData();
        return;
      }

      this.renderAuthorInfo(data.author);
      this.renderSummary(data.summary);
      this.renderTemporalChart(data.temporal);
      this.renderSectionsChart(data.sections);
      this.renderArticlesTable(data.articles);
      this.renderCoAuthorsTable(data.coAuthors);
      document.getElementById("authorInfoCard").style.display = "block";
      document.getElementById("authorSummarySection").style.display = "block";
      document.getElementById("authorArticlesSection").style.display = "block";
      document.getElementById("authorCoAuthorsSection").style.display = "block";
    },

    showNoData() {
      document.getElementById("authorInfoCard").style.display = "none";
      document.getElementById("authorSummarySection").style.display = "none";
      document.getElementById("authorArticlesSection").style.display = "none";
      document.getElementById("authorCoAuthorsSection").style.display = "none";
    },

    renderAuthorInfo(author) {
      const container = document.getElementById("authorInfoContent");
      if (!container) return;

      let html = `
                <div class="ps-author-cards-grid">
                    <div><strong>${i18n.name || "Name"}:</strong> ${
        author.fullName
      }</div>
            `;

      if (author.affiliation) {
        html += `<div><strong>${i18n.affiliation || "Affiliation"}:</strong> ${
          author.affiliation
        }</div>`;
      }

      if (author.country) {
        html += `<div><strong>${i18n.country || "Country"}:</strong> ${
          author.country
        }</div>`;
      }

      if (author.orcid) {
        html += `<div><strong>ORCID:</strong> <a href="${
          author.orcid.indexOf("https://orcid.org/") != -1
            ? author.orcid
            : "https://orcid.org/" + author.orcid
        }" target="_blank">${author.orcid}</a></div>`;
      }

      html += "</div>";
      container.innerHTML = html;
    },

    renderSummary(summary) {
      document.getElementById("authorTotalArticles").textContent =
        summary.totalArticles || 0;
      document.getElementById("authorTotalDownloads").textContent = (
        summary.totalDownloads || 0
      ).toLocaleString();
      document.getElementById("authorTotalViews").textContent = (
        summary.totalViews || 0
      ).toLocaleString();
      document.getElementById("authorAvgDownloads").textContent = (
        summary.avgDownloadsPerArticle || 0
      ).toFixed(1);
      document.getElementById("authorAvgViews").textContent = (
        summary.avgViewsPerArticle || 0
      ).toFixed(1);
      document.getElementById("authorTotalAccesses").textContent = (
        summary.totalAccesses || 0
      ).toLocaleString();
    },

    renderTemporalChart(temporal) {
      const ctx = document.getElementById("authorTemporalChart");
      if (!ctx || !temporal || temporal.length === 0) return;

      if (this.chartInstances.temporal) {
        this.chartInstances.temporal.destroy();
      }

      this.chartInstances.temporal = new Chart(ctx.getContext("2d"), {
        type: "bar",
        data: {
          labels: temporal.map((t) => t.year),
          datasets: [
            {
              label: i18n.publications || "Publications",
              data: temporal.map((t) => t.count),
              backgroundColor: "#8b2635",
              borderColor: "#6b1e2a",
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false },
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 },
            },
          },
        },
      });
    },

    renderSectionsChart(sections) {
      const ctx = document.getElementById("authorSectionsChart");
      if (!ctx || !sections || sections.length === 0) return;

      if (this.chartInstances.sections) {
        this.chartInstances.sections.destroy();
      }

      const colors = Utils.generateColors(sections.length);

      this.chartInstances.sections = new Chart(ctx.getContext("2d"), {
        type: "pie",
        data: {
          labels: sections.map((s) => s.sectionTitle),
          datasets: [
            {
              data: sections.map((s) => s.count),
              backgroundColor: colors,
              borderWidth: 2,
              borderColor: "#fff",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: true,
              position: "bottom",
            },
          },
        },
      });
    },

    renderArticlesTable(articles) {
      const tbody = document.getElementById("authorArticlesTableBody");
      if (!tbody) return;

      if (!articles || articles.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="ps-empty-message">
                    ${i18n.noArticles || "No articles to display"}
                </td></tr>`;
        return;
      }

      tbody.innerHTML = "";
      articles.forEach((article, index) => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = index + 1;

        const titleCell = row.insertCell(1);
        titleCell.innerHTML = `<a href="${escapeHtml(
          article.urlPublished
        )}">${escapeHtml(article.title)}</a>`;

        row.insertCell(2).textContent = article.datePublished || "-";
        row.insertCell(3).textContent = article.section || "-";
        row.insertCell(4).textContent = article.downloads.toLocaleString();
        row.insertCell(5).textContent = article.views.toLocaleString();
        row.insertCell(6).textContent = article.total.toLocaleString();
      });
    },

    renderCoAuthorsTable(coAuthors) {
      const tbody = document.getElementById("authorCoAuthorsTableBody");
      if (!tbody) return;

      if (!coAuthors || coAuthors.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="ps-empty-message">
                    ${i18n.noCoAuthors || "No co-authors to display"}
                </td></tr>`;
        return;
      }

      tbody.innerHTML = "";
      coAuthors.forEach((coAuthor, index) => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = coAuthor.name;
        row.insertCell(2).textContent = coAuthor.affiliation || "-";
        row.insertCell(3).textContent = coAuthor.collaborations;
      });
    },
  };

  const Impact = {
    chartInstance: null,

    async renderTopCited() {
      const body = document.getElementById("topCitedArticlesTableBody");
      if (!body) return;

      Utils.showLoadingIndicator();
      try {
        const response = await PS.API.getTopCited(20);

        const articles = response || [];

        if (!articles || articles.length === 0) {
          body.innerHTML = `<tr><td colspan="5" class="ps-empty-message">${i18n.noCitationData}</td></tr>`;
          return;
        }

        body.innerHTML = "";
        articles.forEach((article, index) => {
          const row = body.insertRow();
          row.insertCell(0).textContent = index + 1;
          row.insertCell(1).innerHTML = `<a href="${escapeHtml(
            article.urlPublished
          )}">${escapeHtml(article.title)}</a>`;
          row.insertCell(2).textContent = article.authors || "-";
          row.insertCell(3).textContent = article.year || "-";

          const citCell = row.insertCell(4);
          citCell.textContent = (article.citations || 0).toLocaleString();
          citCell.className =
            "ps-cell-bold-right " +
            (article.citations > 50
              ? "ps-color-success"
              : article.citations > 20
              ? "ps-color-warning"
              : "ps-color-muted");
        });
      } catch (error) {
        console.error("Error loading cited articles:", error);
        body.innerHTML = `<tr><td colspan="5" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    async renderCitationEvolution() {
      const canvas = document.getElementById("citationEvolutionChart");
      if (!canvas) return;

      Utils.showLoadingIndicator();
      try {
        const response = await PS.API.getCitationEvolution();

        const data = response || [];

        if (!data || data.length === 0) {
          canvas.parentElement.innerHTML = `<p class="ps-empty-message">${i18n.noCitationData}</p>`;
          return;
        }

        if (this.chartInstance) {
          this.chartInstance.destroy();
        }

        this.chartInstance = new Chart(canvas.getContext("2d"), {
          type: "line",
          data: {
            labels: data.map((d) => d.year),
            datasets: [
              {
                label: i18n.citationsReceived || "Citations received",
                data: data.map((d) => d.citations),
                borderColor: "#8b2635",
                backgroundColor: "rgba(139, 38, 53, 0.1)",
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: "#8b2635",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              zoom: {
                zoom: {
                  wheel: { enabled: true },
                  pinch: { enabled: true },
                  mode: "x",
                },
                pan: {
                  enabled: true,
                  mode: "x",
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: { precision: 0 },
              },
              x: {
                ticks: { maxRotation: 45, minRotation: 45 },
              },
            },
          },
        });

        await this.renderCitedArticlesTable();
      } catch (error) {
        console.error("Error loading citation evolution:", error);
        canvas.parentElement.innerHTML = `<p class="ps-error-message">${i18n.errorLoading}</p>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    async renderCitedArticlesTable() {
      const tbody = document.getElementById("citedArticlesTableBody");
      if (!tbody) return;

      try {
        const year = selectedYear || null;
        const articles = await PS.API.getTopCited(20, year);

        const table = tbody.closest("table");
        if (table) {
          const citationsHeader = table.querySelector(
            "thead tr th:last-child"
          );
          if (citationsHeader) {
            citationsHeader.textContent = year
              ? (i18n.citationsReceivedInYear || "Citations received in ") +
                year
              : i18n.externalCitations || "External Citations";
          }
        }

        if (!articles || articles.length === 0) {
          tbody.innerHTML = `<tr><td colspan="5" class="ps-empty-message">${i18n.noCitationData}</td></tr>`;
          return;
        }

        tbody.innerHTML = "";
        articles.forEach((article, index) => {
          const row = tbody.insertRow();
          row.insertCell(0).textContent = index + 1;
          row.insertCell(1).innerHTML = `<a href="${escapeHtml(
            article.urlPublished
          )}">${escapeHtml(article.title)}</a>`;
          row.insertCell(2).textContent = article.authors || "-";
          row.insertCell(3).textContent = article.year || "-";

          const citCell = row.insertCell(4);
          citCell.textContent = (article.citations || 0).toLocaleString();
          citCell.className =
            "ps-cell-bold-right " +
            (article.citations > 50
              ? "ps-color-success"
              : article.citations > 20
              ? "ps-color-warning"
              : "ps-color-muted");
        });
      } catch (error) {
        console.error("Error loading cited articles table:", error);
        tbody.innerHTML = `<tr><td colspan="5" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      }
    },

    oaChartInstance: null,
    async renderOpenAccessStats() {
      Utils.showLoadingIndicator();
      try {
        const response = await PS.API.getOpenAccessStats();
        const data = response;

        if (!data || !data.total) {
          document.getElementById(
            "oaSummaryCards"
          ).innerHTML = `<p class="ps-empty-message">${i18n.noOaData}</p>`;
          return;
        }

        this.renderOaSummaryCards(data);
        this.renderOaDistributionChart(data);
      } catch (error) {
        console.error("Error loading OA stats:", error);
        document.getElementById(
          "oaSummaryCards"
        ).innerHTML = `<p class="ps-error-message">${i18n.errorLoading}</p>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    renderOaSummaryCards(data) {
      const container = document.getElementById("oaSummaryCards");
      if (!container) return;

      const oaPercentage =
        data.total > 0 ? ((data.open_access / data.total) * 100).toFixed(1) : 0;

      container.innerHTML = `
            <div class="metric-card">
                <div class="metric-value ps-color-success">${oaPercentage}%</div>
                <div class="metric-label">${i18n.oaPercentage}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value ps-color-primary">${data.open_access.toLocaleString()}</div>
                <div class="metric-label">${i18n.openAccess}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value ps-color-muted">${data.total.toLocaleString()}</div>
                <div class="metric-label">${i18n.articlesAnalyzed}</div>
            </div>
        `;
    },

    renderOaDistributionChart(data) {
      const canvas = document.getElementById("oaDistributionChart");
      if (!canvas) return;

      if (this.oaChartInstance) {
        this.oaChartInstance.destroy();
      }

      const labels = [];
      const values = [];
      const colors = [];

      const typeMap = {
        diamond: { label: i18n.oaDiamond, color: "#00BFC4" },
        gold: { label: i18n.oaGold, color: "#f39c12" },
        hybrid: { label: i18n.oaHybrid, color: "#FF9800" },
        green: { label: i18n.oaGreen, color: "#27ae60" },
        bronze: { label: i18n.oaBronze, color: "#CD7F32" },
        closed: { label: i18n.oaClosed, color: "#95a5a6" },
        unknown: { label: i18n.oaUnknown, color: "#bdc3c7" },
      };

      Object.keys(data.by_type || {}).forEach((type) => {
        const count = data.by_type[type];
        if (count > 0) {
          const config = typeMap[type] || { label: type, color: "#666" };
          labels.push(config.label);
          values.push(count);
          colors.push(config.color);
        }
      });

      this.oaChartInstance = new Chart(canvas.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: colors,
              borderWidth: 2,
              borderColor: "#fff",
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: "bottom",
              labels: {
                padding: 15,
                font: { size: 12 },
              },
            },
          },
        },
      });
    },

    thematicChartInstance: null,

    async renderThematicProfile() {
      Utils.showLoadingIndicator();
      try {
        const response = await PS.API.getThematicProfile();
        const data = response;

        if (!data || !data.topics || data.topics.length === 0) {
          document.getElementById(
            "thematicTableBody"
          ).innerHTML = `<tr><td colspan="3" class="ps-empty-message">${i18n.noThematicData}</td></tr>`;
          return;
        }

        this.renderThematicChart(data.topics);
        this.renderThematicTable(data.topics);
      } catch (error) {
        console.error("Error loading thematic profile:", error);
        document.getElementById(
          "thematicTableBody"
        ).innerHTML = `<tr><td colspan="3" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },
    thematicChartLimit: 5,
    renderThematicChart(topics) {
      const canvas = document.getElementById("thematicChart");
      if (!canvas) return;

      if (this.thematicChartInstance) {
        this.thematicChartInstance.destroy();
      }
      const limit = this.thematicChartLimit || topics.length;

      const topTopics = topics.slice(0, limit);
      const colors = Utils.generateColors(topTopics.length);

      this.thematicChartInstance = new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
          labels: topTopics.map((t) =>
            t.name.length > 40 ? t.name.substring(0, 40) + "..." : t.name
          ),
          datasets: [
            {
              label: i18n.articlesInArea || "Articles",
              data: topTopics.map((t) => t.count),
              backgroundColor: colors,
              borderColor: colors.map((c) => c.replace("0.7", "1")),
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: "y",
          plugins: {
            legend: { display: false },
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { precision: 0 },
            },
            y: {
              ticks: {
                autoSkip: false,
                font: { size: 12 },
              },
            },
          },
        },
      });
    },

    renderThematicTable(topics) {
      const tbody = document.getElementById("thematicTableBody");
      if (!tbody) return;

      const colors = Utils.generateColors(topics.length);
      tbody.innerHTML = "";
      topics.forEach((topic, index) => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = index + 1;

        const colorCell = row.insertCell(1);
        colorCell.innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[index]}"></span>`;

        row.insertCell(2).textContent = topic.name;

        const countCell = row.insertCell(3);
        countCell.textContent = topic.count.toLocaleString();
        countCell.className = "ps-cell-bold-right";
      });
    },

    citingJournalsChartInstance: null,
    citingJournalsOriginalData: null,
    citingJournalsAvailableYears: [],
    citingJournalsSelectedYear: "all",

    async renderCitingJournals() {
      Utils.showLoadingIndicator();
      try {
        const response = await PS.API.getCitingJournals();

        if (
          !response ||
          !response.journals ||
          response.journals.length === 0
        ) {
          document.getElementById(
            "citingJournalsTableBody"
          ).innerHTML = `<tr><td colspan="6" class="ps-empty-message">${i18n.noCitingJournalsData}</td></tr>`;
          return;
        }

        this.citingJournalsOriginalData = response.journals;
        this.citingJournalsAvailableYears = response.available_years || [];

        this.populateCitingJournalsYearFilter();

        const filteredData = this.filterCitingJournalsByCitationYear(
          this.citingJournalsSelectedYear
        );
        this.renderCitingJournalsChart(filteredData);
        this.renderCitingJournalsTable(filteredData);
      } catch (error) {
        console.error("Error loading citing journals:", error);
        document.getElementById(
          "citingJournalsTableBody"
        ).innerHTML = `<tr><td colspan="5" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    populateCitingJournalsYearFilter() {
      const select = document.getElementById("citingJournalsYearFilter");
      if (!select) return;

      select.innerHTML = `<option value="all">${
        i18n.allTime || "Todo el tiempo"
      }</option>`;

      this.citingJournalsAvailableYears.forEach((year) => {
        const option = document.createElement("option");
        option.value = year;
        option.textContent = year;
        if (year == this.citingJournalsSelectedYear) {
          option.selected = true;
        }
        select.appendChild(option);
      });
    },

    filterCitingJournalsByCitationYear(year) {
      if (!this.citingJournalsOriginalData) return [];

      if (year === "all") {
        return this.citingJournalsOriginalData;
      }

      return this.citingJournalsOriginalData
        .map((journal) => {
          const filteredArticles = (journal.cited_articles || []).filter(
            (article) => article.citation_year == year
          );

          const filteredCitations = journal.citations_by_year?.[year] || 0;

          return {
            ...journal,
            citations: filteredCitations,
            cited_articles: filteredArticles,
          };
        })
        .filter((journal) => journal.citations > 0)
        .sort((a, b) => b.citations - a.citations);
    },

    journalsChartLimit: 5,

    renderCitingJournalsChart(data) {
      const canvas = document.getElementById("citingJournalsChart");
      if (!canvas) return;

      if (this.citingJournalsChartInstance) {
        this.citingJournalsChartInstance.destroy();
      }
      const TOP_JOURNALS = this.journalsChartLimit || data.length;
      const topJournals = data.slice(0, TOP_JOURNALS);
      const colors = Utils.generateColors(topJournals.length);

      this.citingJournalsChartInstance = new Chart(canvas.getContext("2d"), {
        type: "bar",
        data: {
          labels: topJournals.map((journal) =>
            journal.name.length > 50
              ? journal.name.substring(0, 50) + "..."
              : journal.name
          ),
          datasets: [
            {
              label: i18n.citationsFromJournal || "Citations",
              data: topJournals.map((journal) => journal.citations),
              backgroundColor: colors,
              borderColor: colors.map((c) => c.replace("0.7", "1")),
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: "y",
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return `${context.parsed.x} ${i18n.citations || "citations"}`;
                },
              },
            },
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { precision: 0 },
            },
            y: {
              ticks: {
                autoSkip: false,
                font: { size: 12 },
              },
            },
          },
        },
      });
    },

    renderCitingJournalsTable(data) {
      const tbody = document.getElementById("citingJournalsTableBody");
      if (!tbody) return;

      const colors = Utils.generateColors(data.length);
      tbody.innerHTML = "";

      data.forEach((journal, index) => {
        const row = tbody.insertRow();
        row.className = "journal-row";
        row.onclick = () => window.toggleJournalArticles(index);

        const expandCell = row.insertCell(0);
        expandCell.className = "ps-col-expand";
        expandCell.innerHTML = `<span class="expand-icon" id="expand-icon-${index}"><i class="fa-solid fa-chevron-right"></i></span>`;

        const numCell = row.insertCell(1);
        numCell.className = "ps-col-index";
        numCell.textContent = index + 1;

        const colorCell = row.insertCell(2);
        colorCell.className = "ps-col-color";
        colorCell.innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[index]}"></span>`;

        row.insertCell(3).textContent = journal.name;

        const issnCell = row.insertCell(4);
        issnCell.className = "ps-col-issn";
        issnCell.textContent = journal.issn || "-";

        const citCell = row.insertCell(5);
        citCell.className = "ps-col-citations ps-cell-bold-right";
        citCell.textContent = journal.citations.toLocaleString();
        citCell.classList.add(
          journal.citations > 50
            ? "ps-color-success"
            : journal.citations > 20
            ? "ps-color-warning"
            : "ps-color-muted"
        );

        const detailRow = tbody.insertRow();
        detailRow.id = `journal-articles-${index}`;
        detailRow.style.display = "none";
        detailRow.className = "journal-articles-row";

        const detailCell = detailRow.insertCell(0);
        detailCell.colSpan = 6;

        const articles = journal.cited_articles || [];
        if (articles.length > 0) {
          let articlesHtml = `
            <div class="ps-articles-detail">
              <div class="ps-articles-detail-title">
                ${
                  i18n.citedArticlesFromJournal ||
                  "Artículos citados por esta revista"
                }:
              </div>
              <table class="ps-articles-table">
                <colgroup>
                  <col class="ps-col-num">
                  <col class="ps-col-title">
                  <col class="ps-col-authors">
                  <col class="ps-col-year">
                  <col class="ps-col-cited">
                </colgroup>
                <thead>
                  <tr>
                    <th class="ps-col-center">#</th>
                    <th>${i18n.articleTitle || "Título"}</th>
                    <th class="ps-col-authors">${
                      i18n.authors || "Autores"
                    }</th>
                    <th class="ps-col-center ps-col-year">${
                      i18n.year || "Año"
                    }</th>
                    <th class="ps-col-center">${
                      i18n.timesCited || "Veces citado"
                    }</th>
                  </tr>
                </thead>
                <tbody>
          `;

          articles.forEach((article, artIndex) => {
            const titleHtml = article.url
              ? `<a href="${escapeHtml(article.url)}" class="ps-article-link">${escapeHtml(article.title)}</a>`
              : escapeHtml(article.title);
            articlesHtml += `
              <tr>
                <td class="ps-cell-num">${artIndex + 1}</td>
                <td class="ps-cell-title">${titleHtml}</td>
                <td class="ps-cell-authors">${escapeHtml(
                  article.authors || "-"
                )}</td>
                <td class="ps-cell-year">${article.year || "-"}</td>
                <td class="ps-cell-cited">${article.times_cited}</td>
              </tr>
            `;
          });

          articlesHtml += `
                </tbody>
              </table>
            </div>
          `;
          detailCell.innerHTML = articlesHtml;
        } else {
          detailCell.innerHTML = `<div class="ps-articles-detail ps-color-muted">${
            i18n.noArticlesData || "No hay datos de artículos"
          }</div>`;
        }
      });
    },
  };

  PS.AuthorStats = AuthorStats;
  PS.Impact = Impact;
})();
