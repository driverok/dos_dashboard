# dos_dashboard
Drupal open-source dashboard.

Able to gather issue credits from Drupal.org and parse activity from git.drupal.org.

## Prerequisites
composer install && composer dump-autoload

## Params
* --company [-c] Drupal.org node id for your company.
* --user [-u] Drupal.org node id for user
* --start [sd] Starting date in format YYYY/MM/DD
* --end [ed] End date in format YYYY/MM/DD
* --mapping-file [mf] csv mapping between D.org user url and company email
* --verbose [-v] Add some extra output to the result csv

## Usage

### Get credits for user 'driverok' for Febryary 2022
```
php index.php --user=driverok --start=2022/02/01 --end=2022/02/27
```

### Get credits for company Epam for 2022
```
php index.php --company=2114867 --start=2022/01/01 --end=2022/07/31
```
### Get credits for company Epam for 2022 with mapping D.org names to company emails
Please notice mapping file is a .csv file with the following structure:
user's drupal.org url;company email
```
php index.php --company=2114867 --start=2022/01/01 --end=2022/07/31 --mapping-file=epam_users.csv
```

### Verbose output
In this case more info on the page, and more info in the result CSV
```
php index.php --company=2114867 --start=2022/01/01 --end=2022/07/31 --verbose=1
```




Results CSV file will be located in /tmp/contribution_credits.csv
