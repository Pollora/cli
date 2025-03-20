#!/usr/bin/env bash

# Get project name from args
project_name="${args[project_name]}"

# Get flags
site_name="${args[--site-name]}"
docroot="${args[--docroot]}"
setup_rewrite=true

# Use project name as site name if not provided
if [[ -z "$site_name" ]]; then
  site_name="$project_name"
fi

# Check if --no-rewrite flag is set
if [[ -n "${args[--no-rewrite]}" ]]; then
  setup_rewrite=false
fi

log_info "Creating new Pollora project: ${project_name}"

# Step 1: Create project directory
log_info "Creating project directory..."
mkdir -p "$project_name"
cd "$project_name"

# Step 2: Configure DDEV
log_info "Configuring DDEV..."
ddev config --projecttype wordpress --disable-settings-management --sitename "$site_name" --docroot "$docroot"
# Step 3: Start DDEV
log_info "Starting DDEV..."
ddev start

# Step 4: Create Pollora project using Composer
log_info "Creating Pollora project..."
ddev exec composer create-project pollora/pollora:dev-main temp --stability dev --no-interaction

# Step 5: Move files from temp directory (including hidden files)
log_info "Moving project files..."
ddev exec rm -rf public
ddev exec bash -c "mv temp/* . 2>/dev/null || true"
ddev exec bash -c "mv temp/.[!.]* . 2>/dev/null || true"  # Pour les fichiers cachés
ddev exec rm -rf temp

# Step 6: Set up WordPress environment
log_info "Setting up WordPress environment..."
ddev exec php artisan wp:env-setup

# Step 7: Install WordPress
log_info "Installing WordPress..."
ddev exec php artisan wp:install

# Step 8: Configure rewrite rules if enabled
if [ "$setup_rewrite" = true ]; then
  log_info "Setting up WordPress permalink structure..."
  ddev exec wp rewrite structure '/%postname%'
fi
