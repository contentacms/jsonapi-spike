<?php

/**
 * @file
 * Contains \Drupal\jsonapi\Normalizer\ContentEntityNormalizer.
 */

namespace Drupal\jsonapi\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\jsonapi\HardCodedConfig;
use Drupal\jsonapi\JsonApiEntityReference;

/**
 * Normalizes/denormalizes Drupal content entities into an array structure.
 */
class ContentEntityNormalizer extends NormalizerBase {
    public function __construct() {
        $this->config = new HardCodedConfig();
    }

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var array
     */
    protected $supportedInterfaceOrClass = ['Drupal\Core\Entity\ContentEntityInterface'];
    protected $format = array('jsonapi');

    protected function addMeta(&$record, $key, $value) {
        if (!$record['meta']) {
            $record['meta'] = [];
        }
        $record['meta'][$key] = $value;
    }

    protected function normalizeFields($object, $fields, $context) {
        $attributes = [];
        $relationships = [];
        $unused = [];

        foreach ($object as $name => $field) {
            if (!$field->access('view', $context['account'])) {
                continue;
            }

            if (isset($fields[$name])) {
                $innerContext = $context;
                $innerContext['jsonapi_path'][] = $name;
                $child = $this->serializer->normalize($field, $format, $innerContext);
                if ($child instanceof JsonApiEntityReference) {
                    $relationships[$fields[$name]["as"]] = $child->normalize();
                } else {
                    $attributes[$fields[$name]["as"]] = $child;
                }
            } else {
                $unused[] = $name;
            }
        }

        $record = ['attributes' => &$attributes];
        if (count($relationships) > 0) {
            $record['relationships'] = &$relationships;
        }

        if ($context['debug']) {
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

        $config = $this->config->configFor($object);


        $record = $this->normalizeFields($object, $config['fields'], $context);
        $record['id'] = $record['attributes']['id'];
        unset($record['attributes']['id']);
        $record['type'] = $config['type'];

        if ($context['debug']) {
            $this->addMeta($record, 'bundle', $object->bundle());
            $this->addMeta($record, 'entity-type', $object->getEntityTypeId());
        }


        if (count($context['jsonapi_path']) == 0) {
            return $record;
        } else {
            if (isset($context['jsonapi_document'])) {
                $doc = $context['jsonapi_document'];
                if ($doc->shouldInclude($context['jsonapi_path'])) {
                    $doc->addIncluded($record);
                }
            }
            return new JsonApiEntityReference($record);
        }

    }

}
