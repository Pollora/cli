# Pollora CLI

[![GitHub stars](https://img.shields.io/github/stars/Pollora/cli.svg?style=social&label=Star&maxAge=2592000)](https://github.com/Pollora/cli/stargazers/)
[![License](https://img.shields.io/github/license/Pollora/cli.svg)](https://github.com/Pollora/cli/blob/main/LICENSE)

A command-line interface for managing Pollora framework projects.

## Features

- Create new Pollora projects with a single command
- Automatically configure DDEV, WordPress, and Pollora components
- Extensible architecture for adding new commands
- Check project status and display project information

## Installation

### Quick Installation (Recommended)

You can install Pollora CLI directly from GitHub with a single command:

#### Global Installation (requires sudo)

```bash
sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/Pollora/cli/main/install.sh)"
```

#### Local Installation (no sudo required)

```bash
INSTALL_DIR=~/bin bash -c "$(curl -fsSL https://raw.githubusercontent.com/Pollora/cli/main/install.sh)"
```

Make sure `~/bin` is in your PATH. If not, add it:

```bash
echo 'export PATH="$PATH:$HOME/bin"' >> ~/.bashrc
source ~/.bashrc
```

### Manual Installation

If you prefer to manually install:

```bash
# Clone the repository
git clone https://github.com/Pollora/cli.git
cd cli

# Make scripts executable
chmod +x pollora install.sh

# Install (global or local)
sudo ./install.sh
# OR
INSTALL_DIR=~/bin ./install.sh
```

## Usage

### Create a new project

```bash
pollora create mon-projet
```

This will:
1. Create a new directory for your project
2. Configure DDEV
3. Install Pollora via Composer
4. Set up WordPress
5. Configure permalink structure

#### Options

```bash
pollora create mon-projet --site-name=mon-site --docroot=web --no-rewrite
```

- `--site-name=NAME`: Set the DDEV site name (default: project name)
- `--docroot=FOLDER`: Set the document root folder (default: "public")
- `--no-rewrite`: Skip WordPress permalink configuration

### Check project status

```bash
pollora status
```

For more detailed information:

```bash
pollora status --detailed
```

### Get help

```bash
pollora help
```

For command-specific help:

```bash
pollora help create
```

## Extending the CLI

The CLI is designed to be easily extensible. To add a new command:

1. Create a new file in the `commands` directory, e.g., `commands/your-command.sh`
2. Follow this template:

```bash
#!/bin/bash

# Description: Description of your command
# Usage: ./pollora your-command [options]
#
# Arguments:
#   arg1    Description of argument 1
#
# Options:
#   --option1    Description of option 1

# Command implementation
command_your_command() {
    # Your code here
    log_info "Command executed successfully"
}
```

The CLI will automatically detect your new command and add it to the list of available commands.

## License

This project is licensed under the MIT License - see the LICENSE file for details.
