<?php

declare(strict_types=1);

namespace ContaoIsotopeSherlockBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use ContaoIsotopeSherlockBundle\ContaoIsotopeSherlockBundle;

/**
 * Plugin for the Contao Manager.
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */
class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(ContaoIsotopeSherlockBundle::class)
                ->setLoadAfter([
                    ContaoCoreBundle::class,
                    'isotope',
                ])
                ->setReplace(['isotope-sherlock']),
        ];
    }
}
