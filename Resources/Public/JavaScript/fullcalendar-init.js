/**
 * fullcalendar-init.js
 *
 * Boots FullCalendar mounts for mai_events.
 * Expects window.FullCalendar (vendored global bundle) and optional locales-all.
 *
 * Markup contract (per mount):
 *   .mai-calendar[data-mai-calendar]
 *     data-initial-view, data-locale, data-initial-date (Y-m-d), data-list-limit
 *     > script[type="application/json"].mai-calendar__events-json
 *     > .mai-calendar__mount
 *     > dialog.mai-calendar-popup
 */
(function () {
  'use strict';

  /**
   * @param {HTMLElement} root
   */
  function initCalendar(root) {
    if (typeof window.FullCalendar === 'undefined' || !window.FullCalendar.Calendar) {
      return;
    }

    var mount = root.querySelector('.mai-calendar__mount');
    var jsonEl = root.querySelector('.mai-calendar__events-json');
    var dialog = root.querySelector('dialog.mai-calendar-popup');
    if (!mount || !jsonEl) {
      return;
    }

    var events = [];
    try {
      events = JSON.parse(jsonEl.textContent || '[]');
    } catch (e) {
      events = [];
    }

    var initialView = root.getAttribute('data-initial-view') || 'dayGridMonth';
    var locale = root.getAttribute('data-locale') || 'de';
    var initialDate = root.getAttribute('data-initial-date') || undefined;
    var listLimit = parseInt(root.getAttribute('data-list-limit') || '10', 10);
    if (isNaN(listLimit) || listLimit < 1) {
      listLimit = 10;
    }
    if (listLimit > 100) {
      listLimit = 100;
    }

    var titleEl = dialog ? dialog.querySelector('.mai-calendar-popup__title') : null;
    var timeEl = dialog ? dialog.querySelector('.mai-calendar-popup__time') : null;
    var locationEl = dialog ? dialog.querySelector('.mai-calendar-popup__location') : null;
    var descriptionEl = dialog ? dialog.querySelector('.mai-calendar-popup__description') : null;
    var closeBtn = dialog ? dialog.querySelector('.mai-calendar-popup__close') : null;

    if (dialog && closeBtn) {
      closeBtn.addEventListener('click', function () {
        dialog.close();
      });
      dialog.addEventListener('click', function (event) {
        if (event.target === dialog) {
          dialog.close();
        }
      });
    }

    /**
     * @param {import('@fullcalendar/core').EventApi} event
     */
    function openPopup(event) {
      if (!dialog) {
        return;
      }
      // Keep the dialog in the document root so centering uses the viewport,
      // not a scrolled/transformed calendar ancestor.
      if (dialog.parentElement !== document.body) {
        document.body.appendChild(dialog);
      }
      if (titleEl) {
        titleEl.textContent = event.title || '';
      }
      if (timeEl) {
        timeEl.textContent = formatEventTime(event);
      }
      if (locationEl) {
        var location = (event.extendedProps && event.extendedProps.location) || '';
        locationEl.textContent = location;
        locationEl.hidden = location === '';
      }
      if (descriptionEl) {
        var description = (event.extendedProps && event.extendedProps.description) || '';
        descriptionEl.innerHTML = description;
        descriptionEl.hidden = description === '';
      }
      dialog.showModal();
    }

    /**
     * @param {import('@fullcalendar/core').EventApi} event
     * @returns {string}
     */
    function formatEventTime(event) {
      if (event.allDay) {
        return root.getAttribute('data-label-all-day') || 'All day';
      }
      var start = event.start;
      var end = event.end;
      if (!start) {
        return '';
      }
      var opts = { dateStyle: 'medium', timeStyle: 'short' };
      try {
        var formatter = new Intl.DateTimeFormat(locale, opts);
        if (end) {
          return formatter.format(start) + ' – ' + formatter.format(end);
        }
        return formatter.format(start);
      } catch (err) {
        return start.toISOString();
      }
    }

    /**
     * @returns {Date}
     */
    function startOfToday() {
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      return today;
    }

    /**
     * @param {Date} date
     * @returns {Date}
     */
    function startOfDay(date) {
      var copy = new Date(date.getTime());
      copy.setHours(0, 0, 0, 0);
      return copy;
    }

    /**
     * @param {Date} date
     * @returns {Date}
     */
    function addDays(date, days) {
      var copy = new Date(date.getTime());
      copy.setDate(copy.getDate() + days);
      return copy;
    }

    /**
     * @param {string} viewType
     * @returns {boolean}
     */
    function isListView(viewType) {
      return typeof viewType === 'string' && viewType.indexOf('list') === 0;
    }

    /**
     * Upcoming events from today onwards (full set; paging applied separately).
     *
     * @returns {Array}
     */
    function getUpcomingEvents() {
      var from = startOfToday();
      return events.filter(function (event) {
        var start = event && event.start ? new Date(event.start) : null;
        return start instanceof Date && !isNaN(start.getTime()) && start >= from;
      });
    }

    /**
     * Current list page (next N upcoming events, offset by listOffset).
     *
     * @returns {Array}
     */
    function getListPageEvents() {
      return getUpcomingEvents().slice(listOffset, listOffset + listLimit);
    }

    /**
     * Visible range covering the current list page so FullCalendar shows those events.
     *
     * @returns {{ start: Date, end: Date }}
     */
    function computeListVisibleRange() {
      var page = getListPageEvents();
      if (page.length === 0) {
        var emptyStart = startOfToday();
        return { start: emptyStart, end: addDays(emptyStart, 1) };
      }

      var rangeStart = startOfDay(new Date(page[0].start));
      var last = page[page.length - 1];
      var lastEnd = last.end ? new Date(last.end) : new Date(last.start);
      var rangeEnd = addDays(startOfDay(lastEnd), 1);
      if (rangeEnd <= rangeStart) {
        rangeEnd = addDays(rangeStart, 1);
      }
      return { start: rangeStart, end: rangeEnd };
    }

    /**
     * @param {string} viewType
     * @returns {Array}
     */
    function resolveEventsForView(viewType) {
      if (!isListView(viewType)) {
        return events;
      }
      return getListPageEvents();
    }

    // Align with theme bp-down(md): below 48rem always use listUpcoming.
    var MOBILE_MQ = '(max-width: 47.98rem)';
    var LIST_VIEW = 'listUpcoming';
    var listOffset = 0;
    var desktopToolbar = {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listUpcoming',
    };
    var mobileToolbar = {
      left: 'prev,next today',
      center: 'title',
      right: '',
    };

    /**
     * @returns {boolean}
     */
    function isMobileViewport() {
      return window.matchMedia(MOBILE_MQ).matches;
    }

    var lastDesktopView = initialView;
    var startView = isMobileViewport() ? LIST_VIEW : initialView;
    var activeViewType = startView;
    var syncingViewport = false;
    var refreshingList = false;

    /**
     * Enable/disable list paging buttons based on remaining upcoming events.
     */
    function updateListNavState() {
      var prevBtn = mount.querySelector('.fc-prev-button');
      var nextBtn = mount.querySelector('.fc-next-button');
      if (!prevBtn && !nextBtn) {
        return;
      }

      if (!calendar || !isListView(calendar.view.type)) {
        if (prevBtn) {
          prevBtn.disabled = false;
        }
        if (nextBtn) {
          nextBtn.disabled = false;
        }
        return;
      }

      var upcomingCount = getUpcomingEvents().length;
      if (prevBtn) {
        prevBtn.disabled = listOffset <= 0;
      }
      if (nextBtn) {
        nextBtn.disabled = listOffset + listLimit >= upcomingCount;
      }
    }

    /**
     * Recompute list visible range + events after paging or entering list view.
     */
    function refreshListView() {
      if (!calendar || refreshingList) {
        return;
      }
      refreshingList = true;
      try {
        var range = computeListVisibleRange();
        calendar.gotoDate(range.start);
        calendar.refetchEvents();
        updateListNavState();
      } finally {
        refreshingList = false;
      }
    }

    /**
     * Force list on small viewports; restore the last desktop view when widening.
     */
    function syncViewportMode() {
      if (!calendar || syncingViewport) {
        return;
      }

      syncingViewport = true;
      try {
        var mobile = isMobileViewport();
        if (mobile) {
          calendar.setOption('headerToolbar', mobileToolbar);
          if (!isListView(calendar.view.type)) {
            lastDesktopView = calendar.view.type;
            listOffset = 0;
            activeViewType = LIST_VIEW;
            calendar.changeView(LIST_VIEW);
            refreshListView();
          }
          return;
        }

        calendar.setOption('headerToolbar', desktopToolbar);
        if (isListView(calendar.view.type) && lastDesktopView && calendar.view.type !== lastDesktopView) {
          activeViewType = lastDesktopView;
          calendar.changeView(lastDesktopView);
        }
      } finally {
        syncingViewport = false;
      }
    }

    var calendar = new window.FullCalendar.Calendar(mount, {
      initialView: startView,
      initialDate: initialDate,
      locale: locale,
      height: 'auto',
      headerToolbar: isMobileViewport() ? mobileToolbar : desktopToolbar,
      views: {
        listUpcoming: {
          type: 'list',
          // Range follows the current event page (not a fixed month/year window).
          visibleRange: function () {
            return computeListVisibleRange();
          },
          buttonText: root.getAttribute('data-label-list') || 'List',
        },
      },
      customButtons: {
        prev: {
          click: function () {
            if (isListView(calendar.view.type)) {
              if (listOffset <= 0) {
                return;
              }
              listOffset = Math.max(0, listOffset - listLimit);
              refreshListView();
              return;
            }
            calendar.prev();
          },
        },
        next: {
          click: function () {
            if (isListView(calendar.view.type)) {
              var upcomingCount = getUpcomingEvents().length;
              if (listOffset + listLimit >= upcomingCount) {
                return;
              }
              listOffset += listLimit;
              refreshListView();
              return;
            }
            calendar.next();
          },
        },
        today: {
          text: root.getAttribute('data-label-today') || 'Today',
          click: function () {
            if (isListView(calendar.view.type)) {
              listOffset = 0;
              refreshListView();
              return;
            }
            calendar.today();
          },
        },
      },
      buttonText: {
        today: root.getAttribute('data-label-today') || 'Today',
        month: root.getAttribute('data-label-month') || 'Month',
        week: root.getAttribute('data-label-week') || 'Week',
        list: root.getAttribute('data-label-list') || 'List',
      },
      datesSet: function (info) {
        if (info && info.view && info.view.type) {
          var nextType = info.view.type;
          // Reset paging when switching into the list view from another view.
          if (isListView(nextType) && !isListView(activeViewType)) {
            listOffset = 0;
            if (!refreshingList) {
              activeViewType = nextType;
              refreshListView();
            }
          }
          activeViewType = nextType;
          // Remember any desktop choice (incl. list) so resize-back restores it.
          if (!isMobileViewport() && !syncingViewport) {
            lastDesktopView = nextType;
          }
        }
        updateListNavState();
      },
      windowResize: function () {
        syncViewportMode();
      },
      events: function (info, successCallback) {
        var viewType = activeViewType;
        if (calendar && calendar.view && calendar.view.type) {
          viewType = calendar.view.type;
        }
        successCallback(resolveEventsForView(viewType));
      },
      eventClick: function (info) {
        var url = info.event.url;
        if (url) {
          info.jsEvent.preventDefault();
          window.location.assign(url);
          return;
        }
        info.jsEvent.preventDefault();
        openPopup(info.event);
      },
    });

    calendar.render();
    if (isListView(calendar.view.type)) {
      refreshListView();
    }
    syncViewportMode();
    updateListNavState();

    var mobileMql = window.matchMedia(MOBILE_MQ);
    if (typeof mobileMql.addEventListener === 'function') {
      mobileMql.addEventListener('change', syncViewportMode);
    } else if (typeof mobileMql.addListener === 'function') {
      mobileMql.addListener(syncViewportMode);
    }
  }

  function boot() {
    document.querySelectorAll('[data-mai-calendar]').forEach(initCalendar);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
