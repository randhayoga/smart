# Graph Report - smart  (2026-06-04)

## Corpus Check
- 325 files · ~251,618 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1013 nodes · 1400 edges · 171 communities (153 shown, 18 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 40 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `47b7c4d2`
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
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
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
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 36|Community 36]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 40|Community 40]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 44|Community 44]]
- [[_COMMUNITY_Community 45|Community 45]]
- [[_COMMUNITY_Community 46|Community 46]]
- [[_COMMUNITY_Community 47|Community 47]]
- [[_COMMUNITY_Community 48|Community 48]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 52|Community 52]]
- [[_COMMUNITY_Community 53|Community 53]]
- [[_COMMUNITY_Community 54|Community 54]]
- [[_COMMUNITY_Community 55|Community 55]]
- [[_COMMUNITY_Community 56|Community 56]]
- [[_COMMUNITY_Community 57|Community 57]]
- [[_COMMUNITY_Community 59|Community 59]]
- [[_COMMUNITY_Community 61|Community 61]]
- [[_COMMUNITY_Community 62|Community 62]]
- [[_COMMUNITY_Community 63|Community 63]]
- [[_COMMUNITY_Community 64|Community 64]]
- [[_COMMUNITY_Community 65|Community 65]]
- [[_COMMUNITY_Community 79|Community 79]]
- [[_COMMUNITY_Community 83|Community 83]]
- [[_COMMUNITY_Community 89|Community 89]]
- [[_COMMUNITY_Community 93|Community 93]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 65 edges
2. `AdmUser` - 23 edges
3. `Barang` - 15 edges
4. `TestCase` - 14 edges
5. `Floor` - 13 edges
6. `Location` - 13 edges
7. `RequestStatusLog` - 13 edges
8. `LotControllerTest` - 13 edges
9. `compilerOptions` - 13 edges
10. `Lot` - 12 edges

## Surprising Connections (you probably didn't know these)
- `AuthenticatedSessionController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/AuthenticatedSessionController.php → app/Http/Controllers/Controller.php
- `InboxController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/InboxController.php → app/Http/Controllers/Controller.php
- `MasterController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/MasterController.php → app/Http/Controllers/Controller.php
- `ReturnController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ReturnController.php → app/Http/Controllers/Controller.php
- `ApprovalController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Manager/ApprovalController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (171 total, 18 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.06
Nodes (27): ArsipController, BorrowedController, DashboardController, HandoverController, ManajemenStokController, RedirectResponse, Request, Response (+19 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (22): RedirectResponse, Request, RedirectResponse, Request, AdmUserFactory, HrdEmployeeFactory, HrdOrgchartFactory, Factory (+14 more)

### Community 2 - "Community 2"
Cohesion: 0.06
Nodes (9): AuthenticationTest, BaseTestCase, BarangControllerTest, ExampleTest, LotControllerTest, MasterDataTest, RefreshDatabase, TestCase (+1 more)

### Community 3 - "Community 3"
Cohesion: 0.09
Nodes (19): MasterController, RedirectResponse, Request, RedirectResponse, Request, RedirectResponse, Request, Request (+11 more)

### Community 4 - "Community 4"
Cohesion: 0.10
Nodes (13): ReturnController, Request, Request, Response, BelongsTo, BelongsTo, BelongsTo, BelongsTo (+5 more)

### Community 5 - "Community 5"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 6 - "Community 6"
Cohesion: 0.09
Nodes (8): BelongsTo, HasMany, BelongsTo, HasMany, Authenticatable, AdmUser, HrdOrgchart, Notifiable

### Community 7 - "Community 7"
Cohesion: 0.11
Nodes (10): Request, Request, BelongsTo, BelongsTo, HasMany, HasOne, ConsumableBasket, Request (+2 more)

### Community 8 - "Community 8"
Cohesion: 0.14
Nodes (10): InboxController, Request, Response, Request, Response, BelongsTo, BelongsTo, ApprovalController (+2 more)

### Community 9 - "Community 9"
Cohesion: 0.13
Nodes (10): RedirectResponse, Request, VendorController, Seeder, BarangSeeder, DatabaseSeeder, LotSeeder, MasterSeeder (+2 more)

### Community 10 - "Community 10"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Lot, OrganizerController, Organizer

### Community 11 - "Community 11"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Unit, RoomController, Room

### Community 12 - "Community 12"
Cohesion: 0.11
Nodes (17): aliases, components, composables, lib, ui, utils, iconLibrary, registries (+9 more)

### Community 13 - "Community 13"
Cohesion: 0.12
Nodes (16): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module, moduleResolution (+8 more)

### Community 14 - "Community 14"
Cohesion: 0.23
Nodes (8): RedirectResponse, Request, Response, Request, Response, Category, CategoryController, BrowseController

### Community 15 - "Community 15"
Cohesion: 0.21
Nodes (6): Request, Request, BelongsTo, AssetBasket, BorrowCartConfirmationController, BorrowCartController

### Community 17 - "Community 17"
Cohesion: 0.24
Nodes (6): Request, BelongsTo, HasMany, Barang, BarangController, RequestItem

### Community 19 - "Community 19"
Cohesion: 0.23
Nodes (7): HasMany, HasMany, HasMany, HasFactory, Organizer, Uom, Vendor

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
Cohesion: 0.31
Nodes (4): BelongsTo, HasMany, HasOne, HrdEmployee

### Community 28 - "Community 28"
Cohesion: 0.33
Nodes (5): HasMany, HasMany, Brand, Model, TbRbs

### Community 29 - "Community 29"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 30 - "Community 30"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 31 - "Community 31"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 32 - "Community 32"
Cohesion: 0.22
Nodes (8): About Laravel, Code of Conduct, Contributing, Laravel Sponsors, Learning Laravel, License, Premium Partners, Security Vulnerabilities

### Community 33 - "Community 33"
Cohesion: 0.39
Nodes (3): BelongsTo, HasMany, Floor

### Community 36 - "Community 36"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/sanctum, laravel/tinker, php, tightenco/ziggy

### Community 37 - "Community 37"
Cohesion: 0.43
Nodes (3): BelongsTo, HasMany, Room

### Community 38 - "Community 38"
Cohesion: 0.38
Nodes (3): BelongsTo, HasMany, Subcategory

### Community 40 - "Community 40"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 41 - "Community 41"
Cohesion: 0.33
Nodes (5): rowsPerPage, searchQuery, statusFilter, timeFilter, typeFilter

### Community 42 - "Community 42"
Cohesion: 0.33
Nodes (4): methodFilter, rowsPerPage, searchQuery, timeFilter

### Community 43 - "Community 43"
Cohesion: 0.53
Nodes (4): Request, Response, Closure, RoleMiddleware

### Community 48 - "Community 48"
Cohesion: 0.47
Nodes (3): BelongsTo, BelongsToMany, Project

### Community 52 - "Community 52"
Cohesion: 0.33
Nodes (5): mainNavigation, NavItem, NavSection, quickActions, userNavigation

### Community 55 - "Community 55"
Cohesion: 0.40
Nodes (3): page, user, userInitials

### Community 56 - "Community 56"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 59 - "Community 59"
Cohesion: 0.50
Nodes (3): rowsPerPage, searchQuery, timeFilter

### Community 61 - "Community 61"
Cohesion: 0.50
Nodes (3): ComponentCustomProperties, PageProps, Window

### Community 64 - "Community 64"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 65 - "Community 65"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **151 isolated node(s):** `$schema`, `style`, `typescript`, `config`, `css` (+146 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **18 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Community 0` to `Community 1`, `Community 3`, `Community 4`, `Community 7`, `Community 8`, `Community 9`, `Community 10`, `Community 11`, `Community 14`, `Community 15`, `Community 17`, `Community 25`?**
  _High betweenness centrality (0.088) - this node is a cross-community bridge._
- **Why does `Barang` connect `Community 17` to `Community 0`, `Community 1`, `Community 2`, `Community 3`, `Community 4`, `Community 7`, `Community 9`, `Community 14`, `Community 15`?**
  _High betweenness centrality (0.029) - this node is a cross-community bridge._
- **Why does `Floor` connect `Community 1` to `Community 2`, `Community 3`, `Community 9`, `Community 10`, `Community 11`?**
  _High betweenness centrality (0.022) - this node is a cross-community bridge._
- **What connects `$schema`, `style`, `typescript` to the rest of the system?**
  _151 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.05605499735589635 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.05714285714285714 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.05757575757575758 - nodes in this community are weakly interconnected._