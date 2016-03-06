<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\EntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\ResourceObject;
use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\JsonApiEntityReference;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class EntityNormalizer extends NormalizerBase implements DenormalizerInterface {

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
    if (!isset($record['meta'])) {
      $record['meta'] = [];
    }
    $record['meta'][$key] = $value;
  }

  protected function bundleLabel($entityTypeDefinition) {
    return strtolower(preg_replace('/\s/', '-', $entityTypeDefinition->getBundleLabel()));
  }

  // This exposes fields (in the JSONAPI sense) that are not fields
  // in the Drupal sense. They are eligible to be included as record
  // attributes or serve as the record's `type` or `id`.
  protected function coreFields($object) {
    $bundleLabel = $this->bundleLabel($object->getEntityType());
    return [
      'entity-type' => $object->getEntityTypeId(),
      'id' => (int)$object->id(),
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

  protected function normalizeFields($object, $context, $req) {
    $attributes = [];
    $relationships = [];
    $unused = [];
    $coreFields = $this->coreFields($object);

    $fields = $req->fieldsFor($coreFields['entity-type'], $coreFields['bundle']);
    if (count($context['jsonapi_path']) == 0) {
      // Top level entries expose their defaultInclude configuration to their children
      $context['jsonapi_default_include'] = $req->defaultIncludeFor($coreFields['entity-type'], $coreFields['bundle']);
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
        if ($req->shouldIncludeField($type, $outputName)) {
          $innerContext = $context;
          $innerContext['jsonapi_path'][] = $outputName;
          $child = $this->serializer->normalize($field, 'jsonapi', $innerContext);
          if ($field instanceof EntityReferenceFieldItemList) {
            if (is_array($child)) {
              $relationships[$outputName] = ["data" => array_map(function($elt){
                if ($elt instanceof JsonApiEntityReference) {
                  return $elt->normalize();
                }
              }, $child)];
            } else if ($child instanceof JsonApiEntityReference) {
              $relationships[$outputName] = ["data" => $child->normalize()];
            } else {
              $relationships[$outputName] = ["data" => $child];
            }
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

    if ($req->debugEnabled()) {
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
    $req = $context['jsonapi_request'];

    $record = $this->normalizeFields($object, $context, $req);
    $record['id'] = $record['attributes']['id'];
    unset($record['attributes']['id']);
    $record['type'] = $record['attributes']['type'];
    unset($record['attributes']['type']);

    if ($req->requestType() == 'relationship') {
      return (new JsonApiEntityReference($record))->normalize();
    } else if (count($context['jsonapi_path']) == 0) {
      return $record;
    } else {
      if ($req->shouldInclude($context['jsonapi_path'], $context['jsonapi_default_include'])) {
        $doc->addIncluded($record);
      }
      return new JsonApiEntityReference($record);
    }

  }

  protected function jsonBundleKey($config, $bundleKey, $bundleLabel) {
    foreach($config['fields'] as $drupalName => $jsonConfig) {
      // These are all ways people are allowed to reference the
      // bundleId in their configuration. It doesn't matter which we
      // find as long as we find one.
      if ($drupalName == $bundleLabel || $drupalName == $bundleKey || $drupalName == 'bundle') {
        return $jsonConfig['as'];
      }
    }
  }

  protected function identifyBundle($payload, $config, $entityType, $entityTypeDefinition) {
    $validBundles = array_keys($this->entityManager->getBundleInfo($entityType));
    if (isset($config['bundles']) && count($config['bundles']) > 0) {
      $validBundles = array_intersect($validBundles, $config['bundles']);
    }

    $bundleKey = $entityTypeDefinition->getKey('bundle');
    $bundleLabel = $this->bundleLabel($entityTypeDefinition);
    $jsonBundleKey = $this->jsonBundleKey($config, $bundleKey, $bundleLabel);

    if (!$jsonBundleKey) {
      // User hasn't exposed bundle name into API
      if (count($validBundles) == 1) {
        // If there's only one valid choice, we're good.
        return [
          "id" => $validBundles[0],
          "jsonKey" => null,
          "key" => $bundleKey
        ];
      } else {
        throw new UnexpectedValueException("This endpoint encompasses multiple Drupal bundles, but you haven't exposed the bundle name in your API. So we can't tell what type of entity you're trying to create.");
      }
    }

    if ($jsonBundleKey == 'type' || $jsonBundleKey == 'id') {
      $source = $payload;
    } else {
      $source = $payload['attributes'];
    }

    if (!isset($source[$jsonBundleKey])) {
      // User didn't send us a value for bundle
      if (count($validBundles) == 1) {
        // If there's only one valid choice, we're good.
        return [
          "id" => $validBundles[0],
          "jsonKey" => $jsonBundleKey,
          "key" => $bundleKey
        ];
      } else {
        throw new UnexpectedValueException("You must specificy " . $jsonBundleKey . '. Valid values are: ' . join(', ', $validBundles));
      }
    }

    $bundleId = $source[$jsonBundleKey];

    if (!in_array($bundleId, $validBundles)) {
      throw new UnexpectedValueException($bundleId . " is not a valid value for " . $jsonBundleKey . '. Valid values are: ' . join(', ', $validBundles));
    }

    return [
      "id" => $bundleId,
      "jsonKey" => $jsonBundleKey,
      "key" => $bundleKey
    ];
  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    $doc = $context['jsonapi_document'];
    $req = $context['jsonapi_request'];
    if ($req->requestType() == 'relationship') {
      return $this->denormalizeRelationship($req, $doc, $payload);
    } else {
      return $this->denormalizeResource($req, $doc, $payload);
    }
  }

  protected function denormalizeRelationship($req, $doc, $payload) {
    if (isset($payload['id'])) {
      return [ "target_id" => $payload['id']];
    } else {
      return [ "target_id" => null ];
    }
  }

  protected function denormalizeResource($req, $doc, $payload) {
    $entityType = $req->entityType();
    $entityTypeDefinition = $this->entityManager->getDefinition($req->entityType(), FALSE);
    $bundle = $this->identifyBundle($payload, $req->config()['entryPoint'], $entityType, $entityTypeDefinition);
    $fieldDefinitions = $this->entityManager->getFieldDefinitions($req->entityType(), $bundle['id']);
    $inputs = [];
    $sources = [];

    foreach($req->fieldsFor($req->entityType(), $bundle['id']) as $drupalName => $jsonConfig) {
      $jsonName = $jsonConfig['as'];
      if ($drupalName == 'id') {
        $drupalName = $entityTypeDefinition->getKey('id');
      }
      if ($jsonName == $bundle['jsonKey']) {
        $drupalName = $bundle['key'];
      }
      $value = [];
      $this->denormalizeField($payload, $drupalName, $jsonName, $jsonConfig, $fieldDefinitions[$drupalName]->getFieldStorageDefinition(), $value);
      if (array_key_exists('result', $value)) {
        $inputs[$drupalName] = $value['result'];
        $sources[$drupalName] = $jsonName;
      }
    }
    return new ResourceObject($sources, $inputs, $req->storage());
  }

  protected function denormalizeField($payload, $drupalName, $jsonName, $jsonConfig, $fieldDefinition, &$output) {
    if ($jsonName == 'type') {
      $output['result'] = $payload['type'];
    } else if ($jsonName == 'id' && isset($payload['id'])) {
      $output['result'] = $payload['id'];
    } else if (isset($payload['attributes']) && array_key_exists($jsonName, $payload['attributes'])) {
      $output['result'] = $payload['attributes'][$jsonName];
    } else if (isset($payload['relationships'][$jsonName]) && array_key_exists('data', $payload['relationships'][$jsonName])) {
      if ($fieldDefinition->isMultiple()) {
        $output['result'] = array_map(function($elt){ return ["target_id" => $elt['id']]; }, $payload['relationships'][$jsonName]['data']);
      } else {
        if (isset($payload['relationships'][$jsonName]['data']['id'])) {
          $output['result'] = ["target_id" => $payload['relationships'][$jsonName]['data']['id'] ];
        } else {
          $output['result'] = ["target_id" => null];
        }
      }
    }

    if (array_key_exists('result', $output)) {
      if (["value"] == $fieldDefinition->getPropertyNames()) {
        $downstreamClass = null;
        switch($fieldDefinition->getPropertyDefinition('value')->getDataType()) {
        case "boolean":
          $downstreamClass = 'Drupal\Core\TypedData\Plugin\DataType\BooleanData';
          break;
        case "timestamp":
          $downstreamClass = 'Drupal\Core\TypedData\Plugin\DataType\Timestamp';
          break;
        case "integer":
          $downstreamClass = 'Drupal\Core\TypedData\Plugin\DataType\IntegerData';
          break;
        }
        if ($downstreamClass) {
          $output['result'] = $this->serializer->denormalize($output['result'], $downstreamClass, 'jsonapi', []);
        }
      }
    }
    return $output;
  }

}
