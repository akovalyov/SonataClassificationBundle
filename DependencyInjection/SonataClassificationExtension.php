<?php

/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\ClassificationBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use Sonata\EasyExtendsBundle\Mapper\DoctrineCollector;

/**
 * SonataClassificationBundleExtension
 *
 * @author Thomas Rabaix <thomas.rabaix@sonata-project.org>
 */
class SonataClassificationExtension extends Extension
{
    /**
     * @throws \InvalidArgumentException
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);
        $bundles = $container->getParameter('kernel.bundles');

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        if (!in_array(strtolower($config['db_driver']), array('doctrine_orm', 'doctrine_mongodb', 'doctrine_phpcr'))) {
            throw new \InvalidArgumentException(sprintf('SonataClassificationBundle - Invalid db driver "%s".', $config['db_driver']));
        }
        if(in_array($config['db_driver'], array('doctrine_orm', 'doctrine_mongodb'))){
            $loader->load(sprintf('%1$s/%1$s.xml', $config['db_driver']));
            $loader->load(sprintf('%s/api_form.xml', $config['db_driver']));
        }
        else{
            throw new \InvalidArgumentException(sprintf('SonataClassificationBundle - db driver "%s" is not supported yet.', $config['db_driver']));
        }
        $loader->load('form.xml');
        $loader->load('serializer.xml');
        $loader->load('api_controllers.xml');

        if (isset($bundles['SonataAdminBundle'])) {
            $loader->load(sprintf('%1$s/%1$s_admin.xml', $config['db_driver']));
        }

        $this->registerDoctrineMapping($config, $container);
        $this->configureClass($config, $container);
        $this->configureAdmin($config, $container);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function configureClass($config, ContainerBuilder $container)
    {
        if('doctrine_orm' === $config['db_driver']){
            $postfix = 'entity';
        }
        elseif('doctrine_mongodb' === $config['db_driver']){
            $postfix = 'document';
        }
        elseif('doctrine_phpcr' === $config['db_driver']){
            throw new \InvalidArgumentException(sprintf('SonataClassificationBundle - db driver "%s" is not supported yet.', $config['db_driver']));
        }
        // admin configuration
        $container->setParameter(sprintf('sonata.classification.admin.tag.%s', $postfix),        $config['class']['tag']);
        $container->setParameter(sprintf('sonata.classification.admin.category.%s', $postfix),   $config['class']['category']);
        $container->setParameter(sprintf('sonata.classification.admin.collection.%s', $postfix), $config['class']['collection']);
        $container->setParameter(sprintf('sonata.classification.admin.context.%s', $postfix),    $config['class']['context']);

        // manager configuration
        $container->setParameter(sprintf('sonata.classification.manager.tag.%s', $postfix),        $config['class']['tag']);
        $container->setParameter(sprintf('sonata.classification.manager.category.%s', $postfix),   $config['class']['category']);
        $container->setParameter(sprintf('sonata.classification.manager.collection.%s', $postfix), $config['class']['collection']);
        $container->setParameter(sprintf('sonata.classification.manager.context.%s', $postfix),    $config['class']['context']);
    }

    /**
     * @param array            $config
     * @param ContainerBuilder $container
     */
    public function configureAdmin($config, ContainerBuilder $container)
    {
        $container->setParameter('sonata.classification.admin.category.class',                $config['admin']['category']['class']);
        $container->setParameter('sonata.classification.admin.category.controller',           $config['admin']['category']['controller']);
        $container->setParameter('sonata.classification.admin.category.translation_domain',   $config['admin']['category']['translation']);

        $container->setParameter('sonata.classification.admin.tag.class',                     $config['admin']['tag']['class']);
        $container->setParameter('sonata.classification.admin.tag.controller',                $config['admin']['tag']['controller']);
        $container->setParameter('sonata.classification.admin.tag.translation_domain',        $config['admin']['tag']['translation']);

        $container->setParameter('sonata.classification.admin.collection.class',              $config['admin']['collection']['class']);
        $container->setParameter('sonata.classification.admin.collection.controller',         $config['admin']['collection']['controller']);
        $container->setParameter('sonata.classification.admin.collection.translation_domain', $config['admin']['collection']['translation']);

        $container->setParameter('sonata.classification.admin.context.class',                 $config['admin']['context']['class']);
        $container->setParameter('sonata.classification.admin.context.controller',            $config['admin']['context']['controller']);
        $container->setParameter('sonata.classification.admin.context.translation_domain',    $config['admin']['context']['translation']);
    }

    /**
     * @param array $config
     */
    public function registerDoctrineMapping(array $config)
    {

        foreach ($config['class'] as $type => $class) {
            if (!class_exists($class)) {
                return;
            }
        }

        $collector = DoctrineCollector::getInstance();

        $collector->addAssociation($config['class']['category'], 'mapOneToMany', array(
            'fieldName'     => 'children',
            'targetEntity'  => $config['class']['category'],
            'cascade'       => array(
                'remove',
                'persist',
            ),
            'mappedBy'      => 'parent',
            'orphanRemoval' => true,
            'orderBy'       => array(
                'position'  => 'ASC',
            ),
        ));

        $collector->addAssociation($config['class']['category'], 'mapManyToOne', array(
            'fieldName'     => 'parent',
            'targetEntity'  => $config['class']['category'],
            'cascade'       => array(
                'remove',
                'persist',
                'refresh',
                'merge',
                'detach',
            ),
            'mappedBy'      => NULL,
            'inversedBy'    => 'children',
            'joinColumns'   => array(
                array(
                 'name'                 => 'parent_id',
                 'referencedColumnName' => 'id',
                 'onDelete'             => 'CASCADE',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addAssociation($config['class']['category'], 'mapManyToOne', array(
            'fieldName'     => 'context',
            'targetEntity'  => $config['class']['context'],
            'cascade'       => array(
                'persist',
            ),
            'mappedBy'      => null,
            'inversedBy'    => null,
            'joinColumns'   => array(
                array(
                    'name'  => 'context',
                    'referencedColumnName' => 'id',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addAssociation($config['class']['tag'], 'mapManyToOne', array(
            'fieldName'     => 'context',
            'targetEntity'  => $config['class']['context'],
            'cascade'       => array(
                'persist',
            ),
            'mappedBy'      => null,
            'inversedBy'    => null,
            'joinColumns'   => array(
                array(
                    'name'  => 'context',
                    'referencedColumnName' => 'id',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addUnique($config['class']['tag'], 'tag_context', array('slug', 'context'));

        $collector->addAssociation($config['class']['collection'], 'mapManyToOne', array(
            'fieldName'     => 'context',
            'targetEntity'  => $config['class']['context'],
            'cascade'       => array(
                'persist',
            ),
            'mappedBy'      => null,
            'inversedBy'    => null,
            'joinColumns'   => array(
                array(
                    'name'  => 'context',
                    'referencedColumnName' => 'id',
                ),
            ),
            'orphanRemoval' => false,
        ));

        $collector->addUnique($config['class']['collection'], 'tag_collection', array('slug', 'context'));

        if (interface_exists('Sonata\MediaBundle\Model\MediaInterface')) {
            $collector->addAssociation($config['class']['collection'], 'mapManyToOne', array(
                'fieldName'     => 'media',
                'targetEntity'  => $config['class']['media'],
                'cascade'       => array(
                    'persist',
                ),
                'mappedBy'      => NULL,
                'inversedBy'    => NULL,
                'joinColumns'   => array(
                    array(
                     'name'                 => 'media_id',
                     'referencedColumnName' => 'id',
                     'onDelete'             => 'SET NULL',
                    ),
                ),
                'orphanRemoval' => false,
            ));

            $collector->addAssociation($config['class']['category'], 'mapManyToOne', array(
                'fieldName'     => 'media',
                'targetEntity'  => $config['class']['media'],
                'cascade'       => array(
                    'persist',
                ),
                'mappedBy'      => NULL,
                'inversedBy'    => NULL,
                'joinColumns'   => array(
                    array(
                     'name'                 => 'media_id',
                     'referencedColumnName' => 'id',
                     'onDelete'             => 'SET NULL',
                    ),
                ),
                'orphanRemoval' => false,
            ));
        }
    }
}
