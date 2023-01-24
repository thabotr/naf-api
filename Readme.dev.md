TODO start developing on containers so the DEV setup is portable

    - get unit tests running in docker
    - get docker to serve api over ipv4 so the front-end app can be able to run against docker

TODO complete endpoint tests

## Start development server

    docker compose up

- check the server for a pulse by visiting `http://[::1]:80/naf/api/ping`
- any of the other endpoints in the [Readme](./Readme.md) will then be run against the base path `http://[::1]:80/naf/api/`

## Running tests

Download [PHPUnit](https://phpunit.de/index.html) phar and place it in project tests directory
  
Run from within tests directory:
  
    php .\phpunit-6.5.14.phar --verbose .\<path\to\test\file>.php
