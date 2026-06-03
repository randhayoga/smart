# Graph Report - smart  (2026-06-03)

## Corpus Check
- 361 files · ~154,318 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 978 nodes · 1244 edges · 204 communities (193 shown, 11 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 35 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `8652f939`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Community 0|Community 0]]
- [[_COMMUNITY_Community 1|Community 1]]
- [[_COMMUNITY_Community 2|Community 2]]
- [[_COMMUNITY_Community 3|Community 3]]
- [[_COMMUNITY_Community 4|Community 4]]
- [[_COMMUNITY_Community 5|Community 5]]
- [[_COMMUNITY_Community 6|Community 6]]
- [[_COMMUNITY_Community 7|Community 7]]
- [[_COMMUNITY_Community 8|Community 8]]
- [[_COMMUNITY_Community 9|Community 9]]
- [[_COMMUNITY_Community 10|Community 10]]
- [[_COMMUNITY_Community 11|Community 11]]
- [[_COMMUNITY_Community 12|Community 12]]
- [[_COMMUNITY_Community 13|Community 13]]
- [[_COMMUNITY_Community 14|Community 14]]
- [[_COMMUNITY_Community 15|Community 15]]
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 20|Community 20]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 27|Community 27]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 44|Community 44]]
- [[_COMMUNITY_Community 45|Community 45]]
- [[_COMMUNITY_Community 47|Community 47]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 51|Community 51]]
- [[_COMMUNITY_Community 52|Community 52]]
- [[_COMMUNITY_Community 53|Community 53]]
- [[_COMMUNITY_Community 67|Community 67]]
- [[_COMMUNITY_Community 71|Community 71]]
- [[_COMMUNITY_Community 76|Community 76]]
- [[_COMMUNITY_Community 80|Community 80]]
- [[_COMMUNITY_Community 203|Community 203]]
- [[_COMMUNITY_Community 204|Community 204]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 59 edges
2. `Barang` - 14 edges
3. `TestCase` - 14 edges
4. `Floor` - 13 edges
5. `Location` - 13 edges
6. `RequestStatusLog` - 13 edges
7. `compilerOptions` - 13 edges
8. `Room` - 12 edges
9. `Unit` - 12 edges
10. `Request` - 12 edges

## Surprising Connections (you probably didn't know these)
- `AuthenticatedSessionController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/AuthenticatedSessionController.php → app/Http/Controllers/Controller.php
- `InboxController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/InboxController.php → app/Http/Controllers/Controller.php
- `ManajemenStokController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStokController.php → app/Http/Controllers/Controller.php
- `ReturnController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ReturnController.php → app/Http/Controllers/Controller.php
- `ApprovalController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Manager/ApprovalController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (204 total, 11 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (28): BelongsTo, BelongsTo, HasMany, BelongsTo, BelongsTo, BelongsTo, BelongsTo, Authenticatable (+20 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (28): ArsipController, BorrowedController, DashboardController, HandoverController, MasterController, RedirectResponse, Request, Response (+20 more)

### Community 2 - "Community 2"
Cohesion: 0.06
Nodes (22): RedirectResponse, Request, RedirectResponse, Request, DepartmentFactory, UserFactory, Factory, Floor (+14 more)

### Community 3 - "Community 3"
Cohesion: 0.08
Nodes (9): AuthenticationTest, BaseTestCase, BarangControllerTest, ExampleTest, LotControllerTest, MasterDataTest, RefreshDatabase, TestCase (+1 more)

### Community 4 - "Community 4"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 5 - "Community 5"
Cohesion: 0.09
Nodes (15): InboxController, ReturnController, Request, Response, Request, Request, Request, Response (+7 more)

### Community 6 - "Community 6"
Cohesion: 0.19
Nodes (7): Request, Response, BelongsTo, BelongsTo, RequestHandover, RequestStatusLog, RequestHistoryController

### Community 7 - "Community 7"
Cohesion: 0.15
Nodes (8): Request, Lot, LotController, Seeder, BarangSeeder, DatabaseSeeder, LotSeeder, UserSeeder

### Community 8 - "Community 8"
Cohesion: 0.10
Nodes (13): activeTab, filteredFloors, filteredRooms, generateLotCode(), isDetailConsumablesOpen, isLotFormValid, isLotModalOpen, lotForm (+5 more)

### Community 9 - "Community 9"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Unit, RoomController, Room

### Community 10 - "Community 10"
Cohesion: 0.18
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Lot, OrganizerController, Organizer

### Community 11 - "Community 11"
Cohesion: 0.11
Nodes (17): aliases, components, composables, lib, ui, utils, iconLibrary, registries (+9 more)

### Community 12 - "Community 12"
Cohesion: 0.12
Nodes (16): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module, moduleResolution (+8 more)

### Community 13 - "Community 13"
Cohesion: 0.23
Nodes (8): RedirectResponse, Request, Response, Request, Response, Category, CategoryController, BrowseController

### Community 14 - "Community 14"
Cohesion: 0.21
Nodes (6): Request, Request, BelongsTo, ConsumableBasket, AssetCartConfirmationController, AssetCartController

### Community 15 - "Community 15"
Cohesion: 0.21
Nodes (6): Request, Request, BelongsTo, AssetBasket, BorrowCartConfirmationController, BorrowCartController

### Community 16 - "Community 16"
Cohesion: 0.23
Nodes (4): BelongsTo, HasMany, HasOne, Request

### Community 20 - "Community 20"
Cohesion: 0.15
Nodes (9): cancelNote, filteredRequests, filterProject, filterSort, filterTimeRange, filterUtilization, isCancelModalOpen, projectOptions (+1 more)

### Community 25 - "Community 25"
Cohesion: 0.36
Nodes (5): RedirectResponse, Request, Response, AuthenticatedSessionController, LoginRequest

### Community 26 - "Community 26"
Cohesion: 0.31
Nodes (4): Request, Middleware, HandleInertiaRequests, TrustProxies

### Community 27 - "Community 27"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 28 - "Community 28"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 29 - "Community 29"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 30 - "Community 30"
Cohesion: 0.22
Nodes (8): About Laravel, Code of Conduct, Contributing, Laravel Sponsors, Learning Laravel, License, Premium Partners, Security Vulnerabilities

### Community 33 - "Community 33"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/sanctum, laravel/tinker, php, tightenco/ziggy

### Community 35 - "Community 35"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 36 - "Community 36"
Cohesion: 0.33
Nodes (5): rowsPerPage, searchQuery, statusFilter, timeFilter, typeFilter

### Community 37 - "Community 37"
Cohesion: 0.24
Nodes (6): Request, BelongsTo, HasMany, Barang, BarangController, RequestItem

### Community 38 - "Community 38"
Cohesion: 0.33
Nodes (4): methodFilter, rowsPerPage, searchQuery, timeFilter

### Community 39 - "Community 39"
Cohesion: 0.53
Nodes (4): Request, Response, Closure, RoleMiddleware

### Community 42 - "Community 42"
Cohesion: 0.33
Nodes (5): mainNavigation, NavItem, NavSection, quickActions, userNavigation

### Community 43 - "Community 43"
Cohesion: 0.40
Nodes (3): page, user, userInitials

### Community 44 - "Community 44"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 47 - "Community 47"
Cohesion: 0.50
Nodes (3): rowsPerPage, searchQuery, timeFilter

### Community 49 - "Community 49"
Cohesion: 0.50
Nodes (3): ComponentCustomProperties, PageProps, Window

### Community 52 - "Community 52"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 53 - "Community 53"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 203 - "Community 203"
Cohesion: 0.21
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Brand, Barang, BrandController

### Community 204 - "Community 204"
Cohesion: 0.60
Nodes (3): ManajemenStokController, Request, Response

## Knowledge Gaps
- **162 isolated node(s):** `$schema`, `style`, `typescript`, `config`, `css` (+157 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **11 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Community 1` to `Community 2`, `Community 37`, `Community 5`, `Community 7`, `Community 6`, `Community 9`, `Community 10`, `Community 203`, `Community 204`, `Community 13`, `Community 14`, `Community 15`, `Community 25`?**
  _High betweenness centrality (0.071) - this node is a cross-community bridge._
- **Why does `Barang` connect `Community 37` to `Community 2`, `Community 3`, `Community 6`, `Community 7`, `Community 13`, `Community 14`, `Community 15`?**
  _High betweenness centrality (0.022) - this node is a cross-community bridge._
- **Why does `Location` connect `Community 2` to `Community 1`, `Community 10`, `Community 3`, `Community 9`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **What connects `$schema`, `style`, `typescript` to the rest of the system?**
  _162 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.05472636815920398 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.062310949788263764 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.05714285714285714 - nodes in this community are weakly interconnected._