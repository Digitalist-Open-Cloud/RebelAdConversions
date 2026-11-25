(function () {
  // The URL parameters an ad platform puts its click ID in.
  var PARAMETERS = ['gclid', 'msclkid'];

  var HOOKS = ['log', 'link', 'goal', 'event', 'sitesearch', 'ecommerce', 'ping',
      'contentImpression', 'contentInteraction'];

  var consent = false;
  var sent = false;
  var click = readClick();

  // The click the page was opened from, as "parameter:ID". It is kept in memory only.
  function readClick() {
      var pairs = String(window.location.search).substring(1).split('&');
      for (var i = 0; i < pairs.length; i++) {
          var pair = pairs[i].split('=');
          if (PARAMETERS.indexOf(pair[0]) !== -1 && /^[A-Za-z0-9_.-]{8,200}$/.test(pair[1] || '')) {
              return pair[0] + ':' + pair[1];
          }
      }
      return '';
  }

  function trackingParams() {
      if (!consent || !click) {
          return '';
      }
      sent = true;
      return '&rac=' + click;
  }

  function setConsentGiven() {
      consent = true;
      // The page view may be sent already. The wait lets a page view that is queued go first.
      setTimeout(function () {
          var trackers = click && !sent ? window.Matomo.getAsyncTrackers() : [];
          for (var i = 0; i < trackers.length; i++) {
              trackers[i].ping();
          }
      }, 0);
  }

  function init() {
      var plugin = {};
      for (var i = 0; i < HOOKS.length; i++) {
          plugin[HOOKS[i]] = trackingParams;
      }
      window.Matomo.addPlugin('RebelAdConversions', plugin);
      window.Matomo.RebelAdConversions = { setConsentGiven: setConsentGiven };
      if (window.Matomo.retryMissedPluginCalls) {
          window.Matomo.retryMissedPluginCalls();
      }
  }

  if ('object' === typeof window.Matomo) {
      init();
  } else {
      if ('object' !== typeof window.matomoPluginAsyncInit) {
          window.matomoPluginAsyncInit = [];
      }
      window.matomoPluginAsyncInit.push(init);
  }
})();
