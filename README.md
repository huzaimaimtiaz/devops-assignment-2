# ATC Flight Strip Console

CSC418 DevOps for Cloud Computing, Assignment # 2
Huzaima Imtiaz, FA23-BDS-017, BDS-7

A PHP web application backed by a MySQL database, deployed on AWS EC2 in two
containerized ways: directly with docker compose (Part I) and through a
Jenkins pipeline triggered by a GitHub webhook (Part II).

## Application

| | |
|---|---|
| Title | ATC Flight Strip Console |
| Web server | Apache with PHP 8.2 (`php:8.2-apache`) |
| Database | MySQL 8.0 |
| Pages | `index.php` controller sign in, `home.php` strip board, `logout.php` |

Air traffic controllers sign in at `index.php`, which authenticates against
the `controllers` table. `home.php` shows the controller's registration
number, the live flight strip board read from the `flight_strips` table, and a
form to file a new strip, which proves the database is writable and that the
attached volume keeps the data. Both pages render a database status panel, so
the integration is visible without signing in.

All traffic data in the seed file is fictional.

Demo positions: `huzaima` / `Fa23Bds017` and `qasim` / `Devops2026`.

## Database

| | |
|---|---|
| Database name | `atcdb` |
| Tables | `controllers`, `flight_strips` |
| Application user | `atcapp` / `Atcapp_2026` |
| Read only user | `readonly` / `Readonly_2026` |

Schema and seed data live in `db/init.sql`, which the MySQL entrypoint runs
the first time the data volume is empty.

## Part I, containerized deployment

Files: `Dockerfile`, `docker-compose.yml`

* The image is built from the Dockerfile with the application code copied in,
  and pushed to Docker Hub as `huzaimaimtiaz/atc-web:v1`.
* `docker-compose.yml` launches the web and database containers.
* The database container has the named volume `atc_db_data` attached at
  `/var/lib/mysql`, so data survives `docker compose down` and `up`.
* Host ports differ from container ports.

| Service | Container name | Container port | Host port |
|---|---|---|---|
| web | `atc-web` | 80 | 8100 |
| db | `atc-db` | 3306 | 3307 |

```bash
docker build -t huzaimaimtiaz/atc-web:v1 .
docker push huzaimaimtiaz/atc-web:v1
docker compose up -d
```

Application: `http://<EC2_PUBLIC_IP>:8100/index.php`
Database: `mysql -h <EC2_PUBLIC_IP> -P 3307 -u readonly -p atcdb`

## Part II, containerized automation pipeline

Files: `docker-compose-pipeline.yml`, `Jenkinsfile`

Changes from Part I, as required by the assignment:

* No build step and no Dockerfile. The application code is attached as a
  volume, so the container serves whatever the pipeline just fetched from
  GitHub.
* Different host ports and different container names.
* Its own compose project name and its own data volume, so the Part I
  deployment is never touched by the pipeline.

| Service | Container name | Container port | Host port |
|---|---|---|---|
| web-ci | `atc-web-ci` | 80 | 8200 |
| db-ci | `atc-db-ci` | 3306 | 3308 |

Pipeline stages in `Jenkinsfile`:

1. **Checkout from GitHub** with the Git plugin, reading the commit author so
   the notification can go back to whoever pushed.
2. **Build inside a container** with the Docker Pipeline plugin: the PHP
   syntax check runs in a `php:8.2-cli` container, so no PHP is installed on
   the EC2 instance itself.
3. **Deploy with docker compose**: the fetched code is published to
   `/srv/ci/assignment-2`, which the web container mounts as a volume, then
   the stack is recreated.
4. **Verify deployment**: polls `http://localhost:8200/index.php` until it
   returns HTTP 200 with the database connected.
5. **Post build**: emails the result, with the console log attached, to the
   author of the push and to the collaborator.

This deployment is left down. A push to the repository brings it up.

## Ports to open in the EC2 security group

| Port | Purpose |
|---|---|
| 22 | SSH |
| 8080 | Jenkins |
| 8100 | Part I web |
| 3307 | Part I MySQL, for read only verification |
| 8200 | Part II web |
| 3308 | Part II MySQL |
