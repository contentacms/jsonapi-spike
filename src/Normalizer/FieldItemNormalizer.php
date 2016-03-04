<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\FieldItemNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;

class FieldItemNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\FieldItemInterface';
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $name = $object->mainPropertyName();
    if ($name) {
      return $this->serializer->normalize($object->get($name), $format, $context);
    }
    $attributes = array();
    foreach ($object as $name => $field) {
      $attributes[$name] = $this->serializer->normalize($field, $format, $context);
    }
    return $attributes;
  }

}
