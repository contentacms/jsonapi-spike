<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\EndpointController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\jsonapi\DocumentContext;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\jsonapi\Encoder\JsonApiEncoder;
use Symfony\Component\HttpFoundation\Request;
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
      $args = [
        "config" => $this->config->forEndpoint($scope, $endpoint),
        "options" => $this->optionsFor($request),
        "id" => $id,
        "related" => $related,
      ];
      $content = $request->getContent();
      if ($content) {
        $args['requestDocument'] = $this->serializer->deserialize($content, 'Drupal\jsonapi\DocumentContext', 'jsonapi', [
          "options" => $args['options'],
          "config" => $args['config']
        ]);
      }
      $response = $this->{$handler}($args);
    } else {
      $response = this.errorResponse(500, "Not implemented", $handler . " endpoint not implemented.");
    }
    if ($response instanceof Response && $data = $response->getResponseData()) {
      $output = $this->serializer->serialize($data, "jsonapi");
      $response->setContent($output);
      $response->headers->set('Content-Type', 'application/vnd.api+json');
    }
    return $response;
  }

  protected function handleIndividualGET($req) {
    $entityType = $req['config']['entryPoint']['entityType'];
    $entity = $this->entityManager->getStorage($entityType)->load($req['id']);

    if (!$entity) {
      return this.errorResponse(404, $entityType . " not found", "where id=" . $req['id']);
    }


    if (!$entity->access('view')) {
      return this.errorResponse(403, "Access denied to " . $entityType, "where id=" . $req['id']);
    }
    return new Response(new DocumentContext($entity, $req['config'], $req['options']), 200);
  }

  protected function handleCollectionGET($req) {
    $entityType = $req['config']['entryPoint']['entityType'];
    $query = $this->entityQuery->get($entityType);
    $ids = $query->execute();
    $entities = $this->entityManager->getStorage($entityType)->loadMultiple($id);
    $output = array_values(array_filter($entities, function($entity) { return $entity->access('view'); }));
    return new Response(new DocumentContext($output, $req['config'], $req['options'], 200));
  }

  protected function handleCollectionPOST($req) {
    return new Response(["errors" => [[
      "title" => "Implementation in progress",
      "description" => $req['requestDocument']
    ]]], 201);
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

  protected function errorResponse($statusCode, $title, $detail) {
    # http://jsonapi.org/format/#error-objects
    return new Response(["errors" => [[
      "title" => $title,
      "detail" => $detail
    ]]], $statusCode);
  }

}