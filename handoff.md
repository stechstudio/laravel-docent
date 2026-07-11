# Laravel Contextual Documentation Package
## Product and Architecture Handoff

**Prepared:** July 10, 2026  
**Audience:** Codex, Claude, or another engineer/product designer continuing the project  
**Status:** Product discovery and preliminary architecture; no final package name selected

---

## 1. Executive Summary

The proposed product is a Laravel package that lets an application owner install a polished, searchable, fast user-documentation website directly inside an existing Laravel application.

The basic installation promise is:

```bash
composer require vendor/package
php artisan docs:install
```

After installation, the application immediately has a user-facing documentation center, conventionally at:

```text
/docs
```

At a superficial level, the package resembles products such as Mintlify or other documentation-site generators. However, its core value is not simply that it renders Markdown inside Laravel.

The differentiator is:

> Documentation installed inside a Laravel application can participate in the application.

The documentation can use the host application's:

- Authentication
- Middleware
- Gates and policies
- User roles and permissions
- Feature flags
- Tenant/account state
- Named routes
- Configuration
- Database-backed values
- Laravel service container
- Blade or application-native UI components
- Deployment lifecycle
- Test suite

This makes the package a contextual product-guidance runtime, not merely a static documentation generator.

The recommended architecture is:

> **Repository-first authoring for v1, built on a common document AST, allowlisted Laravel integrations, pluggable content repositories, and a structured database/Tiptap editor later.**

This preserves the strongest original advantage—tight coupling to the Laravel app—without permanently coupling the product to Markdown files.

---

## 2. Product Thesis

### 2.1 What user documentation actually accomplishes

User documentation is not primarily a collection of pages. It helps users:

1. **Orient themselves**
   - What does the application do?
   - Where am I?
   - Which features are available to me?

2. **Complete tasks**
   - How do I create, configure, manage, or troubleshoot something?
   - What sequence of actions should I follow?

3. **Recover from friction**
   - Why did something fail?
   - What does an error mean?
   - What can I do next?

4. **Build confidence**
   - Am I doing this correctly?
   - What happens if I take this action?
   - Are there prerequisites or consequences?

The package therefore delivers an **understanding layer** or **guidance layer** for a Laravel application.

A useful positioning statement is:

> Give every Laravel application a native, self-service guide that understands the application and the user reading it.

### 2.2 Why install a Laravel package instead of using a separate documentation platform?

A separate platform can produce an attractive documentation website, but it usually sits outside the application's runtime and authorization model.

An in-app Laravel package can:

- Require authentication for all documentation.
- Use the exact same authentication guard as the application.
- Hide an entire page from unauthorized users.
- Hide or change a section based on a Laravel gate or policy.
- Show different instructions to administrators and normal users.
- Show documentation only when a feature is enabled.
- Change text based on account plan, tenant configuration, or application state.
- Render real account-specific values.
- Link to actual named routes rather than hard-coded URLs.
- Render an application-native component.
- Deploy documentation atomically with the feature it describes.
- Run automated tests against documentation references.
- Remove pages from search and navigation when users cannot access them.

This is the reason the package should exist.

### 2.3 Important product distinction

The package should not merely:

> Display documentation inside Laravel.

It should:

> Let documentation participate safely in Laravel.

---

## 3. Initial Product Experience

A desirable initial experience:

```bash
composer require vendor/package
php artisan docs:install
```

The installer could publish:

```text
config/docs.php
resources/docs/
resources/views/vendor/docs/
public/vendor/docs/
```

The app then exposes:

```text
/docs
```

Expected baseline qualities:

- Attractive default design
- Fast page rendering
- Fast search
- Responsive layout
- Dark mode
- Keyboard-friendly navigation
- Table of contents
- Syntax highlighting
- Copy buttons for code
- Previous/next page navigation
- Customizable logo, colors, and typography
- Optional authentication middleware
- Configurable route prefix and domain
- Versioned assets
- Accessible HTML
- Good SEO when docs are public
- No indexing or leakage when docs are private

The experience should feel like a first-class product surface, not a package README viewer.

---

# 4. Naming Exploration

No final name has been selected.

The naming goal is to avoid literal names such as:

- Laravel Docs
- Laravel Documentation
- Docs Builder
- Documenter

Those names describe storage or writing but do not communicate what documentation does for a user.

The preferred naming territory is:

- Guidance
- Orientation
- Understanding
- Confidence
- Self-service
- Forward movement
- Finding answers
- Getting unstuck

One evocative noun is desirable because it fits Laravel's package ecosystem: examples include Horizon, Telescope, Scout, Pennant, and Wayfinder.

## 4.1 Strong candidates

### Docent

A docent is a knowledgeable guide who helps visitors understand what they are seeing.

Potential positioning:

> **Docent — Give your Laravel app a guide.**

> **Every application deserves a docent.**

> **Beautiful, searchable product guidance for Laravel.**

Example package ergonomics:

```bash
composer require vendor/docent
php artisan docent:install
```

```text
config/docent.php
resources/docs/
```

Advantages:

- Precise metaphor
- Professional
- Fits the Laravel ecosystem
- Describes guidance, not just writing
- Works well as a noun in commands and configuration

Concerns:

- The term is used by unrelated products and projects.
- An AI-agent analysis platform also uses “Docent.”
- The name may not be globally ownable.
- Trademark and Composer/Packagist availability must be rechecked before launch.

Current status:

> Strongest polished/professional candidate, but not selected.

### Unstuck

This names the user outcome rather than the mechanism.

Potential positioning:

> **Unstuck — Documentation that keeps users moving.**

> **Give your users somewhere to get unstuck.**

> **A beautiful self-service help center for Laravel.**

Advantages:

- Memorable
- Benefit-driven
- Approachable
- Broad enough to expand into troubleshooting, contextual help, and AI-assisted answers

Concerns:

- An education/study product already uses the name.
- Broad brand search results may be noisy.
- Less conventionally “Laravel package-like” than Docent.

Current status:

> Strongest personality-driven candidate.

### Bearings

Documentation helps users “get their bearings.”

Potential positioning:

> **Bearings — Help users find their way.**

> **Give your users their bearings.**

> **A native documentation center for Laravel applications.**

Advantages:

- Clever but understandable
- Strong orientation metaphor
- Good visual branding possibilities: compass, coordinates, map marks
- Communicates what documentation accomplishes

Concerns:

- Heavy search noise from mechanical bearings.
- Marketing would likely need “Bearings for Laravel.”
- Slightly less immediately obvious as a software product.

Current status:

> Strongest understated metaphor.

### Milepost

A milepost tells users where they are and helps them understand the road ahead.

Potential positioning:

> **Milepost — Product guidance along the way.**

> **Help users know where they are and what comes next.**

Advantages:

- Visual
- Friendly
- Suggests progress and orientation

Concerns:

- Existing businesses use the name.
- The metaphor is slightly narrower than Docent.
- It may imply linear tutorials more than a full documentation center.

### Onward

Documentation removes friction and lets the user continue.

Potential positioning:

> **Onward — Documentation that keeps users moving.**

> **Answers found. Work continued.**

Advantages:

- Positive
- Outcome-oriented
- Broad enough for docs, onboarding, and help

Concerns:

- Extremely common word
- Difficult search ownership
- Better as a tagline than a standalone package name

### Handrail

Documentation supports users while they navigate something complex.

Potential positioning:

> **Handrail — Support at every step.**

> **A dependable guide for your application's users.**

Advantages:

- Accurate support metaphor
- Particularly suitable for complex enterprise software

Concerns:

- Existing software company uses Handrail.
- May sound more like accessibility or safety software.
- Probably unsuitable as the final brand despite the strong metaphor.

## 4.2 Additional names considered

These were considered but ranked below the main shortlist.

### Familiar

Documentation makes an unfamiliar product familiar.

Concerns:

- Existing software and publishing uses.
- Generic word and difficult search ownership.

### Guidepost

A very direct navigation metaphor.

Concerns:

- Numerous active companies and software products already use it.
- Crowded brand space.

### Portico

A portico is an entrance: the docs can be seen as the welcoming entrance to application knowledge.

Concerns:

- Already heavily used in software and other businesses.
- Metaphor is elegant but not immediately self-explanatory.

### Fluency

Documentation makes users fluent in the application.

Concerns:

- “Fluent” already has substantial meaning in Laravel and PHP APIs.
- Potential ambiguity with Laravel's `Fluent` utility and fluent interfaces.

### Fieldguide / Field Guide

A field guide is an excellent description of practical user documentation.

Concerns:

- Fieldguide is already an established software company.
- Too likely to conflict in search and branding.

### Surefoot

Documentation makes users more confident and sure-footed.

Concerns:

- Metaphor may be one step too indirect.
- Existing uses may create search noise.

### Compendium

A complete body of knowledge.

Concerns:

- Emphasizes collection more than assistance.
- Sounds archival rather than actionable.

### Lore

Compact and memorable.

Concerns:

- Can imply informal, legendary, or fictional knowledge.
- Might weaken the authority of official product documentation.

### Answerbook

A place to find answers.

Concerns:

- Descriptive but less elegant.
- May feel dated.

### Helpway

A coined name suggesting a route to help.

Concerns:

- Sounds more like a standalone SaaS company than a Laravel package.
- Less natural and less sophisticated than the best candidates.

## 4.3 Names explicitly removed or strongly discouraged

### Wayfinder

Rejected because Laravel has an official package named Wayfinder.

### Lantern

Rejected because a Laravel-oriented package/project already uses Lantern.

### Sextant

Rejected because Laravel packages already use the name.

### Librarian

Rejected because a documentation-related package already uses the term.

### Onramp

Rejected because Tighten has used Onramp for Laravel learning, and the name is used by other onboarding products.

### Waybook

Rejected because an established training/onboarding/process-documentation company uses it.

### Guidebook

Rejected due to existing Laravel and broader software usage.

## 4.4 Working naming recommendation

The current top three:

1. **Docent**
2. **Unstuck**
3. **Bearings**

The working name used in API examples may be `Docent`, but it is only a placeholder.

Before final selection, re-run:

- Packagist exact-name search
- GitHub repository search
- Google general search
- Composer vendor/package availability
- PHP namespace search
- Domain availability
- USPTO and relevant international trademark search
- Social handle search

---

# 5. Competitor and Ecosystem Notes

The Laravel documentation-package space is not empty.

Known or discussed examples include:

- **LaRecipe** — established code-driven Markdown documentation inside Laravel.
- **Laravel App Documentation Editor** — edits Markdown and can submit GitHub pull requests.
- Other names previously surfaced in discovery:
  - LaraDocs
  - Lemme
  - Docs Builder
  - Laravel Docs
  - OI Laravel Documentation

The latter names should be reverified before relying on them in a formal market comparison.

LaRecipe is especially relevant because its headline proposition is already close to:

> Beautiful Markdown documentation inside a Laravel application.

Therefore, this package must not be positioned only as a prettier Markdown renderer. The stronger differentiation is the Laravel-aware contextual runtime:

- Authorization-aware navigation
- Authorization-safe search
- Dynamic application values
- Feature-aware content
- Native application components
- Testable route/gate/component references
- Multiple source providers
- Structured browser editing later

---

# 6. Documentation Storage and Authoring Models Considered

Three initial models were proposed, plus several hybrid/fourth options.

These choices involve at least three separate concerns:

1. **Authoring interface**
   - Text editor and Git
   - Browser rich-text editor
   - Both

2. **Source of truth**
   - Application repository
   - Database
   - Separate Git repository
   - Composite/override system

3. **Runtime representation and execution**
   - Markdown rendered directly
   - HTML blobs
   - Structured JSON
   - Common document AST
   - Laravel-aware runtime directives/components

These do not need to be the same decision.

---

## 6.1 Option 1: Markdown-like files in the application repository

Example:

```text
resources/docs/
├── index.md
├── getting-started/
│   ├── index.md
│   └── creating-a-project.md
├── billing/
│   ├── index.md
│   └── payment-methods.md
└── _partials/
    └── permissions-note.md
```

### Advantages

- Versioned with Git.
- Rollback comes naturally through source control.
- Documentation changes can be reviewed in pull requests.
- Documentation and application code can change together.
- Documentation can be tested in CI.
- Authors can preview locally.
- A feature and its documentation deploy atomically.
- Technical writers and developers can use familiar Markdown workflows.
- Easy to branch documentation along with code.
- Easy to maintain different docs for different application versions.
- No production write permissions are required.
- Static source can be parsed and cached aggressively.
- Can support Laravel-aware directives and components.

### Deepest advantage: application-version affinity

The strongest benefit is not merely rollback.

A pull request can:

- Add a feature.
- Add or change its docs.
- Register its documentation conditions or dynamic values.
- Update screenshots.
- Add policy visibility.
- Add automated documentation checks.
- Remove obsolete pages.
- Deploy all pieces as one release.

This is highly valuable when documentation describes software whose behavior changes frequently.

### Disadvantages

- No straightforward browser editor in production.
- Nontechnical editors may not have repository access.
- Content changes require a branch/commit/deployment workflow.
- Emergency corrections are slower than database publishing.
- Markdown alone is not a full structured editing experience.
- Concurrent editorial workflow depends on Git.
- Authors need a local environment or repository-based editing platform.

### Conclusion

This is the recommended primary workflow for v1.

---

## 6.2 Option 2: Documentation stored in the application database

Possible tables:

```text
documentation_pages
documentation_page_revisions
documentation_publications
```

Potential source representation:

- HTML
- Markdown text
- Tiptap/ProseMirror JSON
- Package-defined document JSON

### Advantages

- Browser editor can run in production.
- Editors do not need repository access.
- Content can be changed and published immediately.
- Drafts, scheduled publishing, approvals, and revision history are natural.
- Easier role-based editorial permissions.
- Easier collaboration and content ownership.
- Better fit for customer-success or support teams.
- Can support tenant-specific content.
- Can support content analytics and feedback workflows.

### Important correction to the initial concern

Database content does **not** have to be an opaque HTML blob.

A Tiptap document can be persisted as structured JSON. Custom nodes can represent:

- Gate blocks
- Policy blocks
- Conditions
- Dynamic values
- Native components
- Includes
- Route links
- Interactive actions

Laravel can interpret those nodes at render time.

Therefore, database content can still participate in Laravel if the format is structured and the runtime is designed correctly.

### Disadvantages

- Content and code no longer automatically deploy atomically.
- A page may reference a component or condition removed by a deployment.
- Technical writers who prefer files and Git may dislike the workflow.
- Database backups and revision tables replace Git's simple history.
- Local development may not contain production documentation.
- Review workflows must be implemented.
- Export/import and environment synchronization become concerns.
- Search and caching remain user-sensitive.
- Arbitrary executable logic in database content would be unsafe.

### Conclusion

Database authoring is viable, but should use structured content and stable, allowlisted integration identifiers.

It is a strong later feature, not the recommended foundational workflow for v1.

---

## 6.3 Option 3: Documentation files in a separate writable Git repository

Concept:

- Documentation lives in a dedicated repository.
- Technical authors can edit locally.
- A production web editor can read/write that repository.
- Production syncs documentation from Git.
- Browser publishing may create commits or pull requests.

### Intended benefit

This appears to provide the best of both worlds:

- Git history
- Local editing
- Browser editing
- Separate docs deployment
- No need to write into the main application repository

### Complexity introduced

- Git provider authentication
- Deploy keys, app tokens, or OAuth
- Read/write production credentials
- Repository and branch configuration
- Commit identity
- Commit signing
- Pull versus push synchronization
- Webhooks
- Merge conflicts
- Concurrent edits
- Failed pushes
- Branch protection
- Pull request workflow
- What “publish” means
- Whether a commit becomes live immediately
- How production detects updates
- What happens when GitHub/GitLab is unavailable
- How secrets are rotated
- Whether production is allowed to push at all
- Audit logging
- Environment drift
- Local changes conflicting with web edits

### Important limitation

This does not automatically provide instant publishing.

If the source of truth requires:

1. Commit
2. Push
3. Pull request
4. Merge
5. Deployment
6. Cache warming

then a browser editor cannot honestly promise “publish now” without bypassing part of the Git workflow.

### Conclusion

Potentially useful as an advanced integration, but too complicated to be the foundational setup or initial product promise.

Do not make production Git-write access a v1 requirement.

---

# 7. Option 4: Common Document AST with Pluggable Content Sources

This is the key missing option and the recommended long-term architecture.

Storage and rendering should be separated.

```text
Markdown files ───────────┐
                          │
Tiptap JSON ──────────────┼──> Common Document AST ──> Laravel-aware renderer
                          │
Database records ─────────┤
                          │
Package-provided docs ────┘
```

## 7.1 Internal document tree

A normalized document might contain nodes such as:

```text
Document
├── Heading
├── Paragraph
├── Text
├── Emphasis
├── Link
├── Image
├── List
├── Table
├── CodeBlock
├── Callout
├── Tabs
├── AuthorizationBlock
├── ConditionBlock
├── AudienceBlock
├── DynamicValue
├── ApplicationLink
├── ApplicationComponent
├── Include
└── EditableRegion
```

Every authoring format compiles into this model.

Examples:

- Markdown directive → `AuthorizationBlock`
- Tiptap custom node → `AuthorizationBlock`
- Database JSON node → `AuthorizationBlock`

The renderer does not need to know whether the content originated in a file or database.

## 7.2 Why this matters

It allows the package to start with files but later add:

- Database pages
- Tiptap editor
- Package-provided docs
- Tenant-specific docs
- Production overrides
- API-fed docs

without redesigning the Laravel integration runtime.

## 7.3 Suggested runtime pipeline

```text
Content Repository
    ↓
Document Source
    ↓
Parser
    ↓
Common AST
    ↓
Validation / Static Analysis
    ↓
Authorization and Dynamic Resolution
    ↓
Renderer
    ↓
HTML / Search Text / TOC / Exports
```

---

# 8. Additional Hybrid Models Considered

## 8.1 Hybrid A: Repository-owned page with database-editable regions

The file controls:

- Page existence
- Slug
- Navigation
- Authorization
- Logical structure
- Dynamic components

Selected prose fragments are editable in the database.

Example:

```markdown
---
title: Creating a Project
authorize: projects.view
---

# Creating a Project

<docs-editable key="projects.create.introduction">
Creating a project gives your team a shared workspace.
</docs-editable>

:::can ability="projects.create"
<docs-editable key="projects.create.instructions">
Select **New Project** and enter the project information.
</docs-editable>
:::

<docs-component name="project-limit-status" />
```

### Advantages

- Safe application logic remains in code.
- Editors can change selected prose instantly.
- Repository text provides a default/fallback.
- Browser editor does not control execution.
- Good for warnings, introductions, announcements, and frequently changing text.

### Disadvantages

- Editorial experience becomes fragmented.
- Editors cannot freely rearrange the full page.
- Developers must predeclare editable regions.
- It can become cumbersome if every paragraph is an override key.
- Database and file values may drift.

### Conclusion

Useful targeted feature, but should not be the only database workflow.

---

## 8.2 Hybrid B: Database drafts or production overrides over repository pages

Possible resolution order:

```text
1. Published database override
2. Repository page
3. Package default page
```

Workflow:

```text
Repository version
    ↓
Import into browser editor
    ↓
Database draft
    ↓
Preview
    ↓
Publish temporary override
    ↓
Export Markdown or create pull request
```

### Advantages

- Emergency fixes can be published immediately.
- Source can eventually be reconciled back into Git.
- Editors get a full-page browser experience.
- Repository remains the canonical long-term format.

### Disadvantages

- Creates configuration/content drift.
- Repository and production can disagree.
- Deployments may overwrite or ignore production changes.
- Requires comparison, reconciliation, and conflict UI.
- “Source of truth” becomes ambiguous.

The product would need to make drift obvious:

```text
This page has a production override newer than the repository version.

[Compare] [Export patch] [Create pull request] [Discard override]
```

### Conclusion

Credible advanced workflow, but not recommended for v1.

---

# 9. Do Not Use Actual MDX as the Core Format

The desired concept resembles MDX: prose mixed with executable or component behavior.

However, actual MDX combines:

- Markdown
- JSX
- JavaScript expressions
- ESM imports and exports
- A JavaScript compilation/runtime model

That is not a natural foundation for a server-rendered PHP/Laravel package.

The package should instead offer a Laravel-native equivalent, potentially described as:

- Laravel Markdown
- Dynamic Markdown
- Contextual Markdown
- Documentation components
- Markdown with directives

Do not imply strict compatibility with the MDX specification unless the package truly uses the MDX compiler and JSX runtime.

---

# 10. Laravel-Aware Documentation Syntax

Two syntax styles were considered.

## 10.1 Directive/container syntax

```markdown
---
title: Managing Billing
authorize: billing.view
---

# Managing Billing

Your current plan is {{ value:account.plan_name }}.

:::can ability="billing.manage"
You can update your payment method from
[Billing settings]({{ link:billing.settings }}).
:::

:::cannot ability="billing.manage"
Only an account administrator can change the payment method.
:::

:::when condition="advanced-exports-enabled"
Advanced exports are available on your account.
:::

<docs-component name="billing-usage" />
```

Advantages:

- Reads naturally in Markdown.
- Easier for technical authors.
- Clear separation between content and dynamic behavior.
- Can be parsed into explicit AST nodes.

## 10.2 HTML/component-style syntax

```markdown
<docs-can ability="billing.manage">

## Update your payment method

Visit your billing settings to update the card on file.

</docs-can>

<docs-component name="billing-usage" />
```

Advantages:

- Familiar to authors who know component systems.
- Easy to represent nested structured content.
- Maps naturally to Tiptap custom nodes.

Concerns:

- HTML-like syntax can become verbose.
- Must avoid allowing arbitrary unsafe tags or Blade execution.

## 10.3 Front matter

Suggested metadata:

```yaml
---
title: Payroll Reports
description: Review and export payroll reports.
slug: payroll/reports
authorize: payroll.view
navigation:
  section: Payroll
  order: 20
search:
  exclude: false
---
```

Potential metadata:

- Title
- Description
- Slug
- Redirects
- Page-level gate/policy
- Navigation group and order
- Search inclusion
- Draft status
- Version compatibility
- Tags
- Audience
- Layout
- Table-of-contents depth

---

# 11. Do Not Allow Arbitrary Application Code in Documentation

It would be possible to render raw Blade or PHP:

```blade
@if(auth()->user()->account->plan->allowsAdvancedExports())
    ...
@endif
```

This is not recommended as the primary abstraction.

## Problems with arbitrary PHP/Blade in docs

- Documentation authors must understand internal models.
- Refactors silently break docs.
- Static analysis becomes difficult.
- Search indexing becomes difficult.
- Local preview requires full application state.
- Database-authored executable code is a serious security risk.
- Caching can leak personalized content.
- Exporting to other formats becomes difficult.
- The package cannot identify dependencies reliably.
- Error messages become harder to make editor-friendly.

## Recommended alternative: stable, registered identifiers

The application registers allowlisted integrations:

```php
use Docent\Facades\Docent;

Docent::condition(
    'advanced-exports-enabled',
    fn (DocumentationContext $context): bool =>
        $context->user?->account->plan->allowsAdvancedExports() ?? false,
);

Docent::value(
    'account.plan_name',
    fn (DocumentationContext $context): string =>
        $context->user?->account->plan->name ?? 'Unknown',
);

Docent::link(
    'billing.settings',
    fn (DocumentationContext $context): string =>
        route('billing.settings'),
);

Docent::component(
    'billing-usage',
    BillingUsageDocumentationComponent::class,
);
```

Content uses stable public identifiers:

```markdown
:::when condition="advanced-exports-enabled"
Advanced exports are available on your account.
:::

Your current plan is {{ value:account.plan_name }}.

[Manage billing]({{ link:billing.settings }}).
```

This registration layer becomes a public API between the application and its docs.

The application can refactor internal models while keeping identifiers stable.

---

# 12. Documentation Context

The renderer needs an explicit context object.

Possible shape:

```php
final class DocumentationContext
{
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly Request $request,
        public readonly Container $container,
        public readonly array $parameters = [],
        public readonly ?string $audience = null,
        public readonly ?string $tenantId = null,
    ) {}
}
```

Potential context data:

- Current user
- Current request
- Auth guard
- Tenant/account
- Locale
- Product/application version
- Feature flags
- Route parameters
- Preview user
- Preview audience
- Arbitrary host application parameters

The context should be passed explicitly rather than accessed through hidden global state wherever practical.

---

# 13. Types of Laravel-Aware Nodes

The runtime could support the following concepts.

## 13.1 Page authorization

```yaml
authorize: payroll.view
```

The entire page is unavailable when authorization fails.

Desired behavior may be configurable:

- 404 to avoid revealing page existence
- 403
- Redirect to login
- Redirect to application
- Custom response

## 13.2 Conditional blocks

```markdown
:::can ability="billing.manage"
Administrator instructions.
:::
```

```markdown
:::when condition="feature-enabled"
Feature-specific instructions.
:::
```

## 13.3 Alternative/fallback content

```markdown
:::choose
:::case condition="advanced-exports-enabled"
Use Advanced Export.
:::
:::default
Use Standard Export.
:::
:::
```

This was not fully designed but follows naturally from conditional content.

## 13.4 Dynamic values

```markdown
Your current plan is {{ value:account.plan_name }}.
```

Values should be escaped by default.

Possible explicit raw HTML output should be heavily restricted or represented by a component instead.

## 13.5 Application links

```markdown
[Manage billing]({{ link:billing.settings }})
```

Could also support Laravel named routes directly:

```markdown
[Manage billing]({{ route:billing.settings }})
```

However, registered links are more stable if route names or parameters are complex.

## 13.6 Native components

```markdown
<docs-component name="billing-usage" />
```

Use cases:

- Current storage usage
- Current plan limits
- Interactive checklists
- Status panels
- Application screenshots generated from real data
- Contextual call-to-action buttons
- Diagnostic tools

Components should have a constrained contract and explicit authorization behavior.

## 13.7 Includes and reusable fragments

```markdown
:::include name="permissions-note"
:::
```

Includes can come from:

- `_partials` directory
- Package-provided fragments
- Database fragments
- Registered renderers

Need cycle detection and dependency tracking.

## 13.8 Audience blocks

```markdown
:::audience name="billing-admin"
Only billing administrators see this content.
:::
```

Audiences are useful for safe search indexing and cache grouping.

---

# 14. Tiptap / Browser Editor Model

Tiptap is a plausible browser editor because it supports:

- Structured JSON
- Custom nodes
- Custom node views
- Validation through a schema
- Rich authoring interfaces
- Custom toolbars and dialogs

A gate block can appear visually in the editor:

```text
┌ Conditional content ─────────────────────────────┐
│ Show when: Gate “billing.manage”                 │
│                                                  │
│ Administrators can update the payment method.   │
└──────────────────────────────────────────────────┘
```

Potential custom editor nodes:

- Gate block
- Policy block
- Feature condition
- Audience block
- Dynamic value
- Application component
- Route/application link
- Documentation include
- Interactive action
- Editable region
- User-specific example

Example persisted JSON:

```json
{
  "type": "docsGate",
  "attrs": {
    "ability": "billing.manage"
  },
  "content": [
    {
      "type": "paragraph",
      "content": [
        {
          "type": "text",
          "text": "You can update your payment method."
        }
      ]
    }
  ]
}
```

Laravel—not JavaScript—should remain authoritative for evaluating the gate at runtime.

## 14.1 Editor experience requirements

The editor should eventually provide:

- Dropdown of registered gates/conditions
- Human-readable descriptions for integrations
- Validation for missing identifiers
- Preview as current user
- Preview as selected user
- Preview as named audience
- Draft and publish states
- Revision history
- Compare revisions
- Warnings when a referenced component no longer exists
- Search preview
- Navigation editor
- Link validation
- Image/media management

## 14.2 Integration registry metadata

To make the editor usable, registered items should expose metadata:

```php
Docent::condition(
    name: 'advanced-exports-enabled',
    resolver: fn (DocumentationContext $context) => ...,
    label: 'Advanced exports enabled',
    description: 'True when the account may use advanced exports.',
);
```

The editor can list these without understanding the callback.

---

# 15. Proposed Package Architecture

## 15.1 Content repositories

```php
interface DocumentationRepository
{
    public function find(string $slug): ?DocumentSource;

    public function navigation(
        DocumentationContext $context
    ): Navigation;

    public function all(): iterable;
}
```

Potential implementations:

```text
FilesystemDocumentationRepository
DatabaseDocumentationRepository
PackageDocumentationRepository
CompositeDocumentationRepository
OverrideDocumentationRepository
```

Responsibilities:

- Locate source documents.
- Enumerate documents for indexing and validation.
- Resolve navigation metadata.
- Expose source version/hash.
- Expose modification information.
- Avoid rendering concerns.

## 15.2 Parsers

```php
interface DocumentParser
{
    public function supports(DocumentSource $source): bool;

    public function parse(DocumentSource $source): Document;
}
```

Implementations:

```text
MarkdownDocumentParser
TiptapDocumentParser
JsonDocumentParser
```

All return the common AST.

## 15.3 Integration resolvers

```php
interface DocumentationCondition
{
    public function evaluate(
        DocumentationContext $context,
        array $arguments
    ): bool;
}
```

```php
interface DocumentationValue
{
    public function resolve(
        DocumentationContext $context,
        array $arguments
    ): mixed;
}
```

```php
interface DocumentationComponent
{
    public function render(
        DocumentationContext $context,
        array $arguments
    ): Htmlable;
}
```

Other possible contracts:

```text
DocumentationLink
DocumentationAction
DocumentationAudience
DocumentationAuthorization
```

## 15.4 Renderers

```text
HtmlRenderer
SearchTextRenderer
PlainTextRenderer
TableOfContentsRenderer
StaticAnalysisRenderer
MarkdownExporter
```

Different renderers should walk the same AST.

This is important because searchable text is not always the same as personalized HTML.

## 15.5 Compiler/processing stages

Possible stages:

```text
Parse
Normalize
Validate
Resolve includes
Annotate dependencies
Evaluate authorization
Resolve dynamic nodes
Render
```

Static validation should happen before user-sensitive execution whenever possible.

---

# 16. Search Is a Major Security Boundary

Conditional documentation makes naïve global indexing unsafe.

Example:

```markdown
:::can ability="view-payroll"
Payroll reports include employee salary information.
:::
```

A search index can leak restricted information through:

- Page title
- Keywords
- Headings
- Search snippets
- Result count
- Navigation breadcrumbs
- Suggested searches

Even if clicking the result returns 403, the snippet has already leaked content.

## 16.1 Page-level authorization

Example:

```yaml
---
title: Payroll Reports
authorize: payroll.view
---
```

Unauthorized pages must be removed before results are returned.

The result should generally behave as if the page does not exist.

## 16.2 Block-level authorization

Possible strategies:

1. Do not index conditional blocks.
2. Index with attached visibility metadata.
3. Generate separate audience indexes.
4. Retrieve broad candidates and re-render/rebuild snippets after authorization.
5. Build per-user indexes, which is usually impractical.

## 16.3 Recommended initial strategy

For v1:

- Index unconditional content globally.
- Store page-level authorization metadata with each search record.
- Filter page results through current authorization.
- Exclude dynamic values from global indexes.
- Exclude block-level conditional content by default.
- Generate snippets only from content confirmed visible to the current user.
- Avoid exposing inaccessible headings or breadcrumbs.

Later, support named audiences:

```php
Docent::audience(
    'billing-admin',
    fn (DocumentationContext $context) =>
        Gate::forUser($context->user)->allows('billing.manage')
);
```

Then:

```markdown
:::audience name="billing-admin"
Administrators can update organization billing information.
:::
```

Stable audiences can support:

- Audience-specific indexes
- Audience-specific rendered caches
- Preview
- Analytics segmentation

## 16.4 Search backends

Not decided.

Potential options:

- Local prebuilt JSON/Lunr-style index
- SQLite full-text search
- Database full-text search
- Laravel Scout
- Meilisearch
- Typesense
- Algolia

The default should work without external infrastructure.

A server-side search endpoint may be safer for private/contextual docs than shipping the entire index to the browser.

Do not ship restricted content in a client-side static search index.

---

# 17. Caching and Personalization

A user-specific page cannot be cached globally under:

```text
docs:billing
```

That could serve one user's content to another.

## 17.1 Recommended cache layers

```text
Parsed source / AST            globally cacheable
Normalized document            globally cacheable
Static analysis results        globally cacheable
Navigation skeleton            often globally cacheable
Authorization decisions        request/user/audience scoped
Dynamic values                 request scoped
Final personalized HTML        usually not globally cacheable
```

## 17.2 Audience caching

If content is guaranteed to vary only by a stable audience:

```text
docs:rendered:billing:billing-admin
```

This should require explicit declaration. The package must not guess that arbitrary user-specific logic is audience-safe.

## 17.3 Cache safety requirements

- Dynamic nodes should indicate whether they are cacheable.
- Components should declare cache scope:
  - Global
  - Audience
  - Tenant
  - User
  - Request/no cache
- Final response caching should be disabled by default for personalized pages.
- Cache keys must include locale and documentation version.
- Invalidating a source should invalidate dependent includes and rendered pages.

---

# 18. Static Analysis and Testing

A major opportunity is to make documentation testable.

Suggested command:

```bash
php artisan docs:check
```

Potential checks:

- Invalid front matter
- Missing titles
- Duplicate slugs
- Broken internal links
- Missing images/assets
- Unknown gates
- Unknown policies
- Unknown conditions
- Unknown values
- Unknown components
- Unknown audiences
- Invalid component arguments
- Links to nonexistent named routes
- Invalid include names
- Include cycles
- Pages unreachable from navigation
- Orphaned pages
- Duplicate navigation order
- Heading hierarchy problems
- Empty sections
- Invalid code-language identifiers
- Search indexing conflicts
- Unsafe raw HTML
- Database page schema mismatch
- References removed since the last application release

Potential CI behavior:

```bash
php artisan docs:check --strict
```

Possible testing helpers:

```php
$this->docs()
    ->page('billing/payment-methods')
    ->as($user)
    ->assertVisible()
    ->assertSee('Update your payment method')
    ->assertDontSee('Internal billing ID');
```

```php
$this->docs()
    ->search('payroll', as: $unauthorizedUser)
    ->assertMissing('Payroll Reports');
```

This is a meaningful differentiator from generic documentation platforms.

---

# 19. Versioning and Deployment

## 19.1 Repository-backed docs

Naturally versioned with the application.

Possible behavior:

- Current docs only
- Version switcher
- Docs selected by application release
- Multiple directories:

```text
resources/docs/v1/
resources/docs/v2/
```

Or branch/tag-based builds.

Versioning strategy is not yet decided.

## 19.2 Database-backed docs

Need explicit concepts:

- Draft revision
- Published revision
- Publication date
- Application compatibility range
- Schema version
- Referenced integration identifiers
- Rollback
- Audit trail

Potential problem:

A database page references:

```text
component: account-storage-chart
```

A deployment removes that component.

Mitigations:

- Stable integration names
- Deployment-time `docs:check-production`
- Graceful missing-component UI
- Compatibility metadata
- Refuse deployment when published docs contain invalid references
- Preserve deprecated integrations temporarily

## 19.3 Separate repository

Treat as a later connector rather than a core assumption.

Possible future workflows:

- Read-only sync
- Export to Git
- Create pull request
- Import from branch
- Webhook-triggered refresh
- No direct production pushes by default

---

# 20. Recommended Product Roadmap

## Version 1: Repository-first contextual documentation

Core scope:

- Markdown files in `resources/docs`
- YAML front matter
- Beautiful `/docs` UI
- Configurable route and middleware
- Authentication support
- Page-level Laravel gate/policy authorization
- Conditional blocks
- Named conditions
- Named dynamic values
- Named application links
- Named application components
- Includes/partials
- Common internal AST
- Filesystem content repository
- Fast parsing and AST caching
- Permission-safe server-side search
- Navigation filtered by authorization
- Table of contents
- Syntax highlighting
- `docs:check`
- Testing helpers
- Theme customization
- No production editor

This version should strongly prove the Laravel-native differentiation.

## Version 1.5: Product insight and extensibility

Potential features:

- Stabilized repository/provider contracts
- Documentation feedback: “Was this helpful?”
- Search analytics
- Searches with no results
- Most-viewed pages
- Exit/click analytics
- Database-backed notices or snippets
- Revision model groundwork
- More application components
- Named audiences
- Better preview tooling
- Export/static snapshot support

## Version 2: Structured database provider and Tiptap editor

Potential scope:

- Optional migrations
- Database documentation repository
- Tiptap editor
- Custom nodes matching Markdown directives
- Draft/publish workflow
- Revision history
- Preview as user or audience
- Navigation editing
- Media handling
- Validation against registered integrations
- Database search indexing
- Role-based editorial access
- Import from Markdown
- Export to normalized Markdown

## Later / Advanced

- Create GitHub/GitLab pull requests from browser edits
- Temporary production overrides
- Compare database override to repository source
- Tenant-specific documentation
- Localization workflows
- Collaborative editing
- Approval workflow
- Scheduled publication
- AI-assisted search or answer generation
- Contextual help widgets embedded throughout the host app
- “Open relevant docs” based on current application route
- MCP or agent-facing documentation interface
- Documentation health dashboard

---

# 21. Explicit Non-Goals or Deferred Decisions

For the initial release, avoid:

- Raw arbitrary PHP in documentation.
- Raw arbitrary Blade in database-authored content.
- Treating HTML blobs as the canonical structured format.
- Full production Git write integration.
- Perfect lossless two-way Markdown ↔ Tiptap round-tripping.
- Per-user prebuilt search indexes.
- Globally caching personalized rendered HTML.
- Building a full headless CMS before validating the runtime.
- Requiring external search infrastructure.
- Supporting every possible authoring format.
- Competing only on visual design with Mintlify or LaRecipe.

## 21.1 Markdown/Tiptap round-trip warning

Both formats can represent the same semantic AST, but lossless round-tripping is difficult.

Potentially lost or changed:

- Whitespace
- Comment placement
- Exact directive formatting
- Author-specific line wrapping
- Unsupported Markdown constructs
- Raw HTML
- Attribute order
- Custom syntax
- Formatting not represented in the editor schema

The product should promise semantic import/export, not byte-for-byte preservation.

---

# 22. Proposed API Sketches

These are conceptual, not final.

## 22.1 Configuration

```php
return [
    'route' => [
        'prefix' => 'docs',
        'domain' => null,
        'middleware' => ['web', 'auth'],
    ],

    'repository' => 'filesystem',

    'filesystem' => [
        'path' => resource_path('docs'),
    ],

    'search' => [
        'driver' => 'database',
        'index_conditional_blocks' => false,
    ],

    'authorization' => [
        'denied_response' => 404,
    ],

    'cache' => [
        'store' => null,
        'prefix' => 'docs',
    ],
];
```

## 22.2 Registration

```php
Docent::condition(
    'advanced-exports-enabled',
    fn (DocumentationContext $context): bool => ...
);

Docent::value(
    'account.plan_name',
    fn (DocumentationContext $context): string => ...
);

Docent::link(
    'billing.settings',
    fn (DocumentationContext $context): string => ...
);

Docent::component(
    'billing-usage',
    BillingUsageDocumentationComponent::class,
);

Docent::audience(
    'billing-admin',
    fn (DocumentationContext $context): bool => ...
);
```

## 22.3 Service-provider style registration

```php
final class DocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Docent::condition(
            'advanced-exports-enabled',
            AdvancedExportsEnabled::class
        );

        Docent::component(
            'billing-usage',
            BillingUsageDocumentationComponent::class
        );
    }
}
```

Class-based registrations may be easier to inspect, test, and describe in the editor than closures.

## 22.4 Component contract

```php
final class BillingUsageDocumentationComponent
    implements DocumentationComponent
{
    public function render(
        DocumentationContext $context,
        array $arguments
    ): Htmlable {
        // Resolve data and return a safe view.
    }

    public function cacheScope(): CacheScope
    {
        return CacheScope::User;
    }
}
```

---

# 23. Possible File Layout

Application:

```text
app/
├── Documentation/
│   ├── Components/
│   ├── Conditions/
│   ├── Values/
│   └── Audiences/
├── Providers/
│   └── DocumentationServiceProvider.php
resources/
├── docs/
│   ├── index.md
│   ├── getting-started/
│   ├── billing/
│   └── _partials/
└── views/
    └── vendor/
        └── docent/
config/
└── docent.php
```

Package:

```text
src/
├── Console/
├── Content/
│   ├── Repositories/
│   └── DocumentSource.php
├── Documents/
│   ├── Ast/
│   ├── Parser/
│   ├── Renderer/
│   └── Validation/
├── Runtime/
│   ├── DocumentationContext.php
│   ├── Conditions/
│   ├── Values/
│   ├── Components/
│   └── Audiences/
├── Search/
├── Navigation/
├── Http/
├── Support/
└── DocentServiceProvider.php
```

---

# 24. Open Product and Architecture Questions

These remain unresolved.

## Naming

- Is `Docent` available and defensible enough?
- Should the package use a metaphorical name or a benefit name?
- Is clarity more important than global search ownership?
- What Composer vendor/package name will be used?

## Content syntax

- Directive syntax, HTML-component syntax, or both?
- Which Markdown parser should be used?
- How much raw HTML should be allowed?
- Should named Laravel routes be first-class or always registered as links?
- How are policy arguments represented?
- Are model-specific policy checks supported in static docs?

## Authorization

- Default denied response: 404 or 403?
- Can page authorization be a gate, policy, audience, or condition?
- How does preview-as-user work securely?
- Can anonymous and authenticated content coexist on one page?

## Search

- Default backend?
- Server-side or browser-side?
- How are page-level permissions encoded?
- Are conditional blocks excluded by default?
- How are snippets generated without leakage?
- Should named audiences be in v1?

## Components

- Can components include forms or actions?
- Are components server-rendered, Livewire, Blade, Inertia, or framework-neutral?
- How does the package avoid coupling its UI to one frontend stack?
- What is the security model for interactive components?
- How are component assets loaded?

## Database editor

- Is Tiptap the preferred editor?
- Store Tiptap JSON directly or convert immediately to package AST JSON?
- Which format is canonical?
- How are unsupported imported Markdown nodes represented?
- How does editor schema migration work?

## Localization

- Separate directories per locale?
- Translation keys?
- Database translations?
- Fallback behavior?
- Search per locale?

## Multi-tenancy

- Can tenants override pages?
- Can a tenant supply custom pages?
- Is tenant-specific search feasible?
- What prevents cross-tenant cache leakage?

## Versioning

- Does documentation track application version?
- Does the package support multiple simultaneous docs versions?
- How are database pages tied to releases?
- Can a page declare minimum/maximum application versions?

## UI extensibility

- Blade views only?
- Headless API?
- Inertia adapter?
- Livewire adapter?
- User-provided layout?
- Theme tokens?
- Fully publishable frontend assets?

---

# 25. Recommended Immediate Decisions

Before implementation begins, make these decisions explicitly:

1. **Select a temporary project codename.**
   - `Docent` is acceptable as a working name.

2. **Commit to repository-first v1.**
   - Do not build the production editor first.

3. **Design the common AST before tightly coupling rendering to Markdown.**
   - The AST can begin small and grow.

4. **Use allowlisted integrations, not raw PHP.**
   - Conditions, values, links, audiences, and components should be registered.

5. **Treat search as authorization-sensitive from day one.**
   - Do not retrofit privacy later.

6. **Separate parsing cache from personalized render cache.**
   - Never globally cache arbitrary user-specific HTML.

7. **Build `docs:check` early.**
   - It reinforces the code-native value proposition.

8. **Keep provider interfaces internal or marked experimental until proven.**
   - Avoid freezing a poor extension API too early.

9. **Do not promise lossless Markdown/editor synchronization.**

10. **Re-run naming and competitor research immediately before public launch.**

---

# 26. Suggested First Engineering Milestones

## Milestone 1: Core document model

- Define `DocumentSource`.
- Define basic AST nodes.
- Parse front matter.
- Parse standard Markdown.
- Render HTML.
- Render plain/search text.
- Unit-test AST and renderer.

## Milestone 2: Laravel package shell

- Service provider.
- Config.
- Auto-discovery.
- Install/publish command.
- `/docs` route.
- Theme/layout.
- Asset build and distribution.

## Milestone 3: Navigation and files

- Filesystem repository.
- Directory conventions.
- Slug resolution.
- Navigation tree.
- Redirects.
- 404 behavior.
- Table of contents.

## Milestone 4: Contextual runtime

- `DocumentationContext`.
- Page authorization.
- `can`/`cannot`.
- Named conditions.
- Named values.
- Named links.
- Components.
- Includes.

## Milestone 5: Security and cache model

- AST cache.
- Request-scoped evaluation.
- Cache-scope declarations.
- Authorization-filtered navigation.
- HTML escaping.
- Raw HTML policy.
- Dependency invalidation.

## Milestone 6: Search

- Build index from AST.
- Exclude conditional content by default.
- Store page authorization metadata.
- Authorize before returning results.
- Generate safe snippets.
- Test for information leakage.

## Milestone 7: Validation and developer experience

- `docs:check`.
- Broken links.
- Missing integrations.
- Missing assets.
- Include cycles.
- Testing helpers.
- Clear errors with source line information.

## Milestone 8: Production polish

- Custom themes.
- Accessibility audit.
- Responsive navigation.
- Dark mode.
- Performance profiling.
- Example application.
- Package documentation.
- Upgrade strategy.

---

# 27. Product Principles

Use these principles to evaluate future features.

1. **Application-native over merely embedded**
   - A page should understand the app when useful.

2. **Structured behavior over arbitrary execution**
   - Registered capabilities are safer and more maintainable than raw code.

3. **Authorization before convenience**
   - Navigation, search, snippets, and caching must respect visibility.

4. **Repository-first, not repository-only**
   - Start with the strongest workflow without preventing future database editing.

5. **One semantic document model**
   - Multiple authoring and storage systems should converge on the same AST.

6. **Fast static work, late dynamic work**
   - Parse and cache source globally; evaluate user context as late as possible.

7. **Documentation is deployable software**
   - Validate it, test it, review it, and version it.

8. **Graceful defaults, deep extensibility**
   - Installation should be instant, but advanced apps should be able to integrate deeply.

9. **Do not leak through secondary surfaces**
   - Search, navigation, analytics, URLs, and caches are all part of authorization.

10. **The runtime is the product**
    - Markdown and Tiptap are interfaces. Git and the database are persistence. The defensible value is the Laravel-aware documentation runtime.

---

# 28. Concise Final Recommendation

Build v1 as a beautiful, repository-backed documentation package with:

- Markdown source
- A common internal AST
- Laravel authentication and middleware
- Page and block authorization
- Named conditions, values, links, audiences, and components
- Permission-safe navigation and search
- Strong static validation
- Automated testing support
- Aggressive source/AST caching, but conservative personalized HTML caching

Later, add a database provider and Tiptap editor that produce the exact same AST nodes.

Do not begin with:

- Opaque HTML
- Raw Blade/PHP
- Writable production Git integration
- Perfect Markdown/Tiptap round-tripping
- A globally shipped search index containing restricted content

The central product statement is:

> **A Laravel-native documentation system where guidance can respond safely to who the user is, what they can access, and what is happening inside the application.**

---

# 29. Reference Links and Verification Notes

These links were used or discussed during initial research. They are not legal/trademark clearance.

## Laravel and editor architecture

- Laravel authorization:  
  https://laravel.com/docs/13.x/authorization

- Laravel package development:  
  https://laravel.com/docs/13.x/packages

- Tiptap JSON/HTML output:  
  https://tiptap.dev/docs/guides/output-json-html

- Tiptap custom node API:  
  https://tiptap.dev/docs/editor/extensions/custom-extensions/create-new/node

- Tiptap custom node views:  
  https://tiptap.dev/docs/editor/extensions/custom-extensions/node-views

- Tiptap extension names persisted in JSON:  
  https://tiptap.dev/docs/editor/extensions/custom-extensions/create-new/extension

- Tiptap custom Markdown parsing:  
  https://tiptap.dev/docs/editor/markdown/advanced-usage/custom-parsing

- MDX definition:  
  https://mdxjs.com/docs/what-is-mdx/

## Related Laravel packages

- LaRecipe:  
  https://github.com/saleem-hadad/larecipe  
  https://packagist.org/packages/binarytorch/larecipe

- Laravel App Documentation Editor:  
  https://packagist.org/packages/artisansplatform/laravel-app-documentation-editor

- Laravel Wayfinder, confirming the name is occupied:  
  https://github.com/laravel/wayfinder  
  https://packagist.org/packages/laravel/wayfinder

- Lantern, confirming Laravel usage:  
  https://github.com/lanternphp/lantern  
  https://packagist.org/packages/lanternphp/lantern

- Sextant examples:  
  https://packagist.org/packages/projectinfiniteme/sextant  
  https://github.com/projectinfiniteme/sextant

## Broader naming conflicts noted during brainstorming

- Transluce Docent:  
  https://docs.transluce.org/introduction

- Unstuck study product:  
  https://unstuckstudy.com/

- Milepost:  
  https://mileposthq.com/

- Handrail Software:  
  https://handrailsoftware.com/

- Fieldguide:  
  https://www.fieldguide.io/

- Waybook:  
  https://www.waybook.com/

Recheck every candidate immediately before selecting a public name.
