## Start development server

    docker compose up

- check the server for a pulse by visiting `http://[::1]:80/naf/api/ping`
- any of the other endpoints in the [Readme](./Readme.md) can now be run against the base path `http://[::1]:80/naf/api/`

## Running tests
Get project dependencies. This requires that composer is installed on your machine

    composer update

The local project root directory is mounted inside the webserver docker image so the tests can be run as follows:

    docker exec -it naf-api-webserver-1 ./vendor/bin/phpunit --testdox ./tests/<path/to/test/file/or/dir>