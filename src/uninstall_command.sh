#!/usr/bin/env bash

# Parse options from args
force="${args[--force]}"
keep_config="${args[--keep-config]}"

# Get the installation directory
install_dir=$(dirname "$(which pollora 2>/dev/null)")
if [ -z "$install_dir" ] || [ ! -f "$install_dir/pollora" ]; then
  install_dir=$(dirname "$(readlink -f "$0")")
fi

log_info "Pollora CLI is installed at: $install_dir"

# Check if we have the necessary permissions
if [ ! -w "$install_dir" ]; then
  log_error "You don't have write permissions to $install_dir"
  log_info "Try running with sudo: sudo pollora uninstall"
  exit 1
fi

# Confirm uninstallation
if [ -z "$force" ]; then
  echo ""
  log_warning "This will uninstall Pollora CLI from your system."
  echo -n "Are you sure you want to proceed? [y/N] "
  read -r response
  if [[ ! "$response" =~ ^[yY]$ ]]; then
    log_info "Uninstallation cancelled."
    exit 0
  fi
fi

log_info "Uninstalling Pollora CLI..."

# Find config files if they exist
config_files=()
config_dirs=()

# Add here any config locations you might use in the future
if [ -d "$HOME/.pollora" ]; then
  config_dirs+=("$HOME/.pollora")
fi
if [ -f "$HOME/.pollorarc" ]; then
  config_files+=("$HOME/.pollorarc")
fi

# Remove main executable
if [ -f "$install_dir/pollora" ]; then
  log_info "Removing main executable..."
  rm -f "$install_dir/pollora"
fi

# Remove src directory
if [ -d "$install_dir/src" ]; then
  log_info "Removing command files..."
  rm -rf "$install_dir/src"
fi

# Remove lib directory
if [ -d "$install_dir/lib" ]; then
  log_info "Removing library files..."
  rm -rf "$install_dir/lib"
fi

# Handle configuration files
if [ -z "$keep_config" ] && [ ${#config_files[@]} -gt 0 -o ${#config_dirs[@]} -gt 0 ]; then
  log_info "Removing configuration files..."

  for config_file in "${config_files[@]}"; do
    if [ -f "$config_file" ]; then
      log_debug "Removing config file: $config_file"
      rm -f "$config_file"
    fi
  done

  for config_dir in "${config_dirs[@]}"; do
    if [ -d "$config_dir" ]; then
      log_debug "Removing config directory: $config_dir"
      rm -rf "$config_dir"
    fi
  done
elif [ -n "$keep_config" ] && [ ${#config_files[@]} -gt 0 -o ${#config_dirs[@]} -gt 0 ]; then
  log_info "Keeping configuration files as requested."
fi

# Check if uninstallation was successful
if [ ! -f "$install_dir/pollora" ] && [ ! -d "$install_dir/src" ] && [ ! -d "$install_dir/lib" ]; then
  log_success "Pollora CLI has been successfully uninstalled!"

  # Check if the installation directory is empty and can be removed
  if [ -z "$(ls -A "$install_dir" 2>/dev/null)" ]; then
    log_debug "Installation directory is empty."

    # Only attempt to remove the directory if it's not a standard PATH directory
    if [[ "$install_dir" != "/usr/bin" ]] &&
       [[ "$install_dir" != "/usr/local/bin" ]] &&
       [[ "$install_dir" != "/bin" ]]; then
      log_info "Removing empty installation directory..."
      rmdir "$install_dir" 2>/dev/null || true
    fi
  fi

  echo ""
  log_info "Thank you for using Pollora CLI. If you're uninstalling due to an issue,"
  log_info "please consider reporting it at: https://github.com/Pollora/cli/issues"
else
  log_error "Uninstallation may not have been completely successful."
  log_info "Please check if any files remain in: $install_dir"
fi
