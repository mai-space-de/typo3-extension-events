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
     * @param {string} viewType
     * @returns {boolean}
     */
    function isListView(viewType) {
      return typeof viewType === 'string' && viewType.indexOf('list') === 0;
    }

    /**
     * @param {{ start?: Date }} info
     * @param {string} viewType
     * @returns {Array}
     */
    function resolveEventsForView(info, viewType) {
      if (!isListView(viewType)) {
        return events;
      }

      var rangeStart = info && info.start instanceof Date ? info.start : startOfToday();
      var from = new Date(Math.max(rangeStart.getTime(), startOfToday().getTime()));

      return events
        .filter(function (event) {
          var start = event && event.start ? new Date(event.start) : null;
          return start instanceof Date && !isNaN(start.getTime()) && start >= from;
        })
        .slice(0, listLimit);
    }

    var activeViewType = initialView;

    var calendar = new window.FullCalendar.Calendar(mount, {
      initialView: initialView,
      initialDate: initialDate,
      locale: locale,
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listUpcoming',
      },
      views: {
        listUpcoming: {
          type: 'list',
          duration: { months: 12 },
          buttonText: root.getAttribute('data-label-list') || 'List',
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
          activeViewType = info.view.type;
        }
      },
      events: function (info, successCallback) {
        var viewType = activeViewType;
        if (calendar && calendar.view && calendar.view.type) {
          viewType = calendar.view.type;
        }
        successCallback(resolveEventsForView(info, viewType));
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
