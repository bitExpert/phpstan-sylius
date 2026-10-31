# AGENTS.md – PHPStan Sylius Extension

This file provides structured context for AI agents (e.g., copilot, Claude, etc.) working on the `bitexpert/phpstan-sylius` codebase. It documents architecture, conventions, and key implementation details to enable rapid, accurate contributions.

---

## Overview

- **Package**: `bitexpert/phpstan-sylius`
- **Type**: PHPStan extension (type: `phpstan-extension` in `composer.json`)
- **Purpose**: Additional static analysis rules for Sylius projects, validating grid configurations and resource metadata.
- **Requirements**: PHP ^8.2, PHPStan ^2.2. Dev: resource-bundle ^1.12, grid-bundle **^1.15 | ^1.16**.
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
   │  │  ├─ AbstractGridClassCollector.php           # Base: grid + grid mutator detection logic
   │  │  ├─ AbstractGridBuilderCallCollector.php     # Base: shared StaticCall processing for field/filter factories
   │  │  ├─ AbstractGridBuilderFieldCollector.php    # Base: field factory collection (grid or mutator scope)
   │  │  ├─ AbstractGridBuilderFilterCollector.php   # Base: filter factory collection (grid or mutator scope)
   │  │  ├─ CollectRessourceClassForGridClass.php    # Collector: grid ↔ resource mapping
   │  │  ├─ CollectGridNameForGridClass.php          # Collector: grid class → grid name (AsGrid name:, getName(), class name)
   │  │  │
   │  │  ├─ GridBuilder/
   │  │  │  ├─ CollectFields.php                     # Collector: grid field definitions
   │  │  │  └─ CollectFilter.php                     # Collector: grid filter definitions
   │  │  │
   │  │  └─ GridMutator/
   │  │     ├─ CollectFields.php                     # Collector: mutator field definitions
   │  │     ├─ CollectFilter.php                     # Collector: mutator filter definitions
   │  │     └─ CollectGridNames.php                  # Collector: mutator class → grid names (#[AsGridMutator], repeatable)
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
   │     │  ├─ AbstractGridBuilderResourceRule.php      # Base: shared field/filter-vs-resource validation
   │     │  ├─ ResourceAwareGridNeedsResourceClass.php   # Rule: check grid resource class exists
   │     │  │
   │     │  ├─ GridBuilder/
   │     │  │  ├─ FieldIsPartOfResourceClass.php         # Rule: grid fields must match resource
   │     │  │  └─ FilterIsPartOfResourceClass.php         # Rule: grid filters must match resource
   │     │  │
   │     │  └─ GridMutator/
   │     │     ├─ FieldIsPartOfResourceClass.php         # Rule: mutator fields must match mutated grid's resource
   │     │     └─ FilterIsPartOfResourceClass.php         # Rule: mutator filters must match mutated grid's resource
   │     │
   │     └─ Resource/
   │        ├─ AbstractResourceOperationRule.php    # Base: operation-attribute walking + class/callable reference validation
   │        ├─ IndexOperationNeedsGridClassRule.php      # Rule: Index metadata grid exists
   │        ├─ ResourceAttributeNeedsFormTypeRule.php    # Rule: AsResource form type exists
   │        ├─ CreateOperationNeedsFactoryRule.php       # Rule: Create operation factory class/method exists
   │        ├─ OperationNeedsValidProviderRule.php       # Rule: operation provider class/method exists
   │        └─ OperationNeedsValidProcessorRule.php      # Rule: operation processor class/method exists
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

> **Note**: `getNodeType()` is authoritative and must agree with the `@implements` generic. All four
> grid builder/mutator rules return `CollectedDataNode::class`. The `processNode()` parameter stays typed as `Node` because
> that is what `Rule::processNode()` declares, and narrowing a parameter is a contravariance
> violation. Do **not** add a defensive `instanceof` guard to compensate — see
> [Known Issue 2](#2-a-redundant-instanceof-guard-can-hide-a-wrong-implements).

### Rule Execution Flow

| Rule | Node Type | Key Logic |
|------|-----------|-----------|
| `ResourceAwareGridNeedsResourceClass` | `InClassNode` | Checks `#AsGrid(resourceClass:)` first, then `getResourceClass()` / `ResourceAwareGridInterface`, for an existing class. |
| `GridBuilder\FieldIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid field exists as a property/getter on the resource class. Supports recursive fields (`address.city`). |
| `GridBuilder\FilterIsPartOfResourceClass` | `CollectedDataNode` | Validates every grid filter field exists on resource. Does **not** support recursive checks (dots are skipped). |
| `GridMutator\FieldIsPartOfResourceClass` | `CollectedDataNode` | Same as the grid field rule, but for fields a grid mutator (`#[AsGridMutator]`) adds to the builder. Mutator → grid name → grid class → resource class; grid names are treated as unique. Supports recursive fields. |
| `GridMutator\FilterIsPartOfResourceClass` | `CollectedDataNode` | Same as the grid filter rule, but for filters a grid mutator adds. Dots are skipped. |
| `IndexOperationNeedsGridClassRule` | `InClassNode` | Validates `#[Index(grid: '...')]` refers to an existing grid class. |
| `ResourceAttributeNeedsFormTypeRule` | `InClassNode` | Validates `#[AsResource(formType: '...')]` refers to an existing form type. |
| `CreateOperationNeedsFactoryRule` | `InClassNode` | Validates the `factory`/`factoryMethod` of a `Create` operation (class exists, method exists, `createNew` default, callable must be static). |
| `OperationNeedsValidProviderRule` | `InClassNode` | Validates the `provider:` argument of every operation attribute (class exists; callable `[Class::class, 'method']` needs an existing static method). |
| `OperationNeedsValidProcessorRule` | `InClassNode` | Validates the `processor:` argument of every operation attribute (class exists; callable `[Class::class, 'method']` needs an existing static method). |

### Grid mutators

Since grid-bundle 1.16 a grid can be mutated by classes tagged `#[AsGridMutator(grid: '<grid name>')]` implementing
`Sylius\Component\Grid\Mutator\GridMutatorInterface`. The mutator receives a `GridBuilderInterface` and calls the
same `addField()`/`addFilter()` factories as a grid. The attribute carries only the grid **name**, so the rules join
`mutator class → grid name (GridMutator\CollectGridNames) → grid class (CollectGridNameForGridClass) → resource class
(CollectRessourceClassForGridClass, or the AsGrid attribute via CollectGridNameForGridClass, because a 1.16 grid may
declare nothing but the attribute)`. Grid names are assumed unique; the grid name is derived the way grid-bundle
derives it — `#[AsGrid(name: '...')]` wins, then a constant-string `getName()` return, then the grid class name
(the `InvokableGridPass` default and the convention mutators rely on). A grid with a *computed* custom name is not
joined: its mutators' fields stay unvalidated, never falsely reported.
The field/filter collectors share their logic with the grid ones via `AbstractGridBuilderCallCollector` +
`AbstractGridBuilderFieldCollector`/`AbstractGridBuilderFilterCollector`; only the scope predicate differs
(`scopeIsGrid()` vs `scopeIsGridMutator()`). The rules share `AbstractGridBuilderResourceRule`, which owns the
resource-map resolution (`resolveGridResourceMap` / `resolveMutatorResourceMap`), the error attribution and the
recursive dotted-path walk; concrete rules only pick the collector and the validation flavor.

`AsGridMutator` is **repeatable**: one mutator class carrying the attribute twice mutates both grids, mirroring the
`GridMutatorPass`, which registers the mutator once per attribute. The mutator collector therefore returns a list of
grid names and the mutator maps to the list of all those grids' resource classes; every field/filter is validated
against each of them. A mutator for an unknown grid name is silently not validated (no resource class to join).
The interface fallback in `scopeIsGridMutator()` is inert on its own: fields of a class that merely implements
`GridMutatorInterface` are collected, but without the attribute the class never enters the mutator→resource map,
so nothing is validated — matching runtime, where the attribute is what registers a mutator.

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

### `GridBuilder\FieldIsPartOfResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder`
- **Node type**: `CollectedDataNode`
- **Collectors used**:
  - `CollectRessourceClassForGridClass` → `gridClass → resourceClass`
  - `GridBuilder\CollectFields` → `gridClass → [fieldName, line]`
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

### `GridBuilder\FilterIsPartOfResourceClass`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder`
- **Node type**: `CollectedDataNode`
- **Collectors used**:
  - `CollectRessourceClassForGridClass` → `gridClass → resourceClass`
  - `GridBuilder\CollectFilter` → `gridClass → [filterFieldList, line]`
- **Validation**:
  - Each filter field (from array) checked against resource class.
  - **No recursion**: Fields containing `.` (e.g., `address.city`) are **skipped** entirely.
- **Error**: `sylius.grid.resourceClassMissingFilter` → `The filter field "%s" needs to exists as property in resource class "%s".`

---

### Mutator rule identifiers

The mutator rules mirror the grid ones but emit their own identifiers, so users can ignore or baseline
them separately:

| Rule | Missing | Missing type (recursive) |
|------|---------|---------------------------|
| `GridMutator\FieldIsPartOfResourceClass` | `sylius.grid.mutatorResourceClassMissingProperty` | `sylius.grid.mutatorResourceClassPropertyMissingType` |
| `GridMutator\FilterIsPartOfResourceClass` | `sylius.grid.mutatorResourceClassMissingFilter` | — |

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

### `CreateOperationNeedsFactoryRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Node type**: `InClassNode`
- **Scope**: Classes implementing `Sylius\Resource\Model\ResourceInterface`.
- **Checks**: `#[Sylius\Resource\Metadata\Create]` attributes, both declared directly on the class and
  nested as `new Create(...)` inside `#[AsResource(operations: [...])]`.
- **Behaviour**:
  - `factory: BookFactory::class` (or a constant string class name) → the class must exist.
  - `factoryMethod: 'createForAuthor'` → the method must exist on the factory class (instance
    method, matching `$factoryInstance->$factoryMethod(...)` in `State\Factory`).
  - `factory` without `factoryMethod` → the ResourceBundle defaults the method to `createNew`
    (`FactoryResourceMetadataCollectionFactory::addDefaults()`), so `createNew` must exist.
  - `factory: [BookFactory::class, 'create']` (callable form) → the class must exist, the method
    must exist **and be static**, because the runtime invokes the callable statically
    (`is_callable(['NonStaticClass', 'method'])` is false for instance methods).
  - `factory: false` disables the factory → skipped. `factory: null`/`true` → skipped.
  - Non-constant arguments (variables, `new stdClass()`, method calls) are skipped silently; the
    analysis is never aborted for them.
  - Positional arguments are not mapped: the `Create` constructor declares more than twenty
    parameters before `factory`, so only named arguments are read.
  - A `factoryMethod` that is present but not a constant string skips the method check entirely
    (the runtime would call the expression's value, not the `createNew` default).
- **Errors**:
  - `sylius.resource.factoryClassNotFound` → `Factory class "%s" not found!`
  - `sylius.resource.factoryMethodNotFound` → `Factory method "%s" not found on factory class "%s"!`
  - `sylius.resource.factoryMethodNotStatic` → `Factory method "%s" on factory class "%s" has to be static when the factory is passed as callable!`
- **Note**: reads the AST instead of `AttributeReflection`, because nested `new Create(...)`
  expressions inside attribute arguments are not reachable through the attribute reflection API.
  PHPStan's parser resolves all class names to their fully qualified form, so a plain string
  comparison identifies the attributes.

---

### `AbstractResourceOperationRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Type**: abstract base class, not registered as a rule itself
- **Purpose**: shared skeleton for the rules that validate a reference configured on a resource
  operation attribute — currently the `provider` and `processor` arguments, plus the `factory` /
  `factoryMethod` pair of the factory rule. It owns:
  - the `InClassNode` handling and the `ResourceInterface` scope check,
  - the operation-attribute collection: attributes declared directly on the class **and** nested
    `new Show(...)` expressions inside `#[AsResource(operations: [...])]`, found recursively in
    arrays (a `New_` expression is shaped like an `Attribute`, so it is converted into one),
  - named-argument extraction (positional arguments are never mapped: the operation constructors
    declare many parameters before `provider`/`processor`, in differing positions),
  - the class-or-callable validation: class must exist; callable `[Class::class, 'method']` needs an
    existing **static** method; a string naming an existing plain function is a valid callable and is
    skipped; `null`/`false`/non-constant arguments are skipped silently.
- **Subclass hooks**: `isTargetAttribute(string $attributeName): bool` picks the attribute classes a
  rule cares about (Operation-subclass check or a hardcoded name), `validateAttribute(Attribute, Scope)`
  implements the argument extraction and validation flavor.
- **Error identifiers**: derived from a noun (`Provider`, `Processor`, `Factory`), so every rule
  emits its own `sylius.resource.<noun>ClassNotFound` / `<noun>MethodNotFound` / `<noun>MethodNotStatic`
  identifiers and the errors can be ignored or baselined separately.
- **Note**: keeps `@implements Rule<InClassNode>` and `getNodeType()`'s
  `@return class-string<InClassNode>` in agreement; subclasses do not re-add a defensive
  `instanceof` guard (see Known Issue 2).

---

### `OperationNeedsValidProviderRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Node type**: `InClassNode`
- **Scope**: Classes implementing `Sylius\Resource\Model\ResourceInterface`.
- **Checks**: the named `provider:` argument of **every** operation attribute of the class — declared
  directly on the class and nested as `new Show(...)` etc. inside `#[AsResource(operations: [...])]`.
  Every attribute class extending `Sylius\Resource\Metadata\Operation` counts as an operation
  (mirroring the `is_subclass_of()` check in `AttributesResourceMetadataCollectionFactory`), which
  keeps the `Api\*` operations **and** custom operation classes covered; the check is not a hardcoded
  name list.
- **Behaviour**:
  - `provider: BookProvider::class` (or a constant string class name) → the class must exist; the
    runtime looks it up in the `sylius.state_provider` tagged locator (`State\Provider::provide()`).
  - `provider: [BookProvider::class, 'provide']` (callable pair) → the class must exist, the method
    must exist **and be static**, because the runtime invokes the callable as a plain static
    callable (`is_callable(['NonStaticClass', 'method'])` is false for instance methods).
  - `provider: 'provider_function'` where `provider_function` is an existing plain function →
    valid, skipped: the runtime checks `is_callable()` before the locator lookup.
  - `provider: null` (or absent) → skipped; the ResourceBundle defaults the provider
    (`ProviderResourceMetadataCollectionFactory`).
  - Non-constant arguments (variables, `new stdClass()`, method calls) are skipped silently; the
    analysis is never aborted for them.
  - Only the named `provider` argument is read: every operation constructor declares many
    parameters before `provider` and their positions differ between the operation classes.
- **Errors**:
  - `sylius.resource.providerClassNotFound` → `Provider class "%s" not found!`
  - `sylius.resource.providerMethodNotFound` → `Provider method "%s" not found on provider class "%s"!`
  - `sylius.resource.providerMethodNotStatic` → `Provider method "%s" on provider class "%s" has to be static when the provider is passed as callable!`
- **Note**: reads the AST instead of `AttributeReflection`, because nested operation `new`
  expressions inside attribute arguments are not reachable through the attribute reflection API.
  Attribute/new-expression class names are resolved through the scope (`resolveName`), honouring
  namespace and `use` statements.

---

### `OperationNeedsValidProcessorRule`

- **Namespace**: `bitExpert\PHPStan\Sylius\Rule\Resource`
- **Node type**: `InClassNode`
- **Scope**: Classes implementing `Sylius\Resource\Model\ResourceInterface`.
- **Checks**: the named `processor:` argument of **every** operation attribute of the class — the
  mirror of the provider rule, sharing `AbstractResourceOperationRule` for all collection and
  validation logic.
- **Behaviour** (mirrors `Sylius\Resource\State\Processor::process()`):
  - `processor: BookProcessor::class` (or a constant string class name) → the class must exist; the
    runtime looks it up in the `sylius.state_processor` tagged locator and asserts it implements
    `ProcessorInterface` at runtime (`State\Processor::process()`).
  - `processor: [BookProcessor::class, 'process']` (callable pair) → the class must exist, the method
    must exist **and be static**.
  - `processor: 'processor_function'` where `processor_function` is an existing plain function →
    valid, skipped.
  - `processor: null` (or absent) → skipped; the ResourceBundle defaults the processor
    (`ProcessorResourceMetadataCollectionFactory`).
  - Non-constant arguments are skipped silently.
- **Errors**:
  - `sylius.resource.processorClassNotFound` → `Processor class "%s" not found!`
  - `sylius.resource.processorMethodNotFound` → `Processor method "%s" not found on processor class "%s"!`
  - `sylius.resource.processorMethodNotStatic` → `Processor method "%s" on processor class "%s" has to be static when the processor is passed as callable!`

---

## Collectors Reference

### `AbstractGridClassCollector`

- **Purpose**: Base helper for detecting grid classes.
- **Key methods**:
  - `scopeIsGrid(Scope $scope): bool`
    - Checks for `#AsGrid` attribute OR subclass of `Sylius\Bundle\GridBundle\Grid\AbstractGrid`.
    - Catches any `Throwable` to avoid crashes.
  - `scopeIsGridMutator(Scope $scope): bool`
    - Checks for `#AsGridMutator` attribute, falling back to implementations of
      `Sylius\Component\Grid\Mutator\GridMutatorInterface`. Only parseable on grid-bundle >= 1.16;
      the attribute check is what registers a mutator, the interface fallback alone is inert.
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

### `GridBuilder\CollectFields`

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

### `GridBuilder\CollectFilter`

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
  outcomes is order-independent. `GridBuilder\FilterIsPartOfResourceClassUnitTest` drives both orders
  against `grid_custom_filter.php` to show which node wins; keeping the shipped order in
  `extension.neon` correct is a manual job.
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
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder\FieldIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridBuilder\FilterIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridMutator\FieldIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Grid\GridMutator\FilterIsPartOfResourceClass
    - bitExpert\PHPStan\Sylius\Rule\Resource\IndexOperationNeedsGridClassRule
    - bitExpert\PHPStan\Sylius\Rule\Resource\ResourceAttributeNeedsFormTypeRule
    - bitExpert\PHPStan\Sylius\Rule\Resource\CreateOperationNeedsFactoryRule
    - bitExpert\PHPStan\Sylius\Rule\Resource\OperationNeedsValidProviderRule
    - bitExpert\PHPStan\Sylius\Rule\Resource\OperationNeedsValidProcessorRule
  ```
- **Services**:
  - `FieldRegistryFactory` → `syliusFieldTypeRegistry` via `createRegistry()`.
  - `FilterRegistryFactory` → `syliusFilterTypeRegistry`.
  - Collectors (`CollectRessourceClassForGridClass`, `CollectGridNameForGridClass`, `GridBuilder\CollectFields`,
    `GridBuilder\CollectFilter`, `GridMutator\CollectFields`, `GridMutator\CollectFilter`, `GridMutator\CollectGridNames`)
    tagged `phpstan.collector`.
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

- **Framework**: PHPUnit 11.5 and 12.5. The dev constraint is the union `^11.5 || ^12.5` because PHPUnit caps
  the pairing: 11.5 is the last series that installs on PHP 8.2, 12.x needs `>= 8.3`, 13.x needs `>= 8.4.1`.
  Since the package itself must keep supporting PHP 8.2 (Sylius consumers), CI runs a PHP 8.2 lane on 11.5
  *and* a PHP 8.4 lane on 12.5. Every lane pins its own PHPUnit constraint before `composer update`; the
  committed `composer.lock` therefore never decides the matrix and is effectively advisory.
- **Configuration**: `phpunit.xml.dist` (suffix `UnitTest.php`, bootstrap `tests/bootstrap.php`).
- **Rules** use `PHPStan\Testing\RuleTestCase`.
- **Utility/Registry** use standard `PHPUnit\Framework\TestCase`.
- 38 tests total. The count is 38 on every lane, but assertions differ by grid-bundle lane: 31 on `^1.15
  <1.16` (9 skipped — the 8 grid-mutator tests need `GridMutatorInterface` from `>= 1.16`, plus the
  `Sylius\Component\Grid\GridInterface` case) and 40 on `^1.16` (nothing skipped). A 31/40 split is
  expected; a 38-vs-something split is not.

#### Gotchas that make tests lie

- **`RuleTestCase` asserts only `line: message`, never the file.** A wrong `->file()` in a rule cannot be
  caught by these tests. Do not add a test that appears to cover file attribution.
- **`phpunit.xml.dist` sets `stopOnFailure="true"`.** A failing run reports only the tests up to the first
  failure, so a low test count is not a discovery problem — confirm with `--list-tests`.
- **PHPUnit 12 escalates a config-schema mismatch from a deprecation to a failure.** PHPUnit validates the
  config against the schema for the *current series*, not against the URL in
  `xsi:noNamespaceSchemaLocation` — so a stale URL there is harmless on its own. But when the config *does*
  fail series validation, 11.5 emits a plain `testRunnerTriggeredDeprecation` (never fails the run) while 12.5
  emits `testRunnerTriggeredPhpunitDeprecation`, which trips this project's `failOnPhpunitDeprecation="true"`.
  If a 12.5 lane starts failing on config validation, fix the config (or run `--migrate-configuration`) rather
  than relaxing `failOnPhpunitDeprecation`.
- **PHPStan's PHPUnit bridge only supports PHPUnit 11 in practice.** `PHPStan\Testing\PHPUnit\PHPStanPHPUnitExtension`
  and its subscribers implement `PHPUnit\Event\Test\PreparationStarted(Subscriber)` and
  `PHPUnit\Event\Test\DataProviderMethodCalled(Subscriber)`, all of which moved to
  `PHPUnit\Event\Events\Test\*` in PHPUnit 12. The `RuleTestCase` hot path is unaffected (it only touches
  `PHPUnit\Framework\TestCase` and `ExpectationFailedException`), which is why the 12.5 lane passes. But
  **do not** add
  ```xml
  <extensions><bootstrap class="PHPStan\Testing\PHPUnit\PHPStanPHPUnitExtension"/></extensions>
  ```
  to `phpunit.xml.dist` — on PHPUnit 12.5 that is a fatal error. If a test ever needs PHPStan's container
  initialised before a data provider runs, it must be gated on the PHPUnit major version.
- **Test registries are hand-built.** Each grid rule test overrides `getCollectors()` and assembles its own
  node list. Registering a node in `extension.neon` alone changes nothing in the tests, so a green suite can
  pass while the new node is never exercised. Update both, and prove the node is load-bearing by pointing
  one `FILTER_TYPE`/`FIELD_TYPE` at a wrong class name and confirming the expectation disappears. Keep the
  hand-built list in the same order as `extension.neon`, since that is the order the collector really uses.
- **Do not bulk-shift fixture line numbers with sequential `str.replace`.** Replacing `46→49` and then
  `49→52` re-edits the value just written and shifts the wrong entries. Re-derive line numbers from the file.

### Test Files

| Test File | Purpose | Fixtures |
|-----------|---------|----------|
| `PropertyNameUnitTest` | Unit tests for snake → camel conversion | None |
| `ResourceAttributeNeedsFormTypeUnitTest` | Validates `AsResource` form type existence | `Rule/Resource/data/entity.php`, `entity_non_constant_attribute.php` |
| `IndexOperationNeedsGridClassUnitTest` | Validates `Index` grid reference | `Rule/Resource/data/entity_index.php` |
| `CreateOperationNeedsFactoryUnitTest` | Validates `Create` factory class/method (incl. callable form, `createNew` default, non-constant skips) | `Rule/Resource/data/entity_factory.php`, `entity_factory_valid.php`, `entity_factory_skip.php`, `factories.php`, `book.php` |
| `OperationNeedsValidProviderUnitTest` | Validates operation provider class/method (incl. callable form, function-string form, non-resource/non-constant skips) | `Rule/Resource/data/entity_provider.php`, `entity_provider_valid.php`, `entity_provider_skip.php`, `providers.php` |
| `OperationNeedsValidProcessorUnitTest` | Validates operation processor class/method (incl. callable form, function-string form, non-resource/non-constant skips) | `Rule/Resource/data/entity_processor.php`, `entity_processor_valid.php`, `entity_processor_skip.php`, `processors.php` |
| `ResourceAwareGridNeedsResourceClassUnitTest` | Missing resource class (old, attribute, 1.16-native, method-less) | `grid_needs_resource_model.php`, `grid_needs_resource_model_attr.php`, `grid_needs_resource_model_native_interface.php`, `grid_needs_resource_model_no_methods.php` |
| `ResourceAwareGridNeedsResourceClassValidUnitTest` | Validates correct configurations | `grid_valid.php`, `grid_valid_attr.php` |
| `GridBuilder\FieldIsPartOfResourceClassUnitTest` | Invalid field detection (incl. `createForService`) | `grid.php` |
| `GridBuilder\FieldIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `GridBuilder\FilterIsPartOfResourceClassUnitTest` | Invalid filter detection, plus registry-order (catch-all vs. dedicated node) | `grid.php`, `grid_custom_filter.php`, `custom_filter.php` |
| `GridBuilder\FilterIsPartOfResourceClassValidUnitTest` | Validates correct grids | `grid_valid.php` |
| `GridMutator\FieldIsPartOfResourceClassUnitTest` | Invalid mutator field detection (incl. recursive dotted path) | `grid_named.php`, `grid_mutator.php` |
| `GridMutator\FieldIsPartOfResourceClassValidUnitTest` | Validates correct mutator fields | `grid_named.php`, `grid_mutator_valid.php` |
| `GridMutator\FilterIsPartOfResourceClassUnitTest` | Invalid mutator filter detection | `grid_named.php`, `grid_mutator.php` |
| `GridMutator\FilterIsPartOfResourceClassValidUnitTest` | Validates correct mutator filters | `grid_named.php`, `grid_mutator_valid.php` |
| `DefaultFilterRegistryUnitTest` | Registry functionality | None |

### Fixtures

All fixtures (for analysis) reside under `tests/bitExpert/PHPStan/Sylius/Rule/*/data/`.

`Rule/Grid/data/` (namespace `App\Entity` / `App\Grid`):
- `entity.php`: `App\Entity\Status` (enum), `Country`, `Address`, `Supplier` (implements `ResourceInterface`, missing `name`). `Country` and `Address::$country`/`getCountry()` back the three-segment field case.
- `grid.php`: `AdminSupplierGrid` with invalid field/filter definitions, plus `SomeOtherClass`. Shared by the field *and* filter tests, so any edit shifts expectations in both.
- `grid_valid.php` / `grid_valid_attr.php`: `GridValid` / `GridValidAttr`, correct grid configurations.
- `grid_needs_resource_model.php` / `_attr.php` / `_no_methods.php` / `_native_interface.php`: resource-class detection cases.
- `custom_filter.php`: namespace `App\Filter`, declaring `class CustomFilter`; a user-style filter that implements `FilterInterface`, so the catch-all matches it by interface. Implements every method for real so PHPStan verifies the signatures on both lanes.
- `grid_custom_filter.php`: `GridCustomFilter`, holding the one `CustomFilter::create()` call whose extracted field differs depending on which node wins.
- `grid_named.php`: `AdminSupplierNamedGrid`, a grid whose `getName()` returns its class name, so a mutator can reference it by name.
- `grid_named_attr.php` / `grid_named_custom.php` / `grid_named_medipack.php`: attribute-only grids (`#[AsGrid(resourceClass: ...)]`, no methods) — the 1.16 shape — one with an explicit `name:` argument, one with `Medipack` as resource class for the repeatable-mutator join.
- `grid_mutator.php` / `grid_mutator_valid.php` (namespace `App\Grid\Mutator`): grid mutators (`#[AsGridMutator]`, `GridMutatorInterface`) with invalid/valid field and filter definitions, plus a mutator for an unknown grid name and a non-mutator class. Guarded by `interface_exists(GridMutatorInterface::class)` and excluded from self-analysis, since mutators only parse on grid-bundle >= 1.16.
- `grid_mutator_names.php` / `grid_mutator_repeatable.php`: mutators joining via class name, via an explicit `#[AsGrid(name: ...)]`, and a repeatable mutator attached to two grids, proving fields/filters are validated against every joined resource class.

`Rule/Resource/data/` (namespace `App\Entity`):
- `entity.php`: `Entity`, implementing `ResourceInterface`.
- `entity_index.php`: `EntityIndex`, missing `Index(grid:)`.
- `entity_non_constant_attribute.php`: `EntityWithNonConstantFormType`, `EntityWithArrayFormType`, `EntityWithIntFormType`, `EntityWithNonConstantGrid`, `EntityWithArrayGrid`, `EntityWithConstantFormType` — non-constant attribute arguments, which the rules must skip silently.
- `book.php`: `App\Entity\Book`, the resource the fixture factories return. Only needed so the factory fixture classes type-check.
- `factories.php` (namespace `App\Factory`): `BookFactory` (valid `createNew`/`createForAuthor`), `MethodNotExistsFactory`, `ClassOnlyFactory` (no `createNew`), `CallableFactory` (static `create`), `CallableFactoryNotStatic`, `CallableFactoryWrongMethod`. `FactoryClassNotExists`/`CallableFactoryClassNotExists` are deliberately never defined.
- `entity_factory.php`: six `AsResource`+`Create` combinations that each trigger one error (missing class, missing method, missing `createNew` default, missing callable class, non-static callable method, missing callable method).
- `entity_factory_valid.php`: three valid configurations (class+method, class-only with `createNew`, static callable) producing no errors.
- `entity_factory_skip.php`: `factory: false`, non-constant `factory`/`factoryMethod`, and a standalone `#[Create]` on the class (which *is* validated — the one expected error comes from it).
- `providers.php` (namespace `App\Provider`): `BookProvider` (implements `ProviderInterface`), `MethodNotExistsProvider`, `NotStaticProvider` (instance `provide`), `CallableProvider` (static `provide`), `CallableProviderWrongMethod`, plus a plain `provider_function()`. `ProviderClassNotExists` is deliberately never defined.
- `processors.php` (namespace `App\Processor`): `BookProcessor` (implements `ProcessorInterface`), `NotStaticProcessor` (instance `process`), `CallableProcessor` (static `process`), `CallableProcessorWrongMethod`, plus a plain `processor_function()`. `ProcessorClassNotExists` is deliberately never defined.
- `entity_provider.php`: four operation/provider combinations that each trigger one error (missing class, missing callable method, non-static callable method, and a standalone `#[Show]` on the class with a missing method — proving directly-declared attributes are validated too).
- `entity_processor.php`: the processor mirror of `entity_provider.php` (missing class, missing callable method, non-static callable method, standalone `#[Show]` with a missing method). Its classes are named `Entity*Processor*`/`EntityWithStandaloneShowProcessorAttribute` so they never collide with the provider fixture classes: all fixtures load through `autoload-dev.files` into one namespace, so a duplicate class name is a fatal `Cannot redeclare class` at bootstrap.
- `entity_provider_valid.php`: four valid configurations (class string, `Class::class`, static callable, existing function string) producing no errors.
- `entity_provider_skip.php`: `provider: null`, non-constant `provider`, an operation without a `provider` argument, and a non-resource class with a broken provider (never checked — the rule only fires on `ResourceInterface` implementors).
- `entity_processor_valid.php` / `entity_processor_skip.php`: the processor mirrors of the provider valid/skip fixtures (class string, `Class::class`, static callable, existing function string; `processor: null`, non-constant `processor`, missing `processor` argument, non-resource class).

These files are loaded via `composer.json` `autoload-dev.files` so classes exist during analysis, and
`Rule/Grid/data/grid.php` + `entity.php` + `grid_custom_filter.php` are excluded from PHPStan analysis
in `phpstan.dist.neon`. `custom_filter.php` is deliberately **not** excluded: it declares no invalid
references, so analysing it is what proves the interface implementation still holds on grid-bundle 1.16.
The new `Rule/Resource/data/` factory fixtures (`book.php`, `factories.php`, `entity_factory*.php`) are
excluded as well, because they reference the deliberately-missing factory classes. The provider fixtures
(`providers.php`, `entity_provider*.php`) follow the same pattern: they reference the deliberately-missing
`ProviderClassNotExists`, so they are excluded from self-analysis too. The processor fixtures
(`processors.php`, `entity_processor*.php`) are excluded for the same reason (`ProcessorClassNotExists`).

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

Matrix over PHP version × OS × grid-bundle constraint, with the PHPUnit constraint coupled to the PHP
version via a matrix `include:` block (4 lanes). Each lane runs:

1. Checkout repo
2. Configure PHP (`shivammathur/setup-php`)
3. `composer require --dev "sylius/grid-bundle:<constraint>" "phpunit/phpunit:<constraint>" --no-update` +
   `composer update`
4. Show resolved versions — **one `composer show <pkg>` call per package**; `composer show` takes a single
   package plus an optional version, so passing several names exits `1`
5. `composer check-license`
6. `composer cs`
7. `composer static-analysis`
8. `composer test`

The two grid-bundle lanes are `^1.15 <1.16` and `^1.16`, and they cross with two PHP/PHPUnit lanes:
PHP 8.2 on PHPUnit `^11.5`, and PHP 8.4 on PHPUnit `^12.5`. Keep all four green; a change that only passes
one of them is not done. 8.3 is deliberately not used: it is past EOL, and 8.4 is the first non-EOL version
that can host PHPUnit 12.

**Local verification gotcha:** `vendor/bin/*` scripts resolve `php` from `PATH` via their shebang, *not* from
whichever PHP you invoked Composer with. `php8.4 composer test` still runs PHPUnit under 8.2 unless 8.4 is
first on `PATH`, which shows up as a confusing parse error in a dependency (e.g. `sebastian/diff` using typed
class constants, a PHP 8.3 feature). Symlink the target PHP into a throwaway bin dir and prepend it to
`PATH` before running the `composer` scripts.

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
  defensive `instanceof` guard — it can mask a real annotation mistake instead of catching one. Every
  rule's `getNodeType()` now carries `@return class-string<ConcreteNodeType>`, so PHPStan rejects a
  mismatch between the two; do not widen that back to a bare `class-string`, or the safety net goes away.

### 3. Node/collector coverage is opt-in
- A field or filter class with **no** matching node is silently never validated — no error, no warning.
  The catch-all only covers classes that implement `FilterInterface`, and only `Filter` itself does.
- **Impact**: `BooleanFilter`, `DateFilter` and `MoneyFilter` were unvalidated until nodes were added for
  them. When a new grid-bundle factory appears, add a node in the same change, and check the grid-bundle
  changelog when bumping the floor.

### 4. Recursive Filter Fields Skipped
- The rule `GridBuilder\FilterIsPartOfResourceClass` skips any filter field containing `.` (e.g., `address.city`).
- **Rationale**: Filters typically do not need recursive access, but this may be overly restrictive.
- **Consequence**: Users cannot validate recursive filter fields.

### 5. Field errors are attributed to the grid's file, not the node's
- All four grid builder/mutator rules use `$ownerFilesMap[$ownerClass]`, which is **last-write-wins per owning class** (grid
  or mutator) and shared by all fields of that owner.
- **Impact**: A grid split across multiple files reports every field error against whichever file was seen
  last. Fields inside a trait are always attributed to the using class, because the node's real file is not
  recoverable: PHP-Parser 5 exposes only an integer `startFilePos` (no path), and `Scope::getFile()`,
  `getFunction()->getFileName()` and `->getDeclaringClass()->getFileName()` all return the using class file.
- **Action**: not fixable without a broader file-identity design; `RuleTestCase` cannot even assert it.

### 6. `data/` fixtures are not PSR-4 and are excluded from PHP-CS-Fixer
- Fixtures under `tests/**/data/` carry **no license header** (only `src/` is header-checked) and are
  registered in `composer.json` `autoload-dev.files`, so they are loaded by inclusion, not by class name.
  PSR-4 file-name/class-name pairing therefore does not apply, and the file name deliberately stays
  snake_case (`grid_valid.php`) while the class is PSR-1 (`GridValid`).
- That combination is exactly what `@Symfony`'s `psr_autoloading` rule "fixes": its matcher compares
  class and file name **case-insensitively**, so `class GridValid` in `grid_valid.php` gets rewritten
  to `class grid_valid`. `.php-cs-fixer.dist.php` therefore excludes both `Rule/Grid/data` and
  `Rule/Resource/data` from the finder. Do not drop those exclusions: `composer cs` is a CI step, and a
  local `cs-fix` would rewrite fixture classes, breaking the references between fixtures.
- The failure mode is a *missing* error, not a wrong one. A renamed fixture class makes the call site
  name a non-existent class, `GridBuilder\CollectFilter` can no longer resolve a `FilterInterface`
  return type, the call is silently skipped, and the test fails only on the missing expected error. So a
  green-looking `composer cs-fix` followed by a red test usually means the fixer edited a fixture.
- **Action**: fixture file names stay snake_case and lowercase, fixture classes are always PSR-1. When
  renaming either one, update all of them together — the class declaration, every call site and `use`
  statement in sibling fixtures, and any class name a test asserts or matches on
  (e.g. `'App\Filter\CustomFilter'` in the filter ordering tests).

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

*Last updated: 2026-10-31*
