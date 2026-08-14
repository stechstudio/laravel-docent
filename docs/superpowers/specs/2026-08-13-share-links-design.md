# Share links

**Date:** 2026-08-13
**Status:** Approved

## Problem

Docent is deployed as gated documentation: the host puts `auth` on the docs
route group and only signed-in users browse freely. That is the point — the
documentation describes how an application works in enough detail that its
owner does not want it public.

Two things break against that wall.

A support reply links to a documentation page, and the recipient is not
currently signed in. They hit a login screen instead of the answer, which is
friction at exactly the moment the answer mattered.

A sales conversation turns on one feature, and the vendor genuinely wants that
one page readable by someone who has no account at all.

Both need a way to hand out a single page without handing out the site.

## Non-goals

- **Sharing a section or a subtree.** Cross-links, `:::include` partials, and
  navigation suppression all multiply with scope. A section index page can be
  shared; the links inside it are dead, which is honest.
- **Reproducing the sharer's view.** An earlier draft carried the sharer's
  identity in the token so a recipient saw the page as they saw it. Rejected —
  see Rejected alternatives.
- **Any viewer-specific data reaching a share link.** This is what makes the
  feature safe to ship without a PII review step in the UI.

## Behaviour

A staff member viewing a documentation page copies a share URL for it. The URL
is the page's own URL with one query parameter added:

```
https://app.example.com/docs/billing/invoices?s=fyeQ7mPv9LeKd2
```

Who opens it decides what they get:

| Recipient | Result |
| --- | --- |
| Signed in | Their normal page — full navigation, search, assistant, their own `:::can` blocks and resolved values. The token is inert. |
| Not signed in, valid token | Neutral render: the page content alone, centred, no chrome. |
| Not signed in, no/expired/tampered token | The host's usual login redirect. |

The signed-in case is deliberate. A share link is often sent *because* the
recipient needs to see something in their own context, and a colleague who
follows one should land on the real page, not a stripped copy of it.

### The neutral render

Authorization questions resolve through `DocentManager::guestContext()` — the
same anonymous context the sitemap already uses, so `:::can`, `:::cannot`, and
`:::audience` blocks behave exactly as they do for a logged-out visitor.

`{{ value: }}` tokens render their registered label in braces (`{Account
plan}`) rather than resolving, reusing the placeholder path
`AgentMarkdownRenderer` already takes for the agent feed. No host resolver runs
for a share request, so no host data can reach one.

Layout is a new minimal view: centred content, no sidebar, no top-bar
navigation, no search, no assistant, no table of contents, no previous/next.
`noindex, nofollow`, and share URLs never appear in the sitemap.

Links inside the content stay as they are. A recipient who follows one hits the
login wall, which is the intended nudge. A footer line makes the same offer
explicitly when `Route::has('login')`, or when `share.login_url` is set.

Views record to Insights under a new `share` surface, so a link opened four
hundred times is visible to the site owner.

## The token

Fourteen characters in a `?s=` parameter:

- **Expiry** — days since the Unix epoch, base36, three characters. Good to
  roughly 2097. Day granularity is what keeps this short; hour granularity
  would cost five characters and buys nothing for a link measured in weeks.
- **MAC** — `hash_hmac('sha256', "{$path}|{$day}", $key, binary: true)`,
  truncated to 8 bytes, base64url-encoded unpadded. Eleven characters.

`$path` is the request's path (`/docs/billing/invoices`), so the same string is
available when minting and when verifying, with no route-parameter
reconstruction on either side. `$key` derives from `APP_KEY` and the configured
`share.salt`; changing the salt invalidates every outstanding link at once.

Sixty-four bits of MAC is safe here because forgery means guessing a valid MAC
for one specific path against a rate-limited endpoint. The middleware applies
its own limiter when a token is present, so normal reader traffic is
unaffected. That limiter is load-bearing, not decoration.

Because the MAC covers the path, a token minted for one page cannot be edited
to reach another. The failure mode is the login redirect, not a leak.

## Assets are a credential question, not a gating question

`_images`, `_uploads`, and `_assets` sit inside the site route group behind the
host's guard, which is correct and stays that way. Nothing is ungated. Instead
the share token is a second credential that satisfies the same guard for an
enumerated set of routes:

- the page route (`home`, `show`)
- `image`, `upload`, `asset`

Everything else — `search`, `ask`, `insights.store`, `widget.*`, `llms`,
`llms-full`, `sitemap`, and every admin route — refuses the credential no
matter how valid the token. The boundary is one list in one file.

Each resource carries its own path-scoped MAC, `_assets` included. Scoping the
package's own stylesheet is technically unnecessary, but a uniform rule beats a
remembered exception, and it stops a leaked page token being edited into a
fetch against `_uploads`. Asset tokens inherit the expiry day of the page token
that authorised the render, so a page and its images die together.

## Components

### `Sharing` (`src/Sharing/Sharing.php`)

The affordance layer, resolved per site.

- `urlFor(string $slug, ?int $days = null): string` — mint a share URL.
- `decorate(string $url): string` — in share mode, append a path-scoped token;
  otherwise return the URL untouched.
- `credentialFor(Request $request): ?int` — the expiry day when the request
  carries a valid token for its own path, otherwise null.
- `enabled(): bool`, `canShare(?Authenticatable $user): bool`.

### `ShareToken` (`src/Sharing/ShareToken.php`)

Pure encode/decode. No framework dependencies beyond the key.

- `mint(string $path, int $day, string $key): string`
- `verify(string $path, string $token, string $key, int $today): bool`

Split out so the format is unit-testable without a request.

### `ShareCredential` (`src/Http/Middleware/ShareCredential.php`)

Four outcomes, in order. Ordinary reading — no `?s=` at all — leaves at the
first check and does no work.

1. Sharing off, no token, or a route outside the allowlist → continue.
2. Reader signed in → continue, token inert.
3. Token verifies → share mode on, continue with the guard removed.
4. Token present but invalid → count it against the limiter, continue to the
   guard, which answers as it would for any anonymous visitor.

"Signed in" means signed in *as this route understands it*. `$request->user()`
answers for the default guard alone, so a host routing documentation through
`auth:admin` would have its signed-in admins handed the anonymous render.
The middleware reads the guards named by the route's own authentication
middleware and asks those.

"With the guard removed" is not the same as running the matched action.
Skipping straight to the action would bypass everything below this middleware
— a host's `can:`, its security headers, Laravel's `SubstituteBindings` — so
the tail of the pipeline is rebuilt with only the guard filtered out.
Excluding it from the matched route would read better, but
`Route::withoutMiddleware()` mutates the route object, and under Octane that
mutation outlives the request: one share link would strip `auth` from the page
for everyone afterwards.

Registered by the service provider into the group's middleware array, and into
the kernel's priority map before `AuthenticatesRequests`, so it sorts after
`StartSession` and before the host's guard wherever the host wrote `auth`.
Hosts change nothing.

`share.before` names both the priority anchor and the middleware the token
stands in for, defaulting to the `AuthenticatesRequests` contract. A host whose
guard neither implements that contract nor extends `Authenticate` sets its own
class there, and Docent seats that class into the priority map first — Laravel
can only order against an anchor already in the map, and would otherwise append
the credential *behind* the guard, failing silently.

### `DocumentationMode`

Gains `enableShare(int $expiryDay)`, `share(): bool`, and `shareExpiryDay():
?int`, alongside the existing widget flag. Same request-scoped pattern already
used for the widget surface.

### URL decoration

`HtmlRenderer::imageUrl()` currently resolves docs-relative paths itself and
returns everything else untouched, which leaves admin-uploaded images (absolute
URLs baked in at upload time) with nowhere to be decorated. The image resolver
closure changes from taking a docs-root-relative path to taking the URL as
authored, moving the `DocsImagePath::relative()` call into `DocentManager`
where `DocsImagePath` already belongs. One funnel, one decoration point.

`DocentManager::asset()` decorates the same way.

Page links are deliberately not decorated.

## Configuration

```php
'share' => [
    'enabled'   => false,
    'gate'      => 'shareDocentPage',
    'salt'      => env('DOCENT_SHARE_SALT'),
    'ttl'       => 30,
    'max_ttl'   => 90,
    'throttle'  => '60,1',
    'login_url' => null,
    'before'    => \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
],
```

Off by default, per-site overridable like every other Docent config block.
`gate` is checked with `Gate::allows()`, so a host that never defines it gets
nobody able to share, which is the right failure direction.

## Interface

A share button in `partials/topbar-actions.blade.php`, rendered only when
`share.enabled` and the viewer passes `share.gate`. It opens a small panel with
the URL, a copy button, and an expiry selector defaulting to `share.ttl` and
bounded by `share.max_ttl`.

No PII warning anywhere. Neutral mode has nothing to warn about, which is most
of why it was chosen.

## Testing

Middleware fork:

- valid token, guest → neutral render
- valid token, signed-in user → normal page, full chrome, token inert
- expired token, guest → login redirect
- token from a different page's path → login redirect
- no token, guest → login redirect
- no token, signed-in user → normal page

Render:

- a `:::can` block does not render for a share viewer whose ability would
  otherwise pass for the sharer
- a `{{ value: }}` token renders `{Account plan}` rather than resolving
- no host value resolver is invoked during a share request
- the response carries `noindex, nofollow`
- share URLs are absent from `sitemap.xml`

Credential scope:

- `_images`, `_uploads`, `_assets` accept a path-scoped token
- `_search`, `_ask`, `llms.txt`, and admin routes reject a valid token
- an image token cannot be replayed against a different image path

Token format (unit):

- round-trip mint/verify
- tamper in path, tamper in MAC, tamper in expiry all fail
- salt change invalidates previously minted tokens

Sharing:

- `urlFor` respects `max_ttl`
- the share button is absent for a viewer failing `share.gate`
- the whole feature is inert when `share.enabled` is false

## Rejected alternatives

**Carrying the sharer's identity in the token.** The original proposal. It
fails in the direction the feature is mostly used: sharing outward, the
recipient would resolve `:::can` blocks against the *sharer's* abilities, so a
staff member's link would render staff-only content to a stranger. That is an
authorization bypass, not a disclosure risk a modal warning could cover.
Resolvers also run at open time rather than share time, so a link minted in
January shows June's data in June — wrong for a "what I saw" link and wrong for
a safe one. And a token that executes host code as a named user, with no
session and no re-authentication, is an impersonation grant rather than a link.

**Snapshotting the rendered HTML at share time.** Considered as the opt-in
companion to neutral mode, to serve the inbound case where a customer shows
support what they saw. Deferred: it needs a table, and neutral mode already
solves the friction that prompted the feature. Worth revisiting if the
diagnostic case turns out to matter.

**Database-backed share records.** Buys per-link revocation, view counts, and
an audit list of live links, and would shorten the token to about six
characters since a random lookup key carries no proof. Costs a migration.
Rejected for now: with no viewer data behind a link, a bounded TTL plus a
rotatable salt covers the realistic panic case.

**A dedicated `/docs/_s/{token}/{slug}` route.** Would avoid the middleware
entirely — a plain second route with `['web']` and a redirect for signed-in
users. Rejected on URL aesthetics, after confirming the middleware ordering
could be handled by the package rather than pushed onto hosts.

**Ungating `_assets`.** The package's own stylesheet is identical in every
install and leaks nothing, so making it public looked free. Replaced by the
credential model, which keeps one rule for every route instead of one rule and
an exception.
