# List inactive accounts on a GNU social instance

## Installation

1. Navigate to your `/local/plugins` directory
1. `git clone https://github.com/chimo/gs-staleAccounts.git StaleAccounts`

Tell `/config.php` to use it with:

```php
addPlugin('StaleAccounts');
```

## Configuration

By default, accounts are considered stale/inactive after 3 months of inactivity.
To change this to a different number of months, you can use the following:

```php
$config['staleaccounts']['inactive_period'] = 12; // A year
```

## Usage

Instance administrators should see a "Stale Accounts" link in the left-navigation
of the "Admin" section (https://example.org/panel/staleaccounts)

