#!/bin/bash
set -e

# Default to 8080 if PORT is not provided
export PORT="${PORT:-8080}"

# Update Apache configuration to explicitly listen on the Railway PORT
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i "s/:[0-9]*>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Start Apache in the foreground
# forcefully remove all MPMs and enable only prefork right before starting Apache!
rm -f /etc/apache2/mods-enabled/mpm_*.load
rm -f /etc/apache2/mods-enabled/mpm_*.conf
ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

echo "Starting Apache with the following MPM modules in mods-enabled:"
ls -la /etc/apache2/mods-enabled/mpm_*

exec apache2-foreground
