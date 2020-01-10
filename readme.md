## Telescope client

#### Installation

You need to add section repositories in your composer.json, for example:

```
"repositories": {
    "telescope-client": {
        "type": "vcs",
        "url": "git@github.com:alexeykostenko/telescope-client.git"
    }
}
```

Require package:
```
composer require pdffiller/telescope-client:dev-master
```

After installing package, publish its assets using the telescope-client:install Artisan command. After installing Telescope Errors, you should also run the migrate command:
```
php artisan telescope-client:install
```
