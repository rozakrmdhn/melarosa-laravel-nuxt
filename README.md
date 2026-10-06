![preview](https://github.com/user-attachments/assets/2704bbc3-5d4f-4a65-b861-ab24f648d517)

# ⚡ Laravel Nuxt Boilerplate

[![](https://img.shields.io/badge/Laravel-v13-ff2e21.svg)](https://laravel.com)
[![](https://img.shields.io/badge/nuxt.js-v4-04C690.svg)](https://nuxt.com)
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Fk2so-dev%2Flaravel-nuxt.svg?type=shield)](https://app.fossa.com/projects/git%2Bgithub.com%2Fk2so-dev%2Flaravel-nuxt?ref=badge_shield)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/k2so-dev/laravel-nuxt/laravel.yml)](https://github.com/k2so-dev/laravel-nuxt/actions/workflows/laravel.yml)
[![CodeQL](https://github.com/k2so-dev/laravel-nuxt/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/k2so-dev/laravel-nuxt/actions/workflows/github-code-scanning/codeql)

The goal of the project is to create a template for development on Laravel and Nuxt with maximum API performance, ready-made authorization methods, image uploading with optimization and ready-made user roles.

<!-- TOC -->

- [Features](#features)
- [Services & Ports](#services--ports)
- [Requirements](#requirements)
- [Environment Configuration](#environment-configuration)
- [Installation](#installation)
    - [Standalone](#standalone)
    - [Docker](#docker)
- [Usage](#usage)
    - [Fetch wrapper](#fetch-wrapper)
    - [Authentication](#authentication)
    - [Nuxt Middleware](#nuxt-middleware)
    - [Laravel Middleware](#laravel-middleware)
- [Examples](#examples)
    - [Route list](#route-list)
    - [Demo](#demo)
- [Links](#links)
- [License](#license)

<!-- /TOC -->

## Features

 - [**Laravel 13**](https://laravel.com/docs/13.x) and [**Nuxt 4**](https://nuxt.com/)
 - [**Laravel Octane**](https://laravel.com/docs/13.x/octane) supercharges your application's performance by serving your application using high-powered application servers.
 - [**Laravel Socialite**](https://laravel.com/docs/13.x/socialite) OAuth providers
 - [**Laravel Sail**](https://laravel.com/docs/13.x/sail) Light-weight command-line interface for interacting with Laravel's default Docker development environment.
 - [**Spatie Laravel Permissions**](https://spatie.be/docs/laravel-permission/v6/introduction) This package allows you to manage user permissions and roles in a database.
 - [**Pest**](https://pestphp.com/) Elegant testing framework with expressive syntax and zero-config Laravel integration.
 - UI library [**Nuxt UI 4**](https://ui.nuxt.com/) based on [**TailwindCSS 4**](https://tailwindcss.com/) and [**Reka UI**](https://reka-ui.com/).
 - [**Martin Tile Server**](https://maplibre.org/martin/) High-performance PostGIS vector tile server for WebGIS maps and spatial datasets.
 - [**Pinia**](https://pinia.vuejs.org/ssr/nuxt.html) The intuitive store for Vue.js
 - Integrated pages: login, registration, password recovery, email confirmation, account information update, password change.
 - Temporary uploads with cropping and optimization of images.
 - Device management
 - Enhanced Fetch Wrappers : Utilizes `$http` and `useHttp`, which extend the capabilities of **Nuxt's** standard `$fetch` and `useFetch`.
 - Monorepo layout (`apps/api` + `apps/web`) with `just` task runner.

## Services & Ports

All services are dynamically configured via environment variables:

| Service | Port | Default URL | Environment Variable |
| :--- | :---: | :--- | :--- |
| **Laravel API** | `9000` | `http://localhost:9000` | `SERVER_PORT=9000` / `OCTANE_PORT=9000` |
| **Nuxt UI** | `4000` | `http://localhost:4000` | `PORT=4000` / `NUXT_PORT=4000` / `NITRO_PORT=4000` |
| **Martin Serve** | `9090` | `http://localhost:9090` | `MARTIN_PORT=9090` / `MARTIN_LISTEN_ADDRESSES=0.0.0.0:9090` |
| **Redis** | `6379` | `localhost:6379` | `REDIS_PORT=6379` |

## Requirements

 - PHP 8.4+ / Node 20+ (or [**Bun**](https://bun.com))
 - **PostgreSQL with PostGIS extension** (for WebGIS spatial datasets and Martin)
 - **Redis** is required for cache / session / queue / throttling
 - For Docker setup: [**Docker**](https://github.com/docker/docker-install) and [**just**](https://github.com/casey/just) task runner

## Environment Configuration

Configuration files are separated between **Local Development** and **Production Server** across the monorepo:

| Scope | Local Template / Active | Production Template |
| :--- | :--- | :--- |
| **Root (Docker/Orchestration)** | `.env.local` &rarr; `.env` | `.env.production` |
| **Backend API (`apps/api/`)** | `apps/api/.env.local` &rarr; `.env` | `apps/api/.env.production` |
| **Frontend Web (`apps/web/`)** | `apps/web/.env.local` &rarr; `.env` | `apps/web/.env.production` |

You can switch all environment files at once using the `just` task runner:

```bash
just env-local     # Activate LOCAL environment across root, api, and web
just env-prod      # Activate PRODUCTION environment across root, api, and web
```

## Installation
### Standalone

<details>
<summary>Show standalone instructions</summary>

1. **Install dependencies:**
   ```bash
   cd apps/api && composer install && cd ../web && npm install && cd ../..
   ```
2. **Setup environment:**
   ```bash
   just env-local
   # Or manually copy .env.local to .env in root, apps/api/, and apps/web/
   ```
3. **Initialize Laravel:**
   ```bash
   cd apps/api
   php artisan key:generate
   php artisan storage:link
   php artisan migrate --seed
   ```
4. **Run services:**
   - **Laravel API (Port 9000):**
     ```bash
     cd apps/api && php artisan serve
     # or using Octane:
     # php artisan octane:start
     ```
   - **Nuxt UI (Port 4000):**
     ```bash
     cd apps/web && npm run dev
     # or production preview:
     # npm run build && npm run serve
     ```
   - **Martin Tile Server (Port 9090):**
     ```bash
     martin --config martin.yaml
     ```

</details>

### Docker

Single `docker-compose.yml`: API runs on [**Laravel Sail**](https://laravel.com/docs/13.x/sail) with [**Octane**](https://laravel.com/docs/13.x/octane) in watch mode, web runs on `oven/bun:1`, Martin tile server runs on `maplibre/martin:latest`, plus `redis:8-alpine` for cache / queue / session / throttling. Orchestrated via `just`.

#### Installing `just`

```bash
brew install just                          # macOS
apt install just                           # Debian / Ubuntu
winget install --id Casey.Just --exact     # Windows
```

Other systems: see the [full list of packages](https://github.com/casey/just/tree/master#packages).

#### Lifecycle

```bash
just up -d                 # start full app (api + web + martin) — :9000 / :4000 / :9090
just prod -d               # production mode (Octane no-watch, web .output/)
just api -d                # api only — :9000
just web                   # web foreground, ephemeral — :4000
just stop                  # pause containers
just down                  # remove containers
```

Quick start:

```bash
just init                  # copies .env files, composer install, builds api image, bun install, key:generate, storage:link
just up -d
just a migrate --seed
```

Environment management & common commands:

```bash
just env-local             # switch all services to local environment
just env-prod              # switch all services to production environment
just                       # show all recipes
just build                 # rebuild the api image
just a migrate             # `php artisan migrate` in ephemeral container
just sail ...              # invoke Laravel Sail directly (e.g. `just sail tinker`)
just composer require ...
just bun add @vueuse/core
just pint                  # PHP linter
just test                  # Pest
just down -v               # stop and remove volumes
```

## Usage

### Fetch wrapper

To integrate with the API, enhanced `$http` and `useHttp` wrappers are used, expanding the functionality of Nuxt's standard `$fetch` and `useFetch`. The `$http` wrapper includes custom interceptors to replace the originals:
- `onFetch` instead of `onRequest`
- `onFetchError` instead of `onRequestError`
- `onFetchResponse` instead of `onResponse`
- `onFetchResponseError` instead of `onResponseError`

Additionally, `$http` predefines a base url, authorization headers, and proxy IP for convenient API work in SSR mode.
For example, the code for authorizing a user by email and password:
```vue
<script lang="ts" setup>
const router = useRouter();
const auth = useAuthStore();
const form = templateRef("form");
const state = reactive({
  email: "",
  password: "",
  remember: false,
});

const submitting = ref(false);

async function onSubmit(): Promise<void> {
  submitting.value = true;

  try {
    const res = await $http<{ ok?: boolean }>("login", {
      method: "POST",
      body: { ...state },
    });

    if (res?.ok) {
      await auth.login();
      await router.push("/");
    }
  } catch (err: any) {
    if (err?.response?.status === 422) {
      form.value?.setErrors(err.response?._data?.errors ?? []);
    }
  } finally {
    submitting.value = false;
  }
}
</script>
<template>
  <UForm ref="form" :state="state" @submit="onSubmit" class="space-y-4">
    <UFormField label="Email" name="email" required>
      <UInput
        v-model="state.email"
        placeholder="you@example.com"
        icon="i-heroicons-envelope"
        trailing
        type="email"
        autofocus
      />
    </UFormField>

    <UFormField label="Password" name="password" required>
      <UInput v-model="state.password" class="w-full" type="password" />
    </UFormField>

    <UTooltip :delay-duration="0" text="for 1 month" :content="{ side: 'right' }">
      <UCheckbox v-model="state.remember" class="w-full" label="Remember me" />
    </UTooltip>

    <div class="flex items-center justify-end space-x-4">
      <NuxtLink class="text-sm" to="/auth/forgot">Forgot your password?</NuxtLink>
      <UButton type="submit" label="Login" :loading="submitting" />
    </div>
  </UForm>
</template>
```
> In this example, a POST request will be made to the url **"/api/v1/login"**

### Authentication
**useAuthStore()** has everything you need to work with authorization.

Data returned by **useAuthStore**:
* `logged`: Boolean, whether the user is authorized
* `user`: User object, user stored in pinia store
* `fetchCsrf`: Function, fetch csrf token
* `fetchUser`: Function, fetch user data
* `login`: Function, login user
* `logout`: Function, remove local data and call API to remove session
* `hasRole`: Function, checks the role

### Nuxt Middleware

The following middleware is supported:
* `guest`: unauthorized users
* `auth`: authorized users
* `verified`: users who have confirmed their email
* `role-user`: users with the 'user' role
* `role-admin`: users with the 'admin' role

### Laravel Middleware

All built-in middleware from Laravel + middleware based on roles [**Spatie Laravel Permissions Middleware**](https://spatie.be/docs/laravel-permission/v6/basic-usage/middleware)

## Examples

### Route list

![routes](https://github.com/k2so-dev/laravel-nuxt/assets/15279423/39bb3021-a4d1-4472-8320-5a397809904d)

### Demo

https://github.com/k2so-dev/laravel-nuxt/assets/15279423/9b134491-1444-4323-a7a3-d87833dcdc67

## Links
* [Nuxt 4](https://nuxt.com/)
* [Nuxt UI 4](https://ui.nuxt.com/)
* [Tailwind CSS 4](https://tailwindcss.com/)
* [Laravel 13x](https://laravel.com/docs/13.x)
* [Martin Tile Server](https://maplibre.org/martin/)
* [MapLibre GL JS](https://maplibre.org/)

## License
[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2Fk2so-dev%2Flaravel-nuxt.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2Fk2so-dev%2Flaravel-nuxt?ref=badge_large)
