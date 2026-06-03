# Graph Report - .  (2026-06-03)

## Corpus Check
- 395 files · ~159,545 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1030 nodes · 1369 edges · 213 communities (197 shown, 16 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 40 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Admin Controllers and Dashboards|Admin Controllers and Dashboards]]
- [[_COMMUNITY_Laravel Factories and Core Request Definitions|Laravel Factories and Core Request Definitions]]
- [[_COMMUNITY_Feature and Authentication Tests|Feature and Authentication Tests]]
- [[_COMMUNITY_Inbox Controllers and Request Actions|Inbox Controllers and Request Actions]]
- [[_COMMUNITY_Frontend Dependencies and Tools|Frontend Dependencies and Tools]]
- [[_COMMUNITY_User Model and Relations|User Model and Relations]]
- [[_COMMUNITY_Consumable Baskets and Cart Logic|Consumable Baskets and Cart Logic]]
- [[_COMMUNITY_Returns Management Controllers|Returns Management Controllers]]
- [[_COMMUNITY_Brand Management and Seeding|Brand Management and Seeding]]
- [[_COMMUNITY_Inventory Lot Models|Inventory Lot Models]]
- [[_COMMUNITY_Inventory Unit Assets|Inventory Unit Assets]]
- [[_COMMUNITY_Inventory Item Models|Inventory Item Models]]
- [[_COMMUNITY_UI Component Definitions|UI Component Definitions]]
- [[_COMMUNITY_TypeScript Configuration|TypeScript Configuration]]
- [[_COMMUNITY_Category Management Controllers|Category Management Controllers]]
- [[_COMMUNITY_Module Request & BelongsTo|Module: Request & BelongsTo]]
- [[_COMMUNITY_Module Request & BelongsTo|Module: Request & BelongsTo]]
- [[_COMMUNITY_Module Command & if()|Module: Command & if()]]
- [[_COMMUNITY_Module Request & Lot|Module: Request & Lot]]
- [[_COMMUNITY_Module HasMany & HasFactory|Module: HasMany & HasFactory]]
- [[_COMMUNITY_Module PerluApproval & cancelNote|Module: PerluApproval & cancelNote]]
- [[_COMMUNITY_Module index & SheetVariants|Module: index & SheetVariants]]
- [[_COMMUNITY_Module RedirectResponse & Request|Module: RedirectResponse & Request]]
- [[_COMMUNITY_Module Request & app|Module: Request & app]]
- [[_COMMUNITY_Module BelongsTo & HasMany|Module: BelongsTo & HasMany]]
- [[_COMMUNITY_Module HasMany & Organizer|Module: HasMany & Organizer]]
- [[_COMMUNITY_Module composer & description|Module: composer & description]]
- [[_COMMUNITY_Module require-dev & fakerphpfaker|Module: require-dev & fakerphp/faker]]
- [[_COMMUNITY_Module scripts & dev|Module: scripts & dev]]
- [[_COMMUNITY_Module RedirectResponse & Request|Module: RedirectResponse & Request]]
- [[_COMMUNITY_Module BelongsTo & HasMany|Module: BelongsTo & HasMany]]
- [[_COMMUNITY_Module LoginRequest & FormRequest|Module: LoginRequest & FormRequest]]
- [[_COMMUNITY_Module require & inertiajsinertia-laravel|Module: require & inertiajs/inertia-laravel]]
- [[_COMMUNITY_Module BelongsTo & InventoryLog|Module: BelongsTo & InventoryLog]]
- [[_COMMUNITY_Module BelongsTo & HasMany|Module: BelongsTo & HasMany]]
- [[_COMMUNITY_Module BelongsTo & HasMany|Module: BelongsTo & HasMany]]
- [[_COMMUNITY_Module pestphppest-plugin & php-httpdiscovery|Module: pestphp/pest-plugin & php-http/discovery]]
- [[_COMMUNITY_Module Arsip & rowsPerPage|Module: Arsip & rowsPerPage]]
- [[_COMMUNITY_Module SerahTerima & if()|Module: SerahTerima & if()]]
- [[_COMMUNITY_Module Request & Response|Module: Request & Response]]
- [[_COMMUNITY_Module BelongsTo & UnitStatusApproval|Module: BelongsTo & UnitStatusApproval]]
- [[_COMMUNITY_Module HasMany & Location|Module: HasMany & Location]]
- [[_COMMUNITY_Module BelongsTo & BelongsToMany|Module: BelongsTo & BelongsToMany]]
- [[_COMMUNITY_Module BelongsTo & TbAssignProject|Module: BelongsTo & TbAssignProject]]
- [[_COMMUNITY_Module Avatar & AvatarFallback|Module: Avatar & AvatarFallback]]
- [[_COMMUNITY_Module navigation & mainNavigation|Module: navigation & mainNavigation]]
- [[_COMMUNITY_Module HasMany & Category|Module: HasMany & Category]]
- [[_COMMUNITY_Module BelongsTo & RequestAdminConfirmation|Module: BelongsTo & RequestAdminConfirmation]]
- [[_COMMUNITY_Module HasMany & TbProject|Module: HasMany & TbProject]]
- [[_COMMUNITY_Module Navbar & logout()|Module: Navbar & logout()]]
- [[_COMMUNITY_Module autoload & psr-4|Module: autoload & psr-4]]
- [[_COMMUNITY_Module AppServiceProvider & ServiceProvider|Module: AppServiceProvider & ServiceProvider]]
- [[_COMMUNITY_Module Returns & rowsPerPage|Module: Returns & rowsPerPage]]
- [[_COMMUNITY_Module global & ComponentCustomProperties|Module: global & ComponentCustomProperties]]
- [[_COMMUNITY_Module Badge & index|Module: Badge & index]]
- [[_COMMUNITY_Module Button & index|Module: Button & index]]
- [[_COMMUNITY_Module autoload-dev & psr-4|Module: autoload-dev & psr-4]]
- [[_COMMUNITY_Module extra & laravel|Module: extra & laravel]]
- [[_COMMUNITY_Module index & PageProps|Module: index & PageProps]]
- [[_COMMUNITY_Module entrypoint|Module: entrypoint]]

## God Nodes (most connected - your core abstractions)
1. `Controller` - 63 edges
2. `AdmUser` - 23 edges
3. `Barang` - 15 edges
4. `TestCase` - 14 edges
5. `Floor` - 13 edges
6. `Location` - 13 edges
7. `RequestStatusLog` - 13 edges
8. `compilerOptions` - 13 edges
9. `Room` - 12 edges
10. `HasMany` - 12 edges

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

## Communities (213 total, 16 thin omitted)

### Community 0 - "Admin Controllers and Dashboards"
Cohesion: 0.06
Nodes (28): ArsipController, BorrowedController, DashboardController, HandoverController, ManajemenStokController, MasterController, RedirectResponse, Request (+20 more)

### Community 1 - "Laravel Factories and Core Request Definitions"
Cohesion: 0.06
Nodes (19): RedirectResponse, Request, AdmUserFactory, HrdEmployeeFactory, HrdOrgchartFactory, Factory, Floor, BarangFactory (+11 more)

### Community 2 - "Feature and Authentication Tests"
Cohesion: 0.06
Nodes (9): AuthenticationTest, BaseTestCase, BarangControllerTest, ExampleTest, LotControllerTest, MasterDataTest, RefreshDatabase, TestCase (+1 more)

### Community 3 - "Inbox Controllers and Request Actions"
Cohesion: 0.12
Nodes (13): InboxController, Request, Response, Request, Request, Response, BelongsTo, BelongsTo (+5 more)

### Community 4 - "Frontend Dependencies and Tools"
Cohesion: 0.06
Nodes (33): dependencies, class-variance-authority, clsx, lucide-vue-next, reka-ui, tailwind-merge, tailwindcss-animate, @tanstack/vue-table (+25 more)

### Community 5 - "User Model and Relations"
Cohesion: 0.09
Nodes (8): BelongsTo, HasMany, BelongsTo, HasMany, Authenticatable, AdmUser, HrdOrgchart, Notifiable

### Community 6 - "Consumable Baskets and Cart Logic"
Cohesion: 0.11
Nodes (10): Request, Request, BelongsTo, BelongsTo, HasMany, HasOne, ConsumableBasket, Request (+2 more)

### Community 7 - "Returns Management Controllers"
Cohesion: 0.11
Nodes (11): ReturnController, Request, Request, Response, BelongsTo, BelongsTo, BelongsTo, RequestHandover (+3 more)

### Community 8 - "Brand Management and Seeding"
Cohesion: 0.14
Nodes (10): RedirectResponse, Request, Brand, BrandController, Seeder, BarangSeeder, DatabaseSeeder, LotSeeder (+2 more)

### Community 9 - "Inventory Lot Models"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Lot, VendorController, Vendor

### Community 10 - "Inventory Unit Assets"
Cohesion: 0.17
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Unit, Location, LocationController

### Community 11 - "Inventory Item Models"
Cohesion: 0.20
Nodes (7): RedirectResponse, Request, BelongsTo, HasMany, Barang, UomController, Uom

### Community 12 - "UI Component Definitions"
Cohesion: 0.11
Nodes (17): aliases, components, composables, lib, ui, utils, iconLibrary, registries (+9 more)

### Community 13 - "TypeScript Configuration"
Cohesion: 0.12
Nodes (16): compilerOptions, allowJs, esModuleInterop, forceConsistentCasingInFileNames, isolatedModules, jsx, module, moduleResolution (+8 more)

### Community 14 - "Category Management Controllers"
Cohesion: 0.23
Nodes (8): RedirectResponse, Request, Response, Request, Response, Category, CategoryController, BrowseController

### Community 15 - "Module: Request & BelongsTo"
Cohesion: 0.21
Nodes (6): Request, Request, BelongsTo, AssetBasket, BorrowCartConfirmationController, BorrowCartController

### Community 17 - "Module: Request & BelongsTo"
Cohesion: 0.24
Nodes (6): Request, BelongsTo, HasMany, Barang, BarangController, RequestItem

### Community 19 - "Module: Request & Lot"
Cohesion: 0.28
Nodes (5): Request, Request, Lot, BulkLotController, LotController

### Community 20 - "Module: HasMany & HasFactory"
Cohesion: 0.23
Nodes (7): HasMany, HasMany, HasMany, HasFactory, Brand, Vendor, TbRbs

### Community 21 - "Module: PerluApproval & cancelNote"
Cohesion: 0.15
Nodes (9): cancelNote, filteredRequests, filterProject, filterSort, filterTimeRange, filterUtilization, isCancelModalOpen, projectOptions (+1 more)

### Community 26 - "Module: RedirectResponse & Request"
Cohesion: 0.36
Nodes (5): RedirectResponse, Request, Response, AuthenticatedSessionController, LoginRequest

### Community 27 - "Module: Request & app"
Cohesion: 0.31
Nodes (4): Request, Middleware, HandleInertiaRequests, TrustProxies

### Community 28 - "Module: BelongsTo & HasMany"
Cohesion: 0.31
Nodes (4): BelongsTo, HasMany, HasOne, HrdEmployee

### Community 29 - "Module: HasMany & Organizer"
Cohesion: 0.33
Nodes (5): HasMany, HasMany, Organizer, Uom, Model

### Community 30 - "Module: composer & description"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 31 - "Module: require-dev & fakerphp/faker"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/breeze, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision (+1 more)

### Community 32 - "Module: scripts & dev"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 33 - "Module: RedirectResponse & Request"
Cohesion: 0.54
Nodes (4): RedirectResponse, Request, OrganizerController, Organizer

### Community 34 - "Module: BelongsTo & HasMany"
Cohesion: 0.39
Nodes (3): BelongsTo, HasMany, Floor

### Community 37 - "Module: require & inertiajs/inertia-laravel"
Cohesion: 0.25
Nodes (8): require, inertiajs/inertia-laravel, laravel/framework, laravel/octane, laravel/sanctum, laravel/tinker, php, tightenco/ziggy

### Community 39 - "Module: BelongsTo & HasMany"
Cohesion: 0.43
Nodes (3): BelongsTo, HasMany, Room

### Community 40 - "Module: BelongsTo & HasMany"
Cohesion: 0.38
Nodes (3): BelongsTo, HasMany, Subcategory

### Community 42 - "Module: pestphp/pest-plugin & php-http/discovery"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 43 - "Module: Arsip & rowsPerPage"
Cohesion: 0.33
Nodes (5): rowsPerPage, searchQuery, statusFilter, timeFilter, typeFilter

### Community 44 - "Module: SerahTerima & if()"
Cohesion: 0.33
Nodes (4): methodFilter, rowsPerPage, searchQuery, timeFilter

### Community 45 - "Module: Request & Response"
Cohesion: 0.53
Nodes (4): Request, Response, Closure, RoleMiddleware

### Community 48 - "Module: BelongsTo & BelongsToMany"
Cohesion: 0.47
Nodes (3): BelongsTo, BelongsToMany, Project

### Community 52 - "Module: navigation & mainNavigation"
Cohesion: 0.33
Nodes (5): mainNavigation, NavItem, NavSection, quickActions, userNavigation

### Community 56 - "Module: Navbar & logout()"
Cohesion: 0.40
Nodes (3): page, user, userInitials

### Community 57 - "Module: autoload & psr-4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 60 - "Module: Returns & rowsPerPage"
Cohesion: 0.50
Nodes (3): rowsPerPage, searchQuery, timeFilter

### Community 62 - "Module: global & ComponentCustomProperties"
Cohesion: 0.50
Nodes (3): ComponentCustomProperties, PageProps, Window

### Community 65 - "Module: autoload-dev & psr-4"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 66 - "Module: extra & laravel"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **142 isolated node(s):** `$schema`, `style`, `typescript`, `config`, `css` (+137 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **16 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Controller` connect `Admin Controllers and Dashboards` to `Laravel Factories and Core Request Definitions`, `Module: RedirectResponse & Request`, `Inbox Controllers and Request Actions`, `Consumable Baskets and Cart Logic`, `Returns Management Controllers`, `Brand Management and Seeding`, `Inventory Lot Models`, `Inventory Unit Assets`, `Inventory Item Models`, `Category Management Controllers`, `Module: Request & BelongsTo`, `Module: Request & BelongsTo`, `Module: Request & Lot`, `Module: RedirectResponse & Request`?**
  _High betweenness centrality (0.082) - this node is a cross-community bridge._
- **Why does `Barang` connect `Module: Request & BelongsTo` to `Admin Controllers and Dashboards`, `Laravel Factories and Core Request Definitions`, `Feature and Authentication Tests`, `Consumable Baskets and Cart Logic`, `Returns Management Controllers`, `Brand Management and Seeding`, `Category Management Controllers`, `Module: Request & BelongsTo`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Why does `Floor` connect `Laravel Factories and Core Request Definitions` to `Admin Controllers and Dashboards`, `Feature and Authentication Tests`, `Brand Management and Seeding`, `Inventory Lot Models`, `Inventory Unit Assets`?**
  _High betweenness centrality (0.020) - this node is a cross-community bridge._
- **What connects `$schema`, `style`, `typescript` to the rest of the system?**
  _142 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin Controllers and Dashboards` be split into smaller, more focused modules?**
  _Cohesion score 0.05989110707803993 - nodes in this community are weakly interconnected._
- **Should `Laravel Factories and Core Request Definitions` be split into smaller, more focused modules?**
  _Cohesion score 0.05803921568627451 - nodes in this community are weakly interconnected._
- **Should `Feature and Authentication Tests` be split into smaller, more focused modules?**
  _Cohesion score 0.0627177700348432 - nodes in this community are weakly interconnected._