# Graph Report - smart  (2026-06-09)

## Corpus Check
- 329 files · ~324,040 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1046 nodes · 1484 edges · 176 communities (152 shown, 24 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 49 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `1ce6d8ae`
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
- [[_COMMUNITY_Community 16|Community 16]]
- [[_COMMUNITY_Community 17|Community 17]]
- [[_COMMUNITY_Community 18|Community 18]]
- [[_COMMUNITY_Community 19|Community 19]]
- [[_COMMUNITY_Community 22|Community 22]]
- [[_COMMUNITY_Community 23|Community 23]]
- [[_COMMUNITY_Community 25|Community 25]]
- [[_COMMUNITY_Community 26|Community 26]]
- [[_COMMUNITY_Community 28|Community 28]]
- [[_COMMUNITY_Community 29|Community 29]]
- [[_COMMUNITY_Community 30|Community 30]]
- [[_COMMUNITY_Community 31|Community 31]]
- [[_COMMUNITY_Community 32|Community 32]]
- [[_COMMUNITY_Community 33|Community 33]]
- [[_COMMUNITY_Community 34|Community 34]]
- [[_COMMUNITY_Community 35|Community 35]]
- [[_COMMUNITY_Community 37|Community 37]]
- [[_COMMUNITY_Community 38|Community 38]]
- [[_COMMUNITY_Community 39|Community 39]]
- [[_COMMUNITY_Community 41|Community 41]]
- [[_COMMUNITY_Community 42|Community 42]]
- [[_COMMUNITY_Community 43|Community 43]]
- [[_COMMUNITY_Community 44|Community 44]]
- [[_COMMUNITY_Community 46|Community 46]]
- [[_COMMUNITY_Community 47|Community 47]]
- [[_COMMUNITY_Community 48|Community 48]]
- [[_COMMUNITY_Community 49|Community 49]]
- [[_COMMUNITY_Community 50|Community 50]]
- [[_COMMUNITY_Community 51|Community 51]]
- [[_COMMUNITY_Community 52|Community 52]]
- [[_COMMUNITY_Community 53|Community 53]]
- [[_COMMUNITY_Community 54|Community 54]]
- [[_COMMUNITY_Community 55|Community 55]]
- [[_COMMUNITY_Community 56|Community 56]]
- [[_COMMUNITY_Community 58|Community 58]]
- [[_COMMUNITY_Community 59|Community 59]]
- [[_COMMUNITY_Community 61|Community 61]]
- [[_COMMUNITY_Community 62|Community 62]]
- [[_COMMUNITY_Community 63|Community 63]]
- [[_COMMUNITY_Community 65|Community 65]]
- [[_COMMUNITY_Community 67|Community 67]]
- [[_COMMUNITY_Community 68|Community 68]]
- [[_COMMUNITY_Community 69|Community 69]]
- [[_COMMUNITY_Community 70|Community 70]]
- [[_COMMUNITY_Community 71|Community 71]]
- [[_COMMUNITY_Community 85|Community 85]]
- [[_COMMUNITY_Community 89|Community 89]]
- [[_COMMUNITY_Community 95|Community 95]]
- [[_COMMUNITY_Community 99|Community 99]]
- [[_COMMUNITY_Community 177|Community 177]]
- [[_COMMUNITY_Community 178|Community 178]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 67 edges
2. `AdmUser` - 23 edges
3. `TestCase` - 18 edges
4. `Barang` - 15 edges
5. `Unit` - 15 edges
6. `Lot` - 14 edges
7. `Floor` - 13 edges
8. `Location` - 13 edges
9. `RequestStatusLog` - 13 edges
10. `LotControllerTest` - 13 edges

## Surprising Connections (you probably didn't know these)
- `AuthenticatedSessionController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Auth/AuthenticatedSessionController.php → app/Http/Controllers/Controller.php
- `InboxController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/InboxController.php → app/Http/Controllers/Controller.php
- `ReturnController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ReturnController.php → app/Http/Controllers/Controller.php
- `ApprovalController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Manager/ApprovalController.php → app/Http/Controllers/Controller.php
- `BarangController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStok/BarangController.php → app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (176 total, 24 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.05
Nodes (32): ArsipController, BorrowedController, DashboardController, HandoverController, ManajemenStokController, MasterController, RedirectResponse, Request (+24 more)

### Community 1 - "Community 1"
Cohesion: 0.06
Nodes (22): RedirectResponse, Request, RedirectResponse, Request, AdmUserFactory, HrdEmployeeFactory, HrdOrgchartFactory, Factory (+14 more)

### Community 2 - "Community 2"
Cohesion: 0.09
Nodes (17): RedirectResponse, Request, RedirectResponse, Request, RedirectResponse, Request, BelongsTo, HasMany (+9 more)

### Community 3 - "Community 3"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 4 - "Community 4"
Cohesion: 0.06
Nodes (25): InboxController, ReturnController, Request, Response, Request, Request, Request, Response (+17 more)

### Community 6 - "Community 6"
Cohesion: 0.47
Nodes (3): Request, UnitStatusApprovalController, UnitStatusApproval

### Community 7 - "Community 7"
Cohesion: 0.09
Nodes (8): BelongsTo, HasMany, BelongsTo, HasMany, Authenticatable, AdmUser, HrdOrgchart, Notifiable

### Community 8 - "Community 8"
Cohesion: 0.09
Nodes (14): Request, BelongsTo, HasMany, BelongsTo, HasMany, Unit, Lot, LotController (+6 more)

### Community 9 - "Community 9"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Lot, RoomController, Room

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
Cohesion: 0.11
Nodes (10): Request, Request, BelongsTo, BelongsTo, HasMany, HasOne, ConsumableBasket, Request (+2 more)

### Community 16 - "Community 16"
Cohesion: 0.24
Nodes (6): Request, BelongsTo, HasMany, Barang, BarangController, RequestItem

### Community 18 - "Community 18"
Cohesion: 0.22
Nodes (7): HasMany, HasMany, HasMany, HasFactory, Brand, Uom, TbProject

### Community 19 - "Community 19"
Cohesion: 0.15
Nodes (9): cancelNote, filteredRequests, filterProject, filterSort, filterTimeRange, filterUtilization, isCancelModalOpen, projectOptions (+1 more)

### Community 23 - "Community 23"
Cohesion: 0.21
Nodes (6): Request, Request, BelongsTo, AssetBasket, BorrowCartConfirmationController, BorrowCartController

### Community 28 - "Community 28"
Cohesion: 0.36
Nodes (5): RedirectResponse, Request, Response, AuthenticatedSessionController, LoginRequest

### Community 29 - "Community 29"
Cohesion: 0.28
Nodes (4): BaseTestCase, ExampleTest, TestCase, ExampleTest

### Community 30 - "Community 30"
Cohesion: 0.31
Nodes (4): Request, Middleware, HandleInertiaRequests, TrustProxies

### Community 31 - "Community 31"
Cohesion: 0.31
Nodes (4): BelongsTo, HasMany, HasOne, HrdEmployee

### Community 33 - "Community 33"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 34 - "Community 34"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 35 - "Community 35"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 37 - "Community 37"
Cohesion: 0.22
Nodes (8): About Laravel, Code of Conduct, Contributing, Laravel Sponsors, Learning Laravel, License, Premium Partners, Security Vulnerabilities

### Community 38 - "Community 38"
Cohesion: 0.39
Nodes (3): BelongsTo, HasMany, Floor

### Community 41 - "Community 41"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/sanctum, laravel/tinker, php, tightenco/ziggy

### Community 43 - "Community 43"
Cohesion: 0.43
Nodes (3): BelongsTo, HasMany, Room

### Community 44 - "Community 44"
Cohesion: 0.38
Nodes (3): BelongsTo, HasMany, Subcategory

### Community 46 - "Community 46"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 47 - "Community 47"
Cohesion: 0.33
Nodes (5): rowsPerPage, searchQuery, statusFilter, timeFilter, typeFilter

### Community 48 - "Community 48"
Cohesion: 0.33
Nodes (4): methodFilter, rowsPerPage, searchQuery, timeFilter

### Community 49 - "Community 49"
Cohesion: 0.53
Nodes (4): Request, Response, Closure, RoleMiddleware

### Community 54 - "Community 54"
Cohesion: 0.47
Nodes (3): BelongsTo, BelongsToMany, Project

### Community 58 - "Community 58"
Cohesion: 0.33
Nodes (5): mainNavigation, NavItem, NavSection, quickActions, userNavigation

### Community 59 - "Community 59"
Cohesion: 0.23
Nodes (7): HasMany, HasMany, HasMany, Organizer, Vendor, Model, TbRbs

### Community 61 - "Community 61"
Cohesion: 0.40
Nodes (3): page, user, userInitials

### Community 62 - "Community 62"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 65 - "Community 65"
Cohesion: 0.50
Nodes (3): rowsPerPage, searchQuery, timeFilter

### Community 67 - "Community 67"
Cohesion: 0.50
Nodes (3): ComponentCustomProperties, PageProps, Window

### Community 70 - "Community 70"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 71 - "Community 71"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **151 isolated node(s):** `$schema`, `style`, `typescript`, `config`, `css` (+146 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **24 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Community 0` to `Community 1`, `Community 2`, `Community 4`, `Community 6`, `Community 8`, `Community 9`, `Community 13`, `Community 14`, `Community 16`, `Community 23`, `Community 28`?**
  _High betweenness centrality (0.091) - this node is a cross-community bridge._
- **Why does `Barang` connect `Community 16` to `Community 0`, `Community 1`, `Community 2`, `Community 4`, `Community 8`, `Community 42`, `Community 13`, `Community 14`, `Community 178`, `Community 23`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Why does `Location` connect `Community 1` to `Community 0`, `Community 32`, `Community 2`, `Community 8`, `Community 9`, `Community 178`?**
  _High betweenness centrality (0.018) - this node is a cross-community bridge._
- **What connects `$schema`, `style`, `typescript` to the rest of the system?**
  _151 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 0` be split into smaller, more focused modules?**
  _Cohesion score 0.050921861281826165 - nodes in this community are weakly interconnected._
- **Should `Community 1` be split into smaller, more focused modules?**
  _Cohesion score 0.05714285714285714 - nodes in this community are weakly interconnected._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.09230769230769231 - nodes in this community are weakly interconnected._