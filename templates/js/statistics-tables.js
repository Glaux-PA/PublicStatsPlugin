/**
 * Public Statistics - Table renderers.
 *
 * Registers window.PublicStats.Tables. Depends on
 * window.PublicStatsHelpers (escapeHtml, Utils) and window.PublicStats.API
 * (for lazy top-downloaded / top-viewed fetches). Template globals:
 * statsData, selectedYear, i18n.
 */
(function () {
  "use strict";

  const { escapeHtml, Utils } = window.PublicStatsHelpers;
  const PS = (window.PublicStats = window.PublicStats || {});

  const Tables = {
    renderArticlesTable(bodyId, articles, key) {
      const body = document.getElementById(bodyId);
      if (!body) return;
      body.innerHTML = "";
      let index = 1;
      articles.forEach((article) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index;
        row.insertCell(1).innerHTML = `<a href="${escapeHtml(
          article.urlPublished
        )}">${escapeHtml(article.title)}</a>`;
        row.insertCell(2).textContent = article[key];
        index++;
      });
    },

    async renderTopDownloadedArticles() {
      const body = document.getElementById("topArticlesByDownloadsTableBody");
      if (!body) return;

      try {
        await PS.API.getTopDownloaded(selectedYear);
        const localArticles = statsData.topArticlesByDownloads;

        if (!localArticles || localArticles.length === 0) {
          body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
            i18n.noDownloadsData || "No data available"
          }</td></tr>`;
          return;
        }

        body.innerHTML = "";
        localArticles.forEach((article, index) => {
          const row = body.insertRow();
          row.insertCell(0).textContent = index + 1;
          row.insertCell(1).innerHTML = `<a href="${escapeHtml(
            article.urlPublished
          )}">${escapeHtml(article.title)}</a>`;
          row.insertCell(2).textContent = article.authors || "-";
          row.insertCell(3).textContent = (
            article.downloads || 0
          ).toLocaleString();
        });
      } catch (error) {
        console.error("Error loading downloads:", error);
        body.innerHTML = `<tr><td colspan="4" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      }
    },

    async renderTopViewedArticles() {
      const body = document.getElementById("topArticlesByViewsTableBody");
      if (!body) return;

      try {
        await PS.API.getTopViewed(selectedYear);
        const localArticles = statsData.topArticlesByViews;

        if (!localArticles || localArticles.length === 0) {
          body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
            i18n.noViewsData || "No data available"
          }</td></tr>`;
          return;
        }

        body.innerHTML = "";
        localArticles.forEach((article, index) => {
          const row = body.insertRow();
          row.insertCell(0).textContent = index + 1;
          row.insertCell(1).innerHTML = `<a href="${escapeHtml(
            article.urlPublished
          )}">${escapeHtml(article.title)}</a>`;
          row.insertCell(2).textContent = article.authors || "-";
          row.insertCell(3).textContent = (article.views || 0).toLocaleString();
        });
      } catch (error) {
        console.error("Error loading views:", error);
        body.innerHTML = `<tr><td colspan="4" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      }
    },

    renderRecentTopDownloaded() {
      const body = document.getElementById("recentTopDownloadsTableBody");
      if (!body) return;

      if (
        !statsData.recentTopDownloaded ||
        statsData.recentTopDownloaded.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="3" class="ps-empty-message">${
          i18n.noRecentDownloadsData || "No data available"
        }</td></tr>`;
        return;
      }
      this.renderArticlesTable(
        "recentTopDownloadsTableBody",
        statsData.recentTopDownloaded,
        "downloads"
      );
    },

    renderRecentTopViewed() {
      const body = document.getElementById("recentTopViewsTableBody");
      if (!body) return;

      if (
        !statsData.recentTopViewed ||
        statsData.recentTopViewed.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="3" class="ps-empty-message">${
          i18n.noRecentViewsData || "No data available"
        }</td></tr>`;
        return;
      }
      this.renderArticlesTable(
        "recentTopViewsTableBody",
        statsData.recentTopViewed,
        "views"
      );
    },

    renderIssueTable() {
      const body = document.getElementById("issueStatsTableBody");
      if (!body) return;

      if (!statsData.issueStats || statsData.issueStats.length === 0) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noIssueData || "No data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";

      let index = 1;

      statsData.issueStats.forEach((issue) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index;
        row.insertCell(1).innerHTML = `<a href="${escapeHtml(
          issue.urlPublished
        )}">${escapeHtml(issue.issueTitle)}</a>`;
        row.insertCell(2).textContent = issue.downloads.toLocaleString();
        row.insertCell(3).textContent = issue.views.toLocaleString();
        row.insertCell(4).textContent = issue.total.toLocaleString();
        row.insertCell(5).textContent = issue.articleCount.toLocaleString();
        index++;
      });
    },

    renderSectionTable() {
      const body = document.getElementById("sectionStatsTableBody");
      if (!body) return;

      if (!statsData.sectionStats || statsData.sectionStats.length === 0) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noSectionData || "No data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      const colors = Utils.generateColors(statsData.sectionStats.length);
      let index = 1;

      statsData.sectionStats.forEach((section, i) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index;
        row.insertCell(
          1
        ).innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[i]}"></span>`;
        row.insertCell(2).textContent = section.sectionTitle;
        row.insertCell(3).textContent = section.downloads.toLocaleString();
        row.insertCell(4).textContent = section.views.toLocaleString();
        row.insertCell(5).textContent = section.total.toLocaleString();
        row.insertCell(6).textContent = section.articleCount.toLocaleString();
        index++;
      });
    },

    renderLanguageTable() {
      const body = document.getElementById("languageStatsTableBody");
      if (!body) return;

      const items = Array.isArray(statsData.languageStats)
        ? statsData.languageStats
        : [];

      if (items.length === 0) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noLanguageData || "No data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      const colors = Utils.generateColors(items.length);

      items.forEach((item, i) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = i + 1;
        row.insertCell(
          1
        ).innerHTML = `<span class="table-color-indicator" style="background-color: ${colors[i]}"></span>`;
        row.insertCell(2).textContent = item.name;
        row.insertCell(3).textContent = item.count.toLocaleString();
      });
    },

    renderLanguageTrendsSummary() {
      const div = document.getElementById("languageTrendsSummary");
      if (!div) return;

      const data = statsData.languageTrends;
      if (!data || !data.labels || data.labels.length === 0) {
        div.innerHTML = `<div class="ps-grid-span-2 ps-empty-message"><p>${
          i18n.noLanguageTrendsData || "No data available"
        }</p></div>`;
        return;
      }

      const totalArticles = data.series.reduce(
        (sum, s) => sum + s.data.reduce((a, b) => a + b, 0),
        0
      );
      const languageCount = data.series.length;
      const leadingLanguage = data.series[0]?.name || "—";
      const years = data.labels;
      const dataSpan =
        years.length > 1
          ? `${years[0]}–${years[years.length - 1]}`
          : years[0] || "—";

      div.innerHTML = `
        <div class="ps-stat-box">
          <div class="ps-stat-value ps-color-primary">${totalArticles.toLocaleString()}</div>
          <div class="ps-stat-label">${i18n.totalArticles}</div>
        </div>
        <div class="ps-stat-box">
          <div class="ps-stat-value ps-color-blue">${languageCount}</div>
          <div class="ps-stat-label">${i18n.languagesIdentified}</div>
        </div>
        <div class="ps-stat-box">
          <div class="ps-stat-value ps-color-primary" style="font-size:22px">${escapeHtml(leadingLanguage)}</div>
          <div class="ps-stat-label">${i18n.leadingLanguage}</div>
        </div>
        <div class="ps-stat-box">
          <div class="ps-stat-value ps-color-muted" style="font-size:22px">${escapeHtml(dataSpan)}</div>
          <div class="ps-stat-label">${i18n.dataPeriod}</div>
        </div>
      `;
    },

    renderEditorialSummary() {
      const div = document.getElementById("editorialSummary");
      if (!div) return;

      if (!statsData.editorialStats || statsData.editorialStats.length === 0) {
        div.innerHTML = `
                    <div class="ps-grid-span-2 ps-empty-message">
                        <p>${
                          i18n.noEditorialData || "No editorial data available"
                        }</p>
                    </div>
                `;
        return;
      }

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
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-blue">${totals.received}</div>
                    <div class="ps-stat-label">${i18n.totalReceived}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-green">${totals.published}</div>
                    <div class="ps-stat-label">${i18n.published}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-error">${totals.declined}</div>
                    <div class="ps-stat-label">${i18n.declined}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-warning">${totals.inProcess}</div>
                    <div class="ps-stat-label">${i18n.inProcess}</div>
                </div>
            `;
    },

    renderEditorialAnnualSummary() {
      const div = document.getElementById("editorialAnnualSummary");
      if (!div) return;

      if (
        !statsData.editorialStatsAnnual ||
        statsData.editorialStatsAnnual.length === 0
      ) {
        div.innerHTML = `
                    <div class="ps-grid-span-2 ps-empty-message">
                        <p>${
                          i18n.noEditorialAnnualData ||
                          "No annual editorial data available"
                        }</p>
                    </div>
                `;
        return;
      }

      const totals = statsData.editorialStatsAnnual.reduce(
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
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-blue">${totals.received}</div>
                    <div class="ps-stat-label">${i18n.totalReceived}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-green">${totals.published}</div>
                    <div class="ps-stat-label">${i18n.published}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-error">${totals.declined}</div>
                    <div class="ps-stat-label">${i18n.declined}</div>
                </div>
                <div class="ps-stat-box">
                    <div class="ps-stat-value ps-color-warning">${totals.inProcess}</div>
                    <div class="ps-stat-label">${i18n.inProcess}</div>
                </div>
            `;
    },

    renderAuthorsByCountryTable() {
      const body = document.getElementById("authorsByCountryTableBody");
      if (!body) return;

      if (
        !statsData.authorsByCountry ||
        statsData.authorsByCountry.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="3" class="ps-empty-message">${
          i18n.noAuthorsData || "No authors data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      statsData.authorsByCountry.forEach((country, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = country.country_name;
        row.insertCell(2).textContent = country.total_count.toLocaleString();
      });
    },

    renderInstitutionTable() {
      const body = document.getElementById("institutionStatsTableBody");
      if (!body) return;

      if (
        !statsData.authorsByInstitution ||
        statsData.authorsByInstitution.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noInstitutionData || "No data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";

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

      statsData.authorsByInstitution.forEach((item, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;

        let colorIndex = topInstitutions.findIndex(
          (t) => t.institution === item.institution
        );
        let color = colorIndex !== -1 ? colors[colorIndex] : "#cccccc";

        row.insertCell(
          1
        ).innerHTML = `<span class="table-color-indicator" style="background-color: ${color}"></span>`;
        row.insertCell(2).textContent = item.institution;
        row.insertCell(3).textContent = item.total_count.toLocaleString();
      });
    },

    renderReviewersByCountryTable() {
      const body = document.getElementById("reviewersByCountryTableBody");
      if (!body) return;

      if (
        !statsData.reviewersByCountry ||
        statsData.reviewersByCountry.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="3" class="ps-empty-message">${
          i18n.noReviewersData || "No reviewers data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      statsData.reviewersByCountry.forEach((country, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = country.country_name;
        row.insertCell(2).textContent = country.total_count.toLocaleString();
      });
    },

    renderReviewerInstitutionTable() {
      const body = document.getElementById("reviewerInstitutionStatsTableBody");
      if (!body) return;

      if (
        !statsData.reviewersByInstitution ||
        statsData.reviewersByInstitution.length === 0
      ) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noInstitutionData || "No data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";

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

      statsData.reviewersByInstitution.forEach((item, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;

        let colorIndex = topInstitutions.findIndex(
          (t) => t.institution === item.institution
        );
        let color = colorIndex !== -1 ? colors[colorIndex] : "#cccccc";

        row.insertCell(
          1
        ).innerHTML = `<span class="table-color-indicator" style="background-color: ${color}"></span>`;
        row.insertCell(2).textContent = item.institution;
        row.insertCell(3).textContent = item.total_count.toLocaleString();
      });
    },

    renderFirstDecisionSummary() {
      const data = statsData.firstDecisionStats;

      const reviewedDiv = document.getElementById(
        "firstDecisionAverageReviewed"
      );
      const allDiv = document.getElementById("firstDecisionAverageAll");

      if (!data || !data.decisions || data.decisions.length === 0) {
        if (reviewedDiv) {
          reviewedDiv.innerHTML = `
                        <div class="ps-no-data-container">
                            <div class="ps-no-data-icon">-</div>
                            <div class="ps-no-data-text">${
                              i18n.noDecisionData ||
                              "No decision data available"
                            }</div>
                        </div>
                    `;
        }
        if (allDiv) {
          allDiv.innerHTML = `
                        <div class="ps-no-data-container">
                            <div class="ps-no-data-icon">-</div>
                            <div class="ps-no-data-text">${
                              i18n.noDecisionData ||
                              "No decision data available"
                            }</div>
                        </div>
                    `;
        }
        return;
      }

      if (reviewedDiv) {
        reviewedDiv.innerHTML = `
                    <div class="ps-stat-value-large ps-color-blue">
                        ${data.average_days_reviewed.toFixed(1)}
                    </div>
                    <div class="ps-stat-label-sm">
                        ${i18n.daysAverage || "days average"} (${
          data.count_reviewed
        } ${i18n.submissionsReviewed || "reviewed submissions"})
                    </div>
                `;
      }

      if (allDiv) {
        allDiv.innerHTML = `
                    <div class="ps-stat-value-large ps-color-green">
                        ${data.average_days_all.toFixed(1)}
                    </div>
                    <div class="ps-stat-label-sm">
                        ${i18n.daysAverage || "days average"} (${
          data.count_all
        } ${i18n.totalSubmissions || "total submissions"})
                    </div>
                `;
      }
    },

    renderFirstDecisionTable() {
      const body = document.getElementById("firstDecisionTableBody");
      if (!body) return;

      const data = statsData.firstDecisionStats;
      if (!data || !data.decisions || data.decisions.length === 0) {
        body.innerHTML = `<tr><td colspan="4" class="ps-empty-message">${
          i18n.noDecisionData || "No decision data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      data.decisions.slice(0, 30).forEach((decision, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = decision.submission_id;
        row.insertCell(2).textContent = decision.date_submitted;
        row.insertCell(3).textContent =
          decision.recommendation || decision.decision_type || "-";
        row.insertCell(4).textContent = decision.date_decided;
        row.insertCell(5).textContent = `${decision.days_to_decision} ${
          i18n.days || "days"
        }`;
      });
    },

    renderAcceptancePublicationSummary() {
      const data = statsData.acceptancePublicationStats;

      const reviewedDiv = document.getElementById("publicationAverageReviewed");
      const allDiv = document.getElementById("publicationAverageAll");

      if (!data || !data.publications || data.publications.length === 0) {
        if (reviewedDiv) {
          reviewedDiv.innerHTML = `
                        <div class="ps-no-data-container">
                            <div class="ps-no-data-icon">-</div>
                            <div class="ps-no-data-text">${
                              i18n.noPublicationData ||
                              "No publication data available"
                            }</div>
                        </div>
                    `;
        }
        if (allDiv) {
          allDiv.innerHTML = `
                        <div class="ps-no-data-container">
                            <div class="ps-no-data-icon">-</div>
                            <div class="ps-no-data-text">${
                              i18n.noPublicationData ||
                              "No publication data available"
                            }</div>
                        </div>
                    `;
        }
        return;
      }

      if (reviewedDiv) {
        reviewedDiv.innerHTML = `
                    <div class="ps-stat-value-large ps-color-purple">
                        ${data.average_days_reviewed.toFixed(1)}
                    </div>
                    <div class="ps-stat-label-sm">
                        ${i18n.daysAverage || "days average"} (${
          data.count_reviewed
        } ${i18n.publicationsReviewed || "reviewed publications"})
                    </div>
                `;
      }

      if (allDiv) {
        allDiv.innerHTML = `
                    <div class="ps-stat-value-large ps-color-error">
                        ${data.average_days_all.toFixed(1)}
                    </div>
                    <div class="ps-stat-label-sm">
                        ${i18n.daysAverage || "days average"} (${
          data.count_all
        } ${i18n.totalPublications || "total publications"})
                    </div>
                `;
      }
    },

    renderAcceptancePublicationTable() {
      const body = document.getElementById("acceptancePublicationTableBody");
      if (!body) return;

      const data = statsData.acceptancePublicationStats;
      if (!data || !data.publications || data.publications.length === 0) {
        body.innerHTML = `<tr><td colspan="5" class="ps-empty-message">${
          i18n.noPublicationData || "No publication data available"
        }</td></tr>`;
        return;
      }

      body.innerHTML = "";
      data.publications.slice(0, 30).forEach((pub, index) => {
        const row = body.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = pub.submission_id;
        row.insertCell(2).textContent = pub.date_submitted;
        row.insertCell(3).textContent = pub.date_published;
        row.insertCell(4).textContent = `${Number(pub.days_to_publication)} ${
          i18n.days || "days"
        }`;
      });
    },
  };

  PS.Tables = Tables;
})();
