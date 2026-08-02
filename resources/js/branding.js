/** Shared branding helpers for logos, favicon, and primary colour. */

export const BRAND = {
  primary: '#E7660B',
  ink: '#1E262A',
  mark: '/brand/app-icon.svg',
  horizontal: '/brand/logo-horizontal.svg',
  vertical: '/brand/logo-vertical.svg',
  favicon: '/favicon.svg',
};

export function storagePublicUrl(path, bust = false) {
  if (!path) return '';
  let url = String(path);
  if (!url.startsWith('http') && !url.startsWith('/')) {
    url = `/storage/${url.replace(/^\/+/, '')}`;
  }
  if (!bust) return url;
  const sep = url.includes('?') ? '&' : '?';
  return `${url}${sep}v=${Date.now()}`;
}

export function brandingLogoUrl(branding, { prefer = 'mark' } = {}) {
  // Wordmark stays the designed horizontal asset; uploaded logo is the app mark.
  if (prefer === 'horizontal') {
    return BRAND.horizontal;
  }
  const custom = branding?.logo_path ? storagePublicUrl(branding.logo_path) : '';
  return custom || BRAND.mark;
}

export function brandingFaviconUrl(branding) {
  const custom = branding?.favicon_path ? storagePublicUrl(branding.favicon_path) : '';
  return custom || BRAND.favicon;
}

export function brandingPrimary(branding) {
  const color = String(branding?.primary_color || BRAND.primary).trim();
  return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color) ? color : BRAND.primary;
}

/** Derive a small brand palette from the configured primary hex. */
export function applyBrandingToDocument(branding) {
  if (typeof document === 'undefined') return;

  const primary = brandingPrimary(branding);
  const root = document.documentElement;
  root.style.setProperty('--color-brand-600', primary);
  root.style.setProperty('--color-brand-700', shadeHex(primary, -0.14));
  root.style.setProperty('--color-brand-500', shadeHex(primary, 0.12));
  root.style.setProperty('--color-brand-100', mixHex(primary, '#ffffff', 0.88));

  const theme = document.querySelector('meta[name="theme-color"]');
  if (theme) theme.setAttribute('content', primary);

  const href = brandingFaviconUrl(branding);
  let link = document.querySelector("link[rel='icon']");
  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }
  link.type = href.endsWith('.svg') ? 'image/svg+xml' : 'image/x-icon';
  link.href = href.includes('?') ? href : `${href}?v=brand`;
}

function clamp(n) {
  return Math.max(0, Math.min(255, Math.round(n)));
}

function hexToRgb(hex) {
  let h = hex.replace('#', '');
  if (h.length === 3) h = h.split('').map((c) => c + c).join('');
  return {
    r: parseInt(h.slice(0, 2), 16),
    g: parseInt(h.slice(2, 4), 16),
    b: parseInt(h.slice(4, 6), 16),
  };
}

function rgbToHex({ r, g, b }) {
  return `#${[r, g, b].map((v) => clamp(v).toString(16).padStart(2, '0')).join('')}`;
}

function shadeHex(hex, amount) {
  const { r, g, b } = hexToRgb(hex);
  const t = amount < 0 ? 0 : 255;
  const p = Math.abs(amount);
  return rgbToHex({
    r: r + (t - r) * p,
    g: g + (t - g) * p,
    b: b + (t - b) * p,
  });
}

function mixHex(a, b, weightB) {
  const A = hexToRgb(a);
  const B = hexToRgb(b);
  const w = weightB;
  return rgbToHex({
    r: A.r * (1 - w) + B.r * w,
    g: A.g * (1 - w) + B.g * w,
    b: A.b * (1 - w) + B.b * w,
  });
}
