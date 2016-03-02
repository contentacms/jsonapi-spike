<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\DocumentContextNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DocumentContextNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\jsonapi\DocumentContext';

  /**
   * {@inheritdoc}
   */
  public function normalize($document, $format = NULL, array $context = array()) {
    $context['jsonapi_document'] = $document;
    $attributes = [
      "data" => $this->serializer->normalize($document->data, $format, $context),
    ];
    $meta = $document->meta();
    if ($meta) {
      $attributes["meta"] = $meta;
    }
    $included = $document->allIncluded();
    if (count($included) > 0) {
      $attributes["included"] = $included;
    }
    return $attributes;
  }

  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return $data;
  }

}
