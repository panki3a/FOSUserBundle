<?php

namespace Bundle\FOS\UserBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class UserExtension extends Extension
{
    public function configLoad(array $config, ContainerBuilder $container)
    {
        // load all service configuration files (the db_driver first)
        if (!$container->hasDefinition('fos_user.document.user_manager')) {
            $loader = new XmlFileLoader($container, __DIR__.'/../Resources/config');
            foreach (array('user', 'controller', 'templating', 'email', 'form', 'validator', 'security') as $basename) {
                $loader->load(sprintf('%s.xml', $basename));
            }
        }

        // ensure the db_driver is configured
        if (!isset($config['db_driver'])) {
            throw new \InvalidArgumentException('The db_driver parameter must be defined.');
        }
        $this->configureDbDriver($config['db_driver'], $container);

        // change authentication provider class to support multiple algorithms
        $container->setParameter('security.authentication.provider.dao.class', 'Bundle\FOS\UserBundle\Security\Authentication\Provider\DaoAuthenticationProvider');

        // per default, we use a sha512 encoder, but you may change this here
        if (isset($config['encoder'])) {
            $this->configurePasswordEncoder($config['encoder'], $container);
        }

        $this->remapParametersNamespaces($config, $container, array(
            ''                      => array('session_create_success_route' => 'fos_user.session_create.success_route'),
            'template'              => 'fos_user.template.%s',
            'form_name'             => 'fos_user.form.%s.name',
            'confirmation_email'    => 'fos_user.confirmation_email.%s',
        ));

        $this->remapParametersNamespaces($config['class'], $container, array(
            'model'         => 'fos_user.model.%s.class',
            'form'          => 'fos_user.form.%s.class',
            'controller'    => 'fos_user.controller.%s.class'
        ));
    }

    protected function configureDbDriver(array $config, ContainerBuilder $container)
    {
        if (!isset($config['name'])) {
            throw new \InvalidArgumentException('A "name" must be configured for the db driver.');
        }

        switch(strtolower($config['name']))
        {
            case 'orm':
                if (!isset($config['entity'])) {
                    throw new \InvalidArgumentException('"entity" must be configured for the ORM db driver.');
                }

                $em = isset($config['em'])? $config['em'] : 'default';

                $container->setAlias('fos_user.user_manager', 'fos_user.entity.user_manager');
                $container
                    ->getDefinition('fos_user.entity.user_manager')
                    ->setArguments(array(new Reference(sprintf('doctrine.orm.%s_entity_manager', $em)), $config['entity']))
                ;

                break;

            case 'odm':
                if (!isset($config['document'])) {
                    throw new \InvalidArgumentException('"document" must be configured for the ODM driver.');
                }

                $container->setAlias('fos_user.user_manager', 'fos_user.document.user_manager');
                $definition = $container->getDefinition('fos_user.document.user_manager');
                $arguments = $definition->getArguments();
                $arguments[1] = $config['document'];
                $definition->setArguments($arguments);

                break;

            default:
                throw new \InvalidArgumentException(sprintf('Invalid db driver "%s".', $config['name']));

        }
    }

    protected function configurePasswordEncoder($config, ContainerBuilder $container)
    {
        if (!is_array($config)) {
            $container->setAlias('fos_user.encoder', 'security.encoder.'.$config);
        } else {
            if (isset($config['name'])) {
                $container->setAlias('fos_user.encoder', 'security.encoder.'.$config['name']);
            }

            $this->remapParameters($config, $container, array(
                'algorithm' => 'fos_user.encoder.algorithm',
                'encodeHashAsBase64' => 'fos_user.encoder.encodeHashAsBase64',
                'iterations' => 'fos_user.encoder.iterations',
            ));
        }
    }

    protected function remapParameters(array $config, ContainerBuilder $container, array $map)
    {
        foreach ($map as $name => $paramName) {
            if (isset($config[$name])) {
                $container->setParameter($paramName, $config[$name]);
            }
        }
    }

    protected function remapParametersNamespaces(array $config, ContainerBuilder $container, array $namespaces)
    {
        foreach ($namespaces as $ns => $map) {
            if ($ns) {
                if (!isset($config[$ns])) {
                    continue;
                }
                $namespaceConfig = $config[$ns];
            } else {
                $namespaceConfig = $config;
            }
            if (is_array($map)) {
                $this->remapParameters($namespaceConfig, $container, $map);
            } else {
                foreach ($namespaceConfig as $name => $value) {
                    if (null !== $value) {
                        $container->setParameter(sprintf($map, $name), $value);
                    }
                }
            }
        }
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://www.symfony-project.org/schema/dic/fos_user';
    }

    public function getAlias()
    {
        return 'fos_user';
    }
}