# TicketArget — Event Service

Laravel 13 (FrankenPHP) service owning the catalog data plane: venues, events,
tickets and users. Serves the public read API (`GET /events`, `GET /event/{id}`,
cache-first with a read-replica split) and Sanctum-protected admin writes
(`events:write` token).

Part of the [TicketArget platform](https://github.com/ikarolaborda/ticketarget) —
run it from the aggregator repo, which provides the Docker topology and shared
`ticketarget/logging` package.
