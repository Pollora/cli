<p align="center">
  <a href="https://pollora.dev">
    <img src="https://raw.githubusercontent.com/Pollora/.github/main/brand/banners/cli.png" width="100%" alt="Pollora CLI: create and manage Pollora projects">
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pollora/cli"><img src="https://img.shields.io/packagist/v/pollora/cli" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/pollora/cli"><img src="https://img.shields.io/packagist/dt/pollora/cli" alt="Total Downloads"></a>
  <a href="https://github.com/Pollora/cli/actions/workflows/ci.yml"><img src="https://github.com/Pollora/cli/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="LICENSE"><img src="https://img.shields.io/github/license/Pollora/cli" alt="License"></a>
</p>

The Pollora CLI creates [Pollora](https://pollora.dev) projects, the Laravel framework for WordPress, in one command: `pollora new my-site` installs the skeleton, writes the `.env`, sets up WordPress and, if you want, a DDEV environment. Inside a project, `pollora <command>` runs the matching `php artisan pollora:<command>`, in the DDEV container when there is one.

The full documentation, including the [installation guide](https://pollora.dev/getting-started/installation/), lives at **[pollora.dev](https://pollora.dev)**.

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
1. Run `composer create-project pollora/pollora` (without the skeleton's post-install scripts)
2. Create the `.env` file and its application key
3. Execute `php artisan pollora:env:setup` to configure the site URL and database
4. Execute `php artisan pollora:install` for WordPress setup
5. Optionally initialize a Git repository

By default the CLI asks Composer for the newest skeleton release, pre-releases included (`--stability=beta`). The framework's current line is stable, so this installs the latest stable release; `--stable` guarantees you never get a pre-release, and `--ver` pins an exact version or constraint:

```bash
pollora new my-site --stable
pollora new my-site --ver 13.35.0
```

### With DDEV (recommended)

```bash
pollora new my-site --ddev
```

When the `--ddev` flag is passed (or selected interactively), the CLI will:
1. Configure DDEV (WordPress, PHP 8.4, MariaDB 10.11)
2. Start the DDEV environment
3. Install the project via `ddev composer create-project`
4. Run `pollora:env:setup` and `pollora:install` inside the container

Your site will be available at `https://my-site.ddev.site`.

### Options

| Option | Description |
|---|---|
| `--ddev` | Set up the project with DDEV |
| `--force`, `-f` | Force install even if the directory already exists |
| `--git` | Initialize a Git repository |
| `--branch=NAME` | Branch name for the new repository (default: `main`) |
| `--ver=VERSION` | Install a specific version or constraint (e.g. `13.35.0`, `^13.35`) |
| `--stable` | Install the latest stable release only, never a pre-release |

## Using Pollora commands

When you run `pollora` inside a directory that contains both `artisan` and `vendor/pollora/framework`, the CLI acts as a **proxy** and delegates commands to `php artisan pollora:{command}`:

```bash
cd my-site

pollora status               # => php artisan pollora:status
pollora make:plugin Foo      # => php artisan pollora:make:plugin Foo
pollora make:theme starter   # => php artisan pollora:make:theme starter
pollora doctor               # => php artisan pollora:doctor
```

In a project with a `.ddev` directory, the same commands run in the container (`ddev exec php artisan pollora:{command}`). `new`, `version`, `self-update`, `list`, `help` and `completion` always stay with the CLI.

## Updating

The CLI checks for updates automatically (once every 24 hours) and displays a notification when a new version is available:

```
  A new version of Pollora CLI is available: v0.3.0 (current: v0.2.0)
  Run pollora self-update to update.
```

To update manually:

```bash
pollora self-update
```

This runs `composer global update pollora/cli` under the hood. You can also use the alias `pollora self:update`.

## Other commands

| Command | Description |
|---|---|
| `pollora version` | Display the CLI version |
| `pollora self-update` | Update the CLI to the latest version (alias: `self:update`) |

## Requirements

- PHP 8.2+ for the CLI itself (the project it creates needs PHP 8.4+)
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

## Contributing

Contributions are welcome: see the [contributing guide](https://github.com/Pollora/.github/blob/main/CONTRIBUTING.md). Report security issues privately, as described in the [security policy](https://github.com/Pollora/.github/blob/main/SECURITY.md).

## License

Pollora CLI is open-source software licensed under the [MIT license](LICENSE). © [RuBee group](https://rubee.group)
