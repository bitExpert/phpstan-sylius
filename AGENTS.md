# AGENTS.md – PHPStan Sylius Extension

This file provides structured context for AI agents (e.g., copilot, Claude, etc.) working on the `bitexpert/phpstan-sylius` codebase. It documents architecture, conventions, and key implementation details to enable rapid, accurate contributions.

---

## Overview

- **Package**: `bitexpert/phpstan-sylius`
- **Type**: PHPStan extension (type: `phpstan-extension` in `composer.json`)
- **Purpose**: Additional static analysis rules for Sylius projects, validating grid configurations and resource metadata.
- **Requirements**: PHP ^8.2, PHPStan ^2.1. Dev: resource-bundle ^1.12, grid-bundle **^1.15**.
- **grid-bundle support**: `>= 1.15`. CI exercises both `^1.15 <1.16` and `^1.16`; behaviour differs between them (see [Grid Bundle 1.16](#grid-bundle-116)).
- **License**: MIT

### Installation & Usage

Install via Composer as a dev dependency:
```bash
composer require --dev bitexpert/phpstan-sylius
```

The extension is auto-discovered via `composer.json` `extra.phpstan.includes` (`extension.neon`).

---

## Project Structure

```
src/
└─ bitExpert/PHPStan/
   ├─ Util/PropertyName.php                         # Utility for snake_case → camelCase conversion
   ├─ Sylius/
   │  ├─ Collector/Grid/
   │  │  ├─ AbstractGridClassCollector.php           # Base: grid detection logic
   │  │  ├─ CollectRessourceClassForGridClass.php    # Collector: grid ↔ resource mapping
   │  │  ├─ CollectFieldsForGridClass.php            # Collector: grid field definitions
   │  │  └─ CollectFilterForGridClass.php            # Collector: grid filter definitions
   │  │
   │  ├─ Collector/Grid/Field/
   │  │  ├─ FieldNode.php                            # Interface for field type descriptors
   │  │  ├─ FieldRegistry.php                        # Interface
   │  │  ├─ FieldRegistryFactory.php                 # DI factory for field registry
   │  │  ├─ DefaultFieldRegistry.php                 # Concrete registry
   │  │  └─ *FieldNode.php                           # StringFieldNode, DateTimeFieldNode, TwigFieldNode, EnumFieldNode, CallableFieldNode, GenericFieldNode
   │  │
   │  ├─ Collector/Grid/Filter/
   │  │  ├─ FilterNode.php                           # Interface for filter type descriptors
   │  │  ├─ FilterRegistry.php                       # Interface
   │  │  ├─ FilterRegistryFactory.php                # DI factory for filter registry
   │  │  ├─ DefaultFilterRegistry.php                # Concrete registry
   │  │  └─ *.php                                    # EntityFilter, EnumFilter, ExistsFilter, SelectFilter, StringFilter, BooleanFilter, DateFilter, MoneyFilter, Filter (catch-all)
   │  │
   │  └─ Rule/
   │     ├─ Grid/
   │     │  ├─ ResourceAwareGridNeedsResourceClass.php   # Rule: check grid resource class exists
   │     │  ├─ GridBuilderFieldIsPartOfResourceClass.php # Rule: grid fields must match resource
   │     │  └─ GridBuilderFilterIsPartOfResourceClass.php# Rule: grid filters must match resource
   │     │
   │     └─ Resource/
   │        ├─ IndexOperationNeedsGridClassRule.php      # Rule: Index metadata grid exists
   │        └─ ResourceAttributeNeedsFormTypeRule.php    # Rule: AsResource form type exists
```

**Note**: `CollectRessourceClassForGridClass.php` contains a typo: `Ressource` (double `s`), which is preserved for historical compatibility.

**Note**: field nodes carry a `Node` suffix in their class name; filter nodes do **not**. `Field/StringFieldNode.php` vs `Filter/StringFilter.php`.

---

## Key Conventions

### 1. Coding Standards
- PSR-2 (with PHP-CS-Fixer config):
  - Short array syntax `[]`
  - Strict types declared per file (`declare(strict_types=1);`)
  - `final` classes where appropriate
  - `readonly` properties (PHP 8.1+)
  - Constructor property promotion
  - No trailing whitespace, single space around concatenation
- **Strict mode**: All files are strict-typed.
- **PHP Version**: Minimum 8.2 (uses readonly properties, union types, attributes, etc.)

### 2. File Naming
- Classes are `PascalCase`.
- Test files end with `UnitTest.php`.
- Data fixtures (for analysis) use descriptive names like `grid.php`, `entity.php`, often matching test method context.

### 3. Namespace
- Source: `bitExpert\PHPStan\`
- Tests: `bitExpert\PHPStan\` (autoload-dev maps tests to same namespace)

---

## Architecture & Workflow

### Two-Layer Pattern: Collectors → Rules

PHPStan extensions in this project follow a two-phase approach:

1. **Collectors** scan AST and return lightweight tuples (grid class, property/filter names, line numbers).
2. **Rules** consume collected data (via `CollectedDataNode`) and perform validation, reporting errors.

This separation improves performance (collectors can run in parallel) and keeps rules focused on analysis rather than parsing.

> **Note**: `getNodeType()` is authoritative and must agree with the `@implements` generic. Both grid
> rules return `CollectedDataNode::class`. The `processNode()` parameter stays typed as `Node` because
> that is what `Rule::processNode()` declares, and narrowing a parameter is a contravariance
> violation. Do **not** add a defensive `instanceof` guard to compensate — see
> [Known Issue 2](#2-a-redundant-instanceof-guard-can-hide-a-wrong-implements).

### Rule Execution Flow

| Rule | Node Type | Key Logic |
|------|-----------|-----------|
| `ResourceAwareGridNeedsResourceClass` | `InClassNode` | Checks `#AsGrid(resourceClass:)` first, then `getResourceClass()` / `ResourceAwareGridInterface`, for an existing class. |
| `GridBuilderFieldIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid field exists as a property/getter on the resource class. Supports recursive fields (`address.city`). |
| `GridBuilderFilterIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid filter field exists on resource. Does **not** support recursive checks (dots are skipped). |
| `IndexOperationNeedsGridClassRule` | `InClassNode` | Validates `#[Index(grid: '...')]` refers to an existing grid class. |
| `ResourceAttributeNeedsFormTypeRule` | `InClassNode` | Validates `#[AsResource(formType: '...')]` refers to an existing form type. |

---

## Rules Reference

### `ResourceAwareGridNeedsResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid`
- **Node type**: `InClassNode`
- **Checks, in order**:
  1. `#[Sylius\Component\Grid\Attribute\AsGrid(resourceClass: 'App\Entity\Supplier')]` — the attribute wins over any method, because 1.16 grids may declare no methods at all.
  2. Legacy `public function getResourceClass(): string { return Supplier::class; }` (also the `ResourceAwareGridInterface` contract).
  3. Class/subclass of `Sylius\Bundle\GridBundle\Grid\AbstractGrid`.
- **Error**: `sylius.grid.resourceClassRequired` → `Resource class "%s" not found!`
- **Note**: reads the attribute argument from the AST, not the PHPDoc-resolved value, so it also fires under 1.16 where the class exposes no `getResourceClass()`.

---

### `GridBuilderFieldIsPartOfResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid`
- **Node type**: `CollectedDataNode`
- **Collectors used**:
  - `CollectRessourceClassForGridClass` → `gridClass → resourceClass`
  - `CollectFieldsForGridClass` → `gridClass → [fieldName, line]`
- **Validation**:
  - Non-dotted field names (e.g., `name`) → check property or `get{Name}` method.
  - Dotted field names (e.g., `address.city`) → recursive property traversal:
    1. Check first segment (`address`) exists.
    2. Determine next type via getter return or property type (native → PHPDoc).
    3. Repeat until all segments validated.
- **Special case**: Field name `.` means "whole object passed"; ignored.
- **Errors**:
  - `sylius.grid.resourceClassMissingProperty`: Field missing as property/getter.
  - `sylius.grid.resourceClassPropertyMissingType`: Unable to identify type for recursive path.
- **Identifier note**: Grammar intentionally uses "needs to exists" (not "need to exist") in error messages.

#### Invariant: re-resolve the resource class per field

`$resourceClass` is re-resolved from `$resourceClassName` at the **top of every field iteration**, and
the recursive walk keeps it a `ClassReflection` (`resolveNextClass()` / `toClassReflection()`). Both are load-bearing:

- The recursive branch reassigns `$resourceClass` to the last segment's type. Without the re-resolve, a grid
  with `address.city` followed by any other field validates the rest of the grid against `App\Entity\Address`.
- `Type::hasProperty()` is declared on `PHPStan\Type\Type` and returns **`TrinaryLogic`**. Casting that object
  to bool is *always true*, so a `Type` in that position makes `!$type->hasProperty($x)` always `false` and
  every remaining segment silently passes. This is a silent-pass bug, not a crash — it only shows up as
  missing expected errors.

---

### `GridBuilderFilterIsPartOfResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid`
- **Node type**: `CollectedDataNode`
- **Collectors used**:
  - `CollectRessourceClassForGridClass` → `gridClass → resourceClass`
  - `CollectFilterForGridClass` → `gridClass → [filterFieldList, line]`
- **Validation**:
  - Each filter field (from array) checked against resource class.
  - **No recursion**: Fields containing `.` (e.g., `address.city`) are **skipped** entirely.
- **Error**: `sylius.grid.resourceClassMissingFilter` → `The filter field "%s" needs to exists as property in resource class "%s".`

---

### `IndexOperationNeedsGridClassRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Node type**: `InClassNode`
- **Scope**: Classes implementing `Sylius\Resource\Model\ResourceInterface`.
- **Checks**: `#[Sylius\Resource\Metadata\Index(grid: 'App\Grid\MyGrid')]`
- **Error**: `sylius.resource.gridClassNotFound` → `Grid class "%s" not found!`

---

### `ResourceAttributeNeedsFormTypeRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Node type**: `InClassNode`
- **Scope**: Classes implementing `Sylius\Resource\Model\ResourceInterface`.
- **Checks**: `#[Sylius\Resource\Metadata\AsResource(formType: 'App\Form\MyType')]`
- **Error**: `sylius.resource.formTypeNotFound` → `Form Type "%s" not found!`

---

## Collectors Reference

### `AbstractGridClassCollector`

- **Purpose**: Base helper for detecting grid classes.
- **Key methods**:
  - `scopeIsGrid(Scope $scope): bool`
    - Checks for `#AsGrid` attribute OR subclass of `Sylius\Bundle\GridBundle\Grid\AbstractGrid`.
    - Catches any `Throwable` to avoid crashes.
  - `isSubtypeOf(Type $type, string $superType): bool`
    - Returns `true` if `$type` is a subtype of `$superType`.
- **Usage**: All grid collectors extend this.
- **Note**: `scopeIsGrid()` does not match traits analysed on their own, because the scope class is the
  trait itself. Only fields declared in a class/trait that a real grid uses are collected.

---

### `CollectRessourceClassForGridClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Collector\Grid`
- **Type**: `Collector<MethodReturnStatementsNode, array{string, string, int}>`
- **Returns**: `[gridClass, resourceClass, line]`
- **Logic**:
  1. Check `#AsGrid(resourceClass: ...)` attribute for constant string.
  2. Fallback to `getResourceClass()` method returning `String_` or `ClassConstFetch`.
- **Note**: Typo in class name (`Ressource` with double `s`).

---

### `CollectFieldsForGridClass`

- **Type**: `Collector<StaticCall, array{string, string, int}>`
- **Validates node**:
  - Must be a static **`create()` or `createForService()`** call (`FACTORY_METHODS`).
    `CallableField::createForService()` is the only alternative factory in grid-bundle.
  - Must be inside grid scope (`scopeIsGrid`).
  - Return type must be subtype of `Sylius\Component\Grid\Builder\Field\FieldInterface` (new) **or** `Sylius\Bundle\GridBundle\Builder\Field\FieldInterface` (old).
- **Field resolution**:
  - Iterates `FieldRegistry` entries (ordered by service registration).
  - First `supports()` match wins.
  - Returns only the **first field name** from `getFieldNames()` (even if multiple returned).
- **Special handling**: If field name is `.` (object passed to field), returns `null` (ignored).

---

### `CollectFilterForGridClass`

- **Type**: `Collector<StaticCall, array{string, non-empty-array<string>, -1|int<1, max>}>` (doc declares `-1|positive int` but actual line numbers are positive)
- **Validates node**:
  - Static `create()` call.
  - Grid scope.
  - Return type subtype of `Sylius\Component\Grid\Builder\Filter\FilterInterface` (new) **or** `Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface` (old).
- **Filter resolution**:
  - Iterates `FilterRegistry` entries.
  - First `supports()` match wins.
  - Returns `[gridClass, array of field names, line]`.
- **Key**: Supports array of field names (e.g., `StringFilter::create('name', ['field1', 'field2'])`).

---

## Field Registry & Field Nodes

### Field Registry System

- **Factory**: `FieldRegistryFactory::createRegistry()` uses PHPStan Container to collect all services tagged `phpstan.sylius.grid.field`.
- **Implementation**: `DefaultFieldRegistry` stores an immutable array of `FieldNode[]`.

### Field Nodes

All field nodes implement `FieldNode`:

```php
interface FieldNode {
    public function supports(FullyQualified $nodeClass): bool;
    public function getFieldNames(StaticCall $node): array; // snake_case → camelCase
}
```

#### Built-in Field Nodes

| Class | Sylius Class Name | Argument(s) Processed | Conversion |
|-------|-------------------|-----------------------|------------|
| `StringFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\StringField` | args[0] string | snake → camel |
| `DateTimeFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\DateTimeField` | args[0] string | snake → camel |
| `TwigFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\TwigField` | args[0] string | snake → camel |
| `EnumFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\EnumField` | args[0] string | snake → camel |
| `CallableFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\CallableField` | args[0] string | snake → camel |
| `GenericFieldNode` | `Sylius\Bundle\GridBundle\Builder\Field\Field` | args[0] string | snake → camel |

#### Important Notes

- Matching the **bundle** namespace is correct on *both* grid-bundle 1.15 and 1.16 — see
  [Grid Bundle 1.16](#grid-bundle-116). Do not "fix" these to the `Component` namespace; there is
  nothing there to match.
- The conversion helper `PropertyName::convertSnakeToCamelCase()` lowercases the entire string before shifting, so `My_Field` → `myField`.

---

## Filter Registry & Filter Nodes

### Filter Registry System

- **Factory**: `FilterRegistryFactory::createRegistry()` collects services tagged `phpstan.sylius.grid.filter`.
- **Implementation**: `DefaultFilterRegistry` stores `FilterNode[]`.

### Filter Nodes

All filter nodes implement `FilterNode`:

```php
interface FilterNode {
    public function supports(FullyQualified $nodeClass): bool;
    public function getFilterFields(StaticCall $node): array; // snake_case → camelCase
}
```

#### Built-in Filter Nodes

Signatures below are the real grid-bundle ones; the `create()` first argument is always the filter
name, and every node falls back to it.

| Class | Sylius Class Name | `create()` signature | Field Argument |
|-------|-------------------|----------------------|----------------|
| `EntityFilter` | `...Builder\Filter\EntityFilter` | `create(string $name, string $resourceClass, ?bool $multiple = null, ?array $fields = null)` | args[3] if array of strings; fallback args[0] |
| `EnumFilter` | `...Builder\Filter\EnumFilter` | `create(string $name, string $enumClass, ?bool $multiple = null, ?string $field = null)` | args[3] if string; fallback args[0] |
| `BooleanFilter` | `...Builder\Filter\BooleanFilter` | `create(string $name)` | args[0] |
| `DateFilter` | `...Builder\Filter\DateFilter` | `create(string $name)` | args[0] |
| `MoneyFilter` | `...Builder\Filter\MoneyFilter` | `create(string $name, string $currencyCode, ?int $scale = null)` | args[0] (currency + scale ignored) |
| `ExistsFilter` | `...Builder\Filter\ExistsFilter` | `create(string $name, ?string $field = null)` | args[1] if string; fallback args[0] |
| `SelectFilter` | `...Builder\Filter\SelectFilter` | `create(string $name, array $choices, ?bool $multiple = null, ?string $field = null)` | args[3] if string; fallback args[0] |
| `StringFilter` | `...Builder\Filter\StringFilter` | `create(string $name, ?array $fields = null, $type = null)` | args[1] if array of strings; fallback args[0] |
| `Filter` (catch-all) | matches anything that **implements** `FilterInterface` (old *or* new) | `create(string $name, string $type = null)` | args[0] only |

#### Important Notes

- **Registry order matters: keep the catch-all `Filter` node last.** First `supports()` match wins, so an
  earlier catch-all shadows any node registered after it — in particular a user's custom node. Only the
  catch-all is interface-based; the others match one concrete class name each, so the built-in set of
  outcomes is order-independent.
- The catch-all is the *only* interface-based node, and only `Filter` itself implements `FilterInterface`.
  `StringFilter`, `BooleanFilter` etc. are plain `final class`es with a static `create()` returning the
  interface — they are matched by name, never by the catch-all. Anything without a dedicated node is
  silently unvalidated, which is exactly how `BooleanFilter`/`DateFilter`/`MoneyFilter` went unnoticed.
- Recursive filter fields (e.g., `address.city`) are **skipped** by the rule, not by the collector.

---

## Grid Bundle 1.16

grid-bundle 1.16 moved the *interfaces* into `Sylius\Component\Grid`, but **every concrete
`create()` factory class still lives in `Sylius\Bundle\GridBundle\Builder\...`** and returns the new
interface. In `Sylius\Component\Grid\Builder\Field\` and `...\Filter\` there are only the
`*Interface` files — no concrete field or filter classes exist there at all.

Consequences:
- Name-based nodes must keep matching the **bundle** namespace on both lanes.
- The catch-all `Filter` node must check **both** interfaces, because a legacy `Filter::create()` call
  on 1.16 returns the new `Sylius\Component\Grid\Builder\Filter\FilterInterface`. With only the legacy
  interface it matches nothing on 1.16.
- Fixtures using new-namespace concrete classes would not even parse, so
  `grid_needs_resource_model_native_interface.php` is excluded from PHPStan in `phpstan.dist.neon`
  (it only parses on the `>= 1.16` lane).

---

## Utility

### `PropertyName::convertSnakeToCamelCase`

- **Signature**: `static function convertSnakeToCamelCase(string $string): string`
- **Behavior**:
  - If no `_`, returns input unchanged.
  - Splits on `_`, lowercases each part, makes first part lowercase, rest `ucfirst`.
  - Example: `user_profile_image` → `userProfileImage`.
- **Usage**: All field/filter nodes call this on the first string argument.

---

## Configuration & Extension Points

### `extension.neon`

- **Rules** (registered with PHPStan):
  ```neon
  rules:
    - bitExpert\PHPStan\Sylius\Rule\Grid\ResourceAwareGridNeedsResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilderFieldIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilderFilterIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Resource\IndexOperationNeedsGridClassRule
    - bitExpert\PHPStan\Sylius\Rule\Resource\ResourceAttributeNeedsFormTypeRule
  ```
- **Services**:
  - `FieldRegistryFactory` → `syliusFieldTypeRegistry` via `createRegistry()`.
  - `FilterRegistryFactory` → `syliusFilterTypeRegistry`.
  - Collectors (`CollectRessourceClassForGridClass`, `CollectFieldsForGridClass`, `CollectFilterForGridClass`) tagged `phpstan.collector`.
  - All field/filter nodes tagged with `phpstan.sylius.grid.field` / `phpstan.sylius.grid.filter`.
  - Filter nodes are registered specific-first; the catch-all `Filter` node is deliberately last.

### Custom Field/Filter Types

To add custom types:

1. Implement `FieldNode` or `FilterNode`.
2. Register service in `phpstan.neon` with the appropriate tag.
3. Register it **before** the catch-all `Filter` node if you are also adding an interface-based node.

Example (custom filter node):
```neon
services:
  - class: App\PHPStan\CustomFilterNode
    tags:
      - phpstan.sylius.grid.filter
```

`supports()` should match on the concrete class name (`$nodeClass->name`) like the built-in nodes do.
Prefer that over an interface check: interface-based nodes compete with the catch-all.

---

## Testing

### Test Infrastructure

- **Framework**: PHPUnit 11.
- **Configuration**: `phpunit.xml.dist` (suffix `UnitTest.php`, bootstrap `tests/bootstrap.php`).
- **Rules** use `PHPStan\Testing\RuleTestCase`.
- **Utility/Registry** use standard `PHPUnit\Framework\TestCase`.
- 17 tests total across both lanes.

#### Gotchas that make tests lie

- **`RuleTestCase` asserts only `line: message`, never the file.** A wrong `->file()` in a rule cannot be
  caught by these tests. Do not add a test that appears to cover file attribution.
- **`phpunit.xml.dist` sets `stopOnFailure="true"`.** A failing run reports only the tests up to the first
  failure, so a low test count is not a discovery problem — confirm with `--list-tests`.
- **Test registries are hand-built.** Each grid rule test overrides `getCollectors()` and assembles its own
  node list. Registering a node in `extension.neon` alone changes nothing in the tests, so a green suite can
  pass while the new node is never exercised. Update both, and prove the node is load-bearing by pointing
  one `FILTER_TYPE`/`FIELD_TYPE` at a wrong class name and confirming the expectation disappears.
- **Do not bulk-shift fixture line numbers with sequential `str.replace`.** Replacing `46→49` and then
  `49→52` re-edits the value just written and shifts the wrong entries. Re-derive line numbers from the file.

### Test Files

| Test File | Purpose | Fixtures |
|-----------|---------|----------|
| `PropertyNameUnitTest` | Unit tests for snake → camel conversion | None |
| `ResourceAttributeNeedsFormTypeUnitTest` | Validates `AsResource` form type existence | `Rule/Resource/data/entity.php`, `entity_non_constant_attribute.php` |
| `IndexOperationNeedsGridClassUnitTest` | Validates `Index` grid reference | `Rule/Resource/data/entity_index.php` |
| `ResourceAwareGridNeedsResourceClassUnitTest` | Missing resource class (old, attribute, 1.16-native, method-less) | `grid_needs_resource_model.php`, `grid_needs_resource_model_attr.php`, `grid_needs_resource_model_native_interface.php`, `grid_needs_resource_model_no_methods.php` |
| `ResourceAwareGridNeedsResourceClassValidUnitTest` | Validates correct configurations | `grid_valid.php`, `grid_valid_attr.php` |
| `GridBuilderFieldIsPartOfResourceClassUnitTest` | Invalid field detection (incl. `createForService`) | `grid.php` |
| `GridBuilderFieldIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `GridBuilderFilterIsPartOfResourceClassUnitTest` | Invalid filter detection | `grid.php` |
| `GridBuilderFilterIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `DefaultFilterRegistryUnitTest` | Registry functionality | None |

### Fixtures

All fixtures (for analysis) reside under `tests/bitExpert/PHPStan/Sylius/Rule/*/data/`.

`Rule/Grid/data/` (namespace `App\Entity` / `App\Grid`):
- `entity.php`: `App\Entity\Status` (enum), `Country`, `Address`, `Supplier` (implements `ResourceInterface`, missing `name`). `Country` and `Address::$country`/`getCountry()` back the three-segment field case.
- `grid.php`: `AdminSupplierGrid` with invalid field/filter definitions, plus `SomeOtherClass`. Shared by the field *and* filter tests, so any edit shifts expectations in both.
- `grid_valid.php`, `grid_valid_attr.php`: correct grid configurations.
- `grid_needs_resource_model.php` / `_attr.php` / `_no_methods.php` / `_native_interface.php`: resource-class detection cases.

`Rule/Resource/data/` (namespace `App\Entity`):
- `entity.php`: declares a class literally named `entity` (lowercase) implementing `ResourceInterface`.
- `entity_index.php`: missing `Index(grid:)`.
- `entity_non_constant_attribute.php`: non-constant attribute argument.

These files are loaded via `composer.json` `autoload-dev.files` so classes exist during analysis, and
`Rule/Grid/data/grid.php` + `entity.php` are excluded from PHPStan analysis in `phpstan.dist.neon`.

---

## Development Commands

From `composer.json`:

| Command | Description |
|---------|-------------|
| `composer cs` | Check coding standards (dry-run) |
| `composer cs-fix` | Fix coding standards |
| `composer check-license` | Verify license compliance |
| `composer static-analysis` | Run PHPStan (uses `phpstan.dist.neon`) |
| `composer test` | Run PHPUnit tests |

### CI Pipeline (`.github/workflows/ci.yml`)

Matrix over PHP version × OS × grid-bundle constraint. Each lane runs:

1. Checkout repo
2. Configure PHP (`shivammathur/setup-php`)
3. `composer require --dev "sylius/grid-bundle:<constraint>" --no-update` + `composer update`
4. Show resolved versions — **one `composer show <pkg>` call per package**; `composer show` takes a single
   package plus an optional version, so passing several names exits `1`
5. `composer check-license`
6. `composer cs`
7. `composer static-analysis`
8. `composer test`

The two grid-bundle lanes are `^1.15 <1.16` and `^1.16`. Keep both green; a change that only passes the
second lane is not done.

### Coding Standards

- **Tool**: PHP-CS-Fixer
- **Config**: `.php-cs-fixer.dist.php`
- **Rules**: `@Symfony`, `@Symfony:risky`, strict comparison/param, declare strict, ordered imports, multi-line extends, trailing commas, etc.
- **Excludes**: `vendor/`

---

## Known Issues & Gotchas

### 1. Typo in Collector Name
- **File**: `CollectRessourceClassForGridClass.php` (`Ressource` double `s`).
- **Impact**: Must be preserved for BC; do not rename.

### 2. A redundant `instanceof` guard can hide a wrong `@implements`
- `processNode()` must declare `Node $node` because `Rule::processNode()` does, so the generic in
  `@implements` is the only thing that narrows `$node` inside the method body.
- Both grid rules used to open with `if (!$node instanceof CollectedDataNode) { return []; }`. That
  re-narrowing is exactly what **suppressed** their wrong `@implements Rule<StaticCall>` docblocks from
  ever reaching PHPStan. Deleting the guard without fixing the docblock turns
  `$node->get()` into `Call to an undefined method PhpParser\Node::get()`.
- **Action**: when adding a rule, keep `@implements` and `getNodeType()` in agreement, and skip the
  defensive `instanceof` guard — it can mask a real annotation mistake instead of catching one.

### 3. Node/collector coverage is opt-in
- A field or filter class with **no** matching node is silently never validated — no error, no warning.
  The catch-all only covers classes that implement `FilterInterface`, and only `Filter` itself does.
- **Impact**: `BooleanFilter`, `DateFilter` and `MoneyFilter` were unvalidated until nodes were added for
  them. When a new grid-bundle factory appears, add a node in the same change, and check the grid-bundle
  changelog when bumping the floor.

### 4. Recursive Filter Fields Skipped
- The rule `GridBuilderFilterIsPartOfResourceClass` skips any filter field containing `.` (e.g., `address.city`).
- **Rationale**: Filters typically do not need recursive access, but this may be overly restrictive.
- **Consequence**: Users cannot validate recursive filter fields.

### 5. Field errors are attributed to the grid's file, not the node's
- Both grid rules use `$gridFilesMap[$gridClass]`, which is **last-write-wins per grid class** and shared
  by all fields of that grid.
- **Impact**: A grid split across multiple files reports every field error against whichever file was seen
  last. Fields inside a trait are always attributed to the using class, because the node's real file is not
  recoverable: PHP-Parser 5 exposes only an integer `startFilePos` (no path), and `Scope::getFile()`,
  `getFunction()->getFileName()` and `->getDeclaringClass()->getFileName()` all return the using class file.
- **Action**: not fixable without a broader file-identity design; `RuleTestCase` cannot even assert it.

### 6. Lowercase fixture class name
- `tests/bitExpert/PHPStan/Sylius/Rule/Resource/data/entity.php` declares a class named `entity` (lowercase)
  in namespace `App\Entity`.
- **Impact**: Avoid confusion; stick to PSR-1 naming in new fixtures. (The `Rule/Grid/data/entity.php`
  fixture is fine — it declares `Status`, `Country`, `Address`, `Supplier`.)

---

## Extending the Extension

### Adding a New Rule

1. Create rule class in `src/bitExpert/PHPStan/Sylius/Rule/...`.
2. Implement `PHPStan\Rules\Rule<NodeType>` — make `@implements` and `getNodeType()` agree.
3. Register in `extension.neon` under `rules`.
4. Add test in `tests/.../Rule/.../...UnitTest.php`.
5. Add fixture if needed.

### Adding a New Collector

1. Implement `PHPStan\Collectors\Collector<NodeType, mixed>`.
2. Extend `AbstractGridClassCollector` for grid-related logic.
3. Register in `extension.neon` with `phpstan.collector` tag.
4. Use in rule(s) by requesting collected data via `CollectedDataNode`.

### Adding a New Field/Filter Node

1. Implement `FieldNode` or `FilterNode`.
2. Tag service in `extension.neon` with `phpstan.sylius.grid.field` or `phpstan.sylius.grid.filter`.
3. Ensure `supports()` correctly checks the **concrete** class name.
4. Register before the catch-all `Filter` node.
5. Add the node to the affected tests' `getCollectors()` too, then prove it is load-bearing with a
   deliberately wrong class name.

---

## References

- **PHPStan Docs**: [https://phpstan.org](https://phpstan.org)
- **Sylius Resource Bundle**: [https://github.com/Sylius/ResourceBundle](https://github.com/Sylius/ResourceBundle)
- **Sylius Grid Bundle**: [https://github.com/Sylius/GridBundle](https://github.com/Sylius/GridBundle)

---

*Last updated: 2026-09-26*
