# Pulsefy

Personal Spotify companion — explore your listening history, control playback, sync your library, and enrich tracks with lyrics and AI insights. Single-user app built as a portfolio piece with Laravel and Vue.

**Stack:** Laravel 13 · Vue 3 · Inertia v3 · MySQL · Redis · Horizon · Reverb · TypeScript · Tailwind CSS v4

## Features

- Dashboard, recently played, artists, albums, library, and search
- Full player (queue, devices, volume, shuffle, repeat)
- Synced lyrics with AI translation and romanization (Gemini)
- AI track insights and discovery mode
- Spotify OAuth login · Last.fm integration · real-time updates via Reverb

<details>
<summary><strong>Feature details</strong></summary>

| Area | What it does |
|------|----------------|
| **Dashboard** | Top tracks and artists (4w / 6m / all time), deduplicated recently played |
| **Recently played** | History by day with play counts |
| **Library** | Playlists, folders, Spotify sync, liked songs |
| **Player** | Now playing, queue, device transfer, favorites |
| **Lyrics** | LRCLIB sync lyrics + Gemini translation / romanization |
| **Insights** | Gemini-generated track context (queued on Horizon) |
| **Discovery** | Like, skip, and ignore flow with rate limits |
| **Auth** | Spotify OAuth only — no email/password login |

Music metadata comes from the Spotify API live; the database stores users, lyrics cache, insights, and app state — not a full library mirror.

</details>

## Prerequisites

| Requirement | Notes |
|-------------|--------|
| PHP 8.3+ | Extensions: `pdo_mysql`, `redis`, and standard Laravel set |
| Composer 2 · Node.js 20+ · npm | |
| MySQL 8 · Redis | Local install, or via Sail |
| **Spotify** app | [Developer Dashboard](https://developer.spotify.com/dashboard) |
| **Last.fm** API | [Create API account](https://www.last.fm/api/account/create) |
| **Google Gemini** API key | [Google AI Studio](https://aistudio.google.com/apikey) |

Docker is only required if you use Sail (`composer setup:sail`).

## Quick start

```bash
git clone <repository-url> pulsefy && cd pulsefy
composer setup          # local: MySQL + Redis must be running
# Add API keys to .env (see below), then:
composer dev            # serve, Horizon, Pail, Vite
php artisan reverb:start  # separate terminal — WebSockets
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and sign in with Spotify.

<details>
<summary><strong>Local setup</strong></summary>

1. Create a MySQL database named `pulsefy`.
2. Start **MySQL** and **Redis**.
3. Run `composer setup` (installs deps, `.env`, key, migrations, frontend build).
4. Fill in `.env` — [external services](#external-services) and [environment variables](#environment-variables).
5. Run `composer dev` and `php artisan reverb:start`.

</details>

<details>
<summary><strong>Docker (Sail)</strong></summary>

```bash
composer setup:sail
```

Opens [http://localhost](http://localhost). Use `./vendor/bin/sail` for Artisan and npm:

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan reverb:start
```

</details>

<details>
<summary><strong>Laravel Herd</strong></summary>

After `composer setup`:

```bash
bin/env.sh local
php artisan config:clear
```

Set URLs to your Herd site:

```env
APP_URL=https://pulsefy.test
SPOTIFY_REDIRECT_URI=https://pulsefy.test/api/spotify/callback
LASTFM_CALLBACK_URI=https://pulsefy.test/api/lastfm/callback
```

Register the same callbacks in Spotify and Last.fm. Run `php artisan horizon`, `npm run dev`, and `php artisan reverb:start` alongside Herd.

</details>

<details>
<summary><strong>External services</strong></summary>

All three are **required** for the app to work as intended.

### Spotify

1. Create an app at [developer.spotify.com/dashboard](https://developer.spotify.com/dashboard).
2. Add redirect URI: `{APP_URL}/api/spotify/callback`
3. Set `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET`, and `SPOTIFY_REDIRECT_URI` in `.env`.

| Environment | Example redirect |
|-------------|------------------|
| `artisan serve` | `http://127.0.0.1:8000/api/spotify/callback` |
| Sail | `http://localhost/api/spotify/callback` |
| Herd | `https://pulsefy.test/api/spotify/callback` |

### Last.fm

1. Create credentials at [last.fm/api/account/create](https://www.last.fm/api/account/create).
2. Callback: `{APP_URL}/api/lastfm/callback`
3. Set `LASTFM_API_KEY`, `LASTFM_SHARED_SECRET`, and `LASTFM_CALLBACK_URI`.

### Gemini (Google AI)

1. Create an API key at [Google AI Studio](https://aistudio.google.com/apikey).
2. Set `GEMINI_API_KEY` in `.env`.
3. Lyrics and track insights use the models in `config/services.php` (`AI_LYRICS_*`, `AI_TRACK_INSIGHTS_*`).

</details>

<details>
<summary><strong>Environment variables</strong></summary>

Copy `.env.example` → `.env`. Setup generates `APP_KEY` automatically.

**Required for core functionality**

```env
APP_URL=...

SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
SPOTIFY_REDIRECT_URI=

LASTFM_API_KEY=
LASTFM_SHARED_SECRET=
LASTFM_CALLBACK_URI=

GEMINI_API_KEY=
```

**Database & Redis (local defaults)**

```env
DB_HOST=127.0.0.1
DB_DATABASE=pulsefy
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

**Sail defaults** — run `bin/env.sh sail` or use `composer setup:sail`:

```env
DB_HOST=mysql
DB_USERNAME=sail
DB_PASSWORD=password
REDIS_HOST=redis
```

**Reverb** — defaults in `.env.example` work for local dev; align `REVERB_HOST` with how the browser reaches your app.

</details>

<details>
<summary><strong>Development commands</strong></summary>

| Command | Purpose |
|---------|---------|
| `composer setup` | First-time local setup |
| `composer setup:sail` | First-time Docker setup |
| `composer dev` | Server + Horizon + Pail + Vite |
| `composer test` | Pint + Pest |
| `composer ci:check` | Lint, format, types, tests |
| `bin/env.sh local` | Point `.env` at localhost MySQL/Redis |
| `bin/env.sh sail` | Point `.env` at Docker hostnames |

</details>

<details>
<summary><strong>Architecture</strong></summary>

- **Backend** — Invokable controllers, form requests, and services under `app/Services/Spotify/` for API access, token refresh, and caching.
- **Frontend** — Inertia pages in `resources/js/pages/`, shared components, deferred props with skeleton loaders.
- **Routes** — Wayfinder generates typed ` @/routes` and `@/actions` on build.
- **Queues** — Redis + Horizon (`insights`, library sync, and related jobs).
- **Realtime** — Reverb broadcasts insight progress and UI toasts to the client via Echo.

</details>

## License

MIT
