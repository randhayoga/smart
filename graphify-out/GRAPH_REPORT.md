# Graph Report - smart  (2026-06-04)

## Corpus Check
- 373 files · ~294,945 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1394 nodes · 1949 edges · 272 communities (246 shown, 26 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 40 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `3b0a6e54`
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
- [[_COMMUNITY_Community 55|Community 55]]
- [[_COMMUNITY_Community 61|Community 61]]
- [[_COMMUNITY_Community 62|Community 62]]
- [[_COMMUNITY_Community 63|Community 63]]
- [[_COMMUNITY_Community 64|Community 64]]
- [[_COMMUNITY_Community 67|Community 67]]
- [[_COMMUNITY_Community 71|Community 71]]
- [[_COMMUNITY_Community 76|Community 76]]
- [[_COMMUNITY_Community 80|Community 80]]
- [[_COMMUNITY_Community 158|Community 158]]
- [[_COMMUNITY_Community 159|Community 159]]
- [[_COMMUNITY_Community 160|Community 160]]
- [[_COMMUNITY_Community 161|Community 161]]
- [[_COMMUNITY_Community 162|Community 162]]
- [[_COMMUNITY_Community 163|Community 163]]
- [[_COMMUNITY_Community 164|Community 164]]
- [[_COMMUNITY_Community 165|Community 165]]
- [[_COMMUNITY_Community 166|Community 166]]
- [[_COMMUNITY_Community 167|Community 167]]
- [[_COMMUNITY_Community 168|Community 168]]
- [[_COMMUNITY_Community 169|Community 169]]
- [[_COMMUNITY_Community 170|Community 170]]
- [[_COMMUNITY_Community 171|Community 171]]
- [[_COMMUNITY_Community 172|Community 172]]
- [[_COMMUNITY_Community 173|Community 173]]
- [[_COMMUNITY_Community 174|Community 174]]
- [[_COMMUNITY_Community 175|Community 175]]
- [[_COMMUNITY_Community 176|Community 176]]
- [[_COMMUNITY_Community 177|Community 177]]
- [[_COMMUNITY_Community 178|Community 178]]
- [[_COMMUNITY_Community 179|Community 179]]
- [[_COMMUNITY_Community 180|Community 180]]
- [[_COMMUNITY_Community 181|Community 181]]
- [[_COMMUNITY_Community 182|Community 182]]
- [[_COMMUNITY_Community 183|Community 183]]
- [[_COMMUNITY_Community 184|Community 184]]
- [[_COMMUNITY_Community 185|Community 185]]
- [[_COMMUNITY_Community 186|Community 186]]
- [[_COMMUNITY_Community 187|Community 187]]
- [[_COMMUNITY_Community 188|Community 188]]
- [[_COMMUNITY_Community 190|Community 190]]
- [[_COMMUNITY_Community 191|Community 191]]
- [[_COMMUNITY_Community 192|Community 192]]
- [[_COMMUNITY_Community 193|Community 193]]
- [[_COMMUNITY_Community 194|Community 194]]
- [[_COMMUNITY_Community 195|Community 195]]
- [[_COMMUNITY_Community 196|Community 196]]
- [[_COMMUNITY_Community 197|Community 197]]
- [[_COMMUNITY_Community 199|Community 199]]
- [[_COMMUNITY_Community 200|Community 200]]
- [[_COMMUNITY_Community 204|Community 204]]
- [[_COMMUNITY_Community 205|Community 205]]
- [[_COMMUNITY_Community 206|Community 206]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 91 edges
2. `Barang` - 23 edges
3. `AdmUser` - 23 edges
4. `Floor` - 19 edges
5. `Location` - 18 edges
6. `Room` - 17 edges
7. `TestCase` - 17 edges
8. `Brand` - 15 edges
9. `Category` - 15 edges
10. `Organizer` - 15 edges

## Surprising Connections (you probably didn't know these)
- `ManajemenStokController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStokController.php → D:/magang/web app/4/smart/app/Http/Controllers/Controller.php
- `BarangController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStok/BarangController.php → D:/magang/web app/4/smart/app/Http/Controllers/Controller.php
- `BulkBarangController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStok/BulkBarangController.php → D:/magang/web app/4/smart/app/Http/Controllers/Controller.php
- `BulkLotController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStok/BulkLotController.php → D:/magang/web app/4/smart/app/Http/Controllers/Controller.php
- `BulkUnitController` --inherits--> `Controller`  [EXTRACTED]
  app/Http/Controllers/Smart/Admin/ManajemenStok/BulkUnitController.php → D:/magang/web app/4/smart/app/Http/Controllers/Controller.php

## Import Cycles
- None detected.

## Communities (272 total, 26 thin omitted)

### Community 0 - "Community 0"
Cohesion: 0.29
Nodes (6): HasMany, BelongsTo, BelongsTo, Uom, Model, RequestAdminConfirmation

### Community 1 - "Community 1"
Cohesion: 0.15
Nodes (4): ArsipController, BorrowedController, HandoverController, Controller

### Community 2 - "Community 2"
Cohesion: 0.06
Nodes (31): MasterController, Request, Response, Brand, Category, Request, Response, AdmUserFactory (+23 more)

### Community 3 - "Community 3"
Cohesion: 0.05
Nodes (8): AuthenticationTest, BaseTestCase, BarangControllerTest, ExampleTest, LotControllerTest, MasterDataTest, TestCase, ExampleTest

### Community 4 - "Community 4"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 5 - "Community 5"
Cohesion: 0.10
Nodes (19): InboxController, Request, Response, Request, Request, Response, BelongsTo, BelongsTo (+11 more)

### Community 6 - "Community 6"
Cohesion: 0.09
Nodes (17): ReturnController, Request, Request, Response, BelongsTo, BelongsTo, BelongsTo, Request (+9 more)

### Community 7 - "Community 7"
Cohesion: 0.08
Nodes (18): Request, Request, Response, BelongsTo, HasMany, Barang, Request, Response (+10 more)

### Community 8 - "Community 8"
Cohesion: 0.10
Nodes (13): activeTab, filteredFloors, filteredRooms, generateLotCode(), isDetailConsumablesOpen, isLotFormValid, isLotModalOpen, lotForm (+5 more)

### Community 9 - "Community 9"
Cohesion: 0.24
Nodes (3): BelongsTo, HasMany, Unit

### Community 10 - "Community 10"
Cohesion: 0.26
Nodes (3): BelongsTo, HasMany, Lot

### Community 11 - "Community 11"
Cohesion: 0.11
Nodes (17): aliases, components, composables, lib, ui, utils, iconLibrary, registries (+9 more)

### Community 12 - "Community 12"
Cohesion: 0.12
Nodes (16): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module, moduleResolution (+8 more)

### Community 13 - "Community 13"
Cohesion: 0.31
Nodes (7): RedirectResponse, Request, Response, RedirectResponse, Request, Response, CategoryController

### Community 14 - "Community 14"
Cohesion: 0.48
Nodes (3): Request, Request, AssetCartConfirmationController

### Community 15 - "Community 15"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, UomController

### Community 16 - "Community 16"
Cohesion: 0.22
Nodes (7): BelongsTo, HasMany, BelongsTo, HasMany, HasOne, HasOne, Request

### Community 18 - "Community 18"
Cohesion: 0.05
Nodes (36): bytes, rows, name, votes, Laravel\\Octane\\Contracts\\OperationTerminated, Laravel\\Octane\\Events\\RequestHandled, Laravel\\Octane\\Events\\RequestReceived, Laravel\\Octane\\Events\\RequestTerminated (+28 more)

### Community 20 - "Community 20"
Cohesion: 0.15
Nodes (9): cancelNote, filteredRequests, filterProject, filterSort, filterTimeRange, filterUtilization, isCancelModalOpen, projectOptions (+1 more)

### Community 25 - "Community 25"
Cohesion: 0.28
Nodes (8): RedirectResponse, Request, Response, AuthenticatedSessionController, RedirectResponse, Request, Response, LoginRequest

### Community 26 - "Community 26"
Cohesion: 0.23
Nodes (5): Request, Request, Middleware, HandleInertiaRequests, TrustProxies

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
Cohesion: 0.54
Nodes (3): Request, Lot, LotController

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

### Community 158 - "Community 158"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 159 - "Community 159"
Cohesion: 0.13
Nodes (5): BelongsTo, HasMany, Authenticatable, AdmUser, Notifiable

### Community 160 - "Community 160"
Cohesion: 0.11
Nodes (17): aliases, components, composables, lib, ui, utils, iconLibrary, registries (+9 more)

### Community 161 - "Community 161"
Cohesion: 0.12
Nodes (16): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module, moduleResolution (+8 more)

### Community 162 - "Community 162"
Cohesion: 0.22
Nodes (7): HasMany, HasMany, HasMany, HasFactory, Brand, TbProject, TbRbs

### Community 164 - "Community 164"
Cohesion: 0.33
Nodes (7): DashboardController, RedirectResponse, Request, Response, RedirectResponse, Request, Response

### Community 165 - "Community 165"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, BrandController

### Community 166 - "Community 166"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, FloorController

### Community 167 - "Community 167"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, LocationController

### Community 168 - "Community 168"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, OrganizerController

### Community 169 - "Community 169"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, RoomController

### Community 170 - "Community 170"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, SubcategoryController

### Community 171 - "Community 171"
Cohesion: 0.31
Nodes (3): BelongsTo, HasMany, Barang

### Community 172 - "Community 172"
Cohesion: 0.42
Nodes (5): RedirectResponse, Request, RedirectResponse, Request, VendorController

### Community 173 - "Community 173"
Cohesion: 0.33
Nodes (7): RedirectResponse, Request, Response, RedirectResponse, Request, Response, UserDashboardController

### Community 174 - "Community 174"
Cohesion: 0.36
Nodes (3): BelongsTo, HasMany, HrdOrgchart

### Community 175 - "Community 175"
Cohesion: 0.31
Nodes (4): BelongsTo, HasMany, HasOne, HrdEmployee

### Community 176 - "Community 176"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 177 - "Community 177"
Cohesion: 0.22
Nodes (8): About Laravel, Code of Conduct, Contributing, Laravel Sponsors, Learning Laravel, License, Premium Partners, Security Vulnerabilities

### Community 178 - "Community 178"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 179 - "Community 179"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 180 - "Community 180"
Cohesion: 0.25
Nodes (6): Request, BelongsTo, ConsumableBasket, Request, BelongsTo, AssetCartController

### Community 181 - "Community 181"
Cohesion: 0.39
Nodes (3): BelongsTo, HasMany, Floor

### Community 182 - "Community 182"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/sanctum, laravel/tinker, php, tightenco/ziggy

### Community 185 - "Community 185"
Cohesion: 0.16
Nodes (9): Request, Request, BelongsTo, AssetBasket, Request, Request, BelongsTo, BorrowCartConfirmationController (+1 more)

### Community 187 - "Community 187"
Cohesion: 0.38
Nodes (3): HasMany, HasMany, Category

### Community 188 - "Community 188"
Cohesion: 0.43
Nodes (3): BelongsTo, HasMany, Room

### Community 190 - "Community 190"
Cohesion: 0.38
Nodes (3): BelongsTo, HasMany, Subcategory

### Community 191 - "Community 191"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 195 - "Community 195"
Cohesion: 0.47
Nodes (3): BelongsTo, BelongsToMany, Project

### Community 199 - "Community 199"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 204 - "Community 204"
Cohesion: 0.60
Nodes (3): ManajemenStokController, Request, Response

### Community 205 - "Community 205"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 206 - "Community 206"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **298 isolated node(s):** `$schema`, `style`, `typescript`, `config`, `css` (+293 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **26 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Community 1` to `Community 2`, `Community 5`, `Community 6`, `Community 7`, `Community 13`, `Community 14`, `Community 15`, `Community 25`, `Community 163`, `Community 164`, `Community 165`, `Community 166`, `Community 167`, `Community 168`, `Community 169`, `Community 170`, `Community 37`, `Community 172`, `Community 173`, `Community 180`, `Community 183`, `Community 185`, `Community 197`, `Community 204`?**
  _High betweenness centrality (0.081) - this node is a cross-community bridge._
- **Why does `Barang` connect `Community 7` to `Community 2`, `Community 197`, `Community 6`, `Community 180`, `Community 185`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Why does `TestCase` connect `Community 3` to `Community 2`?**
  _High betweenness centrality (0.013) - this node is a cross-community bridge._
- **What connects `$schema`, `style`, `typescript` to the rest of the system?**
  _298 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Community 2` be split into smaller, more focused modules?**
  _Cohesion score 0.05886075949367089 - nodes in this community are weakly interconnected._
- **Should `Community 3` be split into smaller, more focused modules?**
  _Cohesion score 0.0507399577167019 - nodes in this community are weakly interconnected._
- **Should `Community 4` be split into smaller, more focused modules?**
  _Cohesion score 0.058823529411764705 - nodes in this community are weakly interconnected._