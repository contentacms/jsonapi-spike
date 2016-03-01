<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\FieldItemListNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class FieldItemListNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = ['Drupal\Core\Field\FieldItemListInterface'];
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    if (!$object->getFieldDefinition()->getFieldStorageDefinition()->isMultiple()) {
      return $this->serializer->normalize($object->first(), $format, $context);
    }
    $attributes = array();
    foreach ($object as $index => $fieldItem) {
      $path = $context['jsonapi_path'];
      $path[] = $index;
      $innerContext = $context;
      $innerContext['jsonapi_path'] = $path;
      $attributes[] = $this->serializer->normalize($fieldItem, $format, $context);
    }
    return $attributes;
  }
}
