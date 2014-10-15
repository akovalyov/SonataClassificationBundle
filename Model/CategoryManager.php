<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\ClassificationBundle\Model;

use Doctrine\Common\Persistence\ManagerRegistry;
use Sonata\AdminBundle\Datagrid\PagerInterface;

use Sonata\ClassificationBundle\Model\CategoryInterface;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;

use Sonata\ClassificationBundle\Model\ContextInterface;
use Sonata\ClassificationBundle\Model\ContextManagerInterface;
use Sonata\CoreBundle\Model\BaseEntityManager;

use Sonata\DatagridBundle\Pager\Doctrine\Pager;
use Sonata\DatagridBundle\ProxyQuery\Doctrine\ProxyQuery;

abstract class CategoryManager extends BaseEntityManager implements CategoryManagerInterface
{
    /**
     * @var array
     */
    protected $categories;

    protected $contextManager;

    /**
     * @param string                  $class
     * @param ManagerRegistry         $registry
     * @param ContextManagerInterface $contextManager
     */
    public function __construct($class, ManagerRegistry $registry, ContextManagerInterface $contextManager)
    {
        parent::__construct($class, $registry);

        $this->contextManager = $contextManager;
        $this->categories = array();
    }

    /**
     * @param ContextInterface $context
     *
     * @return CategoryInterface
     */
    public function getRootCategory($context = null)
    {
        $context = $this->getContext($context);

        $this->loadCategories($context);

        return $this->categories[$context->getId()][0];
    }

    /**
     * @param ContextInterface $context
     *
     * @return array
     */
    public function getCategories($context = null)
    {
        $context = $this->getContext($context);

        $this->loadCategories($context);

        return $this->categories[$context->getId()];
    }

    /**
     * @param $context
     *
     * @return ContextInterface
     */
    protected function getContext($contextCode)
    {
        if ($contextCode === null) {
            $contextCode = ContextInterface::DEFAULT_CONTEXT;
        }

        if ($contextCode instanceof ContextInterface) {
            return $contextCode;
        }

        $context = $this->contextManager->find($contextCode);

        if (!$context instanceof ContextInterface) {
            $context = $this->contextManager->create();

            $context->setId($contextCode);
            $context->setName($contextCode);
            $context->setEnabled(true);

            $this->contextManager->save($context);
        }

        return $context;
    }
}
