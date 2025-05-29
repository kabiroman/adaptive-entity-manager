# Changelog

All notable changes to the Adaptive Entity Manager package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
