(() => {
  const DB_NAME = 'tcda-field';
  const DB_VERSION = 1;
  const STORES = ['meta', 'farmers', 'plots', 'batches', 'outbox'];

  let db;
  let state = {
    session: null,
    view: 'farmers',
    farmers: [],
    plots: [],
    batches: [],
    outbox: [],
    aggregators: [],
    gps: null,
    fenceCorners: [],
    lastEmail: '',
  };

  function uuid() {
    return crypto.randomUUID();
  }

  function openDb() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = () => {
        const database = req.result;
        STORES.forEach((name) => {
          if (!database.objectStoreNames.contains(name)) {
            const store = database.createObjectStore(name, { keyPath: name === 'outbox' || name === 'meta' ? 'id' : 'client_uuid' });
            if (name === 'outbox') store.createIndex('status', 'status');
          }
        });
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror = () => reject(req.error);
    });
  }

  function tx(store, mode = 'readonly') {
    return db.transaction(store, mode).objectStore(store);
  }

  function reqToPromise(request) {
    return new Promise((resolve, reject) => {
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function getAll(store) {
    return reqToPromise(tx(store).getAll());
  }

  async function put(store, value) {
    return reqToPromise(tx(store, 'readwrite').put(value));
  }

  async function getMeta(id) {
    return reqToPromise(tx('meta').get(id));
  }

  async function setMeta(id, value) {
    return put('meta', { id, ...value });
  }

  async function deleteMeta(id) {
    return reqToPromise(tx('meta', 'readwrite').delete(id));
  }

  async function clearStore(name) {
    return reqToPromise(tx(name, 'readwrite').clear());
  }

  function escapeAttr(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;');
  }

  async function refreshLocal() {
    state.farmers = await getAll('farmers');
    state.plots = await getAll('plots');
    state.batches = await getAll('batches');
    state.outbox = (await getAll('outbox')).sort((a, b) => a.happened_at.localeCompare(b.happened_at));
    state.session = await getMeta('session');
    state.aggregators = state.session?.aggregators || [];
    state.lastEmail = (await getMeta('last_email'))?.email || state.lastEmail || '';
  }

  async function queue(type, clientUuid, payload) {
    const op = {
      id: uuid(),
      op_id: uuid(),
      type,
      client_uuid: clientUuid,
      happened_at: new Date().toISOString(),
      payload,
      status: 'pending',
    };
    await put('outbox', op);
    return op;
  }

  function online() {
    return navigator.onLine;
  }

  function setStatus(message, warn = false) {
    const el = document.getElementById('status');
    el.hidden = !message;
    el.textContent = message || '';
    el.classList.toggle('warn', warn);
  }

  function updateNet() {
    document.getElementById('net-label').textContent = online()
      ? 'Online — sync when ready'
      : 'No signal — capturing locally';
  }

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.matchMedia('(display-mode: fullscreen)').matches
      || window.navigator.standalone === true;
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !/windows|android/i.test(navigator.userAgent);
  }

  function wantsInstall() {
    return new URLSearchParams(window.location.search).get('install') === '1';
  }

  function isAndroid() {
    return /android/i.test(navigator.userAgent);
  }

  function isPhone() {
    return isIos() || isAndroid();
  }

  function requestedTarget() {
    const target = new URLSearchParams(window.location.search).get('target');
    return target === 'pc' || target === 'phone' ? target : '';
  }

  function setupInstall() {
    const banner = document.getElementById('install-banner');
    const btn = document.getElementById('install-btn');
    const desktopBtn = document.getElementById('install-desktop');
    const dismiss = document.getElementById('install-dismiss');
    const copy = document.getElementById('install-copy');
    const stateEl = document.getElementById('install-state');
    const chip = document.getElementById('install-chip');
    const iosSteps = document.getElementById('install-ios-steps');
    if (!banner || !btn || !desktopBtn || !dismiss || !copy || !stateEl || !chip) {
      return;
    }

    let deferred = null;
    let lastTarget = requestedTarget() || localStorage.getItem('tcda-install-target') || '';
    if (requestedTarget() === 'pc') {
      desktopBtn.classList.add('primary');
      copy.textContent = 'Download TCDA Field for this PC. Status below will say Installed or Not added.';
    }

    function setInstallUi(status, message, chipText) {
      stateEl.textContent = message;
      stateEl.classList.toggle('is-ok', status === 'ok');
      stateEl.classList.toggle('is-no', status === 'no');
      chip.textContent = chipText;
      chip.classList.toggle('is-ok', status === 'ok');
      chip.classList.toggle('is-no', status === 'no');
      banner.classList.toggle('is-ok', status === 'ok');
    }

    function markInstalled(target) {
      const where = target === 'pc'
        ? 'this PC (Start menu or the desktop icon)'
        : 'this phone’s home screen';
      localStorage.setItem('tcda-install-result', 'accepted');
      localStorage.setItem('tcda-install-target', target || lastTarget || (isPhone() ? 'phone' : 'pc'));
      setInstallUi(
        'ok',
        'Install status: added. TCDA Field is on ' + where + '.',
        'Installed'
      );
    }

    async function alreadyInstalledElsewhere() {
      if (!navigator.getInstalledRelatedApps) {
        return false;
      }
      try {
        const apps = await navigator.getInstalledRelatedApps();
        return apps.some((app) => app.platform === 'webapp');
      } catch (err) {
        return false;
      }
    }

    if (isStandalone()) {
      document.documentElement.classList.add('standalone');
      markInstalled(isPhone() ? 'phone' : 'pc');
      banner.hidden = !wantsInstall();
      copy.textContent = 'You opened the installed app — not a browser tab.';
      btn.hidden = true;
      desktopBtn.hidden = true;
      dismiss.textContent = 'Hide this message';
    } else if (localStorage.getItem('tcda-install-result') === 'accepted') {
      markInstalled(localStorage.getItem('tcda-install-target') || '');
    } else if (localStorage.getItem('tcda-install-result') === 'dismissed') {
      setInstallUi('no', 'Install status: not added. The last install was cancelled.', 'Not added');
    } else {
      setInstallUi('no', 'Install status: not added yet.', 'Not added');
    }

    alreadyInstalledElsewhere().then((installed) => {
      if (installed && !isStandalone()) {
        markInstalled(isPhone() ? 'phone' : 'pc');
        copy.textContent = 'Already installed on this device. Open the TCDA Field icon — not this browser tab.';
      }
    });

    if (isIos() && !isStandalone()) {
      copy.textContent = 'On iPhone use Share → Add to Home Screen. This status stays “not added” until you open that icon.';
      if (iosSteps) iosSteps.hidden = false;
    } else if (!window.isSecureContext) {
      copy.textContent = 'Install needs https (or localhost). Both phone and PC buttons stay blocked until then.';
    } else if (!isStandalone()) {
      copy.textContent = 'Add it to a phone home screen, or download it for this PC. You will see Installed or Not added after you choose.';
    }

    async function runPrompt(target) {
      lastTarget = target;
      if (!deferred) {
        setInstallUi(
          'no',
          'Install status: not added. Chrome has not offered an install prompt yet. Use the browser menu → Install TCDA Field / Add to Home screen, then return here.',
          'Not added'
        );
        setStatus(target === 'pc'
          ? 'On Windows: Chrome ⋮ → Install TCDA Field. Edge: Apps → Install this site as an app.'
          : 'On Android: Chrome menu → Install app / Add to Home screen. On iPhone: Share → Add to Home Screen.', true);
        return;
      }
      deferred.prompt();
      const choice = await deferred.userChoice;
      deferred = null;
      if (choice.outcome === 'accepted') {
        markInstalled(target);
        setStatus(target === 'pc'
          ? 'Installed on this PC. Open TCDA Field from the Start menu or the desktop shortcut.'
          : 'Added to the phone. Open the FISC icon on the home screen.');
        copy.textContent = target === 'pc'
          ? 'Download finished. The PC app is installed.'
          : 'The phone icon was added.';
      } else {
        localStorage.setItem('tcda-install-result', 'dismissed');
        setInstallUi('no', 'Install status: not added. You cancelled, so no icon was created.', 'Not added');
        setStatus('Install cancelled. TCDA Field was not added.', true);
      }
    }

    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferred = event;
      btn.disabled = false;
      desktopBtn.disabled = false;
      if (localStorage.getItem('tcda-install-result') !== 'accepted') {
        setInstallUi('no', 'Install status: not added yet. Chrome is ready — pick phone or PC.', 'Ready to install');
      }
    });

    btn.addEventListener('click', () => {
      runPrompt('phone').catch((err) => setStatus(err.message, true));
    });
    desktopBtn.addEventListener('click', () => {
      runPrompt('pc').catch((err) => setStatus(err.message, true));
    });
    dismiss.addEventListener('click', () => {
      banner.hidden = true;
    });

    window.addEventListener('appinstalled', () => {
      markInstalled(lastTarget || (isPhone() ? 'phone' : 'pc'));
      setStatus('Install succeeded.');
    });
  }

  async function api(path, options = {}) {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', ...(options.headers || {}) };
    if (state.session?.token) headers.Authorization = `Bearer ${state.session.token}`;
    const res = await fetch(path, { ...options, headers });
    const body = await res.json().catch(() => ({}));
    if (res.status === 401 && path !== '/api/auth/login' && path !== '/api/auth/logout') {
      await forgetSession();
      render();
      throw new Error('Session expired. Sign in again.');
    }
    if (!res.ok) {
      throw new Error(body.message || `HTTP ${res.status}`);
    }
    return body;
  }

  async function clearWorkspace() {
    for (const name of ['farmers', 'plots', 'batches', 'outbox']) {
      await clearStore(name);
    }
    await deleteMeta('cursor');
    await deleteMeta('parked_owner');
    await deleteMeta('data_owner');
    state.farmers = [];
    state.plots = [];
    state.batches = [];
    state.outbox = [];
    state.aggregators = [];
    state.gps = null;
    state.fenceCorners = [];
  }

  async function forgetSession() {
    await deleteMeta('session');
    state.session = null;
    state.aggregators = [];
    state.view = 'farmers';
  }

  async function login(email, password) {
    const deviceId = (await getMeta('device'))?.device_id || uuid();
    await setMeta('device', { device_id: deviceId });
    const parked = await getMeta('parked_owner');
    const pending = (await getAll('outbox')).filter((op) => op.status === 'pending').length;
    if (parked?.email && parked.email !== email && pending) {
      throw new Error(`This phone still holds unsynced work for ${parked.email}. That officer must sign in and tap Sync first.`);
    }

    const result = await api('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        device_id: deviceId,
        device_name: navigator.userAgent.slice(0, 80),
      }),
    });

    const owner = (await getMeta('data_owner'))?.email || parked?.email;
    if (owner && owner !== result.user.email) {
      await clearWorkspace();
    } else if (!owner) {
      await clearWorkspace();
    }

    await setMeta('session', {
      token: result.token,
      user: result.user,
      aggregators: result.aggregators,
      regions: result.regions || [],
    });
    await setMeta('last_email', { email: result.user.email });
    state.lastEmail = result.user.email;
    await setMeta('data_owner', { email: result.user.email });
    await deleteMeta('parked_owner');
    await refreshLocal();
    await syncNow();
  }

  async function logout() {
    const pending = state.outbox.filter((op) => op.status === 'pending').length;
    if (pending) {
      if (online()) {
        try {
          await syncNow();
        } catch (err) {
          setStatus(err.message, true);
        }
      }
      await refreshLocal();
      const leftover = state.outbox.filter((op) => op.status === 'pending').length;
      if (leftover) {
        const email = state.session?.user?.email || 'this officer';
        const ok = window.confirm(
          leftover + ' records are not yet on the registry. Sign out anyway? They stay on this phone until ' + email + ' signs in again.'
        );
        if (!ok) {
          return;
        }
        await setMeta('parked_owner', { email });
      } else {
        await clearWorkspace();
      }
    } else {
      await clearWorkspace();
    }

    const token = state.session?.token;
    if (state.session?.user?.email) {
      state.lastEmail = state.session.user.email;
      await setMeta('last_email', { email: state.session.user.email });
    }
    await forgetSession();
    setStatus('Signed out. The next officer can sign in.');
    render();

    if (online() && token) {
      fetch('/api/auth/logout', {
        method: 'POST',
        headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' },
      }).catch(() => {});
    }
  }

  async function syncNow() {
    if (!state.session?.token) return;
    if (!online()) {
      setStatus('No network. Operations stay in the outbox until signal returns.', true);
      return;
    }

    setStatus('Syncing…');
    const deviceId = (await getMeta('device')).device_id;
    const pending = state.outbox.filter((op) => op.status === 'pending');

    if (pending.length) {
      const push = await api('/api/sync/push', {
        method: 'POST',
        body: JSON.stringify({
          device_id: deviceId,
          operations: pending.map((op) => ({
            op_id: op.op_id,
            type: op.type,
            client_uuid: op.client_uuid,
            happened_at: op.happened_at,
            payload: op.payload,
          })),
        }),
      });

      const accepted = new Map(push.accepted.map((row) => [row.op_id, row]));
      for (const op of pending) {
        const row = accepted.get(op.op_id);
        if (row && row.status === 'applied') {
          op.status = 'synced';
          op.public_id = row.public_id;
          await put('outbox', op);
        } else if (row && row.duplicate) {
          op.status = row.status === 'rejected' ? 'rejected' : 'synced';
          op.public_id = row.public_id;
          op.reason = row.reason;
          await put('outbox', op);
        }
      }
      for (const row of push.rejected) {
        const op = pending.find((item) => item.op_id === row.op_id);
        if (op) {
          op.status = 'rejected';
          op.reason = row.reason;
          await put('outbox', op);
        }
      }
    }

    const since = (await getMeta('cursor'))?.since || null;
    const query = new URLSearchParams({ device_id: deviceId });
    if (since) query.set('since', since);
    const pull = await api(`/api/sync/pull?${query.toString()}`);

    for (const farmer of pull.farmers) {
      await put('farmers', farmer);
      for (const plot of farmer.plots || []) {
        await put('plots', plot);
      }
    }
    for (const batch of pull.batches) {
      await put('batches', batch);
    }
    await setMeta('cursor', { since: pull.server_time });
    await refreshLocal();
    const leftover = state.outbox.filter((op) => op.status === 'pending').length;
    const rejected = state.outbox.filter((op) => op.status === 'rejected').length;
    setStatus(
      leftover
        ? `${leftover} still queued. ${rejected ? rejected + ' rejected.' : ''}`
        : `Synced ${new Date().toLocaleTimeString()}${rejected ? ' · ' + rejected + ' rejected' : ''}`,
      leftover > 0 || rejected > 0
    );
    render();
  }

  function plotsFor(farmerUuid) {
    return state.plots.filter((plot) => plot.farmer_client_uuid === farmerUuid);
  }

  function setSignedInChrome(signedIn) {
    document.body.classList.toggle('is-locked', !signedIn);
    document.getElementById('sync-btn').hidden = !signedIn;
    document.getElementById('logout-btn').hidden = !signedIn;
    document.getElementById('tabs').hidden = !signedIn;
  }

  function renderLogin(app) {
    app.innerHTML = `
      <div class="field-login">
        <h1>Officer sign in</h1>
        <p class="lede">Each field officer uses their own email. The first sign-in needs network. After that you can enrol and map without signal.</p>
        <form class="stack" id="login-form">
          <label>Officer email
            <input name="email" type="email" autocomplete="username" value="${escapeAttr(state.lastEmail)}" required>
          </label>
          <label>Password
            <input name="password" type="password" autocomplete="current-password" required>
          </label>
          <button class="btn" type="submit">Sign in</button>
        </form>
      </div>`;
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'Signing in…';
      try {
        await login(String(fd.get('email') || '').trim(), String(fd.get('password') || ''));
        state.view = 'farmers';
        setStatus('');
        render();
      } catch (err) {
        setStatus(err.message, true);
        btn.disabled = false;
        btn.textContent = 'Sign in';
      }
    });
  }

  function render() {
    const app = document.getElementById('app');
    const who = document.getElementById('who');
    updateNet();

    if (!state.session?.token) {
      setSignedInChrome(false);
      who.textContent = 'Sign in with your officer email';
      renderLogin(app);
      return;
    }

    setSignedInChrome(true);
    who.textContent = state.session.user.region
      ? `${state.session.user.name} · ${state.session.user.region}`
      : state.session.user.name;
    const waitingCount = state.outbox.filter((op) => op.status === 'pending').length;
    document.getElementById('tabs').querySelectorAll('button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.view === state.view);
      if (btn.dataset.view === 'outbox') {
        btn.textContent = waitingCount ? `Outbox (${waitingCount})` : 'Outbox';
        btn.classList.toggle('has-waiting', waitingCount > 0);
      }
    });

    if (state.view === 'farmers') renderFarmers(app);
    if (state.view === 'enrol') renderEnrol(app);
    if (state.view === 'plot') renderPlot(app);
    if (state.view === 'batch') renderBatch(app);
    if (state.view === 'outbox') renderOutbox(app);
    app.classList.remove('is-entering');
    void app.offsetWidth;
    app.classList.add('is-entering');
  }

  function renderFarmers(app) {
    const pending = new Set(state.outbox.filter((o) => o.status === 'pending' && o.type === 'farmer.upsert').map((o) => o.client_uuid));
    app.innerHTML = `
      <h1>Farmers on this phone</h1>
      <div class="field-list">
        ${state.farmers.map((f) => `
          <div class="field-item">
            <strong>${f.name}</strong>
            ${pending.has(f.client_uuid) ? '<div class="pending">Queued — not yet on registry</div>' : ''}
            <small>${f.public_id || 'local'} · ${f.chain} · ${f.community}</small>
            <small>GPS plots: ${plotsFor(f.client_uuid).length}</small>
          </div>
        `).join('') || '<p class="muted">No farmers yet. Enrol one while you still have the person in front of you.</p>'}
      </div>`;
  }

  function aggregatorOptions() {
    return state.aggregators.map((a) => `<option value="${a.public_id}">${a.name} (${a.chain})</option>`).join('');
  }

  function farmerOptions() {
    return state.farmers.map((f) => `<option value="${f.client_uuid}">${f.name} · ${f.chain}</option>`).join('');
  }

  function regionFieldHtml() {
    const regions = state.session?.regions?.length
      ? state.session.regions
      : (state.session?.user?.region ? [state.session.user.region] : []);
    if (regions.length === 1) {
      return `<p class="muted">Region locked to your assignment: <strong>${regions[0]}</strong></p><input type="hidden" name="region" value="${regions[0]}">`;
    }
    if (!regions.length) {
      return `<p class="muted">No region assigned. Ask TCDA to assign this officer before enrolling.</p>`;
    }
    return `<label>Region <select name="region" required>${regions.map((r) => `<option value="${r}">${r}</option>`).join('')}</select></label>`;
  }

  function fenceToGeoJson(corners) {
    if (!corners || corners.length < 3) {
      return '';
    }
    const ring = corners.map((c) => [c.lng, c.lat]);
    ring.push([corners[0].lng, corners[0].lat]);
    return JSON.stringify({ type: 'Polygon', coordinates: [ring] });
  }

  function renderEnrol(app) {
    const corners = state.fenceCorners || [];
    const cornerList = corners.length
      ? corners.map((c, i) => `${i + 1}. ${c.lat.toFixed(6)}, ${c.lng.toFixed(6)}`).join('<br>')
      : 'No corners yet. Walk the farm boundary.';
    app.innerHTML = `
      <h1>Enrol farmer</h1>
      <form class="stack" id="enrol-form">
        <label>Name <input name="name" required></label>
        <label>Phone <input name="phone"></label>
        <label>Paper card no. <input name="paper_card_no"></label>
        <label>Community <input name="community" required></label>
        <label>District <input name="district" required></label>
        ${regionFieldHtml()}
        <label>Chain
          <select name="chain">
            <option value="palm">Palm</option>
            <option value="cashew">Cashew</option>
          </select>
        </label>
        <label>Aggregator <select name="aggregator_public_id">${aggregatorOptions()}</select></label>
        <label style="display:flex;gap:8px;align-items:center">
          <input type="checkbox" name="consent" value="1" style="width:auto" checked>
          Consent given
        </label>
        <h2>Farm land geofence</h2>
        <p class="muted">Ghana only. Stand at each corner of the farm and add that GPS point. At least three corners.</p>
        <div class="gps-readout">${cornerList}</div>
        <button class="btn secondary" type="button" id="fence-gps">Add current GPS corner</button>
        <button class="btn secondary" type="button" id="fence-undo">Undo last corner</button>
        <label>Land status
          <select name="land_status">
            <option value="customary">Customary</option>
            <option value="documented">Documented</option>
            <option value="unknown">Unknown</option>
          </select>
        </label>
        <button class="btn" type="submit">Save on this phone</button>
      </form>`;
    document.getElementById('fence-gps').addEventListener('click', () => {
      if (!navigator.geolocation) {
        setStatus('This browser has no geolocation.', true);
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          state.fenceCorners = state.fenceCorners || [];
          state.fenceCorners.push({ lat: pos.coords.latitude, lng: pos.coords.longitude });
          render();
        },
        (err) => setStatus(err.message, true),
        { enableHighAccuracy: true, timeout: 20000 }
      );
    });
    document.getElementById('fence-undo').addEventListener('click', () => {
      (state.fenceCorners || []).pop();
      render();
    });
    document.getElementById('enrol-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const geojson = fenceToGeoJson(state.fenceCorners);
      if (!geojson) {
        setStatus('Walk at least three corners of the farm land.', true);
        return;
      }
      const fd = new FormData(e.target);
      const clientUuid = uuid();
      const plotUuid = uuid();
      const payload = {
        name: fd.get('name'),
        phone: fd.get('phone'),
        paper_card_no: fd.get('paper_card_no'),
        community: fd.get('community'),
        district: fd.get('district'),
        region: fd.get('region'),
        chain: fd.get('chain'),
        aggregator_public_id: fd.get('aggregator_public_id'),
        consent: fd.get('consent') === '1',
      };
      const plotPayload = {
        farmer_client_uuid: clientUuid,
        geojson,
        land_status: fd.get('land_status'),
        latitude: state.fenceCorners[0].lat,
        longitude: state.fenceCorners[0].lng,
      };
      await put('farmers', { client_uuid: clientUuid, public_id: null, ...payload });
      await put('plots', { client_uuid: plotUuid, public_id: null, ...plotPayload });
      await queue('farmer.upsert', clientUuid, payload);
      await queue('plot.upsert', plotUuid, plotPayload);
      state.fenceCorners = [];
      await refreshLocal();
      state.view = 'farmers';
      setStatus(online() ? 'Saved with geofence. Syncing…' : 'Saved on phone. Will forward when signal returns.');
      render();
      if (online()) syncNow().catch((err) => setStatus(err.message, true));
    });
  }

  function renderPlot(app) {
    const gpsText = state.gps
      ? `${state.gps.latitude.toFixed(6)}, ${state.gps.longitude.toFixed(6)}`
      : 'No point yet';
    app.innerHTML = `
      <h1>Map plot</h1>
      <form class="stack" id="plot-form">
        <label>Farmer <select name="farmer_client_uuid" required>${farmerOptions()}</select></label>
        <div class="gps-readout" id="gps">${gpsText}</div>
        <button class="btn secondary" type="button" id="gps-btn">Use phone GPS</button>
        <label>Latitude <input name="latitude" value="${state.gps?.latitude ?? ''}"></label>
        <label>Longitude <input name="longitude" value="${state.gps?.longitude ?? ''}"></label>
        <label>Area (ha) <input name="area_hectares" type="number" step="0.01"></label>
        <label>Land status
          <select name="land_status">
            <option value="customary">Customary</option>
            <option value="documented">Documented</option>
            <option value="unknown">Unknown</option>
          </select>
        </label>
        <button class="btn" type="submit">Save plot locally</button>
      </form>`;
    document.getElementById('gps-btn').addEventListener('click', () => {
      if (!navigator.geolocation) {
        setStatus('This browser has no geolocation.', true);
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          state.gps = { latitude: pos.coords.latitude, longitude: pos.coords.longitude };
          render();
        },
        (err) => setStatus(err.message, true),
        { enableHighAccuracy: true, timeout: 20000 }
      );
    });
    document.getElementById('plot-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const clientUuid = uuid();
      const payload = {
        farmer_client_uuid: fd.get('farmer_client_uuid'),
        latitude: fd.get('latitude') ? Number(fd.get('latitude')) : null,
        longitude: fd.get('longitude') ? Number(fd.get('longitude')) : null,
        area_hectares: fd.get('area_hectares') ? Number(fd.get('area_hectares')) : null,
        land_status: fd.get('land_status'),
      };
      await put('plots', { client_uuid: clientUuid, public_id: null, ...payload });
      await queue('plot.upsert', clientUuid, payload);
      await refreshLocal();
      setStatus(online() ? 'Plot saved. Syncing…' : 'Plot stored on phone.');
      render();
      if (online()) syncNow().catch((err) => setStatus(err.message, true));
    });
  }

  function renderBatch(app) {
    const open = state.batches.filter((b) => b.status === 'open' || !b.status);
    const current = open[0];
    app.innerHTML = `
      <h1>Buying-point batch</h1>
      ${current ? `<p class="muted">Open: ${current.public_id || 'local'} · ${current.chain} · ${(current.members || []).length} deliveries</p>` : ''}
      <form class="stack" id="open-form">
        <label>Chain
          <select name="chain"><option value="palm">Palm</option><option value="cashew">Cashew</option></select>
        </label>
        <label>Aggregator <select name="aggregator_public_id">${aggregatorOptions()}</select></label>
        <button class="btn secondary" type="submit">Open new batch on this phone</button>
      </form>
      ${current ? `
        <form class="stack" id="del-form" style="margin-top:20px">
          <h2>Add delivery</h2>
          <label>Farmer <select name="farmer_client_uuid">${farmerOptions()}</select></label>
          <label>Kg <input name="volume_kg" type="number" step="0.1" required></label>
          <label>Paper stub <input name="paper_stub_no"></label>
          <button class="btn" type="submit">Queue delivery</button>
        </form>
        <form id="close-form" style="margin-top:12px">
          <button class="btn secondary" type="submit">Close batch</button>
        </form>` : ''}`;

    document.getElementById('open-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const clientUuid = uuid();
      const payload = {
        chain: fd.get('chain'),
        aggregator_public_id: fd.get('aggregator_public_id'),
      };
      await put('batches', { client_uuid: clientUuid, status: 'open', members: [], ...payload });
      await queue('batch.open', clientUuid, payload);
      await refreshLocal();
      render();
      if (online()) syncNow().catch((err) => setStatus(err.message, true));
    });

    const del = document.getElementById('del-form');
    if (del) {
      del.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const farmerUuid = fd.get('farmer_client_uuid');
        const plot = plotsFor(farmerUuid)[0];
        const clientUuid = uuid();
        const payload = {
          batch_client_uuid: current.client_uuid,
          farmer_client_uuid: farmerUuid,
          plot_client_uuid: plot?.client_uuid || null,
          volume_kg: Number(fd.get('volume_kg')),
          paper_stub_no: fd.get('paper_stub_no'),
          delivered_at: new Date().toISOString(),
        };
        current.members = current.members || [];
        current.members.push(payload);
        await put('batches', current);
        await queue('batch.delivery', clientUuid, payload);
        await refreshLocal();
        render();
        if (online()) syncNow().catch((err) => setStatus(err.message, true));
      });
    }
    const close = document.getElementById('close-form');
    if (close) {
      close.addEventListener('submit', async (e) => {
        e.preventDefault();
        current.status = 'closed';
        await put('batches', current);
        await queue('batch.close', current.client_uuid, { batch_client_uuid: current.client_uuid });
        await refreshLocal();
        render();
        if (online()) syncNow().catch((err) => setStatus(err.message, true));
      });
    }
  }

  function outboxTypeLabel(type) {
    return ({
      'farmer.upsert': 'Farmer enrolment',
      'plot.upsert': 'Farm geofence',
      'batch.open': 'Open farm-set',
      'batch.delivery': 'Delivery kg',
      'batch.close': 'Close farm-set',
    })[type] || type;
  }

  function outboxSubject(op) {
    const payload = op.payload || {};
    if (op.type === 'farmer.upsert') {
      return [payload.name, payload.community, payload.chain].filter(Boolean).join(' · ') || 'Farmer';
    }
    if (op.type === 'plot.upsert') {
      const farmer = state.farmers.find((row) => row.client_uuid === payload.farmer_client_uuid);
      return farmer?.name || 'Plot corners';
    }
    if (op.type === 'batch.open') {
      return payload.chain ? `${payload.chain} farm-set` : 'Farm-set';
    }
    if (op.type === 'batch.delivery') {
      const farmer = state.farmers.find((row) => row.client_uuid === payload.farmer_client_uuid);
      const kg = payload.volume_kg != null ? `${payload.volume_kg} kg` : 'Delivery';
      return farmer?.name ? `${kg} · ${farmer.name}` : kg;
    }
    if (op.type === 'batch.close') {
      return 'Close farm-set';
    }
    return op.public_id || 'Outgoing record';
  }

  function outboxRows(ops, kind) {
    const badge = kind === 'waiting'
      ? 'Waiting for network'
      : kind === 'synced'
        ? 'Synced'
        : 'Rejected';
    if (!ops.length) {
      return kind === 'waiting'
        ? '<p class="muted">Nothing waiting — all outgoing records are synced.</p>'
        : '<p class="muted">No synced records yet.</p>';
    }
    return `<div class="field-list">${ops.map((op) => `
      <article class="field-item outbox-item is-${kind}">
        <div class="outbox-item-top">
          <strong>${outboxTypeLabel(op.type)}</strong>
          <span class="outbox-badge is-${kind}">${badge}</span>
        </div>
        <p class="outbox-subject">${outboxSubject(op)}</p>
        <small>${new Date(op.happened_at).toLocaleString()}${op.public_id ? ' · ' + op.public_id : ''}</small>
        ${op.reason ? `<small class="pending">${op.reason}</small>` : ''}
      </article>
    `).join('')}</div>`;
  }

  function renderOutbox(app) {
    const waiting = state.outbox.filter((op) => op.status === 'pending').slice().reverse();
    const synced = state.outbox.filter((op) => op.status === 'synced').slice().reverse();
    const rejected = state.outbox.filter((op) => op.status === 'rejected').slice().reverse();
    app.innerHTML = `
      <h1>Outbox</h1>
      <p class="lede">Every outgoing record is marked <strong>waiting for network</strong> or <strong>synced</strong>.</p>
      <div class="outbox-summary">
        <span class="outbox-summary-chip is-waiting">${waiting.length} waiting for network</span>
        <span class="outbox-summary-chip is-synced">${synced.length} synced</span>
        ${rejected.length ? `<span class="outbox-summary-chip is-rejected">${rejected.length} rejected</span>` : ''}
      </div>
      <section class="outbox-section is-waiting">
        <h2>Waiting for network</h2>
        <p class="muted">${online() ? 'Signal is up. Tap Sync to send these to the registry.' : 'No signal. These stay on this phone until the network returns.'}</p>
        ${outboxRows(waiting, 'waiting')}
      </section>
      <section class="outbox-section is-synced">
        <h2>Synced</h2>
        <p class="muted">Already on the registry.</p>
        ${outboxRows(synced, 'synced')}
      </section>
      ${rejected.length ? `
        <section class="outbox-section is-rejected">
          <h2>Rejected</h2>
          <p class="muted">The registry did not accept these.</p>
          ${outboxRows(rejected, 'rejected')}
        </section>` : ''}
    `;
  }

  document.getElementById('tabs').addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-view]');
    if (!btn) return;
    state.view = btn.dataset.view;
    render();
  });
  document.getElementById('sync-btn').addEventListener('click', () => {
    const btn = document.getElementById('sync-btn');
    btn.classList.add('is-busy');
    syncNow()
      .catch((err) => setStatus(err.message, true))
      .finally(() => btn.classList.remove('is-busy'));
  });
  document.getElementById('logout-btn').addEventListener('click', () => {
    logout().catch((err) => setStatus(err.message, true));
  });
  window.addEventListener('online', () => {
    updateNet();
    syncNow().catch((err) => setStatus(err.message, true));
  });
  window.addEventListener('offline', updateNet);

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/field-sw.js').then((reg) => reg.update()).catch(() => {});
  }

  setupInstall();

  openDb()
    .then(async (database) => {
      db = database;
      await refreshLocal();
      render();
      if (online() && state.session?.token) {
        syncNow().catch((err) => setStatus(err.message, true));
      }
    })
    .catch((err) => setStatus(err.message, true));
})();
