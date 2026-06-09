# ChessMate

## Run with Docker

Build and start the Symfony application and MariaDB:

```bash
docker compose up --build
```

Open <http://localhost:8081>. The first startup waits for MariaDB and creates
the Doctrine schema automatically. The project directory is bind-mounted into
the application container, so local source changes are visible immediately.
Development fixtures are loaded automatically when the user table is empty.

Use a different host port when `8081` is occupied:

```bash
APP_PORT=8082 docker compose up --build
```

Run Symfony console commands inside the application container:

```bash
docker compose exec app php bin/console
```

Load the bundled development fixtures:

```bash
docker compose exec app php bin/console doctrine:fixtures:load --no-interaction
```

Stop the stack with `docker compose down`. To also remove the database volume,
run `docker compose down --volumes`.
