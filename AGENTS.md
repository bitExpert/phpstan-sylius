# AGENTS.md – PHPStan Sylius Extension

This file provides structured context for AI agents (e.g., copilot, Claude, etc.) working on the `bitexpert/phpstan-sylius` codebase. It documents architecture, conventions, and key implementation details to enable rapid, accurate contributions.

---

## Overview

- **Package**: `bitexpert/phpstan-sylius`
- **Type**: PHPStan extension (type: `phpstan-extension` in `composer.json`)
- **Purpose**: Additional static analysis rules for Sylius projects, validating grid configurations and resource metadata.
- **Requirements**: PHP ^8.2, PHPStan ^2.1, Sylius ^2.0 (resource-bundle ^1.12, grid-bundle ^1.13).
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
   │  │  └─ *Filter.php                              # EntityFilter, EnumFilter, ExistsFilter, Filter, SelectFilter, StringFilter
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

### Rule Execution Flow

| Rule | Node Type | Key Logic |
|------|-----------|-----------|
| `ResourceAwareGridNeedsResourceClass` | `MethodReturnStatementsNode` | Checks `getResourceClass()` or `#AsGrid(resourceClass:)` for existing class. |
| `GridBuilderFieldIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid field exists as a property/getter on the resource class. Supports recursive fields (`address.city`). |
| `GridBuilderFilterIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid filter field exists on resource. Does **not** support recursive checks (dots are skipped). |
| `IndexOperationNeedsGridClassRule` | `InClassNode` | Validates `#[Index(grid: '...')]` refers to an existing grid class. |
| `ResourceAttributeNeedsFormTypeRule` | `InClassNode` | Validates `#[AsResource(formType: '...')]` refers to an existing form type. |

---

## Rules Reference

### `ResourceAwareGridNeedsResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid`
- **Node type**: `MethodReturnStatementsNode`
- **Scope**: Only subclasses of `Sylius\Bundle\GridBundle\Grid\AbstractGrid` or classes with `#AsGrid` attribute.
- **Checks**:
  - **New API**: `#[Sylius\Component\Grid\Attribute\AsGrid(resourceClass: 'App\Entity\Supplier')]`
  - **Old API**: `public function getResourceClass(): string { return Supplier::class; }`
- **Error**: `sylius.grid.resourceClassRequired` → `Resource class "%s" not found!`

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
  - Must be a static `create()` call.
  - Must be inside grid scope (`scopeIsGrid`).
  - Return type must be subtype of `Sylius\Component\Grid\Builder\Field\FieldInterface` (new) **or** `Sylius\Bundle\GridBundle\Builder\Field\FieldInterface` (old).
- **Field resolution**:
  - Iterates `FieldRegistry` entries (ordered by service registration).
  - First `supports()` match wins.
  - Returns only the **first field name** from `getFieldNames()` (even if multiple returned).
- **Special handling**: If field name is `.` (object passed to field), returns `null` (ignored).

---

### `CollectFilterForGridClass`

- **Type**: `Collector<StaticCall, array{string, non-empty-array<string>, int<1, max>}>` (doc declares `-1|positive int` but actual line numbers are positive)
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

- **All nodes only support the old `Sylius\Bundle\GridBundle` namespace**, even though collectors accept both old and new (`Sylius\Component\Grid`) interfaces.
- If a project uses the new `Sylius\Component\Grid\Builder\Field\Field` classes, they will **not** be recognized by any field node unless custom nodes are registered.
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

| Class | Sylius Class Name | Argument Logic | Notes |
|-------|-------------------|----------------|-------|
| `EntityFilter` | `Sylius\Bundle\GridBundle\Builder\Filter\EntityFilter` | args[3] if array of strings; fallback to args[0] | Signature likely `create(string, string, bool, ?array)` |
| `EnumFilter` | `Sylius\Bundle\GridBundle\Builder\Filter\EnumFilter` | args[3] if string; fallback to args[0] | Signature likely `create(string, string, bool, ?string)` |
| `ExistsFilter` | `Sylius\Bundle\GridBundle\Builder\Filter\ExistsFilter` | args[1] if string; fallback to args[0] | Signature likely `create(string, string)` |
| `Filter` | `Sylius\Bundle\GridBundle\Builder\Filter\FilterInterface` | args[0] only | **Bug?**: `isSuperTypeOf` check appears inverted (should be `$filterType->isSuperTypeOf($nodeClassType)`). Practically only supports direct interface calls. |
| `SelectFilter` | `Sylius\Bundle\GridBundle\Builder\Filter\SelectFilter` | args[3] if string; fallback to args[0] | |
| `StringFilter` | `Sylius\Bundle\GridBundle\Builder\Filter\StringFilter` | args[1] if array of strings; fallback to args[0] | Signature likely `create(string, array|callable)` |

#### Important Notes

- Same limitation as field nodes: **only old bundle namespace** recognized.
- `Filter` class likely broken: its `supports()` logic is reversed, making it effectively unused.
- Recursive filter fields (e.g., `address.city`) are **skipped** by the rule, not by the collector.

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

### Custom Field/Filter Types

To add custom types:

1. Implement `FieldNode` or `FilterNode`.
2. Register service in `phpstan.neon` with the appropriate tag.

Example (custom field node):
```neon
services:
  - class: App\PHPStan\CustomFieldNode
    tags:
      - phpstan.sylius.grid.field
```

---

## Testing

### Test Infrastructure

- **Framework**: PHPUnit 11.
- **Configuration**: `phpunit.xml.dist` (suffix `UnitTest.php`, bootstrap `tests/bootstrap.php`).
- **Rules** use `PHPStan\Testing\RuleTestCase`.
- **Utility/Registry** use standard `PHPUnit\Framework\TestCase`.

### Test Files

| Test File | Purpose | Fixtures |
|-----------|---------|----------|
| `PropertyNameUnitTest` | Unit tests for snake → camel conversion | None |
| `ResourceAttributeNeedsFormTypeUnitTest` | Validates `AsResource` form type existence | `tests/bitExpert/PHPStan/Sylius/Rule/Resource/data/entity.php` |
| `ResourceAwareGridNeedsResourceClassUnitTest` | Tests old + new grid API with missing resource class | `grid_needs_resource_model.php`, `grid_needs_resource_model_attr.php` |
| `ResourceAwareGridNeedsResourceClassValidUnitTest` | Validates correct configurations | `grid_valid.php`, `grid_valid_attr.php` |
| `GridBuilderFieldIsPartOfResourceClassUnitTest` | Tests invalid field detection | `grid.php` |
| `GridBuilderFieldIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `GridBuilderFilterIsPartOfResourceClassUnitTest` | Tests invalid filter detection | `grid.php` |
| `GridBuilderFilterIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `DefaultFilterRegistryUnitTest` | Registry functionality | None |

### Fixtures

All fixtures (for analysis) reside under `tests/bitExpert/PHPStan/Sylius/Rule/*/data/`:
- `entity.php` (Resource): `App\Entity\Status` enum, `Address`, `Supplier` (implements `ResourceInterface`, missing `name` property).
- `grid.php` (Grid): `AdminSupplierGrid` with invalid field/filter definitions.
- `grid_valid.php`: Correct grid configuration.
- `grid_valid_attr.php`: Correct `#AsGrid` usage.
- `grid_needs_resource_model.php`: Old API with one missing class.
- `grid_needs_resource_model_attr.php`: `#AsGrid` with one missing class.

These files are loaded via `composer.json` `autoload-dev.files` so classes exist during analysis.

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

1. Checkout repo
2. Setup PHP 8.2
3. Install dependencies
4. License check
5. Coding standards
6. Static analysis
7. Unit tests

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

### 2. Stale PHPStan Ignore
- `phpstan.dist.neon` ignores errors in `AbstractGridBuilderRule.php`, but this file **does not exist** in the codebase.
- **Action**: Can likely be removed.

### 3. Field/Filter Support Limitation
- **All built-in nodes only support `Sylius\Bundle\GridBundle\*` namespace**, even though collectors accept both old and new (`Sylius\Component\Grid`) interfaces.
- **Consequence**: Projects using the new component interfaces must register custom nodes.
- **Recommendation**: Update field/filter nodes to support both namespaces.

### 4. `Filter` Node Bug
- `Filter::supports()` logic uses `$nodeClassType->isSuperTypeOf($filterType)` when it should be the reverse.
- **Consequence**: The generic `Filter` node likely never matches any concrete filter class.
- **Impact**: May cause some custom filters to be silently skipped.

### 5. Recursive Filter Fields Skipped
- The rule `GridBuilderFilterIsPartOfResourceClass` skips any filter field containing `.` (e.g., `address.city`).
- **Rationale**: Filters typically do not need recursive access, but this may be overly restrictive.
- **Consequence**: Users cannot validate recursive filter fields.

### 6. Case Sensitivity in Fixtures
- Test fixtures use non-standard naming: `tests/bitExpert/PHPStan/Sylius/Rule/Grid/data/entity.php` defines class `App\Entity\entity` (lowercase `entity`).
- **Impact**: Avoid confusion; stick to PSR-1 naming in new code.

---

## Extending the Extension

### Adding a New Rule

1. Create rule class in `src/bitExpert/PHPStan/Sylius/Rule/...`.
2. Implement `PHPStan\Rules\Rule<NodeType>`.
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
3. Ensure `supports()` correctly checks class name.
4. Test with appropriate fixtures.

---

## References

- **PHPStan Docs**: [https://phpstan.org](https://phpstan.org)
- **Sylius Resource Bundle**: [https://github.com/Sylius/ResourceBundle](https://github.com/Sylius/ResourceBundle)
- **Sylius Grid Bundle**: [https://github.com/Sylius/GridBundle](https://github.com/Sylius/GridBundle)

---

*Last updated: 2026-09-26*
