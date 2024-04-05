<?php

declare(strict_types=1);

/*
 * Add payment to tl_iso_payment
 */
$GLOBALS['TL_DCA']['tl_iso_payment']['palettes']['sherlock'] = '
	{type_legend},type,name,label;
	{note_legend:hide},note;
	{config_payweak_legend},sherlock_merchant_id,sherlock_key_secret;
	{config_legend},new_order_status,postsale_mail,minimum_total,maximum_total,countries,shipping_modules,product_types;
	{price_legend:hide},price,tax_class;
	{enabled_legend},enabled;
';

$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['sherlock_merchant_id'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
    'load_callback' => [
        ['plenta.encryption', 'decrypt']
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt']
    ],
];
$GLOBALS['TL_DCA']['tl_iso_payment']['fields']['sherlock_key_secret'] = [
    'exclude' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
    'load_callback' => [
        ['plenta.encryption', 'decrypt']
    ],
    'save_callback' => [
        ['plenta.encryption', 'encrypt']
    ],
];