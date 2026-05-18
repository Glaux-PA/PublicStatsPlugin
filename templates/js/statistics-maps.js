/**
 * Public Statistics - Leaflet map renderers.
 *
 * Registers window.PublicStats.Maps. Depends on Leaflet (global L),
 * window.PublicStatsHelpers (escapeHtml, Utils, ChartInstances) and
 * window.PublicStats.API (for the citations map).
 */
(function () {
  "use strict";

  const { escapeHtml, Utils, ChartInstances } = window.PublicStatsHelpers;
  const PS = (window.PublicStats = window.PublicStats || {});

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
                            <h3 class="ps-map-tooltip-title">${escapeHtml(
                              country.country_name
                            )}</h3>
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
                            <h3 class="ps-map-tooltip-title">${escapeHtml(
                              country.country_name
                            )}</h3>
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
                            <h3 class="ps-map-tooltip-title">${escapeHtml(
                              country.country_name
                            )}</h3>
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
        const data = await PS.API.getCitationsByCountry();

        if (data && data.is_computing) {
          const base = i18n.computingPlaceholder;
          const p = data.progress;
          const msg =
            p && p.total
              ? `${base} ${parseInt(p.processed, 10) || 0}/${
                  parseInt(p.total, 10) || 0
                }`
              : base;
          document.getElementById(
            "citationsMapTableBody"
          ).innerHTML = `<tr><td colspan="3" class="ps-empty-message">${msg}</td></tr>`;
          return;
        }

        if (!data || data.length === 0) {
          document.getElementById(
            "citationsMapTableBody"
          ).innerHTML = `<tr><td colspan="3" class="ps-empty-message">${i18n.noCitationMapData}</td></tr>`;
          return;
        }

        this.renderCitationsMap(data);
        this.renderCitationsTable(data);
      } catch (error) {
        if (error && error.code === "STALE_REQUEST") return;
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

  PS.Maps = Maps;
})();
