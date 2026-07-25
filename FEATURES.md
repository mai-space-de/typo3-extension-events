## Event Records

* Event records — custom TCA record type (`tx_maievents_event`) with date, location, categories (`sys_category`), and image
* Recurring events — virtual series via `recurrence_frequency` (`daily` / `weekly` / `monthly` / `yearly`) and optional `recurrence_until` (empty = never ends); calendar and list views expand occurrences on-the-fly within the viewed date window
* Registration records — event registration TCA (`tx_maievents_registration`) with attendee data, waiting-list support, and per-occurrence binding (`occurrence_start`)

## Calendar Views

* Month view — full monthly grid with event indicators
* Week view — seven-day view with event display
* List view — chronological event list with configurable limit
* Category filter — FlexForm `categoryUid` on Calendar View / List plugin (empty = all categories); filters via `sys_category_record_mm`

## Export & Integration

* iCal export — export events as RFC 5545-compliant `.ics` files via `EventsController` (expanded occurrences in the export window; respects category filter)
* EventProviderInterface — pluggable data source aggregation; implement `Maispace\MaiEvents\EventProvider\EventProviderInterface` to contribute events from other extensions
* EventsDataProcessor — builds the calendar grid and navigation for Fluid templates
* FlexForm settings — view mode (month / week / list), list limit, and category filter configurable per content element

## Event Registration

* Registration flow — Browse events → Register for a concrete occurrence → Confirm via email dispatched via `mai_mail`
* Capacity per occurrence — `max_attendees` / waiting list counted per `(event, occurrence_start)`
* Relative deadlines — for recurring series, the registration deadline is applied as an offset from each occurrence start
* Attendee list — per-event attendee management with CSV export (includes occurrence date) in the backend
* Waiting list — optional waiting-list support via `tx_maievents_registration.waiting_list` flag
