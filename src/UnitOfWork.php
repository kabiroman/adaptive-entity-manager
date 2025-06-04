<?php

namespace Kabiroman\AEM;

use Kabiroman\AEM\Event\PostPersistEvent;
use Kabiroman\AEM\Event\PostRemoveEvent;
use Kabiroman\AEM\Event\PostUpdateEvent;
use Kabiroman\AEM\Event\PrePersistEvent;
use Kabiroman\AEM\Event\PreRemoveEvent;
use Kabiroman\AEM\Event\PreUpdateEvent;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Mapping\LifecycleCallbackHandlerTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use SplObjectStorage;
use Throwable;

class UnitOfWork implements UnitOfWorkInterface
{
    use LifecycleCallbackHandlerTrait;

    /**
     * @var PersisterInterface[]
     */
    private static array $persisters = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    )
    {
    }

    public function getEntityPersister(ClassMetadata $classMetadata): PersisterInterface
    {
        $entityName = $classMetadata->getName();
        if (isset(self::$persisters[$entityName])) {
            $persister = self::$persisters[$entityName];
        } else {
            $persister = $this->em->getEntityPersisterFactory()
                ->makePersister($this->em, $classMetadata);

            self::$persisters[$entityName] = $persister;
        }

        return $persister;
    }

    public function clear(): void
    {
        self::$persisters = [];
    }

    /**
     * @throws CommitFailedException
     */
    public function commit(): void
    {
        $connection = $this->em->getConnection();
        $connection?->beginTransaction();
        try {
            $updatedInserts = new SplObjectStorage();
            foreach (self::$persisters as $persister) {
                foreach ($persister->getInserts() as $insert) {
                    if ($this->em->getClassMetadata(get_class($insert))->hasLifecycleCallbacks('preFlush')) {
                        $callbacks = $this->em->getClassMetadata(get_class($insert))->getLifecycleCallbacks('preFlush');
                        $this->handleLifecycleCallbacks($insert, $callbacks);
                    }
                    $this->eventDispatcher?->dispatch(new PrePersistEvent($insert));
                    $persister->insert($insert);
                    $this->eventDispatcher?->dispatch(new PostPersistEvent($insert));
                    $updatedInserts->attach($insert);
                }
            }
            foreach (self::$persisters as $persister) {
                foreach ($persister->getUpdates() as $update) {
                    if ($this->em->getClassMetadata(get_class($update))->hasLifecycleCallbacks('preFlush')) {
                        $callbacks = $this->em->getClassMetadata(get_class($update))->getLifecycleCallbacks('preFlush');
                        $this->handleLifecycleCallbacks($update, $callbacks);
                    }
                    if (!$updatedInserts->contains($update)) {
                        $this->eventDispatcher?->dispatch(new PreUpdateEvent($update));
                        $persister->update($update);
                        $this->eventDispatcher?->dispatch(new PostUpdateEvent($update));
                    }
                }
            }
            foreach (self::$persisters as $persister) {
                foreach ($persister->getDeletes() as $delete) {
                    if ($this->em->getClassMetadata(get_class($delete))->hasLifecycleCallbacks('preFlush')) {
                        $callbacks = $this->em->getClassMetadata(get_class($delete))->getLifecycleCallbacks('preFlush');
                        $this->handleLifecycleCallbacks($delete, $callbacks);
                    }
                    $this->eventDispatcher?->dispatch(new PreRemoveEvent($delete));
                    $persister->delete($delete);
                    $this->eventDispatcher?->dispatch(new PostRemoveEvent($delete));
                    $persister->detach($delete);
                }
            }
            $connection?->commitTransaction();

        } catch (Throwable $e) {
            $connection?->rollbackTransaction();
            throw new CommitFailedException($e);
        }
    }
}
