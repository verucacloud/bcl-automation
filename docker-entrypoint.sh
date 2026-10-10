#!/bin/bash
set -e

# Generate config.local.php from environment variables if not present
if [ ! -f /var/www/html/config.local.php ]; then
    echo "Generating config.local.php from environment variables..."
    cat <<'EOF' > /var/www/html/config.local.php
<?php
return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306,
    'db_ssl'  => getenv('DB_SSL') === 'true' || getenv('DB_PORT') == '4000',
    'db_name' => getenv('DB_NAME') ?: '',
    'db_user' => getenv('DB_USER') ?: '',
    'db_password' => getenv('DB_PASSWORD') ?: '',
    'app_base_url' => getenv('APP_BASE_URL') ?: '',
    'controller_api_key' => getenv('CONTROLLER_API_KEY') ?: '',
    'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'google_redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '',
];
EOF
    chown www-data:www-data /var/www/html/config.local.php
fi

# Configure Apache port based on PORT env variable (Render sets $PORT, e.g. 10000)
LISTEN_PORT="${PORT:-80}"
sed -i "s/Listen [0-9]*/Listen ${LISTEN_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost \*:${LISTEN_PORT}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
