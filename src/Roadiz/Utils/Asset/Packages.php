<?php
/**
 * Copyright © 2015, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file Packages.php
 * @author Ambroise Maupate
 */
namespace RZ\Roadiz\Utils\Asset;

use RZ\Roadiz\Core\Entities\Document;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\Packages as BasePackages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Packages
 * @package RZ\Roadiz\Utils\Asset
 */
class Packages extends BasePackages
{
    const ABSOLUTE = 'absolute';
    const DOCUMENTS = 'doc';
    const ABSOLUTE_DOCUMENTS = 'absolute_doc';

    /**
     * Build a new asset packages for Roadiz root and documents.
     *
     * @param VersionStrategyInterface $versionStrategy
     * @param RequestStack $requestStack
     * @param string $staticDomain
     * @param bool $isPreview
     */
    public function __construct(
        VersionStrategyInterface $versionStrategy,
        RequestStack $requestStack,
        $staticDomain = "",
        $isPreview = false
    ) {
        $requestStackContext = new RequestStackContext($requestStack);
        $request = $requestStack->getCurrentRequest();

        if (false === $isPreview && $staticDomain != "") {
            /*
             * Add non-default port to static domain.
             */
            $staticDomainAndPort = $staticDomain;
            if (($request->isSecure() && $request->getPort() != 443) ||
                (!$request->isSecure() && $request->getPort() != 80)) {
                $staticDomainAndPort .= ':' . $request->getPort();
            }

            /*
             * If no protocol, use https as default
             */
            if (!preg_match("~^(?:f|ht)tps?://~i", $staticDomainAndPort)) {
                $staticDomainAndPort = "https://" . $staticDomainAndPort;
            }

            $defaultPackage = new UrlPackage(
                $staticDomainAndPort,
                $versionStrategy
            );
            $documentPackage = new UrlPackage(
                $staticDomainAndPort . '/' . Document::getFilesFolderName(),
                $versionStrategy
            );

            $namedPackages = array(
                static::ABSOLUTE => $defaultPackage,
                static::DOCUMENTS => $documentPackage,
                static::ABSOLUTE_DOCUMENTS => $documentPackage,
            );
        } else {
            $defaultPackage = new PathPackage(
                '/',
                $versionStrategy,
                $requestStackContext
            );
            $documentPackage = new PathPackage(
                '/' . Document::getFilesFolderName(),
                $versionStrategy,
                $requestStackContext
            );

            $namedPackages = array(
                static::ABSOLUTE => new UrlPackage(
                    $request->getSchemeAndHttpHost() . $request->getBasePath(),
                    $versionStrategy
                ),
                static::DOCUMENTS => $documentPackage,
                static::ABSOLUTE_DOCUMENTS => new UrlPackage(
                    $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . Document::getFilesFolderName(),
                    $versionStrategy
                ),
            );
        }

        parent::__construct($defaultPackage, $namedPackages);
    }
}
