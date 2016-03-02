<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\DocumentContextNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;
use Drupal\jsonapi\ResourceObject;
use Drupal\serialization\Normalizer\NormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

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

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    if (!isset($payload['data'])) {
      throw new UnexpectedValueException("Incoming JSONAPI document had no 'data' member");
    }

    $data = $payload['data'];

    // PHP considers both JSON list and JSON object as arrays. :-(
    if (!is_array($data)) {
      throw new UnexpectedValueException("'data' member was not a list or object");
    }

    if (isset($data['type'])) {
      $document = new $class(new ResourceObject($data), $context['config'], $context['options']);
    } else {
      // Maybe a list, but we need to check.
      foreach($data as $item) {
        if (!isset($item['type'])) {
          throw new UnexpectedValueException("data must contain either a single object with a type, or a list of objects with types");
        }
      }
      $objects = array_map($data, function($elt) {
        return new ResourceObject($elt);
      });
      $document = new $class($objects, $context['config'], $context['options']);
    }
    return $document;
  }

}
