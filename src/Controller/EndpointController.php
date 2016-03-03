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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Drupal\Component\Serialization\Json;

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

  // We don't want to let exceptions bubble out to the rest of Drupal,
  // because it will send back HTML, and we want to send back JSON.
  protected function withErrorTrap($fn) {
    try {
      return $fn();
    } catch(UnexpectedValueException $exception) {
      return $this->errorResponse(400, "Bad request", $exception->getMessage());
    } catch (\Exception $exception) {
      error_log(sprintf('Uncaught PHP Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine()));
      return $this->errorResponse(500, "Unexpected error", "See server logs");
    }
  }

  public function handle(Request $request, $_route, $scope, $endpoint, $id=null, $related=null) {
    return $this->withErrorTrap(function () use ($request, $_route, $scope, $endpoint, $id, $related) {
      $handler = 'handle' . ucwords(end(explode(".", $_route))) . $request->getMethod();
      if (is_callable([$this, $handler])) {
        $args = [
          "config" => $this->config->forEndpoint($scope, $endpoint),
          "options" => $this->optionsFor($request),
          "id" => $id,
          "related" => $related,
        ];
        $args['entityType'] = $args['config']['entryPoint']['entityType'];
        $args['storage'] = $this->entityManager->getStorage($args['entityType']);

        $content = $request->getContent();
        if ($content) {
          $args['requestDocument'] = $this->serializer->deserialize($content, 'Drupal\jsonapi\DocumentContext', 'jsonapi', $args);
        }
        $response = $this->{$handler}($args);
      } else {
        $response = $this->errorResponse(500, "Not implemented", $handler . " endpoint not implemented.");
      }
      if ($response instanceof Response && $data = $response->getResponseData()) {
        $output = $this->serializer->serialize($data, "jsonapi");
        $response->setContent($output);
        $response->headers->set('Content-Type', 'application/vnd.api+json');
      }
      return $response;
    });
  }

  protected function handleIndividualGET($req) {
    $entity = $req['storage']->load($req['id']);

    if (!$entity) {
      return $this->errorResponse(404, $req['entityType'] . " not found", "where id=" . $req['id']);
    }


    if (!$entity->access('view')) {
      return $this->errorResponse(403, "Access denied to " . $req['entityType'], "where id=" . $req['id']);
    }
    return new Response(new DocumentContext($entity, $req['config'], $req['options']), 200);
  }

  protected function handleCollectionGET($req) {
    $query = $this->entityQuery->get($req['entityType']);
    $bundles = $req['config']['entryPoint']['bundles'];
    if ($bundles) {
      $entity_type = $this->entityManager->getDefinition($req['entityType'], FALSE);
      $query->condition($entity_type->getKey('bundle'), $bundles, 'IN');
    }
    $entities = $req['storage']->loadMultiple($query->execute());
    $output = array_values(array_filter($entities, function($entity) { return $entity->access('view'); }));
    return new Response(new DocumentContext($output, $req['config'], $req['options'], 200));
  }

  protected function handleCollectionPOST($req) {
    $doc = $req['requestDocument'];
    if (!$doc || is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "POST to a collection endpoint must contain a single resource");
    }

    $resource = $doc->data;
    if (!is_null($resource->id)) {
      // http://jsonapi.org/format/#crud-creating-client-ids
      return $this->errorResponse(403, "Forbidden", "This server does not accept client-generated IDs");
    }

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
    $body = Json::encode([
      "errors" => [[
        "title" => $title,
        "detail" => $detail
      ]]]);

    // This is a SymfonyResponse and not our own Response type so that
    // it's independent of our serialization step (since errors can
    // happen during that step itself)
    return new SymfonyResponse($body, $statusCode, ["Content-Type" => 'application/vnd.api+json'] );
  }

}