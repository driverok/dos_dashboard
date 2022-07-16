# dos_dashboard
Drupal open-source dashboard

## Usage:

composer install && composer dump-autoload

then 

### Get credits for user 'driverok' for Febryary 2022
php index.php --user=driverok --start=2022/02/01 --end=2022/02/27

### Get credits for company Epam for 2022
php index.php --company=2114867 --start=2022/01/01 --end=2022/07/31


Results CSV file will be located in /tmp/contribution_credits.csv
