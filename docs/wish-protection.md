# Protecting the wishing

Wishing is public and needs no sign-in. That makes it a target for scripts
that flood the list. `src/WishGuard.php` puts several layers in front of it.
None of them uses a third-party service, and no plain-text IP address is
stored.

## The layers

| Layer | What happens | Where it is set |
| --- | --- | --- |
| Closed room | The moderator closes the room. The repertoire stays visible, the *Wish* buttons and the suggestion form disappear, a notice stands in the header of every page. A late submission is turned away with *The room is closed right now.* | *Close room* under Rooms, see [Rooms](rooms.md) |
| Global limits | At most N open wishes per room, at most M wishes per minute across all visitors | *Limits*: open wishes per room, wishes per minute (everyone) |
| Limit per sender | At most X per minute and Y per hour from the same address | *Limits*: wishes per minute / per hour per sender |
| Brake per session | Minimum gap between two wishes in the same browser session. Suggestions have a clock of their own. | *Limits*: seconds between two wishes |
| Repeated wishes | A song that is already open on the list is not added a second time. The wish counts on the existing entry (see [Usage](usage.md)). It stays subject to the per-sender and per-minute limits, but not to the cap on open wishes, since it adds no row. | – |
| Bot trap | An invisible form field. If it is filled, or if the signed timestamp is missing or forged, the wish is silently discarded and the sender sees a success message. | – |
| Minimum time | A signed timestamp in the form. Submitting sooner than N s after the page load is rejected with a request to click once more. A form older than 6 h is rejected with a request to reload the page. | *Limits*: seconds after the page load |

The bot trap and the minimum time protect the suggestion form as well. The
checks run in this order: closed room, bot trap and timestamp, session brake,
song exists in the room, then the global and per-sender limits. Only an
accepted wish is counted for the per-sender and per-minute limits;
suggestions are not.

Those checks decide the message a guest gets. What actually happens is
decided by the write. The unique keys on the wishes and the suggestions mean
a song is listed once per room whatever order two requests arrive in:
`WishRepository::wish()` turns a second wish into a count-up,
`SuggestionRepository::add()` lets a duplicate fall away, and both take a row
back out again when it made the list longer than the cap. The per-minute and
per-sender limits and the session brake stay best-effort – they dampen a
flood, and making them exact would mean a lock on the one path that has to
stay quick.

## The Limits page

Admins set the limits under **Limits** (`/admin/limits`, in the
*Administration* group of the account menu). They apply to every room alike.
A limit of `0` switches it off. The page also carries the two limits on
[song suggestions](suggestions.md) and the page size of the lists.

| Setting | Default | Allowed |
| --- | --- | --- |
| Open wishes per room | 200 | 0 – 100000 |
| Wishes per minute, everyone | 30 | 0 – 10000 |
| Wishes per minute per sender | 3 | 0 – 1000 |
| Wishes per hour per sender | 20 | 0 – 10000 |
| Seconds between two wishes | 5 | 0 – 3600 |
| Seconds after the page load | 2 | 0 – 60 |
| Open suggestions per room | 200 | 0 – 100000 |
| Seconds between two suggestions | 10 | 0 – 3600 |
| Rows per page | 50 | 10 – 500 |

*Rows per page* sets the page size of every paged list: the repertoire, the
wish list, the suggestions, the rooms, the users, the pages, the logos, and
both columns of a room's song picker and of the footer. The wish list, the
users, the pages, the logos, the footer and the song picker fall back to the
last page when a page beyond the last is requested – after the last entry of a
page was moved or deleted, say. The repertoire, the suggestions and the rooms
list show an empty page instead.

The values live in the `settings` table as `limits.<name>` (`src/Limits.php`).
A value at its default is stored too, so a later change of the built-in
default does not silently change a site whose admins looked at the page and
left the number as it was. A stored value outside its range counts as the
default.

## Senders without storing IPs

The per-sender limit needs one attribute per visitor. What is stored is not
the IP address but an HMAC-SHA256 of the address with a secret that changes
daily.

* The secret is 32 random bytes. It lives in the `settings` table as
  `secret:<date>` and is created on the first access of the day. Only today's
  and yesterday's secrets are kept; older ones are deleted.
* The entries in `wish_throttle` (the HMAC and a timestamp) are kept for an
  hour. Older entries are deleted each time a wish is accepted.
* As long as the daily secret exists, the value is a pseudonym in the sense of
  the GDPR. Afterwards it can no longer be attributed to anyone.
* The same secret signs the timestamp in the form. Today's and yesterday's
  secret are both accepted, so a form opened around midnight is not rejected.

## Behind a reverse proxy

Behind a reverse proxy the visitor's address is in `X-Forwarded-For`. The
application uses the last entry of that header: the proxy itself appends that
one, anything before it can be invented by the client. It does so only if
`trust_proxy` is set (`TRUST_PROXY=1`; `compose.yml` sets it for the Docker
stack, `config.example.php` defaults to `0`). Without it, only the connection's
own address counts.

If the web server is also reachable directly, leave the value at `0`. Otherwise
a forged header bypasses the per-sender limit.

## What the application cannot do

A flood of requests that overwhelms the web server itself belongs to the layer
in front – Traefik's rate-limit middleware or the hoster.
