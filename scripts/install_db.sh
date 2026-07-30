#!/bin/bash

echo "=========================================="
echo "    Database Installation & Setup Script"
echo "=========================================="
echo ""

# --- OS and Package Manager Detection ---
PKG_MANAGER=""
INSTALL_CMD=""
UPDATE_CMD=""

if command -v apt-get &> /dev/null; then
    PKG_MANAGER="apt"
    INSTALL_CMD="sudo apt-get install -y"
    UPDATE_CMD="sudo apt-get update"
elif command -v dnf &> /dev/null; then
    PKG_MANAGER="dnf"
    INSTALL_CMD="sudo dnf install -y"
    UPDATE_CMD="sudo dnf check-update || true"
elif command -v yum &> /dev/null; then
    PKG_MANAGER="yum"
    INSTALL_CMD="sudo yum install -y"
    UPDATE_CMD="sudo yum check-update || true"
elif command -v pacman &> /dev/null; then
    PKG_MANAGER="pacman"
    INSTALL_CMD="sudo pacman -S --noconfirm"
    UPDATE_CMD="sudo pacman -Sy"
elif command -v brew &> /dev/null; then
    PKG_MANAGER="brew"
    INSTALL_CMD="brew install"
    UPDATE_CMD="brew update"
else
    echo "No supported package manager found (apt, dnf, yum, pacman, brew)."
    echo "You may need to install the database manually."
    exit 1
fi

echo "Detected package manager: $PKG_MANAGER"

# Does apt have a real installation candidate for this package?
#
# On Debian, `mysql-server` is referred to by other packages but has no candidate of
# its own — apt reports "Candidate: (none)". Asking first means we can fall through to
# the package that does exist instead of failing the whole run.
#
# Fails open: only an explicit "(none)" counts as unavailable. If apt-cache is missing
# or says nothing we can read, the package is still worth attempting — a failed install
# attempt is recoverable, whereas skipping every candidate leaves nothing installed.
apt_has_candidate() {
    local pkg=$1
    local candidate

    candidate=$(apt-cache policy "$pkg" 2>/dev/null | awk -F': ' '/Candidate:/ {print $2; exit}')

    [ "$candidate" != "(none)" ]
}

install_pkg() {
    local apt_pkg=$1
    local yum_pkg=$2
    local pacman_pkg=$3
    local brew_pkg=$4

    echo "Updating package list..."
    eval "$UPDATE_CMD" > /dev/null 2>&1

    echo "Installing packages..."
    local success=0
    if [ "$PKG_MANAGER" == "apt" ]; then
        # apt_pkg may be a '|'-separated list of equivalent candidates, most preferred
        # first — see the MySQL/MariaDB note below. The first one apt can actually
        # install wins.
        local candidates pkg
        IFS='|' read -ra candidates <<< "$apt_pkg"

        for pkg in "${candidates[@]}"; do
            if [ ${#candidates[@]} -gt 1 ] && ! apt_has_candidate "$pkg"; then
                echo "  '$pkg' is not available in this distribution's repositories, trying the next option..."
                continue
            fi

            # Keep the package's own prompts (root password, config file diffs) from
            # hanging an otherwise unattended run. sudo takes VAR=value before the
            # command, which is why this spells out apt-get rather than reusing
            # INSTALL_CMD (which already carries its own sudo).
            if sudo DEBIAN_FRONTEND=noninteractive apt-get install -y $pkg; then
                echo "  installed '$pkg'."
                success=1
                break
            fi

            echo "  '$pkg' failed to install, trying the next option..."
        done
    elif [[ "$PKG_MANAGER" == "dnf" || "$PKG_MANAGER" == "yum" ]]; then
        eval "$INSTALL_CMD $yum_pkg" && success=1
    elif [ "$PKG_MANAGER" == "pacman" ]; then
        eval "$INSTALL_CMD $pacman_pkg" && success=1
    elif [ "$PKG_MANAGER" == "brew" ]; then
        eval "$INSTALL_CMD $brew_pkg" && success=1
    fi

    if [ $success -eq 0 ]; then
        echo "Error: Failed to install packages. Please check your internet connection, package manager, or the specified version."
        exit 1
    fi
}

restart_service() {
    local service_name=$1
    local alt_service=$2
    
    if command -v systemctl &> /dev/null; then
        sudo systemctl restart "$service_name" || sudo systemctl restart "$alt_service"
    elif command -v brew &> /dev/null; then
        brew services restart "$service_name" || brew services restart "$alt_service"
    elif command -v service &> /dev/null; then
        sudo service "$service_name" restart || sudo service "$alt_service" restart
    else
        echo "Could not detect service manager. Please restart $service_name manually."
    fi
}

mysql_exec() {
    if command -v mariadb &> /dev/null; then
        sudo mariadb -e "$1"
    else
        sudo mysql -e "$1"
    fi
}

# Same, but quiet and returning only raw rows — for reading state rather than changing it.
mysql_query() {
    if command -v mariadb &> /dev/null; then
        sudo mariadb -N -B -e "$1" 2>/dev/null
    else
        sudo mysql -N -B -e "$1" 2>/dev/null
    fi
}

# The client binary an ordinary (non-root) user would connect with.
mysql_client() {
    if command -v mariadb &> /dev/null; then echo "mariadb"; else echo "mysql"; fi
}

# Escape a value for use inside single quotes in SQL.
#
# Backslash first, then the quote — the other order would double-escape. Without this, a
# password containing an apostrophe silently truncates the SQL statement, and one
# containing a backslash gets stored as something other than what was typed. Either way
# the stored password stops matching the one in .env and every later connection is
# denied, with nothing to indicate why.
sql_escape() {
    local v=$1
    v=${v//\\/\\\\}
    v=${v//\'/\\\'}
    printf '%s' "$v"
}

# --- Prompt User ---
echo "Which database would you like to install?"
echo "1) MySQL / MariaDB"
echo "2) PostgreSQL"
echo "3) MongoDB"
echo "4) SQLite"
read -p "Select an option (1-4): " DB_CHOICE

case $DB_CHOICE in
    1) DB_TYPE="MySQL" ;;
    2) DB_TYPE="PostgreSQL" ;;
    3) DB_TYPE="MongoDB" ;;
    4) DB_TYPE="SQLite" ;;
    *) echo "Invalid choice."; exit 1 ;;
esac

echo "You selected $DB_TYPE."
echo ""

read -p "Enter version to install (leave blank for latest): " DB_VERSION
echo ""

if [ "$DB_TYPE" == "SQLite" ]; then
    read -p "Enter the path for the database file (default: ./database.sqlite): " SQLITE_FILE
    SQLITE_FILE=${SQLITE_FILE:-./database.sqlite}
    echo "Installing SQLite..."
    
    if [ -n "$DB_VERSION" ] && [ "$PKG_MANAGER" == "apt" ]; then
        install_pkg "sqlite3=${DB_VERSION}*" "sqlite" "sqlite" "sqlite"
    else
        install_pkg "sqlite3" "sqlite" "sqlite" "sqlite"
    fi
    
    # Create the directory if it doesn't exist
    SQLITE_DIR=$(dirname "$SQLITE_FILE")
    mkdir -p "$SQLITE_DIR"
    
    touch "$SQLITE_FILE"
    echo "SQLite installed and database file '$SQLITE_FILE' created."
    exit 0
fi

read -p "Do you want to proceed with setting up an admin user, password, and database? (y/n): " CONSENT
if [[ "$CONSENT" != "y" && "$CONSENT" != "Y" ]]; then
    echo "Setup aborted by user."
    exit 0
fi

read -p "Enter Database Name: " DB_NAME
read -p "Enter Admin Username: " DB_USER
read -s -p "Enter Admin Password: " DB_PASS
echo ""

# Which hosts the grant covers.
#
# This is not cosmetic. A user granted only on '%' or only on a remote IP is genuinely
# not permitted to connect from this machine, and the server reports that as
# "Access denied for user 'x'@'localhost'" — which reads like a wrong password and sends
# people hunting for the wrong problem entirely.
DB_USER_HOST="localhost"

if [ "$DB_TYPE" == "MySQL" ]; then
    echo ""
    echo "Which host should this user be allowed to connect from?"
    echo "  1) localhost — the app runs on this same server (recommended)"
    echo "  2) any host (%) — for connections from other machines"
    echo "  3) a specific IP or hostname"
    read -p "Select an option (1-3) [1]: " HOST_CHOICE
    HOST_CHOICE=${HOST_CHOICE:-1}

    case $HOST_CHOICE in
        1) DB_USER_HOST="localhost" ;;
        2) DB_USER_HOST="%" ;;
        3)
            read -p "Enter the IP or hostname: " DB_USER_HOST
            if [ -z "$DB_USER_HOST" ]; then
                echo "No host given; falling back to localhost."
                DB_USER_HOST="localhost"
            fi
            ;;
        *) echo "Unrecognised choice; using localhost."; DB_USER_HOST="localhost" ;;
    esac

    echo "User will be created as '${DB_USER}'@'${DB_USER_HOST}'."

    # A grant that excludes this machine while the app runs on it is the single most
    # likely way to end up locked out, so say so now rather than after the fact.
    if [ "$DB_USER_HOST" != "localhost" ] && [ "$DB_USER_HOST" != "%" ]; then
        echo "Note: the app on THIS server will not be able to connect with that user."
        echo "      If the app runs here too, choose localhost, or re-run for a second grant."
    fi
fi

ADVANCED_SETTINGS="n"
read -p "Do you want to configure advanced settings (e.g. expose to specific IP/everyone, custom port)? (y/n): " ADVANCED_SETTINGS

BIND_IP="127.0.0.1"
CUSTOM_PORT=""

if [[ "$ADVANCED_SETTINGS" == "y" || "$ADVANCED_SETTINGS" == "Y" ]]; then
    echo "--- Advanced Settings ---"
    read -p "Bind Address / Expose to IP (e.g. 0.0.0.0 for everyone, or 127.0.0.1 for local): " BIND_IP
    read -p "Custom Port (leave blank for default): " CUSTOM_PORT
fi

echo ""
echo "Starting installation for $DB_TYPE..."

if [ "$DB_TYPE" == "MySQL" ]; then
    # Two apt candidates, most preferred first.
    #
    # Ubuntu carries Oracle's mysql-server. Debian does not — there it exists only as a
    # name other packages refer to, so apt says "Package 'mysql-server' has no
    # installation candidate" and the run dies. Debian's own build is mariadb-server.
    #
    # Falling back rather than branching on distro ID is deliberate: derivatives
    # misreport their ID constantly, and "whatever apt can actually install" is the
    # question we care about. MariaDB is a drop-in here — the app talks to it through
    # PDO's mysql driver, and the rest of this script already assumes MariaDB on every
    # other package manager.
    apt_pkg="mysql-server|mariadb-server"
    yum_pkg="mariadb-server"
    pacman_pkg="mariadb"
    brew_pkg="mysql"

    if [ -n "$DB_VERSION" ]; then
        apt_pkg="mysql-server-${DB_VERSION}|mariadb-server-${DB_VERSION}"
        pacman_pkg="mariadb${DB_VERSION}"
        brew_pkg="mysql@${DB_VERSION}"
    fi

    install_pkg "$apt_pkg" "$yum_pkg" "$pacman_pkg" "$brew_pkg"

    # Init DB on yum/dnf/pacman
    if [[ "$PKG_MANAGER" == "dnf" || "$PKG_MANAGER" == "yum" || "$PKG_MANAGER" == "pacman" ]]; then
        if [ "$PKG_MANAGER" == "pacman" ]; then
            sudo mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql || true
        fi
        sudo systemctl enable mariadb --now || sudo systemctl enable mysqld --now || true
    fi

    # apt normally starts the server itself, but not in a container or when policy-rc.d
    # blocked it — and the CREATE DATABASE calls below need a live socket either way.
    if [ "$PKG_MANAGER" == "apt" ] && command -v systemctl &> /dev/null; then
        sudo systemctl enable mariadb --now 2>/dev/null \
            || sudo systemctl enable mysql --now 2>/dev/null \
            || sudo systemctl enable mysqld --now 2>/dev/null \
            || true
    fi

    echo "Configuring MySQL / MariaDB..."
    # Ensure service is up before queries
    sleep 3

    DB_PASS_SQL=$(sql_escape "$DB_PASS")

    mysql_exec "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql_exec "CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_USER_HOST}' IDENTIFIED BY '${DB_PASS_SQL}';"

    # Set the password explicitly after creating the user.
    #
    # CREATE USER IF NOT EXISTS does nothing at all when the user already exists — it does
    # not update the password. Re-running this script with a new password therefore
    # appeared to succeed while leaving the old password in place, and every later
    # connection was denied. ALTER USER makes the outcome match what was just typed,
    # whether the user is new or not.
    mysql_exec "ALTER USER '${DB_USER}'@'${DB_USER_HOST}' IDENTIFIED BY '${DB_PASS_SQL}';"

    mysql_exec "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_USER_HOST}';"
    mysql_exec "FLUSH PRIVILEGES;"

    # --- Warn about rows that would shadow this grant ---------------------------
    #
    # Privilege matching picks the most specific host row, not the most permissive. So
    # 'user'@'localhost' beats 'user'@'%' for local connections, and an anonymous
    # ''@'localhost' row beats a named user entirely. Either can deny a user that plainly
    # exists with the right password, which is close to impossible to diagnose blind.
    ANON_ROWS=$(mysql_query "SELECT CONCAT('''', user, '''@''', host, '''') FROM mysql.user WHERE user = '';")
    if [ -n "$ANON_ROWS" ]; then
        echo ""
        echo "Warning: this server has anonymous user row(s):"
        echo "$ANON_ROWS" | sed 's/^/  /'
        echo "  These take precedence over named users for local connections and can cause"
        echo "  'Access denied' for '${DB_USER}'. Remove them with:"
        echo "    sudo $(mysql_client) -e \"DELETE FROM mysql.user WHERE user=''; FLUSH PRIVILEGES;\""
    fi

    OTHER_HOSTS=$(mysql_query "SELECT host FROM mysql.user WHERE user = '${DB_USER}' AND host <> '${DB_USER_HOST}';")
    if [ -n "$OTHER_HOSTS" ]; then
        echo ""
        echo "Note: '${DB_USER}' also exists for host(s):"
        echo "$OTHER_HOSTS" | sed 's/^/  /'
        echo "  A more specific host row wins, so an old entry can override the one just set."
        echo "  Drop one with:  sudo $(mysql_client) -e \"DROP USER '${DB_USER}'@'<host>';\""
    fi

    # --- Prove the credentials actually work ------------------------------------
    #
    # Everything above can succeed while leaving a login that does not work. Testing it
    # here turns a silent problem into an immediate, specific one.
    if [ "$DB_USER_HOST" == "localhost" ] || [ "$DB_USER_HOST" == "%" ]; then
        echo ""
        echo "Verifying the credentials..."
        if $(mysql_client) -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -e "SELECT 1;" > /dev/null 2>&1; then
            echo "  OK — '${DB_USER}' can connect to '${DB_NAME}' from this server."
            echo ""
            echo "Put these in your .env:"
            echo "  DB_HOST=127.0.0.1"
            echo "  DB_DATABASE=${DB_NAME}"
            echo "  DB_USERNAME=${DB_USER}"
            echo "  DB_PASSWORD=<the password you just entered>"
        else
            echo "  FAILED — the user was created but cannot log in."
            echo "  Check the notes above for shadowing rows, then inspect with:"
            echo "    sudo $(mysql_client) -e \"SELECT user, host, plugin FROM mysql.user WHERE user='${DB_USER}';\""
        fi
    fi

    if [[ "$ADVANCED_SETTINGS" == "y" || "$ADVANCED_SETTINGS" == "Y" ]]; then
        echo "Applying advanced settings..."
        MYSQL_CONF="/etc/mysql/mysql.conf.d/mysqld.cnf"
        if [ ! -f "$MYSQL_CONF" ]; then MYSQL_CONF="/etc/mysql/mariadb.conf.d/50-server.cnf"; fi
        if [ ! -f "$MYSQL_CONF" ]; then MYSQL_CONF="/etc/my.cnf"; fi
        if [ ! -f "$MYSQL_CONF" ]; then MYSQL_CONF="/etc/mysql/my.cnf"; fi
        
        if [ -f "$MYSQL_CONF" ]; then
            sudo sed -i "s/^bind-address\s*=.*/bind-address = $BIND_IP/" "$MYSQL_CONF"
            if [ -n "$CUSTOM_PORT" ]; then
                sudo sed -i "s/^port\s*=.*/port = $CUSTOM_PORT/" "$MYSQL_CONF"
            fi
        else
            echo "Could not locate MySQL config file to apply advanced settings."
        fi
        restart_service "mysql" "mariadb"
    fi

elif [ "$DB_TYPE" == "PostgreSQL" ]; then
    apt_pkg="postgresql postgresql-contrib"
    yum_pkg="postgresql-server postgresql-contrib"
    pacman_pkg="postgresql"
    brew_pkg="postgresql"
    
    if [ -n "$DB_VERSION" ]; then
        apt_pkg="postgresql-${DB_VERSION} postgresql-contrib"
        yum_pkg="postgresql${DB_VERSION}-server postgresql${DB_VERSION}-contrib"
        pacman_pkg="postgresql${DB_VERSION}"
        brew_pkg="postgresql@${DB_VERSION}"
    fi
    
    install_pkg "$apt_pkg" "$yum_pkg" "$pacman_pkg" "$brew_pkg"
    
    if [[ "$PKG_MANAGER" == "dnf" || "$PKG_MANAGER" == "yum" ]]; then
        sudo postgresql-setup --initdb || true
        sudo systemctl enable postgresql --now || true
    elif [ "$PKG_MANAGER" == "pacman" ]; then
        sudo -u postgres initdb -D /var/lib/postgres/data || true
        sudo systemctl enable postgresql --now || true
    fi

    echo "Configuring PostgreSQL..."
    restart_service "postgresql" "postgres"
    sleep 3
    
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME};"
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH ENCRYPTED PASSWORD '${DB_PASS}';"
    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
    
    if [[ "$ADVANCED_SETTINGS" == "y" || "$ADVANCED_SETTINGS" == "Y" ]]; then
        echo "Applying advanced settings..."
        PG_CONF=$(sudo find /etc/postgresql /var/lib/pgsql /usr/local/var/postgres /var/lib/postgres -name postgresql.conf 2>/dev/null | head -n 1)
        PG_HBA=$(sudo find /etc/postgresql /var/lib/pgsql /usr/local/var/postgres /var/lib/postgres -name pg_hba.conf 2>/dev/null | head -n 1)
        
        if [ -n "$PG_CONF" ]; then
            sudo sed -i "s/^#listen_addresses = 'localhost'/listen_addresses = '*'/g" "$PG_CONF"
            sudo sed -i "s/^listen_addresses = .*/listen_addresses = '$BIND_IP'/g" "$PG_CONF"
            
            if [ -n "$CUSTOM_PORT" ]; then
                sudo sed -i "s/^port = .*/port = $CUSTOM_PORT/g" "$PG_CONF"
                sudo sed -i "s/^#port = .*/port = $CUSTOM_PORT/g" "$PG_CONF"
            fi
            
            if [ "$BIND_IP" == "0.0.0.0" ] || [ "$BIND_IP" == "*" ]; then
                echo "host    all             all             0.0.0.0/0               md5" | sudo tee -a "$PG_HBA"
            else
                echo "host    all             all             $BIND_IP/32             md5" | sudo tee -a "$PG_HBA"
            fi
            
            restart_service "postgresql" "postgres"
        else
            echo "Could not locate PostgreSQL config file to apply advanced settings."
        fi
    fi

elif [ "$DB_TYPE" == "MongoDB" ]; then
    apt_pkg="mongodb"
    yum_pkg="mongodb-org"
    pacman_pkg="mongodb"
    brew_pkg="mongodb-community"
    
    if [ -n "$DB_VERSION" ]; then
        apt_pkg="mongodb-org=${DB_VERSION}"
        brew_pkg="mongodb-community@${DB_VERSION}"
    fi
    
    install_pkg "$apt_pkg" "$yum_pkg" "$pacman_pkg" "$brew_pkg"
    
    if [[ "$PKG_MANAGER" == "dnf" || "$PKG_MANAGER" == "yum" || "$PKG_MANAGER" == "pacman" ]]; then
        sudo systemctl enable mongod --now || true
    fi

    echo "Configuring MongoDB..."
    restart_service "mongodb" "mongod"
    sleep 3
    
    if command -v mongosh &> /dev/null; then
        mongosh admin --eval "db.createUser({user: '${DB_USER}', pwd: '${DB_PASS}', roles: [{role: 'userAdminAnyDatabase', db: 'admin'}, 'readWriteAnyDatabase']})"
    else
        mongo admin --eval "db.createUser({user: '${DB_USER}', pwd: '${DB_PASS}', roles: [{role: 'userAdminAnyDatabase', db: 'admin'}, 'readWriteAnyDatabase']})"
    fi
    
    if [[ "$ADVANCED_SETTINGS" == "y" || "$ADVANCED_SETTINGS" == "Y" ]]; then
        echo "Applying advanced settings..."
        MONGO_CONF="/etc/mongodb.conf"
        if [ ! -f "$MONGO_CONF" ]; then MONGO_CONF="/etc/mongod.conf"; fi
        if [ ! -f "$MONGO_CONF" ]; then MONGO_CONF="/usr/local/etc/mongod.conf"; fi
        
        if [ -f "$MONGO_CONF" ]; then
            sudo sed -i "s/bindIp: .*/bindIp: $BIND_IP/" "$MONGO_CONF"
            if [ -n "$CUSTOM_PORT" ]; then
                sudo sed -i "s/port: .*/port: $CUSTOM_PORT/" "$MONGO_CONF"
            fi
            restart_service "mongodb" "mongod"
        else
            echo "Could not locate MongoDB config file to apply advanced settings."
        fi
    fi
fi

echo "=========================================="
echo " Setup Complete!"
echo " Database: $DB_TYPE"
echo " DB Name: $DB_NAME"
echo " User: $DB_USER"
if [ -n "$DB_VERSION" ]; then
    echo " Version: $DB_VERSION"
else
    echo " Version: Latest"
fi
if [[ "$ADVANCED_SETTINGS" == "y" || "$ADVANCED_SETTINGS" == "Y" ]]; then
    echo " Bind IP: $BIND_IP"
    echo " Custom Port: ${CUSTOM_PORT:-Default}"
fi
echo "=========================================="
