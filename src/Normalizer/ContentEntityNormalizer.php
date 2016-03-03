<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\ResourceObject;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\JsonApiEntityReference;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class ContentEntityNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var array
   */
  protected $supportedInterfaceOrClass = ['Drupal\Core\Entity\EntityInterface'];
  protected $format = array('jsonapi');


  public function __construct($entityManager) {
    $this->entityManager = $entityManager;
  }

  protected function addMeta(&$record, $key, $value) {
    if (!$record['meta']) {
      $record['meta'] = [];
    }
    $record['meta'][$key] = $value;
  }

  // This exposes fields (in the JSONAPI sense) that are not fields
  // in the Drupal sense. They are eligible to be included as record
  // attributes or serve as the record's `type` or `id`.
  protected function coreFields($object) {
    $bundleLabel = strtolower(preg_replace('/\s/', '-', $object->getEntityType()->getBundleLabel()));
    return [
      'entity-type' => $object->getEntityTypeId(),
      'id' => $object->id(),
      'bundle-label' => $bundleLabel,
      'bundle' => $object->bundle(),
      $bundleLabel => $object->bundle()
    ];
  }

  protected function findType($object, $coreFields, $fields) {
    foreach($fields as $key => $value) {
      if ($value['as'] == 'type') {
        if (isset($coreFields[$key])) {
          return $coreFields[$key];
        }
        return $this->serializer->normalize($object->get($key), 'jsonapi', null);
      }
    }
  }

  protected function normalizeFields($object, $context, $doc) {
    $attributes = [];
    $relationships = [];
    $unused = [];
    $coreFields = $this->coreFields($object);

    $fields = $doc->fieldsFor($coreFields['entity-type'], $coreFields['bundle']);
    if (count($context['jsonapi_path']) == 0) {
      // Top level entries expose their defaultInclude configuration to their children
      $context['jsonapi_default_include'] = $doc->defaultIncludeFor($coreFields['entity-type'], $coreFields['bundle']);
    }

    // We need to look ahead and discover the final JSONAPI type
    // because it will guide any sparse fieldset filtering
    $type = $this->findType($object, $coreFields, $fields);

    foreach ($object as $name => $field) {
      if (!$field->access('view', $context['account'])) {
        continue;
      }

      if (isset($fields[$name])) {
        $outputName = $fields[$name]["as"];
        if ($doc->shouldIncludeField($type, $outputName)) {
          $innerContext = $context;
          $innerContext['jsonapi_path'][] = $outputName;
          $child = $this->serializer->normalize($field, 'jsonapi', $innerContext);
          if ($child instanceof JsonApiEntityReference) {
            $relationships[$outputName] = $child->normalize();
          } else {
            $attributes[$outputName] = $child;
          }
        }
      } else {
        $unused[] = $name;
      }
    }

    foreach($coreFields as $name => $value) {
      if (isset($fields[$name])) {
        $outputName = $fields[$name]["as"];
        $attributes[$outputName] = $value;
      } else {
        $unused[] = $name;
      }
    }

    $record = ['attributes' => &$attributes];
    if (count($relationships) > 0) {
      $record['relationships'] = &$relationships;
    }

    if ($doc->debugEnabled()) {
      $this->addMeta($record, 'unused-fields', $unused);
    }

    return $record;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = array()) {
    $context += array(
      'account' => NULL,
      'jsonapi_path' => [] # Defaults to top level path, unless we've inherited one already.
    );

    $doc = $context['jsonapi_document'];

    $record = $this->normalizeFields($object, $context, $doc);
    $record['id'] = $record['attributes']['id'];
    unset($record['attributes']['id']);
    $record['type'] = $record['attributes']['type'];
    unset($record['attributes']['type']);

    if (count($context['jsonapi_path']) == 0) {
      return $record;
    } else {
      if ($doc->shouldInclude($context['jsonapi_path'], $context['jsonapi_default_include'])) {
        $doc->addIncluded($record);
      }
      return new JsonApiEntityReference($record);
    }

  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    $config = $context['config']['entryPoint'];
    $entityType = $config['entityType'];
    $entityTypeDefinition = $this->entityManager->getDefinition($config['entityType'], FALSE);
    $bundleKey = $entityTypeDefinition->getKey('bundle');

    // If this endpoint only handles a single bundle, we don't need to discover the bundleId from the request.
    if (isset($config['bundles']) && count($config['bundles']) == 1) {
      $bundleId = $config['bundles'][0];
    } else {
      $bundleLabel = strtolower($entityTypeDefinition->getBundleLabel());
      $fields = $config['fields'];

      // The user's fields config can do either:
      //    "bundle" => "type"
      // or
      //    "vocabulary" => "type"
      // where "vocabulary" happens to be the bundle label for this
      // entity type. So we look for either here.
      foreach([$bundleLabel, "bundle"] as $candidate) {
        if (isset($fields[$candidate])) {
          $jsonBundleKey = $fields[$candidate]['as'];
          break;
        }
      }

      if (!isset($jsonBundleKey)) {
        throw new UnexpectedValueException("The configuration for this endpoint needs to include either 'bundle' or " . $bundleLabel . " in its fields so we can disambiguate what type of entity you are trying to submit");
      }

      if (!isset($payload[$jsonBundleKey])) {
        throw new UnexpectedValueException("You must include a value for " . $jsonBundleKey . " so we know what type you are submitting.");
      }

      $bundleId = $payload[$jsonBundleKey];
    }


    return $context['storage']->create([
      $bundleKey => $bundleId
    ]);
  }

}
