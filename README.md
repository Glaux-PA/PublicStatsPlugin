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

| Setting            | Description                                                    |
| ------------------ | -------------------------------------------------------------- |
| **OpenAlex Email** | Contact email for OpenAlex API (see note below)                |
| **Primary Color**  | Choose a color that matches your journal's branding (optional) |

![Plugin Settings](screenshots/settings.png)

4. Click **Save**

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

> **Note**: Citation data from OpenAlex may take a few moments to load on first access as it is fetched and cached.

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

### Customization

You can customize the interface color theme in the plugin settings. The selected color will be applied to:

- Navigation menu active states
- Charts and graphs
- Links and buttons
- Metric highlights

## Usage

Once installed, the statistics page is accessible at:

```
https://your-journal.com/publicStats/total
```

The interface includes sections for:

| Section               | Description                                      |
| --------------------- | ------------------------------------------------ |
| Monthly Trends        | Views and downloads over time                    |
| Publication Stats     | Articles published and submission status         |
| Article Statistics    | Top downloaded and viewed articles               |
| Geographic Stats      | Reader distribution by country                   |
| Issue & Section Stats | Metrics by journal issue and section             |
| Authors & Reviewers   | Contributor statistics and affiliations          |
| Editorial Metrics     | Processing times and decisions                   |
| Impact Statistics     | Citations, citing journals, and thematic profile |
| Open Access           | OA type distribution                             |

![Geographic Statistics](screenshots/geographic.png)

## Data Export

All sections support CSV export. Click the export button in any section to download the data.

## Support

For issues or feature requests, please contact the plugin maintainer.

## License

This plugin is licensed under the GNU General Public License v3.0.
