# Changelog

All notable changes to the Adaptive Entity Manager package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.2] - 2025-06-30

### Changed
- **Entity Instantiation**: Modified `EntityFactory::makeEntity()` to use `ReflectionClass::newInstanceWithoutConstructor()` instead of calling entity constructors directly
- **Doctrine-like Behavior**: EntityFactory now creates entities without invoking constructors during hydration, matching Doctrine ORM behavior
- **Constructor Safety**: Prevents potential issues with entities that have constructors requiring parameters or containing business logic

### Technical Details
- Replaced `new ($classMetadata->getName())()` with `$reflectionClass->newInstanceWithoutConstructor()` in `EntityFactory::makeEntity()`
- This change ensures entity constructors are not called during database hydration process
- Maintains compatibility with existing code while providing safer entity instantiation
- `ValueObjectAwareEntityFactory` inherits this improvement automatically

### Benefits
- **Safer hydration**: No unexpected constructor execution during entity creation from database
- **Better compatibility**: Aligns with Doctrine ORM's entity instantiation approach
- **Reduced errors**: Prevents constructor-related errors during entity hydration

## [1.3.1] - 2025-06-18

### Fixed
- **DateTime Immutable Support**: Added support for `datetime_immutable` field type in `FieldTypeEnum::normalizeType()`
- **Enhanced DateTime Conversion**: Implemented automatic string-to-DateTime conversion in `ValueObjectAwareEntityFactory`
- **Flexible Identifier Handling**: Improved `loadById()` method to handle various identifier formats (`['id' => 1]`, `[1]`, `['ID' => 1]`)
- **Type Safety**: Enhanced type validation and conversion for DateTime types (`DateTime`, `DateTimeImmutable`, `DateTimeInterface`)

### Technical Details
- Extended `FieldTypeEnum::normalizeType()` to map `'datetime_immutable'` and `'datetimeimmutable'` to `DateTime` type
- Added `convertStandardType()` method in `ValueObjectAwareEntityFactory` for automatic DateTime conversion
- Improved identifier resolution in data adapters to support multiple identifier key formats
- Enhanced type compatibility checking between entity properties and field metadata

### Compatibility
- **100% Backward Compatible**: All existing code continues to work without changes
- **Enhanced Type Support**: Better handling of modern PHP DateTime types
- **Improved Robustness**: More flexible identifier and type handling

## [1.3.0] - 2024-06-18

### Added

#### 🎉 ValueObject Support
- **Complete ValueObject system** with `ValueObjectInterface` and `AbstractValueObject` base class
- **Built-in ValueObjects**:
  - `Email` - Email validation with domain/local part extraction
  - `Money` - Currency-aware monetary values with arithmetic operations
  - `UserId` - Type-safe user identifiers with validation
- **Converter System**:
  - `ValueObjectConverterInterface` for custom conversions
  - `DefaultValueObjectConverter` with automatic primitive conversion
  - `ValueObjectConverterRegistry` for managing multiple converters
- **Framework Integration**:
  - Extended `FieldTypeEnum` with `ValueObject` type
  - `ValueObjectAwareEntityFactory` for automatic conversion during hydration
  - Enhanced `AdaptiveEntityManager` with optional `ValueObjectConverterRegistry`
  - Updated `EntityPersister` with ValueObject-aware persistence

#### 🔧 Core Enhancements
- **New Field Type**: Added `FieldTypeEnum::ValueObject` for ValueObject fields
- **Enhanced Entity Factory**: ValueObject-aware entity creation and hydration
- **Improved Entity Manager**: Optional ValueObject support with registry parameter
- **Automatic Conversion**: Seamless primitive ↔ ValueObject conversion during persistence

#### 📚 Documentation & Examples
- **Complete Documentation**: `docs/VALUE_OBJECTS.md` with usage examples
- **Demo Script**: `examples/value_objects_demo.php` showcasing all features
- **Integration Tests**: Comprehensive test suite with 80+ tests
- **Updated README**: ValueObject examples and usage patterns

### Enhanced

#### 🚀 Performance & Reliability
- **Runtime Caching**: Optimized ValueObject conversion with caching
- **Type Safety**: Full PHP type checking for ValueObjects
- **Validation**: Built-in validation for all ValueObject types
- **Immutability**: ValueObjects are immutable by design

#### 🔄 Backward Compatibility
- **100% Backward Compatible**: Existing code works without changes
- **Optional Feature**: ValueObject support is opt-in
- **Graceful Degradation**: Works with or without ValueObject registry

### Technical Details

#### Architecture
- **Registry Pattern**: Centralized ValueObject converter management
- **Factory Pattern**: Enhanced entity factories with ValueObject support
- **Strategy Pattern**: Pluggable converter system for custom ValueObjects

#### Database Integration
- **Transparent Persistence**: ValueObjects serialize to primitives automatically
- **Schema Compatibility**: No database schema changes required
- **Type Mapping**: Automatic type conversion during hydration/persistence

#### Testing
- **Comprehensive Coverage**: 80+ tests covering all ValueObject functionality
- **Integration Tests**: Real-world usage scenarios
- **Validation Tests**: Error handling and edge cases
- **Performance Tests**: Conversion efficiency verification

### Examples

```php
// Using built-in Email ValueObject
$user = new User();
$user->setEmail(new Email('user@example.com'));

// Automatic conversion during persistence
$entityManager->persist($user);  // Email converts to string
$entityManager->flush();

// Automatic conversion during hydration
$loadedUser = $entityManager->find(User::class, 1);
$email = $loadedUser->getEmail();  // Returns Email ValueObject
echo $email->getDomain();  // "example.com"
```

### Migration Guide

For existing projects wanting to use ValueObjects:

1. **Add ValueObject Registry** to Entity Manager:
```php
$registry = new ValueObjectConverterRegistry();
$entityManager = new AdaptiveEntityManager($config, $metadata, $dataAdapter, valueObjectRegistry: $registry);
```

2. **Update Entity Metadata** to specify ValueObject fields:
```php
$metadata->addField('email', FieldTypeEnum::ValueObject, Email::class);
```

3. **Update Entity Properties** to use ValueObject types:
```php
private ?Email $email = null;
```

### Dependencies
- No new required dependencies
- Enhanced PSR-6 cache support for metadata caching
- Compatible with existing Doctrine persistence interfaces

## [1.2.1] - 2025-01-17

### Fixed
- **DateTimeInterface Type Support**: Added support for `DateTimeInterface` typed properties in entities
- **Enhanced Type Comparison**: Fixed EntityFactory type comparison logic to properly normalize both property and field types
- **PHP 8.1+ Compatibility**: Resolved compatibility issue with modern PHP typed properties using `DateTimeInterface`

### Technical Details
- Extended `FieldTypeEnum::normalizeType()` to recognize `'datetimeinterface'` as equivalent to `'datetime'`
- Modified EntityFactory comparison logic to normalize both sides: `FieldTypeEnum::normalizeType(strtolower($propertyType->getName())) !== FieldTypeEnum::normalizeType($typeOfField)`
- This allows entities to declare properties as `private ?DateTimeInterface $dateRegister` while using `'datetime'` type in metadata

## [1.2.0] - 2025-06-16

### Added
- **PSR-6 Compatible Metadata Caching System**: Implemented multi-level caching for entity metadata with runtime and persistent cache layers
- **CachedEntityMetadataProvider**: Decorator with cache invalidation, warm-up, and statistics
- **OptimizedEntityMetadataFactory**: Replacement factory with cached entity scanning and optimized proxy generation
- **SimpleFileCache & SimpleCacheItem**: Basic PSR-6 implementation as fallback when no external cache is provided
- **MetadataSystemFactory**: Factory for easy setup supporting both optimized and legacy modes
- **New optional constructor parameters** in `AdaptiveEntityManager`:
  - `?CacheItemPoolInterface $metadataCache = null` - PSR-6 cache for metadata
  - `bool $useOptimizedMetadata = true` - Enable/disable optimized metadata system
  - `EventDispatcherInterface $eventDispatcher = null` - Event dispatcher integration
- **Comprehensive test suite** with 72 tests (52 unit + 14 integration + 6 metadata)
- **Documentation files**: `CACHING.md` and `TESTING.md`
- **Enhanced Composer scripts**: test:syntax, test:quick, cache:clear, install:dev

### Changed
- **Performance improvements**: Expected 50-70% faster cold start, 90-95% faster warm cache requests
- **Optimized metadata system enabled by default** while maintaining full backward compatibility
- **Enhanced PHPUnit configuration** with proper test suites and execution order
- **Improved test reliability** with fixed SplObjectStorage handling and static property initialization

### Fixed
- **Removed archive exclusions** from `composer.json` - tests are now included in package distribution
- **Fixed test execution order** issues that caused random failures
- **Resolved SplObjectStorage::current()** issues with proper rewind() calls
- **Fixed static property initialization** problems in test classes

### Dependencies
- Added `psr/cache: ^1.0 || ^2.0 || ^3.0` for metadata caching support

### Breaking Changes
- **None** - Full backward compatibility maintained for all existing code

## [1.1.1] - 2024-06-05

### Fixed
- Corrected the dependency injection of `Psr\EventDispatcher\EventDispatcherInterface` into `UnitOfWork` via the `AdaptiveEntityManager` constructor, resolving issues with event dispatching and service instantiation.

## [1.1.0] - 2025-06-05

### Added
- Implemented a universal event system using PSR-14 to allow extensibility and modularity for entity lifecycle events (Pre/Post Persist, Update, Remove).
- Added `psr/event-dispatcher` as a required dependency.

## [1.0.8] - 2025-06-05

### Fixed
- Normalized entity namespaces in `EntityMetadataFactory::getResult()` to allow configuration with or without trailing backslashes.

## [1.0.7] - 2024-05-30

### Changed
- Added .gitattributes file to enforce LF line endings
- Fixed namespace issues in test files
- Improved test environment configuration

## [1.0.6] - 2024-05-30

### Fixed
- Fixed: Corrected folder paths in Config.php to resolve issues with incorrect directory references.

## [v1.0.5] - 2024-05-30

### Fixed
- Fixed paths and namespaces in tests when initializing Config objects

## [v1.0.4] - 2024-05-30

### Changed
- Reorganized test structure for better maintainability
- Moved unit tests to dedicated `tests/Unit` directory
- Relocated mock objects to `tests/Mock` directory
- Updated namespace declarations in test files
- Fixed file paths in test configurations

## [v1.0.3] - 2024-05-30

### Added
- Added comprehensive example of REST API data adapter implementation
- Enhanced documentation with detailed data adapter usage examples
- Improved data adapter configuration guide

## [v1.0.2] - 2024-05-30

### Added
- Enhanced README.md with detailed metadata configuration example
- Added comprehensive example of entity metadata implementation
- Improved documentation for metadata management

## [v1.0.1] - 2025-05-29

### Fixed
- Fixed DefaultEntityMetadataProvider implementation for proper metadata class resolution
- Enhanced README.md with comprehensive documentation
- Improved metadata class name resolution logic
- Fixed namespace handling in metadata provider

### Documentation
- Added detailed installation instructions
- Expanded usage examples
- Added configuration documentation
- Improved API documentation
- Added examples for custom data adapters
- Enhanced metadata configuration guide

## [v1.0.0] - 2024-05-28

### Added
- Initial release of Adaptive Entity Manager
- Core entity management functionality
- Support for multiple data sources through adapters
- Entity metadata system
- Transaction management
- Lazy loading through proxy objects
- Flexible repository system
- Unit of Work implementation
- Entity lifecycle callbacks
- Relationship management (One-to-One, One-to-Many)

### Dependencies
- PHP 8.1 or higher
- doctrine/persistence: ^3.0 || ^4.0
- laminas/laminas-code: ^4.0
- psr/container: ^1.1 || ^2.0
- symfony/cache: ^6.0 || ^7.0
- symfony/string: ^6.0 || ^7.0

## Support

If you discover any security-related issues, please email kabirovruslan@gmail.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
