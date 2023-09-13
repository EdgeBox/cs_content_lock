<?php

namespace Drupal\cs_content_lock\Controller;

use Drupal\cms_content_sync\Controller\ContentSyncSettings;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\PushIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\cms_content_sync\EntityStatusProxy;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Lock controller.
 */
class Lock extends ControllerBase {

  public const LOCKED = 'locked';
  public const LOCKED_UNLOCKABLE = 'locked_unlockable';
  public const LOCKED_NOT_UNLOCKABLE = 'locked_not_unlockable';
  public const UNLOCKED = 'unlocked';

  /**
   * Lock a give entity.
   *
   * @param string $entity_type
   * @param int $entity_id
   * @return void
   */
  public function lock($entity_type, $entity_id) {
    // Set lock field value.
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $entity->field_content_sync_content_lock = ContentSyncSettings::getInstance()->getSiteUuid();
    if($entity instanceof EntityChangedInterface) {
      $entity->setChangedTime(time());
    }

    // @ToDo: Implement Try/Catch for entity save().
    $entity->save();

    // Reset Status Entities.
    $status_entities = EntityStatus::getInfosForEntity($entity_type, $entity->uuid());
    if(empty($status_entities)) {
      return new RedirectResponse('/');
    }

    $status_entity = new EntityStatusProxy($status_entities);
    $status_entity->setLastPull(null);
    $status_entity->save();

    $pools = [];
    foreach($status_entities as $status) {
      if(!in_array($status->getPool(), $pools)) {
        $pools[] = $status->getPool();
      }
    }

    $flows = PushIntent::getFlowsForEntity($entity,PushIntent::PUSH_FORCED, SyncIntent::ACTION_CREATE);
    if (!count($flows)) {
      return new RedirectResponse('/');
    }

    $pool_config = Pool::getSelectablePools($entity->getEntityTypeId(), $entity->bundle());
    // If no pool_config is give, the user is not allowed to assign the pools
    // manually, and we do not have to check for it.
    if(empty($pool_config)) {
      try {
        PushIntent::pushEntity($entity, PushIntent::PUSH_FORCED, SyncIntent::ACTION_CREATE);
        \Drupal::messenger()->addMessage(t('Item %label has been locked and pushed.', ['%label' => $entity->label()]));
      } catch (\Exception $exception) {
        \Drupal::messenger()->addWarning(t('Item %label could not be pushed: %exception', ['%label' => $entity->label(), '%exception' => $exception->getMessage()]));
      }
    } else {
      foreach ($pool_config as $flow_id => $flow_config) {
        $allowed_pools = [];
        foreach ($flow_config['pools'] as $id => $name) {
          foreach ($pools as $pool) {
            if ($pool->id() == $id) {
              $allowed_pools[] = $pool;
            }
          }
        }
        if (empty($allowed_pools)) {
          continue;
        }

        $flow = Flow::getAll()[$flow_id];
        // Push entity to all related push flows.
        try {
          PushIntent::pushEntity($entity, PushIntent::PUSH_FORCED, SyncIntent::ACTION_CREATE, $flow, $allowed_pools);
          \Drupal::messenger()->addMessage(t('Item %label has been locked and pushed.', ['%label' => $entity->label()]));

          break;
        } catch (\Exception $exception) {
          \Drupal::messenger()->addWarning(t('Item %label could not be pushed: %exception', ['%label' => $entity->label(), '%exception' => $exception->getMessage()]));
        }
      }
    }

    return new RedirectResponse('/');
  }

  /**
   * Unlock a give entity.
   *
   * @param string $entity_type
   * @param int $entity_id
   * @return void
   */
  public function unlock($entity_type, $entity_id) {
    // Set lock field value.
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $entity->field_content_sync_content_lock = NULL;
    if ($entity instanceof EntityChangedInterface) {
      $entity->setChangedTime(time());
    }

    // @ToDo: Implement Try/Catch for entity save().
    $entity->save();

    // Push entity to all related push flows.
    try {
      PushIntent::pushEntity($entity, PushIntent::PUSH_FORCED, SyncIntent::ACTION_CREATE);
      \Drupal::messenger()->addMessage(t('Item %label has been unlocked and pushed.', ['%label' => $entity->label()]));
    } catch (\Exception $exception) {
      \Drupal::messenger()->addWarning(t('Item %label could not be pushed: %exception', ['%label' => $entity->label(), '%exception' => $exception->getMessage()]));
    }

    return new RedirectResponse('/');
  }

  /**
   * Check the lock status for a give entity.
   *
   * @param object $entity
   * @return void
   */
  public static function lockStatus($entity) {

    // Locked
    if(!$entity->field_content_sync_content_lock->isEmpty()) {
      if($entity->field_content_sync_content_lock->value == ContentSyncSettings::getInstance()->getSiteUuid()) {
        // Can be unlocked
        return Lock::LOCKED_UNLOCKABLE;
      } else {
        // Can NOT be unlocked
        return Lock::LOCKED_NOT_UNLOCKABLE;
      }

    } else {
      // Is currently unlocked.
      return Lock::UNLOCKED;;
    }
  }

}
