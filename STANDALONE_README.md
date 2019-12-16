# Event Sourced Content Repository - Standalone PHP Version

**ALPHA QUALITY**: Note that the standalone version of the CR is not yet
officially supported by the Neos team, but alpha quality. We do our best
to keep it working in sync with the integrated version.

## What is this?



## Trying it out

```
cd standalone-example
composer install
# adjust DB credentials in index.php
php index.php
```

## Internal Structure - Shims

- Ignoring @Flow\Inject annotations
- ensure only minimal packages are installed (e.g. no Flow / Eel / Fluid)
- custom Symfony DI configuration
