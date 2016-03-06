<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\TimestampNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class TimestampNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\TypedData\Plugin\DataType\Timestamp';
  protected $format = array('jsonapi');

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $date = $object->getDateTime();
    if ($date) {
      return $date->format(\DateTime::ATOM);
    }
  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    if ($payload) {
      $date = \DateTime::createFromFormat('Y-m-d\TH:i:s.uP', $payload);
      if ($date) {
        return ["value" => $date->getTimestamp()];
      } else {
        throw new UnexpectedValueException("Invalid ISO8601 Date: " . $payload);
      }
    } else {
      return ["value" => null];
    }
  }

}
