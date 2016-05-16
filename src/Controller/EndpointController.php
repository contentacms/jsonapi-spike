<?php

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

use Drupal\Core\Entity\EntityManagerInterface;

use Symfony\Component\Serializer\SerializerInterface;

class EndpointController implements ContainerInjectionInterface {

  /**
   * Constructs an EndpointController object.
   *
   * @param Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory service.
   * @param Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   */
  public function __construct(SerializerInterface $serializer, $entity_query, EntityManagerInterface $entity_manager, $logger_factory) {
    $this->config = new HardCodedConfig();
    $this->serializer = $serializer;
    $this->entityQuery = $entity_query;
    $this->entityManager = $entity_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('serializer'),
      $container->get('entity.query'),
      $container->get('entity.manager')
    );
  }

  /**
   * Wraps a handler call to catch errors and serve useful response.
   *
   * We don't want to let exceptions bubble out to the rest of Drupal, because
   * it will send back HTML, and we want to send back JSON.
   *
   * @param $fn
   *   A callable function which returns a response.
  */
  protected function withErrorTrap($fn) {
    try {
      return $fn();
    }
    catch (UnexpectedValueException $exception) {
      return $this->errorResponse(400, "Bad request", $exception->getMessage());
    }
    catch (\Exception $exception) {
      $message = sprintf(
        'Uncaught PHP Exception %s: "%s" at %s line %s',
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
      );

      $this->loggerFactory->get('jsonapi')->error($message);

      return $this->errorResponse(500, "Unexpected error", "See server logs");
    }
  }

  /**
   * Handles a request.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   A request object.
   */
  public function handle(Request $request, $_route, $scope, $endpoint, $id = NULL, $related = NULL) {
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
      }
      else {
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

  /**
   * Handles a GET request for a single resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleIndividualGet($request) {
    $entity = $request->loadEntity();

    if (!$entity) {
      return $this->errorResponse(
        404,
        $req->entityType() . " not found",
        "where id=" . $request->id()
      );
    }

    if (!$entity->access('view')) {
      return $this->errorResponse(
        403,
        "Access denied to " . $request->entityType(),
        "where id=" . $req->id()
      );
    }

    return new Response(new DocumentContext($request, $entity), 200);
  }

  /**
   * Handles a DELETE request for a single resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  protected function handleIndividualDelete($request) {
    $entity = $request->loadEntity();

    if (!$entity) {
      return $this->errorResponse(404, $request->entityType() . " not found", "where id=" . $request->id());
    }

    if (!$entity->access('delete')) {
      return $this->errorResponse(403, "Access denied to " . $request->entityType(), "where id=" . $request->id());
    }
    $request->storage()->delete([$entity]);
    return new SymfonyResponse(NULL, 204);
  }

  /**
   * Handles a PATCH request for a single resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleIndividualPatch($request) {
    $entity = $request->loadEntity();

    if (!$entity) {
      return $this->errorResponse(404, $request->entityType() . " not found", "where id=" . $request->id());
    }

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $request->entityType(), "where id=" . $request->id());
    }

    $doc = $request->requestDocument();
    if (!$doc || is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "PATCH to an individual endpoint must contain a single resource");
    }

    $drupalInputs = $doc->data->drupal;
    foreach ($entity as $name => $field) {
      // Ignore read-only fields instead of throwing a 403. This is
      // a nicer behavior for clients, and as far as I can tell it
      // is spec compliant. It's true that PATCH implies the
      // client's intent to change these fields, but the server is
      // also free to make its own arbitrary changes in response, as
      // long as they're reflected in the reply.
      if (array_key_exists($name, $drupalInputs) && $field->access('edit')) {
        $source = $doc->data->sources[$name];
        // we don't accept changes to 'type' or 'id'.
        if ($source != 'id' && $source != 'type') {
          $entity->set($name, $drupalInputs[$name]);
        }
      }
    }

    $request->storage()->save($entity);
    $doc->data = $entity;

    // TODO: the spec says we should return 200 if we changed anything
    // other than what the user sent, and 204 otherwise. (If the
    // "changed" field is exposed, that's an example of needing a
    // 200).
    return new Response($doc, 200);
  }

  /**
   * Handles a GET request for a collection resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleCollectionGet($request) {
    $query = $this->entityQuery->get($request->entityType());
    if (isset($request->config()['entryPoint']['bundles'])) {
      $bundles = $request->config()['entryPoint']['bundles'];
      if (count($bundles) > 0) {
        $entity_type = $this->entityManager->getDefinition($request->entityType(), FALSE);
        $query->condition($entity_type->getKey('bundle'), $bundles, 'IN');
      }
    }
    if (isset($request->options()['filter'])) {
      foreach($request->options()['filter'] as $name => $values) {
        $drupalFields = $request->jsonFieldToDrupalFields($name);
        if (count($drupalFields) == 0) {
          return $this->errorResponse(400, "Bad Request", "Can't filter on " . $name);
        }
        $group = $query->orConditionGroup();
        foreach ($drupalFields as $drupalField) {
          $group->condition($drupalField, $values, 'IN');
        }
        $query->condition($group);
      }
    }
    if (isset($request->options()['sort'])) {
      foreach($request->options()['sort'] as $name) {
        $direction = 'ASC';
        if (substr($name, 0, 1) == '-') {
          $direction = 'DESC';
          $name = substr($name, 1);
        }
        $drupalField = $request->jsonFieldToDrupalField(NULL, $name);
        if (!$drupalField) {
          return $this->errorResponse(400, "Bad Request", "Can't sort by " . $name);
        }
        $query->sort($drupalField, $direction);
      }
    }
    $entities = $request->storage()->loadMultiple($query->execute());
    $output = array_values(array_filter($entities, function($entity) { return $entity->access('view'); }));
    return new Response(new DocumentContext($request, $output), 200);
  }

  /**
   * Handles a POST request for a collection resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleCollectionPost($request) {
    $doc = $request->requestDocument();
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

    $request->storage()->save($entity);
    $doc->data = $entity;
    return new Response($doc, 201);
  }

  /**
   * Handles a GET request for a relationship resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleRelationshipGet($request) {
    $entity = $request->loadEntity();

    if (!$entity->access('view')) {
      return $this->errorResponse(403, "Access denied to " . $request->entityType(), "where id=" . $request->id());
    }

    // Special handling for tracing taxonomy term reverse entity
    // references, since they don't appear as fields on Term.
    if ($request->entityType() === 'taxonomy_term' && $request->related() === 'tagged-with') {
      $ids = db_query('SELECT nid FROM {taxonomy_index} WHERE tid = :tid', [
        ":tid" => $request->id()
      ])->fetchCol(0);
      $entities = $request->storageForType('node')->loadMultiple($ids);
      $doc = new DocumentContext($request, array_values($entities));
      return new Response($doc, 200);
    }


    $fieldName = $request->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a known field");
    }

    $doc = new DocumentContext($request, $entity->get($fieldName));
    if (!$doc->data instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a relationship");
    }
    return new Response($doc, 200);
  }

  /**
   * Handles a POST request for a relationship resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleRelationshipPost($request) {
    $entity = $request->loadEntity();

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $request->entityType(), "where id=" . $request->id());
    }

    $fieldName = $request->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a known field");
    }

    $list = $entity->get($fieldName);
    if (!$list instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a relationship");
    }

    if (!$request->isOneToManyRelationship()) {
      return $this->errorResponse(405, "Method Not Allowed", "You may not POST a one-to-one relationship endpoint, use PATCH");
    }

    $doc = $request->requestDocument();
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
    $request->storage()->save($entity);
    $doc->data = $list;
    return new Response($doc, 200);
  }

  /**
   * Handles a DELETE request for a relationship resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   */
  protected function handleRelationshipDelete($request) {
    $entity = $request->loadEntity();

    if (!$entity->access('edit')) {
      return $this->errorResponse(403, "Access denied to " . $request->entityType(), "where id=" . $request->id());
    }

    $fieldName = $request->relatedAsDrupalField();
    if (!$fieldName) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a known field");
    }

    $list = $entity->get($fieldName);
    if (!$list instanceof EntityReferenceFieldItemList) {
      return $this->errorResponse(400, "Bad Request", $request->related() . " is not a relationship");
    }

    if (!$request->isOneToManyRelationship()) {
      return $this->errorResponse(405, "Method Not Allowed", "You may not DELETE a one-to-one relationship endpoint, use PATCH");
    }

    $doc = $request->requestDocument();
    if (!$doc || !is_array($doc->data)) {
      return $this->errorResponse(400, "Bad Request", "DELETE to a one-to-many relationship endpoint must contain an array of resources");
    }

    $idsToDelete = [];
    foreach($doc->data as $item) {
      $idsToDelete[$item['target_id']] = true;
    }

    $list->filter(function($elt) use ($idsToDelete){
      return !array_key_exists($elt->target_id, $idsToDelete);
    });

    $request->storage()->save($entity);
    $doc->data = $list;
    return new Response($doc, 200);
  }

  /**
   * Handles a GET request for a related resource.
   *
   * @param Drupal\jsonapi\RequestContext $request
   *   The request context.
   *
   * @return Drupal\jsonapi\Response
   *   The response.
   *
   * @see Drupal\jsonapi\Controller\EndpointController::handleRelationshipGet()
   */
  protected function handleRelatedGet($request) {
    // behaves exactly the same, except the normalizers will know to
    // include full records instead of just references to records
    // based on the request type being "related" and not
    // "relationships"
    return $this->handleRelationshipGet($request);
  }

  /**
   * Creates an error response object.
   *
   * @param string $status_code
   *   The status code for the response.
   * @param string $title
   *   The title of the response.
   * @param string $detail
   *   The details of the error.
   *
   * @return Symfony\Component\HttpFoundation\Response
   */
  protected function errorResponse($statusCode, $title, $detail) {
    // @see: http://jsonapi.org/format/#error-objects
    $body = Json::encode([
      "errors" => [[
        "title" => $title,
        "detail" => $detail
      ]]]);

    // This is a SymfonyResponse and not our own Response type so that
    // it's independent of our serialization step (since errors can
    // happen during that step itself)
    return new SymfonyResponse(
      $body,
      $statusCode,
      ["Content-Type" => 'application/vnd.api+json']
    );
  }

}
