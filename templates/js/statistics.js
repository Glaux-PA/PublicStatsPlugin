/**
 * Public Statistics Plugin - Frontend
 *
 * This file is the orchestrator of the statistics dashboard. Shared helpers
 * (escapeHtml, Utils, ChartConfig, ChartInstances, YEAR_FILTERABLE_SECTIONS)
 * live in statistics-helpers.js and are injected via destructuring below.
 *
 * Internal section map (approximate line ranges, keep up to date):
 *
 *   API          - HTTP wrappers around the /publicStats/* endpoints
 *   Export       - CSV download button wiring
 *   Navigation   - section switching, menu state, year-selector visibility
 *   DataManager  - year change + cache invalidation
 *   Charts       - Chart.js renderers (access, editorial, issues, sections)
 *   Maps         - Leaflet renderers (countries, authors, reviewers)
 *   Tables       - HTML table builders
 *   AuthorStats  - per-author dashboard
 *   Impact       - citations / OpenAlex aggregates
 *   (bottom)     - window.* exports + DOMContentLoaded init
 *
 * Globals it reads (defined in publicStats.tpl before this script loads):
 *   statsData, selectedYear, selectedAuthor, i18n
 */

(function () {
  "use strict";

  // Pull shared helpers from the separate file loaded before this one.
  const {
    escapeHtml,
    YEAR_FILTERABLE_SECTIONS,
    ChartInstances,
    Utils,
    ChartConfig,
  } = window.PublicStatsHelpers;

  // ========================================
  // API CALLS
  // ========================================
  const API = {
    baseUrl:
      window.location.origin + window.location.pathname.replace("/total", ""),

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
      try {
        const url = this.buildUrl(endpoint, params);
        const response = await fetch(url);
        if (!response.ok) throw new Error("Network response was not ok");
        return await response.json();
      } catch (error) {
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
      statsData.openAccessStats = data;
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
      const cacheKey = `topCited_${limit}_${year || 'all'}`;
      if (statsData.topCitedArticles && statsData.topCitedArticles._cacheKey === cacheKey) {
        return statsData.topCitedArticles;
      }
      const params = { limit };
      if (year) params.year = year;
      const data = await this.fetchData("topCited", params);
      data._cacheKey = cacheKey;
      statsData.topCitedArticles = data;
      return data;
    },

    async getCitationEvolution() {
      if (statsData.citationEvolution) return statsData.citationEvolution;
      const data = await this.fetchData("citationEvolution");
      statsData.citationEvolution = data;
      return data;
    },
    async getThematicProfile() {
      if (statsData.thematicProfile) return statsData.thematicProfile;
      const data = await this.fetchData("thematicProfile");
      statsData.thematicProfile = data;
      return data;
    },
    async getCitationsByCountry() {
      if (statsData.citationsByCountry) return statsData.citationsByCountry;
      const data = await this.fetchData("citationsByCountry");
      statsData.citationsByCountry = data;
      return data;
    },

    async getCitingJournals() {
      if (statsData.citingJournals) return statsData.citingJournals;
      const data = await this.fetchData("citingJournals");
      statsData.citingJournals = data;
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

    // ========================================
    // CSV Export Functions
    // ========================================

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

    exportCollaboration() {
      this.exportCsv("exportCollaboration");
    },

    exportFundingSources(limit = 100) {
      this.exportCsv("exportFundingSources", { limit });
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
      const year = Impact.citingJournalsSelectedYear || 'all';
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

  // ========================================
  // CSV EXPORT HELPERS
  // ========================================
  const Export = {
    /**
     * Create an export button element
     */
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

    /**
     * Add export button to a section header
     */
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

    /**
     * Initialize export buttons for all sections
     */
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

    /**
     * Check if we're on mobile
     */
    isMobile() {
      return window.innerWidth <= 768;
    },

    /**
     * Create overlay for mobile dropdown
     */
    createOverlay() {
      const overlay = document.createElement("div");
      overlay.className = "export-menu-overlay";
      overlay.onclick = () => this.closeDropdown();
      return overlay;
    },

    /**
     * Close the dropdown menu
     */
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

    /**
     * Toggle dropdown visibility
     */
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

    /**
     * Create the export menu
     */
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

    /**
     * Add global export menu to page
     */
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
  // ========================================
  // NAVIGATION AND SECTION MANAGEMENT
  // ========================================
  const Navigation = {
    toggleSection(sectionId) {
      const content = document.getElementById(sectionId + "-content");
      if (content) {
        const isCollapsed = content.classList.contains("section-collapsed");
        content.classList.toggle("section-collapsed");
        const section = content.closest(".sidebar-section");
        const toggle = section.querySelector(".section-toggle");
        if (toggle) {
          toggle.style.transform = isCollapsed
            ? "rotate(0deg)"
            : "rotate(-90deg)";
        }
      }
    },

    showSection(sectionId) {
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
      this.updateYearSelectorVisibility(sectionId);
      setTimeout(() => this.initializeSectionContent(sectionId), 100);
    },

    updateYearSelectorVisibility(sectionId) {
      const yearSelector = document.querySelector(".year-selector-container");
      if (!yearSelector) return;

      if (YEAR_FILTERABLE_SECTIONS.includes(sectionId)) {
        yearSelector.style.display = "block";
        yearSelector.style.opacity = "1";
      } else {
        yearSelector.style.display = "none";
      }
    },
    async loadAuthorSelector() {
      const selector = document.getElementById("authorSelector");
      if (!selector || selector.options.length > 1) return;

      try {
        const minPubs = 1;
        const authors = await API.getAuthorsList(minPubs);

        selector.innerHTML =
          '<option value="">' +
          (i18n.selectAuthorPlaceholder || "Select an author...") +
          "</option>";

        authors.forEach((author) => {
          const option = document.createElement("option");
          option.value = author.key;

          let text = author.name;
          if (author.affiliation) {
            text += ` (${author.affiliation})`;
          }
          text += ` - ${author.publicationCount} pub.`;

          option.textContent = text;
          selector.appendChild(option);
        });

        // Set selected author if exists
        if (selectedAuthor) {
          selector.value = selectedAuthor;
        }
      } catch (error) {
        console.error("Error loading authors list:", error);
      }
    },
    async initializeSectionContent(sectionId) {
      Utils.showLoadingIndicator();

      try {
        const year = selectedYear || null;

        const handlers = {
          "monthly-trends": async () => {
            await API.getMonthlyStats(year);
            Charts.initializeMonthlyChart();
          },
          "annual-trends": async () => {
            await API.getAnnualStats();
            Charts.initializeAnnualChart();
          },
          "geographic-distribution": async () => {
            await API.getCountryData();
            Maps.initializeWorldMap();
            Maps.renderGeographicTable();
          },
          "general-views": async () => {
            await Tables.renderTopViewedArticles();
          },

          "general-issues": async () => {
            await API.getIssueStats(year);
            Charts.initializeIssueChart();
            Tables.renderIssueTable();
          },
          "general-sections": async () => {
            await API.getSectionStats(year);
            Charts.initializeSectionChart();
            Tables.renderSectionTable();
          },
          "general-downloads": async () => {
            await Tables.renderTopDownloadedArticles();
          },
          "recent-downloads": async () => {
              await API.getRecentDownloaded();
              Tables.renderRecentTopDownloaded();
          },
          "recent-views": async () => {
            await API.getRecentViewed();
            Tables.renderRecentTopViewed();
          },
          "editorial-submissions": async () => {
            await API.getEditorialStats(year);
            Charts.initializeEditorialChart();
            Tables.renderEditorialSummary();
          },
          "editorial-annual": async () => {
            await API.getEditorialAnnual();
            Charts.initializeEditorialAnnualChart();
            Tables.renderEditorialAnnualSummary();
          },
          "authors-by-country": async () => {
            await API.getAuthorsByCountry();
            Maps.initializeAuthorsWorldMap();
            Tables.renderAuthorsByCountryTable();
          },
          "authors-by-institution": async () => {
            await API.getAuthorsByInstitution();
            Charts.initializeInstitutionChart();
            Tables.renderInstitutionTable();
          },
          "reviewers-by-country": async () => {
            await API.getReviewersByCountry();
            Maps.initializeReviewersWorldMap();
            Tables.renderReviewersByCountryTable();
          },
          "reviewers-by-institution": async () => {
            await API.getReviewersByInstitution();
            Charts.initializeReviewerInstitutionChart();
            Tables.renderReviewerInstitutionTable();
          },
          "first-decision-stats": async () => {
            await API.getFirstDecisionStats(year);
            Tables.renderFirstDecisionSummary();
            Tables.renderFirstDecisionTable();
          },
          "acceptance-publication-stats": async () => {
            await API.getAcceptancePublicationStats(year);
            Tables.renderAcceptancePublicationSummary();
            Tables.renderAcceptancePublicationTable();
          },
          "author-individual-stats": async () => {
            await this.loadAuthorSelector();
            if (selectedAuthor) {
              const year = selectedYear || null;
              await API.getAuthorStats(selectedAuthor, year);
              AuthorStats.renderAll();
            }
          },
          "top-cited": async () => {
            await Impact.renderTopCited();
          },
          "citation-evolution": async () => {
            await Impact.renderCitationEvolution();
          },
          "open-access-stats": async () => {
            await Impact.renderOpenAccessStats();
          },

          "thematic-profile": async () => {
            await Impact.renderThematicProfile();
          },
          "citations-map": async () => {
            await Maps.initializeCitationsMap();
          },
          "citing-journals": async () => {
            await Impact.renderCitingJournals();
          },
        };

        if (handlers[sectionId]) {
          await handlers[sectionId]();
        }

        // Add export button to this section after content is loaded
        Export.initializeExportButtons();
      } catch (error) {
        console.error("Error loading section:", error);
        alert(i18n.errorLoading || "Error loading data");
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    updateSectionTitles(year) {
      const yearText = year ? ` (${year})` : "";
      const titles = {
        "monthly-trends": `${i18n.monthlyOverview}${yearText}`,
        "general-downloads": `${i18n.mostDownloadedArticles}${yearText}`,
        "general-views": `${i18n.mostViewedArticles}${yearText}`,
        "general-issues": `${i18n.downloadsByIssue}${yearText}`,
        "general-sections": `${i18n.downloadsBySection}${yearText}`,
        "editorial-submissions": `${i18n.submissionsOverview}${yearText}`,
        "acceptance-publication-stats": `${i18n.acceptancePublicationStats}${yearText}`,
        "first-decision-stats": `${i18n.firstDecisionStats}${yearText}`,
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
      if (issueTable) {
        issueTable.textContent = `${i18n.downloadsByIssue}${yearText}`;
      }
    },
  };

  // ========================================
  // DATA MANAGEMENT
  // ========================================
  const DataManager = {
    updateStatsData(data) {
      statsData.monthlyStats = data.monthlyStats || [];
      statsData.topArticlesByDownloads = data.topDownloadedArticles || [];
      statsData.topArticlesByViews = data.topViewedArticles || [];
      statsData.annualStats = data.annualStats || [];
      statsData.issueStats = data.issueStats || [];
      statsData.sectionStats = data.sectionStats || [];
      statsData.recentTopDownloaded = data.recentTopDownloaded || [];
      statsData.recentTopViewed = data.recentTopViewed || [];
      statsData.editorialStats = data.editorialStats || [];
      statsData.editorialStatsAnnual = data.editorialStatsAnnual || [];
      statsData.authorsByCountry = data.authorsByCountry || [];
      statsData.authorsByInstitution = data.authorsByInstitution || [];
      statsData.reviewersByCountry = data.reviewersByCountry || [];
      statsData.reviewersByInstitution = data.reviewersByInstitution || [];
      statsData.firstDecisionStats = data.firstDecisionStats || {};
      statsData.acceptancePublicationStats =
        data.acceptancePublicationStats || {};
    },

    changeYear(year) {
      selectedYear = year;
      API.clearYearDependentCache();
      API.clearAuthorCache();
      Navigation.updateSectionTitles(year);

      const newUrl = new URL(window.location.href);
      if (year) {
        newUrl.searchParams.set("year", year);
      } else {
        newUrl.searchParams.delete("year");
      }
      window.history.pushState({ year: year }, "", newUrl.toString());

      const current = document.querySelector(
        '.content-section[style*="display: block"]'
      );
      if (current) {
        Navigation.initializeSectionContent(current.id);
      }
    },
  };

  // ========================================
  // CHART IMPLEMENTATIONS
  // ========================================
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
          i18n.noEditorialAnnualData
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
                (item) => item.published
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
                (item) => item.inProcess
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

    initializeInstitutionChart() {
      const ctx = document.getElementById("institutionStatsChart");
      if (!ctx || !ctx.getContext || !statsData.authorsByInstitution) return;

      if (
        !statsData.authorsByInstitution ||
        statsData.authorsByInstitution.length === 0
      ) {
        Utils.showNoDataMessage(
          "institutionStatsChart",
          i18n.noInstitutionData
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
          i18n.noInstitutionData
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

  // ========================================
  // MAP IMPLEMENTATIONS
  // ========================================
  const Maps = {
    getCountryCoordinates(code) {
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
    },

    initializeWorldMap() {
      const container = document.getElementById("worldMap");
      if (!container || typeof L === "undefined") return;

      if (!statsData.countryData || statsData.countryData.length === 0) {
        Utils.showNoDataMessage("worldMap", i18n.noGeographicData);
        return;
      }

      if (ChartInstances.worldMap) ChartInstances.worldMap.remove();

      ChartInstances.worldMap = L.map("worldMap", { center: [20, 0], zoom: 2 });

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
      }).addTo(ChartInstances.worldMap);

      const maxAccess = Math.max(
        ...statsData.countryData.map((c) => c.total_access)
      );

      statsData.countryData.forEach((country) => {
        const coords = this.getCountryCoordinates(country.country_code);
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
                        <div class="ps-map-tooltip">
                            <h3 class="ps-map-tooltip-title">${
                              country.country_name
                            }</h3>
                            <div class="ps-map-tooltip-value">${country.total_access.toLocaleString()}</div>
                            <div class="ps-map-tooltip-label">${
                              i18n.totalAccesses
                            }</div>
                        </div>
                    `);

          marker.addTo(ChartInstances.worldMap);
        }
      });
    },

    renderGeographicTable() {
      const tbody = document.getElementById("geographicTableBody");
      if (!tbody) return;

      if (!statsData.countryData || statsData.countryData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="ps-empty-message">${i18n.noGeographicData}</td></tr>`;
        return;
      }

      tbody.innerHTML = "";
      const top20 = statsData.countryData.slice(0, 20);

      top20.forEach((country, index) => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = country.country_name;

        const countCell = row.insertCell(2);
        countCell.textContent = country.total_access.toLocaleString();
        countCell.className = "ps-cell-bold-right";
      });
    },

    initializeAuthorsWorldMap() {
      const container = document.getElementById("authorsWorldMap");
      if (!container || typeof L === "undefined") return;

      if (
        !statsData.authorsByCountry ||
        statsData.authorsByCountry.length === 0
      ) {
        Utils.showNoDataMessage("authorsWorldMap", i18n.noAuthorsData);
        return;
      }

      if (ChartInstances.authorsMap) ChartInstances.authorsMap.remove();

      ChartInstances.authorsMap = L.map("authorsWorldMap", {
        center: [20, 0],
        zoom: 2,
      });

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
      }).addTo(ChartInstances.authorsMap);

      const maxAuthors = Math.max(
        ...statsData.authorsByCountry.map((c) => c.total_count)
      );

      statsData.authorsByCountry.forEach((country) => {
        const coords = this.getCountryCoordinates(country.country_code);
        if (coords) {
          const size = (country.total_count / maxAuthors) * 17 + 8;
          const color =
            country.total_count >= 100
              ? "#8b2635"
              : country.total_count >= 50
              ? "#c74251"
              : country.total_count >= 20
              ? "#e6677a"
              : country.total_count >= 10
              ? "#f096a6"
              : "#f8c5cf";

          const marker = L.circleMarker(coords, {
            radius: size,
            fillColor: color,
            color: "#fff",
            weight: 2,
            opacity: 1,
            fillOpacity: 0.7,
          });

          marker.bindPopup(`
                        <div class="ps-map-tooltip">
                            <h3 class="ps-map-tooltip-title">${
                              country.country_name
                            }</h3>
                            <div class="ps-map-tooltip-value">${country.total_count.toLocaleString()}</div>
                            <div class="ps-map-tooltip-label">${
                              i18n.totalAuthors
                            }</div>
                        </div>
                    `);

          marker.addTo(ChartInstances.authorsMap);
        }
      });
    },

    initializeReviewersWorldMap() {
      const container = document.getElementById("reviewersWorldMap");
      if (!container || typeof L === "undefined") return;

      if (
        !statsData.reviewersByCountry ||
        statsData.reviewersByCountry.length === 0
      ) {
        Utils.showNoDataMessage("reviewersWorldMap", i18n.noReviewersData);
        return;
      }

      if (ChartInstances.reviewersMap) ChartInstances.reviewersMap.remove();

      ChartInstances.reviewersMap = L.map("reviewersWorldMap", {
        center: [20, 0],
        zoom: 2,
      });

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
      }).addTo(ChartInstances.reviewersMap);

      const maxReviewers = Math.max(
        ...statsData.reviewersByCountry.map((c) => c.total_count)
      );

      statsData.reviewersByCountry.forEach((country) => {
        const coords = this.getCountryCoordinates(country.country_code);
        if (coords) {
          const size = (country.total_count / maxReviewers) * 17 + 8;
          const color =
            country.total_count >= 100
              ? "#8b2635"
              : country.total_count >= 50
              ? "#c74251"
              : country.total_count >= 20
              ? "#e6677a"
              : country.total_count >= 10
              ? "#f096a6"
              : "#f8c5cf";

          const marker = L.circleMarker(coords, {
            radius: size,
            fillColor: color,
            color: "#fff",
            weight: 2,
            opacity: 1,
            fillOpacity: 0.7,
          });

          marker.bindPopup(`
                        <div class="ps-map-tooltip">
                            <h3 class="ps-map-tooltip-title">${
                              country.country_name
                            }</h3>
                            <div class="ps-map-tooltip-value">${country.total_count.toLocaleString()}</div>
                            <div class="ps-map-tooltip-label">${
                              i18n.totalReviewers
                            }</div>
                        </div>
                    `);

          marker.addTo(ChartInstances.reviewersMap);
        }
      });
    },
    citationsMapInstance: null,

    async initializeCitationsMap() {
      Utils.showLoadingIndicator();
      try {
        const data = await API.getCitationsByCountry();

        if (!data || data.length === 0) {
          document.getElementById(
            "citationsMapTableBody"
          ).innerHTML = `<tr><td colspan="3" class="ps-empty-message">${i18n.noCitationMapData}</td></tr>`;
          return;
        }

        this.renderCitationsMap(data);
        this.renderCitationsTable(data);
      } catch (error) {
        console.error("Error loading citations map:", error);
        document.getElementById(
          "citationsMapTableBody"
        ).innerHTML = `<tr><td colspan="3" class="ps-error-message">${i18n.errorLoading}</td></tr>`;
      } finally {
        Utils.hideLoadingIndicator();
      }
    },

    renderCitationsMap(data) {
      const container = document.getElementById("citationsMapContainer");
      if (!container) return;

      if (this.citationsMapInstance) {
        this.citationsMapInstance.remove();
      }

      const map = L.map("citationsMapContainer").setView([20, 0], 2);
      this.citationsMapInstance = map;

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "© OpenStreetMap contributors",
        maxZoom: 18,
      }).addTo(map);

      const maxCitations = Math.max(...data.map((d) => d.citations_count));

      data.forEach((country) => {
        const coords = this.getCountryCoordinates(country.country_code);
        if (!coords) return;

        const size = (country.citations_count / maxCitations) * 17 + 8;

        const ratio = country.citations_count / maxCitations;
        const color =
          ratio >= 0.75
            ? "#ff6b6b"
            : ratio >= 0.5
            ? "#4ecdc4"
            : ratio >= 0.25
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
                    <div class="ps-map-tooltip">
                        <h3 class="ps-map-tooltip-title">${escapeHtml(
                          country.country_name
                        )}</h3>
                        <div class="ps-map-tooltip-value">${country.citations_count.toLocaleString()}</div>
                        <div class="ps-map-tooltip-label">${
                          i18n.citationsFromCountry || "Citations"
                        }</div>
                    </div>
                `);

        marker.addTo(map);
      });
    },

    renderCitationsTable(data) {
      const tbody = document.getElementById("citationsMapTableBody");
      if (!tbody) return;

      tbody.innerHTML = "";
      const top20 = data.slice(0, 20);

      top20.forEach((country, index) => {
        const row = tbody.insertRow();
        row.insertCell(0).textContent = index + 1;
        row.insertCell(1).textContent = country.country_name;

        const countCell = row.insertCell(2);
        countCell.textContent = country.citations_count.toLocaleString();
        countCell.className = "ps-cell-bold-right";
      });
    },

    getHeatColor(normalized) {
      if (normalized < 0.33) {
        return `rgb(${Math.round(normalized * 3 * 255)}, ${Math.round(
          normalized * 3 * 255
        )}, 255)`;
      } else if (normalized < 0.66) {
        const n = (normalized - 0.33) * 3;
        return `rgb(255, ${Math.round(255 * (1 - n))}, ${Math.round(
          255 * (1 - n)
        )})`;
      } else {
        return `rgb(255, 0, 0)`;
      }
    },
  };

  // ========================================
  // TABLE RENDERING
  // ========================================
  const Tables = {
    renderArticlesTable(bodyId, articles, key) {
      const body = document.getElementById(bodyId);
      if (!body) return;
      body.innerHTML = "";
      let index = 1;
      articles.forEach((article) => {
        const row = body.insertRow();
        row.insertCell(0).innerHTML = index;
        row.insertCell(1).innerHTML = `<a href="${escapeHtml(
          article.urlPublished
        )}">${escapeHtml(article.title)}</a>`;
        row.insertCell(2).innerHTML = article[key];
        index++;
      });
    },

    async renderTopDownloadedArticles() {
      const body = document.getElementById("topArticlesByDownloadsTableBody");
      if (!body) return;

      try {
        await API.getTopDownloaded(selectedYear);
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
        await API.getTopViewed(selectedYear);
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

      statsData.issueStats.forEach((issue, i) => {
        console.log(issue);
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

  // ========================================
  // IMPACT MODULE
  // ========================================
  const Impact = {
    chartInstance: null,

    async renderTopCited() {
      const body = document.getElementById("topCitedArticlesTableBody");
      if (!body) return;

      Utils.showLoadingIndicator();
      try {
        const response = await API.getTopCited(20);

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
          citCell.className = "ps-cell-bold-right " + (
            article.citations > 50 ? "ps-color-success" :
            article.citations > 20 ? "ps-color-warning" : "ps-color-muted"
          );
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
        const response = await API.getCitationEvolution();

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

        // Render cited articles table
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
        const articles = await API.getTopCited(20, year);

        // Update column header based on year filter
        const table = tbody.closest('table');
        if (table) {
          const citationsHeader = table.querySelector('thead tr th:last-child');
          if (citationsHeader) {
            citationsHeader.textContent = year 
              ? (i18n.citationsReceivedInYear || 'Citations received in ') + year
              : (i18n.externalCitations || 'External Citations');
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
          row.insertCell(1).innerHTML = `<a href="${escapeHtml(article.urlPublished)}">${escapeHtml(article.title)}</a>`;
          row.insertCell(2).textContent = article.authors || "-";
          row.insertCell(3).textContent = article.year || "-";

          const citCell = row.insertCell(4);
          citCell.textContent = (article.citations || 0).toLocaleString();
          citCell.className = "ps-cell-bold-right " + (
            article.citations > 50 ? "ps-color-success" :
            article.citations > 20 ? "ps-color-warning" : "ps-color-muted"
          );
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
        const response = await API.getOpenAccessStats();
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
        const response = await API.getThematicProfile();
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
    citingJournalsSelectedYear: 'all',

    async renderCitingJournals() {
      Utils.showLoadingIndicator();
      try {
        const response = await API.getCitingJournals();

        if (!response || !response.journals || response.journals.length === 0) {
          document.getElementById(
            "citingJournalsTableBody"
          ).innerHTML = `<tr><td colspan="6" class="ps-empty-message">${i18n.noCitingJournalsData}</td></tr>`;
          return;
        }

        // Store original data and available years
        this.citingJournalsOriginalData = response.journals;
        this.citingJournalsAvailableYears = response.available_years || [];
        
        // Populate year filter
        this.populateCitingJournalsYearFilter();
        
        // Render with current filter
        const filteredData = this.filterCitingJournalsByCitationYear(this.citingJournalsSelectedYear);
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
      
      // Clear existing options except "all"
      select.innerHTML = `<option value="all">${i18n.allTime || 'Todo el tiempo'}</option>`;
      
      // Add year options from available years
      this.citingJournalsAvailableYears.forEach(year => {
        const option = document.createElement('option');
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
      
      if (year === 'all') {
        return this.citingJournalsOriginalData;
      }
      
      // Filter journals by citation year
      return this.citingJournalsOriginalData.map(journal => {
        // Filter cited articles to only those cited in the selected year
        const filteredArticles = (journal.cited_articles || []).filter(
          article => article.citation_year == year
        );
        
        // Get citations count for selected year
        const filteredCitations = journal.citations_by_year?.[year] || 0;
        
        return {
          ...journal,
          citations: filteredCitations,
          cited_articles: filteredArticles
        };
      }).filter(journal => journal.citations > 0) // Remove journals with no citations in selected year
        .sort((a, b) => b.citations - a.citations); // Re-sort by citations
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

      this.citingJournalsChartInstance = new Chart(
        canvas.getContext("2d"),
        {
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
                    return `${context.parsed.x} ${i18n.citations || 'citations'}`;
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
        }
      );
    },

    renderCitingJournalsTable(data) {
      const tbody = document.getElementById("citingJournalsTableBody");
      if (!tbody) return;

      const colors = Utils.generateColors(data.length);
      tbody.innerHTML = "";

      data.forEach((journal, index) => {
        // Main row
        const row = tbody.insertRow();
        row.className = "journal-row";
        row.onclick = () => window.toggleJournalArticles(index);
        
        // Expand icon
        const expandCell = row.insertCell(0);
        expandCell.className = "ps-col-expand";
        expandCell.innerHTML = `<span class="expand-icon" id="expand-icon-${index}"><i class="fa-solid fa-chevron-right"></i></span>`;
        
        const numCell = row.insertCell(1);
        numCell.className = "ps-col-index";
        numCell.textContent = index + 1;

        // Color indicator
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
          journal.citations > 50 ? "ps-color-success" :
          journal.citations > 20 ? "ps-color-warning" : "ps-color-muted"
        );

        // Hidden row for cited articles
        const detailRow = tbody.insertRow();
        detailRow.id = `journal-articles-${index}`;
        detailRow.style.display = "none";
        detailRow.className = "journal-articles-row";
        
        const detailCell = detailRow.insertCell(0);
        detailCell.colSpan = 6;
        
        // Build articles sub-table
        const articles = journal.cited_articles || [];
        if (articles.length > 0) {
          let articlesHtml = `
            <div class="ps-articles-detail">
              <div class="ps-articles-detail-title">
                ${i18n.citedArticlesFromJournal || 'Artículos citados por esta revista'}:
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
                    <th>${i18n.articleTitle || 'Título'}</th>
                    <th class="ps-col-authors">${i18n.authors || 'Autores'}</th>
                    <th class="ps-col-center ps-col-year">${i18n.year || 'Año'}</th>
                    <th class="ps-col-center">${i18n.timesCited || 'Veces citado'}</th>
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
                <td class="ps-cell-authors">${escapeHtml(article.authors || '-')}</td>
                <td class="ps-cell-year">${article.year || '-'}</td>
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
          detailCell.innerHTML = `<div class="ps-articles-detail ps-color-muted">${i18n.noArticlesData || 'No hay datos de artículos'}</div>`;
        }
      });
    },
  };
  // ========================================
  // GLOBAL API EXPORTS
  // ========================================
  window.toggleSection = function (sectionId) {
    Navigation.toggleSection(sectionId);
  };

  window.showSection = function (sectionId) {
    Navigation.showSection(sectionId);
  };

  window.changeYear = function (year) {
    DataManager.changeYear(year);
  };

  window.resetChartZoom = function (chartId) {
    Charts.resetZoom(chartId);
  };

  window.initializeSectionContent = function (sectionId) {
    Navigation.initializeSectionContent(sectionId);
  };

  window.showNoDataMessage = function (containerId, message) {
    Utils.showNoDataMessage(containerId, message);
  };

  window.changeAuthor = async function (authorKey) {
    if (!authorKey) {
      selectedAuthor = null;
      AuthorStats.showNoData();
      return;
    }

    selectedAuthor = authorKey; // Guardar la key
    API.clearAuthorCache();

    Utils.showLoadingIndicator();
    try {
      const year = selectedYear || null;
      await API.getAuthorStats(authorKey, year);
      AuthorStats.renderAll();
    } catch (error) {
      console.error("Error loading author stats:", error);
      alert(i18n.errorLoading || "Error loading data");
    } finally {
      Utils.hideLoadingIndicator();
    }
  };
  // ========================================
  // EVENT LISTENERS
  // ========================================
  window.addEventListener("popstate", function (event) {
    if (event.state && event.state.year !== undefined) {
      const yearSelector = document.getElementById("yearSelector");
      if (yearSelector) {
        yearSelector.value = event.state.year || "";
        DataManager.changeYear(event.state.year);
      }
    }
  });

  document.addEventListener("DOMContentLoaded", function () {
    const firstLink = document.querySelector(".menu-link");
    if (firstLink) firstLink.classList.add("active");
    Navigation.showSection("monthly-trends");

    // Initialize export functionality
    setTimeout(() => {
      Export.initializeExportButtons();
    }, 500);
  });

  window.updateIssueChart = function (limit) {
    Charts.issueChartLimit = limit;
    Charts.initializeIssueChart();
  };

  window.updateJournalsChart = function (limit) {
    Impact.journalsChartLimit = limit;
    // Re-render with current year filter
    if (Impact.citingJournalsOriginalData) {
      const filteredData = Impact.filterCitingJournalsByCitationYear(
        Impact.citingJournalsSelectedYear
      );
      Impact.renderCitingJournalsChart(filteredData);
      Impact.renderCitingJournalsTable(filteredData);
    }
  };
  
  window.filterCitingJournalsByYear = function (year) {
    Impact.citingJournalsSelectedYear = year;
    if (Impact.citingJournalsOriginalData) {
      const filteredData = Impact.filterCitingJournalsByCitationYear(year);
      Impact.renderCitingJournalsChart(filteredData);
      Impact.renderCitingJournalsTable(filteredData);
    }
  };

  window.toggleJournalArticles = function (index) {
    const detailRow = document.getElementById(`journal-articles-${index}`);
    const expandIcon = document.getElementById(`expand-icon-${index}`);
    
    if (detailRow && expandIcon) {
      if (detailRow.style.display === "none") {
        detailRow.style.display = "table-row";
        expandIcon.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
      } else {
        detailRow.style.display = "none";
        expandIcon.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
      }
    }
  };

  window.updateSectionChart = function (limit) {
    Charts.sectionChartLimit = limit;
    Charts.initializeSectionChart();
  };
  window.updateThematicChart = async function (limit) {
    Impact.thematicChartLimit = limit;
    const response = await API.getThematicProfile();
    Impact.renderThematicChart(response.topics);
  };
})();
