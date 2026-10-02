# Hostinger to shared PostgreSQL deployment

This preparation keeps source MySQL support. It does not switch production by itself.
The renderer and Laravel must use the same `DB_SCHEMA=json2video`, a dedicated
application login and the shared Supabase PostgreSQL database. The account must
have no grants to Brain, other projects or public application tables. Preserve
Laravel APP_KEY, users, API-key hashes, plan/owner links and existing URLs.

1. Obtain a verified source backup, including MySQL, Redis, uploads, renders,
   application configuration and tracked/untracked production changes.
2. Rehearse restoration and all Laravel migrations in an isolated PostgreSQL DB.
   Export the 16 application tables as JSON from a consistent MySQL snapshot.
   Unknown tables are preserved in the original backup, never imported as code.
3. Have Mind provision the project schema and its account through its reviewed
   migration/release process. This repository must not administer shared Supabase.
4. Run migrations with the dedicated role on the empty target schema. Import
   `tools/import_mysql_snapshot.py snapshot.json --schema json2video` using libpq
   environment or a private PGSERVICE file. It refuses populated tables, checks
   every column and row fingerprint and restores serial counters transactionally.
5. Fill a private `.env.rs8000` (gitignored); preserve APP_KEY and other existing
   credentials. No credentials belong in compose, PRs or shell arguments.
   Use `docker compose -f deploy/compose.rs8000.yml config --quiet` to validate.
6. Build and prepare without `--profile cutover`. The build should happen off
   the resource-constrained production target. Queue, scheduler and renderer
   must remain off until the final source freeze and synchronization.
7. The coordinator serializes shared capacity, routing and DB cutover. Preserve
   compatibility with AlMotion's existing JSON2Video endpoint and stored URLs.
   Stop source writers, capture the final DB/Redis/files, verify target, then
   enable the cutover profile. Never run both consumers on copied queues/sessions.
8. Verify authentication/owner filtering, existing job status, retained media,
   database isolation and HTTPS without real external generation or webhooks.
   Retain original services/data and verified backups for rollback.

The default compose ceiling is 3.94 GiB (three 256 MiB PHP services, 3 GiB renderer,
128 MiB Redis and 64 MiB nginx), plus shared database growth. This is a ceiling,
not a measured renderer peak or a reservation. Do not enable rendering until the
coordinator accepts capacity and a fixture render fits within its limit.

No automatic deployment exists in the upstream repository. This PR prepares
compatibility, import verification and deployment configuration; production
release uses the user-owned `TuralHeydarov/json2video` fork, Mind safety and
coordinated cutover. The original upstream repository and history remain retained.

Shared Auth is disabled by default. Configure a static confidential client with
`SUPABASE_OAUTH_CLIENT_ID`, `SUPABASE_OAUTH_CLIENT_SECRET` and exact callback
`SUPABASE_OAUTH_CALLBACK=https://json2video.tural.ai/shared/callback`; set
`APP_URL=https://json2video.tural.ai`. Code/refresh exchange uses the fixed
`https://id.tural.ai/auth/v1/oauth/token` endpoint. Verify the real issuer and signed
client/session claims before enabling. Common consent belongs to the existing
`https://tural.ai/oauth/authorize` site. Runtime needs only EXECUTE on reviewed
`tural_auth.session_active(uuid,uuid)`, not direct access to auth tables.

The browser flow implements one-use PKCE/state/nonce callbacks. Its database row
holds access/refresh credentials encrypted with the preserved APP_KEY. Refresh is
serialized by a database row lock across PHP workers. Laravel browser sessions
must use a server-side driver; the enabled flag forces a host-only Secure/HttpOnly
`__Host-json2video-session` cookie with Path=/ and SameSite=Lax. Tokens never enter
browser JavaScript or callback redirects. Keep callback query code/state out of
Caddy access logs.

Existing users sign in locally, then open Tural account and confirm their existing
password before proving the shared identity. No email merge or IdP admin grant is
allowed. New verified accounts receive the existing Free plan, a normal user and
default API key through the app's ordinary onboarding. Existing user/plan IDs,
admin roles, API-key hashes and jobs remain intact. Linked legacy password and
remember-me sessions are denied. Service API keys remain separate credentials.
Every shared browser request checks native session revocation. Local logout clears
the app session; global logout revokes Supabase sessions and reports unconfirmed
revocation explicitly. The former browser-supplied token bridge is replaced by
server code exchange.

Apply the two reviewed additive identity/browser-session migrations with the app
migrator after the isolated import. Keep the flag off until real login/onboarding,
two-account linking, callback replay, foreign-user data denial and cross-app global
logout pass. Offline provider fixtures do not establish live OAuth acceptance.

An offline fixture exercises actual MP4 encoding, image resizing and decoded
pixels without production DB/queues or network. Run `python tools/render_fixture.py`
with the renderer requirements and FFmpeg installed. The NumPy 1.26.4 pin prevents
the observed OpenCV 4.9 / NumPy 2 ABI failure. This small fixture does not establish
peak memory for Whisper, large media or concurrent workloads.

A bounded offline Whisper load plus two seconds of silent audio exited normally,
but reached exactly the 2 GiB memory ceiling. This has no safety headroom and does
not establish speech-processing capacity. The cutover renderer profile must stay
off until a representative bounded rehearsal and shared capacity approval establish
an adequate budget. The 3 GiB candidate ceiling is provisional and configurable with
JSON2VIDEO_RENDERER_MEMORY. A second 3 GiB silent-audio fixture peaked at
2,268,069,888 bytes (RSS 1,743,618,048), with no max/oom/oom_kill events; real speech,
large media and combined render/transcribe loads still need acceptance.


## Immutable target release

Successful main CI builds PHP, nginx and renderer images from that exact commit,
checks native extensions and an isolated PHP/nginx login, and loads the baked
Whisper model offline under a 3 GiB limit. Its `json2video-images-<SHA>` workflow
artifact includes the image archive and SHA-256. Download the exact green main
run, verify the hash and load images before the coordinated release. Set the three
JSON2VIDEO_*_IMAGE variables to its SHA tags. No production configuration belongs
in images or artifacts.

Target compose embeds reviewed code rather than mounting a mutable checkout.
Set JSON2VIDEO_RUNTIME_DIR to the verified target runtime copy containing
api-storage, renders, videos and Redis data. API storage and renders retain their
source contents and writable PHP UID permissions; nginx sees public uploads read-only.
Before any target consumer starts, restore the final source Redis state into its
own directory, preserving a rollback copy and avoiding stale target AOF files.
Redis, queue, scheduler and renderer are all behind `cutover`.

For migrations use a separate private migrator env file via JSON2VIDEO_ENV_FILE
and `docker compose -f deploy/compose.rs8000.yml run --rm --no-deps api php artisan
migrate --force`; runtime uses the restricted runtime role. Run only after the
reviewed shared DB admission, against the agreed isolated schema. Do not invoke
production migrations as part of build/preview. Keep ordinary runtime secrets in
the default private .env.rs8000.

The final switch must update AlMotion's existing HTTP endpoint or deploy a reviewed
compatibility proxy for it in the same serialized routing window. Until that is
verified, starting target consumers is blocked: accepting new jobs into the old
source database after final synchronization would split the queue. Preserve old
media URLs and the rollback services/data. Live acceptance must record the exact
image/commit, database isolation, retained jobs/media and shared Auth checks.


Set JSON2VIDEO_REDIS_PREFIX to the verified source PHP/renderer queue prefix.
Do not rename prefixes during restore: that would hide pending source jobs from
the target worker. PHP and renderer must receive the same preserved prefix.
