<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\IntegerDataNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class IntegerDataNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\TypedData\Plugin\DataType\IntegerData';
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    return $object->getCastedValue();
  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    return ["value" => $payload];
  }

}
