<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\BooleanDataNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class BooleanDataNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\TypedData\Plugin\DataType\BooleanData';
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    return $object->getCastedValue();
  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    if ($payload) {
      return ["value" => "1"];
    } else {
      return ["value" => "0"];
    }
  }

}
