#!/bin/bash

# Pollora CLI Installer
# This script installs the Pollora CLI from GitHub releases

# Set script to exit on error
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# GitHub repository information
GITHUB_REPO="Pollora/cli"
VERSION=${VERSION:-"latest"}  # Use specific version or latest

# Installation directory
DEFAULT_INSTALL_DIR="/usr/local/bin"
INSTALL_DIR=${INSTALL_DIR:-$DEFAULT_INSTALL_DIR}

# Temporary directory for downloads
TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

# Debug mode
DEBUG=${DEBUG:-false}

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
    if [ "$DEBUG" = "true" ]; then
        echo -e "[DEBUG] $1" >&2
    fi
}

# Check if curl or wget is available
check_download_tool() {
    if command -v curl &> /dev/null; then
        log_debug "Using curl for downloads"
        DOWNLOAD_CMD="curl"
    elif command -v wget &> /dev/null; then
        log_debug "Using wget for downloads"
        DOWNLOAD_CMD="wget"
    else
        log_error "Neither curl nor wget found. Please install one of them and try again."
        exit 1
    fi
}

# Download a file from GitHub releases
download_release_file() {
    local file_name=$1
    local output_path=$2
    local release_tag=$3

    local download_url=""

    if [ "$release_tag" = "latest" ]; then
        # Get latest release info
        local release_info=""
        if [ "$DOWNLOAD_CMD" = "curl" ]; then
            release_info=$(curl -s "https://api.github.com/repos/${GITHUB_REPO}/releases/latest")
        else
            release_info=$(wget -q -O - "https://api.github.com/repos/${GITHUB_REPO}/releases/latest")
        fi

        # Extract tag name from release info
        release_tag=$(echo "$release_info" | grep '"tag_name":' | sed -E 's/.*"tag_name": "([^"]+)".*/\1/')

        if [ -z "$release_tag" ]; then
            log_error "Failed to determine latest release tag."
            exit 1
        fi

        log_info "Latest release version: $release_tag"
    fi

    # Construct download URL
    download_url="https://github.com/${GITHUB_REPO}/releases/download/${release_tag}/${file_name}"

    log_debug "Downloading ${download_url} to ${output_path}"

    if [ "$DOWNLOAD_CMD" = "curl" ]; then
        curl -L -s -o "${output_path}" "${download_url}"
    else
        wget -q -O "${output_path}" "${download_url}"
    fi

    if [ ! -s "${output_path}" ]; then
        log_error "Failed to download ${file_name} from release ${release_tag}"
        exit 1
    fi
}

# Check if running with sudo
check_permissions() {
    if [ "$EUID" -ne 0 ] && [ "$INSTALL_DIR" = "$DEFAULT_INSTALL_DIR" ]; then
        log_warning "You need root privileges to install to $INSTALL_DIR"
        log_info "Please run with sudo or specify a different installation directory:"
        log_info "  sudo bash -c \"$(curl -fsSL https://raw.githubusercontent.com/Pollora/cli/main/install.sh)\""
        log_info "  or"
        log_info "  INSTALL_DIR=~/bin bash -c \"$(curl -fsSL https://raw.githubusercontent.com/Pollora/cli/main/install.sh)\""
        exit 1
    fi
}

# Create destination directory if it doesn't exist
create_dest_dir() {
    if [ ! -d "$INSTALL_DIR" ]; then
        log_info "Creating installation directory: $INSTALL_DIR"
        mkdir -p "$INSTALL_DIR"
    fi
}

# Download Pollora CLI binary
download_cli() {
    log_info "Downloading Pollora CLI from GitHub releases..."

    # Download the CLI binary
    download_release_file "pollora" "${TMP_DIR}/pollora" "$VERSION"
    chmod +x "${TMP_DIR}/pollora"

    log_success "Downloaded Pollora CLI successfully!"
}

# Install Pollora CLI
install_cli() {
    log_info "Installing Pollora CLI to $INSTALL_DIR"

    # Copy the executable
    cp "${TMP_DIR}/pollora" "${INSTALL_DIR}/pollora"
    chmod +x "${INSTALL_DIR}/pollora"

    # Get the installed version
    local installed_version=$("${INSTALL_DIR}/pollora" --version 2>/dev/null || echo "Unknown version")

    log_success "Pollora CLI (${installed_version}) has been installed successfully!"
}

# Check if the installation directory is in PATH
check_path() {
    if [[ ":$PATH:" != *":$INSTALL_DIR:"* ]]; then
        log_warning "The installation directory is not in your PATH"
        log_info "Consider adding it to your shell profile:"
        log_info "  echo 'export PATH=\"\$PATH:$INSTALL_DIR\"' >> ~/.bashrc"
        log_info "  source ~/.bashrc"
    fi
}

# Main installation process
main() {
    log_info "Starting Pollora CLI installation from GitHub..."

    check_download_tool
    check_permissions
    create_dest_dir
    download_cli
    install_cli
    check_path

    log_success "Installation completed!"
    log_info "Run 'pollora help' to get started"
}

# Run the main function
main
