const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

const state = {
  setupRequired: false,
  brand: null,
  settings: null,
  brandFile: '',
};

function api(action, body = undefined) {
  const options = {
    method: body === undefined ? 'GET' : 'POST',
    headers: body === undefined ? {} : { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
  };
  if (body !== undefined) options.body = JSON.stringify(body);
  return fetch(`${window.QLH_BASE}/api.php?action=${encodeURIComponent(action)}`, options)
    .then(async (response) => {
      const data = await response.json().catch(() => ({ ok: false, message: 'Không đọc được phản hồi API.' }));
      if (!response.ok || data.ok === false) {
        const error = new Error(data.message || 'Có lỗi xảy ra.');
        error.status = response.status;
        error.payload = data;
        throw error;
      }
      return data;
    });
}

function showAuth() {
  $('#auth').hidden = false;
  $('#app').hidden = true;
}

function showApp() {
  $('#auth').hidden = true;
  $('#app').hidden = false;
}

function setAuthBusy(busy, label = 'Tiếp tục') {
  const button = $('#authForm button[type="submit"]');
  button.disabled = busy;
  button.textContent = busy ? 'Đang đăng nhập...' : label;
}

function toast(message, error = false) {
  const notice = $('#notice');
  notice.textContent = message;
  notice.className = `notice${error ? ' error' : ''}`;
  notice.hidden = false;
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => { notice.hidden = true; }, 4200);
}

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[char]));
}

function today() {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  return now;
}

function parseDateParts(value) {
  const text = String(value || '').trim();
  if (!text) return null;
  let day;
  let month;
  let year;
  let match = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (match) {
    [, day, month, year] = match;
  } else {
    match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;
    [, year, month, day] = match;
  }

  const date = new Date(Number(year), Number(month) - 1, Number(day));
  if (
    Number.isNaN(date.getTime())
    || date.getFullYear() !== Number(year)
    || date.getMonth() !== Number(month) - 1
    || date.getDate() !== Number(day)
  ) {
    return null;
  }

  date.setHours(0, 0, 0, 0);
  return { day: Number(day), month: Number(month), year: Number(year), date };
}

function toStorageDate(value) {
  const parsed = parseDateParts(value);
  if (!parsed) return '';
  return `${String(parsed.year).padStart(4, '0')}-${String(parsed.month).padStart(2, '0')}-${String(parsed.day).padStart(2, '0')}`;
}

function toDisplayDate(value) {
  const parsed = parseDateParts(value);
  if (!parsed) return '';
  return `${String(parsed.day).padStart(2, '0')}/${String(parsed.month).padStart(2, '0')}/${String(parsed.year).padStart(4, '0')}`;
}

function daysUntil(value) {
  const parsed = parseDateParts(value);
  if (!parsed) return null;
  return Math.round((parsed.date - today()) / 86400000);
}

function monthKeyFromDate(value) {
  const parsed = parseDateParts(value);
  if (!parsed) return '';
  return `${String(parsed.year).padStart(4, '0')}-${String(parsed.month).padStart(2, '0')}`;
}

function monthLabel(monthDate) {
  return monthDate.toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' });
}

function monthShortLabel(monthDate) {
  return `${String(monthDate.getMonth() + 1).padStart(2, '0')}/${monthDate.getFullYear()}`;
}

function monthStart(date = new Date()) {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function upcomingMonths(total = 12) {
  const start = monthStart();
  return Array.from({ length: total }, (_, index) => new Date(start.getFullYear(), start.getMonth() + index, 1));
}

function buildSchedule(total = 12) {
  const months = upcomingMonths(total);
  const groups = new Map(months.map((date) => [monthKeyFromDate(`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`), {
    key: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
    label: monthLabel(date),
    date,
    items: [],
  }]));

  const overdue = [];
  Object.entries(state.brand?.domains || {}).forEach(([domain, info]) => {
    const parsed = parseDateParts(info.expire);
    if (!parsed) return;
    const item = { domain, info, days: daysUntil(info.expire), displayDate: toDisplayDate(info.expire) };
    const key = `${String(parsed.year).padStart(4, '0')}-${String(parsed.month).padStart(2, '0')}`;
    if (item.days < 0) {
      overdue.push(item);
    }
    if (groups.has(key)) {
      groups.get(key).items.push(item);
    }
  });

  const list = Array.from(groups.values()).map((group) => ({
    ...group,
    items: group.items.sort((a, b) => (a.days ?? 0) - (b.days ?? 0) || a.domain.localeCompare(b.domain)),
  }));

  return {
    months: list,
    overdue: overdue.sort((a, b) => a.days - b.days),
    max: Math.max(1, ...list.map((group) => group.items.length)),
  };
}

function statusFor(info) {
  const days = daysUntil(info.expire);
  if (days === null) return { label: 'Chưa có hạn', className: '' };
  if (days < 0) return { label: `Quá ${Math.abs(days)} ngày`, className: 'danger' };
  if (days <= 14) return { label: `${days} ngày`, className: 'danger' };
  if (days <= 30) return { label: `${days} ngày`, className: 'warn' };
  return { label: `${days} ngày`, className: 'ok' };
}

function blankNotify() {
  return { active: false, type: 'info', message: '', button_text: '', button_url: '' };
}

function blankDomain() {
  return { expire: '', hosting_note: '', notify: blankNotify() };
}

function notifyEditorHtml(scope, notify = blankNotify()) {
  return `
    <h3>${scope === 'global' ? 'Thông báo chung' : 'Thông báo riêng'}</h3>
    <div class="notify-grid">
      <label class="checkline"><input data-nf="${scope}:active" type="checkbox" ${notify.active ? 'checked' : ''}><span>Bật</span></label>
      <label><span>Loại</span>
        <select data-nf="${scope}:type">
          ${['info', 'warning', 'error', 'success'].map((type) => `<option value="${type}" ${notify.type === type ? 'selected' : ''}>${type}</option>`).join('')}
        </select>
      </label>
      <label><span>Nội dung</span><input data-nf="${scope}:message" type="text" value="${escapeHtml(notify.message)}"></label>
      <label><span>Nút bấm</span><input data-nf="${scope}:button_text" type="text" value="${escapeHtml(notify.button_text)}"></label>
    </div>
    <label><span>URL nút bấm</span><input data-nf="${scope}:button_url" type="url" value="${escapeHtml(notify.button_url)}"></label>
  `;
}

function bindNotifyEditor(root, getNotify) {
  $$('[data-nf]', root).forEach((input) => {
    input.addEventListener('input', () => {
      const [, key] = input.dataset.nf.split(':');
      const notify = getNotify();
      notify[key] = input.type === 'checkbox' ? input.checked : input.value;
      syncJson();
    });
    input.addEventListener('change', () => {
      const [, key] = input.dataset.nf.split(':');
      const notify = getNotify();
      notify[key] = input.type === 'checkbox' ? input.checked : input.value;
      syncJson();
      renderOverview();
    });
  });
}

function renderBrand() {
  $$('[data-brand]').forEach((input) => {
    const key = input.dataset.brand;
    if (input.dataset.dateInput) {
      input.value = toDisplayDate(state.brand[key]);
      input.oninput = null;
      input.onchange = () => {
        const normalized = toStorageDate(input.value);
        if (!normalized && input.value.trim() !== '') {
          input.setCustomValidity('Nhập theo định dạng dd/mm/yyyy');
          input.reportValidity();
          return;
        }
        input.setCustomValidity('');
        state.brand[key] = normalized;
        input.value = toDisplayDate(normalized);
        syncJson();
      };
      return;
    }

    input.value = state.brand[key] || '';
    input.oninput = () => {
      state.brand[key] = input.value;
      syncJson();
    };
  });

  const editor = $('[data-notify="global"]');
  editor.innerHTML = notifyEditorHtml('global', state.brand.notify || blankNotify());
  bindNotifyEditor(editor, () => state.brand.notify);
}

function renderDomains() {
  const list = $('#domainList');
  const query = $('#domainSearch').value.trim().toLowerCase();
  const entries = Object.entries(state.brand.domains || {})
    .filter(([domain]) => !query || domain.toLowerCase().includes(query))
    .sort(([a], [b]) => a.localeCompare(b));

  list.innerHTML = entries.length ? entries.map(([domain, info]) => {
    const status = statusFor(info);
    return `
      <article class="domain-row" data-domain="${escapeHtml(domain)}">
        <button class="domain-summary" type="button">
          <span>
            <span class="domain-name">${escapeHtml(domain)}</span>
            <span class="domain-meta">${escapeHtml(toDisplayDate(info.expire) || 'Chưa có ngày hết hạn')}${info.hosting_note ? ` · ${escapeHtml(info.hosting_note)}` : ''}</span>
          </span>
          <span class="status ${status.className}">${escapeHtml(status.label)}</span>
        </button>
        <div class="domain-editor">
          <div class="form-grid">
            <label><span>Domain</span><input data-domain-field="name" type="text" value="${escapeHtml(domain)}"></label>
            <label><span>Ngày hết hạn</span><input data-domain-field="expire" type="text" inputmode="numeric" placeholder="dd/mm/yyyy" value="${escapeHtml(toDisplayDate(info.expire))}"></label>
            <label><span>Ghi chú hosting</span><input data-domain-field="hosting_note" type="text" value="${escapeHtml(info.hosting_note)}"></label>
            <label><span>&nbsp;</span><button class="danger-btn" data-remove-domain type="button">Xóa domain</button></label>
          </div>
          <div class="notify-editor">${notifyEditorHtml(domain, info.notify || blankNotify())}</div>
        </div>
      </article>
    `;
  }).join('') : '<p class="empty">Không có domain phù hợp.</p>';

  $$('.domain-row', list).forEach((row) => {
    const domain = row.dataset.domain;
    $('.domain-summary', row).addEventListener('click', () => row.classList.toggle('open'));
    $('[data-remove-domain]', row).addEventListener('click', () => {
      if (!confirm(`Xóa ${domain}?`)) return;
      delete state.brand.domains[domain];
      renderAll();
      syncJson();
    });

    $$('[data-domain-field]', row).forEach((input) => {
      input.addEventListener('change', () => {
        const field = input.dataset.domainField;
        if (field === 'name') {
          const next = input.value.trim().toLowerCase();
          if (!next || next === domain) return;
          state.brand.domains[next] = state.brand.domains[domain] || blankDomain();
          delete state.brand.domains[domain];
          renderAll();
        } else if (field === 'expire') {
          const normalized = toStorageDate(input.value);
          if (!normalized && input.value.trim() !== '') {
            input.setCustomValidity('Nhập theo định dạng dd/mm/yyyy');
            input.reportValidity();
            return;
          }
          input.setCustomValidity('');
          state.brand.domains[domain][field] = normalized;
          input.value = toDisplayDate(normalized);
          renderOverview();
          renderCalendar();
          syncJson();
        } else {
          state.brand.domains[domain][field] = input.value;
          renderOverview();
          renderCalendar();
          syncJson();
        }
      });
      input.addEventListener('input', () => {
        const field = input.dataset.domainField;
        if (field !== 'name' && field !== 'expire') state.brand.domains[domain][field] = input.value;
        syncJson();
      });
    });

    bindNotifyEditor($('.notify-editor', row), () => {
      state.brand.domains[domain].notify = state.brand.domains[domain].notify || blankNotify();
      return state.brand.domains[domain].notify;
    });
  });
}

function renderContacts() {
  const list = $('#contactList');
  const contacts = state.brand.contacts || [];
  list.innerHTML = contacts.length ? contacts.map((contact, index) => `
    <article class="contact-row" data-contact="${index}">
      <label><span>Nhãn</span><input data-contact-field="label" type="text" value="${escapeHtml(contact.label)}"></label>
      <label><span>Điện thoại</span><input data-contact-field="phone" type="text" value="${escapeHtml(contact.phone || '')}"></label>
      <label><span>Hiển thị</span><input data-contact-field="display" type="text" value="${escapeHtml(contact.display || '')}"></label>
      <label><span>Email</span><input data-contact-field="email" type="email" value="${escapeHtml(contact.email || '')}"></label>
      <label><span>URL</span><input data-contact-field="url" type="url" value="${escapeHtml(contact.url || '')}"></label>
      <label><span>Link</span><input data-contact-field="link_url" type="text" value="${escapeHtml(contact.link_url || '')}"></label>
      <button class="danger-btn" data-remove-contact type="button">Xóa</button>
    </article>
  `).join('') : '<p class="empty">Chưa có liên hệ.</p>';

  $$('.contact-row', list).forEach((row) => {
    const index = Number(row.dataset.contact);
    $$('[data-contact-field]', row).forEach((input) => {
      input.addEventListener('input', () => {
        const field = input.dataset.contactField;
        const contact = contacts[index];
        contact[field] = input.value;
        syncJson();
      });
    });
    $('[data-remove-contact]', row).addEventListener('click', () => {
      contacts.splice(index, 1);
      renderContacts();
      syncJson();
    });
  });
}

function renderCalendar() {
  const schedule = buildSchedule(12);
  $('#monthPreview').innerHTML = schedule.months.map((month) => `
    <article class="month-card">
      <div>
        <strong>${escapeHtml(monthShortLabel(month.date))}</strong>
        <small>${month.items.length}</small>
      </div>
      <div class="month-bar"><span style="width:${month.items.length ? Math.max(10, (month.items.length / schedule.max) * 100) : 0}%"></span></div>
    </article>
  `).join('');

  $('#monthSchedule').innerHTML = `
    ${schedule.overdue.length ? `
      <section class="month-panel">
        <div class="month-panel-head">
          <strong>Đã quá hạn</strong>
          <span class="status danger">${schedule.overdue.length} dịch vụ</span>
        </div>
        <div class="schedule-list">
          ${schedule.overdue.map((item) => `
            <article class="schedule-item">
              <div>
                <strong>${escapeHtml(item.domain)}</strong>
                <p class="muted">${escapeHtml(item.displayDate)}${item.info.hosting_note ? ` · ${escapeHtml(item.info.hosting_note)}` : ''}</p>
              </div>
              <span class="status danger">Quá ${Math.abs(item.days)} ngày</span>
            </article>
          `).join('')}
        </div>
      </section>
    ` : ''}
    ${schedule.months.map((month) => `
      <section class="month-panel">
        <div class="month-panel-head">
          <strong>${escapeHtml(monthShortLabel(month.date))}</strong>
          <span class="status ${month.items.length ? 'warn' : ''}">${month.items.length} dịch vụ</span>
        </div>
        ${month.items.length ? `
          <div class="schedule-list">
            ${month.items.map((item) => {
              const status = statusFor(item.info);
              return `
                <article class="schedule-item">
                  <div>
                    <strong>${escapeHtml(item.domain)}</strong>
                    <p class="muted">${escapeHtml(item.displayDate)}${item.info.hosting_note ? ` · ${escapeHtml(item.info.hosting_note)}` : ''}</p>
                  </div>
                  <span class="status ${status.className}">${escapeHtml(status.label)}</span>
                </article>
              `;
            }).join('')}
          </div>
        ` : '<p class="empty">Tháng này chưa có dịch vụ đến hạn.</p>'}
      </section>
    `).join('')}
  `;
}

function renderTelegram() {
  const settings = state.settings || {};
  $('#tgEnabled').checked = !!settings.telegram?.enabled;
  $('#tgToken').placeholder = settings.telegram?.bot_token_masked || 'Để trống nếu không đổi';
  $('#tgChatId').value = settings.telegram?.chat_id || '';
  $('#reminderDays').value = (settings.reminders?.days || [30, 14, 7, 3, 1, 0]).join(',');
  $('#repeatAfter').value = settings.reminders?.repeat_after_days || 1;
  $('#notifyOverdue').checked = settings.reminders?.notify_overdue !== false;
  $('#adminUsername').value = settings.username || 'phudigital';
  $('#cronHint').textContent = `Cron hằng ngày: ${location.origin}${window.QLH_BASE}/cron.php?key=${settings.cron_key || 'an-trong-settings-json'}`;
}

function renderOverview() {
  const domains = Object.entries(state.brand?.domains || {});
  const attention = domains
    .map(([domain, info]) => ({ domain, info, days: daysUntil(info.expire) }))
    .filter((row) => row.days !== null && row.days <= 30)
    .sort((a, b) => a.days - b.days);
  const schedule = buildSchedule(12);
  const logo = state.brand?.logo || '';
  const peakMonth = schedule.months.reduce((best, month) => (month.items.length > best.items.length ? month : best), schedule.months[0] || { date: new Date(), items: [] });
  const notifyText = state.brand?.notify?.active
    ? (state.brand.notify.message || state.brand.notify.button_text || 'Đang bật')
    : 'Đang tắt';

  $('#brandLogoPreview').src = logo || 'https://pdl.vn/wp-content/uploads/2025/12/logopdlphudigital.png';
  $('#brandLogoPreview').hidden = false;
  $('#heroCompany').textContent = state.brand?.company || 'PDL';
  $('#heroAddress').textContent = state.brand?.address || 'Chưa có địa chỉ thương hiệu';
  $('#heroUpdated').textContent = `Cập nhật ${toDisplayDate(state.brand?.updated_at) || '--/--/----'}`;
  $('#heroWebsite').textContent = (state.brand?.website || 'https://pdl.vn').replace(/^https?:\/\//, '');
  $('#heroFile').textContent = state.brandFile.split('/').pop() || 'brand.json';
  $('#heroNotify').textContent = notifyText;
  $('#heroPeakMonth').textContent = peakMonth?.items?.length ? monthShortLabel(peakMonth.date) : 'Chưa có';

  $('#totalDomains').textContent = domains.length;
  $('#nearExpiry').textContent = attention.filter((row) => row.days >= 0).length;
  $('#expiredDomains').textContent = attention.filter((row) => row.days < 0).length;
  $('#attentionList').innerHTML = attention.length ? attention.map((row) => {
    const status = statusFor(row.info);
    return `
      <article class="attention-item">
        <div><strong>${escapeHtml(row.domain)}</strong><p class="muted">${escapeHtml(toDisplayDate(row.info.expire))}${row.info.hosting_note ? ` · ${escapeHtml(row.info.hosting_note)}` : ''}</p></div>
        <span class="status ${status.className}">${escapeHtml(status.label)}</span>
      </article>
    `;
  }).join('') : '<p class="empty">Không có dịch vụ nào hết hạn trong 30 ngày.</p>';
}

function syncJson() {
  $('#jsonEditor').value = JSON.stringify(state.brand, null, 2);
}

function renderAll() {
  renderOverview();
  renderCalendar();
  renderBrand();
  renderDomains();
  renderContacts();
  renderTelegram();
  syncJson();
}

function parseJsonEditor() {
  try {
    state.brand = JSON.parse($('#jsonEditor').value);
    renderAll();
    toast('Đã cập nhật dữ liệu từ JSON editor.');
    return true;
  } catch (error) {
    toast(`JSON lỗi: ${error.message}`, true);
    return false;
  }
}

async function loadData() {
  try {
    const data = await api('data');
    state.brand = data.brand;
    state.settings = data.settings;
    state.brandFile = data.brand_file;
    $('#sessionLabel').textContent = `Đăng nhập: ${data.settings.username || 'phudigital'} · File: ${data.brand_file.split('/').pop()}`;
    showApp();
    renderAll();
    if (!data.brand_writable) toast('brand.json có thể chưa ghi được. Kiểm tra permission trên hosting.', true);
  } catch (error) {
    if (error.status === 401 || error.payload?.setup_required) {
      showAuth();
    } else {
      showApp();
      toast(error.message, true);
    }
    throw error;
  }
}

function wireTabs() {
  $$('.tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      $$('.tab').forEach((item) => item.classList.toggle('active', item === tab));
      $$('.panel').forEach((panel) => panel.classList.toggle('active', panel.dataset.panel === tab.dataset.tab));
    });
  });
}

function wireActions() {
  $('#authForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const action = state.setupRequired ? 'setup' : 'login';
    setAuthBusy(true, state.setupRequired ? 'Tạo tài khoản' : 'Tiếp tục');
    try {
      await api(action, {
        username: $('#authUsername').value.trim(),
        password: $('#authPassword').value,
      });
      showApp();
      $('#sessionLabel').textContent = `Đăng nhập: ${$('#authUsername').value.trim().toLowerCase()} · Đang tải dữ liệu...`;
      $('#authPassword').value = '';
      await loadData();
    } catch (error) {
      showAuth();
      toast(error.message, true);
    } finally {
      setAuthBusy(false, state.setupRequired ? 'Tạo tài khoản' : 'Tiếp tục');
    }
  });

  $('#logoutBtn').addEventListener('click', async () => {
    try {
      await api('logout', {});
    } finally {
      showAuth();
      $('#authPassword').value = '';
      toast('Đã đăng xuất.');
    }
  });

  $('#saveBtn').addEventListener('click', async () => {
    if (!parseJsonEditor()) return;
    $('#saveBtn').disabled = true;
    try {
      const result = await api('save-brand', { brand: state.brand });
      state.brand = result.brand;
      await saveSettings();
      renderAll();
      toast('Đã lưu brand.json và cài đặt.');
    } catch (error) {
      toast(error.message, true);
    } finally {
      $('#saveBtn').disabled = false;
    }
  });

  $('#refreshBtn').addEventListener('click', loadData);
  $('#jumpDomainsBtn').addEventListener('click', () => $('.tab[data-tab="domains"]')?.click());
  $('#jumpTelegramBtn').addEventListener('click', () => $('.tab[data-tab="telegram"]')?.click());
  $('#scheduleToggle').addEventListener('click', () => {
    const panel = $('#schedulePanel');
    const expanded = $('#scheduleToggle').getAttribute('aria-expanded') === 'true';
    $('#scheduleToggle').setAttribute('aria-expanded', expanded ? 'false' : 'true');
    panel.hidden = expanded;
  });
  $('#domainSearch').addEventListener('input', renderDomains);
  $('#formatJsonBtn').addEventListener('click', parseJsonEditor);

  $('#addDomainBtn').addEventListener('click', () => {
    const domain = prompt('Nhập domain mới');
    if (!domain) return;
    state.brand.domains[domain.trim().toLowerCase()] = blankDomain();
    renderAll();
    syncJson();
  });

  $('#addContactBtn').addEventListener('click', () => {
    state.brand.contacts = state.brand.contacts || [];
    state.brand.contacts.push({ label: '', phone: '', display: '', link_url: '' });
    renderContacts();
    syncJson();
  });

  $('#testTelegramBtn').addEventListener('click', async () => {
    try {
      await saveSettings();
      const result = await api('test-telegram', {});
      toast(result.message || 'Đã gửi thử Telegram.');
    } catch (error) {
      toast(error.message, true);
    }
  });

  $('#dryRunBtn').addEventListener('click', async () => {
    try {
      await saveSettings();
      const result = await api('run-reminders', { dry_run: true });
      const sent = result.sent?.length ? result.sent.join(', ') : 'không có domain cần nhắc';
      toast(`Kiểm tra xong: ${sent}.`);
    } catch (error) {
      toast(error.message, true);
    }
  });
}

async function saveSettings() {
  const token = $('#tgToken').value.trim();
  const settings = {
    username: $('#adminUsername').value.trim(),
    telegram: {
      enabled: $('#tgEnabled').checked,
      chat_id: $('#tgChatId').value.trim(),
    },
    reminders: {
      days: $('#reminderDays').value.split(',').map((value) => Number(value.trim())).filter((value) => Number.isFinite(value)),
      notify_overdue: $('#notifyOverdue').checked,
      repeat_after_days: Number($('#repeatAfter').value || 1),
    },
    new_password: $('#newPassword').value,
  };
  if (token) settings.telegram.bot_token = token;
  const result = await api('save-settings', { settings });
  state.settings = result.settings;
  $('#tgToken').value = '';
  $('#newPassword').value = '';
  renderTelegram();
}

async function boot() {
  wireTabs();
  wireActions();
  try {
    const status = await api('status');
    state.setupRequired = !!status.setup_required;
    if (status.authenticated) {
      await loadData();
    } else {
      showAuth();
      $('#authCopy').textContent = state.setupRequired
        ? 'Tạo tài khoản quản trị đầu tiên cho ql-hosting.'
        : 'Đăng nhập để cập nhật brand.json và lịch nhắc Telegram.';
    }
  } catch (error) {
    showAuth();
    toast(error.message, true);
  }
}

boot();
