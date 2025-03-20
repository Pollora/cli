#!/usr/bin/env bash

# Parse options from args
force="${args[--force]}"
check_only="${args[--check-only]}"
specific_version="${args[--version]}"
debug="${args[--debug]}"

# Enable debug mode if requested
if [[ -n "$debug" ]]; then
  DEBUG=true
fi

# Get the installation directory
install_dir=$(dirname "$(which pollora 2>/dev/null)")
if [ -z "$install_dir" ] || [ ! -f "$install_dir/pollora" ]; then
  install_dir=$(dirname "$(readlink -f "$0")")
fi

log_info "Current installation directory: $install_dir"

# Get current version
current_version=$(pollora --version | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
log_info "Current version: ${current_version:-unknown}"

# Get latest version from GitHub
log_info "Checking for updates..."
latest_tag=""
latest_version=""

if [ -n "$specific_version" ]; then
  latest_tag="$specific_version"
  # Remove 'v' prefix if present
  latest_version="${specific_version#v}"
  log_info "Targeting specific version: $specific_version"
else
  # Get latest release info from GitHub API
  if command -v curl &> /dev/null; then
    release_info=$(curl -s "https://api.github.com/repos/Pollora/cli/releases/latest")
  elif command -v wget &> /dev/null; then
    release_info=$(wget -q -O - "https://api.github.com/repos/Pollora/cli/releases/latest")
  else
    log_error "Neither curl nor wget found. Cannot check for updates."
    exit 1
  fi

  # Extract tag name from release info
  latest_tag=$(echo "$release_info" | grep '"tag_name":' | sed -E 's/.*"tag_name": "([^"]+)".*/\1/')
  latest_version="${latest_tag#v}"
fi

if [ -z "$latest_version" ]; then
  log_error "Failed to determine latest version. Check your internet connection or try again later."
  exit 1
fi

log_info "Latest version available: $latest_version"

# Compare versions
if [ "$current_version" = "$latest_version" ] && [ -z "$force" ]; then
  log_success "You already have the latest version installed!"
  exit 0
fi

# If check-only flag is set, just return
if [ -n "$check_only" ]; then
  if [ "$current_version" != "$latest_version" ]; then
    log_info "A new version ($latest_version) is available. Run 'pollora self-update' to update."
  fi
  exit 0
fi

# Confirm update
if [ -z "$force" ]; then
  echo -n "Do you want to update to version $latest_version? [y/N] "
  read -r response
  if [[ ! "$response" =~ ^[yY]$ ]]; then
    log_info "Update canceled."
    exit 0
  fi
fi

# Create temporary directory
tmp_dir=$(mktemp -d)
trap 'rm -rf "$tmp_dir"' EXIT

log_info "Downloading version $latest_tag..."

# Download CLI binary directly from GitHub release
download_url="https://github.com/Pollora/cli/releases/download/${latest_tag}/pollora"
if command -v curl &> /dev/null; then
  curl -L -s -o "${tmp_dir}/pollora" "$download_url"
elif command -v wget &> /dev/null; then
  wget -q -O "${tmp_dir}/pollora" "$download_url"
else
  log_error "Neither curl nor wget found. Cannot download update."
  exit 1
fi

# Check if download was successful
if [ ! -s "${tmp_dir}/pollora" ]; then
  log_error "Failed to download update from GitHub releases."
  log_error "URL: $download_url"
  exit 1
fi

# Make the binary executable
chmod +x "${tmp_dir}/pollora"

# Validate the downloaded binary
if ! "${tmp_dir}/pollora" --version &>/dev/null; then
  log_error "The downloaded binary does not seem to be valid."
  exit 1
fi

# Check if we have write permissions to the install directory
if [ ! -w "$install_dir" ]; then
  log_error "You don't have write permissions to $install_dir."
  log_info "Try running with sudo: sudo pollora self-update"
  exit 1
fi

# Install the new version
log_info "Installing new version..."

# Backup current version
backup_dir="${tmp_dir}/backup"
mkdir -p "$backup_dir"
cp "${install_dir}/pollora" "$backup_dir/" 2>/dev/null || true

# Install new binary
cp "${tmp_dir}/pollora" "${install_dir}/pollora"
chmod +x "${install_dir}/pollora"

# Verify installation
new_version=$("${install_dir}/pollora" --version | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")
if [ -z "$new_version" ]; then
  log_error "Installation verification failed. Restoring backup..."
  cp "$backup_dir/pollora" "${install_dir}/pollora" 2>/dev/null || true
  exit 1
fi

log_success "Pollora CLI has been updated to version $new_version!"
log_info "Run 'pollora help' to see all available commands."
