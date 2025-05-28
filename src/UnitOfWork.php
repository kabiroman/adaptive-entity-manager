<?php

namespace Kabiroman\AEM;

use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Mapping\LifecycleCallbackHandlerTrait;
use SplObjectStorage;
use Throwable;

class UnitOfWork implements UnitOfWorkInterface
{
    use LifecycleCallbackHandlerTrait;

    /**
     * @var PersisterInterface[]
     */
    private static array $persisters = [];

    public function __construct(private readonly EntityManagerInterface $em)
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
                    $persister->insert($insert);
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
                        $persister->update($update);
                    }
                }
            }
            foreach (self::$persisters as $persister) {
                foreach ($persister->getDeletes() as $delete) {
                    if ($this->em->getClassMetadata(get_class($delete))->hasLifecycleCallbacks('preFlush')) {
                        $callbacks = $this->em->getClassMetadata(get_class($delete))->getLifecycleCallbacks('preFlush');
                        $this->handleLifecycleCallbacks($delete, $callbacks);
                    }
                    $persister->delete($delete);
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
