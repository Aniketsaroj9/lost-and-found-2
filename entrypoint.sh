#!/bin/bash
set -e

# Default to 8080 if PORT is not provided
export PORT="${PORT:-8080}"

# Update Apache configuration with the exact port number.
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Start Apache in the foreground
exec apache2-foreground
