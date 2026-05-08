{**
 * templates/publicStats.tpl
 * Public Statistics Display Template
 *}
{include file="frontend/components/header.tpl"}


{* ============================================= *}
{* DYNAMIC CSS VARIABLES - Primary Color Theme  *}
{* ============================================= *}
<style>
    :root {
        --ps-primary: {$primaryColor|default:'#8b2635'};
        --ps-primary-light: {$primaryColorLight|default:'#9b3645'};
        --ps-primary-dark: {$primaryColorDark|default:'#6b1e2a'};
        --ps-primary-darker: {$primaryColorDarker|default:'#5b1620'};
        --ps-primary-rgb: {$primaryColorRgb|default:'139, 38, 53'};
    }

    /* ========================================
       COMMON TABLE STYLES
       ======================================== */

    /* Empty state messages */
    .ps-empty-message {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .ps-error-message {
        text-align: center;
        padding: 40px;
        color: #e74c3c;
    }

    /* Table cell styles */
    .ps-cell-bold-right {
        font-weight: bold;
        text-align: right;
    }

    .ps-cell-center {
        text-align: center;
    }

    .ps-cell-clickable {
        cursor: pointer;
    }

    /* Color indicators */
    .ps-color-success {
        color: #27ae60;
    }

    .ps-color-warning {
        color: #f39c12;
    }

    .ps-color-muted {
        color: #666;
    }

    .ps-color-error {
        color: #e74c3c;
    }

    .ps-color-primary {
        color: var(--ps-primary);
    }

    .ps-color-blue {
        color: #3498db;
    }

    .ps-color-green {
        color: #2ecc71;
    }

    .ps-color-purple {
        color: #9b59b6;
    }

    /* ========================================
       STAT CARDS & SUMMARIES
       ======================================== */

    .ps-stat-box {
        text-align: center;
    }

    .ps-stat-value {
        font-size: 32px;
        font-weight: bold;
    }

    .ps-stat-value-large {
        font-size: 48px;
        font-weight: bold;
        margin: 20px 0;
    }

    .ps-stat-label {
        color: #666;
        margin-top: 5px;
    }

    .ps-stat-label-sm {
        color: #666;
        font-size: 14px;
    }

    /* No data placeholder */
    .ps-no-data-container {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .ps-no-data-icon {
        font-size: 48px;
        margin-bottom: 10px;
        opacity: 0.3;
    }

    .ps-no-data-text {
        font-size: 14px;
    }

    /* Grid span utilities */
    .ps-grid-span-2 {
        grid-column: span 2;
    }

    /* ========================================
       MAP TOOLTIPS
       ======================================== */

    .ps-map-tooltip {
        text-align: center;
        min-width: 120px;
    }

    .ps-map-tooltip-title {
        margin: 0 0 8px 0;
        color: #333;
        font-size: 14px;
    }

    .ps-map-tooltip-value {
        font-size: 16px;
        font-weight: bold;
        color: var(--ps-primary);
    }

    .ps-map-tooltip-label {
        font-size: 12px;
        color: #666;
    }

    /* ========================================
       EMPTY STATE LARGE
       ======================================== */

    .ps-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 300px;
        color: #666;
    }

    .ps-empty-state-icon {
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .ps-empty-state-title {
        font-size: 18px;
        font-weight: 500;
        margin: 0 0 10px 0;
    }

    .ps-empty-state-desc {
        font-size: 14px;
        margin: 0;
        opacity: 0.7;
    }

    /* ========================================
       EXPORT MENU
       ======================================== */

    .ps-group-disabled {
        display: none !important;
    }

    .ps-item-disabled {
        display: none !important;
    }

    .export-menu-dropdown {
        display: none;
    }

    .ps-section-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    /* ========================================
       CITING JOURNALS - EXPANDABLE TABLE
       ======================================== */

    #citingJournalsTable {
        table-layout: fixed;
        width: 100%;
    }

    .journal-row {
        transition: background-color 0.15s ease;
        cursor: pointer;
    }

    .journal-row:hover {
        background-color: #f5f5f5;
    }

    .journal-row td {
        vertical-align: middle;
    }

    .expand-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        color: #666;
        transition: transform 0.2s;
    }

    .expand-icon i {
        font-size: 12px;
    }

    /* Journal articles detail row */
    .journal-articles-row td {
        padding: 0 !important;
        border-top: none;
    }

    .journal-articles-row table th,
    .journal-articles-row table td {
        text-transform: none !important;
        letter-spacing: normal !important;
    }

    /* Articles detail container */
    .ps-articles-detail {
        padding: 20px 25px 20px 25px;
        background: linear-gradient(to bottom, #f8f9fa, #f1f3f4);
        border-bottom: 2px solid #dee2e6;
    }

    .ps-articles-detail-title {
        color: #495057;
        font-size: 0.9em;
        font-weight: 600;
        margin-bottom: 12px;
    }

    /* Articles sub-table */
    .ps-articles-table {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        font-size: 0.9em;
        background: white;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    }

    .ps-articles-table thead tr {
        background-color: #495057;
        color: white;
    }

    .ps-articles-table th {
        padding: 12px 10px;
        text-align: left;
        font-weight: 500;
        font-size: 0.85em;
    }

    .ps-articles-table th.ps-col-center {
        text-align: center;
    }

    .ps-articles-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .ps-articles-table tr:nth-child(even) {
        background-color: #f8f9fa;
    }

    .ps-articles-table tr:nth-child(odd) {
        background-color: #ffffff;
    }

    .ps-articles-table .ps-cell-num {
        text-align: center;
        color: #999;
        font-size: 0.9em;
        padding-left: 15px;
    }

    .ps-articles-table .ps-cell-title {
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ps-articles-table .ps-cell-authors {
        color: #666;
        font-size: 0.9em;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ps-articles-table .ps-cell-year {
        text-align: center;
        color: #666;
    }

    .ps-articles-table .ps-cell-cited {
        text-align: center !important;
        font-weight: 600;
        color: var(--ps-primary);
        font-size: 1.05em;
    }

    /* Article link style */
    .ps-article-link {
        color: var(--ps-primary);
        text-decoration: none;
    }

    .ps-article-link:hover {
        text-decoration: underline;
    }

    /* Column widths for articles table */
    .ps-col-num {
        width: 45px;
    }

    .ps-col-title {
        /* flexible */
    }

    .ps-col-authors {
        width: 180px;
    }

    .ps-col-year {
        width: 65px;
    }

    .ps-col-cited {
        width: 85px;
    }

    /* Column widths for journals table */
    .ps-col-expand {
        width: 40px;
        text-align: center;
    }

    .ps-col-index {
        width: 50px;
    }

    .ps-col-color {
        width: 40px;
    }

    .ps-col-issn {
        width: 120px;
    }

    .ps-col-citations {
        width: 80px;
    }

    /* Author cards grid */
    .ps-author-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    /* Responsive: hide authors and year columns on small screens */
    @media (max-width: 992px) {

        .ps-articles-table .ps-col-authors,
        .ps-articles-table .ps-cell-authors,
        .ps-articles-table th.ps-col-authors,
        .ps-articles-table .ps-col-year,
        .ps-articles-table .ps-cell-year,
        .ps-articles-table th.ps-col-year {
            display: none;
        }
    }
</style>

{$pluginJavaScriptURL = "{$baseUrl}/plugins/generic/publicStats/templates/js"}
{$pluginCssURL = "{$baseUrl}/plugins/generic/publicStats/templates/styles"}

<script src="{$pluginJavaScriptURL}/vendor/chart.min.js"></script>
<script src="{$pluginJavaScriptURL}/vendor/hammer.min.js"></script>
<script src="{$pluginJavaScriptURL}/vendor/chartjs-plugin-zoom.min.js"></script>
<link rel="stylesheet" href="{$pluginCssURL}/vendor/leaflet.css" />
<script src="{$pluginJavaScriptURL}/vendor/leaflet.js"></script>
{* Font Awesome Icons (vendored, solid only) *}
<link rel="stylesheet" href="{$pluginCssURL}/vendor/fontawesome/css/fontawesome.min.css" />
<link rel="stylesheet" href="{$pluginCssURL}/vendor/fontawesome/css/solid.min.css" />

{* Initialize data *}
<script>
    {literal}
        var statsData = {
            monthlyStats: null,
            topArticlesByDownloads: null,
            topArticlesByViews: null,
            countryData: null,
            annualStats: null,
            issueStats: null,
            sectionStats: null,
            recentTopDownloaded: null,
            recentTopViewed: null,
            editorialStats: null,
            editorialStatsAnnual: null,
            authorsByCountry: null,
            authorsByInstitution: null,
            reviewersByCountry: null,
            reviewersByInstitution: null,
            reviewerList: null,
            firstDecisionStats: null,
            acceptancePublicationStats: null,
            authorStats: null,
            authorsList: null,
            topCitedArticles: null,
            citationEvolution: null,
            openAccessStats: null,
            oaChartInstance: null,
            thematicProfile: null,
            citationsByCountry: null,
            citingJournals: null,
            languageStats: null,
            languageTrends: null,
        };

        var selectedYear = "{/literal}{$selectedYear}{literal}";
        var selectedAuthor = null;
        var enabledSubsections = {/literal}{$enabledSubsections|json_encode}{literal};
        var defaultSection     = "{/literal}{$defaultSection}{literal}";

        // Translation strings
        var i18n = {
            downloads: "{/literal}{translate key="plugins.generic.publicStats.downloads"}{literal}",
            views: "{/literal}{translate key="plugins.generic.publicStats.views"}{literal}",
            received: "{/literal}{translate key="plugins.generic.publicStats.received"}{literal}",
            published: "{/literal}{translate key="plugins.generic.publicStats.published"}{literal}",
            declined: "{/literal}{translate key="plugins.generic.publicStats.declined"}{literal}",
            inProcess: "{/literal}{translate key="plugins.generic.publicStats.inProcess"}{literal}",
            totalReceived: "{/literal}{translate key="plugins.generic.publicStats.totalReceived"}{literal}",
            totalAccesses: "{/literal}{translate key="plugins.generic.publicStats.totalAccesses"}{literal}",
            errorLoading: "{/literal}{translate key="plugins.generic.publicStats.errorLoading"}{literal}",
            monthlyOverview: "{/literal}{translate key="plugins.generic.publicStats.monthlyOverview"}{literal}",
            mostDownloadedArticles: "{/literal}{translate key="plugins.generic.publicStats.mostDownloadedArticles"}{literal}",
            mostViewedArticles: "{/literal}{translate key="plugins.generic.publicStats.mostViewedArticles"}{literal}",
            downloadsByIssue: "{/literal}{translate key="plugins.generic.publicStats.downloadsByIssue"}{literal}",
            downloadsBySection: "{/literal}{translate key="plugins.generic.publicStats.downloadsBySection"}{literal}",
            submissionsOverview: "{/literal}{translate key="plugins.generic.publicStats.submissionsOverview"}{literal}",
            totalAuthors: "{/literal}{translate key="plugins.generic.publicStats.totalAuthors"}{literal}",
            totalReviewers: "{/literal}{translate key="plugins.generic.publicStats.totalReviewers"}{literal}",
            otherInstitutions: "{/literal}{translate key="plugins.generic.publicStats.otherInstitutions"}{literal}",
            acceptancePublicationStats:"{/literal}{translate key="plugins.generic.publicStats.acceptancePublicationDaysTitle"}{literal}",
            firstDecisionStats:"{/literal}{translate key="plugins.generic.publicStats.firstDecisionDaysTitle"}{literal}",
            noDataAvailable: "{/literal}{translate key="plugins.generic.publicStats.noDataAvailable"}{literal}",
            noDataMessage: "{/literal}{translate key="plugins.generic.publicStats.noDataMessage"}{literal}",
            noAuthorsData: "{/literal}{translate key="plugins.generic.publicStats.noAuthorsData"}{literal}",
            noReviewersData: "{/literal}{translate key="plugins.generic.publicStats.noReviewersData"}{literal}",
            noReviewerListData: "{/literal}{translate key="plugins.generic.publicStats.noReviewerListData"}{literal}",
            reviewerListCardTitle: "{/literal}{translate key="plugins.generic.publicStats.reviewerListCardTitle"}{literal}",
            noInstitutionData: "{/literal}{translate key="plugins.generic.publicStats.noInstitutionData"}{literal}",
            noMonthlyData: "{/literal}{translate key="plugins.generic.publicStats.noMonthlyData"}{literal}",
            noAnnualData: "{/literal}{translate key="plugins.generic.publicStats.noAnnualData"}{literal}",
            noIssueData: "{/literal}{translate key="plugins.generic.publicStats.noIssueData"}{literal}",
            noSectionData: "{/literal}{translate key="plugins.generic.publicStats.noSectionData"}{literal}",
            noEditorialData: "{/literal}{translate key="plugins.generic.publicStats.noEditorialData"}{literal}",
            noEditorialAnnualData:"{/literal}{translate key="plugins.generic.publicStats.noEditorialAnnualData"}{literal}",
            noDownloadsData: "{/literal}{translate key="plugins.generic.publicStats.noDownloadsData"}{literal}",
            noViewsData: "{/literal}{translate key="plugins.generic.publicStats.noViewsData"}{literal}",
            noGeographicData: "{/literal}{translate key="plugins.generic.publicStats.noGeographicData"}{literal}",
            noRecentDownloadsData:"{/literal}{translate key="plugins.generic.publicStats.noRecentDownloadsData"}{literal}",
            noRecentViewsData: "{/literal}{translate key="plugins.generic.publicStats.noRecentViewsData"}{literal}",
            externalCitations: "{/literal}{translate key='plugins.generic.publicStats.externalCitations'}{literal}",
            year: "{/literal}{translate key='plugins.generic.publicStats.year'}{literal}",
            noCitationData: "{/literal}{translate key='plugins.generic.publicStats.noCitationData'}{literal}",
            computingPlaceholder: "{/literal}{translate key='plugins.generic.publicStats.computingPlaceholder'}{literal}",
            citationsReceived: "{/literal}{translate key='plugins.generic.publicStats.citationsReceived'}{literal}",
            citationsReceivedInYear: "{/literal}{translate key='plugins.generic.publicStats.citationsReceivedInYear'}{literal}",
            openAccess: "{/literal}{translate key='plugins.generic.publicStats.openAccess'}{literal}",
            oaPercentage: "{/literal}{translate key='plugins.generic.publicStats.oaPercentage'}{literal}",
            oaDiamond: "{/literal}{translate key='plugins.generic.publicStats.oaDiamond'}{literal}",
            oaGold: "{/literal}{translate key='plugins.generic.publicStats.oaGold'}{literal}",
            oaHybrid: "{/literal}{translate key='plugins.generic.publicStats.oaHybrid'}{literal}",
            oaGreen: "{/literal}{translate key='plugins.generic.publicStats.oaGreen'}{literal}",
            oaBronze: "{/literal}{translate key='plugins.generic.publicStats.oaBronze'}{literal}",
            oaClosed: "{/literal}{translate key='plugins.generic.publicStats.oaClosed'}{literal}",
            oaUnknown: "{/literal}{translate key='plugins.generic.publicStats.oaUnknown'}{literal}",
            articlesAnalyzed: "{/literal}{translate key='plugins.generic.publicStats.articlesAnalyzed'}{literal}",
            noOaData: "{/literal}{translate key='plugins.generic.publicStats.noOaData'}{literal}",
            articlesInArea: "{/literal}{translate key='plugins.generic.publicStats.articlesInArea'}{literal}",
            noThematicData: "{/literal}{translate key='plugins.generic.publicStats.noThematicData'}{literal}",
            citationsFromCountry:"{/literal}{translate key='plugins.generic.publicStats.citationsFromCountry'}{literal}",
            noCitationMapData: "{/literal}{translate key='plugins.generic.publicStats.noCitationMapData'}{literal}",
            noCitingJournalsData:"{/literal}{translate key='plugins.generic.publicStats.noCitingJournalsData'}{literal}",
            citationsFromJournal:"{/literal}{translate key='plugins.generic.publicStats.citationsFromJournal'}{literal}",
            citedArticlesFromJournal:"{/literal}{translate key='plugins.generic.publicStats.citedArticlesFromJournal'}{literal}",
            timesCited:"{/literal}{translate key='plugins.generic.publicStats.timesCited'}{literal}",
            allTime:"{/literal}{translate key='plugins.generic.publicStats.allTime'}{literal}",
            noLanguageData:"{/literal}{translate key='plugins.generic.publicStats.noLanguageData'}{literal}",
            noLanguageTrendsData:"{/literal}{translate key='plugins.generic.publicStats.noLanguageTrendsData'}{literal}",
            totalArticles:"{/literal}{translate key='plugins.generic.publicStats.totalArticles'}{literal}",
            languagesIdentified:"{/literal}{translate key='plugins.generic.publicStats.languagesIdentified'}{literal}",
            leadingLanguage:"{/literal}{translate key='plugins.generic.publicStats.leadingLanguage'}{literal}",
            dataPeriod:"{/literal}{translate key='plugins.generic.publicStats.dataPeriod'}{literal}",

            // -- Keys consumed by JS modules. Any key referenced in templates/js/*.js
            // -- via `i18n.X` must be declared here so the localized string is exposed.
            affiliation:"{/literal}{translate key='plugins.generic.publicStats.affiliation'}{literal}",
            annualStats:"{/literal}{translate key='plugins.generic.publicStats.annualStats'}{literal}",
            articleTitle:"{/literal}{translate key='plugins.generic.publicStats.articleTitle'}{literal}",
            authors:"{/literal}{translate key='plugins.generic.publicStats.authors'}{literal}",
            authorsByCountry:"{/literal}{translate key='plugins.generic.publicStats.authorsByCountry'}{literal}",
            citations:"{/literal}{translate key='plugins.generic.publicStats.citations'}{literal}",
            country:"{/literal}{translate key='plugins.generic.publicStats.country'}{literal}",
            countryStats:"{/literal}{translate key='plugins.generic.publicStats.countryStats'}{literal}",
            days:"{/literal}{translate key='plugins.generic.publicStats.days'}{literal}",
            daysAverage:"{/literal}{translate key='plugins.generic.publicStats.daysAverage'}{literal}",
            editorialStats:"{/literal}{translate key='plugins.generic.publicStats.editorialStats'}{literal}",
            export:"{/literal}{translate key='plugins.generic.publicStats.export'}{literal}",
            exportCsv:"{/literal}{translate key='plugins.generic.publicStats.exportCsv'}{literal}",
            exportOptions:"{/literal}{translate key='plugins.generic.publicStats.exportOptions'}{literal}",
            fullReport:"{/literal}{translate key='plugins.generic.publicStats.fullReport'}{literal}",
            monthlyStats:"{/literal}{translate key='plugins.generic.publicStats.monthlyStats'}{literal}",
            name:"{/literal}{translate key='plugins.generic.publicStats.name'}{literal}",
            noArticles:"{/literal}{translate key='plugins.generic.publicStats.noArticles'}{literal}",
            noArticlesData:"{/literal}{translate key='plugins.generic.publicStats.noArticlesData'}{literal}",
            noCoAuthors:"{/literal}{translate key='plugins.generic.publicStats.noCoAuthors'}{literal}",
            noDecisionData:"{/literal}{translate key='plugins.generic.publicStats.noDecisionData'}{literal}",
            noPublicationData:"{/literal}{translate key='plugins.generic.publicStats.noPublicationData'}{literal}",
            publications:"{/literal}{translate key='plugins.generic.publicStats.publications'}{literal}",
            publicationsReviewed:"{/literal}{translate key='plugins.generic.publicStats.publicationsReviewed'}{literal}",
            reviewersByCountry:"{/literal}{translate key='plugins.generic.publicStats.reviewersByCountry'}{literal}",
            selectAuthorPlaceholder:"{/literal}{translate key='plugins.generic.publicStats.selectAuthorPlaceholder'}{literal}",
            submissionsReviewed:"{/literal}{translate key='plugins.generic.publicStats.submissionsReviewed'}{literal}",
            topCited:"{/literal}{translate key='plugins.generic.publicStats.topCited'}{literal}",
            topDownloaded:"{/literal}{translate key='plugins.generic.publicStats.topDownloaded'}{literal}",
            topViewed:"{/literal}{translate key='plugins.generic.publicStats.topViewed'}{literal}",
            totalPublications:"{/literal}{translate key='plugins.generic.publicStats.totalPublications'}{literal}",
            totalSubmissions:"{/literal}{translate key='plugins.generic.publicStats.totalSubmissions'}{literal}",
        };
    {/literal}
</script>

<div class="page page_statistics">
    <div class="container">

        {* Sidebar navigation *}
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>{translate key="plugins.generic.publicStats.statistics"}</h1>
            </div>

            {* General statistics section *}
            <div class="sidebar-section" data-group="general">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('general')">
                        <i class="fa-solid fa-chart-pie section-icon"></i>
                        <span>{translate key="plugins.generic.publicStats.generalStatistics"}</span>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="section-content" id="general-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" data-section="monthly-trends" onclick="showSection('monthly-trends')">
                                {translate key="plugins.generic.publicStats.monthlyTrends"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="annual-trends" onclick="showSection('annual-trends')">
                                {translate key="plugins.generic.publicStats.annualTrends"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="general-downloads" onclick="showSection('general-downloads')">
                                {translate key="plugins.generic.publicStats.contributionsDownloads"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="general-views" onclick="showSection('general-views')">
                                {translate key="plugins.generic.publicStats.contributionsViews"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="general-sections" onclick="showSection('general-sections')">
                                {translate key="plugins.generic.publicStats.sections"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="general-issues" onclick="showSection('general-issues')">
                                {translate key="plugins.generic.publicStats.issues"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="general-languages" onclick="showSection('general-languages')">
                                {translate key="plugins.generic.publicStats.languageDistribution"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="language-trends" onclick="showSection('language-trends')">
                                {translate key="plugins.generic.publicStats.languageTrends"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="geographic-distribution" onclick="showSection('geographic-distribution')">
                                {translate key="plugins.generic.publicStats.geographicDistribution"}</div>
                        </li>
                    </ul>
                </div>
            </div>


            {* Editorial statistics section *}
            <div class="sidebar-section" data-group="editorial">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('editorial')">
                        <i class="fa-solid fa-pen-to-square section-icon"></i>
                        <span>{translate key="plugins.generic.publicStats.editorialStatistics"}</span>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="section-content section-collapsed" id="editorial-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" data-section="author-individual-stats" onclick="showSection('author-individual-stats')">
                                {translate key="plugins.generic.publicStats.authorIndividualStats"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="editorial-submissions" onclick="showSection('editorial-submissions')">
                                {translate key="plugins.generic.publicStats.monthlyContributions"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="editorial-annual" onclick="showSection('editorial-annual')">
                                {translate key="plugins.generic.publicStats.annualContributions"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="authors-by-country" onclick="showSection('authors-by-country')">
                                {translate key="plugins.generic.publicStats.authorsByCountry"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="authors-by-institution" onclick="showSection('authors-by-institution')">
                                {translate key="plugins.generic.publicStats.authorsByInstitution"}
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="reviewers-by-country" onclick="showSection('reviewers-by-country')">
                                {translate key="plugins.generic.publicStats.reviewersByCountry"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="reviewers-by-institution" onclick="showSection('reviewers-by-institution')">
                                {translate key="plugins.generic.publicStats.reviewersByInstitution"}
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="reviewer-list" onclick="showSection('reviewer-list')">
                                {translate key="plugins.generic.publicStats.reviewerList"}
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="first-decision-stats" onclick="showSection('first-decision-stats')">
                                {translate key="plugins.generic.publicStats.firstDecisionDays"}
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="acceptance-publication-stats" onclick="showSection('acceptance-publication-stats')">
                                {translate key="plugins.generic.publicStats.acceptancePublicationDays"}
                            </div>
                        </li>
                    </ul>
                </div>
            </div>


            {* Article reach section *}
            <div class="sidebar-section" data-group="reach">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('reach')">
                        <i class="fa-solid fa-globe section-icon"></i>
                        <span>{translate key="plugins.generic.publicStats.articleReach"}</span>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="section-content section-collapsed" id="reach-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" data-section="recent-downloads" onclick="showSection('recent-downloads')">
                                {translate key="plugins.generic.publicStats.mostDownloaded60Days"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="recent-views" onclick="showSection('recent-views')">
                                {translate key="plugins.generic.publicStats.mostViewed60Days"}</div>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="sidebar-section" data-group="impact">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('impact')">
                        <i class="fa-solid fa-chart-line section-icon"></i>
                        <span>{translate key="plugins.generic.publicStats.impactAnalysis"}</span>
                        <span class="section-toggle"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                </div>
                <div class="section-content section-collapsed" id="impact-content">
                    <ul class="sidebar-menu">

                        <li class="menu-item">
                            <div class="menu-link" data-section="top-cited" onclick="showSection('top-cited')">
                                {translate key="plugins.generic.publicStats.topCitedArticles"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="citation-evolution" onclick="showSection('citation-evolution')">
                                {translate key="plugins.generic.publicStats.citationEvolution"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="open-access-stats" onclick="showSection('open-access-stats')">
                                {translate key="plugins.generic.publicStats.openAccessStats"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="thematic-profile" onclick="showSection('thematic-profile')">
                                {translate key="plugins.generic.publicStats.thematicProfile"}</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="citations-map" onclick="showSection('citations-map')">
                                {translate key="plugins.generic.publicStats.citationsByCountry"}
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" data-section="citing-journals" onclick="showSection('citing-journals')">
                                {translate key="plugins.generic.publicStats.citingJournals"}
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {* Main content area *}
        <div class="main-content">
            <div id="loadingIndicator" class="loading-indicator" style="display: none;">
                <div class="loading-content">
                    <div class="loading-spinner"></div>
                    <p>{translate key="plugins.generic.publicStats.loadingStats"}</p>
                </div>
            </div>


            {* Year selector *}
            <div class="year-selector-container">
                <label for="yearSelector">{translate key="plugins.generic.publicStats.selectYear"}</label>
                <select id="yearSelector" onchange="changeYear(this.value)">
                    <option value="">{translate key="plugins.generic.publicStats.allTime"}</option>
                    {foreach from=$availableYears item=year}
                        <option value="{$year}" {if $selectedYear == $year}selected{/if}>{$year}</option>
                    {/foreach}
                </select>
            </div>

            {* Monthly trends section *}
            <div id="monthly-trends" class="content-section"{if $defaultSection !== 'monthly-trends'} style="display:none"{/if}>
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.monthlyOverview"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.downloadsViewsOverYear"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.activity"}</h2>
                            <button onclick="resetChartZoom('monthlyStatsChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Annual trends section *}
            <div id="annual-trends" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.annualOverview"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.yearlyDownloadsViews"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.annualActivity"}</h2>
                            <button onclick="resetChartZoom('annualStatsChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="annualStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            {* Geographic distribution section *}
            <div id="geographic-distribution" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.geographicDistribution"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.worldwideDistribution"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.worldAccessMap"}</h2>
                            <div class="map-legend">
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #ff6b6b;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.veryHighAccess"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #4ecdc4;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.highAccess"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #45b7d1;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.mediumAccess"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #96ceb4;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.lowAccess"}</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="worldMap" class="map-container"></div>
                        </div>
                    </div>
                    <div class="stats-card" style="margin-top: 30px;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topCountriesByAccess"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.country"}</th>
                                            <th>{translate key="plugins.generic.publicStats.totalAccesses"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="geographicTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {* Top downloaded articles section *}
            <div id="general-downloads" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.mostDownloadedArticles"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.rankingByDownloads"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topArticlesByDownloads"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.authors"}</th>
                                            <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topArticlesByDownloadsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Top viewed articles section *}
            <div id="general-views" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.mostViewedArticles"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.rankingByViews"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topArticlesByViews"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.authors"}</th>
                                            <th>{translate key="plugins.generic.publicStats.views"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topArticlesByViewsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Issues section *}
            <div id="general-issues" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.downloadsByIssue"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.distributionAcrossIssues"}
                    </p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.issueDistribution"}</h2>
                            <div style=" text-align: right;">
                                <label for="issueChartLimit" style="margin-right: 10px;">
                                    {translate key="plugins.generic.publicStats.showTop"}
                                </label>
                                <select id="issueChartLimit" onchange="updateIssueChart(this.value)">
                                    <option value="5" selected>5</option>
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="25">25</option>
                                    <option value="30">30</option>
                                    <option value="all">{translate key="plugins.generic.publicStats.all"}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">

                                <canvas id="issueStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.statsByIssue"}{if $selectedYear}
                                ({$selectedYear}){/if}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th>{translate key="plugins.generic.publicStats.issue"}
                                            <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                            <th>{translate key="plugins.generic.publicStats.views"}</th>
                                            <th>{translate key="plugins.generic.publicStats.total"}</th>
                                            <th>{translate key="plugins.generic.publicStats.articles"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="issueStatsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Language distribution section *}
            <div id="general-languages" class="content-section" style="display: none;">
                {* Issue filter - same style as global year selector *}
                <div class="year-selector-container">
                    <label for="languageIssueFilter">{translate key="plugins.generic.publicStats.filterByIssue"}</label>
                    <select id="languageIssueFilter" onchange="filterLanguagesByIssue(this.value)">
                        <option value="">{translate key="plugins.generic.publicStats.allIssues"}</option>
                        {foreach from=$availableIssues item=issue}
                            <option value="{$issue.id}">{$issue.label|escape}</option>
                        {/foreach}
                    </select>
                </div>

                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.languageDistribution"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.languageDistributionDesc"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.articleLanguages"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="languageStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.articlesByLanguage"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 40px;"></th>
                                            <th>{translate key="plugins.generic.publicStats.language"}</th>
                                            <th>{translate key="plugins.generic.publicStats.articles"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="languageStatsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Language trends section *}
            <div id="language-trends" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.languageTrends"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.languageTrendsDesc"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.languageTrendsChart"}</h2>
                            <button onclick="resetChartZoom('languageTrendsChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="languageTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.summary"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="languageTrendsSummary"
                                style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Sections section *}
            <div id="general-sections" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.downloadsBySection"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.distributionAcrossSections"}
                    </p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.sectionDistribution"}
                            </h2>

                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="sectionStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.statsBySection"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 40px;"></th>
                                            <th>{translate key="plugins.generic.publicStats.section"}</th>
                                            <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                            <th>{translate key="plugins.generic.publicStats.views"}</th>
                                            <th>{translate key="plugins.generic.publicStats.total"}</th>
                                            <th>{translate key="plugins.generic.publicStats.articles"}</th>
                                        </tr>
                                        </tr>
                                    </thead>
                                    <tbody id="sectionStatsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {* Editorial submissions section *}
            <div id="editorial-submissions" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.submissionsOverview"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.monthlySubmissionsTracking"}
                    </p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.submissionActivity"}</h2>
                            <button onclick="resetChartZoom('editorialStatsChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="editorialStatsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    {* Summary of totals *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.summary"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="editorialSummary"
                                style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Editorial annual section *}
            <div id="editorial-annual" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.annualSubmissionsOverview"}
                    </h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.yearlySubmissionsTracking"}
                    </p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.annualSubmissionActivity"}</h2>
                            <button onclick="resetChartZoom('editorialAnnualChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="editorialAnnualChart"></canvas>
                            </div>
                        </div>
                    </div>

                    {* Summary of totals *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.allTimeSummary"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="editorialAnnualSummary"
                                style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Authors by country section *}
            <div id="authors-by-country" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.authorsByCountryTitle"}</h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.authorsByCountryDescription"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.authorsWorldMap"}</h2>
                            <div class="map-legend">
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #8b2635;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.authors100plus"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #c74251;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.authors50to99"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #e6677a;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.authors20to49"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #f096a6;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.authors10to19"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #f8c5cf;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.authorsLessThan10"}</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="authorsWorldMap" style="height: 500px; border-radius: 8px;"></div>
                        </div>
                    </div>

                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topCountriesByAuthors"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.country"}</th>
                                            <th>{translate key="plugins.generic.publicStats.numberOfAuthors"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="authorsByCountryTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Authors by institution section *}
            <div id="authors-by-institution" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.authorsByInstitutionTitle"}
                    </h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.authorsByInstitutionDescription"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.institutionDistribution"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="institutionStatsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topInstitutions"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th style="width: 40px;"></th>
                                            <th>{translate key="plugins.generic.publicStats.institution"}</th>
                                            <th>{translate key="plugins.generic.publicStats.numberOfAuthors"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="institutionStatsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Reviewers by country section *}
            <div id="reviewers-by-country" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.reviewersByCountryTitle"}</h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.reviewersByCountryDescription"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.reviewersWorldMap"}</h2>
                            <div class="map-legend">
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #8b2635;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.reviewers100plus"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #c74251;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.reviewers50to99"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #e6677a;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.reviewers20to49"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #f096a6;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.reviewers10to19"}</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #f8c5cf;"></div>
                                    <span>{translate key="plugins.generic.publicStats.legend.reviewersLessThan10"}</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="reviewersWorldMap" style="height: 500px; border-radius: 8px;"></div>
                        </div>
                    </div>

                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topCountriesByReviewers"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.country"}</th>
                                            <th>{translate key="plugins.generic.publicStats.numberOfReviewers"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reviewersByCountryTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Reviewers by institution section *}
            <div id="reviewers-by-institution" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.reviewersByInstitutionTitle"}
                    </h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.reviewersByInstitutionDescription"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.reviewerInstitutionDistribution"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="reviewerInstitutionStatsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topReviewerInstitutions"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th style="width: 40px;"></th>
                                            <th>{translate key="plugins.generic.publicStats.institution"}</th>
                                            <th>{translate key="plugins.generic.publicStats.numberOfReviewers"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reviewerInstitutionStatsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Reviewer list section *}
            <div id="reviewer-list" class="content-section" style="display: none;">
                <div class="year-selector-container">
                    <label for="reviewerListYearFilter">{translate key="plugins.generic.publicStats.selectYear"}</label>
                    <select id="reviewerListYearFilter" onchange="filterReviewerListByYear(this.value)">
                        <option value="">{translate key="plugins.generic.publicStats.allTime"}</option>
                        {foreach from=$availableYears item=year}
                            <option value="{$year}">{$year}</option>
                        {/foreach}
                    </select>
                </div>
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.reviewerListTitle"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.reviewerListDescription"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title" id="reviewerListCardTitle">
                                {translate key="plugins.generic.publicStats.reviewerListCardTitle"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.name"}</th>
                                            <th>{translate key="plugins.generic.publicStats.institution"}</th>
                                            <th>{translate key="plugins.generic.publicStats.country"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reviewerListTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* First decision days section *}
            <div id="first-decision-stats" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.firstDecisionDaysTitle"}{if $selectedYear}
                        ({$selectedYear}){/if}
                    </h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.firstDecisionDaysDescription"}
                    </p>
                </div>
                <div class="stats-grid">
                    {* Summary cards *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.averageDaysReviewed"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div id="firstDecisionAverageReviewed" style="text-align: center;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.averageDaysAll"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="firstDecisionAverageAll" style="text-align: center;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>

                    {* Decisions table *}
                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.recentDecisions"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.submissionId"}</th>
                                            <th>{translate key="plugins.generic.publicStats.dateSubmitted"}</th>
                                            <th>{translate key="plugins.generic.publicStats.recommendation"}</th>
                                            <th>{translate key="plugins.generic.publicStats.dateDecided"}</th>
                                            <th>{translate key="plugins.generic.publicStats.daysFirstDecision"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="firstDecisionTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Acceptance to publication days section *}
            <div id="acceptance-publication-stats" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.acceptancePublicationDaysTitle"}{if $selectedYear}
                        ({$selectedYear}){/if}
                    </h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.acceptancePublicationDaysDescription"}
                    </p>
                </div>
                <div class="stats-grid">
                    {* Summary Cards *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.avgDaysReviewedPublications"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="publicationAverageReviewed" style="text-align: center;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.avgDaysAllPublications"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div id="publicationAverageAll" style="text-align: center;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>

                    {* Publications table *}
                    <div class="stats-card" style="grid-column: span 2;">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.recentPublications"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.submissionId"}</th>
                                            <th>{translate key="plugins.generic.publicStats.dateSubmitted"}</th>
                                            <th>{translate key="plugins.generic.publicStats.datePublished"}</th>
                                            <th>{translate key="plugins.generic.publicStats.daysToPublication"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="acceptancePublicationTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Recent top downloaded articles section *}
            <div id="recent-downloads" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.mostDownloadedArticles"}
                        {translate key="plugins.generic.publicStats.last60Days"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.rankingByRecentDownloads"}
                    </p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.topArticlesByRecentDownloads"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentTopDownloadsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Recent top viewed articles section *}
            <div id="recent-views" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.mostViewedArticles"}
                        {translate key="plugins.generic.publicStats.last60Days"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.rankingByRecentViews"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.topArticlesByRecentViews"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.views"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentTopViewsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Top cited articles section *}
            <div id="top-cited" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.topCitedArticles"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.rankingByCitations"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topCitedArticles"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.authors"}</th>
                                            <th>{translate key="plugins.generic.publicStats.year"}</th>
                                            <th>{translate key="plugins.generic.publicStats.externalCitations"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topCitedArticlesTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Citation Evolution Section *}
            <div id="citation-evolution" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">
                        {translate key="plugins.generic.publicStats.citationEvolution"}{if $selectedYear}
                        ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.citationsPerYear"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.totalCitationsReceived"}
                            </h2>
                            <button onclick="resetChartZoom('citationEvolutionChart')"
                                class="reset-zoom-btn">{translate key="plugins.generic.publicStats.resetZoom"}</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="citationEvolutionChart"></canvas>
                            </div>
                        </div>
                    </div>

                    {* Cited articles table *}
                    <div class="stats-card" style="margin-top: 30px;">
                        <div class="card-header">
                            <h2 class="card-title">
                                {translate key="plugins.generic.publicStats.topCitedArticles"}{if $selectedYear}
                                ({$selectedYear}){/if}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.authors"}</th>
                                            <th>{translate key="plugins.generic.publicStats.year"}</th>
                                            <th>{translate key="plugins.generic.publicStats.externalCitations"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="citedArticlesTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Open Access statistics section *}
            <div id="open-access-stats" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.openAccessStats"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.oaDistribution"}</p>
                </div>
                <div class="stats-grid">
                    {* Summary cards *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.overview"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="oaSummaryCards"
                                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>

                    {* Distribution chart *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.oaDistribution"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="oaDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



            {* Thematic profile section *}
            <div id="thematic-profile" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.thematicProfile"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.topResearchAreas"}</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.areaDistribution"}</h2>
                            <div style=" text-align: right;">
                                <label for="thematicChartLimit" style="margin-right: 10px;">
                                    {translate key="plugins.generic.publicStats.showTop"}
                                </label>
                                <select id="thematicChartLimit" onchange="updateThematicChart(this.value)">
                                    <option value="5" selected>5</option>
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="25">25</option>
                                    <option value="30">30</option>
                                    <option value="all">{translate key="plugins.generic.publicStats.all"}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 500px;">
                                <canvas id="thematicChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topResearchAreas"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th></th>
                                            <th>{translate key="plugins.generic.publicStats.researchAreas"}</th>
                                            <th>{translate key="plugins.generic.publicStats.articleCount"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="thematicTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Citations by country map section *}
            <div id="citations-map" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.citationsByCountry"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.citationsByCountryDesc"}</p>
                </div>
                <div class="stats-card">
                    <div class="card-header">
                        <h2 class="card-title">{translate key="plugins.generic.publicStats.citationsMapTitle"}</h2>
                        <div class="map-legend">
                            <span class="legend-item">
                                <div class="legend-color" style="background: #ff6b6b;"></div>
                                <span>{translate key="plugins.generic.publicStats.legend.topQuartile"}</span>
                            </span>
                            <span class="legend-item">
                                <div class="legend-color" style="background: #4ecdc4;"></div>
                                <span>{translate key="plugins.generic.publicStats.legend.thirdQuartile"}</span>
                            </span>
                            <span class="legend-item">
                                <div class="legend-color" style="background: #45b7d1;"></div>
                                <span>{translate key="plugins.generic.publicStats.legend.secondQuartile"}</span>
                            </span>
                            <span class="legend-item">
                                <div class="legend-color" style="background: #96ceb4;"></div>
                                <span>{translate key="plugins.generic.publicStats.legend.bottomQuartile"}</span>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="citationsMapContainer" class="map-container"></div>
                    </div>
                </div>

                <div class="stats-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h2 class="card-title">{translate key="plugins.generic.publicStats.topCitingCountries"}</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="stats-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{translate key="plugins.generic.publicStats.country"}</th>
                                        <th>{translate key="plugins.generic.publicStats.citations"}</th>
                                    </tr>
                                </thead>
                                <tbody id="citationsMapTableBody">
                                    {* Populated by JavaScript *}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {* Citing  institutions section *}
            <div id="citing-journals" class="content-section" style="display: none;">
                {* Year filter - same style as global year selector *}
                <div class="year-selector-container">
                    <label
                        for="citingJournalsYearFilter">{translate key="plugins.generic.publicStats.selectYear"}</label>
                    <select id="citingJournalsYearFilter" onchange="filterCitingJournalsByYear(this.value)">
                        <option value="all">{translate key="plugins.generic.publicStats.allTime"}</option>
                        {* Years will be populated by JavaScript *}
                    </select>
                </div>

                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.citingJournals"}</h1>
                    <p class="content-subtitle">{translate key="plugins.generic.publicStats.citingJournalsDesc"}</p>
                </div>

                <div class="stats-grid">
                    {* Chart *}
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.topCitingJournals"}
                            </h2>
                            <div style="text-align: right;">
                                <label for="journalsChartLimit" style="margin-right: 10px;">
                                    {translate key="plugins.generic.publicStats.showTop"}
                                </label>
                                <select id="journalsChartLimit" onchange="updateJournalsChart(this.value)">
                                    <option value="5" selected>5</option>
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="20">20</option>
                                    <option value="25">25</option>
                                    <option value="30">30</option>
                                    <option value="all">{translate key="plugins.generic.publicStats.all"}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 500px;">
                                <canvas id="citingJournalsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    {* Table *}
                    <div class="stats-card" style="margin-top: 30px;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.allCitingJournals"}
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table" id="citingJournalsTable"
                                    style="table-layout: fixed; width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th style="width: 50px;">#</th>
                                            <th style="width: 40px;"></th>
                                            <th>{translate key="plugins.generic.publicStats.journalName"}</th>
                                            <th style="width: 120px;">{translate key="plugins.generic.publicStats.issn"}
                                            </th>
                                            <th style="width: 80px;">
                                                {translate key="plugins.generic.publicStats.citations"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="citingJournalsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="author-individual-stats" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">{translate key="plugins.generic.publicStats.authorIndividualStats"}</h1>
                    <p class="content-subtitle">
                        {translate key="plugins.generic.publicStats.authorIndividualStatsDescription"}</p>
                </div>

                {* Filters *}
                <div class="year-selector-container">
                    <div class="filter-group">
                        <div>
                            <label
                                for="authorSelector">{translate key="plugins.generic.publicStats.selectAuthor"}</label>
                            <select id="authorSelector" style="width: 300px" onchange="changeAuthor(this.value)">
                                <option value="">{translate key="plugins.generic.publicStats.selectAuthorPlaceholder"}
                                </option>
                                {* Populated by JavaScript *}
                            </select>
                        </div>
                    </div>
                </div>

                {* Author info card *}
                <div id="authorInfoCard" style="display: none;">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.authorInformation"}</h2>
                        </div>
                        <div class="card-body">
                            <div id="authorInfoContent">
                                {* Populated by JavaScript *}
                            </div>
                        </div>
                    </div>
                </div>

                {* Summary statistics *}
                <div id="authorSummarySection" style="display: none;">
                    <div class="stats-grid"
                        style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                        {* Total articles *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">{translate key="plugins.generic.publicStats.totalArticles"}</h2>
                            </div>
                            <div class="card-body">
                                <div id="authorTotalArticles"
                                    style="text-align: center; font-size: 48px; font-weight: bold; color: #8b2635;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                        {* Total downloads *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">{translate key="plugins.generic.publicStats.totalDownloads"}</h2>
                            </div>
                            <div class="card-body">
                                <div id="authorTotalDownloads"
                                    style="text-align: center; font-size: 48px; font-weight: bold; color: #36a2eb;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                        {* Total views *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">{translate key="plugins.generic.publicStats.totalViews"}</h2>
                            </div>
                            <div class="card-body">
                                <div id="authorTotalViews"
                                    style="text-align: center; font-size: 48px; font-weight: bold; color: #4ecdc4;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                        {* Avg downloads *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    {translate key="plugins.generic.publicStats.avgDownloadsPerArticle"}</h2>
                            </div>
                            <div class="card-body">
                                <div id="authorAvgDownloads"
                                    style="text-align: center; font-size: 36px; font-weight: bold; color: #ff6b6b;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                        {* Avg views *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">{translate key="plugins.generic.publicStats.avgViewsPerArticle"}
                                </h2>
                            </div>
                            <div class="card-body">
                                <div id="authorAvgViews"
                                    style="text-align: center; font-size: 36px; font-weight: bold; color: #45b7d1;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                        {* Total accesses *}
                        <div class="stats-card">
                            <div class="card-header">
                                <h2 class="card-title">{translate key="plugins.generic.publicStats.totalAccesses"}</h2>
                            </div>
                            <div class="card-body">
                                <div id="authorTotalAccesses"
                                    style="text-align: center; font-size: 48px; font-weight: bold; color: #2ecc71;">
                                    {* Populated by JavaScript *}
                                </div>
                            </div>
                        </div>

                    </div>
                </div>


                {* Articles table *}
                <div id="authorArticlesSection" style="display: none;">
                    <div class="stats-card" style="margin-top: 30px;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.authorArticles"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.articleTitle"}</th>
                                            <th>{translate key="plugins.generic.publicStats.datePublished"}</th>
                                            <th>{translate key="plugins.generic.publicStats.section"}</th>
                                            <th>{translate key="plugins.generic.publicStats.downloads"}</th>
                                            <th>{translate key="plugins.generic.publicStats.views"}</th>
                                            <th>{translate key="plugins.generic.publicStats.total"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="authorArticlesTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {* Co-Authors section *}
                <div id="authorCoAuthorsSection" style="display: none;">
                    <div class="stats-card" style="margin-top: 30px;">
                        <div class="card-header">
                            <h2 class="card-title">{translate key="plugins.generic.publicStats.frequentCoAuthors"}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>{translate key="plugins.generic.publicStats.coAuthorName"}</th>
                                            <th>{translate key="plugins.generic.publicStats.affiliation"}</th>
                                            <th>{translate key="plugins.generic.publicStats.collaborations"}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="authorCoAuthorsTableBody">
                                        {* Populated by JavaScript *}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{include file="frontend/components/footer.tpl"}