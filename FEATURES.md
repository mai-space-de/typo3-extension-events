## Event Records

* Event records — custom TCA record type (`tx_maievents_event`) with date, location, optional link (`LinkConfig`: page / url / file), categories (`sys_category`), and image
* Recurring events — virtual series via `recurrence_frequency` (`daily` / `weekly` / `monthly` / `monthly_weekday` / `yearly`) and optional `recurrence_until` (empty = never ends); `monthly_weekday` repeats the 1st/2nd/3rd/4th/last weekday of each month (weekday from `start_date`, position in `recurrence_month_weekday`); calendar expands occurrences on-the-fly within the preload window
* Registration records — event registration TCA (`tx_maievents_registration`) with attendee data, waiting-list support, and per-occurrence binding (`occurrence_start`)

## Calendar Views

* FullCalendar UI — vendored FullCalendar 6 (dayGridMonth / timeGridWeek / listUpcoming) with client-side toolbar navigation; mobile-friendly
* Mobile list lock — below theme `md` (48rem) the calendar always uses `listUpcoming` and hides month/week switchers; widening restores the last desktop view
* Initial view — FlexForm / GET `viewMode` maps to FullCalendar (`month`→`dayGridMonth`, `week`→`timeGridWeek`, `list`→`listUpcoming`)
* List limit — FlexForm `listLimit` (1–100, default 10) caps how many upcoming events the list view shows (next N from today); prev/next page through upcoming events by that page size (not by month/year); month/week still use the full preload set
* Event link — optional TCA `link` resolved to a frontend URL; click navigates when set
* Info popup — events without a link open a native `<dialog>` with title, time, location, and sanitized RTE description (links preserved)
* Category filter — FlexForm `categoryUid` on Calendar View / List plugin (empty = all categories); filters via `sys_category_record_mm`
* Scroll anchors — calendar root uses `id="c{uid}"` for deep-link scroll targets
* Preload window — events for roughly today −3 months … +12 months are serialized as FullCalendar JSON so navigation needs no page reload

## Export & Integration

* iCal export — export events as RFC 5545-compliant `.ics` files via `EventsController` (expanded occurrences in the export window; respects category filter)
* EventProviderInterface — pluggable data source aggregation; implement `Maispace\MaiEvents\EventProvider\EventProviderInterface` to contribute events from other extensions
* EventsDataProcessor — builds FullCalendar payload (`fullCalendarEvents`, `initialView`, `locale`, `contentUid`, `listLimit`) for Fluid mounts
* FlexForm settings — view mode (month / week / list), list limit (upcoming events in list mode), and category filter configurable per content element

## Event Registration

* Registration flow — Browse events → Register for a concrete occurrence → Confirm via email dispatched via `mai_mail`
* Capacity per occurrence — `max_attendees` / waiting list counted per `(event, occurrence_start)`
* Relative deadlines — for recurring series, the registration deadline is applied as an offset from each occurrence start
* Attendee list — per-event attendee management with CSV export (includes occurrence date) in the backend
* Waiting list — optional waiting-list support via `tx_maievents_registration.waiting_list` flag
