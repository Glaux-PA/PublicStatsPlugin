/**
 * Public Statistics Plugin - Orchestrator.
 *
 * Wires navigation, year changes, and page initialisation. All feature
 * modules live in sibling files and attach themselves to window.PublicStats:
 *
 *   statistics-helpers.js  (escapeHtml, Utils, ChartInstances, ChartConfig,
 *                           YEAR_FILTERABLE_SECTIONS on window.PublicStatsHelpers)
 *   statistics-api.js      (PS.API, PS.Export)
 *   statistics-charts.js   (PS.Charts)
 *   statistics-tables.js   (PS.Tables)
 *   statistics-maps.js     (PS.Maps)
 *   statistics-impact.js   (PS.AuthorStats, PS.Impact)
 *
 * Globals it reads (defined in publicStats.tpl before this script loads):
 *   statsData, selectedYear, selectedAuthor, i18n,
 *   window.enabledSubsections, window.defaultSection
 */
(function () {
  "use strict";

  const { Utils, YEAR_FILTERABLE_SECTIONS } = window.PublicStatsHelpers;
  const PS = window.PublicStats || {};
  const { API, Export, Charts, Tables, Maps, AuthorStats, Impact } = PS;

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

        if (selectedAuthor) {
          selector.value = selectedAuthor;
        }
      } catch (error) {
        if (error && error.code === "STALE_REQUEST") return;
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
          "general-languages": async () => {
            const sel = document.getElementById("languageIssueFilter");
            const issueId = sel && sel.value ? parseInt(sel.value, 10) : null;
            await API.getLanguageStats(issueId);
            Charts.initializeLanguageChart();
            Tables.renderLanguageTable();
          },
          "language-trends": async () => {
            await API.getLanguageTrends();
            Charts.initializeLanguageTrendsChart();
            Tables.renderLanguageTrendsSummary();
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
          "reviewer-list": async () => {
            const sel = document.getElementById("reviewerListYearFilter");
            if (sel && !sel.dataset.initialized) {
              const prevYear = String(new Date().getFullYear() - 1);
              if ([...sel.options].some((o) => o.value === prevYear))
                sel.value = prevYear;
              sel.dataset.initialized = "1";
            }
            const listYear = sel?.value || null;
            const title = document.getElementById("reviewerListCardTitle");
            if (title) {
              title.textContent = listYear
                ? `${i18n.reviewerListCardTitle} ${listYear}`
                : i18n.reviewerListCardTitle;
            }
            await API.getReviewerList(listYear || null);
            Tables.renderReviewerListTable();
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
              const y = selectedYear || null;
              await API.getAuthorStats(selectedAuthor, y);
              AuthorStats.renderAll();
            }
          },
          "top-cited": async () => {
            await Impact.renderTopCited();
          },
          "citation-evolution": async () => {
            const sel = document.getElementById("citedArticlesYearFilter");
            if (sel) {
              sel.value = Impact.citedArticlesYear || "all";
            }
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

        Export.initializeExportButtons();
      } catch (error) {
        if (error && error.code === "STALE_REQUEST") {
          // The user changed year mid-fetch; the new fetch already owns the UI.
          return;
        }
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
    changeYear(year) {
      selectedYear = year;
      // Invalidate any in-flight fetches from the previous year so a slow
      // response can't overwrite the UI we're about to repopulate.
      API.invalidateInflight();
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

  PS.Navigation = Navigation;
  PS.DataManager = DataManager;

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

    selectedAuthor = authorKey;
    API.clearAuthorCache();

    Utils.showLoadingIndicator();
    try {
      const year = selectedYear || null;
      await API.getAuthorStats(authorKey, year);
      AuthorStats.renderAll();
    } catch (error) {
      if (error && error.code === "STALE_REQUEST") return;
      console.error("Error loading author stats:", error);
      alert(i18n.errorLoading || "Error loading data");
    } finally {
      Utils.hideLoadingIndicator();
    }
  };

  window.updateIssueChart = function (limit) {
    Charts.issueChartLimit = limit;
    Charts.initializeIssueChart();
  };

  window.updateJournalsChart = function (limit) {
    Impact.journalsChartLimit = limit;
    if (Impact.citingJournalsOriginalData) {
      const filteredData = Impact.filterCitingJournalsByCitationYear(
        Impact.citingJournalsSelectedYear
      );
      Impact.renderCitingJournalsChart(filteredData);
      Impact.renderCitingJournalsTable(filteredData);
    }
  };

  window.filterLanguagesByIssue = async function (issueId) {
    const id = issueId ? parseInt(issueId, 10) : null;
    Utils.showLoadingIndicator();
    try {
      await API.getLanguageStats(id);
      Charts.initializeLanguageChart();
      Tables.renderLanguageTable();
    } catch (error) {
      if (error && error.code === "STALE_REQUEST") return;
      console.error("Error filtering languages by issue:", error);
    } finally {
      Utils.hideLoadingIndicator();
    }
  };

  window.filterReviewerListByYear = async function (year) {
    statsData.reviewerList = null;
    // Cancel any earlier reviewer-list fetch still in flight so a slow
    // response from the previous year can't overwrite this one's UI.
    API.invalidateInflight();
    const title = document.getElementById("reviewerListCardTitle");
    if (title) {
      title.textContent = year
        ? `${i18n.reviewerListCardTitle} ${year}`
        : i18n.reviewerListCardTitle;
    }
    Utils.showLoadingIndicator();
    try {
      await API.getReviewerList(year || null);
      Tables.renderReviewerListTable();
    } catch (error) {
      if (error && error.code === "STALE_REQUEST") return;
      console.error("Error loading reviewer list:", error);
    } finally {
      Utils.hideLoadingIndicator();
    }
  };

  window.filterCitedArticlesByYear = async function (year) {
    Impact.citedArticlesYear = year && year !== "all" ? year : null;
    API.invalidateInflight();
    Utils.showLoadingIndicator();
    try {
      await Impact.renderCitedArticlesTable();
    } catch (error) {
      if (error && error.code === "STALE_REQUEST") return;
      console.error("Error filtering cited articles:", error);
    } finally {
      Utils.hideLoadingIndicator();
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
    if (response && response.is_computing) return;
    Impact.renderThematicChart((response && response.topics) || []);
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
    const enabledSubs = Array.isArray(window.enabledSubsections)
      ? window.enabledSubsections
      : null;

    if (enabledSubs) {
      document.querySelectorAll(".menu-link[data-section]").forEach((link) => {
        if (!enabledSubs.includes(link.dataset.section)) {
          link.closest(".menu-item").classList.add("ps-item-disabled");
        }
      });

      document
        .querySelectorAll(".sidebar-section[data-group]")
        .forEach((group) => {
          const total = group.querySelectorAll(".menu-item").length;
          const hidden = group.querySelectorAll(
            ".menu-item.ps-item-disabled"
          ).length;
          if (total > 0 && total === hidden) {
            group.classList.add("ps-group-disabled");
          }
        });
    }

    const firstLink = document.querySelector(
      ".sidebar-section:not(.ps-group-disabled) .menu-item:not(.ps-item-disabled) .menu-link"
    );
    if (firstLink) firstLink.classList.add("active");

    const start =
      typeof window.defaultSection === "string" && window.defaultSection
        ? window.defaultSection
        : "monthly-trends";
    Navigation.showSection(start);

    setTimeout(() => {
      Export.initializeExportButtons();
    }, 500);
  });
})();
