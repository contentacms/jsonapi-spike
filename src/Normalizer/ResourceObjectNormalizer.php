<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;

class ResourceObjectNormalizer extends NormalizerBase {

  protected $supportedInterfaceOrClass = ['Drupal\jsonapi\ResourceObject'];
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    return [
      "type" => $object->type,
      "id" => $object->id,
      "attributes" => $object->attributes,
      "relationships" => $object->relationships,
      "meta" => $object->meta
    ];
  }
}
