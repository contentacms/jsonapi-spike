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
    if (!$record['meta']) {
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

  protected function identifyBundle($payload, $config, $entityType, $entityTypeDefinition) {
    $bundleKey = $entityTypeDefinition->getKey('bundle');
    $bundleLabel = $this->bundleLabel($entityTypeDefinition);
    $jsonBundleKey = null;
    foreach($config['fields'] as $drupalName => $jsonConfig) {
      // These are all ways people are allowed to reference the
      // bundleId in their configuration. It doesn't matter which we
      // find as long as we find one.
      if ($drupalName == $bundleLabel || $drupalName == $bundleKey || $drupalName == 'bundle') {
        $jsonBundleKey = $jsonConfig['as'];
        break;
      }
    }

    // If our endpoint is limited to a single bundle, we can quit
    // early already knowing the answer.
    if (isset($config['bundles']) && count($config['bundles']) == 1) {
      return [
        "id" => $config['bundles'][0],
        "jsonKey" => $jsonBundleKey,
        "key" => $bundleKey
      ];
    }

    if (!isset($jsonBundleKey)) {
      throw new UnexpectedValueException("This endpoint encompasses multiple Drupal bundles, but you haven't exposed the bundle name in your API. So we can't tell what type of entity you're trying to create.");
    }

    if ($jsonBundleKey == 'type' || $jsonBundleKey == 'id') {
      $source = $payload;
    } else {
      $source = $payload['attributes'];
    }

    if (!isset($source[$jsonBundleKey])) {
      throw new UnexpectedValueException("You must specificy " . $jsonBundleKey);
    }

    $bundleId = $source[$jsonBundleKey];

    return [
      "id" => $bundleId,
      "jsonKey" => $jsonBundleKey,
      "key" => $bundleKey
    ];
  }

  public function denormalize($payload, $class, $format = NULL, array $context = []) {
    $doc = $context['jsonapi_document'];
    $req = $context['jsonapi_request'];

    $entityTypeDefinition = $this->entityManager->getDefinition($req->entityType(), FALSE);
    $bundle = $this->identifyBundle($payload, $req->config()['entryPoint'], $entityType, $entityTypeDefinition);
    $fieldDefinitions = $this->entityManager->getFieldDefinitions($req->entityType(), $bundle['id']);
    $inputs = [];

    foreach($req->fieldsFor($req->entityType(), $bundle['id']) as $drupalName => $jsonConfig) {
      $jsonName = $jsonConfig['as'];
      if ($jsonName == $bundle['jsonKey']) {
        // We already grabbed the bundle ID
        continue;
      }
      if ($jsonName == 'type') {
        $inputs[$drupalName] = $payload['type'];
      } else if ($jsonName == 'id' && isset($payload['id'])) {
        $inputs[$drupalName] = $payload['id'];
      } else if (array_key_exists($jsonName, $payload['attributes'])) {
         $inputs[$drupalName] = $payload['attributes'][$jsonName];
      } else if (isset($payload['relationships'][$jsonName]) && array_key_exists('data', $payload['relationships'][$jsonName])) {
        if ($fieldDefinitions[$drupalName]->getFieldStorageDefinition()->isMultiple()) {
          $inputs[$drupalName] = array_map(function($elt){ return ["target_id" => $elt['id']]; }, $payload['relationships'][$jsonName]['data']);
        } else {
          if (isset($payload['relationships'][$jsonName]['data']['id'])) {
            $inputs[$drupalName] = ["target_id" => $payload['relationships'][$jsonName]['data']['id'] ];
          } else {
            $inputs[$drupalName] = ["target_id" => null];
          }
        }
      }
    }

    if (isset($inputs['id'])) {
      $idKey = $entityTypeDefinition->getKey('id');
      $inputs[$idKey] = $inputs['id'];
      unset($inputs['id']);
    }

    $inputs[$bundle['key']] = $bundle['id'];
    return new ResourceObject($payload, $inputs, $req->storage());
  }

}
