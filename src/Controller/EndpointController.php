<?php
/**
 * @file
 * Contains \Drupal\jsonapi\Controller\EndpointController.
 */

namespace Drupal\jsonapi\Controller;

use Drupal\jsonapi\Response;
use Drupal\jsonapi\RequestContext;
use Drupal\jsonapi\DocumentContext;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\jsonapi\Encoder\JsonApiEncoder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
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

    $req = new RequestContext(
      $this->config,
      $this->serializer,
      $this->entityManager,
      $scope,
      $endpoint,
      $_route,
      $request,
      $id,
      $related
    );

    return $this->withErrorTrap(function () use ($req) {
      $handler = $req->handlerName();
      if (is_callable([$this, $handler])) {
        $response = $this->{$handler}($req);
      } else {
        $response = $this->errorResponse(500, "Not implemented", $handler . " endpoint not implemented.");
      }
      if ($response instanceof Response && $data = $response->getResponseData()) {
        $output = $this->serializer->serialize($data, "jsonapi", ["jsonapi_request" => $req]);
        $response->setContent($output);
        $response->headers->set('Content-Type', 'application/vnd.api+json');
      }
      return $response;
    });
  }

  protected function handleIndividualGET($req) {
    $entity = $req->loadEntity();

    if (!$entity) {
      return $this->errorResponse(404, $req->entityType() . " not found", "where id=" . $req->id());
    }

    if (!$entity->access('view')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }
    return new Response(new DocumentContext($req, $entity), 200);
  }

  protected function handleIndividualDELETE($req) {
    $entity = $req->loadEntity();

    if (!$entity) {
      return $this->errorResponse(404, $req->entityType() . " not found", "where id=" . $req->id());
    }

    if (!$entity->access('delete')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }
    $req->storage()->delete([$entity]);
    return new SymfonyResponse(null, 204);
  }

  protected function handleIndividualPATCH($req) {
    $entity = $req->loadEntity();

    if (!$entity) {
      return $this->errorResponse(404, $req->entityType() . " not found", "where id=" . $req->id());
    }

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }

    $doc = $req->requestDocument();
    if (!$doc || is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "PATCH to an individual endpoint must contain a single resource");
    }

    $originalId = $entity->id();

    $drupalInputs = $doc->data->drupal;
    foreach ($entity as $name => $field) {
      // Ignore read-only fields instead of throwing a 403. This is
      // a nicer behavior for clients, and as far as I can tell it
      // is spec compliant. It's true that PATCH implies the
      // client's intent to change these fields, but the server is
      // also free to make its own arbitrary changes in response, as
      // long as they're reflected in the reply.
      if (array_key_exists($name, $drupalInputs) && $field->access('edit')) {
        $entity->set($name, $drupalInputs[$name]);
      }
    }

    if ($originalId != $entity->id()) {
      return $this->errorResponse(403, "Forbidden", "You may not change an object's id");
    }
    $req->storage()->save($entity);
    $doc->data = $entity;

    // TODO: the spec says we should return 200 if we changed anything
    // other than what the user sent, and 204 otherwise. (If the
    // "changed" field is exposed, that's an example of needing a
    // 200).
    return new Response($doc, 200);
  }


  protected function handleCollectionGET($req) {
    $query = $this->entityQuery->get($req->entityType());
    if (isset($req->config()['entryPoint']['bundles'])) {
      $bundles = $req->config()['entryPoint']['bundles'];
      if (count($bundles) > 0) {
        $entity_type = $this->entityManager->getDefinition($req->entityType(), FALSE);
        $query->condition($entity_type->getKey('bundle'), $bundles, 'IN');
      }
    }
    if (isset($req->options()['filter'])) {
      foreach($req->options()['filter'] as $name => $values) {
        $drupalField = $req->jsonFieldToDrupalField(null, $name);
        if (!$drupalField) {
          return $this->errorResponse(400, "Bad Request", "Can't filter on " . $name);
        }
        $query->condition($drupalField, $values, 'IN');
      }
    }
    $entities = $req->storage()->loadMultiple($query->execute());
    $output = array_values(array_filter($entities, function($entity) { return $entity->access('view'); }));
    return new Response(new DocumentContext($req, $output), 200);
  }

  protected function handleCollectionPOST($req) {
    $doc = $req->requestDocument();
    if (!$doc || is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "POST to a collection endpoint must contain a single resource");
    }

    $entity = $doc->data->toEntity();
    if (!is_null($entity->id())) {
      // http://jsonapi.org/format/#crud-creating-client-ids
      return $this->errorResponse(403, "Forbidden", "This server does not accept client-generated IDs");
    }

    if (!$entity->access('create')) {
      return $this->errorResponse(403, "Forbidden", "You are not authorized to create this entity.");
    }

    $req->storage()->save($entity);
    $doc->data = $entity;
    return new Response($doc, 201);
  }

  protected function handleRelationshipGET($req) {
    $entity = $req->loadEntity();

    if (!$entity->access('view')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }

    $fieldName = $req->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a known field");
    }

    $doc = new DocumentContext($req, $entity->get($fieldName));
    if (!$doc->data instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a relationship");
    }
    return new Response($doc, 200);
  }


  protected function handleRelationshipPOST($req) {
    $entity = $req->loadEntity();

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }

    $fieldName = $req->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a known field");
    }

    $list = $entity->get($fieldName);
    if (!$list instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a relationship");
    }

    if (!$req->isOneToManyRelationship()) {
      return $this->errorResponse(405, "Method Not Allowed", "You may not POST a one-to-one relationship endpoint, use PATCH");
    }

    $doc = $req->requestDocument();
    if (!$doc || !is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "POST to a one-to-many relationship endpoint must contain an array of resources");
    }

    $existingIds = [];
    foreach($list as $item) {
      $existingIds[$item->target_id] = true;
    }

    foreach($doc->data as $item) {
      if (!array_key_exists($item['target_id'], $existingIds)) {
        $list->appendItem($item['target_id']);
        $existingIds[$item['target_id']] = true;
      }
    }
    $req->storage()->save($entity);
    $doc->data = $list;
    return new Response($doc, 200);
  }

  protected function handleRelationshipDELETE($req) {
    $entity = $req->loadEntity();

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $req->entityType(), "where id=" . $req->id());
    }

    $fieldName = $req->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a known field");
    }

    $list = $entity->get($fieldName);
    if (!$list instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $req->related() . " is not a relationship");
    }

    if (!$req->isOneToManyRelationship()) {
      return $this->errorResponse(405, "Method Not Allowed", "You may not DELETE a one-to-one relationship endpoint, use PATCH");
    }

    $doc = $req->requestDocument();
    if (!$doc || !is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "DELeTE to a one-to-many relationship endpoint must contain an array of resources");
    }

    $idsToDelete = [];
    foreach($doc->data as $item) {
      $idsToDelete[$item['target_id']] = true;
    }

    $list->filter(function($elt) use ($idsToDelete){
      return !array_key_exists($elt->target_id, $idsToDelete);
    });

    $req->storage()->save($entity);
    $doc->data = $list;
    return new Response($doc, 200);
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
