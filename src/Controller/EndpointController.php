<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\EndpointController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\jsonapi\DocumentContext;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

class EndpointController implements ContainerInjectionInterface {

  public function __construct($serializer, $entityQuery, $entityManager) {
    $this->config = new HardCodedConfig();
    $this->serializer = $serializer;
    $this->entityQuery = $entityQuery;
    $this->entityManager = $entityManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('serializer'),
      $container->get('entity.query'),
      $container->get('entity.manager')
    );
  }

  public function handle(Request $request, $_route, $scope, $endpoint, $id=null, $related=null) {
    $handler = 'handle' . ucwords(end(explode(".", $_route))) . $request->getMethod();
    if (is_callable([$this, $handler])) {
      $config = $this->config->forEndpoint($scope, $endpoint);
      $options = $this->optionsFor($request);
      $response = $this->{$handler}($config, $options, $id, $related);
    } else {
      $response = new Response(["errors" => [["title" => "Not implemented", "detail" => $handler . " endpoint not implemented."]]], 500);

    }
    if ($response instanceof Response && $data = $response->getResponseData()) {
      $output = $this->serializer->serialize($data, "jsonapi");
      $response->setContent($output);
      $response->headers->set('Content-Type', 'application/vnd.api+json');
    }
    return $response;
  }

  protected function handleIndividualGET($config, $options, $id) {
    $entityType = $config['entryPoint']['entityType'];
    $entity = $this->entityManager->getStorage($entityType)->load($id);

    if (!$entity) {
      # http://jsonapi.org/format/#error-objects
      return new Response(["errors" => [["title" => $entityType . " not found", "detail" => "where id=" . $id]]], 404);
    }


    if (!$entity->access('view')) {
      # http://jsonapi.org/format/#error-objects
      return new Response(["errors" => [["title" => "Access denied to " . $entityType, "detail" => "where id=" . $id . "."]]], 403);
    }
    return new Response(new DocumentContext($entity, $config, $options), 200);
  }

  protected function handleCollectionGET($config, $options) {
    $entityType = $config['entryPoint']['entityType'];
    $query = $this->entityQuery->get($entityType);
    $ids = $query->execute();
    $entities = $this->entityManager->getStorage($entityType)->loadMultiple($id);
    $output = array_values(array_filter($entities, function($entity) { return $entity->access('view'); }));
    return new Response(new DocumentContext($output, $config, $options, 200));
  }

  protected function handleCollectionPOST($config, $options) {
    return new Response(["errors" => [["title" => "Woah"]]], 500);
  }

  protected function optionsFor($request) {
    $output = [];
    foreach($request->query->all() as $key => $value) {
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
    }
    return $output;
  }

}