#!/usr/bin/env bash
# Check if --detailed flag is set
detailed="$args[--detailed]"

# Check if we're in a Pollora project
if ! is_pollora_project; then
  log_error "Not in a Pollora project. Please navigate to a Pollora project directory."
  exit 1
fi

# Get project information
project_root=$(get_project_root)
project_name=$(basename "$project_root")

# DDEV status
ddev_status=$(ddev describe --json 2>/dev/null || echo '{"status": "not running"}')
ddev_running=$(echo "$ddev_status" | grep -o '"status": "[^"]*' | cut -d'"' -f4)
site_url=""

if [ "$ddev_running" = "running" ]; then
  site_url=$(echo "$ddev_status" | grep -o '"primary_url": "[^"]*' | cut -d'"' -f4)
fi

# Display basic information
echo "Pollora Project Status"
echo "======================"
echo "Project name: $project_name"
echo "Project root: $project_root"
echo "DDEV status: $ddev_running"

if [ -n "$site_url" ]; then
  echo "Site URL: $site_url"
  echo "Admin URL: ${site_url}/wp/wp-admin"
fi

# Display detailed information if requested
if [ -n "$detailed" ]; then
  echo ""
  echo "Detailed Information"
  echo "-------------------"

  # PHP version
  if [ "$ddev_running" = "running" ]; then
    php_version=$(ddev exec php -v | head -n 1 | cut -d' ' -f2)
    echo "PHP version: $php_version"
  fi

  # WordPress version
  if [ "$ddev_running" = "running" ]; then
    wp_version=$(ddev exec wp core version 2>/dev/null || echo "Not installed")
    echo "WordPress version: $wp_version"
  fi

  # Composer dependencies
  echo "Composer dependencies:"
  if [ -f "composer.json" ]; then
    composer_deps=$(grep -A 20 "require" composer.json | grep -v "require-dev" | grep ":" | sed 's/[",]//g' | sed 's/^[ \t]*/    /')
    echo "$composer_deps"
  else
    echo "    No composer.json found"
  fi
fi
