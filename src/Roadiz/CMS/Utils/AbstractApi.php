<?php
/**
 * Copyright © 2014, Ambroise Maupate and Julien Blanchet
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
 * @file AbstractApi.php
 * @author Maxime Constantinian
 */
namespace RZ\Roadiz\CMS\Utils;

use Pimple\Container;

/**
 * Class AbstractApi.
 *
 * @package RZ\Roadiz\CMS\Utils
 */
abstract class AbstractApi
{
    /**
     * DI container
     *
     * @var Container $container
     */
    protected $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Return entity path for current API.
     *
     * @return \Doctrine\ORM\EntityRepository
     */
    abstract public function getRepository();

    /**
     * Return an array of entities matching criteria array.
     *
     * @param \Doctrine\Common\Collections\ArrayCollection|\Doctrine\ORM\Tools\Pagination\Paginator|array $criteria
     *
     * @return array
     */
    abstract public function getBy(array $criteria);

    /**
     * Return one entity matching criteria array.
     *
     * @param array $criteria
     *
     * @return mixed
     */
    abstract public function getOneBy(array $criteria);

    /**
     * Count entities matching criteria array.
     *
     * @param array $criteria
     *
     * @return int
     */
    abstract public function countBy(array $criteria);
}
