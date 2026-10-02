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

The compose limit is 2.94 GiB at maximum (three 256 MiB PHP services, 2 GiB renderer,
128 MiB Redis and 64 MiB nginx), plus shared database growth. This is a ceiling,
not a measured renderer peak or a reservation. Do not enable rendering until the
coordinator accepts capacity and a fixture render fits within its limit.

No automatic deployment exists in the upstream repository. This PR prepares
compatibility, import verification and deployment configuration; production
release still needs upstream merge, Mind safety and coordinated cutover.

Shared Auth is disabled by default. The prepared routes validate a fixed ES256
issuer and resolve an explicit old-user binding. Linking requires a fresh legacy
password plus a valid shared token; matching email or IdP metadata never creates
ownership/admin rights. Existing API-key hashes, user/plan IDs and jobs remain.
An expired shared login invalidates its Laravel session.

The coordinated browser callback is proposed at
`https://json2video.tural.ai/shared/callback`. OAuth code/refresh exchange uses
`https://id.tural.ai/auth/v1/oauth/token`; common consent belongs to the existing
`https://tural.ai/oauth/authorize` site. Callback/PKCE, server-held refresh tokens,
link/onboarding UI and immediate common session revocation still need acceptance
before enabling this flag. This PR does not claim complete browser SSO.

An offline fixture exercises actual MP4 encoding, image resizing and decoded
pixels without production DB/queues or network. Run `python tools/render_fixture.py`
with the renderer requirements and FFmpeg installed. The NumPy 1.26.4 pin prevents
the observed OpenCV 4.9 / NumPy 2 ABI failure. This small fixture does not establish
peak memory for Whisper, large media or concurrent workloads.
