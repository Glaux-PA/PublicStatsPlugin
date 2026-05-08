# Public Statistics Plugin for OJS 3.4

A comprehensive statistics plugin for Open Journal Systems that provides public-facing metrics and analytics for your journal.

![Plugin Overview](screenshots/overview.png)

## Features

- **Usage statistics**: Views and downloads by article, issue, section, and country
- **Author & reviewer metrics**: Geographic distribution and institutional affiliation
- **Editorial analytics**: Processing times, decision rates, and workflow metrics
- **Citation data**: Integration with OpenAlex for citation counts, citing journals, and thematic profiles
- **Open Access statistics**: Distribution of OA types across publications
- **Interactive visualizations**: Charts, maps, and filterable tables
- **CSV export**: Export any dataset for further analysis
- **Multilingual**: Available in English, Spanish, and Catalan
- **Customizable**: Configurable primary color theme

![Citation Statistics](screenshots/citations.png)

## Requirements

- OJS 3.4.0
- PHP 8.1 or higher

## Installation

### Step 1: Download and Extract

Download the plugin and extract it to your OJS plugins folder. The final structure should be:

```
ojs/
└── plugins/
    └── generic/
        └── publicStats/
            ├── controllers/
            ├── classes/
            ├── helpers/
            ├── jobs/
            ├── services/
            ├── templates/
            ├── locale/
            ├── PublicStatsPlugin.php
            ├── PublicStatsSettingsForm.php
            └── version.xml
```

### Step 2: Enable the Plugin

1. Log in to your OJS installation as an administrator
2. Navigate to **Settings → Website → Plugins**
3. Find **Generic Plugins**
4. Find "Public Statistics" in the list and check the box to enable it

![Enable Plugin](screenshots/enable-plugin.png)

### Step 3: Configure Plugin Settings

1. Click the arrow next to the plugin name to expand options
2. Click **Settings**
3. Configure the following:

| Setting                        | Description                                                                                         |
| ------------------------------ | --------------------------------------------------------------------------------------------------- |
| **OpenAlex Email**             | Contact email for OpenAlex API (see note below)                                                     |
| **Active statistics sections** | Show or hide each subsection independently — see [Enable/disable sections](#enabledisable-sections) |
| **Primary Color**              | Choose a color that matches your journal's branding (optional)                                      |

![Plugin Settings](screenshots/settings.png)

4. Click **Ok**

#### About the OpenAlex Email

The email field is **optional but recommended**. Here's what you need to know:

- **No account needed**: You don't need to register or create an account with OpenAlex. It's simply a contact email included in API requests.
- **Why provide it**: OpenAlex separates users into two pools: the _polite pool_ (with email) and the _common pool_ (without). The polite pool gets **faster and more consistent response times**.
- **What happens without it**: The plugin will still work, but API responses may be slower or less consistent during peak usage times.
- **Recommendation**: Use your journal's contact email or an institutional email.

> **Privacy**: The email is only sent to OpenAlex in API requests. It is not shared with third parties or used for marketing.

### Step 4: Add Navigation Menu Link

To make the statistics page accessible to your readers:

1. Go to **Settings → Website → Setup → Navigation**
2. Click **Add Item**
3. In **Navigation Menu Item Type**, select **Statistics**
4. Enter a **Title** for the menu item (e.g., "Statistics" or "Journal Metrics")
5. Click **Save**

![Add Navigation Item](screenshots/add-nav-item.png)

6. Now click **Edit** on the menu where you want the link to appear (e.g., "Primary Navigation Menu")
7. Find your new item in the **Unassigned** list
8. Drag it to the **Assigned** list in your preferred position
9. Click **Save**

![Navigation Menu](screenshots/navigation-menu.png)

### Step 5: Verify Installation

Visit your journal's statistics page to verify everything works correctly:

```
https://your-journal.com/publicStats/total
```

You should see the statistics dashboard with your journal's data.

> **Note**: On the first visit, OpenAlex-powered sections (top cited, citation evolution, open access stats, thematic profile, citations map, citing journals) may show a loading state while the OJS queue processes them. See the [OpenAlex Integration](#openalex-integration) section for details.

## Configuration

### OpenAlex Integration

The plugin uses [OpenAlex](https://openalex.org/) to retrieve citation metrics. OpenAlex is a free, open catalog of scholarly works and citations.

Features powered by OpenAlex:

- Citation counts and evolution
- Citing journals analysis
- Thematic profile (research topics)
- Citations by country
- Open Access status

Data is fetched automatically and cached for 24 hours to optimize performance.

> **First-load notice**: building OpenAlex aggregates for a whole journal can take several minutes on large catalogues. The plugin offloads these calculations to the OJS background-jobs queue, so the affected sections — top cited, citation evolution, open access stats, thematic profile, citations map, citing journals, citing institutions, and the enriched totals on the overview — will show a loading state until the queue worker finishes. Make sure the `acron` plugin or a scheduled queue worker is running. Subsequent visits read from cache and are instant.

### Enable/disable sections

Every subsection listed in [Usage](#usage) can be turned on or off independently:

1. Open **Settings → Website → Plugins → Public Statistics → Settings**
2. Under **Active statistics sections**, expand any group
3. Tick or untick each subsection (use the group checkbox to toggle all of its children at once)
4. Click **Ok**

Disabled subsections are hidden from both the sidebar and the page output, and their data is never queried — disabling features you don't use also reduces the load on your database. New subsections introduced in future plugin updates appear automatically the next time settings are opened.

### Customization

You can customize the interface color theme in the plugin settings. The selected color will be applied to:

- Active sidebar menu items
- Loading indicator
- Reset-zoom buttons on charts
- Metric values and table links
- Export buttons

The form shows a live preview of the four shades that will be derived from your chosen colour (light / primary / dark / darker).

## Usage

Once installed, the statistics page is accessible at:

```
https://your-journal.com/publicStats/total
```

The dashboard groups statistics into four categories. Each subsection can be enabled or disabled individually from the plugin settings — see [Enable/disable sections](#enabledisable-sections) below.

### General

- **Monthly trends** — views and downloads aggregated by month
- **Annual trends** — same data aggregated by year
- **Top downloads** — most-downloaded articles
- **Top views** — most-viewed articles
- **By section** — distribution across journal sections
- **By issue** — metrics for each issue
- **Language distribution** — published articles broken down by language (with per-issue filter)
- **Language trends** — yearly evolution of articles per language (zoomable chart)
- **Geographic distribution** — readers by country (world map + table)

### Editorial

- **Author dashboard** — per-author publications, downloads and views
- **Monthly submissions** — received / published / declined / in-process timeline
- **Annual submissions** — same data aggregated by year
- **Authors by country / institution** — contributor distribution
- **Reviewers by country / institution** — reviewer distribution
- **Reviewer list** — alphabetical public list of reviewers who completed reviews each year (FECYT-aligned acknowledgment)
- **First-decision time** — average days from submission to first decision
- **Acceptance-to-publication time** — average days from accepted to published

### Reach

- **Most downloaded (last 60 days)**
- **Most viewed (last 60 days)**

### Impact _(requires OpenAlex)_

- **Top cited** — most-cited articles, all-time and per year
- **Citation evolution** — citations received per year
- **Open Access stats** — Diamond / Gold / Hybrid / Green / Bronze / Closed breakdown
- **Thematic profile** — research areas inferred from OpenAlex topics
- **Citations by country** — geographic origin of citations (map + table)
- **Citing journals** — top journals citing your articles, with per-article drill-down

![Geographic Statistics](screenshots/geographic.png)

## Data Export

All sections support CSV export. Click the export button in any section to download the data.

## Support

For issues or feature requests, please contact the plugin maintainer.

## For developers

The plugin follows the standard OJS 3.4 layout with a service-oriented backend and a modular frontend.

### Backend

- `controllers/PublicStatisticsHandler.php` — page handler. Each public method is a JSON or HTML endpoint registered on the `publicStats` page route.
- `controllers/traits/*Trait.php` — endpoint groups (article rankings, editorial, author/reviewer, impact, CSV exports). Traits are thin HTTP wrappers; logic lives in services.
- `services/*.php` — business logic, one service per domain (statistics, articles, editorial, language, OpenAlex, CSV export).
- `jobs/*.php` — Laravel queue jobs for heavy OpenAlex aggregations. They run via the OJS queue worker (`acron` plugin or `php tools/jobs.php run`).
- `classes/PublicStatsConstants.php` — central registry of subsection IDs and their group. Adding a subsection here is enough for it to appear in the settings form.
- `classes/Logger.php` — wrapper over `error_log` that prefixes every line with `[publicStats]`. Use `Logger::error($msg, $exception)` and `Logger::warning($msg)`; never call `error_log` directly.

### Frontend

The dashboard JS is split across seven files in `templates/js/`:

| File                    | Responsibility                                 |
| ----------------------- | ---------------------------------------------- |
| `statistics-helpers.js` | Shared utilities (escapeHtml, ChartConfig, …)  |
| `statistics-api.js`     | API client + CSV export buttons                |
| `statistics-charts.js`  | Chart.js renderers                             |
| `statistics-tables.js`  | Table builders                                 |
| `statistics-maps.js`    | Leaflet renderers                              |
| `statistics-impact.js`  | Author dashboard + Impact (OpenAlex) renderers |
| `statistics.js`         | Orchestrator: navigation, year changes, init   |

Each module attaches itself to `window.PublicStats.*`; the orchestrator destructures it and wires section navigation. Load order is enforced from `PublicStatisticsHandler::setupAssets()`.

### Adding a new section

1. Add the subsection id to `PublicStatsConstants::SUBSECTIONS` under the right group.
2. Implement the data layer in `services/`.
3. Add an endpoint method on `PublicStatisticsHandler`.
4. Register an `API.get<Foo>` wrapper in `statistics-api.js` and a renderer (chart, map or table) in the matching frontend module.
5. Add the sidebar item and the content section to `templates/publicStats.tpl`, plus a handler entry in `Navigation.initializeSectionContent` (`statistics.js`).
6. Add translation keys to `locale/<code>/locale.po`.

### Adding a translation

Copy `locale/en/locale.po` to `locale/<your-code>/locale.po` and translate the `msgstr` lines. The locale code must match an OJS-supported locale (e.g. `pt_BR`, `de`, `fr_CA`).

## License

This plugin is licensed under the GNU General Public License v3.0.
