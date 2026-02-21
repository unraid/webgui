#!/bin/bash
set -e

echo "===================================="
echo "Unraid WebGUI Test Suite"
echo "===================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}Composer not found. Installing dependencies may fail.${NC}"
    echo "Please install composer from https://getcomposer.org/"
    echo ""
fi

# Check if bats is installed for shell script tests
BATS_AVAILABLE=false
if command -v bats &> /dev/null; then
    BATS_AVAILABLE=true
else
    echo -e "${YELLOW}Bats not found. Shell script tests will be skipped.${NC}"
    echo "Install bats-core: https://github.com/bats-core/bats-core"
    echo ""
fi

# Install PHP dependencies if needed
if [ ! -d "vendor" ]; then
    echo "Installing PHP dependencies..."
    composer install --dev
    echo ""
fi

# Run PHP unit tests
echo "Running PHP Unit Tests..."
echo "------------------------"
if vendor/bin/phpunit --testdox; then
    echo -e "${GREEN}✓ PHP tests passed${NC}"
else
    echo -e "${RED}✗ PHP tests failed${NC}"
    exit 1
fi
echo ""

# Run shell script tests if bats is available
if [ "$BATS_AVAILABLE" = true ]; then
    echo "Running Shell Script Tests..."
    echo "----------------------------"
    if bats tests/scripts/*.bats; then
        echo -e "${GREEN}✓ Shell script tests passed${NC}"
    else
        echo -e "${RED}✗ Shell script tests failed${NC}"
        exit 1
    fi
    echo ""
else
    echo -e "${YELLOW}Skipping shell script tests (bats not installed)${NC}"
    echo ""
fi

echo "===================================="
echo -e "${GREEN}All available tests passed!${NC}"
echo "===================================="