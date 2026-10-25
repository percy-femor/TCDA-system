(function () {
  const GHANA_BOUNDS = [[4.539, -3.261], [11.175, 1.199]];

  const roots = document.querySelectorAll('[data-geofence]');
  if (!roots.length) {
    return;
  }

  function inGhana(lat, lng) {
    return lat >= GHANA_BOUNDS[0][0] && lat <= GHANA_BOUNDS[1][0]
      && lng >= GHANA_BOUNDS[0][1] && lng <= GHANA_BOUNDS[1][1];
  }

  function loadLeaflet() {
    if (window.L) {
      return Promise.resolve();
    }
    return new Promise(function (resolve, reject) {
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(css);
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  function parseRing(raw) {
    if (!raw) {
      return [];
    }
    try {
      const data = JSON.parse(raw);
      const geom = data.type === 'Feature' ? data.geometry : data;
      const ring = (geom && geom.coordinates && geom.coordinates[0]) || [];
      return ring
        .map(function (p) { return { lng: Number(p[0]), lat: Number(p[1]) }; })
        .filter(function (p, i, all) {
          if (i === all.length - 1 && all.length > 1 && p.lat === all[0].lat && p.lng === all[0].lng) {
            return false;
          }
          return !isNaN(p.lat) && !isNaN(p.lng);
        });
    } catch (e) {
      return [];
    }
  }

  function toGeoJson(vertices) {
    if (vertices.length < 3) {
      return '';
    }
    const ring = vertices.map(function (v) { return [v.lng, v.lat]; });
    ring.push([vertices[0].lng, vertices[0].lat]);
    return JSON.stringify({ type: 'Polygon', coordinates: [ring] });
  }

  function areaHa(vertices) {
    if (vertices.length < 3) {
      return 0;
    }
    const mid = vertices.reduce(function (s, v) { return s + v.lat; }, 0) / vertices.length;
    const mLat = 111320;
    const mLng = 111320 * Math.cos(mid * Math.PI / 180);
    let area = 0;
    const pts = vertices.concat([vertices[0]]);
    for (let i = 0; i < pts.length - 1; i++) {
      const x1 = pts[i].lng * mLng;
      const y1 = pts[i].lat * mLat;
      const x2 = pts[i + 1].lng * mLng;
      const y2 = pts[i + 1].lat * mLat;
      area += (x1 * y2) - (x2 * y1);
    }
    return Math.abs(area) / 2 / 10000;
  }

  function boot(root) {
    const readonly = root.getAttribute('data-readonly') === '1';
    const geoField = root.querySelector('[data-geojson]');
    const mapEl = root.querySelector('[data-map]');
    const summary = root.querySelector('[data-summary]');
    const startLat = Number(root.getAttribute('data-start-lat') || 7.95);
    const startLng = Number(root.getAttribute('data-start-lng') || -1.02);
    const startZoom = Number(root.getAttribute('data-start-zoom') || 7);
    const region = root.getAttribute('data-region') || '';
    let vertices = parseRing(geoField ? geoField.value : '');
    let map;
    let layer;

    function setSummary(text) {
      if (summary) {
        summary.textContent = text;
      }
    }

    function syncField() {
      if (geoField && !readonly) {
        geoField.value = toGeoJson(vertices);
      }
      if (!vertices.length) {
        setSummary(region
          ? 'Ghana map · ' + region + '. Find the farm, then mark each corner.'
          : 'Ghana map only. Find the farm, then mark each corner.');
      } else if (vertices.length < 3) {
        setSummary(vertices.length + ' corner(s) in Ghana. Add at least three, walking around the farm.');
      } else {
        setSummary(vertices.length + ' corners in Ghana · about ' + areaHa(vertices).toFixed(2) + ' ha geofenced.');
      }
      if (map && window.L) {
        if (layer) {
          map.removeLayer(layer);
        }
        const latlngs = vertices.map(function (v) { return [v.lat, v.lng]; });
        layer = window.L.layerGroup();
        latlngs.forEach(function (ll, i) {
          window.L.circleMarker(ll, { radius: 6, color: '#1f4a36', fillOpacity: 1 })
            .bindTooltip('Corner ' + (i + 1))
            .addTo(layer);
        });
        if (latlngs.length >= 3) {
          window.L.polygon(latlngs, { color: '#1f4a36', weight: 2, fillOpacity: 0.25 }).addTo(layer);
        } else if (latlngs.length === 2) {
          window.L.polyline(latlngs, { color: '#1f4a36' }).addTo(layer);
        }
        layer.addTo(map);
      }
    }

    function addVertex(lat, lng) {
      if (readonly) {
        return;
      }
      if (!inGhana(lat, lng)) {
        setSummary('That point is outside Ghana. The geofence stays inside the country.');
        return;
      }
      vertices.push({ lat: Number(lat), lng: Number(lng) });
      syncField();
    }

    function flyTo(lat, lng, zoom) {
      if (!map) {
        return;
      }
      map.setView([lat, lng], zoom || 16);
    }

    loadLeaflet().then(function () {
      map = window.L.map(mapEl, {
        maxBounds: GHANA_BOUNDS,
        maxBoundsViscosity: 1,
        minZoom: 6,
      });
      if (vertices[0]) {
        map.setView([vertices[0].lat, vertices[0].lng], 16);
      } else {
        map.setView([startLat, startLng], startZoom);
      }
      window.L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri · Ghana only',
        maxZoom: 18,
        bounds: GHANA_BOUNDS,
      }).addTo(map);
      window.L.rectangle(GHANA_BOUNDS, {
        color: '#c4a24a',
        weight: 1,
        fill: false,
        dashArray: '4 4',
        interactive: false,
      }).addTo(map);
      if (!readonly) {
        map.on('click', function (e) {
          addVertex(e.latlng.lat, e.latlng.lng);
        });
      }
      syncField();
      if (vertices.length >= 3) {
        map.fitBounds(vertices.map(function (v) { return [v.lat, v.lng]; }), { padding: [24, 24] });
      }
    }).catch(function () {
      mapEl.textContent = 'Map tiles need a connection. You can still add GPS corners on the farm.';
      syncField();
    });

    const locateBtn = root.querySelector('[data-locate]');
    if (locateBtn) {
      locateBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
          setSummary('This browser has no GPS.');
          return;
        }
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            if (!inGhana(pos.coords.latitude, pos.coords.longitude)) {
              setSummary('Your GPS is outside Ghana. Move onto the farm first.');
              return;
            }
            flyTo(pos.coords.latitude, pos.coords.longitude, 17);
            setSummary('Map centred on your GPS. Zoom to the farm edge, then add corners.');
          },
          function (err) {
            setSummary(err.message);
          },
          { enableHighAccuracy: true, timeout: 20000 }
        );
      });
    }

    const gpsBtn = root.querySelector('[data-gps]');
    if (gpsBtn) {
      gpsBtn.addEventListener('click', function () {
        if (!navigator.geolocation) {
          setSummary('This browser has no GPS.');
          return;
        }
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            addVertex(pos.coords.latitude, pos.coords.longitude);
            flyTo(pos.coords.latitude, pos.coords.longitude, 17);
          },
          function (err) {
            setSummary(err.message);
          },
          { enableHighAccuracy: true, timeout: 20000 }
        );
      });
    }

    const undoBtn = root.querySelector('[data-undo]');
    if (undoBtn) {
      undoBtn.addEventListener('click', function () {
        vertices.pop();
        syncField();
      });
    }

    const clearBtn = root.querySelector('[data-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        vertices = [];
        syncField();
      });
    }

    const searchBtn = root.querySelector('[data-place-search]');
    const searchInput = root.querySelector('[data-place-q]');
    const resultsEl = root.querySelector('[data-place-results]');
    function searchGhana() {
      const q = (searchInput && searchInput.value || '').trim();
      if (!q || !resultsEl) {
        return;
      }
      resultsEl.hidden = false;
      resultsEl.innerHTML = '<li class="muted">Searching Ghana…</li>';
      const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&countrycodes=gh&q=' + encodeURIComponent(q);
      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (places) {
          if (!places.length) {
            resultsEl.innerHTML = '<li class="muted">No Ghana place found for that name.</li>';
            return;
          }
          resultsEl.innerHTML = '';
          places.forEach(function (place) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'link-btn';
            btn.textContent = place.display_name;
            btn.addEventListener('click', function () {
              const lat = Number(place.lat);
              const lng = Number(place.lon);
              if (!inGhana(lat, lng)) {
                setSummary('That result is outside Ghana.');
                return;
              }
              flyTo(lat, lng, 15);
              setSummary('Zoomed to ' + place.display_name + '. Click the farm corners.');
              resultsEl.hidden = true;
            });
            li.appendChild(btn);
            resultsEl.appendChild(li);
          });
        })
        .catch(function () {
          resultsEl.innerHTML = '<li class="muted">Place search needs a connection.</li>';
        });
    }
    if (searchBtn) {
      searchBtn.addEventListener('click', searchGhana);
    }
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          searchGhana();
        }
      });
    }

    const form = root.closest('form');
    if (form && !readonly) {
      form.addEventListener('submit', function (e) {
        syncField();
        if (vertices.length < 3) {
          e.preventDefault();
          setSummary('Walk or click at least three corners of the farm in Ghana.');
        }
      });
    }
  }

  roots.forEach(boot);
})();
