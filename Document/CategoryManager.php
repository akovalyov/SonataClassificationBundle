<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Sonata Project <https://github.com/sonata-project/SonataClassificationBundle/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\ClassificationBundle\Document;

use Doctrine\ODM\MongoDB\DocumentManager;
use Sonata\ClassificationBundle\Model\CategoryManagerInterface;

use Sonata\ClassificationBundle\Model\CategoryManager as BaseCategoryManager;
use Sonata\ClassificationBundle\Model\ContextInterface;
use Sonata\DatagridBundle\Pager\Doctrine\Pager;
use Sonata\DatagridBundle\ProxyQuery\Doctrine\ProxyQuery;

class CategoryManager extends BaseCategoryManager implements CategoryManagerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConnection()
    {
        return $this->getManager()->getConnection();
    }

    /**
     * @return DocumentManager
     */
    public function getManager()
    {
        return $this->getObjectManager();
    }

    /**
     * {@inheritdoc}
     */
    public function getPager(array $criteria, $page, $limit = 10, array $sort = array())
    {
        $parameters = array();

        $query = $this->getRepository()
            ->createQueryBuilder('c')
            ->select('c');

        $criteria['enabled'] = isset($criteria['enabled']) ? $criteria['enabled'] : true;
        $query->andWhere('c.enabled = :enabled');
        $parameters['enabled'] = $criteria['enabled'];

        $query->setParameters($parameters);

        $pager = new Pager();
        $pager->setMaxPerPage($maxPerPage);
        $pager->setQuery(new ProxyQuery($query));
        $pager->setPage($page);
        $pager->init();

        return $pager;
    }

    /**
     * Load all categories from the database, the current method is very efficient for < 256 categories
     *
     */
    protected function loadCategories(ContextInterface $context)
    {
        if (array_key_exists($context->getId(), $this->categories)) {
            return;
        }

        $class = $this->getClass();

        $categories = $this->getObjectManager()->createQueryBuilder($class)
            ->select('c')
            ->where('c.context='.$context->getId())
            ->sort('c.parent', 'asc')
            //    sprintf('SELECT c FROM %s c WHERE c.context = :context ORDER BY c.parent ASC', $class))
//            ->setParameter('context', $context->getId())
            ->getQuery()
            ->execute();

        if (count($categories) == 0) {
            // no category, create one for the provided context
            $category = $this->create();
            $category->setName($context->getName());
            $category->setEnabled(true);
            $category->setContext($context);
            $category->setDescription($context->getName());

            $this->save($category);

            $categories = array($category);
        }

        foreach ($categories as $pos => $category) {
            if ($pos === 0 && $category->getParent()) {
                throw new \RuntimeException('The first category must be the root');
            }

            if ($pos == 0) {
                $root = $category;
            }

            $this->categories[$context->getId()][$category->getId()] = $category;

            $parent = $category->getParent();

            $category->disableChildrenLazyLoading();

            if ($parent) {
                $parent->addChild($category);
            }
        }

        $this->categories[$context->getId()] = array(
            0 => $root
        );
    }
}
