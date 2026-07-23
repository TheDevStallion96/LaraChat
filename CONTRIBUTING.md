# Contributing to LaraChat

Thank you for considering contributing to LaraChat.

## Bug Reports

Open an issue using the bug report template. Include steps to reproduce, expected vs actual behaviour, and environment details (OS, PHP version, browser).

## Feature Requests

Open an issue using the feature request template. Describe the problem you're trying to solve and how the feature would work.

## Pull Requests

1. Fork the repository and create a branch from `master`.
2. Name branches with a prefix: `fix/`, `feat/`, `tests/`, `docs/`, `chore/`.
3. Write clear, descriptive commit messages using conventional commits.
4. Run the full CI suite before opening your PR:

   ```bash
   composer ci:check
   ```

5. Keep PRs focused on a single concern. Large changes should be broken into smaller PRs.
6. All tests must pass. Add tests for new functionality.

## Development Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
```

## Code Style

PHP follows Laravel Pint (PSR-12). TypeScript/Vue follows Prettier and ESLint. Run formatting before committing:

```bash
vendor/bin/pint --format agent
npm run format
```

## Code of Conduct

This project adheres to the [Contributor Covenant](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.
