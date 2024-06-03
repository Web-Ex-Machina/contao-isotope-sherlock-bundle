# Isotope Sherlock Interface
This bundle adds support for payment interface Sherlock to Isotope ecommerce bundle.

## Install
Use composer to install this bundle: `composer require webexmachina/contao-isotope-sherlock-bundle`

Or use the Contao Manager.

## Sherlock Documentation
Sherlock API documentation can be found here: 
- en : [https://sherlocks-documentation.secure.lcl.fr/en/](https://sherlocks-documentation.secure.lcl.fr/en/)
- fr : [https://sherlocks-documentation.secure.lcl.fr/fr/](https://sherlocks-documentation.secure.lcl.fr/fr/)

There is no other languages available at the moment.

## Bundle configuration
You just have to create a new Isotope Payment and fill the `Merchant ID`, the `secret key`, the `key version` provided by Sherlock and chose the mode, `DEV` or `PROD`.

## Bundle usage
This section explains how to use the bundle and more precisely the Sherlock features. If a feature, listed in Sherlock API, is not documented here, assume it is not included inside this bundle. You can request it with an issue in Github.

In `DEV` mode, payments are made on Sherlock's simulation environment. The `Merchant ID`, `secret key`, and `key version` values used are the one provided by Sherlock's documentation. (en [https://sherlocks-documentation.secure.lcl.fr/en/sherlocks-paypage-post.html#Step-3-Doing-tests-in-the-simulation-environment_](https://sherlocks-documentation.secure.lcl.fr/en/sherlocks-paypage-post.html#Step-3-Doing-tests-in-the-simulation-environment_) / fr [https://sherlocks-documentation.secure.lcl.fr/fr/sherlocks-paypage-post.html#Etape-3-Tester-sur-l-environnement-de-simulation_](https://sherlocks-documentation.secure.lcl.fr/fr/sherlocks-paypage-post.html#Etape-3-Tester-sur-l-environnement-de-simulation_))

In `PROD` mode, payments are made on the real environment. The `Merchant ID`, `secret key`, and `key version` values used are the one your provided in the configuration.

### Test cards
You can find test payment cards here: 
- en : [https://sherlocks-documentation.secure.lcl.fr/en/sherlocks-paypage-post.html#Testing-CB-VISA-MASTERCARD-and-AMEX-transactions_](https://sherlocks-documentation.secure.lcl.fr/en/sherlocks-paypage-post.html#Testing-CB-VISA-MASTERCARD-and-AMEX-transactions_)
- fr : [https://sherlocks-documentation.secure.lcl.fr/fr/sherlocks-paypage-post.html#Tester-CB-VISA-MASTERCARD-AMEX_](https://sherlocks-documentation.secure.lcl.fr/fr/sherlocks-paypage-post.html#Tester-CB-VISA-MASTERCARD-AMEX_)

## Nota :

### paymentWebInit :

Documentation : [link](https://sherlocks-documentation.secure.lcl.fr/fr/dictionnaire-des-donnees/paypage/paymentwebinit.html)

- `Data.transactionReference` : useable only if your contract with LCL include this option. Not managed at the moment.
- `Data.seal` : Not in `Data` contrary to what the documentation states. It has to be at the same level as the `Data` & named `Seal`
- `Data.interfaceVersion` : Not in `Data` contrary to what the documentation states. It has to be at the same level as the `Data` & named `InterfaceVersion`

```php
// Example for POST message,
// What the doc states
[
	'Data'=>[
		// mandatory Data fields
		'amount'=>9999,
		'currencyCode'=>9999,
		'keyVersion'=>'9999',
		'merchantId'=>9999,
		'normalReturnUrl'=>'XXX',
		'orderChannel'=>'XXX',
		'interfaceVersion'=>'XXX',
		'seal'=>'XXX',
		// optionnal Data fields
		'transactionReference'=>'XXX', // only if your contract have activated this option
		...
	],
	// optionnal fields
	'Encode'=>'XXX',
	'SealAlgorithm'=>'XXX',
]

// What is really required
[
	'Data'=>[
		// mandatory Data fields
		'amount'=>9999,
		'currencyCode'=>9999,
		'keyVersion'=>'9999',
		'merchantId'=>9999,
		'normalReturnUrl'=>'XXX',
		'orderChannel'=>'XXX',
		// optionnal Data fields
		'transactionReference'=>'XXX', // only if your contract have activated this option
	]
	'InterfaceVersion'=>'XXX',
	'Seal'=>'XXX',
	// optionnal fields
	'Encode'=>'XXX',
	'sealAlgorithm'=>'XXX',
]
```