<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v5
- phpunit/phpunit (PHPUNIT) - v13
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

# TDD Workflow (project-specific, not managed by Boost)

No feature is done until it has tests at the layer(s) that actually exercise it. Pick the layer(s) by what changed — most changes need one, some need two, few need all three:

| Layer | Tool | Lives in | Covers |
|---|---|---|---|
| Unit | Pest | `tests/Unit` | Pure logic: services, actions, helpers, casts, arch rules. No DB, no HTTP. |
| Feature/Integration | Pest + `RefreshDatabase` | `tests/Feature` | Routes, controllers, form requests, policies, Inertia responses, DB side effects. |
| Frontend unit | Vitest + Testing Library | co-located `*.test.ts(x)` next to the source file | React components, hooks, and `resources/js/lib` utilities in isolation (jsdom, no server). |
| Browser/E2E | Pest Browser plugin (Playwright) | `tests/Browser` | Real user flows through a real Chromium instance against the actual Inertia/React app — auth flows, navigation, anything where the JS actually has to run correctly. |

Rules:
- New Eloquent model, action, or backend service → Unit test.
- New/changed route, controller, form request, or policy → Feature test (assert the HTTP/Inertia response, not implementation details).
- New/changed React component, hook, or `resources/js/lib` helper → Vitest test alongside it.
- New/changed user-facing flow (login, a new page, navigation, anything that must survive a real browser) → a Browser test in `tests/Browser`.
- Don't retrofit all four layers onto every one-line change — match effort to risk. A pure CSS/copy tweak doesn't need a Browser test; a new auth flow does.

Commands:
- `composer test` — full gate: Pint, PHPStan (larastan), Vitest, and the entire Pest suite (Unit + Feature + Browser). This is what must pass before calling work done.
- `composer test:unit` / `composer test:feature` / `composer test:browser` — run one Pest suite while iterating.
- `npm run test` / `npm run test:watch` — Vitest only.
- `php artisan test --compact --filter=testName` — fastest inner loop for a single test.

Notes:
- Prefer `--parallel` for the full suite — ~13s against ~22s serial (`composer test` and `composer test:browser` already pass it). It is a speed-up, not a correctness fix: serial passes too.
- **A browser run that takes minutes is hung, not slow.** Every browser file finishes in 3-6s. The usual cause is a click waiting forever on an element that is present but unclickable (see the selector note below). Kill the run when it stalls, and check for orphan headless Chromium before retrying — they leave the next runs flaky, which is what makes the problem look intermittent and environmental.
- `tests/Pest.php` binds `RefreshDatabase` for both `Feature` and `Browser` — every test gets a clean, migrated sqlite `:memory:` DB.
- Browser tests need Playwright's Chromium installed once locally: `npx playwright install chromium` (already wired into `composer setup`).
- In browser tests, never click by bare text when the label can appear twice. The persistent `SessionBar` stays in the DOM on the focus screen — collapsed and `inert`, but a text click resolves to it first and then waits forever on an invisible element. Scope the selector (`[data-slot="focus-timer"] button:text-is("Pausar")`). Parentheses in a label also break the CSS selector parser — use `button:has-text("…")` without them.
- Browser tests render the actual built frontend. If `public/hot` is left over from a killed `npm run dev`, the page will load blank (pointing at a dead dev-server URL) — delete it, or just don't have a stale dev server running when you run browser tests.
- **The first `composer test` right after `npm run build` blows the 300s Composer process timeout**; run it again and it passes in ~35s. Without a preceding build it passes first try. Reproduced twice — the suspect is OneDrive syncing the freshly rewritten `public/build`, not the suite. Don't go hunting a hung browser test over it.
- `tests/Unit/ArchTest.php` enforces baseline structure (controllers/requests/models suffixes and extends, no `dd`/`dump`/`die` in `App`). Extend it as new conventions get established, don't bypass it.

# Motion & UI (project-specific, not managed by Boost)

Motion here communicates state, hierarchy, or continuity. If an animation does none of those, it shouldn't exist. Every rule below is already implemented somewhere in the codebase — follow the existing example rather than inventing a parallel approach.

## Tokens — never magic values

All durations and curves live in `@theme` in `resources/css/app.css`:

| Token | Value | Use for |
|---|---|---|
| `duration-instant` | 80ms | `:active`, click feedback |
| `duration-fast` | 120ms | hover, color, focus ring |
| `duration-base` | 200ms | state change, progress |
| `duration-slow` | 320ms | panel / route entrance |
| `ease-out` | `cubic-bezier(0.16, 1, 0.3, 1)` | entering (asymmetric: decelerates) |
| `ease-in` | `cubic-bezier(0.7, 0, 0.84, 0)` | exiting (accelerates) |
| `ease-standard` | `cubic-bezier(0.4, 0, 0.2, 1)` | repositioning something already on screen |

- Never write raw `duration-200`, `duration-[350ms]`, or `ease-linear`. `ease-linear` in particular reads as mechanical and is what made the app feel dry before.
- **The project overrides Tailwind's own `ease-in`/`ease-out`.** Don't cross-reference the Tailwind docs for their values — read `app.css`.
- **Tailwind v4 namespace trap:** `duration-*` utilities resolve *only* from `--transition-duration-*` declared inside `@theme`. A duration token defined in `:root` compiles to **nothing at all**, with no error and no warning — the class simply doesn't exist and the transition is instant. After adding a token, confirm it materialized: build and grep the output for `.duration-<name>{`.

## Reduced motion

- `@layer base` in `app.css` already neutralizes *all* motion under `prefers-reduced-motion: reduce` with a global `*` guard. New CSS transitions/animations inherit it — do not add per-component media queries.
- It uses `1ms`, not `0s`, on purpose: Radix unmounts dialog/sheet by listening to `animationend`/`transitionend`, and `0s` never fires those. Don't "simplify" it.
- The corollary: **JS-driven animation escapes that guard.** Anything animated via rAF, Framer Motion, or GSAP must check `prefers-reduced-motion` itself. Prefer pure CSS; only reach for a library when the gain clearly justifies the cost, and say what the trade-off is.

## What to animate

- Default to `transform` and `opacity`. Animating `width`/`height`/`top`/`left`/`margin` forces layout on every frame.
- Exceptions are allowed when the layout change *is* the point — but they need an inline comment justifying it. `resources/js/components/session-bar.tsx` is the canonical template: it animates `height` because reserving and returning the 64px is exactly what stops the page from jumping, and only the container pays.
- Progress fills use `translateX(-N%)`, not `width` and not `scaleX` (`scaleX` flattens the right-end border radius). See `resources/js/components/ui/progress-bar.tsx`.

## Interaction feedback

- Every clickable surface responds **instantly**, at 0ms delay, regardless of how long the underlying request takes: `active:scale-[0.98] active:duration-instant` (or `active:scale-90` on small controls like checkboxes). `ui/button.tsx` already carries it — reuse `Button` instead of styling a bare `<button>`.
- Anything clickable must be a real `<button>` or `<a>`, never a `div` with `onClick` — keyboard focus and screen readers come first, and `focus-visible` rings are part of the motion budget, not an afterthought.

## Perceived latency

The optimistic-update pattern, used in `pages/history/index.tsx`, `pages/plan/index.tsx`, and `components/session-bar.tsx`:

1. Local pending state set on submit, rendered immediately.
2. `router.get`/`router.post` with `showProgress: false` — the top bar competing with local feedback reads as double reporting.
3. `onFinish` clears the pending state (rollback happens naturally when the server's props win).
4. The optimistic echo renders at reduced opacity (`opacity-60`) *in the slot the real item will occupy*, so nothing moves when it resolves. Lists in flight get `aria-busy` + `opacity-50` instead of being replaced by a spinner.

## Choreography and layout

- `.stagger-children` (defined in `app.css`) staggers direct children by 40ms, capped at 8 steps so long lists don't finish entering after the user has scrolled past. Use it instead of hand-rolled per-item delays. `animate-none` on a child is the documented escape hatch (used for the pending echo, which must not wait its turn).
- Don't nest staggers — a delayed child inside a delayed parent is exactly the excess these rules exist to prevent.
- Prefer always-rendering a section with an empty state over conditionally mounting it; a section popping into existence is a layout shift.
- UI that collapses but stays mounted (to animate its own entrance) must be hidden from assistive tech too: `aria-hidden` plus `inert={!visible || undefined}`. The `|| undefined` matters — `inert="false"` still inerts the subtree. This mounted-but-inert `SessionBar` is also why the browser-test note above insists on scoping text selectors.

## Testing

Motion changes are React-component changes: they follow the TDD rules above (Vitest alongside the file). Assert the *contract* — `data-visible`, `aria-hidden`, `inert`, `aria-busy`, the rendered pending echo — not class strings or timing.
