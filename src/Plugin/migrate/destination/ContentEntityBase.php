<?php

/**
 * @file
 * Contains \Drupal\multiversion\Plugin\migrate\destination\ContentEntityBase.
 */

namespace Drupal\multiversion\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\destination\EntityContentBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migration destination class for content entities.
 *
 * @todo: Implement derivatives for all content entity types and bundles.
 *
 * @MigrateDestination(
 *   id = "multiversion"
 * )
 */
class ContentEntityBase extends EntityContentBase {

  /**
   * The password service class.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $password;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    list($entity_type_id) = explode('__', $migration->id());

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity.manager')->getStorage($entity_type_id),
      array_keys($container->get('entity.manager')->getBundleInfo($entity_type_id)),
      $container->get('entity.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('password')
    );
  }

  /**
   * Builds an user entity destination.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param MigrationInterface $migration
   *   The migration.
   * @param EntityStorageInterface $storage
   *   The storage for this entity type.
   * @param array $bundles
   *   The list of bundles this entity type has.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\Password\PasswordInterface $password
   *   The password service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, EntityStorageInterface $storage, array $bundles, EntityManagerInterface $entity_manager, FieldTypePluginManagerInterface $field_type_manager, PasswordInterface $password) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $storage, $bundles, $entity_manager, $field_type_manager);

    // Since password records from the earlier schema already was hashed we
    // disable hashing so that passwords stay intact.
    $this->password = $password;
    $this->password->disablePasswordHashing();
    $this->storage->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = array()) {
    $this->rollbackAction = MigrateIdMapInterface::ROLLBACK_DELETE;
    $entity = $this->getEntity($row, $old_destination_id_values);
    if (!$entity) {
      throw new MigrateException('Unable to get entity');
    }
    if ($entity->getEntityTypeId() == 'file') {
      $destinations = $row->getDestination();
      if (isset($destinations['uri'])) {
        $destination = 'public://';
        if ($target = file_uri_target($destinations['uri'])) {
          $destination = $destination . $target;
        }
        $dirname = \Drupal::service('file_system')->dirname($destination);
        $logger = \Drupal::logger('Multiversion');
        if (!is_dir($dirname) && !\Drupal::service('stream_wrapper.public')->mkdir($dirname, NULL, TRUE)) {
          // If the directory does not exists and cannot be created.
          $logger->error('The directory %directory does not exist and could not be created.', array('%directory' => $dirname));
        }

        if (is_dir($dirname) && !is_writable($dirname) && !\Drupal::service('file_system')->chmod($dirname, NULL)) {
          // If the directory is not writable and cannot be made so.
          $logger->error('The directory %directory exists but is not writable and could not be made writable.', array('%directory' => $dirname));
        }
        elseif (is_dir($dirname) && is_writable($dirname)) {
          // Move the file to a folder from 'public://' directory.
          file_unmanaged_move($destinations['uri'], $destination, FILE_EXISTS_REPLACE);
        }
        $entity->uri->setValue($destination);
      }
    }
    return $this->save($entity, $old_destination_id_values);
  }

}
