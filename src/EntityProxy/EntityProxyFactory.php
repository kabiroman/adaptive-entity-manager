<?php

namespace Kabiroman\AEM\EntityProxy;

use Kabiroman\AEM\EntityManagerInterface;
use RuntimeException;

class EntityProxyFactory
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function createProxy(string $targetClass, array $criteria): object
    {
        $classMetadata = $this->entityManager->getClassMetadata($targetClass);
        $config = $this->entityManager->getConfig();
        $shortClassName = $classMetadata->getReflectionClass()->getShortName();
        $proxyFolder = realpath($this->entityManager->getConfig()->getCacheFolder().'/proxy/entity');
        if (!file_exists($proxyClassFile = $proxyFolder.'/'.$shortClassName.'Proxy.php')) {
            throw new RuntimeException(sprintf('Proxy %s not found', $proxyFolder.'/'.$shortClassName.'Proxy.php'));
        }
        require_once $proxyClassFile;

        $proxyClass = 'Proxy\\Entity\\'.$shortClassName.'Proxy';
        $repository= $this->entityManager->getRepository($targetClass);

        $callback = function () use ($criteria, $repository) {
            return $repository->findOneBy($criteria);
        };

        /** @var EntityProxyTrait $proxy */
        $proxy = new $proxyClass();
        $proxy->__setCallback($callback);

        return $proxy;
    }
}
