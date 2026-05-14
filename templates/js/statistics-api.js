/**
 * Public Statistics - API client + export helpers.
 *
 * Registers window.PublicStats.API (endpoint wrappers) and
 * window.PublicStats.Export (per-section export buttons + global menu).
 *
 * Depends on globals defined in publicStats.tpl: statsData, i18n.
 */
(function () {
  "use strict";

  const PS = (window.PublicStats = window.PublicStats || {});

  const API = {
    baseUrl:
      window.location.origin + window.location.pathname.replace("/total", ""),

    // Token bumped by callers to invalidate in-flight fetches; fetchData
    // throws STALE_REQUEST if the token changes mid-request.
    _token: 0,
    invalidateInflight() {
      this._token++;
    },

    buildUrl(endpoint, params = {}) {
      const url = new URL(`${this.baseUrl}/${endpoint}`);
      Object.keys(params).forEach((key) => {
        if (params[key] !== null && params[key] !== undefined) {
          url.searchParams.append(key, params[key]);
        }
      });
      return url.toString();
    },

    async fetchData(endpoint, params = {}) {
      const myToken = this._token;
      try {
        const url = this.buildUrl(endpoint, params);
        const response = await fetch(url);
        if (!response.ok) throw new Error("Network response was not ok");
        const data = await response.json();
        if (myToken !== this._token) {
          const stale = new Error("STALE_REQUEST");
          stale.code = "STALE_REQUEST";
          throw stale;
        }
        return data;
      } catch (error) {
        if (error.code === "STALE_REQUEST") throw error;
        console.error(`Error fetching ${endpoint}:`, error);
        throw error;
      }
    },

    async getMonthlyStats(year = null) {
      if (statsData.monthlyStats && statsData.monthlyStats._year === year) {
        return statsData.monthlyStats;
      }
      const data = await this.fetchData("monthly", { year });
      data._year = year;
      statsData.monthlyStats = data;
      return data;
    },

    async getAnnualStats() {
      if (statsData.annualStats) return statsData.annualStats;
      const data = await this.fetchData("annual");
      statsData.annualStats = data;
      return data;
    },
    async getOpenAccessStats() {
      if (statsData.openAccessStats) return statsData.openAccessStats;
      const data = await this.fetchData("openAccessStats");
      // Skip caching while computing — the next call may already have the result.
      if (!data || !data.is_computing) statsData.openAccessStats = data;
      return data;
    },

    async getTopDownloaded(year = null) {
      if (
        statsData.topArticlesByDownloads &&
        statsData.topArticlesByDownloads._year === year
      ) {
        return statsData.topArticlesByDownloads;
      }
      const data = await this.fetchData("topDownloaded", { year });
      data._year = year;
      statsData.topArticlesByDownloads = data;
      return data;
    },

    async getTopViewed(year = null) {
      if (
        statsData.topArticlesByViews &&
        statsData.topArticlesByViews._year === year
      ) {
        return statsData.topArticlesByViews;
      }
      const data = await this.fetchData("topViewed", { year });
      data._year = year;
      statsData.topArticlesByViews = data;
      return data;
    },

    async getIssueStats(year = null) {
      if (statsData.issueStats && statsData.issueStats._year === year) {
        return statsData.issueStats;
      }
      const data = await this.fetchData("issues", { year });
      data._year = year;
      statsData.issueStats = data;
      return data;
    },

    async getSectionStats(year = null) {
      if (statsData.sectionStats && statsData.sectionStats._year === year) {
        return statsData.sectionStats;
      }
      const data = await this.fetchData("sections", { year });
      data._year = year;
      statsData.sectionStats = data;
      return data;
    },

    async getCountryData() {
      if (statsData.countryData) return statsData.countryData;
      const data = await this.fetchData("countries");
      statsData.countryData = data;
      return data;
    },

    async getRecentDownloaded() {
      if (statsData.recentTopDownloaded) return statsData.recentTopDownloaded;
      const data = await this.fetchData("recentDownloaded");
      statsData.recentTopDownloaded = data;
      return data;
    },

    async getRecentViewed() {
      if (statsData.recentTopViewed) return statsData.recentTopViewed;
      const data = await this.fetchData("recentViewed");
      statsData.recentTopViewed = data;
      return data;
    },

    async getEditorialStats(year = null) {
      if (statsData.editorialStats && statsData.editorialStats._year === year) {
        return statsData.editorialStats;
      }
      const data = await this.fetchData("editorial", { year });
      data._year = year;
      statsData.editorialStats = data;
      return data;
    },

    async getEditorialAnnual() {
      if (statsData.editorialStatsAnnual) return statsData.editorialStatsAnnual;
      const data = await this.fetchData("editorialAnnual");
      statsData.editorialStatsAnnual = data;
      return data;
    },

    async getAuthorsByCountry() {
      if (statsData.authorsByCountry) return statsData.authorsByCountry;
      const data = await this.fetchData("authorsByCountry");
      statsData.authorsByCountry = data;
      return data;
    },

    async getAuthorsByInstitution() {
      if (statsData.authorsByInstitution) return statsData.authorsByInstitution;
      const data = await this.fetchData("authorsByInstitution");
      statsData.authorsByInstitution = data;
      return data;
    },

    async getReviewersByCountry() {
      if (statsData.reviewersByCountry) return statsData.reviewersByCountry;
      const data = await this.fetchData("reviewersByCountry");
      statsData.reviewersByCountry = data;
      return data;
    },

    async getReviewerList(year = null) {
      if (statsData.reviewerList !== null && statsData.reviewerList !== undefined)
        return statsData.reviewerList;
      const data = await this.fetchData("reviewerList", { year });
      statsData.reviewerList = data;
      return data;
    },

    async getReviewersByInstitution() {
      if (statsData.reviewersByInstitution)
        return statsData.reviewersByInstitution;
      const data = await this.fetchData("reviewersByInstitution");
      statsData.reviewersByInstitution = data;
      return data;
    },

    async getFirstDecisionStats(year = null) {
      if (
        statsData.firstDecisionStats &&
        statsData.firstDecisionStats._year === year
      ) {
        return statsData.firstDecisionStats;
      }
      const data = await this.fetchData("firstDecision", { year });
      data._year = year;
      statsData.firstDecisionStats = data;
      return data;
    },

    async getAcceptancePublicationStats(year = null) {
      if (
        statsData.acceptancePublicationStats &&
        statsData.acceptancePublicationStats._year === year
      ) {
        return statsData.acceptancePublicationStats;
      }
      const data = await this.fetchData("acceptancePublication", { year });
      data._year = year;
      statsData.acceptancePublicationStats = data;
      return data;
    },

    async getAuthorStats(authorKey, year = null) {
      if (!authorKey) return null;

      const cacheKey = `author_${authorKey}_${year || "all"}`;
      if (
        statsData.authorStats &&
        statsData.authorStats._cacheKey === cacheKey
      ) {
        return statsData.authorStats;
      }

      const data = await this.fetchData("authorStats", {
        authorKey,
        year,
      });
      data._cacheKey = cacheKey;
      statsData.authorStats = data;
      return data;
    },

    async getAuthorsList(minPublications = 1) {
      const cacheKey = `authors_list_${minPublications}`;
      if (
        statsData.authorsList &&
        statsData.authorsList._cacheKey === cacheKey
      ) {
        return statsData.authorsList;
      }

      const data = await this.fetchData("authorsListForStats", {
        minPublications,
      });
      data._cacheKey = cacheKey;
      statsData.authorsList = data;
      return data;
    },

    async getTopCited(limit = 20, year = null) {
      const cacheKey = `topCited_${limit}_${year || "all"}`;
      if (
        statsData.topCitedArticles &&
        statsData.topCitedArticles._cacheKey === cacheKey
      ) {
        return statsData.topCitedArticles;
      }
      const params = { limit };
      if (year) params.year = year;
      const data = await this.fetchData("topCited", params);
      if (data && !data.is_computing) {
        data._cacheKey = cacheKey;
        statsData.topCitedArticles = data;
      }
      return data;
    },

    async getCitationEvolution() {
      if (statsData.citationEvolution) return statsData.citationEvolution;
      const data = await this.fetchData("citationEvolution");
      if (data && !data.is_computing) statsData.citationEvolution = data;
      return data;
    },
    async getThematicProfile() {
      if (statsData.thematicProfile) return statsData.thematicProfile;
      const data = await this.fetchData("thematicProfile");
      if (data && !data.is_computing) statsData.thematicProfile = data;
      return data;
    },
    async getCitationsByCountry() {
      if (statsData.citationsByCountry) return statsData.citationsByCountry;
      const data = await this.fetchData("citationsByCountry");
      // Skip caching while computing — the next call may already have the result.
      if (data && !data.is_computing) statsData.citationsByCountry = data;
      return data;
    },

    async getCitingJournals() {
      if (statsData.citingJournals) return statsData.citingJournals;
      const data = await this.fetchData("citingJournals");
      // Skip caching while computing — the next call may already have the result.
      if (data && !data.is_computing) statsData.citingJournals = data;
      return data;
    },

    async getLanguageTrends() {
      if (statsData.languageTrends) return statsData.languageTrends;
      const data = await this.fetchData("languageTrends");
      statsData.languageTrends = data;
      return data;
    },

    async getLanguageStats(issueId = null) {
      const cacheKey = `language_${issueId || "all"}`;
      if (
        statsData.languageStats &&
        statsData.languageStats._cacheKey === cacheKey
      ) {
        return statsData.languageStats;
      }
      const params = {};
      if (issueId) params.issueId = issueId;
      const data = await this.fetchData("languages", params);
      data._cacheKey = cacheKey;
      statsData.languageStats = data;
      return data;
    },

    clearAuthorCache() {
      statsData.authorStats = null;
    },

    clearYearDependentCache() {
      if (statsData.monthlyStats) statsData.monthlyStats = null;
      if (statsData.topArticlesByDownloads)
        statsData.topArticlesByDownloads = null;
      if (statsData.topArticlesByViews) statsData.topArticlesByViews = null;
      if (statsData.issueStats) statsData.issueStats = null;
      if (statsData.sectionStats) statsData.sectionStats = null;
      if (statsData.editorialStats) statsData.editorialStats = null;
      if (statsData.firstDecisionStats) statsData.firstDecisionStats = null;
      if (statsData.acceptancePublicationStats)
        statsData.acceptancePublicationStats = null;
      if (statsData.topCitedArticles) statsData.topCitedArticles = null;
    },


    exportCsv(endpoint, params = {}) {
      const url = this.buildUrl(endpoint, params);
      window.location.href = url;
    },

    exportMonthly(year = null) {
      this.exportCsv("exportMonthly", year ? { year } : {});
    },

    exportAnnual() {
      this.exportCsv("exportAnnual");
    },

    exportCountries() {
      this.exportCsv("exportCountries");
    },

    exportTopDownloaded(year = null, limit = 100) {
      this.exportCsv("exportTopDownloaded", { year, limit });
    },

    exportTopViewed(year = null, limit = 100) {
      this.exportCsv("exportTopViewed", { year, limit });
    },

    exportEditorial(year = null) {
      this.exportCsv("exportEditorial", year ? { year } : {});
    },

    exportEditorialAnnual() {
      this.exportCsv("exportEditorialAnnual");
    },

    exportAuthorsByCountry() {
      this.exportCsv("exportAuthorsByCountry");
    },

    exportAuthorsByInstitution() {
      this.exportCsv("exportAuthorsByInstitution");
    },

    exportTopCited(limit = 100) {
      this.exportCsv("exportTopCited", { limit });
    },

    exportIssues(year = null) {
      this.exportCsv("exportIssues", year ? { year } : {});
    },

    exportSections(year = null) {
      this.exportCsv("exportSections", year ? { year } : {});
    },

    exportLanguageTrends() {
      this.exportCsv("exportLanguageTrends");
    },

    exportLanguages() {
      const issueId = document.getElementById("languageIssueFilter")?.value || null;
      this.exportCsv("exportLanguages", issueId ? { issueId } : {});
    },

    exportReviewersByCountry() {
      this.exportCsv("exportReviewersByCountry");
    },

    exportReviewersByInstitution() {
      this.exportCsv("exportReviewersByInstitution");
    },

    exportFirstDecision(year = null) {
      this.exportCsv("exportFirstDecision", year ? { year } : {});
    },

    exportAcceptancePublication(year = null) {
      this.exportCsv("exportAcceptancePublication", year ? { year } : {});
    },

    exportRecentDownloaded(limit = 100) {
      this.exportCsv("exportRecentDownloaded", { limit });
    },

    exportRecentViewed(limit = 100) {
      this.exportCsv("exportRecentViewed", { limit });
    },

    exportCitationEvolution() {
      this.exportCsv("exportCitationEvolution");
    },

    exportOpenAccessStats() {
      this.exportCsv("exportOpenAccessStats");
    },

    exportCitingJournals() {
      const year =
        (PS.Impact && PS.Impact.citingJournalsSelectedYear) || "all";
      this.exportCsv("exportCitingJournals", { year });
    },

    exportCitationsByCountry() {
      this.exportCsv("exportCitationsByCountry");
    },

    exportThematicProfile() {
      this.exportCsv("exportThematicProfile");
    },

    exportFullReport(year = null) {
      this.exportCsv("exportFullReport", year ? { year } : {});
    },
  };

  const Export = {
    createButton(
      label,
      onClick,
      icon = '<i class="fa-solid fa-file-csv"></i>'
    ) {
      const btn = document.createElement("button");
      btn.className = "export-csv-btn";
      btn.innerHTML = `${icon} ${label}`;
      btn.onclick = onClick;
      btn.title = i18n.exportCsv || "Export to CSV";
      return btn;
    },

    addToSection(sectionId, exportType, params = {}) {
      const section = document.getElementById(sectionId);
      if (!section) return;

      const header = section.querySelector(
        ".content-title, .section-header, h2, h3"
      );
      if (!header) return;

      if (header.querySelector(".export-csv-btn")) return;
      if (
        header.parentElement &&
        header.parentElement.querySelector(".export-csv-btn")
      )
        return;

      const exportFn = API["export" + exportType];
      if (!exportFn) {
        console.warn(`Export function export${exportType} not found`);
        return;
      }

      const btn = this.createButton(i18n.exportCsv || "CSV", () =>
        exportFn.call(API, params.year, params.limit)
      );

      if (!header.style.display || header.style.display !== "flex") {
        header.style.display = "flex";
        header.style.justifyContent = "space-between";
        header.style.alignItems = "center";
        header.style.flexWrap = "wrap";
        header.style.gap = "10px";
      }
      header.appendChild(btn);
    },

    initializeExportButtons() {
      const currentYear =
        document.getElementById("yearSelector")?.value || null;

      const exportMap = {
        "monthly-trends": { type: "Monthly", params: { year: currentYear } },
        "annual-trends": { type: "Annual" },
        "geographic-distribution": { type: "Countries" },
        "general-downloads": {
          type: "TopDownloaded",
          params: { year: currentYear, limit: 100 },
        },
        "general-views": {
          type: "TopViewed",
          params: { year: currentYear, limit: 100 },
        },
        "recent-downloads": {
          type: "RecentDownloaded",
          params: { limit: 100 },
        },
        "recent-views": { type: "RecentViewed", params: { limit: 100 } },
        "editorial-submissions": {
          type: "Editorial",
          params: { year: currentYear },
        },
        "editorial-annual": { type: "EditorialAnnual" },
        "authors-by-country": { type: "AuthorsByCountry" },
        "authors-by-institution": { type: "AuthorsByInstitution" },
        "reviewers-by-country": { type: "ReviewersByCountry" },
        "reviewers-by-institution": { type: "ReviewersByInstitution" },
        "general-issues": { type: "Issues", params: { year: currentYear } },
        "general-sections": { type: "Sections", params: { year: currentYear } },
        "general-languages": { type: "Languages" },
        "language-trends": { type: "LanguageTrends" },
        "top-cited": { type: "TopCited", params: { limit: 100 } },
        "citation-evolution": { type: "CitationEvolution" },
        "open-access-stats": { type: "OpenAccessStats" },
        "thematic-profile": { type: "ThematicProfile" },
        "citations-map": { type: "CitationsByCountry" },
        "citing-journals": { type: "CitingJournals" },
        "first-decision-stats": {
          type: "FirstDecision",
          params: { year: currentYear },
        },
        "acceptance-publication-stats": {
          type: "AcceptancePublication",
          params: { year: currentYear },
        },
      };

      Object.entries(exportMap).forEach(([sectionId, config]) => {
        this.addToSection(sectionId, config.type, config.params || {});
      });
    },

    isMobile() {
      return window.innerWidth <= 768;
    },

    createOverlay() {
      const overlay = document.createElement("div");
      overlay.className = "export-menu-overlay";
      overlay.onclick = () => this.closeDropdown();
      return overlay;
    },

    closeDropdown() {
      const dropdown = document.querySelector(".export-menu-dropdown");
      const overlay = document.querySelector(".export-menu-overlay");

      if (dropdown) {
        dropdown.style.display = "none";
      }
      if (overlay) {
        overlay.remove();
      }
    },

    toggleDropdown(dropdown) {
      const isVisible = dropdown.style.display === "block";

      if (isVisible) {
        this.closeDropdown();
      } else {
        dropdown.style.display = "block";

        if (this.isMobile()) {
          const existingOverlay = document.querySelector(
            ".export-menu-overlay"
          );
          if (!existingOverlay) {
            document.body.appendChild(this.createOverlay());
          }
        }
      }
    },

    createExportMenu() {
      const menu = document.createElement("div");
      menu.className = "export-menu";
      menu.innerHTML = `
                <button class="export-menu-toggle" title="${
                  i18n.exportOptions || "Export Options"
                }">
                    📊 ${i18n.export || "Export"}
                </button>
                <div class="export-menu-dropdown" style="display: none;">
                    <button data-action="fullReport">${
                      i18n.fullReport || "📋 Full Report"
                    }</button>
                    <hr>
                    <button data-action="monthly">${
                      i18n.monthlyStats || "📅 Monthly Stats"
                    }</button>
                    <button data-action="annual">${
                      i18n.annualStats || "📆 Annual Stats"
                    }</button>
                    <button data-action="countries">${
                      i18n.countryStats || "🌍 Country Stats"
                    }</button>
                    <hr>
                    <button data-action="topDownloaded">${
                      i18n.topDownloaded || "⬇️ Top Downloaded"
                    }</button>
                    <button data-action="topViewed">${
                      i18n.topViewed || "👁️ Top Viewed"
                    }</button>
                    <button data-action="topCited">${
                      i18n.topCited || "📚 Top Cited"
                    }</button>
                    <hr>
                    <button data-action="editorialAnnual">${
                      i18n.editorialStats || "✏️ Editorial Stats"
                    }</button>
                    <button data-action="authorsByCountry">${
                      i18n.authorsByCountry || "👥 Authors by Country"
                    }</button>
                    <button data-action="reviewersByCountry">${
                      i18n.reviewersByCountry || "🔍 Reviewers by Country"
                    }</button>
                </div>
            `;

      const toggle = menu.querySelector(".export-menu-toggle");
      const dropdown = menu.querySelector(".export-menu-dropdown");

      toggle.onclick = (e) => {
        e.stopPropagation();
        this.toggleDropdown(dropdown);
      };

      const actions = {
        fullReport: () => API.exportFullReport(),
        monthly: () => API.exportMonthly(),
        annual: () => API.exportAnnual(),
        countries: () => API.exportCountries(),
        topDownloaded: () => API.exportTopDownloaded(),
        topViewed: () => API.exportTopViewed(),
        topCited: () => API.exportTopCited(),
        editorialAnnual: () => API.exportEditorialAnnual(),
        authorsByCountry: () => API.exportAuthorsByCountry(),
        reviewersByCountry: () => API.exportReviewersByCountry(),
      };

      dropdown.querySelectorAll("button[data-action]").forEach((btn) => {
        btn.onclick = (e) => {
          e.stopPropagation();
          const action = btn.getAttribute("data-action");
          if (actions[action]) {
            actions[action]();
          }
          this.closeDropdown();
        };
      });

      document.addEventListener("click", (e) => {
        if (!menu.contains(e.target)) {
          this.closeDropdown();
        }
      });

      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          this.closeDropdown();
        }
      });

      window.addEventListener("resize", () => {
        const dropdown = document.querySelector(".export-menu-dropdown");
        if (dropdown && dropdown.style.display === "block") {
          this.closeDropdown();
        }
      });

      return menu;
    },

    addGlobalExportMenu() {
      if (document.querySelector(".export-menu")) {
        return;
      }

      let container = document.getElementById("global-export-container");

      if (!container) {
        container = document.querySelector(".global-export-container");
      }

      if (!container) {
        const mainContent = document.querySelector(".main-content");
        if (mainContent) {
          container = document.createElement("div");
          container.id = "global-export-container";
          container.className = "global-export-container";
          mainContent.insertBefore(container, mainContent.firstChild);
        }
      }

      if (container) {
        container.appendChild(this.createExportMenu());
      }
    },
  };

  PS.API = API;
  PS.Export = Export;
})();
