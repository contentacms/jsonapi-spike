<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\EntityReferenceFieldItemNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Adds the file URI to embedded file entities.
 */
class EntityReferenceFieldItemNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = EntityReferenceItem::class;

  protected $format = array('jsonapi');
    
    
  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $entity = $field_item->get('entity')->getValue();
    if ($entity) {
      return $this->serializer->normalize($entity, $format, $context);
    }
  }

}
