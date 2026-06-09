#!/bin/sh
set -eu

if [ "${1:-}" = "apache2-foreground" ]; then
    echo "Waiting for the database..."
    attempts=0

    until php -r '
        try {
            new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    getenv("DATABASE_HOST"),
                    getenv("DATABASE_PORT"),
                    getenv("DATABASE_NAME")
                ),
                getenv("DATABASE_USER"),
                getenv("DATABASE_PASSWORD")
            );
        } catch (Throwable $exception) {
            exit(1);
        }
    '; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 30 ]; then
            echo "Database did not become available in time." >&2
            exit 1
        fi
        sleep 2
    done

    php bin/console doctrine:schema:update --force --env=prod --no-interaction

    if php -r '
        $pdo = new PDO(
            sprintf(
                "mysql:host=%s;port=%s;dbname=%s",
                getenv("DATABASE_HOST"),
                getenv("DATABASE_PORT"),
                getenv("DATABASE_NAME")
            ),
            getenv("DATABASE_USER"),
            getenv("DATABASE_PASSWORD")
        );
        exit((int) $pdo->query("SELECT COUNT(*) FROM cm_user")->fetchColumn() === 0 ? 0 : 1);
    '; then
        php bin/console doctrine:fixtures:load --env=prod --no-interaction
    fi

    php bin/console cache:clear --env=prod --no-warmup
    chown -R www-data:www-data var
fi

exec "$@"
