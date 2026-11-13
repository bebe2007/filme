(function () {
  'use strict';
  var KEY = 'fbm_privacy_v1', WATCH = 'fbm_watch_v2', DAYS = 180 * 86400000;
  var banner = document.getElementById('cookieBanner'), dialog = document.getElementById('privacyDialog');
  var functional = document.getElementById('consentFunctional'), external = document.getElementById('consentExternal');
  var notice = document.getElementById('mediaConsentNotice'), allow = document.getElementById('mediaConsentAllow');
  var pending = null, previousFocus = null, consent = null;
  try { var saved = JSON.parse(localStorage.getItem(KEY) || 'null'); if (saved && saved.version === 1 && saved.at > Date.now() - DAYS && saved.at <= Date.now()) consent = saved; else localStorage.removeItem(KEY); } catch (_) {}
  function cleanupProgress() {
    try {
      if (!consent || !consent.functional) { localStorage.removeItem(WATCH); localStorage.removeItem('fbm_favorites_v1'); return; }
      var favorites=JSON.parse(localStorage.getItem('fbm_favorites_v1')||'{}'); Object.keys(favorites).forEach(function(id){if(!favorites[id]||favorites[id].saved<Date.now()-DAYS)delete favorites[id];}); localStorage.setItem('fbm_favorites_v1',JSON.stringify(favorites));
      var rows = JSON.parse(localStorage.getItem(WATCH) || '{}'), clean = {};
      Object.keys(rows).filter(function (key) { return rows[key] && rows[key].updated > Date.now() - DAYS; }).sort(function (a,b) { return rows[b].updated - rows[a].updated; }).slice(0,40).forEach(function(key) { clean[key] = rows[key]; });
      if (Object.keys(clean).length) localStorage.setItem(WATCH, JSON.stringify(clean)); else localStorage.removeItem(WATCH);
    } catch (_) { try { localStorage.removeItem(WATCH); } catch (ignore) {} }
  }
  cleanupProgress();
  if (banner) banner.hidden = !!consent;
  function close() { pending = null; if (dialog && dialog.open) dialog.close(); if (previousFocus && document.contains(previousFocus)) previousFocus.focus(); }
  function save(f, e) {
    consent = {version:1, at:Date.now(), functional:!!f, external:!!e};
    try { localStorage.setItem(KEY, JSON.stringify(consent)); } catch (_) {}
    cleanupProgress();
    if (banner) banner.hidden = true;
    var next = consent.external ? pending : null;
    close();
    document.dispatchEvent(new CustomEvent('fbm:privacy-changed', {detail:consent}));
    if (next) next();
  }
  function open(callback) {
    pending = typeof callback === 'function' ? callback : null;
    previousFocus = document.activeElement;
    if (!dialog) return;
    functional.checked = !!(consent && consent.functional);
    external.checked = !!(consent && consent.external);
    document.getElementById('externalProvider').textContent = document.body.dataset.providerOrigin + ' / youtube-nocookie.com';
    notice.hidden = !pending; allow.hidden = !pending;
    if (!dialog.open) dialog.showModal();
    document.getElementById('privacyClose').focus();
  }
  document.querySelectorAll('[data-consent]').forEach(function(button) { button.addEventListener('click', function() { var all = button.dataset.consent === 'all'; save(all, all); }); });
  document.querySelectorAll('[data-privacy-open]').forEach(function(button) { button.addEventListener('click', function(e) { e.preventDefault(); open(); }); });
  document.getElementById('privacySave').addEventListener('click', function() { save(functional.checked, external.checked); });
  allow.addEventListener('click', function() { save(functional.checked, true); });
  document.getElementById('privacyClose').addEventListener('click', close);
  dialog.addEventListener('cancel', function() { pending = null; });
  window.FBMPrivacy = {
    functional:function() { return !!(consent && consent.functional); },
    external:function() { return !!(consent && consent.external); },
    requestMedia:function(callback) { if (consent && consent.external) callback(); else open(callback); },
    open:function() { open(); }
  };
})();
