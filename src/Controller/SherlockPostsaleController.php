<?php

declare(strict_types=1);

namespace ContaoIsotopeSherlockBundle\Controller;

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendCron;
use Contao\System;
use Isotope\Frontend;
use Isotope\Interfaces\IsotopePostsale;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Payment;
use Isotope\PostSale;
use Monolog\Logger;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(name="sherlock_isotope_postsale", path="/_sherlock/postsale/{mod}/{id}", requirements={"mod" = "pay|ship" ,"id" = "\d+"}, defaults={"_scope" = "frontend", "_token_check" = false, "_bypass_maintenance" = true})
 */
class SherlockPostsaleController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var UriSigner
     */
    private $uriSigner;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(ContaoFramework $framework, UriSigner $uriSigner, Logger $logger)
    {
        $this->framework = $framework;
        $this->uriSigner = $uriSigner;
        $this->logger = $logger;
    }

    public function __invoke(Request $request)
    {
        // Allow redirects to bypass POST data issues in payment return URLs
        // if ($request->query->has('redirect')) {
        //     if ($this->uriSigner->check($request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo() . (null !== ($qs = $request->server->get('QUERY_STRING')) ? '?' . $qs : ''))) {
        //         return new RedirectResponse($request->query->get('redirect'));
        //     }

        //     return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
        // }

        $objMethod = null;

        try {
            $intId  = (int) $request->get('id');

            if ($intId == 0) {
                $this->logger->log(
                    LogLevel::ERROR,
                    'Invalid sherlock-post-sale request (param error): ' . $request->getUri(),
                    ['contao' => new ContaoContext(__METHOD__, LogLevel::ERROR)]
                );

                return new Response('Bad Request', Response::HTTP_BAD_REQUEST);
            }
            
            $objMethod = Payment::findByPk($intId);

            if (null === $objMethod) {
                $this->logger->log(
                    LogLevel::ERROR,
                    'Invalid sherlock-post-sale request (model not found): ' . $request->getUri(),
                    ['contao' => new ContaoContext(__METHOD__, LogLevel::ERROR)]
                );

                return new Response('Not Found', Response::HTTP_NOT_FOUND);
            }

            $this->logger->log(LogLevel::INFO,'New sherlock-post-sale request: ' . $request->getUri(), ['contao' => new ContaoContext(__METHOD__, LogLevel::INFO)]);

            if (!($objMethod instanceof \ContaoIsotopeSherlockBundle\Model\Payment\Sherlock)) {
                $this->logger->log(
                    LogLevel::ERROR,
                    'Invalid sherlock-post-sale request (payment is not a Sherlock object): ' . $request->getUri(),
                    ['contao' => new ContaoContext(__METHOD__, LogLevel::ERROR)]
                );

                return new Response('Not a Sherlock object', Response::HTTP_NOT_IMPLEMENTED);
            }

            /** @type Order $objOrder */
            $objOrder = $objMethod->getPostsaleOrder();

            if (null === $objOrder || !($objOrder instanceof IsotopeProductCollection)) {
                $this->logger->log(LogLevel::ERROR,\get_class($objMethod) . ' did not return a valid order', ['contao' => new ContaoContext(__METHOD__, LogLevel::ERROR)]);

                return new Response('Failed Dependency', Response::HTTP_FAILED_DEPENDENCY);
            }

            Frontend::loadOrderEnvironment($objOrder);

            $response = $objMethod->checkPaymentReturn($objOrder);

            return $response instanceof Response ? $response : new Response();

        } catch (\Exception $e) {
            if ($e instanceof ResponseException) {
                return $e->getResponse();
            }

            $this->logger->log(
                LogLevel::ERROR,
                'Exception in sherlock-post-sale request. See system/logs/sherlock_isotope_postsale.log for details.',
                ['contao' => new ContaoContext(__METHOD__, LogLevel::ERROR)]
            );

            log_message((string) $e, 'sherlock_isotope_postsale-' . date('Y-m-d') . '.log');
            
            return new Response('Internal Server Error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
