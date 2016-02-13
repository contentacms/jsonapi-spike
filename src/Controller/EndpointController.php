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
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;


class EndpointController implements ContainerAwareInterface {

    use ContainerAwareTrait;

    public function __construct() {
        // unused?
        $this->config = new HardCodedConfig();
    }

    public function handleCollection(Request $request) {
        return $this->respondWith(new Response(["errors" => [["title" => "Not implemented", "detail" => "Collection endpoint not implemented."]]], 500));
    }

    public function handleIndividual(Request $request, $scope, $endpoint, $id) {
        return $this->respondWith($this->makeResponse($request, $scope, $endpoint, $id));
    }

    public function handleRelated(Request $request, $id, $related) {
        return $this->respondWith(new Response(["errors" => [["title" => "Not implemented", "detail" => "Related endpoint not implemented."]]], 500));
    }

    public function handleRelationship(Request $request, $id, $related) {
        return $this->respondWith(new Response(["errors" => [["title" => "Not implemented", "detail" => "Relationship endpoint not implemented."]]], 500));
    }

    protected function makeResponse($request, $scope, $endpoint, $id) {
        $config = $this->config->forEndpoint($scope, $endpoint);
        $entityType = $config['entryPoint']['entityType'];
        $entity_manager = \Drupal::entityManager();
        $entity = $entity_manager->getStorage($entityType)->load($id);

        if (!$entity) {
            # http://jsonapi.org/format/#error-objects
            return new Response(["errors" => [["title" => $entityType . " not found", "detail" => "where id=" . $id]]], 404);
        }


        if (!$entity->access('view')) {
            # http://jsonapi.org/format/#error-objects
            return new Response(["errors" => [["title" => "Access denied to " . $entityType, "detail" => "where id=" . $id . "."]]], 403);
        }
        return new Response(new DocumentContext($entity, $config, $this->optionsFor($request)), 200);
    }

    protected function respondWith($response) {
        if ($response instanceof Response && $data = $response->getResponseData()) {
            $serializer = $this->container->get('serializer');
            $output = $serializer->serialize($data, "jsonapi");
            $response->setContent($output);
            $response->headers->set('Content-Type', 'application/vnd.api+json');
        }
        return $response;
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