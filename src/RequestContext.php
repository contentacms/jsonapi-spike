<?php

namespace Drupal\jsonapi;


class RequestContext {
  public function __construct($moduleConfig, $serializer, $entityManager, $scope, $endpoint, $route, $request, $id, $related) {
    $this->moduleConfig = $moduleConfig;
    $this->serializer = $serializer;
    $this->entityManager = $entityManager;
    $this->scope = $scope;
    $this->endpoint = $endpoint;
    $this->request = $request;
    $this->route = $route;
    $this->_id = $id;
    $this->_related = $related;
  }

  public function requestType() {
    return $requestType = last(explode(".", $this->route));
  }

  public function handlerName() {
    return 'handle' . ucwords($this->requestType()) . $this->request->getMethod();
  }

  public function storage() {
    return $this->entityManager->getStorage($this->entityType());
  }

  public function loadEntity() {
    if (!isset($this->_entity)) {
      $entity = $this->storage()->load($this->id());
      if (!$entity || !$this->inAllowedBundles($entity)) {
        $this->_entity = null;
      } else {
        $this->_entity = $entity;
      }
    }
    return $this->_entity;
  }

  protected function inAllowedBundles($entity) {
    $config = $this->config();
    if (isset($config['entryPoint']['bundles'])) {
      $bundles = $config['entryPoint']['bundles'];
      if (count($bundles) > 0) {
        if (!in_array($entity->bundle(), $bundles)) {
          throw new \Exception("bundle violation: " . join(',',$config['entryPoint']['bundles']) . " != " . $entity->bundle());
          return false;
        }
      }
    }
    return true;
  }

  // the relevant part of the JSONAPI module config for this request
  public function config() {
    if (!isset($this->_config)) {
      $this->_config = $this->moduleConfig->forEndpoint($this->scope, $this->endpoint);
    }
    return $this->_config;
  }

  // client-provided options for this request
  public function options() {
    if (!isset($this->_options)) {
      $output = [];
      foreach($this->request->query->all() as $key => $value) {
        if ($key == 'debug') {
          $output['debug'] = true;
        }
        if ($key == 'include') {
          if ($value == "") {
            $output['include'] = [];
          } else {
            $output['include'] = array_map(function($path) {
              return explode('.', $path);
            }, explode(',', $value));
          }
        }
        if ($key == 'fields') {
          $output['fields'] = array_map(function($fieldList){ return explode(',', $fieldList); }, $value);
        }
        if ($key == 'filter') {
          $output['filter'] = array_map(function($valueList){ return explode(',', $valueList); }, $value);
        }
        if ($key == 'sort') {
          $output['sort'] = explode(',', $value);
        }
      }
      $this->_options = $output;
    }
    return $this->_options;
  }

  public function id() {
    return $this->_id;
  }

  // If we're handling a relationship endpoint, the name of the relationship
  public function related() {
    return $this->_related;
  }

  // If we're handling a relationship endpoint, the name of the relationship in Drupal's fields.
  public function relatedAsDrupalField() {
    if (!isset($this->_relatedAsDrupalField)) {
      $this->_relatedAsDrupalField = $this->jsonFieldToDrupalField($this->loadEntity()->bundle(), $this->related());
    }
    return $this->_relatedAsDrupalField;
  }

  public function isOneToManyRelationship() {
    return $this->loadEntity()->getFieldDefinition($this->relatedAsDrupalField())->getFieldStorageDefinition()->isMultiple();
  }

  public function entityType() {
    return $this->config()['entryPoint']['entityType'];
  }

  // The JSONAPI document that was sent by the client as part of this
  // request.
  public function requestDocument() {
    if (!isset($this->_requestDocument)){
      $content = $this->request->getContent();
      if ($content) {
        $this->_requestDocument = $this->serializer->deserialize(
          $content,
          'Drupal\jsonapi\DocumentContext',
          'jsonapi',
          ["jsonapi_request" => $this]
        );
      } else {
        $this->_requestDocument = null;
      }
    }
    return $this->_requestDocument;
  }

  // TODO: track top-level entity type as part of $path so that
  // $defaultInclude doesn't need to be exposed.
  public function shouldInclude($path, $defaultInclude) {
    if (isset($this->options()['include'])) {
      $include = $this->options()['include'];
    } else {
      $include = $defaultInclude;
    }
    foreach($include as $included) {
      if ($path == array_slice($included, 0, count($path))) {
        return true;
      }
    }
  }

  protected function endpointFor($entityType, $bundleId) {
    $scopeConfig = $this->config()['scope'];
    if (isset($scopeConfig['bundles'][$entityType][$bundleId])) {
      return $scopeConfig['bundles'][$entityType][$bundleId];
    }
    if (isset($scopeConfig['entities'][$entityType])) {
      return $scopeConfig['entities'][$entityType];
    }
  }

  protected function configFor($entityType, $bundleId) {
    $endpointConfig = $this->config()['scope']['endpoints'][$this->endpointFor($entityType, $bundleId)];
    if (isset($endpointConfig['extensions'][$bundleId])) {
      return $endpointConfig['extensions'][$bundleId];
    } else {
      return $endpointConfig;
    }
  }

  public function fieldsFor($entityType, $bundleId) {
    return $this->configFor($entityType, $bundleId)['fields'];
  }

  // TODO not needed or not public after refactoring jsonapi_path handling
  public function defaultIncludeFor($entityType, $bundleId) {
    return $this->configFor($entityType, $bundleId)['defaultInclude'];
  }

  protected function bundleLabel($entityTypeDefinition) {
    return strtolower(preg_replace('/\s/', '-', $entityTypeDefinition->getBundleLabel()));
  }

  protected function extendedBundles($entityType) {
    $endpointConfig = $this->config()['scope']['endpoints'][$this->endpointFor($entityType, $bundleId)];
    if (isset($endpointConfig['extensions'])) {
      return array_keys($endpointConfig['extensions']);
    } else {
      return [];
    }
  }

  // finds any number of matching drupal fields (scanning across all bundles)
  public function jsonFieldToDrupalFields($jsonField) {
    $bundles = $this->extendedBundles($this->entityType());
    array_push($bundles, null); // this one checks the top-level, un-extended configuration
    $output = [];
    foreach($bundles as $bundle) {
      $drupalField = $this->jsonFieldToDrupalfield($bundle, $jsonField);
      if ($drupalField) {
        $output[$drupalField] = true;
      }
    }
    return array_keys($output);
  }

  public function jsonFieldToDrupalField($bundleId, $jsonField) {
    $entityTypeDefinition = $this->entityManager->getDefinition($this->entityType(), FALSE);
    $bundleLabel = $this->bundleLabel($entityTypeDefinition);

    foreach($this->fieldsFor($this->entityType(), $bundleId) as $drupalName => $jsonConfig) {
      if ($jsonConfig['as'] == $jsonField) {
        if ($drupalName == $bundleLabel || $drupalName == 'bundle') {
          return $entityTypeDefinition->getKey('bundle');
        } else if ($drupalName == 'id') {
          return $entityTypeDefinition->getKey('id');
        } else {
          return $drupalName;
        }
      }
    }
  }

  public function shouldIncludeField($type, $field) {
    return $field == 'id' || !isset($this->options()['fields'][$type]) || in_array($field, $this->options()['fields'][$type]);
  }

  public function debugEnabled() {
    return isset($this->options()['debug']) && $this->options()['debug'];
  }

}

// This cleans up a strict mode warning, because the 'end' function
// takes an array reference a mutates the underlying array's internal
// pointer. This wraps it in a function that calls by value. Oh,
// PHP...
function last( $array ) { return end( $array ); }
