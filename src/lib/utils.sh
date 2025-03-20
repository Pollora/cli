# Color definitions
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
  echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
  echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
  echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
  echo -e "${RED}[ERROR]${NC} $1" >&2
}

log_debug() {
  if [[ "${DEBUG:-}" == "true" ]]; then
    echo -e "[DEBUG] $1" >&2
  fi
}

# Check if we're in a Pollora project
is_pollora_project() {
  # Check for common files/directories that would indicate a Pollora project
  if [ -f "composer.json" ] && [ -f "artisan" ] && grep -q "pollora/pollora" "composer.json"; then
    return 0 # True
  else
    return 1 # False
  fi
}

# Check if ddev is already configured for this project
is_ddev_configured() {
  if [ -f ".ddev/config.yaml" ]; then
    return 0 # True
  else
    return 1 # False
  fi
}

# Get the project root directory
get_project_root() {
  local current_dir=$(pwd)
  while [[ "$current_dir" != "/" ]]; do
    if [ -f "$current_dir/composer.json" ] && [ -f "$current_dir/artisan" ] && grep -q "pollora/pollora" "$current_dir/composer.json"; then
      echo "$current_dir"
      return 0
    fi
    current_dir=$(dirname "$current_dir")
  done

  # If we're here, we couldn't find a project root
  echo ""
  return 1
}

# Get a value from ddev config
get_ddev_config_value() {
  local key=$1
  local config_file=".ddev/config.yaml"

  if [ -f "$config_file" ]; then
    grep -oP "(?<=$key: ).*" "$config_file" | tr -d '"'
  else
    echo ""
  fi
}

# Wait for a service to be ready
wait_for_service() {
  local service=$1
  local max_attempts=${2:-30}
  local wait_seconds=${3:-2}
  local attempt=1

  log_info "Waiting for $service to be ready..."

  while [ $attempt -le $max_attempts ]; do
    if ddev exec bash -c "nc -z $service 80" &>/dev/null; then
      log_info "$service is ready!"
      return 0
    fi

    log_debug "Attempt $attempt/$max_attempts: $service not ready yet, waiting..."
    sleep $wait_seconds
    ((attempt++))
  done

  log_error "Timeout waiting for $service to be ready after $(($max_attempts * $wait_seconds)) seconds"
  return 1
}

# Get list of command files from GitHub API
get_command_files() {
  log_debug "Fetching command files list..."
  local github_repo="Pollora/cli"
  local github_branch=${1:-"main"}
  local github_api="https://api.github.com/repos/${github_repo}"
  local file_list=""

  # Try using GitHub API first
  if command -v curl &> /dev/null; then
    file_list=$(curl -s "${github_api}/contents/commands?ref=${github_branch}" | grep "\"name\":" | grep "\.sh\"" | cut -d'"' -f4)
  elif command -v wget &> /dev/null; then
    file_list=$(wget -qO- "${github_api}/contents/commands?ref=${github_branch}" | grep "\"name\":" | grep "\.sh\"" | cut -d'"' -f4)
  fi

  # If GitHub API failed, fallback to a reasonable default list
  if [ -z "$file_list" ]; then
    log_warning "Could not determine command files dynamically, using default list."
    file_list="create.sh status.sh self-update.sh uninstall.sh"
  fi

  echo "$file_list"
}

# Download a file from GitHub
download_file() {
  local github_repo="Pollora/cli"
  local github_branch=${3:-"main"}
  local github_url="https://raw.githubusercontent.com/${github_repo}/${github_branch}"

  local remote_path=$1
  local local_path=$2
  local url="${github_url}/${remote_path}"

  log_debug "Downloading ${url} to ${local_path}"

  if command -v curl &> /dev/null; then
    curl -s -o "${local_path}" "${url}"
  elif command -v wget &> /dev/null; then
    wget -q -O "${local_path}" "${url}"
  else
    log_error "Neither curl nor wget found. Please install one of them and try again."
    return 1
  fi

  # Check if download was successful
  if [ ! -s "${local_path}" ]; then
    log_error "Failed to download ${remote_path}"
    return 1
  fi

  return 0
}

# Get the latest version tag from GitHub
get_latest_version() {
  local github_repo="Pollora/cli"
  local github_api="https://api.github.com/repos/${github_repo}"
  local latest_tag=""

  # Try to get the latest release via GitHub API
  if command -v curl &> /dev/null; then
    latest_tag=$(curl -s "${github_api}/releases/latest" | grep -o '"tag_name": *"[^"]*"' | grep -o 'v[0-9][^"]*' || echo "")
  elif command -v wget &> /dev/null; then
    latest_tag=$(wget -qO- "${github_api}/releases/latest" | grep -o '"tag_name": *"[^"]*"' | grep -o 'v[0-9][^"]*' || echo "")
  fi

  # If no releases or API rate limited, try to get the latest tag
  if [ -z "$latest_tag" ]; then
    if command -v git &> /dev/null; then
      log_info "Falling back to git to get latest version..."
      latest_tag=$(git ls-remote --tags --refs "https://github.com/${github_repo}.git" | sort -Vk2 | tail -n1 | awk '{print $2}' | sed 's|refs/tags/||')
    fi
  fi

  # Extract version number without 'v' prefix and return it
  echo "${latest_tag#v}"
}
