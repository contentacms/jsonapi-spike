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
      "json" => $object->json,
      "drupal" => $object->drupal
    ];
  }
}
