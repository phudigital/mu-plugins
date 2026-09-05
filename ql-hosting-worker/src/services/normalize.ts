import type { BrandDocument, Contact, NotifyConfig, NotifyType, SettingsDocument } from "./types";

const notifyTypes = new Set<NotifyType>(["info", "warning", "error", "success"]);

export function emptyNotify(): NotifyConfig {
  return {
    active: false,
    type: "info",
    message: "",
    button_text: "",
    button_url: ""
  };
}

export function normalizeText(value: unknown): string {
  return String(value ?? "").trim();
}

export function normalizeUsername(value: unknown): string {
  return normalizeText(value).toLocaleLowerCase("vi-VN");
}

export function normalizeDateString(value: unknown): string {
  const text = normalizeText(value);
  if (!text) return "";

  let year = 0;
  let month = 0;
  let day = 0;
  const display = text.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  const storage = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);

  if (display) {
    day = Number(display[1]);
    month = Number(display[2]);
    year = Number(display[3]);
  } else if (storage) {
    year = Number(storage[1]);
    month = Number(storage[2]);
    day = Number(storage[3]);
  } else {
    return "";
  }

  const date = new Date(Date.UTC(year, month - 1, day));
  if (
    date.getUTCFullYear() !== year ||
    date.getUTCMonth() !== month - 1 ||
    date.getUTCDate() !== day
  ) {
    return "";
  }

  return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

export function normalizeNotify(value: unknown): NotifyConfig {
  const input = value && typeof value === "object" ? value as Record<string, unknown> : {};
  const type = notifyTypes.has(input.type as NotifyType) ? input.type as NotifyType : "info";
  return {
    active: Boolean(input.active),
    type,
    message: normalizeText(input.message),
    button_text: normalizeText(input.button_text),
    button_url: normalizeText(input.button_url)
  };
}

function normalizeContact(value: unknown): Contact | null {
  if (!value || typeof value !== "object") return null;
  const input = value as Record<string, unknown>;
  const contact: Contact = {
    label: normalizeText(input.label),
    phone: normalizeText(input.phone),
    display: normalizeText(input.display),
    link_url: normalizeText(input.link_url),
    email: normalizeText(input.email),
    url: normalizeText(input.url)
  };

  Object.keys(contact).forEach((key) => {
    if (!contact[key as keyof Contact]) delete contact[key as keyof Contact];
  });

  return Object.keys(contact).length ? contact : null;
}

export function defaultBrand(): BrandDocument {
  return {
    company: "",
    address: "",
    website: "",
    logo: "",
    updated_at: new Date().toISOString().slice(0, 10),
    notify: emptyNotify(),
    contacts: [],
    domains: {}
  };
}

export function normalizeBrand(value: unknown): BrandDocument {
  const input = value && typeof value === "object" ? value as Record<string, unknown> : {};
  const contacts = Array.isArray(input.contacts)
    ? input.contacts.map(normalizeContact).filter((contact): contact is Contact => Boolean(contact))
    : [];

  const domains: BrandDocument["domains"] = {};
  const incomingDomains = input.domains && typeof input.domains === "object"
    ? input.domains as Record<string, unknown>
    : {};

  Object.keys(incomingDomains).sort((a, b) => a.localeCompare(b)).forEach((domainKey) => {
    const domain = normalizeText(domainKey).toLocaleLowerCase("vi-VN");
    if (!domain) return;
    const info = incomingDomains[domainKey] && typeof incomingDomains[domainKey] === "object"
      ? incomingDomains[domainKey] as Record<string, unknown>
      : {};
    domains[domain] = {
      expire: normalizeDateString(info.expire),
      hosting_note: normalizeText(info.hosting_note),
      notify: normalizeNotify(info.notify)
    };
  });

  return {
    company: normalizeText(input.company),
    address: normalizeText(input.address),
    website: normalizeText(input.website),
    logo: normalizeText(input.logo),
    updated_at: normalizeDateString(input.updated_at) || new Date().toISOString().slice(0, 10),
    notify: normalizeNotify(input.notify),
    contacts,
    domains
  };
}

export function defaultSettings(): SettingsDocument {
  return {
    telegram: {
      enabled: false,
      chat_id: "",
      bot_token_encrypted: ""
    },
    reminders: {
      days: [30, 14, 7, 3, 1, 0],
      notify_overdue: true,
      repeat_after_days: 1
    },
    last_run: null
  };
}

export function normalizeSettings(value: unknown, previous: SettingsDocument = defaultSettings()): SettingsDocument {
  const input = value && typeof value === "object" ? value as Record<string, unknown> : {};
  const telegram = input.telegram && typeof input.telegram === "object"
    ? input.telegram as Record<string, unknown>
    : {};
  const reminders = input.reminders && typeof input.reminders === "object"
    ? input.reminders as Record<string, unknown>
    : {};

  const days = Array.from(new Set(
    (Array.isArray(reminders.days) ? reminders.days : previous.reminders.days)
      .map((day) => Number(day))
      .filter((day) => Number.isInteger(day) && day >= 0 && day <= 365)
  )).sort((a, b) => a - b);

  return {
    telegram: {
      enabled: Boolean(telegram.enabled),
      chat_id: normalizeText(telegram.chat_id ?? previous.telegram.chat_id),
      bot_token_encrypted: normalizeText(telegram.bot_token_encrypted ?? previous.telegram.bot_token_encrypted)
    },
    reminders: {
      days: days.length ? days : previous.reminders.days,
      notify_overdue: reminders.notify_overdue === undefined ? previous.reminders.notify_overdue : Boolean(reminders.notify_overdue),
      repeat_after_days: Math.max(1, Number(reminders.repeat_after_days ?? previous.reminders.repeat_after_days) || 1)
    },
    last_run: previous.last_run
  };
}
