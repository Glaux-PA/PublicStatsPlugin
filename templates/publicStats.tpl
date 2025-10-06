{**
 * templates/publicStats.tpl
 * Public Statistics Display Template
 *}
{include file="frontend/components/header.tpl"}

{* External Dependencies *}
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

{* Initialize data *}
<script>
    {literal}
        var statsData = {
            monthlyStats: {/literal}{$monthlyStats}{literal},
            topArticlesByDownloads: {/literal}{$topDownloadedArticles}{literal},
            topArticlesByViews: {/literal}{$topViewedArticles}{literal},
            countryData: {/literal}{$countryData}{literal},
            annualStats: {/literal}{$annualStats}{literal},
            issueStats: {/literal}{$issueStats}{literal},
            sectionStats: {/literal}{$sectionStats}{literal},
            recentTopDownloaded: {/literal}{$recentTopDownloaded}{literal},
            recentTopViewed: {/literal}{$recentTopViewed}{literal},
            editorialStats: {/literal}{$editorialStats}{literal}
        };
    {/literal}
</script>

<div class="page page_statistics">
    <div class="container">

        <div id="loadingIndicator" class="loading-indicator" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Loading statistics...</p>
        </div>
        {* Sidebar Navigation *}
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>Statistics</h1>
            </div>

            {* General Statistics Section *}
            <div class="sidebar-section">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('general')">
                        <span>📊 General Statistics</span>
                        <span class="section-toggle">▼</span>
                    </div>
                </div>
                <div class="section-content" id="general-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('monthly-trends')">Monthly Trends</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('annual-trends')">Annual Trends</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('general-downloads')">Contributions (Downloads)
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('general-views')">Contributions (Views)</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('general-sections')">Sections</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('general-issues')">Issues</div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('geographic-distribution')">Geographic
                                distribution</div>
                        </li>
                    </ul>
                </div>
            </div>

            {* Editorial Statistics Section *}
            <div class="sidebar-section">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('editorial')">
                        <span>📝 Editorial Statistics</span>
                        <span class="section-toggle">▼</span>
                    </div>
                </div>
                <div class="section-content section-collapsed" id="editorial-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('editorial-submissions')">Monthly contributions
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            {* Article Reach Section *}
            <div class="sidebar-section">
                <div class="section-header">
                    <div class="section-title" onclick="toggleSection('reach')">
                        <span>🌍 Article Reach</span>
                        <span class="section-toggle">▼</span>
                    </div>
                </div>
                <div class="section-content section-collapsed" id="reach-content">
                    <ul class="sidebar-menu">
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('recent-downloads')">Most Downloaded (60 days)
                            </div>
                        </li>
                        <li class="menu-item">
                            <div class="menu-link" onclick="showSection('recent-views')">Most Viewed (60 days)</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {* Main Content Area *}
        <div class="main-content">
            {* Year Selector *}
            <div class="year-selector-container">
                <label for="yearSelector">Select Year:</label>
                <select id="yearSelector" onchange="changeYear(this.value)">
                    <option value="">All Time</option>
                    {foreach from=$availableYears item=year}
                        <option value="{$year}" {if $selectedYear == $year}selected{/if}>{$year}</option>
                    {/foreach}
                </select>
            </div>

            {* Monthly Trends Section *}
            <div id="monthly-trends" class="content-section">
                <div class="content-header">
                    <h1 class="content-title">Monthly overview{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Downloads and views over the year</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Activity</h2>
                            <button onclick="resetChartZoom('monthlyStatsChart')" class="reset-zoom-btn">Reset
                                Zoom</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Annual Trends Section *}
            <div id="annual-trends" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Annual overview</h1>
                    <p class="content-subtitle">Yearly downloads and views since journal inception</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Annual activity</h2>
                            <button onclick="resetChartZoom('annualStatsChart')" class="reset-zoom-btn">Reset
                                Zoom</button>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="annualStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Geographic Distribution Section *}
            <div id="geographic-distribution" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Geographic distribution</h1>
                    <p class="content-subtitle">Worldwide distribution of article access</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">World access map</h2>
                            <div class="map-legend">
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #ff6b6b;"></div>
                                    <span>Over 1000</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #4ecdc4;"></div>
                                    <span>500-1000</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #45b7d1;"></div>
                                    <span>100-500</span>
                                </span>
                                <span class="legend-item">
                                    <div class="legend-color" style="background: #96ceb4;"></div>
                                    <span>Under 100</span>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="worldMap" class="map-container"></div>
                        </div>
                    </div>
                </div>
            </div>

            {* Top Downloaded Articles Section *}
            <div id="general-downloads" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Most downloaded articles{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Ranking of articles by download count</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Top articles by downloads</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>ARTICLE TITLE</th>
                                            <th>DOWNLOADS</th>
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

            {* Top Viewed Articles Section *}
            <div id="general-views" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Most viewed articles{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Ranking of articles by view count</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Top articles by views</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>ARTICLE TITLE</th>
                                            <th>VIEWS</th>
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

            {* Issues Section *}
            <div id="general-issues" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Downloads by Issue{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Distribution of downloads across journal issues</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Issue distribution</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="issueStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Downloads by issue{if $selectedYear} ({$selectedYear}){/if}</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th>ISSUE</th>
                                            <th>VIEWS</th>
                                            <th>ARTICLES</th>
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

            {* Sections Section *}
            <div id="general-sections" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Downloads by section{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Distribution of downloads across journal sections</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Section distribution</h2>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="sectionStatsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Downloads by section</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"></th>
                                            <th>SECTION</th>
                                            <th>DOWNLOADS</th>
                                            <th>ARTICLES</th>
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

            {* Editorial Submissions Section *}
            <div id="editorial-submissions" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Submissions Overview{if $selectedYear} ({$selectedYear}){/if}</h1>
                    <p class="content-subtitle">Monthly tracking of received, declined, published, and in-process
                        submissions</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Submission Activity</h2>
                            <button onclick="resetChartZoom('editorialStatsChart')" class="reset-zoom-btn">Reset
                                Zoom</button>
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
                            <h2 class="card-title">Summary</h2>
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

            {* Recent Top Downloaded Articles Section *}
            <div id="recent-downloads" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Most downloaded articles (Last 60 days)</h1>
                    <p class="content-subtitle">Ranking of articles by download count in the last 60 days</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Top articles by recent downloads</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>ARTICLE TITLE</th>
                                            <th>DOWNLOADS</th>
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

            {* Recent Top Viewed Articles Section *}
            <div id="recent-views" class="content-section" style="display: none;">
                <div class="content-header">
                    <h1 class="content-title">Most viewed articles (Last 60 days)</h1>
                    <p class="content-subtitle">Ranking of articles by view count in the last 60 days</p>
                </div>
                <div class="stats-grid">
                    <div class="stats-card">
                        <div class="card-header">
                            <h2 class="card-title">Top articles by recent views</h2>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table class="stats-table">
                                    <thead>
                                        <tr>
                                            <th>ARTICLE TITLE</th>
                                            <th>VIEWS</th>
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
        </div>
    </div>
</div>

{include file="frontend/components/footer.tpl"}