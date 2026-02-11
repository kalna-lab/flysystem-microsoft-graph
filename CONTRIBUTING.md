# Contributing to Flysystem Microsoft Graph Adapter

Thank you for considering contributing to this package! 🎉

## Code of Conduct

Please be respectful and constructive in all interactions.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues. When creating a bug report, include:

- A clear and descriptive title
- Steps to reproduce the behavior
- Expected behavior
- Actual behavior
- Your environment (PHP version, Laravel version, OS)
- Any relevant logs or error messages

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion, include:

- A clear and descriptive title
- A detailed description of the proposed functionality
- Explain why this enhancement would be useful
- List any alternative solutions you've considered

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Add tests for your changes
5. Ensure all tests pass (`composer test`)
6. Run static analysis (`composer analyse`)
7. Commit your changes (`git commit -m 'Add amazing feature'`)
8. Push to the branch (`git push origin feature/amazing-feature`)
9. Open a Pull Request

#### Pull Request Guidelines

- Write clear, descriptive commit messages
- Include tests for new functionality
- Update documentation as needed
- Follow PSR-12 coding standards
- One feature/fix per pull request
- Keep pull requests focused and small

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/flysystem-microsoft-graph.git
cd flysystem-microsoft-graph

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse
```

## Testing

We use PHPUnit for testing. Please ensure:

- All tests pass before submitting PR
- New features include tests
- Bug fixes include regression tests
- Aim for high code coverage

```bash
# Run all tests
composer test

# Run specific test
vendor/bin/phpunit tests/Unit/TokenManagerTest.php
```

## Coding Standards

We follow PSR-12 coding standards. You can check your code with:

```bash
composer analyse
```

## Documentation

- Update README.md for new features
- Add PHPDoc blocks for all public methods
- Include code examples where appropriate
- Update CHANGELOG.md following [Keep a Changelog](https://keepachangelog.com/)

## Questions?

Feel free to open an issue for questions or contact us at contact@kalna.it

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
