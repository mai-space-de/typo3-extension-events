/**
 * fullcalendar-init.js
 *
 * Boots FullCalendar mounts for mai_events.
 * Expects window.FullCalendar (vendored global bundle) and optional locales-all.
 *
 * Markup contract (per mount):
 *   .mai-calendar[data-mai-calendar]
 *     data-initial-view, data-locale, data-initial-date (Y-m-d)
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
        descriptionEl.textContent = description;
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

    var calendar = new window.FullCalendar.Calendar(mount, {
      initialView: initialView,
      initialDate: initialDate,
      locale: locale,
      height: 'auto',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek',
      },
      buttonText: {
        today: root.getAttribute('data-label-today') || 'Today',
        month: root.getAttribute('data-label-month') || 'Month',
        week: root.getAttribute('data-label-week') || 'Week',
        list: root.getAttribute('data-label-list') || 'List',
      },
      events: events,
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
