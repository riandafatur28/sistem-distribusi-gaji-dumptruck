//
// SIDIGAS — Async Navigator + Skeleton + Cache
// ------------------------------------------------
// Pola Turbolinks-style: intercept klik link → skeleton → fetch → swap → cache
// Downtime mendekati 0s, semua navigasi mulus.
//
(() => {
  'use strict';

  // ── Konfigurasi ──────────────────────────────────
  const SKELETON_DELAY = 80;       // ms — tunggu sebentar sebelum skeleton (cegah flash buat link cepat)
  const MIN_LOAD_MS = 300;         // ms — skeleton minimal muncul (biar transisi halus)
  const PAGE_CACHE_TTL = 60_000;   // ms — 1 menit cache halaman
  const PRELOAD_HOVER_DELAY = 100; // ms — hover prefetch delay

  // ── State ────────────────────────────────────────
  const pageCache = new Map();      // url → { html, title, sidebarHtml, timestamp }
  let isLoading = false;
  let skeletonTimer = null;
  let preloadTimer = null;
  let preloadedUrl = null;

  // ── DOM refs ──────────────────────────────────────
  const mainEl = document.querySelector('main');
  const sidebarEl = document.getElementById('sidebar');

  // ── Skeleton HTML ─────────────────────────────────
  const SKELETON = `
    <div id="skeleton-overlay" style="
      position: absolute; inset: 0; z-index: 20;
      background: #f8f9fa; border-radius: 8px;
      display: flex; flex-direction: column; gap: 16px; padding: 24px;
      animation: skeletonFadeIn 0.15s ease;
    ">
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div class="skeleton-line" style="width: 35%; height: 24px;"></div>
        <div class="skeleton-line" style="width: 55%; height: 16px;"></div>
      </div>
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
        ${'<div class="skeleton-card" style="height: 100px;"></div>'.repeat(3)}
      </div>
      <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
        ${'<div class="skeleton-line" style="width: 100%; height: 36px;"></div>'.repeat(5)}
      </div>
      <div style="display: flex; gap: 16px; margin-top: 8px;">
        <div class="skeleton-card" style="flex: 1; height: 80px;"></div>
        <div class="skeleton-card" style="flex: 1; height: 80px;"></div>
      </div>
    </div>
  `;

  const SKELETON_SMALL = `
    <div id="skeleton-overlay" style="
      position: absolute; inset: 0; z-index: 20;
      background: #f8f9fa; border-radius: 8px;
      display: flex; flex-direction: column; gap: 12px; padding: 20px;
      animation: skeletonFadeIn 0.15s ease;
    ">
      <div class="skeleton-line" style="width: 40%; height: 22px;"></div>
      ${'<div class="skeleton-line" style="width: 100%; height: 32px;"></div>'.repeat(4)}
      <div class="skeleton-line" style="width: 60%; height: 16px;"></div>
    </div>
  `;

  // ── Inject global skeleton styles ────────────────
  const styleEl = document.createElement('style');
  styleEl.textContent = `
    @keyframes skeletonFadeIn { from { opacity: 0; } to { opacity: 1; } }

    .skeleton-line {
      background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
      background-size: 200% 100%;
      animation: skeletonShimmer 1.5s ease-in-out infinite;
      border-radius: 6px;
    }
    .skeleton-card {
      background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%);
      background-size: 200% 100%;
      animation: skeletonShimmer 1.5s ease-in-out infinite;
      border-radius: 8px;
    }
    @keyframes skeletonShimmer {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }

    /* Page transition */
    .page-enter { opacity: 0; transform: translateY(8px); }
    .page-enter-active { opacity: 1; transform: translateY(0); transition: opacity 0.2s ease, transform 0.25s ease; }

    /* Preload indicator (optional) */
    .nav-preload { background: #f0f4ff !important; }
  `;
  document.head.appendChild(styleEl);

  // ── Helpers ──────────────────────────────────────
  function shouldIntercept(link) {
    if (!link || !link.href) return false;
    if (link.getAttribute('target') === '_blank') return false;
    if (link.getAttribute('download') !== null) return false;
    if (link.hostname !== window.location.hostname) return false;
    if (link.origin !== window.location.origin) return false;
    if (link.dataset.noTurbo !== undefined) return false;
    // Skip file downloads & external
    const ext = link.pathname.split('.').pop()?.toLowerCase();
    if (['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'csv'].includes(ext)) return false;
    return true;
  }

  function normalizeUrl(url) {
    const u = new URL(url, window.location.origin);
    // Remove trailing slash for consistency
    let path = u.pathname.replace(/\/+$/, '') || '/';
    return u.origin + path + u.search;
  }

  function getCsrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  // ── Skeleton ──────────────────────────────────────
  function showSkeleton() {
    hideSkeleton();
    if (!mainEl) return;
    // mainEl harus position relative
    if (getComputedStyle(mainEl).position === 'static') mainEl.style.position = 'relative';
    mainEl.insertAdjacentHTML('beforeend', SKELETON);
    mainEl.style.minHeight = mainEl.offsetHeight + 'px';
  }

  function hideSkeleton() {
    const existing = document.getElementById('skeleton-overlay');
    if (existing) existing.remove();
    if (mainEl) mainEl.style.minHeight = '';
  }

  // ── Navigasi ──────────────────────────────────────
  async function navigateTo(url) {
    if (isLoading) return;
    const normalized = normalizeUrl(url);
    if (normalized === normalizeUrl(window.location.href)) return;

    isLoading = true;

    // Show skeleton after brief delay (prevent flash for instant loads)
    skeletonTimer = setTimeout(() => {
      if (isLoading) showSkeleton();
    }, SKELETON_DELAY);

    const startTime = performance.now();

    try {
      let html, title, sidebarHtml;

      // Check cache
      const cached = pageCache.get(normalized);
      if (cached && Date.now() - cached.timestamp < PAGE_CACHE_TTL) {
        html = cached.html;
        title = cached.title;
        sidebarHtml = cached.sidebarHtml;
      } else {
        // Fetch
        const resp = await fetch(normalized, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        html = await resp.text();

        // Parse title
        const tMatch = html.match(/<title>(.*?)<\/title>/i);
        title = tMatch ? tMatch[1] : '';

        // Parse sidebar nav items (active state)
        const sMatch = html.match(/<aside[^>]*id="sidebar"[^>]*>([\s\S]*?)<\/aside>/i);
        sidebarHtml = sMatch ? sMatch[1] : null;

        // Cache
        pageCache.set(normalized, { html, title, sidebarHtml, timestamp: Date.now() });
      }

      // Enforce minimum load time for smooth transitions
      const elapsed = performance.now() - startTime;
      if (elapsed < MIN_LOAD_MS) {
        await new Promise(r => setTimeout(r, MIN_LOAD_MS - elapsed));
      }

      // Swap content
      swapContent(html, title, sidebarHtml, normalized);

    } catch (err) {
      console.warn('[SIDIGAS] Fetch failed, falling back to full load:', err);
      window.location.href = normalized;
    } finally {
      clearTimeout(skeletonTimer);
      hideSkeleton();
      isLoading = false;
    }
  }

  // ── Content swap ──────────────────────────────────
  function swapContent(html, title, sidebarHtml, url) {
    // Parse HTML
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    // === Main content ===
    const newMain = doc.querySelector('main');
    if (newMain && mainEl) {
      // Animate out
      mainEl.style.opacity = '0';
      mainEl.style.transform = 'translateY(6px)';
      mainEl.style.transition = 'opacity 0.12s ease, transform 0.15s ease';

      setTimeout(() => {
        // Replace content
        mainEl.innerHTML = newMain.innerHTML;

        // Re-apply classes
        mainEl.className = newMain.className;

        // Animate in
        requestAnimationFrame(() => {
          mainEl.style.opacity = '1';
          mainEl.style.transform = 'translateY(0)';
        });

        // Clean up transition styles after animation
        setTimeout(() => {
          mainEl.style.transition = '';
          mainEl.style.opacity = '';
          mainEl.style.transform = '';
        }, 250);
      }, 120);
    }

    // === Title ===
    if (title) {
      document.title = title;
      // Also update header h1
      const h1 = document.querySelector('header h1');
      if (h1) {
        const newH1 = doc.querySelector('header h1');
        if (newH1) h1.textContent = newH1.textContent;
      }
    }

    // === Sidebar ===
    if (sidebarHtml && sidebarEl) {
      const nav = sidebarEl.querySelector('nav');
      if (nav) {
        const newSidebar = doc.querySelector('#sidebar nav');
        if (newSidebar) {
          nav.innerHTML = newSidebar.innerHTML;
        }
      }
    }

    // === Push URL ===
    window.history.pushState({}, '', url);
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // === Re-init page-specific scripts ===
    // Re-run <script> tags that were in main content
    const scripts = mainEl?.querySelectorAll('script');
    if (scripts) {
      scripts.forEach(oldScript => {
        const newScript = document.createElement('script');
        Array.from(oldScript.attributes).forEach(attr => {
          newScript.setAttribute(attr.name, attr.value);
        });
        newScript.textContent = oldScript.textContent;
        oldScript.parentNode?.replaceChild(newScript, oldScript);
      });
    }

    // Dispatch custom event for page-specific initializers
    document.dispatchEvent(new CustomEvent('sidigas:page-loaded', { detail: { url } }));
  }

  // ── Preload on hover ──────────────────────────────
  function preloadPage(url) {
    const normalized = normalizeUrl(url);
    if (pageCache.has(normalized)) return;
    if (normalized === normalizeUrl(window.location.href)) return;

    fetch(normalized, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
    }).then(resp => {
      if (!resp.ok) return;
      return resp.text();
    }).then(html => {
      if (!html) return;
      const tMatch = html.match(/<title>(.*?)<\/title>/i);
      const title = tMatch ? tMatch[1] : '';
      const sMatch = html.match(/<aside[^>]*id="sidebar"[^>]*>([\s\S]*?)<\/aside>/i);
      const sidebarHtml = sMatch ? sMatch[1] : null;
      pageCache.set(normalized, { html, title, sidebarHtml, timestamp: Date.now() });
    }).catch(() => {});
  }

  // ── Event listeners ───────────────────────────────
  // Intercept clicks on <a> elements
  document.addEventListener('click', e => {
    const link = e.target.closest('a');
    if (!link || !shouldIntercept(link)) return;

    // Don't intercept if modifier keys (open in new tab)
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    e.preventDefault();
    navigateTo(link.href);
  });

  // Preload on hover
  document.addEventListener('mouseenter', e => {
    const link = e.target.closest('a');
    if (!link || !shouldIntercept(link)) return;

    clearTimeout(preloadTimer);
    preloadTimer = setTimeout(() => {
      preloadPage(link.href);
    }, PRELOAD_HOVER_DELAY);
  }, true);

  // Handle back/forward buttons
  window.addEventListener('popstate', () => {
    const url = window.location.href;
    pageCache.delete(normalizeUrl(url)); // Force fresh fetch on back
    navigateTo(url);
  });

  // ── Expose for debugging ──────────────────────────
  window.SIDIGAS = { navigateTo, preloadPage, pageCache, showSkeleton, hideSkeleton };

  console.log('[SIDIGAS] Async navigator ready. Caching', PAGE_CACHE_TTL / 1000 + 's');
})();
