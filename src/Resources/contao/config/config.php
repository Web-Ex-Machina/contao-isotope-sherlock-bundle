<?php

declare(strict_types=1);

/*
 * Register Sherlock payment
 */
\Isotope\Model\Payment::registerModelType('sherlock', \ContaoIsotopeSherlockBundle\Model\Payment\Sherlock::class);
