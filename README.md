# Author Admin API

Web Service to verify purchase code from Envato (https://envato.com). Also protect purchase code verification by tracking the number of requests made for each purchase code.

Database script of this web service can be find here https://github.com/fayzandotcom/author-admin-db
APIs in this projects are consumed by an angular web UI (https://github.com/fayzandotcom/author-admin-web)

## Platform/Framework

1. PHP 5.6
2. Slim Framework v3.1 (www.slimframework.com)
3. MySQL

## Installation

1. Run the SQL script migrations in MySQL database from https://github.com/fayzandotcom/author-admin-db
2. Install composer and resolve dependencies using composer. `composer install`
3. Deploy admin-author-api to web server (i.e. apache) with php 5.6
4. Edit connect_db() method in "index.php" under public folder. Provide database name, username and password.

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request :D

## History

Version: 1.0
* Initial release.

## License

GPLv2
http://www.gnu.org/licenses/gpl-2.0.html
