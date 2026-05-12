<p align="center">
  <a href="https://github.com/Pollora/cli">
    <img src="resources/images/pollora-logo.svg" width="400" alt="Pollora">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/cli"><img src="https://img.shields.io/packagist/v/pollora/cli" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/pollora/cli"><img src="https://img.shields.io/packagist/dt/pollora/cli" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/pollora/cli"><img src="https://img.shields.io/packagist/l/pollora/cli" alt="License"></a>
</p>

## About Pollora CLI

The Pollora CLI is a command-line tool for creating and managing [Pollora](https://github.com/Pollora/framework) projects. It provides interactive scaffolding with optional DDEV integration, and acts as an intelligent proxy to framework commands when used inside a project.

## Installation

Install the CLI globally via Composer:

```bash
composer global require pollora/cli
```

Make sure the Composer global `vendor/bin` directory is in your system's `PATH`:

```bash
composer global config bin-dir --absolute
```

Add the returned path to your shell profile if it's not already configured:

<details>
<summary><strong>Bash</strong> — <code>~/.bashrc</code></summary>

```bash
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```
</details>

<details>
<summary><strong>Zsh</strong> — <code>~/.zshrc</code></summary>

```bash
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```
</details>

<details>
<summary><strong>Fish</strong> — <code>~/.config/fish/config.fish</code></summary>

```fish
fish_add_path $HOME/.config/composer/vendor/bin
```
</details>

<details>
<summary><strong>Windows (PowerShell)</strong></summary>

```powershell
# Add to your PowerShell profile ($PROFILE)
$env:PATH = "$env:APPDATA\Composer\vendor\bin;$env:PATH"
```
</details>

> **Note:** On some systems, the Composer global directory may be `~/.composer/vendor/bin` instead of `~/.config/composer/vendor/bin`. Use the `composer global config bin-dir --absolute` command to check.

After updating your profile, reload your shell (`source ~/.zshrc`, `source ~/.bashrc`, etc.) or open a new terminal.

## Creating a new project

### Standard install

```bash
pollora new my-site
```

The command will:
1. Run `composer create-project pollora/pollora`
2. Execute `php artisan pollora:install` for WordPress setup
3. Optionally initialize a Git repository

### With DDEV (recommended)

```bash
pollora new my-site --ddev
```

When the `--ddev` flag is passed (or selected interactively), the CLI will:
1. Configure DDEV (WordPress, PHP 8.4, MariaDB 10.11)
2. Start the DDEV environment
3. Install the project via `ddev composer create-project`
4. Run `pollora:install` inside the container
5. Publish the `./pollora` binary and `ddev pollora` command

Your site will be available at `https://my-site.ddev.site`.

### Options

| Option | Description |
|---|---|
| `--ddev` | Set up the project with DDEV |
| `--force`, `-f` | Force install even if the directory already exists |
| `--git` | Initialize a Git repository |
| `--branch=NAME` | Branch name for the new repository (default: `main`) |

## Using Pollora commands

### Inside a Pollora project

When you run `pollora` inside a directory that contains both `artisan` and `vendor/pollora/framework`, the CLI acts as a **proxy** and delegates commands to `php artisan pollora:{command}`:

```bash
cd my-site

pollora status            # => php artisan pollora:status
pollora make-plugin Foo   # => php artisan pollora:make-plugin Foo
pollora make-theme starter # => php artisan pollora:make-theme starter
```

### With DDEV

If you set up your project with `--ddev`, a custom `ddev pollora` command is available:

```bash
ddev pollora status
ddev pollora make-plugin Foo
ddev pollora list
```

### With the local `./pollora` binary

The framework provides a dedicated `./pollora` binary (published via `vendor:publish --tag=pollora-binary`) that shows only Pollora-related commands with short names:

```bash
./pollora list

# Available commands:
#   install        Install and configure WordPress
#   status         Display Pollora framework status
#   make-plugin    Generate plugin structure
#   make-theme     Generate theme structure
#   make-block     Create a new Gutenberg block
#   make-posttype  Create a new custom post type
#   ...
```

The original `php artisan pollora:*` signatures continue to work as aliases.

## Other commands

| Command | Description |
|---|---|
| `pollora version` | Display the CLI version |
| `pollora self-update` | Update the CLI to the latest version |

## Requirements

- PHP >= 8.2
- Composer 2.x
- DDEV (optional, for `--ddev` mode)

## Testing

```bash
composer test              # Run all checks (Rector, Pint, PHPStan, Pest, type-coverage)
composer test:unit         # Run Pest tests
composer test:types        # Run PHPStan static analysis (level 5)
composer test:lint         # Check code style with Pint
composer test:refacto      # Check refactoring rules with Rector
composer test:type-coverage # Check type coverage (>= 98%)
```

## License

Pollora CLI is open-sourced software licensed under the [GPL-2.0-or-later](LICENSE).
