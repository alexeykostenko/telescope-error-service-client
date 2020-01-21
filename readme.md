## Telescope Error Service Client

#### Installation

You need to add section repositories in your composer.json, for example:

```
"repositories": {
    "telescope-error-service-client": {
        "type": "vcs",
        "url": "git@github.com:alexeykostenko/telescope-error-service-client.git"
    }
}
```

Require package:
```
composer require pdffiller/telescope-error-service-client
```

After installing package, publish its assets using the telescope-error-service-client:install Artisan command. After installing Telescope Errors, you should also run the migrate command:
```
php artisan telescope-error-service-client:install
```

#### Configuration
Add server parameters to `.env` file
```
TELESCOPE_ERROR_SERVICE_BASE_URI=http://0.0.0.0:8001/api/
TELESCOPE_ERROR_SERVICE_CLIENT_ID=1
TELESCOPE_ERROR_SERVICE_CLIENT_SECRET=secret
TELESCOPE_ENABLED=true
```
