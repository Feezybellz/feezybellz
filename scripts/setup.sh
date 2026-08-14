#!/usr/bin/env bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Print helper functions
print_info() { echo -e "${CYAN}[*] $1${NC}"; }
print_success() { echo -e "${GREEN}[+] $1${NC}"; }
print_warning() { echo -e "${YELLOW}[!] $1${NC}"; }
print_error() { echo -e "${RED}[x] $1${NC}"; }

# Check for root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root. Please run with sudo." 
   exit 1
fi

setup_php() {
    print_info "Starting PHP & PHP-FPM Setup..."
    
    # Ask for PHP version
    read -p "Enter desired PHP version (e.g., 8.1, 8.2, 8.3) [default: 8.2]: " PHP_VERSION
    PHP_VERSION=${PHP_VERSION:-8.2}
    
    # Check if installed
    if ! command -v php${PHP_VERSION} &> /dev/null; then
        print_info "PHP ${PHP_VERSION} not found. Installing..."
        
        # Add ondrej/php PPA if on Ubuntu/Debian
        if command -v apt-get &> /dev/null; then
            apt-get update
            apt-get install -y software-properties-common ca-certificates lsb-release apt-transport-https
            LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php
            apt-get update
            
            # Install PHP and extensions
            apt-get install -y php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common php${PHP_VERSION}-mysql \
                               php${PHP_VERSION}-zip php${PHP_VERSION}-gd php${PHP_VERSION}-mbstring php${PHP_VERSION}-curl \
                               php${PHP_VERSION}-xml php${PHP_VERSION}-bcmath
        else
            print_error "Package manager not supported by this script (apt required)."
            exit 1
        fi
    else
        print_success "PHP ${PHP_VERSION} is already installed."
    fi

    # Configure PHP-FPM Pool
    WWW_CONF="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
    
    if [ -f "$WWW_CONF" ]; then
        read -p "Enter the PHP-FPM listen address/port [default: 127.0.0.1:9000]: " FPM_LISTEN
        FPM_LISTEN=${FPM_LISTEN:-127.0.0.1:9000}
        
        print_info "Configuring PHP-FPM to listen on ${FPM_LISTEN}..."
        
        # Backup original config
        cp $WWW_CONF "${WWW_CONF}.bak"
        
        # Use sed to replace the listen directive
        sed -i "s|^listen = .*|listen = ${FPM_LISTEN}|g" $WWW_CONF
        
        # Restart FPM service
        systemctl restart php${PHP_VERSION}-fpm
        systemctl enable php${PHP_VERSION}-fpm
        
        print_success "PHP-FPM configured and restarted."
    else
        print_error "Could not find PHP-FPM configuration file at ${WWW_CONF}."
    fi
}

setup_nginx() {
    print_info "Starting Nginx Setup..."
    
    if ! command -v nginx &> /dev/null; then
        read -p "Nginx is not installed. Install it now? (y/n) [default: y]: " INSTALL_NGINX
        INSTALL_NGINX=${INSTALL_NGINX:-y}
        
        if [[ "$INSTALL_NGINX" =~ ^[Yy]$ ]]; then
            print_info "Installing Nginx..."
            apt-get update
            apt-get install -y nginx
        else
            print_warning "Skipping Nginx installation."
            return
        fi
    else
        print_success "Nginx is already installed."
    fi
    
    systemctl enable nginx
    systemctl start nginx
    print_success "Nginx service is running."
}

setup_domain() {
    print_info "Starting Domain & Server Block Setup..."
    
    read -p "Enter the root domain name (e.g., example.com): " DOMAIN_NAME
    if [[ -z "$DOMAIN_NAME" ]]; then
        print_error "Domain name cannot be empty."
        return
    fi
    
    read -p "Enter the absolute path to the application public directory [default: /var/www/html/feezybellz/public]: " APP_ROOT
    APP_ROOT=${APP_ROOT:-/var/www/html/feezybellz/public}
    
    read -p "Enter the PHP-FPM backend address [default: 127.0.0.1:9000]: " FPM_BACKEND
    FPM_BACKEND=${FPM_BACKEND:-127.0.0.1:9000}
    
    echo "WWW Redirection Preference:"
    echo "1) No redirect (serve both www and non-www)"
    echo "2) Redirect www to non-www (e.g. www.domain.com -> domain.com)"
    echo "3) Redirect non-www to www (e.g. domain.com -> www.domain.com)"
    read -p "Select redirection [1-3, default: 1]: " REDIRECT_OPT
    REDIRECT_OPT=${REDIRECT_OPT:-1}

    CONF_FILE="/etc/nginx/sites-available/${DOMAIN_NAME}"
    
    print_info "Creating Nginx configuration for ${DOMAIN_NAME}..."
    
    # Initialize main server block vars
    MAIN_SERVER_NAME="${DOMAIN_NAME} www.${DOMAIN_NAME}"
    REDIRECT_BLOCK=""

    if [ "$REDIRECT_OPT" == "2" ]; then
        MAIN_SERVER_NAME="${DOMAIN_NAME}"
        REDIRECT_BLOCK="
server {
    listen 80;
    listen [::]:80;
    server_name www.${DOMAIN_NAME};
    return 301 \$scheme://${DOMAIN_NAME}\$request_uri;
}"
    elif [ "$REDIRECT_OPT" == "3" ]; then
        MAIN_SERVER_NAME="www.${DOMAIN_NAME}"
        REDIRECT_BLOCK="
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN_NAME};
    return 301 \$scheme://www.${DOMAIN_NAME}\$request_uri;
}"
    fi

    cat > "$CONF_FILE" <<EOF
${REDIRECT_BLOCK}

server {
    listen 80;
    listen [::]:80;
    server_name ${MAIN_SERVER_NAME};
    root ${APP_ROOT};

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php index.html index.htm;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass ${FPM_BACKEND};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    print_success "Configuration created at ${CONF_FILE}"
    
    # Enable site
    ln -sf "$CONF_FILE" "/etc/nginx/sites-enabled/"
    
    # Test Nginx
    if nginx -t; then
        print_info "Reloading Nginx..."
        systemctl reload nginx
        print_success "Domain ${DOMAIN_NAME} is successfully configured in Nginx!"
    else
        print_error "Nginx configuration test failed. Please check your inputs."
    fi
    
    # SSL Setup via Certbot
    read -p "Do you want to configure SSL using Certbot now? (y/n) [default: y]: " RUN_SSL
    RUN_SSL=${RUN_SSL:-y}
    
    if [[ "$RUN_SSL" =~ ^[Yy]$ ]]; then
        if ! command -v certbot &> /dev/null; then
            read -p "Certbot is not installed. Install it now? (y/n) [default: y]: " INSTALL_CERTBOT
            INSTALL_CERTBOT=${INSTALL_CERTBOT:-y}
            if [[ "$INSTALL_CERTBOT" =~ ^[Yy]$ ]]; then
                print_info "Installing Certbot..."
                apt-get update
                apt-get install -y certbot python3-certbot-nginx
            else
                print_warning "Skipping SSL setup because Certbot is not installed."
            fi
        fi
        
        if command -v certbot &> /dev/null; then
            print_info "Running Certbot for ${DOMAIN_NAME} and www.${DOMAIN_NAME}..."
            certbot --nginx -d "${DOMAIN_NAME}" -d "www.${DOMAIN_NAME}"
            print_success "SSL setup completed!"
        fi
    fi

    # Set permissions
    if [ -d "$APP_ROOT" ]; then
        print_info "Setting correct permissions for ${APP_ROOT}..."
        # Extract the parent directory of 'public'
        PARENT_DIR=$(dirname "$APP_ROOT")
        chown -R www-data:www-data "$PARENT_DIR"
        chmod -R 755 "$PARENT_DIR"
        print_success "Permissions updated."
    else
        print_warning "Directory ${APP_ROOT} does not exist yet. Please make sure to create it or deploy your code there."
    fi
}

# Main Menu
show_menu() {
    echo ""
    echo -e "${CYAN}==============================================${NC}"
    echo -e "${CYAN}   Interactive Web Server Deployment Script   ${NC}"
    echo -e "${CYAN}==============================================${NC}"
    echo "1) Install & Configure PHP / PHP-FPM"
    echo "2) Install & Configure Nginx"
    echo "3) Setup Domain & Server Block"
    echo "4) Run All Steps (Full Setup)"
    echo "q) Quit"
    echo ""
    read -p "Select an option [1-4, q]: " choice
    
    case $choice in
        1)
            setup_php
            show_menu
            ;;
        2)
            setup_nginx
            show_menu
            ;;
        3)
            setup_domain
            show_menu
            ;;
        4)
            setup_php
            setup_nginx
            setup_domain
            print_success "All setups completed!"
            ;;
        q|Q)
            echo "Exiting."
            exit 0
            ;;
        *)
            print_error "Invalid option selected."
            show_menu
            ;;
    esac
}

show_menu
