Opauth-Trello
=============
[Opauth][1] strategy for Trello authentication.

Implemented based on https://trello.com/docs/index.html

Getting started
----------------
1. Install Opauth-Trello:

   Using git:
   ```bash
   cd path_to_opauth/Strategy
   git clone https://github.com/t1mmen/opauth-trello.git Trello
   ```

  Or, using [Composer](https://getcomposer.org/), just add this to your `composer.json`:

   ```bash
   {
       "require": {
           "t1mmen/opauth-trello": "*"
       }
   }
   ```
   Then run `composer install`.


2. Create Trello application at https://trello.com/1/appKey/generate

3. Configure Opauth-Trello strategy with at least `Client ID` and `Client Secret`.

4. Direct user to `http://path_to_opauth/trello` to authenticate

Strategy configuration
----------------------

Required parameters:

```php
<?php
'Trello' => array(
	'client_id' => 'YOUR CLIENT ID',
	'client_secret' => 'YOUR CLIENT SECRET'
)
```

License
---------
Opauth-Trello is MIT Licensed
Copyright © 2014 Timm Stokke (http://timm.stokke.me)

[1]: https://github.com/opauth/opauth
