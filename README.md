# lh-api
API endpoints for the LH-Höjden webpage

## Server requirements

The database connection uses the time zone `Europe/Stockholm`, so MariaDB's time zone tables must be loaded
(once per database server, and again after tzdata updates), otherwise every connection fails with error 1298:

```bash
sudo mariadb-tzinfo-to-sql /usr/share/zoneinfo | sudo mariadb mysql
```
